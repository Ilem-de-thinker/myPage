<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Models\DataSubjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        DataSubjectRequest::create([
            ...$data,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return back()->with('status', 'Your data request was received. We will contact you at the email provided.');
    }
}
