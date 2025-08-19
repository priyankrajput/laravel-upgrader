<?php

namespace priyankrajput\LaravelUpgrader\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Routing\Controller;

class UpgradeLogController extends Controller
{
    protected $logPath;

    public function __construct()
    {
        $this->logPath = config('upgrader.logging.path', storage_path('logs/upgrade.log'));
    }

    public function show()
    {
        if (!File::exists($this->logPath)) {
            return response('', 204);
        }

        $content = File::get($this->logPath);
        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
