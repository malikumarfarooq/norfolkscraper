<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'tax_account_number', // required
        'gpin',              // optional
        'full_address'      // optional
    ];

    protected $casts = [
        'tax_account_number' => 'string'
    ];

    public static function rules(): array
    {
        return [
            'tax_account_number' => 'required|string|max:255',
            'gpin' => 'nullable|string|max:255',
            'full_address' => 'nullable|string'
        ];
    }
}
