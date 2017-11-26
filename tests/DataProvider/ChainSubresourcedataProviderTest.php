<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Tests\DataProvider;

use ApiPlatform\Core\DataProvider\ChainSubresourceDataProvider;
use ApiPlatform\Core\DataProvider\SubresourceDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;

/**
 * Retrieves items from a persistence layer.
 */
class ChainSubresourcedataProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSubresource()
    {
        $dummy = new Dummy();
        $dummy->setName('Rosa');
        $dummy2 = new Dummy();
        $dummy2->setName('Parks');

        $context = ['identifiers' => ['id' => Dummy::class], 'property' => 'relatedDummies'];

        $createFirstDataProvider = function() use ($context, $dummy, $dummy2) {
            $firstDataProvider = $this->prophesize(SubresourceDataProviderInterface::class);
            $firstDataProvider->getSubresource(Dummy::class, ['id' => 1], $context, 'get')->willReturn([$dummy, $dummy2])->willThrow(ResourceClassNotSupportedException::class);

            return $firstDataProvider->reveal();
        };

        $createSecondDataProvider = function() use ($context, $dummy, $dummy2) {
            $secondDataProvider = $this->prophesize(SubresourceDataProviderInterface::class);
            $secondDataProvider->getSubresource(Dummy::class, ['id' => 1], $context, 'get')->willReturn([$dummy, $dummy2]);

            return $secondDataProvider->reveal();
        };

        $createThirdDataProvider = function() use ($context, $dummy) {
            $thirdDataProvider = $this->prophesize(SubresourceDataProviderInterface::class);
            $thirdDataProvider->getSubresource(Dummy::class, ['id' => 1], $context, 'get')->willReturn([$dummy]);

            return $thirdDataProvider->reveal();
        };

        $chainSubresourceDataProvider = new ChainSubresourceDataProvider([$createFirstDataProvider(), $createSecondDataProvider(), $createThirdDataProvider()]);

        $this->assertEquals([$dummy, $dummy2], $chainSubresourceDataProvider->getSubresource(Dummy::class, ['id' => 1], $context, 'get'));
    }

    public function testGetCollectionExeptions()
    {
        $firstDataProvider = $this->prophesize(SubresourceDataProviderInterface::class);
        $firstDataProvider->getSubresource('notfound', ['id' => 1], [], 'get')->willThrow(ResourceClassNotSupportedException::class);

        $chainItemDataProvider = new ChainSubresourceDataProvider([$firstDataProvider->reveal()]);

        $this->assertEquals('', $chainItemDataProvider->getSubresource('notfound', ['id' => 1], [], 'get'));
    }
}
