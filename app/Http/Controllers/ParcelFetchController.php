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
            $bulkSize = 25;

            $query = Property::whereNotNull('tax_account_number');
            $totalAccounts = $query->count();

            if ($totalAccounts === 0) {
                throw new \Exception('No properties with tax account numbers found');
            }

            // ✅ First: Prepare the jobs
            $jobs = [];
            $currentGroup = [];

            $query->orderBy('id')
                ->chunk($chunkSize, function ($properties) use (&$jobs, &$currentGroup, $bulkSize) {
                    foreach ($properties as $property) {
                        $currentGroup[] = [
                            'tax_account_number' => $property->tax_account_number,
                            'property_id' => $property->id,
                        ];

                        if (count($currentGroup) >= $bulkSize) {
                            $jobs[] = new \App\Jobs\BulkFetchParcelDataJob($currentGroup);
                            $currentGroup = [];
                        }
                    }
                });

            if (!empty($currentGroup)) {
                $jobs[] = new \App\Jobs\BulkFetchParcelDataJob($currentGroup);
            }

            // ✅ Now dispatch the batch WITH jobs
            $batch = Bus::batch($jobs)
                ->name('Parcel Bulk Fetch - ' . now()->format('Y-m-d H:i'))
                ->allowFailures()
                ->onQueue('parcels')
                ->dispatch();

            // ✅ Save batch info AFTER dispatch
            ParcelFetchBatch::create([
                'batch_id' => $batch->id,
                'total_jobs' => $batch->totalJobs,
                'status' => 'pending',
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'batch_id' => $batch->id,
                'total_accounts' => $totalAccounts,
                'total_jobs' => $batch->totalJobs,
                'message' => 'Bulk batch processing started'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start bulk batch: ' . $e->getMessage());
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
                return response()->json([
                    'success' => false,
                    'error' => 'Batch not found'
                ], 404);
            }

            // Calculate accurate progress percentage
            $progress = ($batch->totalJobs > 0)
                ? (int) round(($batch->processedJobs() / $batch->totalJobs) * 100)
                : 0;

            // Update batch record in database
            $status = $this->determineBatchStatus($batch);
            $this->updateBatchRecord($batchId, $batch, $status);

            return response()->json([
                'success' => true,
                'progress' => $progress,
                'processedJobs' => $batch->processedJobs(),
                'totalJobs' => $batch->totalJobs,
                'status' => $status,
                'failedJobs' => $batch->failedJobs,
            ]);

        } catch (\Exception $e) {
            Log::error("Progress check failed for batch {$batchId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to check progress',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    protected function determineBatchStatus($batch): string
    {
        if ($batch->cancelled()) {
            return 'cancelled';
        }

        if ($batch->finished()) {
            return ($batch->failedJobs > 0) ? 'completed_with_errors' : 'completed';
        }

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


    public function stopFetching($batchId)
    {
        try {
            $batch = Bus::findBatch($batchId);

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found'
                ], 404);
            }

            if ($batch->cancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch already cancelled'
                ]);
            }

            $batch->cancel();

            // Update the batch record
            ParcelFetchBatch::where('batch_id', $batchId)->update([
                'status' => 'cancelled',
                'finished_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel batch: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
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
                '0$' => function($query) {
                    return $query->where(function($q) {
                        $q->where('latest_sale_price', 0)
                            ->orWhereNull('latest_sale_price');
                    });
                },
                '1$' => function($query) {
                    return $query->where('latest_sale_price', 1.00);
                },
                '2$' => function($query) {
                    return $query->where('latest_sale_price', 2.00);
                },
                'Other' => function($query) {
                    return $query->whereNotNull('latest_sale_price')
                        ->whereNotIn('latest_sale_price', [0, 1.00, 2.00]);
                }
            ];

            foreach ($groups as $group => $condition) {
                try {
                    Log::info("Starting export for group: {$group}");

                    $query = Parcel::query();
                    $condition($query)->chunk(500, function($parcels) use ($file, $group) {
                        foreach ($parcels as $parcel) {
                            fputcsv($file, array_merge([$group], $this->formatParcelRow($parcel)));
                        }
                        flush();
                    });

                    Log::info("Completed export for group: {$group}");
                } catch (\Exception $e) {
                    Log::error("Error exporting group {$group}: " . $e->getMessage());
                    throw $e;
                }
            }

            fclose($file);
        }, 200, $this->getCsvResponseHeaders($filename));
    }
    protected function getCsvHeaders(): array
    {
        return [
            'ID', 'Active', 'Property Address', 'Total Value',
//            'Mailing Address',
            'Mailing Street', 'Mailing City', 'Mailing State', 'Mailing Zip',

            'Last Name', 'First Name',

            'Property Use', 'Building Type', 'Year Built',
            'Stories', 'Bedrooms', 'Full Baths', 'Half Baths', 'Latest Sale Owner',
            'Latest Sale Date', 'Latest Sale Price',
//            'Latest Assessment Year',
            'Latest Total Value', 'GPIN'
        ];
    }

    protected function formatParcelRow(Parcel $parcel): array
    {

        $ownerName = $parcel->owner_name ? explode(' ', trim(str_replace(',', '', $parcel->owner_name)), 2) : [];

//        $ownerName = $parcel->owner_name ? explode(' ', $parcel->owner_name, 2) : [];
        // Parse mailing address into components
        $mailingParts = $this->parseMailingAddress($parcel->mailing_address);
        return [
            $parcel->id,
            $parcel->active ? 'Yes' : 'No',
            $this->escapeCsv($parcel->property_address),
            $this->formatCurrency($parcel->total_value),
//            $this->escapeCsv($parcel->mailing_address),

            $this->escapeCsv($mailingParts['street']),
            $this->escapeCsv($mailingParts['city']),
            $this->escapeCsv($mailingParts['state']),
            $this->escapeCsv($mailingParts['zip']),

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
//            $parcel->latest_assessment_year,
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
//        return '"' . str_replace('"', '""', $value) . '"';
        return str_replace('"', '', $value);
    }

    protected function formatCurrency($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Explicitly check for 0 values
        if ($value === 0 || $value === '0' || $value === '0.00' || $value === '$0.00') {
            return '$0.00';
        }

        // Handle both string ('$1.00') and numeric (1.00) inputs
        $numericValue = is_string($value) ? (float) str_replace(['$', ','], '', $value) : (float) $value;

        return '$' . number_format($numericValue, 2);
    }

    protected function parseMailingAddress(?string $address): array
    {
        $default = [
            'street' => '',
            'city' => '',
            'state' => '',
            'zip' => ''
        ];

        if (empty($address)) {
            return $default;
        }

        // Remove any double quotes if present
        $address = trim(str_replace('"', '', $address));

        // Try comma-separated format first (Street, City, State Zip)
        if (strpos($address, ',') !== false) {
            $parts = explode(',', $address);
            $street = trim($parts[0] ?? '');
            $city = trim($parts[1] ?? '');
            $stateZip = trim($parts[2] ?? '');
        }
        // Handle space-separated format (Street City State Zip)
        else {
            // Extract state and zip first
            if (preg_match('/([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $address, $matches)) {
                $state = $matches[1] ?? '';
                $zip = $matches[2] ?? '';
                $remaining = trim(str_replace($matches[0], '', $address));

                // Now find the city name (should be the last word before state)
                // Split remaining into street and city
                $cityParts = explode(' ', $remaining);
                $city = array_pop($cityParts);
                $street = implode(' ', $cityParts);

                return [
                    'street' => $street,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip
                ];
            }
            return $default;
        }

        // Handle state and zip extraction
        $state = '';
        $zip = '';
        if (!empty($stateZip)) {
            if (preg_match('/([A-Z]{2})\s*(\d{5}(?:-\d{4})?)/', $stateZip, $matches)) {
                $state = $matches[1] ?? '';
                $zip = $matches[2] ?? '';
            } elseif (preg_match('/([A-Z]{2})/', $stateZip, $matches)) {
                $state = $matches[1] ?? '';
            }
        }

        return [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip
        ];
    }
}
