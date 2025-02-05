<?php

declare(strict_types=1);

namespace App\Tests\App\Collection;

use App\Service\RefreshCachedValuesQueue;
use App\Tests\Factory\CollectionFactory;
use App\Tests\Factory\ItemFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\AppTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CollectionCountersTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public ?RefreshCachedValuesQueue $refreshCachedValuesQueue;

    protected function setUp(): void
    {
        $this->refreshCachedValuesQueue = $this->getContainer()->get(RefreshCachedValuesQueue::class);
    }

    /*
     * When adding a new child, all parent counters must be increased by 1
     */
    public function test_add_child_collection(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $collectionLevel1 = CollectionFactory::createOne(['owner' => $user]);
        $collectionLevel2 = CollectionFactory::createOne(['parent' => $collectionLevel1, 'owner' => $user]);
        $collectionLevel3 = CollectionFactory::createOne(['parent' => $collectionLevel2, 'owner' => $user]);

        // Act
        $this->refreshCachedValuesQueue->process();

        // Assert
        $this->assertSame(2, $collectionLevel1->getCachedValues()['counters']['children']);
        $this->assertSame(1, $collectionLevel2->getCachedValues()['counters']['children']);
        $this->assertSame(0, $collectionLevel3->getCachedValues()['counters']['children']);
    }

    /*
     * When moving a child:
     * - Decrease all old parents collections counters by the number of children collection belonging to the child + 1 (itself)
     * - Decrease all old parents items counters by the number of items in the child and in all the child's children
     * - Increase all new parents collections counters by the number of children collection belonging to the child + 1 (itself)
     * - Increase all new parents items counters by the number of items in the child and in all the child's children
     */
    public function test_move_child_collection(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $collectionLevel1 = CollectionFactory::createOne(['parent' => null, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel1, 'owner' => $user]);
        $collectionLevel2 = CollectionFactory::createOne(['parent' => $collectionLevel1, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel2, 'owner' => $user]);
        $collectionLevel3 = CollectionFactory::createOne(['parent' => $collectionLevel2, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel3, 'owner' => $user]);
        $collectionLevel4 = CollectionFactory::createOne(['parent' => $collectionLevel3, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel4, 'owner' => $user]);

        // Act
        $newParentCollection = CollectionFactory::createOne(['owner' => $user]);
        $collectionLevel3->setParent($newParentCollection->object());
        $collectionLevel3->save();

        $this->refreshCachedValuesQueue->process();

        // Assert
        $this->assertSame(6, $newParentCollection->getCachedValues()['counters']['items']);
        $this->assertSame(2, $newParentCollection->getCachedValues()['counters']['children']);

        $this->assertSame(6, $collectionLevel1->getCachedValues()['counters']['items']);
        $this->assertSame(1, $collectionLevel1->getCachedValues()['counters']['children']);

        $this->assertSame(3, $collectionLevel2->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel2->getCachedValues()['counters']['children']);

        $this->assertSame(6, $collectionLevel3->getCachedValues()['counters']['items']);
        $this->assertSame(1, $collectionLevel3->getCachedValues()['counters']['children']);

        $this->assertSame(3, $collectionLevel4->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel4->getCachedValues()['counters']['children']);
    }

    /*
     * When deleting a child:
     * - Decrease all old parents collections counters by the number of children collection belonging to the child + 1 (itself)
     * - Decrease all old parents items counters by the number of items in the child and in all the child's children
     */
    public function test_delete_child_collection(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $collectionLevel1 = CollectionFactory::createOne(['owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel1, 'owner' => $user]);
        $collectionLevel2 = CollectionFactory::createOne(['parent' => $collectionLevel1, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel2, 'owner' => $user]);
        $collectionLevel3 = CollectionFactory::createOne(['parent' => $collectionLevel2, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel3, 'owner' => $user]);
        $collectionLevel4 = CollectionFactory::createOne(['parent' => $collectionLevel3, 'owner' => $user]);
        ItemFactory::createMany(3, ['collection' => $collectionLevel4, 'owner' => $user]);

        // Act
        $collectionLevel3->remove();
        $this->refreshCachedValuesQueue->process();

        // Assert
        $this->assertSame(6, $collectionLevel1->getCachedValues()['counters']['items']);
        $this->assertSame(1, $collectionLevel1->getCachedValues()['counters']['children']);

        $this->assertSame(3, $collectionLevel2->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel2->getCachedValues()['counters']['children']);
    }

    /*
     * When adding a new item, all parent counters must be increased by 1
     */
    public function test_add_item(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $collectionLevel1 = CollectionFactory::createOne(['owner' => $user]);
        $collectionLevel2 = CollectionFactory::createOne(['parent' => $collectionLevel1, 'owner' => $user]);
        $collectionLevel3 = CollectionFactory::createOne(['parent' => $collectionLevel2, 'owner' => $user]);
        ItemFactory::createOne(['collection' => $collectionLevel3, 'owner' => $user]);

        // Act
        $this->refreshCachedValuesQueue->process();

        // Assert
        $this->assertSame(1, $collectionLevel1->getCachedValues()['counters']['items']);
        $this->assertSame(1, $collectionLevel2->getCachedValues()['counters']['items']);
        $this->assertSame(1, $collectionLevel3->getCachedValues()['counters']['items']);
    }

    /*
     * When moving an item, all parent new counters must be increased by 1 and old parent counters decreased by 1
     */
    public function test_move_item(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $collectionLevel1 = CollectionFactory::createOne(['owner' => $user]);
        $collectionLevel2 = CollectionFactory::createOne(['parent' => $collectionLevel1, 'owner' => $user]);
        $collectionLevel3 = CollectionFactory::createOne(['parent' => $collectionLevel2, 'owner' => $user]);
        $item = ItemFactory::createOne(['collection' => $collectionLevel3, 'owner' => $user]);

        // Act
        $newCollection = CollectionFactory::createOne(['owner' => $user]);
        $item->setCollection($newCollection->object());
        $item->save();

        $this->refreshCachedValuesQueue->process();

        // Assert
        $this->assertSame(1, $newCollection->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel1->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel2->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel3->getCachedValues()['counters']['items']);
    }

    /*
     * When deleting an item decrease all old parents collections counters by one
     */
    public function test_delete_item(): void
    {
        // Arrange
        $user = UserFactory::createOne()->object();
        $collectionLevel1 = CollectionFactory::createOne(['owner' => $user]);
        $collectionLevel2 = CollectionFactory::createOne(['parent' => $collectionLevel1, 'owner' => $user]);
        $collectionLevel3 = CollectionFactory::createOne(['parent' => $collectionLevel2, 'owner' => $user]);
        $item = ItemFactory::createOne(['collection' => $collectionLevel3, 'owner' => $user]);

        // Act
        $item->remove();

        $this->refreshCachedValuesQueue->process();

        // Assert
        $this->assertSame(0, $collectionLevel1->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel2->getCachedValues()['counters']['items']);
        $this->assertSame(0, $collectionLevel3->getCachedValues()['counters']['items']);
    }
}
