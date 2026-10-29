<?php

namespace App\Services\Student;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentPhotoService
{
    private const MAX_DIMENSION = 600;

    public function store(UploadedFile $file, ?string $previousPath = null): string
    {
        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        $resizedBinary = $this->resizeIfSupported($file);

        if ($resizedBinary !== null) {
            $path = 'students/photos/'.Str::uuid()->toString().'.jpg';
            Storage::disk('public')->put($path, $resizedBinary);

            return $path;
        }

        return (string) $file->store('students/photos', 'public');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function resizeIfSupported(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false) {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $binary = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }
}
