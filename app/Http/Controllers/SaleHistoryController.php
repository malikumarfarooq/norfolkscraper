<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleHistoryController extends Controller
{
    protected string $apiBaseUrl = 'https://air.norfolk.gov/api/v1/areasales';

    public function show($id)
    {
        $response = Http::get("{$this->apiBaseUrl}/{$id}");

        if (!$response->successful()) {
            abort(404, 'Sale history not found.');
        }

        $data = $response->json();
        $items = collect($data['body'] ?? []);

        $perPage = request()->input('per_page', 10);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
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

        $sales->originalData = $data;

        return view('sale_history', [
            'sales' => $sales,
            'currentId' => $id
        ]);
    }

    public function export($id): StreamedResponse
    {
        $response = Http::get("{$this->apiBaseUrl}/{$id}");

        if (!$response->successful()) {
            abort(404, 'Sale history not found.');
        }

        $data = $response->json();
        $sales = collect($data['body'] ?? []);

        $fileName = "sales-history-{$id}-" . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM

            // Headers
            fputcsv($file, [
                'Parcel ID', 'GPIN', 'Account', 'Address',
                'Property Type', 'Sale Date', 'Sale Price',
            ]);

            // Data rows
            foreach ($sales as $sale) {
                $displayValues = $this->extractDisplayValues($sale['display'] ?? []);

                fputcsv($file, [
                    $sale['ParcelIdentifier'] ?? '',
                    $displayValues['GPIN'] ?? '',
                    $displayValues['Account'] ?? '',
                    $displayValues['PropertyStreet'] ?? '',
                    $displayValues['PropertyUse'] ?? '',
                    $displayValues['saledate'] ?? '',
                    $this->cleanPrice($displayValues['saleprice'] ?? ''),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ]);
    }

    protected function extractDisplayValues(array $displayItems): array
    {
        return collect($displayItems)
            ->filter(fn($item) => isset($item['id'], $item['value']))
            ->pluck('value', 'id')
            ->toArray();
    }

    protected function cleanPrice(string $price): string
    {
        return preg_replace('/[^0-9.]/', '', $price);
    }
}
