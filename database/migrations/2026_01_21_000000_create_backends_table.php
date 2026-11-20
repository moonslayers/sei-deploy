<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backends', function (Blueprint $table) {
            $table->id();
            $table->string('repo')->unique();
            $table->string('path')->nullable();
            $table->string('migrate_command')->nullable();
            $table->longText('script')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backends');
    }
};
