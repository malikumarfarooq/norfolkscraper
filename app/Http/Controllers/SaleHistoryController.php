<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

class SaleHistoryController extends Controller
{
    public function show($id)
    {
        $response = Http::get("https://air.norfolk.gov/api/v1/areasales/{$id}");

        if ($response->successful()) {
            $data = $response->json();
            $perPage = request()->input('per_page', 10);
            $currentPage = LengthAwarePaginator::resolveCurrentPage();

            $items = collect($data['body']);
            $currentItems = $items->slice(($currentPage - 1) * $perPage, $perPage)->all();

            $sales = new LengthAwarePaginator(
                $currentItems,
                $items->count(),
                $perPage,
                $currentPage,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]
            );

            // Add the original data structure for the view
            $sales->originalData = $data;

            return view('sale_history', compact('sales'));
        }

        abort(404, 'Sale history not found.');
    }
}
