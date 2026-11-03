<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Actions\RecordAdminAction;
use App\Domains\Auth\Models\DataSubjectRequest;
use App\Domains\Catalog\Actions\PurgeSellerCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The NDPA request inbox.
 *
 * This is a manual queue on purpose: the status is whatever an admin sets after
 * checking the requester's identity, and the note records what was done. The
 * deadline is shown and overdue requests sort first so nothing sits unanswered.
 */
class AdminDataRequestController extends Controller
{
    private const STATUSES = ['pending', 'in_progress', 'resolved', 'rejected'];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'open');

        $query = DataSubjectRequest::query()->with('seller');

        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $requests = $query->orderByRaw('resolved_at is null desc')->orderBy('due_at')->latest('id')->get();

        return view('admin.data-requests', [
            'requests' => $requests,
            'activeStatus' => $status,
            'statuses' => self::STATUSES,
            'counts' => [
                'open' => DataSubjectRequest::query()->whereNull('resolved_at')->count(),
                'overdue' => DataSubjectRequest::query()->overdue()->count(),
                ...collect(self::STATUSES)->mapWithKeys(fn (string $value): array => [
                    $value => DataSubjectRequest::query()->where('status', $value)->count(),
                ])->all(),
            ],
        ]);
    }

    public function update(Request $request, DataSubjectRequest $dataRequest, RecordAdminAction $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'verified' => ['sometimes', 'boolean'],
        ]);

        $resolved = in_array($data['status'], ['resolved', 'rejected'], true);

        $dataRequest->forceFill([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? $dataRequest->resolution_note,
            'verified_at' => $request->boolean('verified') ? ($dataRequest->verified_at ?? now()) : $dataRequest->verified_at,
            'resolved_at' => $resolved ? ($dataRequest->resolved_at ?? now()) : null,
        ])->save();

        $audit->handle($request->user(), 'data_request.updated', $dataRequest, "Status {$data['status']}");

        return back()->with('status', 'Request updated.');
    }

    /**
     * A verified deletion request is the one case that skips the 30-day
     * product recovery window: the owner asked for the catalogue to be gone.
     */
    public function purge(Request $request, DataSubjectRequest $dataRequest, PurgeSellerCatalogue $purge, RecordAdminAction $audit): RedirectResponse
    {
        abort_unless($dataRequest->request_type === 'deletion', 422, 'Only a deletion request can purge catalogue data.');
        abort_if($dataRequest->seller_id === null, 422, 'This request is not linked to a seller account.');
        abort_unless($dataRequest->verified_at !== null, 422, 'Verify the requester before deleting anything.');

        $count = $purge->handle($dataRequest->seller_id);

        $audit->handle($request->user(), 'data_request.catalogue_purged', $dataRequest, "{$count} products hard-deleted");

        $dataRequest->forceFill([
            'status' => 'resolved',
            'resolved_at' => $dataRequest->resolved_at ?? now(),
            'resolution_note' => trim(($dataRequest->resolution_note ? $dataRequest->resolution_note.' ' : '')."Catalogue purged ({$count} products)."),
        ])->save();

        return back()->with('status', "{$count} product(s) permanently deleted.");
    }
}
