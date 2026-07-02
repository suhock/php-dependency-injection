<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeAttribute;
use Suhock\DependencyInjection\Fakes\FakeClassWithAttribute;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;

/**
 * Test suite for {@see AttributeContainer}.
 */
class AttributeContainerTest extends DependencyInjectionTestCase
{
    public function testGet_WithDefaultInjectorDefaultFactory_ReturnsAutowiredInstance(): void
    {
        // Arrange
        $container = new AttributeContainer(FakeAttribute::class);

        // Act
        $instance = $container->get(FakeClassWithAttribute::class);

        // Assert
        self::assertInstanceOf(FakeClassWithAttribute::class, $instance);
    }

    public function testGet_WithExplicitInjectorExplicitFactory_ReturnsInstanceFromFactory(): void
    {
        // Arrange
        $container = new AttributeContainer(
            FakeAttribute::class,
            factory: fn (string $className, FakeAttribute $attr) => new FakeClassWithAttribute($attr->value)
        );

        // Act
        $result = $container->get(FakeClassWithAttribute::class);

        // Assert
        self::assertInstanceOf(FakeClassWithAttribute::class, $result);
        self::assertSame('test', $result->value);
    }

    public function testGet_WhenClassDoesNotHaveAttribute_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = new AttributeContainer(FakeAttribute::class);

        // Act
        $fn = static fn () => $container->get(FakeClassWithDependencies::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassWithDependencies::class, $fn);
    }

    public function testGet_WhenClassDoesNotExist_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = new AttributeContainer(FakeAttribute::class);

        // Act
        /** @phpstan-ignore-next-line */
        $fn = static fn () => $container->get('NonExistentClass');

        // Assert
        self::assertThrowsClassNotFoundException(
            /** @phpstan-ignore-next-line */
            'NonExistentClass',
            $fn
        );
    }

    public function testHas_WhenClassHasAttribute_ReturnsTrue(): void
    {
        // Arrange
        $container = new AttributeContainer(FakeAttribute::class);

        // Act
        $result = $container->has(FakeClassWithAttribute::class);

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WhenClassDoesNotHaveAttribute_ReturnsFalse(): void
    {
        // Arrange
        $container = new AttributeContainer(FakeAttribute::class);

        // Act
        $result = $container->has(FakeClassWithDependencies::class);

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WhenClassDoesNotExist_ReturnsFalse(): void
    {
        // Arrange
        $container = new AttributeContainer(FakeAttribute::class);

        // Act
        /**
         * @phpstan-ignore-next-line
         */
        $result = $container->has('NonExistentClass');

        // Assert
        self::assertFalse($result);
    }
}
