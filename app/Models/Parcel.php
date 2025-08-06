<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'active',
        'property_address',
        'total_value',
        'mailing_address',
        'owner_name',
        'property_use',
        'building_type',
        'year_built',
        'stories',
        'bedrooms',
        'full_baths',
        'half_baths',
        'latest_sale_owner',
        'latest_sale_date',
        'latest_sale_price',
        'latest_assessment_year',
        'latest_total_value',
        'gpin',
        'created_at',
        'updated_at',
//        'location'
    ];

    protected $casts = [
        'active' => 'boolean',
        'year_built' => 'integer',
        'stories' => 'float',
        'bedrooms' => 'integer',
        'full_baths' => 'integer',
        'half_baths' => 'integer',
        'total_value' => 'float',
        'latest_sale_price' => 'float',
        'latest_total_value' => 'float',
        'latest_sale_date' => 'date',
        'latest_assessment_year' => 'date',
    ];
    public function getLatestSalePriceAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        return (float)$value;
    }
}

