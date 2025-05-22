<?php

// app/Models/Owner.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    protected $fillable = ['parcel_id', 'name'];
}
