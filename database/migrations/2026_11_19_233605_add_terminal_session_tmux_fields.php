<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('terminal_sessions', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
            if (! Schema::hasColumn('terminal_sessions', 'output_offset')) {
                $table->unsignedBigInteger('output_offset')->default(0);
            }
            if (! Schema::hasColumn('terminal_sessions', 'tmux_session')) {
                $table->string('tmux_session')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table) {
            $table->dropColumn(['last_activity_at', 'output_offset', 'tmux_session']);
        });
    }
};
