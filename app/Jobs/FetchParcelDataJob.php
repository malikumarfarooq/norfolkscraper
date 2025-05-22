<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\Feature;
use App\Models\FetchProgress;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        Log::info("Starting job for ID: {$this->currentId}");

        $progress = FetchProgress::first();
        if (!$progress) {
            Log::error('No progress record found in job');
            return;
        }

        if ($progress->should_stop) {
            Log::info('Job stopped due to stop signal', ['id' => $this->currentId]);
            $progress->update(['is_running' => false]);
            return;
        }

        Log::info("Fetching data for ID: {$this->currentId}");

        try {
            $response = Http::timeout(30)->retry(3, 5000)->get(
                "https://air.norfolk.gov/api/v1/recordcard/{$this->currentId}"
            );

            if ($response->successful()) {
                Log::info("Successfully fetched data for ID: {$this->currentId}");
                $this->processParcelData($response->json());
            } elseif ($response->status() === 404) {
                Log::info("Record not found for ID: {$this->currentId}, skipping");
            } else {
                Log::error("API request failed for ID: {$this->currentId}", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Exception fetching ID {$this->currentId}: " . $e->getMessage());
        }

        // Update progress
        $nextId = $this->currentId + 1;
        $progress->update(['current_id' => $nextId]);
        Log::info("Progress updated to ID: {$nextId}");

        // Check if we should continue
        if ($progress->should_stop || ($this->maxId && $nextId > $this->maxId)) {
            Log::info('Stopping fetch process', [
                'reason' => $progress->should_stop ? 'manual stop' : 'reached max_id',
                'last_id' => $this->currentId
            ]);
            $progress->update(['is_running' => false]);
            return;
        }

        // Dispatch next job
        Log::info("Dispatching next job for ID: {$nextId}");
        self::dispatch($nextId, $this->maxId)->delay(now()->addSeconds(1));
    }

    protected function processParcelData(array $data)
    {
        Log::info("Processing data for parcel", ['id' => $data['id'] ?? null]);

        DB::beginTransaction();
        try {
            $parcelData = $data['parcel'] ?? [];
            $header = $parcelData['header'] ?? [];
            $bounds = $parcelData['bounds'] ?? null;

            $parcel = Parcel::updateOrCreate(
                ['id' => $header['Parcel_id'] ?? $data['id']],
                [
                    'gpin' => $header['GPIN'] ?? null,
                    'property_street' => $header['PropertyStreet'] ?? null,
                    'mailing_address' => $header['MailingAddress'] ?? null,
                    'bounds' => $bounds ? DB::raw("ST_GeomFromText('$bounds')") : null,
                    'latitude' => $data['cty'] ?? null,
                    'longitude' => $data['ctx'] ?? null,
                    'active' => $data['active'] ?? true,
                ]
            );

            // Process owners
            if (isset($data['sections'][0][0]['OwnerName'])) {
                $parcel->owners()->updateOrCreate(
                    ['name' => $data['sections'][0][0]['OwnerName']],
                    ['name' => $data['sections'][0][0]['OwnerName']]
                );
            }

            // Process features
            if (isset($data['sections'][0][1])) {
                $featureData = $data['sections'][0][1];
                $parcel->features()->updateOrCreate(
                    ['parcel_id' => $parcel->id],
                    $this->parseFeatureData($featureData)
                );
            }

            // Process sales
            if (isset($data['sections'][0][2])) {
                foreach ($data['sections'][0][2] as $saleData) {
                    $this->processSaleData($parcel, $saleData);
                }
            }

            // Process assessments
            if (isset($data['sections'][0][5])) {
                foreach ($data['sections'][0][5] as $assessmentData) {
                    $this->processAssessmentData($parcel, $assessmentData);
                }
            }

            DB::commit();
            Log::info("Successfully processed parcel {$parcel->id}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing parcel data: " . $e->getMessage());
            throw $e;
        }
    }

    protected function parseFeatureData(array $featureData): array
    {
        return [
            'building_type' => $featureData['BuildingType'] ?? null,
            'stories' => isset($featureData['NumberofStories']) ? (float)$featureData['NumberofStories'] : null,
            'year_built' => $featureData['YearBuilt'] ?? null,
            'construction_quality' => $featureData['ConstructionQuality'] ?? null,
            'finished_living_area' => isset($featureData['FinishedLivingArea']) ?
                (int)str_replace([',', ' sqft'], '', $featureData['FinishedLivingArea']) : null,
            'bedrooms' => $featureData['Bedrooms'] ?? null,
            'full_baths' => $featureData['FullBaths'] ?? null,
            'half_baths' => $featureData['HalfBaths'] ?? null,
            'fireplaces' => ($featureData['Fireplaces'] ?? 'No') === 'Yes',
            'heating' => $featureData['Heating'] ?? null,
            'cooling' => $featureData['Cooling'] ?? null,
            'foundation' => $featureData['Foundation'] ?? null,
            'attic' => ($featureData['Arric'] ?? 'No') === 'Attic',
            'attic_area' => isset($featureData['AtticArea']) ?
                (int)str_replace([',', ' sqft'], '', $featureData['AtticArea']) : null,
            'interior_walls' => $featureData['InteriorWalls'] ?? null,
            'exterior_cover' => $featureData['ExteriorCover'] ?? null,
            'roof_style' => $featureData['RoofStyle'] ?? null,
            'roof_cover' => $featureData['RoofCover'] ?? null,
            'framing' => $featureData['Framing'] ?? null,
            'basement_finished_area' => isset($featureData['BasementFinishedArea']) ?
                (int)str_replace([',', ' sqft'], '', $featureData['BasementFinishedArea']) : null,
        ];
    }

    protected function processSaleData($parcel, $saleData)
    {
        if (isset($saleData['saledate'])) {
            $saleDate = \Carbon\Carbon::createFromFormat('m/d/Y', $saleData['saledate']);
            $salePrice = isset($saleData['saleprice']) ?
                (float)str_replace(['$', ','], '', $saleData['saleprice']) : null;

            $parcel->sales()->updateOrCreate(
                [
                    'sale_date' => $saleDate,
                    'document_number' => $saleData['docnum'] ?? null
                ],
                [
                    'sale_price' => $salePrice,
                    'transaction_type' => $saleData['transtype'] ?? null,
                ]
            );
        }
    }

    protected function processAssessmentData($parcel, $assessmentData)
    {
        if (isset($assessmentData['eff_year'])) {
            $effectiveDate = \Carbon\Carbon::createFromFormat('m/d/Y', $assessmentData['eff_year']);
            $landValue = isset($assessmentData['land_market_value']) ?
                (float)str_replace(['$', ','], '', $assessmentData['land_market_value']) : null;
            $impValue = isset($assessmentData['imp_val']) ?
                (float)str_replace(['$', ','], '', $assessmentData['imp_val']) : null;
            $totalValue = isset($assessmentData['total_value']) ?
                (float)str_replace(['$', ','], '', $assessmentData['total_value']) : null;

            $parcel->assessments()->updateOrCreate(
                ['effective_date' => $effectiveDate],
                [
                    'land_value' => $landValue,
                    'improvement_value' => $impValue,
                    'total_value' => $totalValue,
                ]
            );
        }
    }
}
