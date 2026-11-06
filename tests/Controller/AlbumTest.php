<?php

namespace App\Tests\Entity;

use App\Entity\Album;
use App\Entity\Media;
use PHPUnit\Framework\TestCase;

class AlbumTest extends TestCase
{
    public function testAlbumGettersAndSetters(): void
    {
        $album = new Album();
        $this->assertNull($album->getId());

        $album->setName('Voyages');
        $this->assertSame('Voyages', $album->getName());
    }

    public function testMediaAssociation(): void
    {
        $album = new Album();
        $media = new Media();

        $media->setAlbum($album);
        $this->assertSame($album, $media->getAlbum());
    }
}