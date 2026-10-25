<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        return [
            'name' => $data['name'],
            'slug' => Service::generateSlug($data['name']),
            'description' => Html::sanitize($data['description'] ?? ''),
            'duration_min' => $data['duration_min'],
            'buffer_min' => $data['buffer_min'] ?? 0,
            'price_kobo' => $priceKobo,
            'deposit_type' => $depositType,
            'deposit_value' => $depositType === DepositType::None ? 0 : $depositValue,
            'billing_unit' => BillingUnit::tryFrom((string) ($data['billing_unit'] ?? '')) ?? BillingUnit::PerProject,
            'turnaround_label' => $data['turnaround_label'] ?? null,
            'deliverables' => $this->deliverables($data['deliverables'] ?? null),
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->boolean('is_active'),
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
