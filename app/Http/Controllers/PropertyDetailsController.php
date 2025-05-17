<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;

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

    /**
     * Export property data as CSV.
     */
    public function export($id, Request $request)
    {
        $response = Http::get("https://air.norfolk.gov/api/v1/recordcard/{$id}");

        if ($response->successful()) {
            $property = $response->json();

            // Get date filters from request
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Prepare CSV data with date filtering
            $csvData = $this->formatPropertyDataForCSV($property, $startDate, $endDate);

            $filename = "property-{$id}-" . date('Y-m-d') . ".csv";

            return Response::make($csvData, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename={$filename}",
            ]);
        }

        abort(404, 'Record not found');
    }

    /**
     * Format property data for CSV export with date filtering.
     */
    private function formatPropertyDataForCSV(array $property, ?string $startDate = null, ?string $endDate = null): string
    {
        $output = fopen('php://temp', 'w');

        // Basic Info (always included)
        fputcsv($output, ['Category', 'Field', 'Value']);
        fputcsv($output, ['Basic Information', 'Property ID', $property['id']]);
        fputcsv($output, ['Basic Information', 'City Latitude', $property['cty']]);
        fputcsv($output, ['Basic Information', 'City Longitude', $property['ctx']]);
        fputcsv($output, ['Basic Information', 'Active', $property['active'] ? 'Yes' : 'No']);

        // Parcel Header (always included)
        $header = $property['parcel']['header'];
        fputcsv($output, ['Parcel Header', 'Street', $header['PropertyStreet']]);
        fputcsv($output, ['Parcel Header', 'Total Value', $header['total_value']]);
        fputcsv($output, ['Parcel Header', 'Mailing Address', $header['MailingAddress']]);
        fputcsv($output, ['Parcel Header', 'GPIN', $header['GPIN']]);

        // Filter sales history by date if dates provided
        if ($sales = $property['parcel']['sections']['1'][0] ?? []) {
            $filteredSales = $this->filterByDateRange($sales, $startDate, $endDate, 'saledate');

            foreach ($filteredSales as $sale) {
                fputcsv($output, ['Sales History', 'Owner', $sale['owners']]);
                fputcsv($output, ['Sales History', 'Sale Date', $sale['saledate']]);
                fputcsv($output, ['Sales History', 'Price', $sale['saleprice']]);
                fputcsv($output, ['Sales History', 'Doc #', $sale['docnum']]);
            }
        }

        // Filter assessments by date if dates provided
        if ($assessments = $property['parcel']['sections']['1'][1] ?? []) {
            $filteredAssessments = $this->filterByDateRange($assessments, $startDate, $endDate, 'eff_year');

            foreach ($filteredAssessments as $assess) {
                fputcsv($output, ['Assessment', 'Effective Year', $assess['eff_year']]);
                fputcsv($output, ['Assessment', 'Land Value', $assess['land_market_value']]);
                fputcsv($output, ['Assessment', 'Improvement Value', $assess['imp_val']]);
                fputcsv($output, ['Assessment', 'Total Value', $assess['total_value']]);
            }
        }

        // Always include these sections (not date-based)
        if ($section0 = $property['parcel']['sections']['0'][0][0] ?? null) {
            fputcsv($output, ['Ownership', 'Owner Name', $section0['OwnerName']]);
            fputcsv($output, ['Ownership', 'Legal Description', $section0['LegalDescription']]);
            fputcsv($output, ['Ownership', 'Parcel Area (SF)', $section0['ParcelAreaSF']]);
            fputcsv($output, ['Ownership', 'Parcel Area (Acres)', $section0['ParcelAcreage']]);
            fputcsv($output, ['Ownership', 'Neighborhood', $section0['Neighborhood']]);
        }

        if ($building = $property['parcel']['sections']['0'][1][0] ?? null) {
            fputcsv($output, ['Building', 'Type', $building['BuildingType']]);
            fputcsv($output, ['Building', 'Year Built', $building['YearBuilt']]);
            fputcsv($output, ['Building', 'Stories', $building['NumberofStories']]);
            fputcsv($output, ['Building', 'Bedrooms', $building['Bedrooms']]);
            fputcsv($output, ['Building', 'Full Baths', $building['FullBaths']]);
            fputcsv($output, ['Building', 'Half Baths', $building['HalfBaths']]);
            fputcsv($output, ['Building', 'Area', $building['FinishedLivingArea']]);
            fputcsv($output, ['Building', 'Heating', $building['Heating']]);
            fputcsv($output, ['Building', 'Cooling', $building['Cooling']]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Filter array by date range.
     */
    private function filterByDateRange(array $items, ?string $startDate, ?string $endDate, string $dateField): array
    {
        if (!$startDate && !$endDate) {
            return $items;
        }

        return array_filter($items, function ($item) use ($startDate, $endDate, $dateField) {
            $itemDate = $item[$dateField] ?? null;
            if (!$itemDate) return false;

            $itemDate = strtotime($itemDate);
            $startValid = !$startDate || $itemDate >= strtotime($startDate);
            $endValid = !$endDate || $itemDate <= strtotime($endDate . ' 23:59:59');

            return $startValid && $endValid;
        });
    }


}
