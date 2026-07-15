<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Fakes\FakeCache;

/**
 * Test suite for {@see MetadataCache}, covering the two-tier get-or-compute behavior: the request-local (L1) tier, the
 * shared (L2) tier, and the compute-and-populate path taken on a miss.
 */
final class MetadataCacheTest extends TestCase
{
    public function testGet_WhenValueAlreadyInProcess_ReturnsCachedValueWithoutRecomputing(): void
    {
        // Arrange: a first call populates the L1 tier.
        $cache = new MetadataCache();
        $calls = 0;
        $factory = function () use (&$calls): string {
            $calls++;

            return 'computed';
        };
        $cache->get('id', $factory);

        // Act: the second call for the same id must hit L1 and skip the factory.
        $result = $cache->get('id', $factory);

        // Assert
        self::assertSame('computed', $result);
        self::assertSame(1, $calls, 'The factory must run only once; the second call is served from the L1 tier');
    }

    public function testGet_WhenValueInSharedCache_ReturnsFromSharedWithoutRecomputing(): void
    {
        // Arrange: the shared (L2) tier already holds the value; the L1 tier is empty.
        $shared = new FakeCache();
        $shared->values['id'] = 'fromShared';
        $cache = new MetadataCache($shared);
        $calls = 0;

        // Act
        $result = $cache->get('id', function () use (&$calls): string {
            $calls++;

            return 'computed';
        });

        // Assert
        self::assertSame('fromShared', $result);
        self::assertSame(0, $calls, 'A shared-cache hit must not invoke the factory');
    }

    public function testGet_WhenMiss_ComputesAndPopulatesBothTiers(): void
    {
        // Arrange
        $shared = new FakeCache();
        $cache = new MetadataCache($shared);

        // Act
        $result = $cache->get('id', static fn(): string => 'computed');

        // Assert: the computed value is returned and written through to the shared tier.
        self::assertSame('computed', $result);
        self::assertSame(['id'], $shared->storedIds);
        self::assertSame('computed', $shared->values['id'] ?? null);
    }

    public function testGet_WithoutSharedCache_ComputesOnMiss(): void
    {
        // Arrange: no L2 tier is configured, so a miss falls straight through to the factory.
        $cache = new MetadataCache();

        // Act
        $result = $cache->get('id', static fn(): string => 'computed');

        // Assert
        self::assertSame('computed', $result);
    }
}
