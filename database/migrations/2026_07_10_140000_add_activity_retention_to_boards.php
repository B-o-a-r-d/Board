<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            // Prune activity log entries older than this many days. Null = keep
            // forever. Set by a board admin; enforced by the activities:prune command.
            $table->unsignedSmallInteger('activity_retention_days')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('activity_retention_days');
        });
    }
};
