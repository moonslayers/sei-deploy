<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

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

    private function executeGitCommand(string $command, ?string $path = null): array
    {
        $workingDir = $path ?? $this->baseDir;
        $fullCommand = "cd {$workingDir} && {$command} 2>&1";

        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error('Git command failed', [
                'command' => $command,
                'output' => $errorOutput,
                'return_code' => $returnCode,
            ]);
            throw new Exception("Git command failed: {$errorOutput}");
        }

        return $output;
    }

    public function pullMainRepo(): array
    {
        Log::info('Starting git pull for main repository');

        $repoUrl = $this->getRepoUrlWithCredentials();
        $output = $this->executeGitCommand("git pull {$repoUrl} main");

        Log::info('Git pull completed successfully', ['output' => $output]);

        return $output;
    }

    private function getRepoUrlWithCredentials(): string
    {
        if (empty($this->githubUser) || empty($this->githubToken)) {
            throw new Exception('GitHub credentials not configured');
        }

        $repo = env('GITHUB_REPO_MAIN', '');
        if (empty($repo)) {
            throw new Exception('GITHUB_REPO_MAIN not configured');
        }

        return "https://{$this->githubUser}:{$this->githubToken}@github.com/{$repo}.git";
    }

    public function updateSubmodule(string $submodulePath = 'sei-compilados'): array
    {
        Log::info("Starting submodule update for {$submodulePath}");

        $this->executeGitCommand('git submodule sync');

        $submoduleUrl = $this->getSubmoduleUrlWithCredentials($submodulePath);
        $output = $this->executeGitCommand("git -C {$submodulePath} pull {$submoduleUrl} main");

        Log::info('Submodule update completed successfully', ['output' => $output]);

        return $output;
    }

    private function getSubmoduleUrlWithCredentials(string $submodulePath): string
    {
        if (empty($this->githubUser) || empty($this->githubToken)) {
            throw new Exception('GitHub credentials not configured');
        }

        $repo = env('GITHUB_REPO_SUBMODULE', '');
        if (empty($repo)) {
            throw new Exception('GITHUB_REPO_SUBMODULE not configured');
        }

        return "https://{$this->githubUser}:{$this->githubToken}@github.com/{$repo}.git";
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
