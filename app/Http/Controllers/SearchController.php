<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function suggestions(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $query = $request->input('query');

        return Cache::remember("suggestions:{$query}", now()->addHours(6), function() use ($query) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->post('https://air.norfolk.gov/api/v2/search/suggestions', [
                    'query' => $query,
                    'limit' => 10
                ]);

                if ($response->successful()) {
                    return response()->json($response->json());
                }

                return response()->json([
                    'error' => 'API request failed',
                    'status' => $response->status()
                ], $response->status());

            } catch (\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage()
                ], 500);
            }
        });
    }
}
