<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parcel_fetch_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id');
            $table->integer('total_jobs');
            $table->integer('processed_jobs')->default(0);
            $table->integer('failed_jobs')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parcel_fetch_batches');
    }
};
