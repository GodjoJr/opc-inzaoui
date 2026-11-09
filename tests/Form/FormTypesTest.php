<?php

namespace App\Tests\Form;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Form\AlbumType;
use App\Form\GuestType;
use App\Form\MediaType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class FormTypesTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidAlbumType(): void
    {
        $formData = [
            'name' => 'Concerts 2026',
        ];

        $model = new Album();
        $form = $this->factory->create(AlbumType::class, $model);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Concerts 2026', $model->getName());
    }

    public function testSubmitValidGuestType(): void
    {
        $formData = [
            'email' => 'invite@example.com',
            'name'  => 'Invité Spécial',
        ];

        $model = new User();
        $form = $this->factory->create(GuestType::class, $model);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('invite@example.com', $model->getEmail());
        $this->assertSame('Invité Spécial', $model->getName());
    }

    public function testSubmitValidMediaType(): void
    {
        $formData = [
            'title' => 'Affiche Concert',
        ];

        $model = new Media();
        $form = $this->factory->create(MediaType::class, $model);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Affiche Concert', $model->getTitle());
    }
}