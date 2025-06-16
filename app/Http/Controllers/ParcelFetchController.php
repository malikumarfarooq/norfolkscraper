<?php

namespace App\Http\Controllers;

use App\Jobs\FetchParcelDataJob;
use App\Models\Parcel;
use App\Models\Property;
use App\Models\ParcelFetchBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParcelFetchController extends Controller
{
    public function index()
    {
        $lastBatch = ParcelFetchBatch::latest()->first();
        return view('parcels.fetch', ['lastBatch' => $lastBatch]);
    }

    public function startFetching(Request $request)
    {
        $request->validate([
            'chunk_size' => 'sometimes|integer|min:50|max:500'
        ]);

        try {
            $chunkSize = $request->input('chunk_size', 200);
            $query = Property::whereNotNull('tax_account_number');
            $totalAccounts = $query->count();

            if ($totalAccounts === 0) {
                throw new \Exception('No properties with tax account numbers found');
            }

            $batch = Bus::batch([])
                ->name('Parcel Fetch - ' . now()->format('Y-m-d H:i'))
                ->allowFailures()
                ->onQueue('parcels')
                ->dispatch();

            ParcelFetchBatch::create([
                'batch_id' => $batch->id,
                'total_jobs' => $totalAccounts,
                'status' => 'pending',
                'started_at' => now()
            ]);

            $query->orderBy('id')
                ->chunkById($chunkSize, function($properties) use ($batch) {
                    $jobs = $properties->map(function($property) use ($batch) {
                        $job = new FetchParcelDataJob(
                            (string)$property->tax_account_number,
                            $property->id,
                            $batch->id
                        );
                        $job->onQueue('parcels');
                        return $job;
                    });
                    $batch->add($jobs);
                });

            return response()->json([
                'success' => true,
                'batch_id' => $batch->id,
                'total_accounts' => $totalAccounts,
                'message' => 'Batch processing started'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start batch: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkProgress($batchId)
    {
        try {
            $batch = Bus::findBatch($batchId);
            if (!$batch) {
                return response()->json(['error' => 'Batch not found'], 404);
            }

            $status = $this->determineBatchStatus($batch);
            $this->updateBatchRecord($batchId, $batch, $status);

            return response()->json([
                'id' => $batch->id,
                'totalJobs' => $batch->totalJobs,
                'pendingJobs' => $batch->pendingJobs,
                'failedJobs' => $batch->failedJobs,
                'processedJobs' => $batch->processedJobs(),
                'progress' => $batch->progress(),
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error("Progress check failed: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = "parcels_" . now()->format('Y-m-d_His') . ".csv";

        return Response::stream(function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, $this->getCsvHeaders());

            Parcel::chunk(1000, function($parcels) use ($file) {
                foreach ($parcels as $parcel) {
                    fputcsv($file, $this->formatParcelRow($parcel));
                }
            });

            fclose($file);
        }, 200, $this->getCsvResponseHeaders($filename));
    }

    public function exportBySaleGroups(): StreamedResponse
    {
        $filename = "parcels_by_sale_groups_" . now()->format('Y-m-d_His') . ".csv";

        return Response::stream(function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, array_merge(['Sale Group'], $this->getCsvHeaders()));

            $groups = [
                '0$' => fn($q) => $q->whereIn('latest_sale_price', ['$0.00', '0'])->orWhereNull('latest_sale_price'),
                '1$' => fn($q) => $q->where('latest_sale_price', '$1.00'),
                '2$' => fn($q) => $q->where('latest_sale_price', '$2.00'),
                'Other' => fn($q) => $q->whereNotNull('latest_sale_price')
                    ->whereNotIn('latest_sale_price', ['$0.00', '0', '$1.00', '$2.00'])
            ];

            foreach ($groups as $group => $condition) {
                Parcel::where($condition)->chunk(1000, function($parcels) use ($file, $group) {
                    foreach ($parcels as $parcel) {
                        fputcsv($file, array_merge([$group], $this->formatParcelRow($parcel)));
                    }
                });
            }

            fclose($file);
        }, 200, $this->getCsvResponseHeaders($filename));
    }

    protected function determineBatchStatus($batch): string
    {
        if ($batch->cancelledAt) return 'cancelled';
        if ($batch->finishedAt) return $batch->failedJobs > 0 ? 'failed' : 'completed';
        return 'processing';
    }

    protected function updateBatchRecord($batchId, $batch, $status): void
    {
        ParcelFetchBatch::updateOrCreate(
            ['batch_id' => $batchId],
            [
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'status' => $status,
                'finished_at' => $batch->finishedAt ? now() : null
            ]
        );
    }

    protected function getCsvHeaders(): array
    {
        return [
            'ID', 'Active', 'Property Address', 'Total Value', 'Mailing Address',
            'First Name', 'Last Name', 'Property Use', 'Building Type', 'Year Built',
            'Stories', 'Bedrooms', 'Full Baths', 'Half Baths', 'Latest Sale Owner',
            'Latest Sale Date', 'Latest Sale Price', 'Latest Assessment Year',
            'Latest Total Value', 'GPIN'
        ];
    }

    protected function formatParcelRow(Parcel $parcel): array
    {
        $ownerName = $parcel->owner_name ? explode(' ', $parcel->owner_name, 2) : [];

        return [
            $parcel->id,
            $parcel->active ? 'Yes' : 'No',
            $this->escapeCsv($parcel->property_address),
            $this->formatCurrency($parcel->total_value),
            $this->escapeCsv($parcel->mailing_address),
            $this->escapeCsv($ownerName[0] ?? ''),
            $this->escapeCsv($ownerName[1] ?? ''),
            $this->escapeCsv($parcel->property_use),
            $this->escapeCsv($parcel->building_type),
            $parcel->year_built,
            $parcel->stories,
            $parcel->bedrooms,
            $parcel->full_baths,
            $parcel->half_baths,
            $this->escapeCsv($parcel->latest_sale_owner),
            $parcel->latest_sale_date,
            $this->formatCurrency($parcel->latest_sale_price),
            $parcel->latest_assessment_year,
            $this->formatCurrency($parcel->latest_total_value),
            $parcel->gpin,
        ];
    }

    protected function getCsvResponseHeaders($filename): array
    {
        return [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'X-Vapor-Base64-Encode' => 'True',
        ];
    }

    protected function escapeCsv(?string $value): string
    {
        if ($value === null) return '';
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function formatCurrency($value): string
    {
        if ($value === null) return '';
        return '$' . number_format((float)$value, 2);
    }
}
