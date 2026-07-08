<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Restrict invitations to these email domains (null/empty = any domain).
            $table->json('allowed_invite_domains')->nullable()->after('color');
            // Whitelist of allowed attachment extensions (null/empty = any type).
            $table->json('allowed_attachment_extensions')->nullable()->after('allowed_invite_domains');
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            // A deactivated member keeps their account but loses access to the
            // workspace and its boards until reactivated.
            $table->timestamp('deactivated_at')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['allowed_invite_domains', 'allowed_attachment_extensions']);
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
