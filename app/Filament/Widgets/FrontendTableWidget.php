<?php

namespace App\Filament\Widgets;

use App\Models\Frontend;
use App\Services\RepoStatusService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FrontendTableWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var array<int|string, array> */
    protected array $statusCache = [];

    protected function frontendStatus(Frontend $record): array
    {
        $key = $record->getKey() ?? spl_object_id($record);

        if (! array_key_exists($key, $this->statusCache)) {
            $this->statusCache[$key] = app(RepoStatusService::class)->frontendStatus($record);
        }

        return $this->statusCache[$key];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Frontend::query())
            ->columns([
                TextColumn::make('name')->label('Frontend'),
                TextColumn::make('path')
                    ->label('Compiled path')
                    ->getStateUsing(fn (Frontend $record) => $this->frontendStatus($record)['path']),
                TextColumn::make('exists')
                    ->label('Exists')
                    ->getStateUsing(fn (Frontend $record) => $this->frontendStatus($record)['exists'] ? 'Yes' : 'No')
                    ->color(fn (Frontend $record) => $this->frontendStatus($record)['exists'] ? 'success' : 'danger'),
            ]);
    }
}
