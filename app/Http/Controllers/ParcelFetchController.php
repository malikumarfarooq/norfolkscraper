<?php

namespace App\Http\Controllers;

use App\Jobs\FetchParcelDataJob;
use App\Models\FetchProgress;
use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParcelFetchController extends Controller
{
    public function index()
    {
        $progress = FetchProgress::firstOrCreate([], [
            'current_id' => 10000001,
            'is_running' => false,
            'should_stop' => false
        ]);
        return view('parcels.fetch', compact('progress'));
    }

//    public function startFetching(Request $request)
//    {
//        $progress = FetchProgress::firstOrCreate([], ['current_id' => 10000001]);
//
//        if ($progress->is_running) {
//            return response()->json([
//                'message' => 'Fetch already in progress',
//                'current_id' => $progress->current_id
//            ], 409);
//        }
//
//        $validated = $request->validate([
//            'max_id' => 'nullable|integer|min:'.$progress->current_id
//        ]);
//
//        $progress->update([
//            'is_running' => true,
//            'should_stop' => false,
//            'max_id' => $validated['max_id'] ?? null
//        ]);
//
//        Bus::dispatch(new FetchParcelDataJob($progress->current_id, $progress->max_id));
//
//        return response()->json([
//            'message' => 'Fetching started',
//            'current_id' => $progress->current_id,
//            'max_id' => $progress->max_id
//        ]);
//    }


    public function startFetching(Request $request)
    {
        $progress = FetchProgress::firstOrCreate([], ['current_id' => 10000001]);

        if ($progress->is_running) {
            return response()->json([
                'message' => 'Fetch already in progress',
                'current_id' => $progress->current_id
            ], 409);
        }

        $validated = $request->validate([
            'start_id' => 'required|integer|min:10000001',
            'max_id' => 'nullable|integer|min:'.$request->start_id
        ]);

        $progress->update([
            'current_id' => $validated['start_id'],
            'is_running' => true,
            'should_stop' => false,
            'max_id' => $validated['max_id'] ?? null
        ]);

        Bus::dispatch(new FetchParcelDataJob($progress->current_id, $progress->max_id));

        return response()->json([
            'message' => 'Fetching started',
            'current_id' => $progress->current_id,
            'max_id' => $progress->max_id
        ]);
    }

    public function stopFetching()
    {
        $progress = FetchProgress::firstOrCreate([], ['current_id' => 10000001]);

        if (!$progress->is_running) {
            return response()->json(['message' => 'No active fetch process'], 409);
        }

        $progress->update(['should_stop' => true]);
        return response()->json([
            'message' => 'Stopping fetch process',
            'current_id' => $progress->current_id
        ]);
    }

    public function getProgress()
    {
        $progress = FetchProgress::firstOrCreate([], [
            'current_id' => 10000001,
            'is_running' => false,
            'should_stop' => false
        ]);

        return response()->json([
            'current_id' => $progress->current_id,
            'max_id' => $progress->max_id,
            'is_running' => $progress->is_running,
            'should_stop' => $progress->should_stop
        ]);
    }

//    Generate csv
    public function exportCsv(): StreamedResponse
    {
        $filename = "parcels_" . date('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];

        $parcels = Parcel::all();

        $callback = function() use ($parcels) {
            $file = fopen('php://output', 'w');

            // Add CSV headers - now with First Name and Last Name separated
            fputcsv($file, [
                'ID',
                'Active',
                'Property Address',
                'Total Value',
                'Mailing Address',
                'First Name',  // New column
                'Last Name',   // New column
                'Property Use',
                'Building Type',
                'Year Built',
                'Stories',
                'Bedrooms',
                'Full Baths',
                'Half Baths',
                'Latest Sale Owner',
                'Latest Sale Date',
                'Latest Sale Price',
                'Latest Assessment Year',
                'Latest Total Value',
                'GPIN',
            ]);

            // Add data rows
            foreach ($parcels as $parcel) {
                // Split owner name into first and last name
                $ownerName = trim($parcel->owner_name);
                $nameParts = explode(' ', $ownerName);
                $firstName = $nameParts[0] ?? '';
                $lastName = implode(' ', array_slice($nameParts, 1)) ?? '';

                fputcsv($file, [
                    $parcel->id,
                    $parcel->active ? 'Yes' : 'No',
                    $parcel->property_address,
                    $parcel->total_value,
                    $parcel->mailing_address,
                    $firstName,  // First name
                    $lastName,    // Last name
                    $parcel->property_use,
                    $parcel->building_type,
                    $parcel->year_built,
                    $parcel->stories,
                    $parcel->bedrooms,
                    $parcel->full_baths,
                    $parcel->half_baths,
                    $parcel->latest_sale_owner,
                    $parcel->latest_sale_date,
                    $parcel->latest_sale_price,
                    $parcel->latest_assessment_year,
                    $parcel->latest_total_value,
                    $parcel->gpin,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
