<?php

use Illuminate\Support\Facades\Route;

$config = config('upgrader.route', []);

Route::group(['prefix' => config('upgrader.route.prefix', 'upgrade')], function () {
    Route::get('/', 'UpgradeController@index')->name('upgrader.index');
    Route::post('/run', 'UpgradeController@upgrade')->name('upgrader.run');
    Route::post('/clear-cache', 'UpgradeController@clearCache')->name('upgrader.clear-cache');
    Route::post('/auto-fix', 'UpgradeController@autoFix')->name('upgrader.auto-fix');
    Route::get('/log', 'UpgradeLogController@show')->name('upgrader.log');
    Route::get('/backups', 'UpgradeController@backups')->name('upgrader.backups');
    Route::post('/restore', 'UpgradeController@restore')->name('upgrader.restore');
    Route::delete('/backup', 'UpgradeController@deleteBackup')->name('upgrader.delete-backup');
});
