<?php

namespace App\Http\Controllers\EkycControllers;

use App\Http\Controllers\Controller;
use App\Models\Owner\Owner;
use App\Models\Ekyc\EkycFaceVerification;
use App\Services\EkycServices\FaceProviderService;
use App\Services\EkycServices\OwnerEkycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OwnerEkycController extends Controller
{
    public function __construct(
        private OwnerEkycService $service,
        private FaceProviderService $faceProvider,
    ) {}


    public function step1(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'owner_name'     => ['required', 'string', 'max:255'],
            'pharmacy_name'  => ['required', 'string', 'max:255'],
            'date_of_birth'  => ['required', 'date'],
            'full_address'   => ['required', 'string'],
            'city'           => ['required', 'string', 'max:255'],
            'phone_number'   => ['required', 'string', 'max:50'],
            'email'          => ['required', 'email', 'max:255'],
        ]);

        $ekyc = $this->service->step1_PharmacyProfile($owner, $data);

        return response()->json([
            'message' => 'Profile saved',
            'ekyc' => $ekyc,
            'profile_completed' => true
        ]);
    }

    public function getStep1(Owner $owner)
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            return response()->json([
                'exists' => false,
                'owner_name' => null,
                'pharmacy_name' => null,
                'date_of_birth' => null,
                'full_address' => null,
                'city' => null,
                'phone_number' => null,
                'email' => null,
            ]);
        }

        return response()->json([
            'exists' => true,
            ...$ekyc->only([
                'owner_name',
                'pharmacy_name',
                'date_of_birth',
                'full_address',
                'city',
                'phone_number',
                'email',
            ]),
            'status' => $ekyc->ekyc_review ?? 'draft',
        ]);
    }

    public function getProgress(Owner $owner)
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            return response()->json([
                'profile_completed' => false,
                'documents_completed' => false,
                'selfie_completed' => false,
                'submitted' => false,
            ]);
        }

        $hasIdFront = $ekyc->documents()
            ->where('document_type', 'id_front')
            ->exists();

        $hasIdBack = $ekyc->documents()
            ->where('document_type', 'id_back')
            ->exists();

        return response()->json([
            'profile_completed' =>
            !empty($ekyc->owner_name) &&
                !empty($ekyc->pharmacy_name) &&
                !empty($ekyc->date_of_birth),

            'documents_completed' =>
            $hasIdFront && $hasIdBack,

            'selfie_completed' => !empty($ekyc->selfie_url),

            'submitted' =>
            $ekyc->ekyc_review === 'pending',
        ]);
    }

    public function getEkyc(Owner $owner)
    {
        return response()->json($owner->ekyc()->with('documents')->first());
    }

    public function step2(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'id_front' => ['nullable', 'file'],
            'id_back' => ['nullable', 'file'],
            'license' => ['nullable', 'file'],
            'professional' => ['nullable', 'file'],
            'registration' => ['nullable', 'file'],
        ]);

        $this->service->step2_RequireDocument($owner, $data);

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'completed' => true
        ]);
    }

    public function getStep2(Owner $owner)
    {
        return response()->json(
            $this->service->getStep2Documents($owner)
        );
    }


    public function uploadSelfie(Request $request, Owner $owner)
    {
        $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            return response()->json(['message' => 'Complete Step 1 first'], 400);
        }

        $path = $request->file('selfie')
            ->store("ekyc/owner_{$owner->id}/selfie", 'public');

        if ($ekyc->selfie_url) {
            Storage::disk('public')->delete($ekyc->selfie_url);
        }

        $ekyc->update([
            'selfie_url' => $path,
        ]);
        return response()->json([
            'message' => 'Selfie uploaded',
            'selfie_url' => $path,
        ]);
    }

    public function getSelfie(Owner $owner)
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc || !$ekyc->selfie_url) {
            return response()->json([
                'exists' => false,
                'selfie_url' => null,
            ]);
        }

        return response()->json([
            'exists' => true,
            'selfie_url' => $ekyc->selfie_url,
            'raw_path' => $ekyc->selfie_url,
        ]);
    }

    public function step3(Request $request, Owner $owner)
    {
        $startedAt = microtime(true);
        $ekyc = $owner->ekyc;

        if (!$ekyc || !$ekyc->selfie_url) {
            return response()->json([
                'message' => 'Upload selfie first'
            ], 400);
        }

        $ekyc->load('documents');

        $idFront = $ekyc->documents
            ->where('document_type', 'id_front')
            ->first();

        $idBack = $ekyc->documents
            ->where('document_type', 'id_back')
            ->first();

        if (!$idFront || !$idBack) {
            return response()->json(['message' => 'Complete Step 2 first'], 400);
        }

        $selfiePath = storage_path('app/public/' . $ekyc->selfie_url);

        if (!file_exists($selfiePath)) {
            return response()->json([
                'message' => 'Selfie file missing on server'
            ], 400);
        }

        try {

            $compareStarted = microtime(true);
            $result = $this->faceProvider->compareFiles(
                storage_path('app/public/' . $idFront->file_url),
                storage_path('app/public/' . $idBack->file_url),
                storage_path('app/public/' . $ekyc->selfie_url)
            );
            $compareEnded = microtime(true);

            EkycFaceVerification::create([
                'owner_id' => $owner->id,
                'ekyc_id'  => $ekyc->id,
                'score'    => $result['score'] ?? 0,
                'threshold' => $result['threshold'] ?? 0,
                'passed'   => $result['passed'] ?? false,
            ]);

            $endedAt = microtime(true);
            return response()->json([
                'message' => 'Verification completed',
                'result' => $result,
                'timing' => [
                    'face_compare_seconds' => round($compareEnded - $compareStarted, 2),
                    'total_request_seconds' => round($endedAt - $startedAt, 2),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Face comparison failed',
                'error' => $e->getMessage(),
            ], 502);
        }
    }


    public function getFaceResult(Owner $owner)
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            return response()->json([
                'message' => 'No eKYC record found'
            ], 404);
        }

        // get latest result
        $result = $ekyc->faceVerifications()
            ->latest()
            ->first();

        if (!$result) {
            return response()->json([
                'message' => 'No face verification result yet'
            ], 404);
        }

        return response()->json([
            'score' => $result->score,
            'threshold' => $result->threshold,
            'passed' => $result->passed,
            'created_at' => $result->created_at,
        ]);
    }

    public function step4Submit(Owner $owner)
    {
        return response()->json($this->service->step4_ReviewApplication($owner));
    }
    

    public function getReviewStatus(Owner $owner)
    {
        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            return response()->json([
                'exists' => false,
                'submitted' => false,
                'status' => 'draft',
                'review_message' => null,
                'reviewed_at' => null,
                'submitted_at' => null,
            ]);
        }

        return response()->json([
            'exists' => true,

            // pending / approved / rejected / draft
            'status' => $ekyc->status ?? 'draft',

            // submitted or not
            'submitted' => $ekyc->status === 'pending'
                || $ekyc->status === 'approved'
                || $ekyc->status === 'rejected'
                || $ekyc->status === 'suspended',

            // admin review message
            'review_message' => $ekyc->review_message,

            // when owner submitted
            'submitted_at' => $ekyc->submitted_at,

            // when admin reviewed
            'reviewed_at' => $ekyc->reviewed_at,
        ]);
    }
}
