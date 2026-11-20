<?php

namespace App\Filament\Widgets;

use App\Models\Backend;
use App\Services\RepoStatusService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class BackendTableWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var array<int|string, array> */
    protected array $statusCache = [];

    protected function backendStatus(Backend $record): array
    {
        $key = $record->getKey() ?? spl_object_id($record);

        if (! array_key_exists($key, $this->statusCache)) {
            $this->statusCache[$key] = app(RepoStatusService::class)->backendStatus($record);
        }

        return $this->statusCache[$key];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Backend::query())
            ->columns([
                TextColumn::make('repo')->label('Repo'),
                TextColumn::make('resolved_path')
                    ->label('Path')
                    ->getStateUsing(fn (Backend $record) => $record->resolved_path),
                TextColumn::make('branch')
                    ->label('Branch')
                    ->getStateUsing(function (Backend $record) {
                        return $this->backendStatus($record)['branch'] ?? '—';
                    }),
                TextColumn::make('commit')
                    ->label('Last commit')
                    ->getStateUsing(function (Backend $record) {
                        $s = $this->backendStatus($record);
                        if (! $s['commit_hash']) {
                            return '—';
                        }

                        return $s['commit_hash'].' '.($s['commit_message'] ?? '').' ('.($s['commit_date'] ?? '').')';
                    }),
                TextColumn::make('env')
                    ->label('.env')
                    ->getStateUsing(fn (Backend $record) => $this->backendStatus($record)['env_exists'] ? 'Yes' : 'No')
                    ->color(fn (Backend $record) => $this->backendStatus($record)['env_exists'] ? 'success' : 'danger'),
                TextColumn::make('state')
                    ->label('State')
                    ->getStateUsing(function (Backend $record) {
                        $s = $this->backendStatus($record);
                        if (! $s['exists']) {
                            return 'missing dir';
                        }
                        if (! $s['is_git']) {
                            return 'not git';
                        }

                        return $s['dirty'] ? 'dirty' : 'clean';
                    })
                    ->color(fn (Backend $record) => $this->backendStatus($record)['dirty'] ? 'warning' : 'success'),
            ]);
    }
}
