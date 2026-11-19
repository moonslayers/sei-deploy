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
        try {
            $workingDir = $this->baseDir;
            $fullCommand = "cd {$workingDir} && git config --local user.email 2>&1";
            exec($fullCommand, $output, $returnCode);

            $currentEmail = $returnCode === 0 ? trim(implode("\n", $output)) : '';

            if (empty($currentEmail)) {
                $fullCommand = "cd {$workingDir} && git config --local user.email {$this->gitUserEmail} 2>&1";
                exec($fullCommand, $output, $returnCode);

                if ($returnCode !== 0) {
                    Log::warning('Failed to set git user.email', [
                        'output' => implode("\n", $output),
                        'return_code' => $returnCode,
                    ]);
                } else {
                    Log::info('Git email configured', ['email' => $this->gitUserEmail]);
                }
            }

            $fullCommand = "cd {$workingDir} && git config --local user.name 2>&1";
            exec($fullCommand, $output, $returnCode);

            $currentName = $returnCode === 0 ? trim(implode("\n", $output)) : '';

            if (empty($currentName)) {
                $fullCommand = "cd {$workingDir} && git config --local user.name {$this->gitUserName} 2>&1";
                exec($fullCommand, $output, $returnCode);

                if ($returnCode !== 0) {
                    Log::warning('Failed to set git user.name', [
                        'output' => implode("\n", $output),
                        'return_code' => $returnCode,
                    ]);
                } else {
                    Log::info('Git name configured', ['name' => $this->gitUserName]);
                }
            }
        } catch (Exception $e) {
            Log::error('Exception while setting git identity', [
                'error' => $e->getMessage(),
            ]);
        }
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

    public function installMainDependencies(): array
    {
        Log::info('Starting composer install for main repository');

        $workingDir = $this->baseDir;
        $command = 'composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader';
        $fullCommand = 'cd '.escapeshellarg($workingDir).' && '.$command.' 2>&1';

        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error('Composer install failed', [
                'output' => $errorOutput,
                'return_code' => $returnCode,
            ]);
            throw new Exception("Composer install failed: {$errorOutput}");
        }

        Log::info('Composer install completed successfully', ['output' => $output]);

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

    public function pullCompiledRepo(): array
    {
        $compiledRepoPath = dirname($this->baseDir).DIRECTORY_SEPARATOR.'sei-compilados';

        Log::info("Starting git pull for compiled repository at {$compiledRepoPath}");

        $repoUrl = $this->getCompiledRepoUrlWithCredentials();
        $output = $this->executeGitCommand("git pull {$repoUrl} main", $compiledRepoPath);

        Log::info('Compiled repository pull completed successfully', ['output' => $output]);

        return $output;
    }

    private function getCompiledRepoUrlWithCredentials(): string
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

    public function deployToFrontend(string $projectFolder): array
    {
        Log::info("Starting deployment for {$projectFolder}");

        $sourcePath = dirname(base_path()).DIRECTORY_SEPARATOR.'sei-compilados'.DIRECTORY_SEPARATOR.$projectFolder;
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

    public function deployAllFrontends(): array
    {
        $projectList = config('sei.frontends', []);

        if (empty($projectList)) {
            Log::info('No frontends configured in config/sei.php');

            return [];
        }
        $results = [];

        foreach ($projectList as $project) {
            try {
                Log::info("Starting rsync deployment for {$project}");

                $sourcePath = dirname(base_path()).DIRECTORY_SEPARATOR.'sei-compilados'.DIRECTORY_SEPARATOR.$project;
                $destPath = "/var/www/{$project}";

                if (! is_dir($sourcePath)) {
                    throw new Exception("Source folder {$sourcePath} does not exist");
                }

                if (! is_dir($destPath)) {
                    throw new Exception("Destination folder {$destPath} does not exist");
                }

                $rsyncCommand = "rsync -av --delete {$sourcePath}/ {$destPath}/ 2>&1";
                exec($rsyncCommand, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('Rsync failed: '.implode("\n", $output));
                }

                $results[$project] = [
                    'status' => 'success',
                    'message' => 'Deployment completed successfully',
                    'output' => $output,
                ];

                Log::info("Rsync deployment completed for {$project}");
            } catch (Exception $e) {
                Log::error("Failed to deploy {$project}", [
                    'error' => $e->getMessage(),
                ]);

                $results[$project] = [
                    'status' => 'error',
                    'message' => 'Deployment failed',
                    'error' => $e->getMessage(),
                ];

                throw $e;
            }
        }

        return $results;
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
