<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Owner\OwnerSetting;
use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\OwnerNotification;
use App\Services\NotificationServices\impl\NotificationServiceImpl;

class AdminPharmacyController extends Controller
{

    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $query = OwnerSetting::with([
            'ekyc.documents',
            'ekyc.faceVerifications',
        ]);

        // -----------------------
        // SEARCH
        // -----------------------
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pharmacy_name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%");
            });
        }

        // -----------------------
        // STATUS FILTER
        // -----------------------
        if ($status && $status !== 'all') {
            $query->where(function ($q) use ($status) {
                $q->where('status', $status)
                    ->orWhereHas('ekyc', function ($ekyc) use ($status) {
                        $ekyc->where('status', $status);
                    });
            });
        }

        // -----------------------
        // ORDER BY LATEST
        // -----------------------
        $query->orderByDesc('created_at');

        // -----------------------
        // PAGINATE
        // -----------------------
        $owners = $query->paginate($perPage);

        // -----------------------
        // TRANSFORM
        // -----------------------
        $owners->getCollection()->transform(function ($p) {

            $ekyc = $p->ekyc;

            $latestFace = $ekyc?->faceVerifications()
                ->latest()
                ->first();

            return [
                'id' => $p->owner_id,
                'name' => $p->pharmacy_name,
                'owner' => $p->owner_name,
                'status' => $p->status ?? $ekyc?->status ?? 'draft',

                'ekyc' => [
                    'status' => $ekyc?->status,
                    'review_message' => $ekyc?->review_message,
                    'submitted_at' => $ekyc?->submitted_at,
                    'face' => [
                        'score' => (float) ($latestFace?->score ?? 0),
                        'threshold' => (float) ($latestFace?->threshold ?? 0),
                        'passed' => (bool) ($latestFace?->passed ?? false),
                    ],
                ],

                'location' => [
                    'city' => $p->city,
                    'address' => $p->address,
                    'gps_location' => $p->gps_location,
                ],

                'contact' => [
                    'phone' => $p->phone_number,
                    'email' => $ekyc?->email,
                ],

                'documents' => $ekyc?->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'type' => $doc->document_type,
                        'file_url' => asset('storage/' . $doc->file_url),
                        'status' => $doc->status,
                        'review_message' => $doc->review_message,
                        'reviewed_at' => $doc->reviewed_at,
                    ];
                })->values() ?? [],
            ];
        });

        return response()->json($owners);
    }

    public function show($ownerId)
    {
        $owner = OwnerSetting::with([
            'ekyc.documents',
            'ekyc.faceVerifications',
        ])->where('owner_id', $ownerId)->first();

        if (!$owner) {
            return response()->json([
                'message' => 'Pharmacy not found'
            ], 404);
        }

        $ekyc = $owner->ekyc;

        $latestFace = $ekyc?->faceVerifications()
            ->latest()
            ->first();

        return response()->json([
            'id' => $owner->owner_id,
            'name' => $owner->pharmacy_name,
            'owner' => $owner->owner_name,
            'status' => $owner->status ?? $ekyc?->status ?? 'draft',

            'ekyc' => [
                'status' => $ekyc?->status,
                'review_message' => $ekyc?->review_message,
                'submitted_at' => $ekyc?->submitted_at,
                'face' => [
                    'score' => (float) ($latestFace?->score ?? 0),
                    'threshold' => (float) ($latestFace?->threshold ?? 0),
                    'passed' => (bool) ($latestFace?->passed ?? false),
                ],
            ],

            'location' => [
                'city' => $owner->city,
                'address' => $owner->address,
                'gps_location' => $owner->gps_location,
            ],

            'contact' => [
                'phone' => $owner->phone_number,
                'email' => $ekyc?->email,
            ],

            'documents' => $ekyc?->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $doc->document_type,
                    'file_url' => asset('storage/' . $doc->file_url),
                    'status' => $doc->status,
                    'review_message' => $doc->review_message,
                    'reviewed_at' => $doc->reviewed_at,
                ];
            })->values() ?? [],
        ]);
    }
    // ======================================================
    // COUNTS
    // ======================================================
    public function counts()
    {
        return [
            'all' => OwnerSetting::count(),
            'submitted' => OwnerEkyc::where('status', 'submitted')->count(),

            'pending' => OwnerEkyc::where('status', 'pending_review')->count(),

            'approved' => OwnerEkyc::where('status', 'approved')->count(),
            'rejected' => OwnerEkyc::where('status', 'rejected')->count(),

            'suspended' => OwnerSetting::where('status', 'suspended')->count(),
        ];
    }

    // ======================================================
    // UPDATE STATUS
    // ======================================================


    public function updateStatus(
        Request $request,
        int $owner
    ) {
        $request->validate([
            'status' => 'required|in:approved,rejected,suspended',
            'message' => 'nullable|string|max:1000',
        ]);

        $setting = OwnerSetting::where(
            'owner_id',
            $owner
        )->firstOrFail();

        $ekyc = OwnerEkyc::where(
            'owner_id',
            $owner
        )->firstOrFail();

        // =========================
        // SUSPEND
        // =========================
        if ($request->status === 'suspended') {

            $setting->update([
                'status' => 'suspended',
            ]);

            $ekyc->update([
                'status' => 'suspended',
            ]);

            $ekyc->update([
                'review_message' => $request->message,
            ]);

            OwnerNotification::create([
                'owner_id' => $owner,

                'type' => 'account_suspended',

                'title' => 'Account Suspended',

                'message' =>
                $request->message
                    ? "Your pharmacy account has been suspended. Reason: {$request->message}"
                    : 'Your pharmacy account has been suspended.',

                'channels' => [
                    'web',
                    'email',
                ],

                'data' => [
                    'status' => 'suspended',
                    'ekyc_id' => $ekyc->id,
                ],
            ]);

            NotificationServiceImpl::owner(
                $setting->owner,
                'account_suspended',
                [
                    'title' => 'Account Suspended',

                    'message' => $request->message
                        ? "Your pharmacy account has been suspended. Reason: {$request->message}"
                        : 'Your pharmacy account has been suspended.',



                    'reason' => $request->message,

                    'channels' => ['database', 'mail'],

                    'ekyc_id' => $ekyc->id,
                    'status' => 'suspended',
                ]
            );
        }

        // =========================
        // APPROVED / REJECTED
        // =========================
        else {

            $ekyc->update([
                'status' => $request->status,
                'reviewed_at' => now(),
                'review_message' => $request->message,
            ]);

            $setting->update([
                'status' => $request->status,
            ]);

            // APPROVED
            if ($request->status === 'approved') {

                OwnerNotification::create([
                    'owner_id' => $owner,

                    'type' => 'ekyc_approved',

                    'title' => 'eKYC Approved',

                    'message' =>
                    'Your pharmacy verification has been approved. You can now access all owner features.',

                    'channels' => [
                        'web',
                        'email',
                    ],

                    'data' => [
                        'status' => 'approved',
                        'ekyc_id' => $ekyc->id,
                    ],
                ]);

                NotificationServiceImpl::owner(
                    $setting->owner,
                    'ekyc_approved',
                    [
                        'title' => 'eKYC Approved',
                        'message' =>
                        'Your pharmacy verification has been approved. You can now access all owner features.',

                        'channels' => ['database', 'mail'],

                        'ekyc_id' => $ekyc->id,
                        'status' => 'approved',
                    ]
                );
            }

            // REJECTED
            if ($request->status === 'rejected') {

                OwnerNotification::create([
                    'owner_id' => $owner,

                    'type' => 'ekyc_rejected',

                    'title' => 'eKYC Rejected',

                    'message' =>
                    $request->message
                        ? "Your verification was rejected. Reason: {$request->message}"
                        : 'Your verification was rejected.',

                    'channels' => [
                        'web',
                        'email',
                    ],

                    'data' => [
                        'status' => 'rejected',
                        'ekyc_id' => $ekyc->id,
                    ],
                ]);

                NotificationServiceImpl::owner(
                    $setting->owner,
                    'ekyc_rejected',
                    [
                        'title' => 'eKYC Rejected',

                        'reason' => $request->message
                            ? "Your verification was rejected. Reason: {$request->message}"
                            : 'Your verification was rejected.',

                        'channels' => ['database', 'mail'],

                        'ekyc_id' => $ekyc->id,
                        'status' => 'rejected',
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Status updated successfully',
        ]);
    }
}
