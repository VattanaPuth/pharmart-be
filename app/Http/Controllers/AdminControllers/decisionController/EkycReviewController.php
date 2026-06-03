<?php

namespace App\Http\Controllers\AdminControllers\decisionController;

use App\Http\Controllers\Controller;
use App\Models\Owner\Owner;
use App\Services\AdminServices\DecisionServices\EkycReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EkycReviewController extends Controller
{
    public function __construct(private EkycReviewService $service) {}

    public function index()
    {
        $submissions = $this->service->getPendingSubmissions();
        return response()->json($submissions);
    }

    public function adminDecision(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'ekyc_review' => ['required', Rule::in(['approved','rejected'])],
        ]);

        $ekyc = $this->service->decisionReview(
            $owner,
            $data['ekyc_review'],
            auth('admin')->id()
        );

        return response()->json($ekyc);
    }

    public function getEkycImages(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['id_front', 'selfie'])],
        ]);

        $ekyc = $owner->ekyc;

        if (!$ekyc) {
            return response()->json([
                'message' => 'eKYC submission not found for this owner',
            ], 404);
        }

        $path = $data['type'] === 'id_front' ? $ekyc->id_front_url : $ekyc->selfie_url;

        if (!$path) {
            return response()->json([
                'message' => 'Requested file is not available',
            ], 404);
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'Requested file not found in storage',
            ], 404);
        }

        $absolutePath = Storage::disk('public')->path($path);
        return response()->file($absolutePath);
    }
}
