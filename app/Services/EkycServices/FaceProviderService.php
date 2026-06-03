<?php

namespace App\Services\EkycServices;

use App\Models\Owner\Owner;
use Illuminate\Http\UploadedFile;

interface FaceProviderService
{
    public function compareFiles(string $idFrontAbsolutePath,string $idBackAbsolutePath, string $selfieAbsolutePath): array;
}
