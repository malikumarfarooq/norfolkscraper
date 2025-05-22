<?php

// app/Models/Parcel.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Parcel extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'gpin', 'parent_parcel_id', 'neighborhood', 'property_street',
        'property_use', 'buildings', 'section_plat', 'house_plate_number',
        'mailing_address', 'legal_description', 'parcel_area_sf', 'parcel_acreage',
        'bounds', 'latitude', 'longitude', 'active'
    ];

    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class, 'parcel_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'parcel_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'parcel_id');
    }

    public function features(): HasOne
    {
        return $this->hasOne(Feature::class, 'parcel_id');
    }
}
