<?php

namespace App\Http\Controllers;

use App\Jobs\FetchParcelDataJob;
use App\Models\FetchProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

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
            'max_id' => 'nullable|integer|min:'.$progress->current_id
        ]);

        $progress->update([
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

    public function exportToCSV()
    {
        $filename = 'parcels_export.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return Response::stream(function () {
            $handle = fopen('php://output', 'w');

            ob_start(); // <- Start output buffering

            fputcsv($handle, [
                'ID',
                'Active',
                'Property Address',
                'Total Value',
                'Mailing Address',
                'Owner Name',
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

            Parcel::chunk(1000, function ($parcels) use ($handle) {
                foreach ($parcels as $parcel) {
                    fputcsv($handle, [
                        $parcel->id,
                        $parcel->active ? 'Active' : 'Inactive',
                        $parcel->property_address,
                        $parcel->total_value,
                        $parcel->mailing_address,
                        $parcel->owner_name,
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
            });

            fclose($handle);
            ob_end_flush(); // <- Ensure buffer is flushed
        }, 200, $headers);
    }


}
