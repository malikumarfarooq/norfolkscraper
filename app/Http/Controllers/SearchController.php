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

    /**
     * Handle autocomplete suggestions request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suggestions(Request $request)
    {
        $term = $request->input('query');

        $payload = [
            'filters' => [
                'term' => $term,
            ],
            'debug' => [
                'currentURL' => url('/'),
                'previousURL' => '',
            ],
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin' => 'https://air.norfolk.gov',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => csrf_token(),
        ])->post('https://air.norfolk.gov/api/v2/search/suggestions', $payload);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'suggestions' => $response->json('suggestions', []),
            ]);
        }

        return response()->json([
            'success' => false,
            'suggestions' => [],
        ], 500);
    }
}
