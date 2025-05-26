<?php

namespace App\Jobs;

use App\Models\Parcel;
use App\Models\FetchProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchParcelDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $currentId;
    protected $maxId;

    public function __construct($currentId, $maxId = null)
    {
        $this->currentId = $currentId;
        $this->maxId = $maxId;
    }

    public function handle()
    {
        $progress = FetchProgress::first();
        if (!$progress) {
            Log::error('No progress record found');
            return;
        }

        if ($progress->should_stop) {
            $progress->update(['is_running' => false]);
            Log::info('Job stopped by signal', ['id' => $this->currentId]);
            return;
        }

        try {
            $response = Http::timeout(30)
                ->retry(3, 5000)
                ->get("https://air.norfolk.gov/api/v1/recordcard/{$this->currentId}");

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
        } catch (\Exception $e) {
            Log::error("Fetch error", [
                'id' => $this->currentId,
                'error' => $e->getMessage()
            ]);
        }

        $this->updateProgressAndDispatchNext($progress);
    }

    protected function processResponse(array $data)
    {
        if (empty($data['parcel']['header'])) {
            Log::warning('Invalid parcel data structure', ['id' => $data['id'] ?? null]);
            return;
        }

        $header = $data['parcel']['header'];
        $sections = $data['sections'][0] ?? [];

        $parcelData = [
            'id' => $header['Parcel_id'] ?? $data['id'],
            'active' => $data['active'] ?? true,
            'property_address' => $header['PropertyStreet'] ?? null,
            'total_value' => $this->parseCurrency($header['total_value'] ?? null),
            'mailing_address' => $header['MailingAddress'] ?? null,
            'owner_name' => $sections[0][0]['OwnerName'] ?? null,
            'property_use' => $sections[0][0]['PropertyUse'] ?? null,
            'building_type' => $sections[0][1]['BuildingType'] ?? null,
            'year_built' => isset($sections[0][1]['YearBuilt']) ? (int)$sections[0][1]['YearBuilt'] : null,
            'stories' => isset($sections[0][1]['NumberofStories']) ? (float)$sections[0][1]['NumberofStories'] : null,
            'bedrooms' => $sections[0][1]['Bedrooms'] ?? null,
            'full_baths' => $sections[0][1]['FullBaths'] ?? null,
            'half_baths' => $sections[0][1]['HalfBaths'] ?? null,
            'gpin' => $header['GPIN'] ?? null,
        ];

        // Add latest sale data if available
        if (!empty($sections[2])) {
            $latestSale = $sections[2][0] ?? null;
            if ($latestSale) {
                $parcelData['latest_sale_owner'] = $latestSale['owners'] ?? null;
                $parcelData['latest_sale_date'] = isset($latestSale['saledate']) ?
                    Carbon::createFromFormat('m/d/Y', $latestSale['saledate']) : null;
                $parcelData['latest_sale_price'] = $this->parseCurrency($latestSale['saleprice'] ?? null);
            }
        }

        // Add latest assessment data if available
        if (!empty($sections[5])) {
            $latestAssessment = $sections[5][0] ?? null;
            if ($latestAssessment) {
                $parcelData['latest_assessment_year'] = isset($latestAssessment['eff_year']) ?
                    Carbon::createFromFormat('m/d/Y', $latestAssessment['eff_year']) : null;
                $parcelData['latest_total_value'] = $this->parseCurrency($latestAssessment['total_value'] ?? null);
            }
        }

        // Add location if coordinates exist
        if (isset($data['ctx'], $data['cty'])) {
            $parcelData['location'] = DB::raw("POINT({$data['ctx']}, {$data['cty']})");
        }

        Parcel::updateOrCreate(['id' => $parcelData['id']], $parcelData);
        Log::info("Processed parcel", ['id' => $parcelData['id']]);
    }

    protected function updateProgressAndDispatchNext(FetchProgress $progress)
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

    protected function parseCurrency(?string $value): ?float
    {
        return $value ? (float)preg_replace('/[^0-9.]/', '', $value) : null;
    }
}
