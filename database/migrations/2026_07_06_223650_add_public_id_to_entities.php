<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['workspaces', 'boards', 'board_lists', 'cards', 'labels'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('public_id', 26)->nullable()->after('id');
            });

            // Backfill existing rows with a ULID (raw queries — no model events).
            foreach (DB::table($table)->whereNull('public_id')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['public_id' => (string) Str::ulid()]);
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unique('public_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['public_id']);
                $t->dropColumn('public_id');
            });
        }
    }
};
