<?php

namespace App\Domains\Catalog\Jobs;

use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImport;
use App\Shared\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = ProductImport::query()->with('storePage')->find($this->importId);

        if (! $import) {
            return;
        }

        $import->update(['status' => 'processing', 'started_at' => now()]);

        $path = Storage::disk('local')->path($import->path);
        $handle = is_file($path) ? fopen($path, 'rb') : false;

        if ($handle === false) {
            $import->update(['status' => 'failed', 'errors' => [['row' => 0, 'message' => 'The import file is missing or unreadable.']], 'finished_at' => now()]);

            return;
        }

        $headers = array_map(fn ($header): string => Str::snake(trim((string) $header)), fgetcsv($handle) ?: []);
        $errors = [];
        $imported = 0;
        $failed = 0;
        $rowNumber = 1;

        $entitlements = app(SubscriptionEntitlements::class);

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $row = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));

            try {
                // The limit check and the insert share a transaction so the
                // subscription lock covers the row this iteration creates.
                DB::transaction(function () use ($entitlements, $import, $row): void {
                    $entitlements->assertCanCreateListing($import->storePage);
                    $name = trim((string) ($row['name'] ?? ''));
                    $price = trim((string) ($row['price'] ?? ''));
                    throw_if($name === '' || $price === '', new \InvalidArgumentException('name and price are required'));
                    $category = filled($row['category'] ?? null)
                        ? Category::query()->firstOrCreate(['store_page_id' => $import->store_page_id, 'name' => trim($row['category'])], ['seller_id' => $import->seller_id, 'position' => 0])
                        : null;

                    $priceKobo = Money::toKobo($price);
                    throw_if($priceKobo <= 0, new \InvalidArgumentException('price must parse to a positive amount'));

                    Product::create([
                        'seller_id' => $import->seller_id,
                        'store_page_id' => $import->store_page_id,
                        'category_id' => $category?->getKey(),
                        'name' => $name,
                        'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                        'description' => $row['description'] ?? null,
                        'price_kobo' => $priceKobo,
                        'sku' => filled($row['sku'] ?? null) ? trim($row['sku']) : 'SKU-'.strtoupper(Str::random(8)),
                        'status' => in_array($row['status'] ?? 'draft', ['draft', 'active', 'archived'], true) ? ($row['status'] ?? 'draft') : 'draft',
                        'track_stock' => filled($row['stock_qty'] ?? null),
                        'stock_qty' => max(0, (int) ($row['stock_qty'] ?? 0)),
                    ]);
                });
                $imported++;
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }

        fclose($handle);
        $import->update(['status' => $failed > 0 && $imported === 0 ? 'failed' : 'complete', 'imported_count' => $imported, 'failed_count' => $failed, 'errors' => array_slice($errors, 0, 100), 'finished_at' => now()]);
        Storage::disk('local')->delete($import->path);
    }

    /**
     * Terminal failure safety net: never leave an import pinned at
     * "processing" and never leak the uploaded CSV.
     */
    public function failed(\Throwable $exception): void
    {
        report($exception);

        $import = ProductImport::query()->find($this->importId);

        if (! $import) {
            return;
        }

        $import->update(['status' => 'failed', 'errors' => [['row' => 0, 'message' => 'The import failed unexpectedly. Please try again.']], 'finished_at' => now()]);
        Storage::disk('local')->delete($import->path);
    }
}
