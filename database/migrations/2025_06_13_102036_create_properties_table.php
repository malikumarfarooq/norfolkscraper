<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('tax_account_number')->index(); // Added index for faster lookups
            $table->string('gpin')->nullable()->index(); // Made nullable with index
            $table->text('full_address')->nullable();
            $table->timestamps();

            // Changed to single-column unique since GPIN is nullable
            // (MySQL doesn't allow NULL in unique composite keys)
            $table->unique(['tax_account_number']);

            // Optional: Add composite index for queries that use both fields
            $table->index(['tax_account_number', 'gpin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
