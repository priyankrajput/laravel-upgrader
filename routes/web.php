<?php

use Illuminate\Support\Facades\Route;
use priyank\LaravelUpgrader\Http\Controllers\UpgradeController;
use priyank\LaravelUpgrader\Http\Controllers\UpgradeLogController;

$config = config('upgrader.route', []);

Route::prefix($config['prefix'] ?? 'upgrade')
    // ->middleware($config['middleware'] ?? ['web'])
    ->group(function () {
        Route::get('/', [UpgradeController::class, 'index'])->name('upgrader.index');
        Route::post('/run', [UpgradeController::class, 'upgrade'])->name('upgrader.run');
        Route::post('/clear-cache', [UpgradeController::class, 'clearCache'])->name('upgrader.clear-cache');
        Route::post('/auto-fix', [UpgradeController::class, 'autoFix'])->name('upgrader.auto-fix');
        Route::get('/log', [UpgradeLogController::class, 'show'])->name('upgrader.log');
    });
