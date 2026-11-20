<?php

namespace App\Filament\Pages;

use App\Models\Backend;
use App\Models\DeployScriptVersion;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

class DeployScripts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected string $view = 'filament.pages.deploy-scripts';

    protected static ?string $title = 'Scripts de deploy';

    public function table(Table $table): Table
    {
        return $table
            ->query(Backend::query())
            ->columns([
                TextColumn::make('repo')->label('Backend')->searchable(),
                TextColumn::make('script_status')
                    ->label('Tipo')
                    ->getStateUsing(fn (Backend $record) => $record->script ? 'Script propio' : 'Plantilla compartida')
                    ->badge()
                    ->color(fn (Backend $record) => $record->script ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Editar')
                    ->authorize('admin')
                    ->requiresConfirmation()
                    ->icon(Heroicon::OutlinedPencil)
                    ->form([
                        Textarea::make('script')
                            ->label('Script')
                            ->required()
                            ->rows(20)
                            ->rules(['required', 'string', 'starts_with:#!', 'max:50000']),
                    ])
                    ->fillForm(function (Backend $record): array {
                        $content = $record->script;
                        if ($content === null) {
                            $content = File::get(base_path('scripts/deploy-backend.sh'));
                        }

                        return ['script' => $content];
                    })
                    ->action(function (Backend $record, array $data): void {
                        if ($record->script !== null && $record->script !== '') {
                            DeployScriptVersion::create([
                                'backend_id' => $record->id,
                                'content' => $record->script,
                                'user_id' => Auth::id(),
                            ]);
                        }
                        $record->update(['script' => $data['script']]);
                        Notification::make()->title('Script guardado')->success()->send();
                    }),
                Action::make('history')
                    ->label('Historial')
                    ->icon(Heroicon::OutlinedClock)
                    ->modalContent(fn (Backend $record) => view('filament.pages.deploy-scripts-history', ['versions' => DeployScriptVersion::where('backend_id', $record->id)->with('user')->latest()->limit(20)->get(), 'record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('console')
                    ->label('Consola')
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->url(fn (Backend $record) => Console::getUrl(['backend' => $record])),
            ]);
    }

    public function restoreVersion(int $versionId): void
    {
        Gate::authorize('admin');
        $version = DeployScriptVersion::findOrFail($versionId);
        $backend = Backend::findOrFail($version->backend_id);
        if (! $version->backend()->exists() || $version->backend_id !== $backend->id) {
            abort(404);
        }

        $content = $version->content;
        if ($content === null || trim($content) === '' || ! str_starts_with($content, '#!')) {
            Notification::make()->title('Contenido inválido: debe iniciar con shebang (#!)')->danger()->send();

            return;
        }

        if ($backend->script !== null && $backend->script !== '') {
            DeployScriptVersion::create([
                'backend_id' => $backend->id,
                'content' => $backend->script,
                'user_id' => Auth::id(),
            ]);
        }
        $backend->update(['script' => $version->content]);
        Notification::make()->title('Versión restaurada')->success()->send();
    }
}
