<?php

namespace App\Services;

use App\Models\Backend;
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
        } catch (Exception $e) {
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
        $fullCommand = 'cd '.escapeshellarg($workingDir).' && git pull '.escapeshellarg($repoUrl).' main 2>&1';

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

    protected function getMigrateCommand(string $repoFullName): string
    {
        $backend = Backend::where('repo', $repoFullName)->first();

        if ($backend && ! empty($backend->migrate_command)) {
            return $backend->migrate_command;
        }

        return 'migrate --force';
    }

    protected function getDeployScript(string $repoFullName): string
    {
        $backend = Backend::where('repo', $repoFullName)->first();

        if ($backend && ! empty($backend->script)) {
            return $backend->script;
        }

        $templatePath = base_path('scripts/deploy-backend.sh');

        if (! is_file($templatePath)) {
            throw new \RuntimeException(
                "Deploy template missing: {$templatePath} (required by backend {$repoFullName})"
            );
        }

        return (string) file_get_contents($templatePath);
    }

    private function executeArtisanCommands(string $backendPath, string $repoFullName, string $repoPath): array
    {
        Log::info('Executing deploy script for backend', ['path' => $backendPath]);

        $githubUser = $this->githubUser;
        $githubToken = $this->githubToken;
        $migrateCommand = $this->getMigrateCommand($repoFullName);

        Log::info("Using migrate command for {$repoFullName}", ['command' => $migrateCommand]);

        $escapedMigrateCommand = escapeshellarg($migrateCommand);

        $scriptContent = $this->getDeployScript($repoFullName);
        $tempFile = tempnam(sys_get_temp_dir(), 'deploy-');
        file_put_contents($tempFile, $scriptContent);
        chmod($tempFile, 0755);

        try {
            $command = 'bash '.escapeshellarg($tempFile).' '
                .escapeshellarg($backendPath).' '
                .escapeshellarg($githubUser).' '
                .escapeshellarg($githubToken).' '
                .escapeshellarg($repoFullName).' '
                .escapeshellarg($repoPath).' '
                .$escapedMigrateCommand.' 2>&1';

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
        } finally {
            @unlink($tempFile);
        }
    }
}
