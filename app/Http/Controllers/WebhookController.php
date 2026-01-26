<?php

namespace App\Http\Controllers;

use App\Services\GitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function updateSubmodule(Request $request): JsonResponse
    {
        try {
            $data = $request->input('webhook_data', []);
            $repository = $data['repository'] ?? 'sei-compilados';
            $branch = $data['branch'] ?? 'main';
            $project = $data['project'] ?? null;

            Log::info('Webhook received: update submodule', [
                'repository' => $repository,
                'branch' => $branch,
                'project' => $project,
                'timestamp' => $data['timestamp'],
            ]);

            if ($repository !== 'sei-compilados') {
                Log::warning("Invalid repository specified: {$repository}");

                return response()->json([
                    'error' => 'Invalid repository',
                    'message' => 'Only sei-compilados repository is supported',
                ], 400);
            }

            if ($project !== null) {
                $projectPath = base_path("sei-compilados/{$project}");
                if (! is_dir($projectPath)) {
                    Log::error('Project folder not found in submodule', [
                        'project' => $project,
                        'path' => $projectPath,
                    ]);

                    return response()->json([
                        'error' => 'Project folder not found',
                        'message' => "Folder '{$project}' does not exist in sei-compilados",
                    ], 400);
                }
            }

            $output = $this->gitService->updateSubmodule($repository);

            if ($project !== null) {
                $deployOutput = $this->gitService->deployToFrontend($project);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Submodule updated and deployed successfully',
                    'data' => [
                        'repository' => $repository,
                        'branch' => $branch,
                        'project' => $project,
                        'git_output' => $output,
                        'deploy_output' => $deployOutput,
                    ],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Submodule updated successfully',
                'data' => [
                    'repository' => $repository,
                    'branch' => $branch,
                    'output' => $output,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update submodule', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update submodule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateDeploy(Request $request): JsonResponse
    {
        try {
            $data = $request->input('webhook_data', []);
            $branch = $data['branch'] ?? 'main';

            Log::info('Webhook received: update deploy', [
                'branch' => $branch,
                'timestamp' => $data['timestamp'],
            ]);

            if ($branch !== 'main') {
                Log::warning("Invalid branch specified: {$branch}");

                return response()->json([
                    'error' => 'Invalid branch',
                    'message' => 'Only main branch is supported',
                ], 400);
            }

            $output = $this->gitService->pullMainRepo();

            $submoduleOutput = [];
            try {
                $submoduleOutput = $this->gitService->updateSubmodule('sei-compilados');
            } catch (\Exception $e) {
                Log::warning('Failed to update submodule after deploy', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Deploy updated successfully',
                'data' => [
                    'branch' => $branch,
                    'main_pull' => $output,
                    'submodule_update' => $submoduleOutput,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update deploy', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update deploy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $currentBranch = $this->gitService->getCurrentBranch();
            $status = $this->gitService->getStatus();
            $submoduleStatus = $this->gitService->getSubmoduleStatus('sei-compilados');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'current_branch' => $currentBranch,
                    'git_status' => $status,
                    'submodule_status' => $submoduleStatus,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
