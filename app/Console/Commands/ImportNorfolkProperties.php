<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\NorfolkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportNorfolkProperties extends Command
{
    protected $signature = 'import:norfolk-properties';
    protected $description = 'Import properties from Norfolk API';

    public function handle(NorfolkApiService $apiService): int
    {
        $this->info('Starting property import...');
        Log::info('Starting property import');

        $offset = 0;
        $batchSize = 500; // Should match NorfolkApiService batch size
        $created = 0;
        $skipped = 0;
        $failed = 0;

        while (true) {
            $properties = $apiService->fetchBatch($offset);

            if (empty($properties)) {
                $this->info('No more properties to fetch.');
                break;
            }

            $this->info(sprintf('Processing batch of %d properties (offset %d)...', count($properties), $offset));

//            foreach ($properties as $property) {
//                try {
//                    $exists = Property::where('tax_account_number', $property['tax_account_number'])
//                        ->where('gpin', $property['gpin'])
//                        ->exists();
//
//                    if (!$exists) {
//                        Property::create([
//                            'tax_account_number' => $property['tax_account_number'],
//                            'gpin' => $property['gpin'],
//                            'full_address' => $property['full_address']
//                        ]);
//                        $created++;
//                    } else {
//                        $skipped++;
//                    }
//                } catch (\Exception $e) {
//                    $failed++;
//                    Log::error('Failed to create property', [
//                        'error' => $e->getMessage(),
//                        'property' => $property
//                    ]);
//                }
//            }
            foreach ($properties as $property) {
                try {
                    // Skip if tax_account_number is missing
                    if (empty($property['tax_account_number'])) {
                        $skipped++;
                        Log::warning('Skipping property - missing tax_account_number', [
                            'property' => $property
                        ]);
                        continue;
                    }

                    // SAFELY get GPIN (key change to fix your error)
                    $gpin = $property['gpin'] ?? null; // This fixes the "Undefined array key" error

                    // Check for existing property (NULL-safe comparison)
                    $exists = Property::where('tax_account_number', $property['tax_account_number'])
                        ->when(!is_null($gpin), function ($query) use ($gpin) {
                            return $query->where('gpin', $gpin);
                        }, function ($query) {
                            return $query->whereNull('gpin'); // Match NULL GPINs
                        })
                        ->exists();

                    if (!$exists) {
                        Property::create([
                            'tax_account_number' => $property['tax_account_number'],
                            'gpin' => $gpin, // Can be NULL now
                            'full_address' => $property['full_address'] ?? null
                        ]);
                        $created++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to create property', [
                        'error' => $e->getMessage(),
                        'property' => $property
                    ]);
                }
            }
            $offset += $batchSize;

            // Stop if last batch is smaller than batch size (means no more data)
            if (count($properties) < $batchSize) {
                $this->info('Last batch processed, no more data.');
                break;
            }
        }

        $this->info(sprintf(
            'Import completed: %d created, %d skipped, %d failed',
            $created,
            $skipped,
            $failed
        ));

        Log::info('Import completed', [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed
        ]);

        return 0;
    }
}
