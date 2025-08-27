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
use Illuminate\Support\Facades\DB;
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
            $bulkSize = 35;

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
                ? (int)round(($batch->processedJobs() / $batch->totalJobs) * 100)
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

        return Response::stream(function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, $this->getCsvHeaders());

            Parcel::chunk(1000, function ($parcels) use ($file) {
                foreach ($parcels as $parcel) {
                    fputcsv($file, $this->formatParcelRow($parcel));
                }
            });

            fclose($file);
        }, 200, $this->getCsvResponseHeaders($filename));
    }

    public function exportBySaleGroups(): StreamedResponse
    {
        $filename = "parcels_sorted_" . now()->format('Y-m-d_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        return Response::stream(function() {
            $file = fopen('php://output', 'w');

            // BOM for Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $this->getCsvHeaders());

            // Use raw database cursor for maximum efficiency
            $connection = DB::connection()->getPdo();
            $query = "
            SELECT * FROM parcels
            ORDER BY
                CASE WHEN latest_sale_price IS NULL THEN 1 ELSE 0 END,
                latest_sale_price ASC,
                id ASC
        ";
            $statement = $connection->prepare($query);
            $statement->execute();

            $count = 0;
            $lastFlush = microtime(true);

            while ($parcel = $statement->fetch(PDO::FETCH_OBJ)) {
                fputcsv($file, $this->formatParcelRow((array)$parcel));
                $count++;

                // Flush every 100 records or every 0.5 seconds
                if ($count % 100 === 0 || microtime(true) - $lastFlush > 0.5) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    $lastFlush = microtime(true);
                }
            }

            fclose($file);
        }, 200, $headers);
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
        // Normalize and clean input
        $stringValue = trim((string)$value);
        $cleanedValue = str_replace(['$', ','], '', $stringValue);

        // Convert to float
        $numericValue = is_numeric($cleanedValue) ? (float)$cleanedValue : null;

        if ($numericValue === null) {
            return ''; // Return blank for truly non-numeric/null values
        }

        // Explicitly handle zero
        if (abs($numericValue) < 0.00001) {
            return '$0.00';
        }

        return '$' . number_format($numericValue, 2);
    }


    protected array $knownCities = [
        'colorado springs',
        'foster city',
        'fort worth',
        'kansas city',
        'las vegas',
        'long beach',
        'los angeles',
        'new york',
        'san antonio',
        'san diego',
        'san francisco',
        'universal city',
        'virginia beach',
        'winston salem',
        'salt lake city',
        'glen allen',
        'oklahoma city',
        'newport news',
        'tysons corner',
        'rowland heights',
        'gales ferry',
        'whitefish bay',
        'potomac falls',
        'pembroke pines',
        'cherry hill',
        'laguna beach',
        'wappingers falls',
        'huntington beach',
        'manhattan beach',
        'atlantic beach',
        'apollo beach',
        'delray beach',
        'elizabeth city',
        'north chesterfield',
        'great falls',
        'newport beach',
    ];


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

            // Extract state and zip from stateZip
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

        // Handle space-separated format (Street City State Zip)
        if (preg_match('/([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $address, $matches)) {
            $state = $matches[1];
            $zip = $matches[2];

            // Remove state and zip from the address
            $remaining = trim(str_replace($matches[0], '', $address));

            // Split remaining into words
            $words = explode(' ', $remaining);

            // Try to detect city name from the end (max 3 words)
            $city = '';
            $street = '';
            for ($i = count($words) - 1; $i >= max(0, count($words) - 3); $i--) {
                $possibleCity = implode(' ', array_slice($words, $i));
                if (in_array(strtolower($possibleCity), $this->knownCities)) {
                    $city = $possibleCity;
                    $street = implode(' ', array_slice($words, 0, $i));
                    return [
                        'street' => $street,
                        'city' => $city,
                        'state' => $state,
                        'zip' => $zip
                    ];
                }
            }

            // Fallback: assume last word is city
            $cityParts = $words;
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
}
