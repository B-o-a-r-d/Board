<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A signed, revocable token that exposes a read-only iCal feed:
     *  - per board  (that board's dated cards),
     *  - per user   (dated cards across every board the user can access).
     * Null means the feed is off.
     */
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->string('ical_token', 40)->nullable()->unique()->after('share_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('ical_token', 40)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('ical_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ical_token');
        });
    }
};
