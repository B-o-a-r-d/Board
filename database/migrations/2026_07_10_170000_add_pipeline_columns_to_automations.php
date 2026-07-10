<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 0 of the Butler-level automations plan: rules gain an ordered list
     * of actions, AND-combined conditions, an actor scope ("by me") and run
     * counters. The legacy single `action_type`/`action_config` columns are
     * kept (and backfilled into `actions`) until the engine switches over.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // Ordered pipeline: [{type: string, config: object}, …]
            $table->json('actions')->nullable()->after('trigger_config');
            // AND-combined guards: [{type: string, config: object}, …]
            $table->json('conditions')->nullable()->after('actions');
            // Who may fire the rule: anyone | me (the rule's creator)
            $table->string('actor_scope', 10)->default('anyone')->after('conditions');
            $table->timestamp('last_run_at')->nullable()->after('is_active');
            $table->unsignedInteger('runs_count')->default(0)->after('last_run_at');
            $table->unsignedInteger('failures_count')->default(0)->after('runs_count');
        });

        // Backfill: every existing rule becomes a one-action pipeline.
        DB::table('automations')->orderBy('id')->each(function ($automation) {
            DB::table('automations')->where('id', $automation->id)->update([
                'actions' => json_encode([[
                    'type' => $automation->action_type,
                    'config' => json_decode($automation->action_config ?? 'null', true) ?? [],
                ]]),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn(['actions', 'conditions', 'actor_scope', 'last_run_at', 'runs_count', 'failures_count']);
        });
    }
};
