<?php
// app/Models/Assessment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'parcel_id', 'effective_date', 'land_value',
        'improvement_value', 'total_value'
    ];

    protected $dates = ['effective_date'];
}
