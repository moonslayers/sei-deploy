<?php

namespace App\Filament\Pages;

use App\Models\Backend;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class Console extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'consola/{backend}';

    protected string $view = 'filament.pages.console';

    protected static ?string $title = 'Consola';

    public ?Backend $backend = null;

    public function mount(Backend $backend): void
    {
        abort_unless(Gate::allows('admin'), 403);

        $this->backend = $backend;
    }

    public function getTitle(): string
    {
        return 'Consola: '.($this->backend?->repo ?? '');
    }
}
