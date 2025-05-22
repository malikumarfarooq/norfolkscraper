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
// database/migrations/xxxx_xx_xx_xxxxxx_create_fetch_progress_table.php
        Schema::create('fetch_progress', function (Blueprint $table) {
            $table->id();
            $table->integer('current_id')->default(10000001);
            $table->integer('max_id')->nullable();
            $table->boolean('is_running')->default(false);
            $table->boolean('should_stop')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fetch_progress');
    }
};
