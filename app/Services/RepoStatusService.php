<?php

namespace App\Services;

use App\Models\Backend;
use App\Models\Frontend;

class RepoStatusService
{
    public function backendStatus(Backend $backend): array
    {
        $path = $backend->resolved_path;

        $result = [
            'repo' => $backend->repo,
            'path' => $path,
            'exists' => is_dir($path),
            'env_exists' => false,
            'is_git' => false,
            'branch' => null,
            'commit_hash' => null,
            'commit_message' => null,
            'commit_date' => null,
            'dirty' => false,
            'error' => null,
        ];

        if (! is_dir($path)) {
            $result['error'] = 'directory missing';

            return $result;
        }

        $result['env_exists'] = is_file($path.'/.env');

        $gitDir = $path.'/.git';
        if (! is_dir($gitDir) && ! is_file($gitDir)) {
            $result['error'] = 'not a git repository';

            return $result;
        }

        $result['is_git'] = true;

        $safePath = escapeshellarg($path);

        $branch = $this->run("git -C {$safePath} rev-parse --abbrev-ref HEAD 2>/dev/null");
        $result['branch'] = $branch !== '' ? $branch : null;

        $hash = $this->run("git -C {$safePath} rev-parse --short HEAD 2>/dev/null");
        $result['commit_hash'] = $hash !== '' ? $hash : null;

        $message = $this->run("git -C {$safePath} log -1 --pretty=%s 2>/dev/null");
        $result['commit_message'] = $message !== '' ? $message : null;

        $date = $this->run("git -C {$safePath} log -1 --pretty=%ci 2>/dev/null");
        $result['commit_date'] = $date !== '' ? $date : null;

        $status = $this->run("git -C {$safePath} status --porcelain 2>/dev/null");
        $result['dirty'] = $status !== '';

        return $result;
    }

    public function frontendStatus(Frontend $frontend): array
    {
        $compiledDir = base_path('../sei-compilados/'.$frontend->name);

        return [
            'name' => $frontend->name,
            'path' => $compiledDir,
            'exists' => is_dir($compiledDir),
        ];
    }

    private function run(string $command): string
    {
        $output = [];
        $code = 0;
        exec($command, $output, $code);

        return trim(implode("\n", $output));
    }
}
