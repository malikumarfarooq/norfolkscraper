<?php

namespace App\Jobs;

use App\Models\Parcel;
use App\Models\FetchProgress;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $currentId;
    protected ?int $maxId;

    public function __construct(int $currentId, ?int $maxId = null)
    {
        $this->currentId = $currentId;
        $this->maxId = $maxId;
    }

    public function handle(): void
    {
        $progress = $this->getProgressRecord();

        if (!$progress || $this->shouldStopProcessing($progress)) {
            return;
        }

        $this->processParcelData($progress);
    }

    protected function getProgressRecord(): ?FetchProgress
    {
        $progress = FetchProgress::first();

        if (!$progress) {
            Log::error('No progress record found');
            return null;
        }

        return $progress;
    }

    protected function shouldStopProcessing(FetchProgress $progress): bool
    {
        if ($progress->should_stop) {
            $progress->update(['is_running' => false]);
            Log::info('Job stopped by signal', ['id' => $this->currentId]);
            return true;
        }

        return false;
    }

    protected function processParcelData(FetchProgress $progress): void
    {
        try {
            $response = $this->makeApiRequest();

            if ($response->successful()) {
                $this->processResponse($response->json());
            } elseif ($response->status() === 404) {
                Log::info("Parcel not found", ['id' => $this->currentId]);
            } else {
                Log::error("API request failed", [
                    'id' => $this->currentId,
                    'status' => $response->status()
                ]);
            }
        } catch (Throwable $e) {
            Log::error("Fetch error", [
                'id' => $this->currentId,
                'error' => $e->getMessage()
            ]);
        }

        $this->updateProgressAndDispatchNext($progress);
    }

    protected function makeApiRequest()
    {
        return Http::timeout(30)
            ->retry(3, 5000)
            ->get("https://air.norfolk.gov/api/v1/recordcard/{$this->currentId}");
    }

    protected function processResponse(array $data): ?Parcel
    {
        Log::info('api response', $data);

        try {
            $parcelId = $data['id'] ?? null;
            Log::info('START PROCESSING', ['id' => $parcelId]);

            if (empty($data['parcel']['header'])) {
                Log::error('Missing parcel header', ['id' => $parcelId]);
                return null;
            }

            $parcelData = $this->prepareParcelData($data);
            return $this->saveParcelData($parcelData);

        } catch (Throwable $e) {
            Log::error('PROCESSING FAILED', [
                'id' => $parcelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function prepareParcelData(array $data): array
    {
        $header = $data['parcel']['header'];
        $sections = $data['parcel']['sections'] ?? [];
        $parcelId = $data['id'] ?? null;

        $ownerInfo = $this->extractNestedData($sections, ['0', '0', '0']);
        $buildingInfo = $this->extractNestedData($sections, ['0', '1', '0']);
        $salesHistory = $this->extractNestedData($sections, ['1', '0']) ?? [];
        $assessments = $this->extractNestedData($sections, ['1', '1']) ?? [];

        $latestSale = $salesHistory[0] ?? [];
        $latestAssessment = $assessments[0] ?? [];

        return [
            'id' => $header['Parcel_id'] ?? $parcelId,
            'active' => $data['active'] ?? true,
            'property_address' => $header['PropertyStreet'] ?? null,
            'total_value' => $this->parseCurrency($header['total_value'] ?? null),
            'mailing_address' => $header['MailingAddress'] ?? null,
            'gpin' => $header['GPIN'] ?? null,
            'owner_name' => $ownerInfo['OwnerName'] ?? null,
            'property_use' => $ownerInfo['PropertyUse'] ?? null,
            'building_type' => $buildingInfo['BuildingType'] ?? null,
            'year_built' => isset($buildingInfo['YearBuilt']) ? (int) $buildingInfo['YearBuilt'] : null,
            'stories' => isset($buildingInfo['NumberofStories']) ? (float) $buildingInfo['NumberofStories'] : null,
            'bedrooms' => isset($buildingInfo['Bedrooms']) ? (int) $buildingInfo['Bedrooms'] : null,
            'full_baths' => isset($buildingInfo['FullBaths']) ? (int) $buildingInfo['FullBaths'] : null,
            'half_baths' => isset($buildingInfo['HalfBaths']) ? (int) $buildingInfo['HalfBaths'] : null,
            'last_sale_date' => $this->formatDate($latestSale['saledate'] ?? null),
            'last_sale_price' => $this->parseCurrency($latestSale['saleprice'] ?? null),
            'last_sale_owner' => $latestSale['owners'] ?? null,
            'last_sale_docnum' => isset($latestSale['docnum']) ? trim($latestSale['docnum']) : null,
            'last_assessment_date' => $this->formatDate($latestAssessment['eff_year'] ?? null),
            'land_value' => $this->parseCurrency($latestAssessment['land_market_value'] ?? null),
            'improvement_value' => $this->parseCurrency($latestAssessment['imp_val'] ?? null),
            'last_assessment_value' => $this->parseCurrency($latestAssessment['total_value'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function extractNestedData(array $data, array $keys)
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }

    protected function parseCurrency(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) preg_replace('/[^0-9.]/', '', $value);
    }

    protected function formatDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('m/d/Y', $date)->format('Y-m-d');
        } catch (Throwable $e) {
            Log::warning('Date format error', ['date' => $date, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function saveParcelData(array $parcelData): ?Parcel
    {
        $result = Parcel::updateOrCreate(
            ['id' => $parcelData['id']],
            $parcelData
        );

        Log::info('SAVE RESULT', [
            'id' => $parcelData['id'],
            'action' => $result->wasRecentlyCreated ? 'CREATED' : 'UPDATED',
            'owner_name' => $result->owner_name,
            'building_type' => $result->building_type,
            'year_built' => $result->year_built,
            'last_sale_price' => $result->last_sale_price,
            'last_assessment_value' => $result->last_assessment_value
        ]);

        return $result;
    }

    protected function updateProgressAndDispatchNext(FetchProgress $progress): void
    {
        $nextId = $this->currentId + 1;
        $progress->update(['current_id' => $nextId]);

        if ($progress->should_stop || ($this->maxId && $nextId > $this->maxId)) {
            $progress->update(['is_running' => false]);
            Log::info('Fetching completed', ['last_id' => $this->currentId]);
            return;
        }

        self::dispatch($nextId, $this->maxId)->delay(now()->addSeconds(1));
    }
}
