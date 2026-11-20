<?php

namespace App\Command;

use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:convert-medias-webp',
    description: 'Convertit les anciens médias (JPEG/PNG) au format WebP et met à jour leurs chemins en base de données.'
)]
class ConvertMediasWebpCommand extends Command
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'keep-originals',
            null,
            InputOption::VALUE_NONE,
            'Conserver les fichiers JPEG/PNG d\'origine sur le disque après conversion'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keepOriginals = $input->getOption('keep-originals');

        $medias = $this->mediaRepository->findAll();
        $convertedCount = 0;
        $skippedCount = 0;

        $io->title('Migration des médias existants vers le format WebP');

        foreach ($medias as $media) {
            $relativePath = $media->getPath();

            if (!$relativePath || str_ends_with(strtolower($relativePath), '.webp')) {
                $skippedCount++;
                continue;
            }

            $fullSourcePath = $this->publicDir . '/' . ltrim($relativePath, '/');

            if (!file_exists($fullSourcePath) || !is_file($fullSourcePath)) {
                $io->warning(sprintf('Fichier introuvable sur le disque : %s (ID Media: %d)', $relativePath, $media->getId()));
                continue;
            }

            $extension = strtolower(pathinfo($fullSourcePath, PATHINFO_EXTENSION));
            $image = match ($extension) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($fullSourcePath),
                'png' => $this->createPngWithAlpha($fullSourcePath),
                default => false,
            };

            if ($image === false) {
                $io->error(sprintf('Impossible de lire l\'image : %s', $relativePath));
                continue;
            }

            $image = $this->resizeIfNeeded($image, 1920);

            $newRelativePath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $relativePath);
            $fullDestPath = $this->publicDir . '/' . ltrim($newRelativePath, '/');

            if (imagewebp($image, $fullDestPath, 82)) {
                imagedestroy($image);

                $media->setPath($newRelativePath);

                if (!$keepOriginals && $fullSourcePath !== $fullDestPath) {
                    unlink($fullSourcePath);
                }

                $convertedCount++;
                $io->text(sprintf('✔ Converti : %s -> %s', $relativePath, $newRelativePath));
            } else {
                imagedestroy($image);
                $io->error(sprintf('Échec de l\'écriture WebP pour : %s', $relativePath));
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Migration terminée : %d image(s) convertie(s), %d ignorée(s) (déjà en WebP ou vides).',
            $convertedCount,
            $skippedCount
        ));

        return Command::SUCCESS;
    }

    private function createPngWithAlpha(string $path): \GdImage|false
    {
        $image = @imagecreatefrompng($path);
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