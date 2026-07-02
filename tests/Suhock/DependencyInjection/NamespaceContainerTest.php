<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
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
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $className) => match ($className) {
                Throwable::class => new Exception('test1'),
                RuntimeException::class => new RuntimeException('test2'),
                default => self::fail("Unexpected request for $className")
            });
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
        self::assertSame('test1', $result->throwable->getMessage());
        self::assertSame('test2', $result->runtimeException->getMessage());
    }

    public function testGet_WithClassNotInNamespace_ThrowsClassNotFoundException(): void
    {
        $container = new NamespaceContainer(
            __NAMESPACE__,
            $this->createStub(InjectorInterface::class),
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
