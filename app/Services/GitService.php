<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GitService
{
    private string $githubUser;
    private string $githubToken;
    private string $baseDir;

    public function __construct()
    {
        $this->githubUser = env('GITHUB_USER', '');
        $this->githubToken = env('GITHUB_TOKEN', '');
        $this->baseDir = base_path();
    }

    private function configureGitCredentials(): void
    {
        if (empty($this->githubUser) || empty($this->githubToken)) {
            throw new Exception('GitHub credentials not configured');
        }

        $this->executeGitCommand("config credential.helper store");
        $this->executeGitCommand("config --global credential.helper store");
        $this->executeGitCommand("config --global url.\"https://{$this->githubUser}:{$this->githubToken}@github.com/\".insteadOf \"https://github.com/\"");
    }

    private function executeGitCommand(string $command, string $path = null): array
    {
        $workingDir = $path ?? $this->baseDir;
        $fullCommand = "cd {$workingDir} && {$command} 2>&1";

        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error("Git command failed", [
                'command' => $command,
                'output' => $errorOutput,
                'return_code' => $returnCode
            ]);
            throw new Exception("Git command failed: {$errorOutput}");
        }

        return $output;
    }

    public function pullMainRepo(): array
    {
        Log::info('Starting git pull for main repository');

        $this->configureGitCredentials();

        $output = $this->executeGitCommand('git pull origin main');

        Log::info('Git pull completed successfully', ['output' => $output]);

        return $output;
    }

    public function updateSubmodule(string $submodulePath = 'sei-compilados'): array
    {
        Log::info("Starting submodule update for {$submodulePath}");

        $this->configureGitCredentials();

        $this->executeGitCommand('git submodule sync');
        $output = $this->executeGitCommand('git submodule update --init --recursive --remote');

        Log::info('Submodule update completed successfully', ['output' => $output]);

        return $output;
    }

    public function getStatus(): array
    {
        $output = $this->executeGitCommand('git status --porcelain');

        return $output;
    }

    public function getCurrentBranch(): string
    {
        $output = $this->executeGitCommand('git rev-parse --abbrev-ref HEAD');

        return $output[0] ?? 'unknown';
    }

    public function getSubmoduleStatus(string $submodulePath = 'sei-compilados'): array
    {
        $output = $this->executeGitCommand('git submodule status');

        return $output;
    }
}
