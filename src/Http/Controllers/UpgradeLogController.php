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
        // Prefer current per-run log if pointer exists
        $logsDir = storage_path('logs');
        $pointer = $logsDir . DIRECTORY_SEPARATOR . 'upgrade.current';
        $activeLog = null;
        if (File::exists($pointer)) {
            $basename = trim(@File::get($pointer));
            if ($basename) {
                $candidate = $logsDir . DIRECTORY_SEPARATOR . $basename;
                if (File::exists($candidate)) {
                    $activeLog = $candidate;
                }
            }
        }

        $path = $activeLog ?: $this->logPath;
        if (!File::exists($path)) {
            return response('', 204);
        }

        $content = @File::get($path) ?: '';
        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
