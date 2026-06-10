<?php

namespace App\Services\AdminServices\DecisionServices\impl;

use App\Mail\EkycDecisionMail;
use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\Owner;
use App\Services\AdminServices\DecisionServices\EkycReviewService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EkycReviewServiceImpl implements EkycReviewService
{
    public function getPendingSubmissions(): Collection
    {
        return OwnerEkyc::with('owner.register')
            ->whereIn('status', ['submitted', 'pending_review'])
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at', 'asc')
            ->get();
    }
    private function upsert(Owner $owner, array $attributes): OwnerEkyc
    {
        return DB::transaction(function () use ($owner, $attributes) {
            return $owner->ekyc()->updateOrCreate(
                ['owner_id' => $owner->id],
                $attributes
            );
        });
    }
    public function decisionReview(Owner $owner, string $decision, ?int $reviewedBy = null): OwnerEkyc
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            throw new RuntimeException('No eKYC record found.');
        }

        // Optional: Only allow decision after submit
        if (!$ekyc->submitted_at) {
            throw new RuntimeException('Cannot review before the user submits the application.');
        }

        // Validate decision
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid decision. Use approved or rejected.');
        }

        $updatedEkyc = $this->upsert($owner, [
            'status' => $decision,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => Carbon::now(),
        ]);

        // Send email notification to owner
        $ownerEmail = $updatedEkyc->email ?? $owner->register?->email;
        $ownerName = $owner->setting?->owner_name ?? $ownerEmail ?? 'Owner';
        if ($ownerEmail) {
            Mail::to($ownerEmail)->send(
                new EkycDecisionMail(
                    $updatedEkyc,
                    $ownerName,
                    $decision
                )
            );
        }

        return $updatedEkyc;
    }
}
