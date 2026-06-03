<?php

namespace App\Services\EkycServices;

use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\Owner;

interface OwnerEkycService
{
    public function step1_PharmacyProfile(Owner $owner, array $data): OwnerEkyc;
    public function step2_RequireDocument(Owner $owner, array $data): bool;
    public function getStep2Documents(Owner $owner);
    public function step4_ReviewApplication(Owner $owner): array;
}
