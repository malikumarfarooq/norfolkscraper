<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'tax_account_number',
        'gpin',
        'full_address'
    ];
}
