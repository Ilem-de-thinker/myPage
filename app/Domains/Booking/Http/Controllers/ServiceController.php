<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiLimits;
use App\Domains\Ai\Services\AiUsageReservations;
use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Enums\ServiceMode;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Html;
use App\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);

        $services = $page->services()
            ->withCount('bookings')
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // A service with no opening hours can never be booked, so say so here
        // rather than letting a seller publish it and wonder why nobody arrives.
        $daysOpen = $page->workingHours()->whereNull('staff_member_id')->distinct()->count('weekday');

        return view('dashboard.services.index', [
            'page' => $page,
            'services' => $services,
            'daysOpen' => $daysOpen,
        ]);
    }

    public function create(Request $request): View
    {
        return view('dashboard.services.form', [
            'page' => $this->page($request),
            'service' => null,
            'staff' => [],
        ]);
    }

    public function store(Request $request, SubscriptionEntitlements $entitlements): RedirectResponse
    {
        $page = $this->page($request);
        $entitlements->assertCanCreateListing($page);

        $service = $page->services()->create([
            ...$this->validated($request, $page),
            'seller_id' => $page->seller_id,
            'position' => $page->services()->count(),
            'image_path' => $this->storeImage($request, $page),
        ]);

        $this->syncStaff($service, $request);

        return redirect()
            ->route('services.index')
            ->with('status', "{$service->name} added.");
    }

    public function edit(Request $request, Service $service): View
    {
        $page = $this->page($request);

        abort_unless($service->store_page_id === $page->id, 404);

        return view('dashboard.services.form', [
            'page' => $page,
            'service' => $service,
            'staff' => $page->staffMembers()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($service->store_page_id === $page->id, 404);

        $service->update([
            ...$this->validated($request, $page),
            'image_path' => $this->syncImage($request, $service, $page),
        ]);

        $this->syncStaff($service, $request);

        return redirect()
            ->route('services.index')
            ->with('status', "{$service->name} saved.");
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($service->store_page_id === $page->id, 404);

        $name = $service->name;

        // Bookings keep their record of what was booked; the service only stops
        // being offered.
        $service->update(['is_active' => false]);

        return back()->with('status', "{$name} is no longer offered. Its bookings were kept.");
    }

    public function generateDescriptions(Request $request): RedirectResponse
    {
        $page = $this->page($request);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'tone' => ['nullable', Rule::in(['professional', 'friendly', 'playful', 'luxury', 'concise'])],
            'length' => ['nullable', Rule::in(['short', 'medium', 'long'])],
            'language' => ['nullable', Rule::in(['English', 'Nigerian Pidgin', 'Yoruba', 'Igbo', 'Hausa'])],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        $limits = app(AiLimits::class);
        $credits = app(AiCreditService::class);
        $limits->throttle($page);
        $limits->assertWithinTaskCap(count($data['ids']));
        $limits->assertCanQueue($page, 1);
        $estimate = $credits->estimate($page, count($data['ids']), 'description_generation');
        $slots = $limits->availableSlots($page);
        abort_if($slots < 1 || $estimate['allowed'] < 1, 429, 'This store has reached its monthly Mercho limit for descriptions.');

        $allowed = min($estimate['allowed'], $slots, $limits->maxBatchItems());
        $services = $page->services()
            ->whereIn('id', array_slice($data['ids'], 0, $allowed))
            ->orderBy('id')
            ->get();
        abort_if($services->isEmpty(), 422, 'Select at least one service from this store.');

        $task = AiTask::create([
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->id,
            'type' => 'service_descriptions',
            'status' => 'queued',
            'total' => $services->count(),
        ]);

        foreach ($services as $service) {
            $operation = AiOperation::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->id,
                'ai_task_id' => $task->id,
                'task' => 'description_generation',
                'status' => 'queued',
                'input' => [
                    'feature' => 'service_descriptions',
                    '_no_draft' => true,
                    'item' => [
                        'name' => $service->name,
                        'price' => $service->price_kobo / 100,
                        'duration' => $service->duration_min,
                        'attributes' => $service->ai_metadata,
                    ],
                    'options' => [
                        'tone' => $data['tone'] ?? 'professional',
                        'length' => $data['length'] ?? 'medium',
                        'language' => $data['language'] ?? 'English',
                        'overwrite' => $request->boolean('overwrite'),
                    ],
                    'service_id' => $service->id,
                ],
            ]);
            app(AiUsageReservations::class)->reserveAndDispatch($operation);
        }

        $task->update(['status' => 'processing', 'started_at' => now()]);

        return redirect()->route('dashboard.ai.tasks.show', $task)->with('status', 'Generating service descriptions in the background.');
    }

    public function applyDescriptions(Request $request, int $task): RedirectResponse
    {
        $page = $this->page($request);
        $taskModel = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $previous = [];
        $previousMetadata = [];

        foreach ($taskModel->operations()->where('status', 'succeeded')->get() as $operation) {
            $serviceId = $operation->input['service_id'] ?? null;
            $service = $serviceId ? $page->services()->find($serviceId) : null;
            if (! $service) {
                continue;
            }

            $output = $operation->output ?? [];
            $overwrite = $operation->input['options']['overwrite'] ?? false;
            if (! $overwrite && filled($service->description)) {
                continue;
            }
            if (blank($output['description'] ?? null)) {
                continue;
            }

            $previous[$service->id] = $service->description;
            $previousMetadata[$service->id] = $service->ai_metadata;
            $metadata = $service->ai_metadata ?? [];
            $metadata['seo'] = [
                'title' => $output['seo_title'] ?? ($metadata['seo']['title'] ?? null),
                'description' => $output['seo_description'] ?? ($metadata['seo']['description'] ?? null),
            ];
            $metadata['ai_description'] = [
                'insufficient_info' => $output['insufficient_info'] ?? null,
                'flags' => array_values((array) ($output['flags'] ?? [])),
            ];
            $service->update([
                'description' => Html::sanitize($output['description']),
                'ai_metadata' => $metadata,
            ]);
        }

        $taskModel->update([
            'summary' => array_merge($taskModel->summary ?? [], [
                'previous_service_descriptions' => $previous,
                'previous_service_ai_metadata' => $previousMetadata,
            ]),
        ]);

        return redirect()->route('services.index')->with('status', 'Service descriptions applied. Review them, or undo from the task page.');
    }

    public function undoDescriptions(Request $request, int $task): RedirectResponse
    {
        $page = $this->page($request);
        $taskModel = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $previous = $taskModel->summary['previous_service_descriptions'] ?? [];
        $previousMetadata = $taskModel->summary['previous_service_ai_metadata'] ?? [];

        foreach ($previous as $serviceId => $description) {
            $page->services()->whereKey($serviceId)->update([
                'description' => $description,
                'ai_metadata' => $previousMetadata[$serviceId] ?? null,
            ]);
        }

        $taskModel->update(['summary' => array_merge($taskModel->summary ?? [], [
            'previous_service_descriptions' => [],
            'previous_service_ai_metadata' => [],
        ])]);

        return redirect()->route('services.index')->with('status', 'Service descriptions restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, StorePage $page): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'remove_image' => ['sometimes', 'boolean'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'buffer_min' => ['nullable', 'integer', 'min:0', 'max:240'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'compare_at' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date'],
            'deposit_type' => ['required', Rule::enum(DepositType::class)],
            'deposit_value' => ['nullable', 'integer', 'min:0'],
            // `sometimes`, not `required`. This field arrived after the form
            // existed, so anything still posting a service without it — an old
            // tab, an API caller, the test suite — must keep working rather than
            // start failing validation over a default the column already has.
            'billing_unit' => ['sometimes', Rule::enum(BillingUnit::class)],
            'turnaround_label' => ['nullable', 'string', 'max:60'],
            // One box per line is how a seller thinks about this; the array is
            // split on newlines below rather than asking them to press a button
            // per deliverable.
            'deliverables' => ['nullable', 'string', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'service_mode' => ['nullable', Rule::enum(ServiceMode::class)],
            'travel_fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'travel_radius_km' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'staff' => ['array'],
            'staff.*' => ['integer'],
        ]);

        // The seller types naira; the ledger only ever holds kobo.
        $priceKobo = isset($data['price']) && $data['price'] !== ''
            ? (int) round(((float) $data['price']) * 100)
            : 0;

        $depositType = DepositType::from($data['deposit_type']);
        $depositValue = $depositType === DepositType::Percent
            ? min(100, (int) ($data['deposit_value'] ?? 0))
            : (int) (($data['deposit_value'] ?? 0) * 100);

        $compareAtKobo = filled($data['compare_at'] ?? null)
            ? Money::toKobo($data['compare_at'])
            : null;
        $saleStartsAt = filled($data['sale_starts_at'] ?? null)
            ? Carbon::parse($data['sale_starts_at'])
            : null;
        $saleEndsAt = filled($data['sale_ends_at'] ?? null)
            ? Carbon::parse($data['sale_ends_at'])
            : null;

        if ($compareAtKobo !== null && $compareAtKobo <= $priceKobo) {
            throw ValidationException::withMessages([
                'compare_at' => 'The original price must be higher than the price the buyer pays.',
            ]);
        }

        if ($saleStartsAt !== null && $saleEndsAt !== null && $saleEndsAt->lessThanOrEqualTo($saleStartsAt)) {
            throw ValidationException::withMessages([
                'sale_ends_at' => 'The sale must end after it starts.',
            ]);
        }

        // Dates without an original price have nothing to compare against.
        if ($compareAtKobo === null) {
            $saleStartsAt = null;
            $saleEndsAt = null;
        }

        return [
            'name' => $data['name'],
            'slug' => Service::generateSlug($data['name']),
            'description' => Html::sanitize($data['description'] ?? ''),
            'duration_min' => $data['duration_min'],
            'buffer_min' => $data['buffer_min'] ?? 0,
            'price_kobo' => $priceKobo,
            'compare_at_kobo' => $compareAtKobo,
            'sale_starts_at' => $saleStartsAt,
            'sale_ends_at' => $saleEndsAt,
            'deposit_type' => $depositType,
            'deposit_value' => $depositType === DepositType::None ? 0 : $depositValue,
            'billing_unit' => BillingUnit::tryFrom((string) ($data['billing_unit'] ?? '')) ?? BillingUnit::PerProject,
            'turnaround_label' => $data['turnaround_label'] ?? null,
            'deliverables' => $this->deliverables($data['deliverables'] ?? null),
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->boolean('is_active'),
            // Blank inherits the store profile; anything else applies to this
            // service only. Travel fees are naira on the form, kobo in the row.
            'service_mode' => filled($data['service_mode'] ?? null) ? $data['service_mode'] : null,
            'travel_fee_kobo' => filled($data['travel_fee'] ?? null)
                ? Money::toKobo($data['travel_fee'])
                : null,
            'travel_radius_km' => $data['travel_radius_km'] ?? null,
        ];
    }

    /**
     * A textarea of one-per-line into the card's tick-list.
     *
     * Blank lines are dropped rather than stored, because the card renders every
     * entry as a tick and an empty tick reads as a promise the seller did not make.
     *
     * @return list<string>
     */
    private function deliverables(?string $raw): array
    {
        return collect(preg_split('/\R/', (string) $raw) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    /**
     * A new photo for a service, or null when the seller uploaded none.
     */
    private function storeImage(Request $request, StorePage $page): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store("booking/{$page->getKey()}/services", 'public');
    }

    /**
     * Swap or drop the photo a service already has.
     *
     * The old file is deleted rather than left behind: these are phone photos
     * re-uploaded every few months by a seller who wants a better one, and the
     * public disk is not a version history.
     */
    private function syncImage(Request $request, Service $service, StorePage $page): ?string
    {
        if ($request->boolean('remove_image')) {
            $this->deleteStoredImage($service->image_path);

            return null;
        }

        if (! $request->hasFile('image')) {
            return $service->image_path;
        }

        $this->deleteStoredImage($service->image_path);

        return $this->storeImage($request, $page);
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function syncStaff(Service $service, Request $request): void
    {
        $page = $this->page($request);

        $ids = collect($request->input('staff', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $page->staffMembers()->whereKey($id)->exists())
            ->all();

        $service->staff()->sync($ids);
    }

    private function page(Request $request): StorePage
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless(in_array($page->businessType(), ['service', 'both'], true), 404);

        return $page;
    }
}
