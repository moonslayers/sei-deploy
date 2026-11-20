<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_script_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backend_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_script_versions');
    }
};
