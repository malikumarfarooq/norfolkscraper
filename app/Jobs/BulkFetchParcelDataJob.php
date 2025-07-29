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

class BulkFetchParcelDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 300;
    public $tries = 2;
    public $backoff = [30, 90];

    protected array $propertyBatch;

    public function __construct(array $propertyBatch)
    {
        $this->propertyBatch = $propertyBatch;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            Log::info('Bulk job cancelled.');
            return;
        }

        $results = [];

        foreach ($this->propertyBatch as $item) {
            $taxAccount = $item['tax_account_number'];

//            if (Parcel::where('gpin', $taxAccount)->exists()) {
//                Log::info("Skipping duplicate gpin: {$taxAccount}");
//                continue;
//            }

            try {
                $response = $this->fetchApi($taxAccount);

                if ($response->successful()) {
                    $data = $response->json();
                    $parcel = $this->transformData($data);
                    if (!empty($parcel['gpin'])) {
                        $results[] = $parcel;
                    }
                } elseif ($response->status() === 404) {
                    Log::warning("Not found: {$taxAccount}");
                } else {
                    Log::error("Failed: {$taxAccount} - " . $response->body());
                }
            } catch (Throwable $e) {
                Log::error("Exception for {$taxAccount}: " . $e->getMessage());
            }
        }

        if (!empty($results)) {
            Parcel::upsert($results, ['id'], array_keys($results[0]));
            Log::info("Inserted " . count($results) . " parcels in bulk.");
        }
    }

    protected function fetchApi(string $taxAccount)
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel/ParcelFetcher/1.0',
        ])
            ->timeout(60)
            ->retry(3, 5000)
            ->get("https://air.norfolk.gov/api/v1/recordcard/{$taxAccount}");
    }

    protected function transformData(array $data): array
    {
        $header = $data['parcel']['header'] ?? [];
        $sections = $data['parcel']['sections'] ?? [];

        $ownerInfo = $this->getNested($sections, ['0', '0', '0']);
        $buildingInfo = $this->getNested($sections, ['0', '1', '0']);
        $salesHistory = $this->getNested($sections, ['1', '0']) ?? [];
        $assessments = $this->getNested($sections, ['1', '1']) ?? [];

        $latestSale = $salesHistory[0] ?? [];
        $latestAssessment = $assessments[0] ?? [];

        return [
            'id' => $header['Parcel_id'] ?? null,
            'active' => $data['active'] ?? true,
            'property_address' => $header['PropertyStreet'] ?? null,
            'mailing_address' => $header['MailingAddress'] ?? null,
            'gpin' => $header['GPIN'] ?? null,
            'owner_name' => $ownerInfo['OwnerName'] ?? null,
            'property_use' => $ownerInfo['PropertyUse'] ?? null,
            'building_type' => $buildingInfo['BuildingType'] ?? null,
            'year_built' => $this->toInt($buildingInfo['YearBuilt'] ?? null),
            'stories' => $this->toFloat($buildingInfo['NumberofStories'] ?? null),
            'bedrooms' => $this->toInt($buildingInfo['Bedrooms'] ?? null),
            'full_baths' => $this->toInt($buildingInfo['FullBaths'] ?? null),
            'half_baths' => $this->toInt($buildingInfo['HalfBaths'] ?? null),
            'latest_sale_owner' => trim($latestSale['owners'] ?? ''),
            'latest_sale_date' => $this->parseDate($latestSale['saledate'] ?? null),
            'latest_sale_price' => $this->parseCurrency($latestSale['saleprice'] ?? null),
//            'latest_assessment_year' => $this->parseAssessmentYear($latestAssessment['eff_year'] ?? null),
            'latest_total_value' => $this->parseCurrency($latestAssessment['total_value'] ?? null),
            'total_value' => $this->parseCurrency($header['total_value'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function getNested(array $array, array $keys)
    {
        foreach ($keys as $key) {
            if (!isset($array[$key])) return null;
            $array = $array[$key];
        }
        return $array;
    }

    protected function toInt($val): ?int
    {
        return is_numeric($val) ? (int)$val : null;
    }

    protected function toFloat($val): ?float
    {
        return is_numeric($val) ? (float)$val : null;
    }

    protected function parseCurrency($val): ?float
    {
        return is_null($val) ? null : (float)preg_replace('/[^0-9.]/', '', $val);
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
}
