<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Actions\AdminRoleManager;
use App\Domains\Admin\Enums\AdminRole;
use App\Domains\Seller\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTeamController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.team', [
            'admins' => Seller::withoutGlobalScopes()
                ->where(fn ($query) => $query->whereNotNull('admin_role')->orWhere('is_admin', true))
                ->when($search, function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
                })
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function update(Request $request, Seller $seller, AdminRoleManager $roles): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::enum(AdminRole::class)],
        ]);

        $role = filled($data['role'] ?? null) ? AdminRole::from($data['role']) : null;

        $roles->assign($seller, $role, $request->user());

        return back()->with('status', $role === null
            ? "{$seller->name} no longer has admin access."
            : "{$seller->name} is now a {$role->label()}.");
    }
}
