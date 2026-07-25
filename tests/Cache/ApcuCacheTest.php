<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function apcu_clear_cache;
use function apcu_enabled;
use function extension_loaded;

/**
 * Test suite for {@see ApcuCache}.
 *
 * The functional tests require the apcu extension to be loaded and enabled; on the CLI that means running with
 * <code>apc.enable_cli=1</code>. Where it is unavailable they are skipped, and the constructor guard is exercised
 * instead.
 */
final class ApcuCacheTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (self::isApcuEnabled()) {
            apcu_clear_cache();
        }
    }

    private static function isApcuEnabled(): bool
    {
        return extension_loaded('apcu') && apcu_enabled();
    }

    private function requireApcu(): void
    {
        if (!self::isApcuEnabled()) {
            self::markTestSkipped('The apcu extension must be loaded and enabled (apc.enable_cli=1 on the CLI).');
        }
    }

    public function testTryGet_WhenKeyAbsent_ReturnsFalse(): void
    {
        $this->requireApcu();
        $cache = new ApcuCache();

        self::assertFalse($cache->tryGet('absent', $value));
    }

    public function testSetThenTryGet_ReturnsStoredValue(): void
    {
        $this->requireApcu();
        $cache = new ApcuCache();

        $cache->set('key', 'value');

        self::assertTrue($cache->tryGet('key', $value));
        self::assertSame('value', $value);
    }

    public function testTryGet_WhenStoredValueIsNull_ReturnsTrue(): void
    {
        $this->requireApcu();
        $cache = new ApcuCache();

        $cache->set('key', null);

        self::assertTrue($cache->tryGet('key', $value));
        self::assertNull($value);
    }

    public function testTryGet_WhenStoredValueIsFalse_ReturnsTrue(): void
    {
        $this->requireApcu();
        $cache = new ApcuCache();

        $cache->set('key', false);

        self::assertTrue($cache->tryGet('key', $value));
        self::assertFalse($value);
    }

    public function testSet_OverwritesExistingValue(): void
    {
        $this->requireApcu();
        $cache = new ApcuCache();

        $cache->set('key', 'first');
        $cache->set('key', 'second');

        self::assertTrue($cache->tryGet('key', $value));
        self::assertSame('second', $value);
    }

    public function testConstruct_WhenApcuNotEnabled_ThrowsRuntimeException(): void
    {
        if (self::isApcuEnabled()) {
            self::markTestSkipped('apcu is enabled in this environment; the disabled-environment guard cannot run.');
        }

        $this->expectException(RuntimeException::class);

        new ApcuCache();
    }
}
