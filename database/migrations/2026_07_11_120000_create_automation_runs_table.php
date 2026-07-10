<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8: an execution journal for automations + a consecutive-failure
     * counter used to quarantine a rule that keeps failing.
     */
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            // Denormalized so the board activity view lists runs without a join.
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12); // success | partial | failed
            $table->unsignedSmallInteger('actions_run')->default(0);
            $table->unsignedSmallInteger('actions_failed')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['board_id', 'created_at']);
            $table->index(['automation_id', 'created_at']);
        });

        Schema::table('automations', function (Blueprint $table) {
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('failures_count');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('consecutive_failures');
        });

        Schema::dropIfExists('automation_runs');
    }
};
