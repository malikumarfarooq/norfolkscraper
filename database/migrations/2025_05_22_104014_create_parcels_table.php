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
// database/migrations/xxxx_xx_xx_xxxxxx_create_parcels_table.php
        Schema::create('parcels', function (Blueprint $table) {
            $table->string('id', 20)->primary(); // Parcel_id
            $table->string('gpin', 20)->nullable();
            $table->string('parent_parcel_id', 20)->nullable();
            $table->string('neighborhood', 20)->nullable();
            $table->string('property_street')->nullable();
            $table->string('property_use')->nullable();
            $table->string('buildings')->nullable();
            $table->string('section_plat')->nullable();
            $table->string('house_plate_number')->nullable();
            $table->text('mailing_address')->nullable();
            $table->text('legal_description')->nullable();
            $table->string('parcel_area_sf')->nullable();
            $table->string('parcel_acreage')->nullable();
            $table->geometry('bounds')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('gpin');
            $table->index('neighborhood');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
