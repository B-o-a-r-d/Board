<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Board types: 'kanban' is the historical lists/cards board; plugins can
     * contribute other types (ProvidesBoardType), e.g. 'shelf'. Workspaces
     * keep grouping boards of any type.
     */
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->string('type')->default('kanban')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
