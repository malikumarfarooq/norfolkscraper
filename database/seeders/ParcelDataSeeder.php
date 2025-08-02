<?php

namespace Database\Seeders;

use App\Models\Parcel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ParcelDataSeeder extends Seeder
{
    public function run()
    {
        // Adjust the path to your CSV file
        $filePath = storage_path('parcels.csv');

        if (!File::exists($filePath)) {
            Log::error("CSV file not found at: {$filePath}");
            return;
        }

        $file = fopen($filePath, 'r');

        // Skip header row
        fgetcsv($file);

        $batchSize = 1000; // Adjust based on your Render memory limits
        $batch = [];
        $importedCount = 0;

        while (($data = fgetcsv($file)) !== false) {
            try {
                $batch[] = $this->transformData($data);

                if (count($batch) >= $batchSize) {
                    Parcel::insert($batch);
                    $importedCount += count($batch);
                    $batch = [];
                    Log::info("Imported {$importedCount} records so far...");
                }
            } catch (\Exception $e) {
                Log::error("Error processing record: " . implode(',', $data));
                Log::error($e->getMessage());
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            Parcel::insert($batch);
            $importedCount += count($batch);
        }

        fclose($file);
        Log::info("Completed! Total records imported: {$importedCount}");
    }

    protected function transformData(array $row)
    {
        return [
            'id' => $row[0],
            'active' => (bool)$row[1],
            'property_address' => $row[2],
            'total_value' => $row[3] ? (float)str_replace([',', '$'], '', $row[3]) : null,
            'mailing_address' => $row[4],
            'owner_name' => $row[5],
            'property_use' => $row[6],
            'building_type' => $row[7],
            'year_built' => $row[8] ? (int)$row[8] : null,
            'stories' => $row[9] ? (float)$row[9] : null,
            'bedrooms' => $row[10] ? (int)$row[10] : null,
            'full_baths' => $row[11] ? (int)$row[11] : null,
            'half_baths' => $row[12] ? (int)$row[12] : null,
            'latest_sale_owner' => $row[13],
            'latest_sale_date' => $row[14] ?: null,
            'latest_sale_price' => $row[15] ? (float)str_replace([',', '$'], '', $row[15]) : null,
            'latest_assessment_year' => $row[16] ?: null,
            'latest_total_value' => $row[17] ? (float)str_replace([',', '$'], '', $row[17]) : null,
            'gpin' => $row[18],
            'created_at' => $row[19] ?: now(),
            'updated_at' => $row[20] ?: now(),
        ];
    }
}
