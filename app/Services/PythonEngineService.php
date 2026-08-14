<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Calls the Python analytics engine via subprocess.
 *
 * Protocol:
 *   - Writes JSON { command, payload } to stdin.
 *   - Reads JSON result from stdout.
 *   - Throws RuntimeException on non-zero exit or engine-returned error.
 *
 * Never called directly from controllers — always dispatched through Jobs.
 */
class PythonEngineService
{
    private string $bin;

    private string $enginePath;

    public function __construct()
    {
        $this->bin = (string) config('analytics.python_bin', 'python3');
        $this->enginePath = base_path((string) config('analytics.python_engine_path', 'analytics/engine.py'));
    }

    /**
     * Dispatch a command to the Python engine and return the decoded result.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RuntimeException on subprocess error or engine-returned error key
     */
    public function call(string $command, array $payload): array
    {
        $input = json_encode(['command' => $command, 'payload' => $payload], JSON_THROW_ON_ERROR);

        $result = Process::input($input)->run([$this->bin, $this->enginePath]);

        if ($result->failed()) {
            Log::error('Python engine subprocess failed', [
                'command' => $command,
                'exitCode' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
            ]);

            throw new RuntimeException(
                "Python engine failed for command '{$command}': ".$result->errorOutput()
            );
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($result->output(), associative: true);

        if ($decoded === null) {
            throw new RuntimeException("Python engine returned non-JSON output for command '{$command}'.");
        }

        if (isset($decoded['error'])) {
            throw new RuntimeException("Python engine error in '{$command}': {$decoded['error']}");
        }

        return $decoded;
    }
}
