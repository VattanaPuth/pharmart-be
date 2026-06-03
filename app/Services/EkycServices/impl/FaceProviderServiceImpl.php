<?php

namespace App\Services\EkycServices\impl;

use App\Models\Owner\Owner;
use App\Services\EkycServices\FaceProviderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FaceProviderServiceImpl implements FaceProviderService
{
    public function compareFiles(string $idFrontAbsolutePath,string $idBackAbsolutePath, string $selfieAbsolutePath): array
    {
        if (! is_file($idFrontAbsolutePath)) {
            throw new RuntimeException('ID front file not found.');
        }

        if (! is_file($idBackAbsolutePath)) {
            throw new RuntimeException('ID back file not found.');
        }

        if (! is_file($selfieAbsolutePath)) {
            throw new RuntimeException('Selfie file not found.');
        }

        $response = Http::timeout(config('services.face_service.timeout', 60))
            ->retry(2, 300)
            ->withToken(config('services.face_service.token'))
            ->asMultipart()
            ->attach('id_front', fopen($idFrontAbsolutePath, 'r'), basename($idFrontAbsolutePath))
            ->attach('id_back', fopen($idBackAbsolutePath, 'r'), basename($idBackAbsolutePath))
            ->attach('selfie', fopen($selfieAbsolutePath, 'r'), basename($selfieAbsolutePath))
            ->post(rtrim(config('services.face_service.url'), '/').'/compare');

        if (! $response->successful()) {
            $payload = $response->json();
            $reason = is_array($payload) ? ($payload['reason'] ?? 'face_service_error') : 'face_service_error';
            throw new RuntimeException('Face service error ['.$response->status().']: '.$reason);
        }

        $payload = $response->json();

        return [
            'score' => (float) ($payload['score'] ?? 0),
            'threshold' => (float) ($payload['threshold'] ?? 0),
            'passed' => (bool) ($payload['passed'] ?? false),
        ];
    }
}
