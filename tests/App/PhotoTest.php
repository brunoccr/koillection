<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Enum\VisibilityEnum;
use App\Tests\Factory\AlbumFactory;
use App\Tests\Factory\PhotoFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Tests\AppTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PhotoTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->followRedirects();
    }

    public function test_can_create_photo(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $this->client->loginUser($user);
        $album = AlbumFactory::createOne(['owner' => $user])->object();

        // Act
        $this->client->request('GET', '/photos/add?album='.$album->getId());
        $this->client->submitForm('Submit', [
            'photo[title]' => 'Bedroom',
            'photo[visibility]' => VisibilityEnum::VISIBILITY_PRIVATE,
            'photo[album]' => $album->getId(),
            'photo[place]' => 'Lyon',
            'photo[takenAt]' => '2022-12-01',
            'photo[comment]' => 'This is a comment'
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        PhotoFactory::assert()->exists([
            'title' => 'Bedroom',
            'album' => $album->getId(),
            'visibility' => VisibilityEnum::VISIBILITY_PRIVATE,
            'owner' => $user->getId()
        ]);
    }

    public function test_cant_create_photo_without_album(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $this->client->loginUser($user);

        // Act
        $this->client->request('GET', '/photos/add');

        // Assert
        $this->assertTrue($this->client->getResponse()->isNotFound());
    }

    public function test_can_edit_photo(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $this->client->loginUser($user);
        $album = AlbumFactory::createOne(['owner' => $user])->object();
        $photo = PhotoFactory::createOne(['album' => $album, 'owner' => $user]);

        // Act
        $this->client->request('GET', '/photos/'.$photo->getId().'/edit');
        $this->client->submitForm('Submit', [
            'photo[title]' => 'New title',
            'photo[visibility]' => VisibilityEnum::VISIBILITY_PRIVATE,
            'photo[album]' => $album->getId(),
            'photo[place]' => 'Lyon',
            'photo[takenAt]' => '2022-12-01',
            'photo[comment]' => 'This is a comment'
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        PhotoFactory::assert()->exists([
            'title' => 'New title',
            'album' => $album->getId(),
            'visibility' => VisibilityEnum::VISIBILITY_PRIVATE,
            'owner' => $user->getId()
        ]);
    }

    public function test_can_delete_photo(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $this->client->loginUser($user);
        $album = AlbumFactory::createOne(['owner' => $user])->object();
        $photo = PhotoFactory::createOne(['album' => $album, 'owner' => $user]);

        // Act
        $crawler = $this->client->request('GET', '/albums/'.$album->getId());
        $crawler->filter('#modal-delete form')->getNode(0)->setAttribute('action', '/photos/'.$photo->getId().'/delete');
        $this->client->submitForm('Agree');

        // Assert
        $this->assertResponseIsSuccessful();
        AlbumFactory::assert()->count(1);
        PhotoFactory::assert()->notExists(0);
    }
}
