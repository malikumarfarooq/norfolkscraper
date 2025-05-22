<?php

namespace App\Http\Controllers;

use App\Jobs\FetchParcelDataJob;
use App\Models\FetchProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ParcelFetchController extends Controller
{
    public function index()
    {
        Log::info('Fetch page accessed');
        $progress = FetchProgress::firstOrCreate([], ['current_id' => 10000001]);
        return view('parcels.fetch', compact('progress'));
    }

    public function startFetching(Request $request)
    {
        Log::info('Start fetching request received', ['input' => $request->all()]);

        $progress = FetchProgress::firstOrCreate([], ['current_id' => 10000001]);

        if ($progress->is_running) {
            Log::warning('Fetch already running');
            return response()->json([
                'message' => 'Fetching is already in progress',
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

        Log::info('Dispatching fetch job', [
            'current_id' => $progress->current_id,
            'max_id' => $progress->max_id
        ]);

        Bus::dispatch(new FetchParcelDataJob($progress->current_id, $progress->max_id));

        return response()->json([
            'message' => 'Fetching started successfully',
            'current_id' => $progress->current_id,
            'max_id' => $progress->max_id
        ]);
    }

    public function stopFetching()
    {
        Log::info('Stop fetching request received');

        $progress = FetchProgress::first();
        if (!$progress) {
            Log::error('No fetch progress record found');
            return response()->json(['message' => 'No active fetching process'], 404);
        }

        if (!$progress->is_running) {
            Log::warning('Fetch not running when stop requested');
            return response()->json(['message' => 'No active fetching process'], 409);
        }

        $progress->update(['should_stop' => true]);
        Log::info('Fetch stop signal sent', ['current_id' => $progress->current_id]);

        return response()->json([
            'message' => 'Fetching will stop after current record',
            'current_id' => $progress->current_id
        ]);
    }

    public function getProgress()
    {
        $progress = FetchProgress::firstOrCreate([], ['current_id' => 10000001]);

        return response()->json([
            'current_id' => $progress->current_id,
            'max_id' => $progress->max_id,
            'is_running' => $progress->is_running,
            'should_stop' => $progress->should_stop
        ]);
    }
}
