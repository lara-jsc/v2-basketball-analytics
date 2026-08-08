<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Python Analytics Engine
     |--------------------------------------------------------------------------
     | The engine is invoked exclusively via Laravel Jobs (subprocess, never HTTP).
     | Input is written to stdin as JSON; output is read from stdout as JSON.
     */
    'python_bin' => env('PYTHON_BIN', 'python3'),
    'python_engine_path' => env('PYTHON_ENGINE_PATH', 'analytics/engine.py'),
];
