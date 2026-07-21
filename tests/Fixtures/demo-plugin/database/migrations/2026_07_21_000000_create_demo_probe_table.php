<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fixture: proves the installer runs a plugin's own migrations at install.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_plugin_probe', function (Blueprint $table) {
            $table->id();
            $table->string('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_plugin_probe');
    }
};
