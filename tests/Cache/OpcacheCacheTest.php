<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Suhock\DependencyInjection\Compiler\ResolutionPlan;
use Suhock\DependencyInjection\Compiler\ResolutionPlanEdge;
use Suhock\DependencyInjection\Compiler\ResolutionPlanKind;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\Resolver\ResolvableDependency;

use function chmod;
use function file_get_contents;
use function is_dir;
use function is_writable;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * Test suite for {@see OpcacheCache}: the file-per-id round trip, id sanitization, exportability guard, and the
 * directory guards in the constructor.
 */
final class OpcacheCacheTest extends TestCase
{
    private string $directory = '';

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/suhock-di-' . uniqid('', true);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            chmod($this->directory, 0755);
            self::removeDirectory($this->directory);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);

        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    public function testTryGet_WhenKeyAbsent_ReturnsFalse(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);

        // Act
        $result = $cache->tryGet('absent', $value);

        // Assert
        self::assertFalse($result);
    }

    public function testSetThenTryGet_ReturnsStoredValue(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);

        // Act
        $cache->set('key', 'value');

        // Assert
        self::assertTrue($cache->tryGet('key', $value));
        self::assertSame('value', $value);
    }

    public function testTryGet_WhenStoredValueIsNull_ReturnsTrue(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);
        $cache->set('key', null);

        // Act
        $result = $cache->tryGet('key', $value);

        // Assert
        self::assertTrue($result);
        self::assertNull($value);
    }

    public function testTryGet_WhenStoredValueIsFalse_ReturnsTrue(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);
        $cache->set('key', false);

        // Act
        $result = $cache->tryGet('key', $value);

        // Assert
        self::assertTrue($result);
        self::assertFalse($value);
    }

    public function testSet_OverwritesExistingValue(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);
        $cache->set('key', 'first');

        // Act
        $cache->set('key', 'second');

        // Assert
        self::assertTrue($cache->tryGet('key', $value));
        self::assertSame('second', $value);
    }

    public function testTryGet_WithSecondInstanceOnSameDirectory_ReadsWhatFirstInstanceWrote(): void
    {
        // Arrange
        $first = new OpcacheCache($this->directory);
        $first->set('key', 'value');

        // Act
        $second = new OpcacheCache($this->directory);
        $result = $second->tryGet('key', $value);

        // Assert
        self::assertTrue($result);
        self::assertSame('value', $value);
    }

    public function testSet_WithIdContainingNonSafeCharacters_WritesSanitizedFileName(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);

        // Act
        $cache->set('sdi:graph:abc', 'value');

        // Assert
        $file = $this->directory . '/sdi_graph_abc.php';
        self::assertFileExists($file);
        self::assertStringStartsWith('<?php', (string) file_get_contents($file));
    }

    public function testSetThenTryGet_WithResolutionPlanArray_RestoresAnEquivalentArray(): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);
        $plans = [
            'App\\Service' => new ResolutionPlan(
                FakeClassWithConstructor::class,
                ResolutionPlanKind::AutowiredClass,
                [
                    new ResolutionPlanEdge(
                        'obj',
                        new ResolvableDependency([[FakeClassNoConstructor::class]], FakeUnitEnum::Test),
                        soft: false,
                    ),
                    new ResolutionPlanEdge(
                        'other',
                        new ResolvableDependency([[FakeInterfaceOne::class, FakeClassNoConstructor::class]], 'key1'),
                        soft: true,
                        hasDefault: true,
                        lazy: true,
                    ),
                    new ResolutionPlanEdge('untyped', null, soft: true, declaredType: 'string'),
                ],
                [new ResolutionPlanEdge('self', null, soft: false, self: true)],
                declaredFactoryReturnType: FakeInterfaceOne::class,
            ),
        ];

        // Act
        $cache->set('sdi:graph:abc', $plans);

        // Assert
        self::assertTrue($cache->tryGet('sdi:graph:abc', $value));
        self::assertEquals($plans, $value);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unexportableValueProvider(): array
    {
        return [
            'object without __set_state' => [new stdClass()],
            'closure' => [static fn() => null],
        ];
    }

    #[DataProvider('unexportableValueProvider')]
    public function testSet_WhenValueIsNotExportable_ThrowsInvalidArgumentException(mixed $value): void
    {
        // Arrange
        $cache = new OpcacheCache($this->directory);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $cache->set('key', $value);
    }

    public function testConstruct_WhenDirectoryIsMissingNestedPath_CreatesTheDirectory(): void
    {
        // Arrange
        $nested = $this->directory . '/nested/path';

        // Act
        new OpcacheCache($nested);

        // Assert
        self::assertDirectoryExists($nested);
    }

    public function testConstruct_WhenDirectoryIsNotWritable_ThrowsRuntimeException(): void
    {
        // Arrange
        mkdir($this->directory, 0777, true);
        chmod($this->directory, 0555);

        if (is_writable($this->directory)) {
            self::markTestSkipped('The directory is still writable, likely because tests are running as root.');
        }

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        new OpcacheCache($this->directory);
    }
}
