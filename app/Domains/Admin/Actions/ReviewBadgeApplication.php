<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Admin\Notifications\KycStatusChangedNotification;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The verified badge. A seller proves the business is real with a CAC
 * certificate or a national ID; an admin looks at it and decides.
 *
 * The badge is per page, not per seller, so it disappears if the page it was
 * earned for is suspended.
 */
class ReviewBadgeApplication
{
    public function __construct(private readonly RecordAdminAction $audit) {}

    public function approve(BadgeApplication $application, Seller $admin, ?string $note = null): BadgeApplication
    {
        return $this->decide($application, $admin, BadgeStatus::Approved, $note, null);
    }

    /**
     * A rejection always carries a reason: the seller has to be able to fix it or
     * appeal it, and "no" on its own is indefensible.
     */
    public function reject(BadgeApplication $application, Seller $admin, string $reason): BadgeApplication
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Tell the applicant why, so they can try again.',
            ]);
        }

        return $this->decide($application, $admin, BadgeStatus::Rejected, null, $reason);
    }

    public function requestMoreInfo(BadgeApplication $application, Seller $admin, string $reason): BadgeApplication
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Tell the applicant what is missing so they can fix it.',
            ]);
        }

        return $this->decide($application, $admin, BadgeStatus::NeedsMoreInfo, null, $reason);
    }

    public function revoke(BadgeApplication $application, Seller $admin, string $reason): BadgeApplication
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Record why the verification is being revoked.',
            ]);
        }

        return DB::transaction(function () use ($application, $admin, $reason) {
            $locked = BadgeApplication::whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== BadgeStatus::Approved) {
                return $locked;
            }

            $locked->update([
                'status' => BadgeStatus::Revoked,
                'reviewed_by_seller_id' => $admin->getKey(),
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
            ]);

            $locked->storePage?->forceFill(['verified_at' => null])->save();
            $locked->seller?->forceFill(['kyc_verified_at' => null])->save();

            $this->audit->handle($admin, 'badge.revoked', $locked, $reason);
            $this->notifyStatus($locked, BadgeStatus::Revoked, $reason);

            return $locked;
        });
    }

    public function reopen(BadgeApplication $application, Seller $admin, ?string $note = null): BadgeApplication
    {
        return DB::transaction(function () use ($application, $admin, $note) {
            $locked = BadgeApplication::whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canResubmit()) {
                return $locked;
            }

            $locked->update([
                'status' => BadgeStatus::Pending,
                'reviewed_by_seller_id' => null,
                'rejection_reason' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ]);

            $this->audit->handle($admin, 'badge.reopened', $locked, $note);
            $this->notifyStatus($locked, BadgeStatus::Pending, $note);

            return $locked;
        });
    }

    public function submit(
        StorePage $page,
        array $data,
        UploadedFile $document,
    ): BadgeApplication {
        return DB::transaction(function () use ($page, $data, $document) {
            // One live application at a time: a second pending one would leave an
            // admin choosing between two of the same thing.
            $open = BadgeApplication::where('store_page_id', $page->getKey())
                ->where('status', BadgeStatus::Pending->value)
                ->exists();

            if ($open) {
                throw ValidationException::withMessages([
                    'document' => 'You already have an application waiting to be reviewed.',
                ]);
            }

            // Encrypted before it touches disk. The signed admin link decrypts
            // it only for the reviewer, and the marker lets old plaintext rows
            // keep working until they age out.
            $stored = "badges/{$page->getKey()}/".Str::uuid().'.enc';
            Storage::disk('local')->put($stored, 'enc:v1:'.Crypt::encryptString($document->get()));

            return BadgeApplication::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->getKey(),
                'status' => BadgeStatus::Pending,
                'business_name' => $data['business_name'],
                'registered_legal_name' => $data['registered_legal_name'],
                'document_type' => $data['document_type'] ?? 'cac',
                'registration_number' => $data['registration_number'],
                'document_path' => $stored,
                'document_expires_at' => $data['document_expires_at'] ?? null,
                'note' => $data['note'] ?? null,
                'submitted_at' => now(),
                'retention_until' => now()->addYears((int) config('legal.kyc_retention_years', 5)),
            ]);
        });
    }

    private function decide(
        BadgeApplication $application,
        Seller $admin,
        BadgeStatus $status,
        ?string $note,
        ?string $reason,
    ): BadgeApplication {
        return DB::transaction(function () use ($application, $admin, $status, $note, $reason) {
            $locked = BadgeApplication::whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            // An application is decided once. A replayed click must not rewrite
            // the decision or clear the reviewer's name.
            if (! $locked->isPending()) {
                return $locked;
            }

            $locked->update([
                'status' => $status,
                'reviewed_by_seller_id' => $admin->getKey(),
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
            ]);

            /*
             * Stamp the page itself, not just the application.
             *
             * The application row answers "what happened to this request". The
             * verified_at column answers "is this page trusted right now", and
             * the directory sort, the sitemap priority and the structured data
             * all read that one. Without this the tick appears in the header
             * from the badge query and nowhere else, and the two answers quietly
             * disagree.
             */
            if ($status === BadgeStatus::Approved && $locked->store_page_id !== null) {
                $locked->storePage?->forceFill(['verified_at' => now()])->save();

                $locked->seller?->forceFill([
                    'legal_name' => $locked->registered_legal_name,
                    'legal_registration_type' => $locked->document_type,
                    'legal_registration_number' => $locked->registration_number,
                    'kyc_verified_at' => now(),
                    'kyc_document_expires_at' => $locked->document_expires_at,
                ])->save();
            }

            $this->audit->handle(
                $admin,
                'badge.'.Str::lower($status->value),
                $locked,
                $reason ?: $note,
            );
            $this->notifyStatus($locked, $status, $reason);

            return $locked;
        });
    }

    private function notifyStatus(BadgeApplication $application, BadgeStatus $status, ?string $reason): void
    {
        $application->seller?->notify(new KycStatusChangedNotification($application->id, $status->value, $reason));
    }
}
