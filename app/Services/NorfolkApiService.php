<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NorfolkApiService
{
    protected string $baseUrl = 'https://data.norfolk.gov/resource/ere7-kake.json';
    protected int $batchSize = 500;
    protected int $maxAttempts = 3;
    protected int $retryDelay = 1000; // milliseconds

    /**
     * Fetch one batch of properties with offset.
     *
     * @param int $offset
     * @return array
     */
    public function fetchBatch(int $offset): array
    {
        $attempt = 0;

        do {
            $attempt++;

            try {
                $response = Http::timeout(120)
                    ->retry(3, 500)
                    ->get($this->baseUrl, [
                        '$limit' => $this->batchSize,
                        '$offset' => $offset,
                        '$select' => 'tax_account_number,gpin,full_address'
                    ]);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                Log::warning('API request failed', [
                    'status' => $response->status(),
                    'offset' => $offset
                ]);

            } catch (\Exception $e) {
                Log::error('API request exception', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'offset' => $offset
                ]);

                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }

                usleep($this->retryDelay * 1000);
            }

        } while ($attempt < $this->maxAttempts);

        return [];
    }
}
