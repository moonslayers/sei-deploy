<?php

namespace Database\Seeders;

use App\Models\Backend;
use App\Models\Frontend;
use Illuminate\Database\Seeder;

class BackendConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('sei.backends', []) as $repo) {
            Backend::updateOrCreate(
                ['repo' => $repo],
                [
                    'path' => config("sei.backend_paths.{$repo}"),
                    'migrate_command' => config("sei.backend_commands.{$repo}"),
                ]
            );
        }

        foreach (config('sei.frontends', []) as $name) {
            Frontend::updateOrCreate(['name' => $name], []);
        }
    }
}
