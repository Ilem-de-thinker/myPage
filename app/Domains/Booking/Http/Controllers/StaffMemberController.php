<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StaffMemberController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);

        $staff = $page->staffMembers()
            ->withCount(['services', 'bookings'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.staff.index', [
            'page' => $page,
            'staff' => $staff,
            'services' => $page->services()->orderBy('position')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, SubscriptionEntitlements $entitlements): RedirectResponse
    {
        $page = $this->page($request);
        $entitlements->assertCanCreateStaff($page);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
            'services' => ['array'],
            'services.*' => ['integer'],
        ]);

        $member = $page->staffMembers()->create([
            'seller_id' => $page->seller_id,
            'name' => $data['name'],
            'bio' => Html::sanitize($data['bio'] ?? ''),
            'photo_path' => $this->storePhoto($request, $page),
        ]);

        $this->syncServices($page, $member, $data['services'] ?? []);

        return back()->with('status', "{$member->name} added.");
    }

    public function update(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($staffMember->store_page_id === $page->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'services' => ['array'],
            'services.*' => ['integer'],
        ]);

        $staffMember->update([
            'name' => $data['name'],
            'bio' => Html::sanitize($data['bio'] ?? ''),
            'is_active' => $request->boolean('is_active'),
            'photo_path' => $this->syncPhoto($request, $staffMember, $page),
        ]);

        $this->syncServices($page, $staffMember, $data['services'] ?? []);

        return back()->with('status', "{$staffMember->name} saved.");
    }

    public function destroy(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($staffMember->store_page_id === $page->id, 404);

        $name = $staffMember->name;

        // Hours are removed because they are meaningless without the person, but
        // bookings keep the row so past appointments still render a name.
        WorkingHour::where('staff_member_id', $staffMember->getKey())->delete();

        $staffMember->services()->detach();
        $staffMember->delete();

        return back()->with('status', "{$name} removed. Their bookings were kept.");
    }

    private function storePhoto(Request $request, StorePage $page): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $request->file('photo')->store("booking/{$page->getKey()}/staff", 'public');
    }

    /**
     * Swap or drop the photo a team member already has, deleting the old file
     * rather than leaving it on the public disk forever.
     */
    private function syncPhoto(Request $request, StaffMember $member, StorePage $page): ?string
    {
        if ($request->boolean('remove_photo')) {
            $this->deleteStoredPhoto($member->photo_path);

            return null;
        }

        if (! $request->hasFile('photo')) {
            return $member->photo_path;
        }

        $this->deleteStoredPhoto($member->photo_path);

        return $this->storePhoto($request, $page);
    }

    private function deleteStoredPhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function syncServices(StorePage $page, StaffMember $member, array $ids): void
    {
        // Scoped to this page, so ids belonging to another seller are ignored.
        $serviceIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $page->services()->whereKey($id)->exists())
            ->all();

        $member->services()->sync($serviceIds);
    }

    private function page(Request $request): StorePage
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($page->theme === Theme::Service, 404);

        return $page;
    }
}
