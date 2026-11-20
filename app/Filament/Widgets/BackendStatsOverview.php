<?php

namespace App\Filament\Widgets;

use App\Models\Backend;
use App\Models\Frontend;
use App\Services\RepoStatusService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BackendStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $service = app(RepoStatusService::class);
        $backends = Backend::all();
        $frontends = Frontend::all();

        $missingEnv = 0;
        $dirty = 0;
        $missingDirs = 0;

        foreach ($backends as $backend) {
            $status = $service->backendStatus($backend);
            if (! $status['exists']) {
                $missingDirs++;
            }
            if ($status['exists'] && ! $status['env_exists']) {
                $missingEnv++;
            }
            if ($status['dirty']) {
                $dirty++;
            }
        }

        $missingFrontendDirs = 0;
        foreach ($frontends as $frontend) {
            if (! $service->frontendStatus($frontend)['exists']) {
                $missingFrontendDirs++;
            }
        }

        return [
            Stat::make('Backends', $backends->count())
                ->description('Total configured')
                ->color('primary'),
            Stat::make('Frontends', $frontends->count())
                ->description('Total configured')
                ->color('primary'),
            Stat::make('Missing .env', $missingEnv)
                ->description('Backends without .env')
                ->color($missingEnv > 0 ? 'danger' : 'success'),
            Stat::make('Dirty repos', $dirty)
                ->description('Uncommitted changes')
                ->color($dirty > 0 ? 'warning' : 'success'),
            Stat::make('Missing dirs', $missingDirs + $missingFrontendDirs)
                ->description('Backend/frontend paths missing')
                ->color(($missingDirs + $missingFrontendDirs) > 0 ? 'danger' : 'success'),
        ];
    }
}
