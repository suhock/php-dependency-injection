<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use DateTime;
use Exception;
use RuntimeException;
use Throwable;

/**
 * Test suite for {@see NamespaceContainer}.
 */
class NamespaceContainerTest extends DependencyInjectionTestCase
{
    public function testGet_WithDefaultInjectorAndDefaultFactory_ReturnsInstance(): void
    {
        $container = new NamespaceContainer(__NAMESPACE__);

        self::assertInstanceOf(
            FakeClassNoConstructor::class,
            $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_WithExplicitInjectorAndExplicitFactory_UsesInjectorAndFactory(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))
            ->method('get')
            ->withConsecutive([Throwable::class], [RuntimeException::class])
            ->willReturnOnConsecutiveCalls(new Exception('test'), new RuntimeException('test'));
        $container->method('has')
            ->willReturn(true);

        $namespaceContainer = new NamespaceContainer(
            __NAMESPACE__,
            new ContainerInjector($container),
            fn (string $className, Throwable $throwable, RuntimeException $runtimeException) =>
                new FakeClassWithContexts($throwable, $runtimeException)
        );

        $result = $namespaceContainer->get(FakeClassWithContexts::class);

        self::assertInstanceOf(FakeClassWithContexts::class, $result);
        self::assertSame('test', $result->throwable->getMessage());
    }

    public function testGet_WithClassNotInNamespace_ThrowsClassNotFoundException(): void
    {
        $container = new NamespaceContainer(
            __NAMESPACE__,
            $this->createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertThrowsClassNotFoundException(
            DateTime::class,
            static fn () => $container->get(DateTime::class)
        );
    }

    public function testHas_WithClassInNamespace_ReturnsTrue(): void
    {
        $container = new NamespaceContainer(__NAMESPACE__);

        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }

    public function testHas_WithClassNotInNamespace_ReturnsFalse(): void
    {
        $container = new NamespaceContainer(__NAMESPACE__);

        self::assertFalse($container->has(DateTime::class));
    }

    public function testHas_WithRootNamespaceAndClassInRootNamespace_ReturnsTrue(): void
    {
        $container = new NamespaceContainer('');

        self::assertTrue($container->has(DateTime::class));
    }

    public function testHas_WithRootNamespaceAndClassInOtherNamespace_ReturnsTrue(): void
    {
        $container = new NamespaceContainer('');

        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }
}
