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
//            $gpin = $property['parcel']['header']['GPIN'] ?? null;
//            dd($gpin);

            // Initialize complaints variable
            $complaints = null;

            // If we have a GPIN, fetch code enforcement data
//            if ($gpin) {
//                $complaintsResponse = Http::get("https://api.spatialest.com/v1/va/norfolk/code-enforcements/{$gpin}");
//
//                if ($complaintsResponse->successful()) {
////                    $complaints = $complaintsResponse->json();
//                    $complaints = $complaintsResponse->json()['payload']['code-enforcement'] ?? [];
//                }else{
//                    $complaints = null;
//                }
//            }else{
//                $complaints = null;
//            }
//                dd($complaints);
            return view('property_details',
                compact(
                    'property',
                    'id',
//                    'complaints'
                ));
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
     * Split a name into first and last name components
     */
    private function splitName($name): array
    {
        $firstName = '';
        $lastName = '';

        if (!empty($name)) {
            // Handle names with commas (like "Smith, John")
            if (strpos($name, ',') !== false) {
                $parts = explode(',', $name, 2);
                $lastName = trim($parts[0]);
                $firstName = trim($parts[1] ?? '');
            }
            // Handle names with "&" for multiple owners (like "Devon & Neal")
            elseif (strpos($name, '&') !== false) {
                $firstName = $name; // Keep full name as first name
                $lastName = ''; // No last name for multiple owners
            }
            // Standard first/last name splitting
            else {
                $parts = explode(' ', $name);
                $lastName = array_pop($parts);
                $firstName = implode(' ', $parts);
            }
        }

        return [$firstName, $lastName];
    }

    /**
     * Generate CSV with all data in single row plus full history
     */
    private function generateSingleRowCsv(array $property, Request $request)
    {
        $output = fopen('php://output', 'w');

        // Prepare headers
        $headers = [
            // Basic Information
            'Property ID', 'Active',

            // Parcel Header
            'Property Address', 'Total Value', 'Mailing Address',

            // Ownership and Legal Info
            'Owner First Name', 'Owner Last Name', 'PropertyUse',

            // Building Details
            'Building Type', 'Year Built', 'Stories', 'Bedrooms', 'Full Baths', 'Half Baths',

            // Sales History (latest)
            'Latest Sale Owner', 'Latest Sale Date', 'Latest Sale Price',

            // Assessment (latest)
            'Latest Assessment Year', 'Latest Total Value'
        ];

        // Get data sections
        $header = $property['parcel']['header'] ?? [];
        $ownership = $property['parcel']['sections']['0'][0][0] ?? [];
        $building = $property['parcel']['sections']['0'][1][0] ?? [];

        // Process owner name
        [$firstName, $lastName] = $this->splitName($ownership['OwnerName'] ?? '');

        // Get all sales data (not just latest)
        $sales = $this->filterSales($property['parcel']['sections']['1'][0] ?? [], $request);
        $latestSale = $sales[0] ?? [];

        // Get all assessments
        $assessments = $this->filterAssessments($property['parcel']['sections']['1'][1] ?? [], $request);
        $latestAssessment = $assessments[0] ?? [];

        // Prepare data row
        $data = [
            // Basic Information
            $property['id'] ?? '',
            $property['active'] ? 'Yes' : 'No',

            // Parcel Header
            $header['PropertyStreet'] ?? '',
            $header['total_value'] ?? '',
            $header['MailingAddress'] ?? '',

            // Ownership and Legal Info
            $firstName,
            $lastName,
            $ownership['PropertyUse'] ?? '',

            // Building Details
            $building['BuildingType'] ?? '',
            $building['YearBuilt'] ?? '',
            $building['NumberofStories'] ?? '',
            $building['Bedrooms'] ?? '',
            $building['FullBaths'] ?? '',
            $building['HalfBaths'] ?? '',

            // Latest Sale
            $latestSale['owners'] ?? '',
            $latestSale['saledate'] ?? '',
            $latestSale['saleprice'] ?? '',

            // Latest Assessment
            $latestAssessment['eff_year'] ?? '',
            $latestAssessment['total_value'] ?? ''
        ];

        // Write to CSV
        fputcsv($output, $headers);
        fputcsv($output, $data);

        // Always include all sales data
        if (!empty($sales)) {
            fputcsv($output, []); // Empty row separator
            fputcsv($output, ['Sales History']);
            fputcsv($output, ['Owner First Name', 'Owner Last Name', 'Transfer Date', 'Sale Price', 'Instrument', 'Book/Page']);

            foreach ($sales as $sale) {
                [$saleFirstName, $saleLastName] = $this->splitName($sale['owners'] ?? '');

                fputcsv($output, [
                    $saleFirstName,
                    $saleLastName,
                    $sale['saledate'] ?? '',
                    $sale['saleprice'] ?? '',
                    $sale['instrument'] ?? '',
                    $sale['book_page'] ?? ''
                ]);
            }
        }

        // Always include all assessments
        if (!empty($assessments)) {
            fputcsv($output, []); // Empty row separator
            fputcsv($output, ['Assessment History']);
            fputcsv($output, ['Effective Year', 'Total Value', 'Land Value', 'Building Value']);

            foreach ($assessments as $assessment) {
                fputcsv($output, [
                    $assessment['eff_year'] ?? '',
                    $assessment['total_value'] ?? '',
                    $assessment['land_value'] ?? '',
                    $assessment['building_value'] ?? ''
                ]);
            }
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
}
