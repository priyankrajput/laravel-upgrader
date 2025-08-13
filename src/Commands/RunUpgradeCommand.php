<?php

namespace priyank\LaravelUpgrader\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use priyank\LaravelUpgrader\Services\PackageVersionService;

class RunUpgradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upgrader:run {--packages=* : Specific packages to upgrade}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the package upgrade process';

    /**
     * The package version service.
     *
     * @var PackageVersionService
     */
    protected $packageService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(PackageVersionService $packageService)
    {
        parent::__construct();
        $this->packageService = $packageService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting package upgrade process...');
        
        // Get the packages to upgrade
        $packagesToUpgrade = $this->option('packages');
        
        if (empty($packagesToUpgrade)) {
            $this->info('No specific packages provided. Checking all packages for updates...');
            
            $composer = json_decode(file_get_contents(base_path('composer.json')), true);
            $allPackages = array_merge(
                $composer['require'] ?? [],
                $composer['require-dev'] ?? []
            );
            unset($allPackages['php']);
            
            $currentVersions = $this->packageService->getCurrentVersions();
            $updates = $this->packageService->checkForUpdates($currentVersions, $allPackages);
            
            $packagesToUpgrade = [];
            foreach ($updates as $package => $data) {
                if ($data['has_update']) {
                    $packagesToUpgrade[] = $package;
                }
            }
            
            if (empty($packagesToUpgrade)) {
                $this->info('No updates available.');
                return 0;
            }
            
            $this->info('Found ' . count($packagesToUpgrade) . ' packages to update.');
            
            if (!$this->confirm('Do you want to continue with the upgrade?', true)) {
                $this->info('Upgrade cancelled.');
                return 0;
            }
        }
        
        // Run composer update
        $this->info('Running composer update...');
        $logFile = storage_path('logs/upgrade.log');
        
        // Clear previous log
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        
        // Run composer update for the selected packages
        $packagesList = implode(' ', $packagesToUpgrade);
        $command = "composer update $packagesList --with-all-dependencies --no-interaction --no-progress > " . escapeshellarg($logFile) . " 2>&1";
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B " . $command . "", "r"));
        } else {
            exec("nohup $command > /dev/null 2>&1 &");
        }
        
        $this->info('Upgrade process started in the background.');
        $this->info("You can monitor the progress with: tail -f " . $logFile);
        
        return 0;
    }
}
