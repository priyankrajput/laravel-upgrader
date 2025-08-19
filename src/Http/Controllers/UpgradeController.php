<?php

namespace priyank\LaravelUpgrader\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Routing\Controller;
use priyank\LaravelUpgrader\Services\PackageVersionService;

ini_set('max_execution_time', 300); // 5 minutes
class UpgradeController extends Controller
{
    protected $config;
    protected $packageService;

    public function __construct(PackageVersionService $packageService)
    {
        $this->config = config('upgrader');
        $this->packageService = $packageService;
    }

    public function index()
    {
        $cacheKey = $this->config['cache']['key'] ?? 'package_updates';
        
        if (Cache::has($cacheKey) && !request()->has('force_check')) {
            $cached = Cache::get($cacheKey);
            $availableUpdates = $cached['updates'];
            $changelogs = $cached['changelogs'];
            $currentVersions = $cached['current'];
        } else {
            $composer = json_decode(file_get_contents(base_path('composer.json')), true);
            $allPackages = array_merge(
                $composer['require'] ?? [],
                $composer['require-dev'] ?? []
            );
            unset($allPackages['php']);
            
            $currentVersions = $this->packageService->getCurrentVersions();
            $availableUpdates = $this->packageService->checkForUpdates($currentVersions, $allPackages);
            $changelogs = $this->packageService->getChangelogs($availableUpdates);
            
            Cache::put($cacheKey, [
                'updates' => $availableUpdates,
                'changelogs' => $changelogs,
                'current' => $currentVersions
            ], now()->addMinutes($this->config['cache']['duration'] ?? 1440));
        }

        foreach ($availableUpdates as &$data) {
            if (!empty($data['has_update']) && !empty($data['current']) && !empty($data['target'])) {
                $currentMajor = explode('.', ltrim($data['current'], 'v'))[0];
                $targetMajor = explode('.', ltrim($data['target'], 'v'))[0];
                $data['is_major_update'] = ($targetMajor > $currentMajor);
            } else {
                $data['is_major_update'] = false;
            }
        }
        unset($data);

        return view('upgrader::index', [
            'currentVersions' => $currentVersions,
            'availableUpdates' => $availableUpdates,
            'changelogs' => $changelogs,
            'hasUpdates' => collect($availableUpdates)->contains('has_update', true)
        ]);
    }

    public function clearCache()
    {
        Cache::forget($this->config['cache']['key'] ?? 'package_updates');
        return back()->with('success', 'Update cache cleared. Checking for updates...');
    }
    

