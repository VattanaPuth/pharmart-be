<?php

namespace App\Services\EkycServices\impl;

use App\Models\Admin\AdminNotification;
use App\Models\Auth\Admin;
use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\Owner;
use App\Services\EkycServices\OwnerEkycService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\NotificationServices\impl\NotificationServiceImpl;
use Tymon\JWTAuth\Facades\JWTAuth;


class OwnerEkycServiceImpl implements OwnerEkycService
{
    private function upsert(Owner $owner, array $attributes): OwnerEkyc
    {
        return DB::transaction(function () use ($owner, $attributes) {
            return $owner->ekyc()->updateOrCreate(
                ['owner_id' => $owner->id],
                $attributes
            );
        });
    }
    public function step1_PharmacyProfile(Owner $owner, array $data): OwnerEkyc
    {
        return $this->upsert($owner, [
            'owner_name'     => $data['owner_name'],
            'pharmacy_name'  => $data['pharmacy_name'],
            'date_of_birth'  => $data['date_of_birth'],
            'full_address'   => $data['full_address'],
            'city'           => $data['city'],
            'phone_number'   => $data['phone_number'],
            'email'          => $data['email'],
        ]);
    }

    private function isProfileComplete(OwnerEkyc $ekyc): bool
    {
        return $ekyc &&
            $ekyc->owner_name &&
            $ekyc->pharmacy_name &&
            $ekyc->date_of_birth &&
            $ekyc->full_address &&
            $ekyc->city &&
            $ekyc->phone_number &&
            $ekyc->email;
    }

    public function step2_RequireDocument(Owner $owner, array $files): bool
    {
        $ekyc = $owner->ekyc;

        if (!$this->isProfileComplete($ekyc)) {
            throw new \RuntimeException('Complete profile first');
        }

        $allowed = [
            'license',
            'professional',
            'registration',
            'id_front',
            'id_back',
        ];

        foreach ($allowed as $key) {

            // only process uploaded files
            if (!isset($files[$key]) || !$files[$key] instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $path = $files[$key]
                ->store("ekyc/owner_{$owner->id}/docs", 'public');

            $ekyc->documents()->updateOrCreate(
                [
                    'document_type' => $key,
                ],
                [
                    'owner_id' => $owner->id,
                    'file_url' => $path,
                    'status' => 'pending',
                ]
            );
        }

        return true;
    }


    public function getStep2Documents(Owner $owner)
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) return null;

        return $ekyc->documents()
            ->get()
            ->groupBy('document_type');
    }


    public function step4_ReviewApplication(Owner $owner): array
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            throw new RuntimeException('No eKYC record found.');
        }

        // -----------------------
        // REQUIRED DOCUMENTS
        // -----------------------

        $hasIdFront = $ekyc->documents()
            ->where('document_type', 'id_front')
            ->exists();

        $hasIdBack = $ekyc->documents()
            ->where('document_type', 'id_back')
            ->exists();

        // -----------------------
        // REQUIRED FIELDS
        // -----------------------

        $missing = [];

        if (!$ekyc->owner_name) {
            $missing[] = 'owner_name';
        }

        if (!$ekyc->pharmacy_name) {
            $missing[] = 'pharmacy_name';
        }

        if (!$ekyc->full_address) {
            $missing[] = 'full_address';
        }

        if (!$ekyc->city) {
            $missing[] = 'city';
        }

        if (!$hasIdFront) {
            $missing[] = 'id_front';
        }

        if (!$hasIdBack) {
            $missing[] = 'id_back';
        }

        if (!$ekyc->selfie_url) {
            $missing[] = 'selfie';
        }

        // optional:
        // require successful face verification

        $hasFaceVerification = $ekyc->faceVerifications()->exists();

        if (!$hasFaceVerification) {
            $missing[] = 'face_verification';
        }

        // -----------------------
        // VALIDATION FAIL
        // -----------------------

        if (!empty($missing)) {
            throw new RuntimeException(
                'Missing required fields: ' . implode(', ', $missing)
            );
        }

        // -----------------------
        // UPDATE EKYC
        // -----------------------

        $updatedEkyc = $this->upsert($owner, [
            'submitted_at' => Carbon::now(),
            'reviewed_by'  => null,
            'reviewed_at'  => null,
            'status' => 'pending_review',
        ]);

        // -----------------------
        // UPDATE OWNER STATUS
        // -----------------------

        $owner->setting()->updateOrCreate(
            ['owner_id' => $owner->id],
            [
                'owner_name'    => $ekyc->owner_name,
                'pharmacy_name' => $ekyc->pharmacy_name,
                'displayable_email'=>$ekyc->email,
                'status'        => 'pending',

            ],


        );

        $owner->register()->update([
            'onboarding_completed' => 1,
        ]);

        $user = $owner->register;

        if ($user) {
            $user->refresh();
        }


        $token = JWTAuth::claims([
            'role' => $user->role,
            'onboarding' => $user->onboarding_completed,
            'ekyc_status' => $updatedEkyc->status,
        ])->fromUser($user);
        // -----------------------
        // ADMIN NOTIFICATIONS
        // -----------------------

        $adminIds = Admin::query()->pluck('id');

        foreach ($adminIds as $adminId) {

            AdminNotification::create([
                'admin_id' => $adminId,
                'owner_id' => $owner->id,
                'owner_name' => $owner->setting->owner_name,
                'ekyc_id'  => $updatedEkyc->id,

                'type' => 'ekyc_submitted',

                'title' => 'New eKYC submission',


                'message' =>
                'Owner #' . $owner->id .
                    ' submitted eKYC and is waiting for review.',

                'data' => [

                    'owner_id' => $owner->id,
                    'owner_name' => $owner->setting->owner_name,
                    'ekyc_id' => $updatedEkyc->id,
                    'submitted_at' => optional(
                        $updatedEkyc->submitted_at
                    )->toISOString(),
                ],

                'is_read' => false,
            ]);
            $admin = Admin::findOrFail($adminId);
            NotificationServiceImpl::admin(
                $admin,
                'ekyc_submitted',
                [
                    'owner_id'   => $owner->id,
                    'owner_name' => $owner->setting->owner_name,
                    'ekyc_id'    => $updatedEkyc->id,

                    'title' => 'New eKYC submission',

                    'message' =>
                    'Owner #' . $owner->id .
                        ' submitted eKYC and is waiting for review.',

                    'submitted_at' => optional(
                        $updatedEkyc->submitted_at
                    )->toISOString(),
                ]
            );
        }

        return [
            'ekyc' => $updatedEkyc,
            'token' => $token,
        ];
    }
}
