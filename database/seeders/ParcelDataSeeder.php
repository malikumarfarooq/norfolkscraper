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

        $batchSize = 200; // Adjust based on your Render memory limits
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
            'active' => filter_var($row[1], FILTER_VALIDATE_BOOLEAN),
            'property_address' => $row[2] ?? null,
            'total_value' => $this->parseMoney($row[3] ?? null),
            'mailing_address' => $row[4] ?? null,
            'owner_name' => $row[5] ?? null,
            'property_use' => $row[6] ?? null,
            'building_type' => $row[7] ?? null,
            'year_built' => $this->parseInt($row[8] ?? null),
            'stories' => $this->parseFloat($row[9] ?? null),
            'bedrooms' => $this->parseInt($row[10] ?? null),
            'full_baths' => $this->parseInt($row[11] ?? null),
            'half_baths' => $this->parseInt($row[12] ?? null),
            'latest_sale_owner' => $row[13] ?? null,
            'latest_sale_date' => $this->parseDate($row[14] ?? null),
//            'latest_sale_price' => $this->parseMoney($row[15] ?? null),
            'latest_sale_price' => $this->parseMoneyWithLogging($row[15] ?? null, $row[0]),
            'latest_assessment_year' => $this->parseDate($row[16] ?? null),
            'latest_total_value' => $this->parseMoney($row[17] ?? null),
            'gpin' => $row[18] ?? null,
            'created_at' => $row[19] ?: now(),
            'updated_at' => $row[20] ?: now(),
        ];
    }
    protected function parseMoney($value)
    {
        if (empty($value)) {
            return null;
        }
        return (float) preg_replace('/[^0-9.-]/', '', $value);
    }


    protected function parseInt($value)
    {
        return is_numeric($value) ? (int)$value : null;
    }

    protected function parseFloat($value)
    {
        return is_numeric($value) ? (float)$value : null;
    }

    protected function parseDate($value)
    {
        if (empty($value)) return null;

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Could not parse date: {$value}");
            return null;
        }
    }



    protected function parseMoneyWithLogging($value, $id = null)
    {
        if (empty($value)) {
            return null;
        }

        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^\d\.\-]/', '', $value);

        if (!is_numeric($cleaned)) {
            Log::warning("Non-numeric latest_sale_price for ID {$id}: '{$value}' cleaned to '{$cleaned}'");
            return null;
        }

        return (float)$cleaned;
    }

}
