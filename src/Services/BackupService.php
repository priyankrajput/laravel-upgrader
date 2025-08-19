<?php

namespace priyankrajput\LaravelUpgrader\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupService
{
    protected $backupPath;

    public function __construct()
    {
        $this->backupPath = storage_path('app/upgrader-backups');
        if (!is_dir($this->backupPath)) {
            @mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Create backup before upgrade
     */
    public function createBackup($packages = [])
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupId = 'backup_' . $timestamp;
        $backupDir = $this->backupPath . '/' . $backupId;
        
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Backup composer files
        $this->backupComposerFiles($backupDir);
        
        // Create backup metadata
        $metadata = [
            'id' => $backupId,
            'timestamp' => $timestamp,
            'packages' => $packages,
            'created_at' => Carbon::now()->toISOString(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ];
        
        file_put_contents($backupDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        
        return $backupId;
    }

    /**
     * Backup composer.json and composer.lock
     */
    protected function backupComposerFiles($backupDir)
    {
        $composerJson = base_path('composer.json');
        $composerLock = base_path('composer.lock');
        
        if (file_exists($composerJson)) {
            copy($composerJson, $backupDir . '/composer.json');
        }
        
        if (file_exists($composerLock)) {
            copy($composerLock, $backupDir . '/composer.lock');
        }
    }

    /**
     * Restore from backup
     */
    public function restoreBackup($backupId)
    {
        $backupDir = $this->backupPath . '/' . $backupId;
        
        if (!is_dir($backupDir)) {
            throw new \Exception("Backup not found: {$backupId}");
        }

        // Restore composer files
        $composerJson = $backupDir . '/composer.json';
        $composerLock = $backupDir . '/composer.lock';
        
        if (file_exists($composerJson)) {
            copy($composerJson, base_path('composer.json'));
        }
        
        if (file_exists($composerLock)) {
            copy($composerLock, base_path('composer.lock'));
        }

        // Run composer install to restore exact versions
        $logFile = storage_path('logs/restore.log');
        if (file_exists($logFile)) {
            @unlink($logFile);
        }

        $cmd = 'composer install --no-dev';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'start /B cmd /C "' . $cmd . ' > ' . str_replace('/', '\\', $logFile) . ' 2>&1"';
        } else {
            $cmd = $cmd . ' > "' . $logFile . '" 2>&1 &';
        }
        
        proc_close(proc_open($cmd, [], $pipes, base_path()));
        
        return $logFile;
    }

    /**
     * Get all available backups
     */
    public function getBackups()
    {
        $backups = [];
        
        if (!is_dir($this->backupPath)) {
            return $backups;
        }
        
        $dirs = glob($this->backupPath . '/backup_*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $metadataFile = $dir . '/metadata.json';
            if (file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                $backups[] = $metadata;
            }
        }
        
        // Sort by timestamp descending (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }

    /**
     * Delete old backups (keep last 5)
     */
    public function cleanupOldBackups($keep = 5)
    {
        $backups = $this->getBackups();
        
        if (count($backups) > $keep) {
            $toDelete = array_slice($backups, $keep);
            
            foreach ($toDelete as $backup) {
                $backupDir = $this->backupPath . '/' . $backup['id'];
                if (is_dir($backupDir)) {
                    $this->deleteDirectory($backupDir);
                }
            }
        }
    }

    /**
     * Delete a directory recursively
     */
    protected function deleteDirectory($dir)
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    /**
     * Delete specific backup
     */
    public function deleteBackup($backupId)
    {
        $backupDir = $this->backupPath . '/' . $backupId;
        if (is_dir($backupDir)) {
            $this->deleteDirectory($backupDir);
            return true;
        }
        return false;
    }
}
