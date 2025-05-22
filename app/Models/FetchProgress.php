<?php

// app/Models/FetchProgress.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FetchProgress extends Model
{
    protected $fillable = ['current_id', 'max_id', 'is_running', 'should_stop'];
}
