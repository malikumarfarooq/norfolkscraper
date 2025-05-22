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
// database/migrations/xxxx_xx_xx_xxxxxx_create_features_table.php
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('parcel_id', 20);
            $table->string('building_type')->nullable();
            $table->decimal('stories', 3, 1)->nullable();
            $table->integer('year_built')->nullable();
            $table->string('construction_quality')->nullable();
            $table->integer('finished_living_area')->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('full_baths')->nullable();
            $table->integer('half_baths')->nullable();
            $table->boolean('fireplaces')->default(false);
            $table->string('heating')->nullable();
            $table->string('cooling')->nullable();
            $table->string('foundation')->nullable();
            $table->boolean('attic')->default(false);
            $table->integer('attic_area')->nullable();
            $table->string('interior_walls')->nullable();
            $table->string('exterior_cover')->nullable();
            $table->string('roof_style')->nullable();
            $table->string('roof_cover')->nullable();
            $table->string('framing')->nullable();
            $table->integer('basement_finished_area')->nullable();
            $table->timestamps();

            $table->foreign('parcel_id')->references('id')->on('parcels')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
