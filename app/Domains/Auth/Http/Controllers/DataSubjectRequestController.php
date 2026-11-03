<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Models\DataSubjectRequest;
use App\Domains\Auth\Notifications\DataSubjectRequestSubmitted;
use App\Domains\Seller\Models\Seller;
use App\Shared\Support\CoreAdmins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The public NDPA request form.
 *
 * A request is answered within one month, so the deadline is stamped at intake.
 * When the requester is signed in and the address matches their account we mark
 * the identity as verified; otherwise an admin verifies it by the registered
 * email before acting on the data.
 */
class DataSubjectRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'request_type' => ['required', 'in:access,correction,deletion,restriction'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $owner = $user instanceof Seller && strcasecmp($user->email, $data['email']) === 0 ? $user : null;

        $dataSubjectRequest = DataSubjectRequest::create([
            ...$data,
            'seller_id' => $owner?->getKey(),
            'status' => 'pending',
            'verified_at' => $owner !== null ? now() : null,
            'requested_at' => now(),
            'due_at' => now()->addDays(30),
        ]);

        CoreAdmins::send(new DataSubjectRequestSubmitted($dataSubjectRequest));

        return back()->with('status', 'Your data request was received. We will contact you at the email provided.');
    }
}
