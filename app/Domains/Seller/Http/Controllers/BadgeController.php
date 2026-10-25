<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Admin\Actions\ReviewBadgeApplication;
use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The seller-facing side of the verified badge: apply, and see where the
 * application got to.
 */
class BadgeController extends Controller
{
    public function create(Request $request): View
    {
        $page = $this->page($request);

        return view('dashboard.badge.index', [
            'page' => $page,
            'application' => BadgeApplication::where('store_page_id', $page->id)
                ->latest('submitted_at')
                ->first(),
            'statuses' => BadgeStatus::cases(),
        ]);
    }

    public function store(
        Request $request,
        ReviewBadgeApplication $review,
    ): RedirectResponse {
        $page = $this->page($request);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:190'],
            'registered_legal_name' => ['required', 'string', 'max:190'],
            'document_type' => ['required', Rule::in(['cac', 'national_id'])],
            'registration_number' => ['required', 'string', 'max:120'],
            'document_expires_at' => [new RequiredIf($request->input('document_type') === 'national_id'), 'nullable', 'date', 'after:today'],
            'document' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:8192'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $review->submit($page, $data, $request->file('document'));
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('badge.apply')
            ->with('status', 'Sent. We look at applications every weekday.');
    }

    private function page(Request $request): StorePage
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($page->theme === Theme::Store || $page->theme === Theme::Service, 404);

        return $page;
    }
}
