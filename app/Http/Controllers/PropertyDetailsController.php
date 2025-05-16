<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PropertyDetailsController extends Controller
{
    /**
     * Show property detail page.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $response = Http::get("https://air.norfolk.gov/api/v1/recordcard/{$id}");

        if ($response->successful()) {
            $property = $response->json();
            return view('property_details', compact('property'));
        }

        abort(404, 'Record not found');
    }
}
