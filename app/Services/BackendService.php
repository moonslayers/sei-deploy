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

    public function updateBackend(string $repoFullName, string $repoPath, ?string $backendPath = null): array
    {
        $backendPath = $backendPath ?? $repoPath;

        Log::info("Starting backend update for {$repoFullName}", [
            'repo_path' => $repoPath,
            'backend_path' => $backendPath,
        ]);

        $results = [];

        try {
            $results['git_pull'] = $this->pullBackend($repoFullName, $repoPath);
            $results['artisan_commands'] = $this->executeArtisanCommands($backendPath, $repoFullName, $repoPath);

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

    private function pullBackend(string $repoFullName, string $repoPath): array
    {
        Log::info("Starting git pull for backend {$repoFullName}");

        $repoUrl = $this->getRepoUrlWithCredentials($repoFullName);
        $workingDir = $repoPath;
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

    private function executeArtisanCommands(string $backendPath, string $repoFullName, string $repoPath): array
    {
        Log::info("Executing deploy script for backend", ['path' => $backendPath]);

        $scriptPath = base_path('scripts/deploy-backend.sh');
        $githubUser = $this->githubUser;
        $githubToken = $this->githubToken;

        $command = "bash {$scriptPath} {$backendPath} {$githubUser} {$githubToken} {$repoFullName} {$repoPath} 2>&1";

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error('Deploy script failed', [
                'output' => $errorOutput,
                'return_code' => $returnCode,
            ]);
            throw new Exception("Deploy script failed: {$errorOutput}");
        }

        Log::info('Deploy script completed successfully', ['output' => $output]);

        return [
            'status' => 'success',
            'output' => $output,
        ];
    }
}
