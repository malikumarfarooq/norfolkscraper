<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelFetchBatch extends Model
{
    protected $table = 'parcel_fetch_batches';

    protected $fillable = [
        'batch_id',
        'total_jobs',
        'processed_jobs',
        'failed_jobs',
        'status',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
