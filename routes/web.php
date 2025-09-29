<?php

use Illuminate\Support\Facades\Route;
use priyankrajput\LaravelUpgrader\Http\Controllers\UpgradeController;
use priyankrajput\LaravelUpgrader\Http\Controllers\UpgradeLogController;

$config = config('upgrader.route', []);

Route::group(['prefix' => config('upgrader.route.prefix', 'upgrade')], function () {
    Route::get('/', [UpgradeController::class, 'index'])->name('upgrader.index');
    Route::post('/run', [UpgradeController::class, 'upgrade'])->name('upgrader.run');
    Route::post('/clear-cache', [UpgradeController::class, 'clearCache'])->name('upgrader.clear-cache');
    Route::post('/auto-fix', [UpgradeController::class, 'autoFix'])->name('upgrader.auto-fix');
    Route::get('/log', [UpgradeLogController::class, 'show'])->name('upgrader.log');
    Route::get('/backups', [UpgradeController::class, 'backups'])->name('upgrader.backups');
    Route::post('/restore', [UpgradeController::class, 'restore'])->name('upgrader.restore');
    Route::delete('/backup', [UpgradeController::class, 'deleteBackup'])->name('upgrader.delete-backup');
});
