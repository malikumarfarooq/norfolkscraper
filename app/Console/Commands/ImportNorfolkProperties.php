<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\NorfolkApiService;
use Illuminate\Console\Command;

class ImportNorfolkProperties extends Command
{
    protected $signature = 'import:norfolk-properties';
    protected $description = 'Import properties from Norfolk API';

    public function handle(NorfolkApiService $apiService): int
    {
        $this->info('Starting property import...');

        $offset = 0;
        $batchSize = 500;
        $imported = 0;

        while (true) {
            $properties = $apiService->fetchBatch($offset, $batchSize);

            if (empty($properties)) {
                break;
            }

            foreach ($properties as $property) {
                Property::create([
                    'tax_account_number' => $property['tax_account_number'] ?? null,
                    'gpin' => $property['gpin'] ?? null,
                    'full_address' => $property['full_address'] ?? null,
                ]);
                $imported++;
            }

            $offset += $batchSize;
            $this->info("Imported {$imported} properties so far...");
        }

        $this->info("Import completed. Total imported: {$imported}");
        return 0;
    }
}
