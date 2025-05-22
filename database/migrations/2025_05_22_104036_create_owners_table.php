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
// database/migrations/xxxx_xx_xx_xxxxxx_create_owners_table.php
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('parcel_id', 20);
            $table->string('name');
            $table->timestamps();

            $table->foreign('parcel_id')->references('id')->on('parcels')->onDelete('cascade');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
