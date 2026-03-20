<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class BackendService
{
    private string $githubUser;

    private string $githubToken;

    private string $gitUserName;

    private string $gitUserEmail;

    public function __construct()
    {
        $this->githubUser = env('GITHUB_USER', 'GH USER NO DEFINIDO');
        $this->githubToken = env('GITHUB_TOKEN', 'GH TOKEN NO DEFINIDO');
        $this->gitUserName = env('GIT_USER_NAME', 'Deploy Bot');
        $this->gitUserEmail = env('GIT_USER_EMAIL', 'deploy@server.com');
    }

    public function updateBackend(string $repoFullName, string $backendPath): array
    {
        Log::info("Starting backend update for {$repoFullName}", [
            'path' => $backendPath,
        ]);

        $results = [];

        try {
            $results['git_pull'] = $this->pullBackend($repoFullName, $backendPath);
            $results['artisan_commands'] = $this->executeArtisanCommands($backendPath);

            Log::info("Backend update completed successfully for {$repoFullName}");

            return [
                'status' => 'success',
                'message' => 'Backend updated successfully',
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to update backend {$repoFullName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to update backend',
                'error' => $e->getMessage(),
                'results' => $results,
            ];
        }
    }

    private function pullBackend(string $repoFullName, string $backendPath): array
    {
        Log::info("Starting git pull for backend {$repoFullName}");

        $repoUrl = $this->getRepoUrlWithCredentials($repoFullName);
        $workingDir = $backendPath;
        $fullCommand = "cd {$workingDir} && git pull {$repoUrl} main 2>&1";

        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error('Git pull failed for backend', [
                'backend' => $repoFullName,
                'output' => $errorOutput,
                'return_code' => $returnCode,
            ]);

            throw new Exception("Git pull failed for {$repoFullName}: {$errorOutput}");
        }

        Log::info("Git pull completed for backend {$repoFullName}");

        return [
            'status' => 'success',
            'output' => $output,
        ];
    }

    private function getRepoUrlWithCredentials(string $repo): string
    {
        if (empty($this->githubUser) || empty($this->githubToken)) {
            throw new Exception('GitHub credentials not configured');
        }

        return "https://{$this->githubUser}:{$this->githubToken}@github.com/{$repo}.git";
    }

    private function executeArtisanCommands(string $backendPath): array
    {
        Log::info("Executing artisan commands for backend", ['path' => $backendPath]);

        $results = [];

        $commands = [
            'cache_clear' => 'php artisan cache:clear',
            'config_clear' => 'php artisan config:clear',
            'route_clear' => 'php artisan route:clear',
            'config_cache' => 'php artisan config:cache',
            'route_cache' => 'php artisan route:cache',
            'migrate' => 'php artisan migrate --force',
            'queue' => 'php artisan queue:restart',
            'sleep' => 'sleep 10',
        ];

        foreach ($commands as $key => $command) {
            $results[$key] = $this->executeCommand($command, $backendPath, $key);
        }

        Log::info('All artisan commands completed successfully');

        return $results;
    }

    private function executeCommand(string $command, string $backendPath, string $commandKey): array
    {
        $workingDir = $backendPath;
        $fullCommand = "cd {$workingDir} && {$command} 2>&1";

        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error('Artisan command failed', [
                'command' => $command,
                'command_key' => $commandKey,
                'output' => $errorOutput,
                'return_code' => $returnCode,
            ]);

            throw new Exception("Command failed: {$command}. Error: {$errorOutput}");
        }

        Log::info("Command executed successfully", [
            'command_key' => $commandKey,
            'command' => $command,
        ]);

        return [
            'status' => 'success',
            'output' => $output,
        ];
    }
}
