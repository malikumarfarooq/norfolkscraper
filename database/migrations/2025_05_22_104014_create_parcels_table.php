<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->boolean('active')->default(true);
            $table->string('property_address');
            $table->decimal('total_value', 12, 2)->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('property_use')->nullable();
            $table->string('building_type')->nullable();
            $table->integer('year_built')->nullable();
            $table->decimal('stories', 3, 1)->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('full_baths')->nullable();
            $table->integer('half_baths')->nullable();
            $table->string('latest_sale_owner')->nullable();
            $table->date('latest_sale_date')->nullable();
            $table->decimal('latest_sale_price', 12, 2)->nullable();
            $table->date('latest_assessment_year')->nullable();
            $table->decimal('latest_total_value', 12, 2)->nullable();
            $table->string('gpin')->nullable();
//            $table->point('location')->nullable();
            $table->timestamps();

            $table->index('gpin');
            $table->index('property_address');
//            $table->spatialIndex('location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parcels');
    }
};
