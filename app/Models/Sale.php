<?php

// app/Models/Sale.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'parcel_id', 'sale_date', 'sale_price',
        'transaction_type', 'document_number'
    ];

    protected $dates = ['sale_date'];
}
