<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadsDirectory,
        private SluggerInterface $slugger
    ) {
    }

    public function uploadAndConvertToWebp(UploadedFile $file, int $quality = 82): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.webp';
        $destinationPath = $this->uploadsDirectory . '/' . $newFilename;

        if (!is_dir($this->uploadsDirectory)) {
            mkdir($this->uploadsDirectory, 0775, true);
        }

        $mimeType = $file->getMimeType();
        $sourcePath = $file->getPathname();

        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png' => $this->createPngWithAlpha($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => null,
        };

        if ($image !== null) {
            $image = $this->resizeIfNeeded($image, 1920);
            imagewebp($image, $destinationPath, $quality);
            imagedestroy($image);

            return 'uploads/' . $newFilename;
        }

        $fallbackName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
        $file->move($this->uploadsDirectory, $fallbackName);

        return 'uploads/' . $fallbackName;
    }

    private function createPngWithAlpha(string $path): \GdImage|false
    {
        $image = imagecreatefrompng($path);
        if ($image !== false) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        return $image;
    }

    private function resizeIfNeeded(\GdImage $image, int $maxWidth): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth) {
            return $image;
        }

        $newWidth = $maxWidth;
        $newHeight = (int) floor($height * ($maxWidth / $width));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }
}