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
            $filename = "property-{$id}-" . date('Y-m-d') . ".csv";

            return Response::stream(
                function () use ($property, $request) {
                    $this->generateSingleRowCsv($property, $request);
                },
                200,
                [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename={$filename}",
                ]
            );
        }

        abort(404, 'Record not found');
    }

    /**
     * Generate CSV with all data in single row
     */
    private function generateSingleRowCsv(array $property, Request $request)
    {
        $output = fopen('php://output', 'w');

        // Prepare headers
        $headers = [
            // Basic Information
            'Property ID', 'City Latitude', 'City Longitude', 'Active',

            // Parcel Header
            'Street', 'Total Value', 'Mailing Address', 'GPIN',

            // Ownership
            'Owner Name', 'Legal Description', 'Parcel Area (SF)', 'Parcel Area (Acres)', 'Neighborhood',

            // Building Details
            'Building Type', 'Year Built', 'Stories', 'Bedrooms', 'Full Baths', 'Half Baths',
            'Finished Living Area', 'Heating', 'Cooling',

            // Sales History (latest)
            'Latest Sale Owner', 'Latest Sale Date', 'Latest Sale Price', 'Latest Sale Doc #',

            // Assessment (latest)
            'Latest Assessment Year', 'Latest Land Value', 'Latest Improvement Value', 'Latest Total Value'
        ];

        // Get data sections
        $header = $property['parcel']['header'] ?? [];
        $ownership = $property['parcel']['sections']['0'][0][0] ?? [];
        $building = $property['parcel']['sections']['0'][1][0] ?? [];

        // Get latest sale
        $sales = $this->filterSales($property['parcel']['sections']['1'][0] ?? [], $request);
        $latestSale = $sales[0] ?? [];

        // Get latest assessment
        $assessments = $this->filterAssessments($property['parcel']['sections']['1'][1] ?? [], $request);
        $latestAssessment = $assessments[0] ?? [];

        // Prepare data row
        $data = [
            // Basic Information
            $property['id'] ?? '',
            $property['cty'] ?? '',
            $property['ctx'] ?? '',
            $property['active'] ? 'Yes' : 'No',

            // Parcel Header
            $header['PropertyStreet'] ?? '',
            $header['total_value'] ?? '',
            $header['MailingAddress'] ?? '',
            $header['GPIN'] ?? '',

            // Ownership
            $ownership['OwnerName'] ?? '',
            $ownership['LegalDescription'] ?? '',
            $ownership['ParcelAreaSF'] ?? '',
            $ownership['ParcelAcreage'] ?? '',
            $ownership['Neighborhood'] ?? '',

            // Building Details
            $building['BuildingType'] ?? '',
            $building['YearBuilt'] ?? '',
            $building['NumberofStories'] ?? '',
            $building['Bedrooms'] ?? '',
            $building['FullBaths'] ?? '',
            $building['HalfBaths'] ?? '',
            $building['FinishedLivingArea'] ?? '',
            $building['Heating'] ?? '',
            $building['Cooling'] ?? '',

            // Latest Sale
            $latestSale['owners'] ?? '',
            $latestSale['saledate'] ?? '',
            $latestSale['saleprice'] ?? '',
            $latestSale['docnum'] ?? '',

            // Latest Assessment
            $latestAssessment['eff_year'] ?? '',
            $latestAssessment['land_market_value'] ?? '',
            $latestAssessment['imp_val'] ?? '',
            $latestAssessment['total_value'] ?? ''
        ];

        // Write to CSV
        fputcsv($output, $headers);
        fputcsv($output, $data);

        // Add sales history as additional rows if needed
        if ($request->input('full_history')) {
            $this->appendSalesHistory($output, $sales);
            $this->appendAssessments($output, $assessments);
        }

        fclose($output);
    }

    /**
     * Filter sales by date range
     */
    private function filterSales(array $sales, Request $request): array
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate && !$endDate) {
            return $sales;
        }

        return array_filter($sales, function ($sale) use ($startDate, $endDate) {
            $saleDate = $sale['saledate'] ?? null;
            if (!$saleDate) return false;

            $saleDate = strtotime($saleDate);
            $startValid = !$startDate || $saleDate >= strtotime($startDate);
            $endValid = !$endDate || $saleDate <= strtotime($endDate . ' 23:59:59');

            return $startValid && $endValid;
        });
    }

    /**
     * Filter assessments by date range
     */
    private function filterAssessments(array $assessments, Request $request): array
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate && !$endDate) {
            return $assessments;
        }

        return array_filter($assessments, function ($assessment) use ($startDate, $endDate) {
            $year = $assessment['eff_year'] ?? null;
            if (!$year) return false;

            $assessmentDate = strtotime("$year-07-01"); // Assuming assessments are July 1
            $startValid = !$startDate || $assessmentDate >= strtotime($startDate);
            $endValid = !$endDate || $assessmentDate <= strtotime($endDate . ' 23:59:59');

            return $startValid && $endValid;
        });
    }

    /**
     * Append sales history as additional rows
     */
    private function appendSalesHistory($output, array $sales)
    {
        if (empty($sales)) return;

        fputcsv($output, []); // Empty row separator
        fputcsv($output, ['Sales History']);
        fputcsv($output, ['Owner', 'Date', 'Price', 'Doc #']);

        foreach ($sales as $sale) {
            fputcsv($output, [
                $sale['owners'] ?? '',
                $sale['saledate'] ?? '',
                $sale['saleprice'] ?? '',
                $sale['docnum'] ?? ''
            ]);
        }
    }

    /**
     * Append assessments as additional rows
     */
    private function appendAssessments($output, array $assessments)
    {
        if (empty($assessments)) return;

        fputcsv($output, []); // Empty row separator
        fputcsv($output, ['Assessment History']);
        fputcsv($output, ['Year', 'Land Value', 'Improvement Value', 'Total Value']);

        foreach ($assessments as $assessment) {
            fputcsv($output, [
                $assessment['eff_year'] ?? '',
                $assessment['land_market_value'] ?? '',
                $assessment['imp_val'] ?? '',
                $assessment['total_value'] ?? ''
            ]);
        }
    }


}
