<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class GitService
{
    private string $githubUser;

    private string $githubToken;

    private string $baseDir;

    private string $gitUserName;

    private string $gitUserEmail;

    private bool $gitIdentityConfigured = false;

    public function __construct()
    {
        $this->githubUser = env('GITHUB_USER', '');
        $this->githubToken = env('GITHUB_TOKEN', '');
        $this->baseDir = base_path();
        $this->gitUserName = env('GIT_USER_NAME', 'Deploy Bot');
        $this->gitUserEmail = env('GIT_USER_EMAIL', 'deploy@server.com');
    }

    private function ensureGitIdentity(): void
    {
        if ($this->gitIdentityConfigured) {
            return;
        }

        $this->executeGitCommand("git config --global user.email '{$this->gitUserEmail}'");
        $this->executeGitCommand("git config --global user.name '{$this->gitUserName}'");

        $this->gitIdentityConfigured = true;
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
        $this->ensureGitIdentity();

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
        $this->ensureGitIdentity();

        Log::info("Starting submodule update for {$submodulePath}");

        $this->executeGitCommand('git submodule sync');

        $submoduleUrl = $this->getSubmoduleUrlWithCredentials($submodulePath);
        $output = $this->executeGitCommand("git -C {$submodulePath} pull --allow-unrelated-histories {$submoduleUrl} main");

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

    public function deployToFrontend(string $projectFolder): array
    {
        Log::info("Starting deployment for {$projectFolder}");

        $sourcePath = base_path("sei-compilados/{$projectFolder}");
        $destPath = "/var/www/{$projectFolder}";

        if (! is_dir($sourcePath)) {
            Log::error('Source folder does not exist', [
                'project' => $projectFolder,
                'path' => $sourcePath,
            ]);
            throw new Exception("Source folder {$sourcePath} does not exist");
        }

        if (! is_dir($destPath)) {
            Log::error('Destination folder does not exist', [
                'project' => $projectFolder,
                'path' => $destPath,
            ]);
            throw new Exception("Destination folder {$destPath} does not exist");
        }

        $this->deleteDirectoryContents($destPath);
        $this->copyDirectory($sourcePath, $destPath);

        Log::info("Deployment completed for {$projectFolder}");

        return [
            'deployment' => 'success',
            'project' => $projectFolder,
            'source' => $sourcePath,
            'destination' => $destPath,
        ];
    }

    private function deleteDirectoryContents(string $path): void
    {
        Log::info("Deleting contents from {$path}");

        $files = glob("{$path}/*");
        if ($files === false) {
            $files = [];
        }

        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDirectoryRecursive($file);
            } else {
                unlink($file);
            }
        }
    }

    private function deleteDirectoryRecursive(string $path): void
    {
        $files = glob("{$path}/*");
        if ($files === false) {
            $files = [];
        }

        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDirectoryRecursive($file);
            } else {
                unlink($file);
            }
        }

        rmdir($path);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        Log::info("Copying files from {$source} to {$destination}");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest = $destination.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
            if ($item->isDir()) {
                mkdir($dest, 0755, true);
            } else {
                copy($item, $dest);
            }
        }
    }
}
