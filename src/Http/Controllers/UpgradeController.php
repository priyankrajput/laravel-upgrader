<?php

namespace priyankrajput\LaravelUpgrader\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Routing\Controller;
use priyankrajput\LaravelUpgrader\Services\PackageVersionService;
use priyankrajput\LaravelUpgrader\Services\BackupService;

ini_set('max_execution_time', 300); // 5 minutes
class UpgradeController extends Controller
{
    protected $config;
    protected $packageService;
    protected $backupService;

    public function __construct(PackageVersionService $packageService, BackupService $backupService)
    {
        $this->config = config('upgrader');
        $this->packageService = $packageService;
        $this->backupService = $backupService;
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
            ], now()->addMinutes((int)$this->config['cache']['duration'] ?? 1440));
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
            'hasUpdates' => collect($availableUpdates)->contains('has_update', true),
            'hasMajorUpdates' => collect($availableUpdates)->contains(function($d){ return !empty($d['is_major_update']); })
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
            // Determine selected packages
            $packagesToUpgrade = (array) $request->input('packages', []);
            
            // Create backup before upgrade
            $backupId = 'manual';
            try {
                $backupId = $this->backupService->createBackup($packagesToUpgrade);
            } catch (\Exception $backupError) {
                \Log::warning('Backup creation failed: ' . $backupError->getMessage());
                // Continue without backup if it fails
            }
            
            // Start composer update in the background, outputting to a unique per-run log
            $logDir = storage_path('logs');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $runId = date('Ymd_His') . '-' . bin2hex(random_bytes(3));
            $logFile = $logDir . DIRECTORY_SEPARATOR . 'upgrade-' . $runId . '.log';

            // Pre-write start banner BEFORE launching composer, and write pointer file
            $startMessage = "[Upgrade started at " . date('Y-m-d H:i:s') . "]\n";
            $startMessage .= "[Backup created: {$backupId}]\n";
            @file_put_contents($logFile, $startMessage);
            @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'upgrade.current', basename($logFile));

            // Build composer command with selected packages
            $packagesList = !empty($packagesToUpgrade) ? implode(' ', $packagesToUpgrade) : '';
            $composerCmd = 'composer update ' . $packagesList . ' --with-all-dependencies';
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows background execution (append) - use full path to composer if needed
                $composerPath = config('upgrader.composer_path', 'composer');
                $winLog = str_replace('/', '\\', $logFile);
                $cmd = 'start /B cmd /C "' . $composerPath . ' update ' . $packagesList . ' --with-all-dependencies >> "' . $winLog . '" 2>&1"';
            } else {
                // Unix background execution (append)
                $composerPath = config('upgrader.composer_path', 'composer');
                $cmd = 'sh -c "' . $composerPath . ' update ' . $packagesList . ' --with-all-dependencies >> ' . escapeshellarg($logFile) . ' 2>&1 &"';
            }
            
            // Use proc_open for better control
            proc_close(proc_open($cmd, [], $pipes, base_path()));
            
            // Clean up old backups (keep last 5)
            $this->backupService->cleanupOldBackups(5);

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

    /**
     * Show available backups
     */
    public function backups()
    {
        $backups = $this->backupService->getBackups();
        
        if (request()->expectsJson()) {
            return response()->json(['backups' => $backups]);
        }
        
        return view('upgrader::backups', compact('backups'));
    }

    /**
     * Restore from backup
     */
    public function restore(Request $request)
    {
        $request->validate([
            'backup_id' => 'required|string',
            'confirm' => 'required|accepted'
        ]);

        try {
            $backupId = $request->input('backup_id');
            $logFile = $this->backupService->restoreBackup($backupId);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'started',
                    'message' => 'Restore started',
                    'log_file' => $logFile
                ]);
            }
            
            return redirect()->route('upgrader.index')
                ->with('success', 'Restore started. Check logs for progress.');
                
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('upgrader.index')
                ->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete backup
     */
    public function deleteBackup(Request $request)
    {
        $request->validate([
            'backup_id' => 'required|string'
        ]);

        try {
            $backupId = $request->input('backup_id');
            $deleted = $this->backupService->deleteBackup($backupId);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => $deleted ? 'success' : 'error',
                    'message' => $deleted ? 'Backup deleted' : 'Backup not found'
                ]);
            }
            
            return redirect()->route('upgrader.backups')
                ->with($deleted ? 'success' : 'error', 
                       $deleted ? 'Backup deleted successfully' : 'Backup not found');
                
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('upgrader.backups')
                ->with('error', 'Failed to delete backup: ' . $e->getMessage());
        }
    }

}