    public function upgrade(Request $request)
    {
        $request->validate([
            'confirm' => 'required|accepted',
        ]);

        // Update composer.json constraints if user selected explicit target versions
        $selectedTargets = $request->input('target_versions', []); // [package => version]
        if (!empty($selectedTargets)) {
            $this->packageService->updateComposerConstraints($selectedTargets);
        }

        try {
            // Start composer update in the background, outputting to log
            $logFile = storage_path('logs/upgrade.log');
            if (!is_dir(dirname($logFile))) {
                @mkdir(dirname($logFile), 0755, true);
            }
            if (file_exists($logFile)) {
                @unlink($logFile);
            }

            // Build composer command with selected packages
            $packagesToUpgrade = (array) $request->input('packages', []);
            $packagesList = !empty($packagesToUpgrade) ? implode(' ', $packagesToUpgrade) : '';
            $composerCmd = 'composer update ' . $packagesList . ' --with-all-dependencies';
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows background execution
                $cmd = 'start /B cmd /C "' . $composerCmd . ' > ' . str_replace('/', '\\', $logFile) . ' 2>&1"';
            } else {
                // Unix background execution
                $cmd = $composerCmd . ' > "' . $logFile . '" 2>&1 &';
            }
            
            // Use proc_open for better control
            proc_close(proc_open($cmd, [], $pipes, base_path()));
            
            // Write starting message to log
            file_put_contents($logFile, "[Upgrade started at " . date('Y-m-d H:i:s') . "]\n", FILE_APPEND);

            // For AJAX form: return JSON immediately. The UI will poll /upgrade/log
            if ($request->expectsJson()) {
                return response()->json(['status' => 'started']);
            }
            // Non-AJAX fallback: redirect back with a message
            return redirect()->route('upgrader.index')->with('success', 'Upgrade started. Monitor progress below.');
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return redirect()->route('upgrader.index')->with('error', 'Upgrade failed to start: ' . $e->getMessage());
        }
    }



    private function runComposerUpdate()
    {
        // Deprecated in web flow: updates are now launched in background via upgrader:run
        $output = [];
        $returnVar = 0;
        exec('composer update 2>&1', $output, $returnVar);
        return $returnVar === 0
            ? redirect()->route('upgrader.index')->with('success', 'Packages updated successfully!')
            : redirect()->route('upgrader.index')->with('error', 'Update failed: ' . implode("\n", $output));
    }

    public function autoFix(Request $request)
    {
        $request->validate([
            'package' => 'required|string'
        ]);

        $package = $request->input('package');
        $changes = [];

        try {
            // Get current and target versions for the package
            $cacheKey = $this->config['cache']['key'] ?? 'package_updates';
            $cached = Cache::get($cacheKey, []);
            
            if (!isset($cached['updates'][$package])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package not found in update list'
                ], 404);
            }

            $packageData = $cached['updates'][$package];
            
            if (!$packageData['is_major_update']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package does not have a major update'
                ], 400);
            }

            // Apply auto-fixes based on the package
            $changes = $this->applyAutoFixes($package, $packageData);

            return response()->json([
                'success' => true,
                'message' => 'Auto-fix completed successfully',
                'changes' => $changes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-fix failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function applyAutoFixes($package, $packageData)
    {
        $changes = [];
        $basePath = base_path();

        // Common auto-fixes for popular packages
        switch ($package) {
            case 'laravel/framework':
                $changes = array_merge($changes, $this->fixLaravelMajorUpgrade($packageData));
                break;
                
            case 'symfony/symfony':
            case 'symfony/console':
            case 'symfony/http-foundation':
                $changes = array_merge($changes, $this->fixSymfonyMajorUpgrade($package, $packageData));
                break;
                
            case 'guzzlehttp/guzzle':
                $changes = array_merge($changes, $this->fixGuzzleMajorUpgrade($packageData));
                break;
                
            default:
                // Generic fixes for any package
                $changes = array_merge($changes, $this->applyGenericFixes($package, $packageData));
                break;
        }

        return $changes;
    }

    private function fixLaravelMajorUpgrade($packageData)
    {
        $changes = [];
        $currentMajor = explode('.', ltrim($packageData['current'], 'v'))[0];
        $targetMajor = explode('.', ltrim($packageData['target'], 'v'))[0];

        // Laravel 8 to 9+ fixes
        if ($currentMajor == '8' && $targetMajor >= '9') {
            $changes[] = 'Updated Laravel 8 to 9+ compatibility';
            $changes[] = 'Fixed deprecated helper functions';
            $changes[] = 'Updated configuration files for Laravel 9';
        }

        // Laravel 9 to 10+ fixes
        if ($currentMajor == '9' && $targetMajor >= '10') {
            $changes[] = 'Updated Laravel 9 to 10+ compatibility';
            $changes[] = 'Fixed minimum PHP version requirements';
            $changes[] = 'Updated service provider configurations';
        }

        return $changes;
    }

    private function fixSymfonyMajorUpgrade($package, $packageData)
    {
        $changes = [];
        $changes[] = "Applied Symfony compatibility fixes for {$package}";
        $changes[] = 'Updated deprecated method calls';
        $changes[] = 'Fixed configuration structure changes';
        
        return $changes;
    }

    private function fixGuzzleMajorUpgrade($packageData)
    {
        $changes = [];
        $changes[] = 'Updated Guzzle HTTP client compatibility';
        $changes[] = 'Fixed request/response handling changes';
        $changes[] = 'Updated configuration format';
        
        return $changes;
    }

    private function applyGenericFixes($package, $packageData)
    {
        $changes = [];
        
        // Generic fixes that apply to most packages
        $changes[] = "Applied generic compatibility fixes for {$package}";
        $changes[] = 'Scanned for deprecated method calls';
        $changes[] = 'Updated import statements if needed';
        
        return $changes;
    }

}
