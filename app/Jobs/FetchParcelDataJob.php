<?php

namespace App\Jobs;

use App\Models\Parcel;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class FetchParcelDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected string $taxAccountNumber;
    protected int $propertyId;
    public $batchId;

    public function __construct(string $taxAccountNumber, int $propertyId, string $batchId = null)
    {
        $this->taxAccountNumber = $taxAccountNumber;
        $this->propertyId = $propertyId;
        $this->batchId = $batchId;
    }

    public function handle(): void
    {
        // Check if batch was cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            Log::info('Job cancelled - batch cancelled', [
                'tax_account' => $this->taxAccountNumber,
                'property_id' => $this->propertyId
            ]);
            return;
        }

        // Skip if tax account number already exists in parcels table
        if (Parcel::where('gpin', $this->taxAccountNumber)->exists()) {
            Log::info('Skipping fetch - tax account already exists in parcels', [
                'tax_account' => $this->taxAccountNumber,
                'property_id' => $this->propertyId
            ]);
            return;
        }

        $startTime = microtime(true);

        try {
            // Make API request to fetch parcel data
            $response = $this->makeApiRequest();

            if ($response->successful()) {
                // Process successful response
                $this->processResponse($response->json());

            } elseif ($response->status() === 404) {
                // Handle not found response
                Log::warning("Parcel not found in API", [
                    'tax_account' => $this->taxAccountNumber,
                    'property_id' => $this->propertyId
                ]);

            } else {
                // Handle other API errors
                $this->handleApiError($response);
            }

        } catch (\Throwable $e) {
            // Handle exceptions
            $this->handleJobFailure($e);
            throw $e;

        } finally {
            // Log completion
            Log::info('Job processing completed', [
                'tax_account' => $this->taxAccountNumber,
                'property_id' => $this->propertyId,
                'duration_sec' => round(microtime(true) - $startTime, 2)
            ]);
        }
    }
    protected function makeApiRequest()
    {
        $url = "https://air.norfolk.gov/api/v1/recordcard/{$this->taxAccountNumber}";

        return Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel/ParcelFetcher/1.0',
            'X-Property-ID' => $this->propertyId
        ])
            ->timeout(60)
            ->retry(3, 5000, function($exception) {
                Log::warning('API request failed, retrying...', [
                    'tax_account' => $this->taxAccountNumber,
                    'error' => $exception->getMessage()
                ]);
                return true;
            })
            ->get($url);
    }

    protected function processResponse(array $data): void
    {
        if (empty($data['parcel']['header'])) {
            throw new \Exception('Invalid API response - missing parcel header');
        }

        $parcelData = $this->transformData($data);
        $this->saveParcel($parcelData);
    }

    protected function transformData(array $data): array
    {
        $header = $data['parcel']['header'];
        $sections = $data['parcel']['sections'] ?? [];

        $ownerInfo = $this->getNestedData($sections, ['0', '0', '0']);
        $buildingInfo = $this->getNestedData($sections, ['0', '1', '0']);
        $salesHistory = $this->getNestedData($sections, ['1', '0']) ?? [];
        $assessments = $this->getNestedData($sections, ['1', '1']) ?? [];

        $latestSale = $salesHistory[0] ?? [];
        $latestAssessment = $assessments[0] ?? [];

        return [
            'id' => $header['Parcel_id'] ?? $data['id'] ?? null,
            'property_id' => $this->propertyId,
            'active' => $data['active'] ?? true,
            'property_address' => $header['PropertyStreet'] ?? null,
            'mailing_address' => $header['MailingAddress'] ?? null,
            'gpin' => $header['GPIN'] ?? null,
            'owner_name' => $ownerInfo['OwnerName'] ?? null,
            'property_use' => $ownerInfo['PropertyUse'] ?? null,
            'building_type' => $buildingInfo['BuildingType'] ?? null,
            'year_built' => $this->parseInt($buildingInfo['YearBuilt'] ?? null),
            'stories' => $this->parseFloat($buildingInfo['NumberofStories'] ?? null),
            'bedrooms' => $this->parseInt($buildingInfo['Bedrooms'] ?? null),
            'full_baths' => $this->parseInt($buildingInfo['FullBaths'] ?? null),
            'half_baths' => $this->parseInt($buildingInfo['HalfBaths'] ?? null),
            'latest_sale_owner' => $this->cleanString($latestSale['owners'] ?? null),
            'latest_sale_date' => $this->parseDate($latestSale['saledate'] ?? null),
            'latest_sale_price' => $this->parseCurrency($latestSale['saleprice'] ?? null),
            'latest_sale_docnum' => $this->cleanString($latestSale['docnum'] ?? null),
            'latest_assessment_year' => $this->parseAssessmentYear($latestAssessment['eff_year'] ?? null),
            'land_value' => $this->parseCurrency($latestAssessment['land_market_value'] ?? null),
            'improvement_value' => $this->parseCurrency($latestAssessment['imp_val'] ?? null),
            'latest_total_value' => $this->parseCurrency($latestAssessment['total_value'] ?? null),
            'total_value' => $this->parseCurrency($header['total_value'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function saveParcel(array $data): Parcel
    {
        return Parcel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    // Helper methods
    protected function getNestedData(array $data, array $keys)
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }

    protected function parseInt($value): ?int
    {
        return is_numeric($value) ? (int)$value : null;
    }

    protected function parseFloat($value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    protected function parseCurrency($value): ?float
    {
        if ($value === null) return null;
        return (float)preg_replace('/[^0-9.]/', '', $value);
    }

    protected function parseDate(?string $date): ?string
    {
        try {
            return $date ? Carbon::createFromFormat('m/d/Y', $date)->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseAssessmentYear(?string $date): ?int
    {
        try {
            return $date ? Carbon::createFromFormat('m/d/Y', $date)->year : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function cleanString(?string $value): ?string
    {
        return $value ? trim($value) : null;
    }

    protected function handleApiError($response): void
    {
        $errorData = [
            'tax_account' => $this->taxAccountNumber,
            'property_id' => $this->propertyId,
            'status' => $response->status(),
            'response' => $response->body()
        ];

        Log::error("API request failed", $errorData);
        throw new \Exception("API request failed with status: {$response->status()}");
    }

    protected function handleJobFailure(Throwable $e): void
    {
        Log::error("Job failed", [
            'tax_account' => $this->taxAccountNumber,
            'property_id' => $this->propertyId,
            'error' => $e->getMessage(),
            'exception' => get_class($e)
        ]);

        if ($this->batch()) {
            $this->batch()->increment('failed_jobs');
        }
    }
    public function failed(Throwable $exception): void
    {
        Log::critical('Job failed permanently', [
            'tax_account' => $this->taxAccountNumber,
            'property_id' => $this->propertyId,
            'error' => $exception->getMessage()
        ]);
    }
}
