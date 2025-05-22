<?php

// app/Models/Feature.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = [
        'parcel_id', 'building_type', 'stories', 'year_built', 'construction_quality',
        'finished_living_area', 'bedrooms', 'full_baths', 'half_baths', 'fireplaces',
        'heating', 'cooling', 'foundation', 'attic', 'attic_area', 'interior_walls',
        'exterior_cover', 'roof_style', 'roof_cover', 'framing', 'basement_finished_area'
    ];
}
