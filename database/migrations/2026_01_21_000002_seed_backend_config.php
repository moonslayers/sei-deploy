<?php

use App\Models\Backend;
use App\Models\Frontend;
use Database\Seeders\BackendConfigSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', ['--class' => BackendConfigSeeder::class, '--force' => true]);
    }

    public function down(): void
    {
        $repos = config('sei.backends', []);
        Backend::whereIn('repo', $repos)->delete();

        $names = config('sei.frontends', []);
        Frontend::whereIn('name', $names)->delete();
    }
};
