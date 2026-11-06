<?php

namespace App\Tests\Entity;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    public function testMediaGettersAndSetters(): void
    {
        $media = new Media();
        $this->assertNull($media->getId());

        $media->setTitle('Aurore Boréale');
        $this->assertSame('Aurore Boréale', $media->getTitle());

        $media->setPath('uploads/aurore.jpg');
        $this->assertSame('uploads/aurore.jpg', $media->getPath());

        $user = new User();
        $media->setUser($user);
        $this->assertSame($user, $media->getUser());

        $album = new Album();
        $media->setAlbum($album);
        $this->assertSame($album, $media->getAlbum());
    }
}