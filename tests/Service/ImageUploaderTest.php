<?php

namespace App\Tests\Service;

use App\Service\ImageUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ImageUploaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/inazaoui_test_uploads_' . uniqid();
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testUploadAndConvertJpegToWebp(): void
    {
        $sourceFile = $this->tempDir . '/test-photo.jpg';
        $im = imagecreatetruecolor(2000, 1000);
        imagejpeg($im, $sourceFile, 90);

        $uploadedFile = new UploadedFile(
            $sourceFile,
            'test-photo.jpg',
            'image/jpeg',
            null,
            true // mode test
        );

        // 2. Exécuter le service
        $uploader = new ImageUploader($this->tempDir, new AsciiSlugger());
        $relativePath = $uploader->uploadAndConvertToWebp($uploadedFile);

        // 3. Vérifications
        $this->assertStringEndsWith('.webp', $relativePath);
        $generatedFile = $this->tempDir . '/' . basename($relativePath);
        $this->assertFileExists($generatedFile);

        // Vérifier que la largeur a bien été plafonnée à 1920px
        [$width, $height] = getimagesize($generatedFile);
        $this->assertSame(1920, $width);
        $this->assertSame(960, $height);
    }
}