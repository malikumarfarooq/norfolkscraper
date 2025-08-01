<?php

namespace App\Console\Commands;

use App\Models\Parcel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ImportParcels extends Command
{
    protected $signature = 'import:parcels {file} {--dry-run}';
    protected $description = 'Import parcels from CSV file';

    public function handle()
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting " . ($dryRun ? 'DRY RUN ' : '') . "import from {$filePath}...");

        try {
            $csvData = File::get($filePath);
            $rows = array_map('str_getcsv', explode("\n", $csvData));
            $header = array_map('trim', array_shift($rows));

            $bar = $this->output->createProgressBar(count($rows));
            $successCount = 0;
            $errorCount = 0;

            foreach ($rows as $i => $row) {
                if (empty($row) || count($row) !== count($header)) {
                    $bar->advance();
                    continue;
                }

                $data = array_combine($header, array_map('trim', $row));

                // Handle empty values for date fields
                $dateFields = ['latest_sale_date', 'latest_assessment_year'];
                foreach ($dateFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $data[$field] = empty($data[$field]) ? null : $data[$field];
                    }
                }

                // Convert numeric fields
                $numericFields = [
                    'year_built' => 'integer',
                    'stories' => 'float',
                    'bedrooms' => 'integer',
                    'full_baths' => 'integer',
                    'half_baths' => 'integer',
                    'total_value' => 'float',
                    'latest_sale_price' => 'float',
                    'latest_total_value' => 'float'
                ];

                foreach ($numericFields as $field => $type) {
                    if (isset($data[$field])) {
                        $data[$field] = ($type === 'integer') ? (int)$data[$field] : (float)$data[$field];
                    }
                }

                // Convert boolean field
                if (isset($data['active'])) {
                    $data['active'] = !empty($data['active']);
                }

                try {
                    if ($dryRun) {
                        $this->line("Would import: " . json_encode($data));
                        $successCount++;
                    } else {
                        Parcel::create($data);
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Row {$i} error: " . $e->getMessage());
                    $this->warn("Skipped row {$i} - " . $e->getMessage());
                    $errorCount++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Import results:");
            $this->info("✅ Successfully processed: {$successCount} records");
            $this->error("❌ Failed to process: {$errorCount} records");
            $this->info($dryRun ? 'Dry run completed (no data was actually saved)' : 'Import completed!');
            return 0;

        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            Log::error("CSV import failed: " . $e->getMessage());
            return 1;
        }
    }
}
