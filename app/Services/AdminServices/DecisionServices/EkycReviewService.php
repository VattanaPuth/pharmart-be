<?php

namespace App\Services\AdminServices\DecisionServices;

use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\Owner;
use Illuminate\Support\Collection;

interface EkycReviewService
{
    public function getPendingSubmissions(): Collection;
    public function decisionReview(Owner $owner, string $decision, ?int $reviewedBy = null): OwnerEkyc;
}
