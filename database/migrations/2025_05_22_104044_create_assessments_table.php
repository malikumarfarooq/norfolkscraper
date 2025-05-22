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
// database/migrations/xxxx_xx_xx_xxxxxx_create_assessments_table.php
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('parcel_id', 20);
            $table->date('effective_date');
            $table->decimal('land_value', 12, 2);
            $table->decimal('improvement_value', 12, 2);
            $table->decimal('total_value', 12, 2);
            $table->timestamps();

            $table->foreign('parcel_id')->references('id')->on('parcels')->onDelete('cascade');
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
