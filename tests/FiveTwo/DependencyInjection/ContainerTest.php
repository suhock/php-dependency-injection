<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Lifetime\SingletonStrategy;
use FiveTwo\DependencyInjection\Provision\ObjectInstanceProvider;

/**
 * Test suite for {@see Container}.
 */
class ContainerTest extends DependencyInjectionTestCase
{
    protected function createContainer(): Container
    {
        return new Container();
    }

    private function getNestedContainer(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($container);
        $container->method('has')->willReturn(true);

        return $container;
    }

    public function testAdd_WithValidClass_AddsDescriptor(): void
    {
        $container = $this->createContainer();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        /** @phpstan-ignore-next-line PHPStan fails to resolve correct types */
        $container->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider);
        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }

    public function testAdd_WithDuplicateClass_ThrowsContainerException(): void
    {
        $container = $this->createContainer();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        /** @phpstan-ignore-next-line PHPStan fails to resolve correct types */
        $container->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider);

        $this->expectException(ContainerException::class);
        /** @phpstan-ignore-next-line PHPStan fails to resolve correct types */
        $container->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider);
    }

    public function testBuild_WithCallback_InvokesCallbackWithSelf(): void
    {
        $container = $this->createContainer();
        $builder = $this->createMock(FakeBuilder::class);
        $builder->expects(self::once())
            ->method('build')
            ->with($container);

        /** @psalm-var FakeBuilder $builder Psalm gets confused by the union with the MockObject type here */
        $container->build($builder->build(...));
    }

    public function testRemove_WithExistingClassName_RemovesClassFromContainer(): void
    {
        self::assertFalse(
            $this->createContainer()
                ->addSingletonClass(FakeClassNoConstructor::class)
                ->remove(FakeClassNoConstructor::class)
                ->has(FakeClassNoConstructor::class)
        );
    }

    public function testTryGet_WithValueInFactoryAndContainer_ReturnsValueFromFactory(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeClassNoConstructor::class,
            'Namespace mismatch. Test would be invalid.'
        );

        $expectedInstance = new FakeClassExtendsNoConstructor();

        $container = $this->createContainer()
            ->addSingletonContainer($this->getNestedContainer())
            ->addSingletonFactory(
                FakeClassExtendsNoConstructor::class,
                fn () => $expectedInstance
            );

        self::assertSame($expectedInstance, $container->get(FakeClassExtendsNoConstructor::class));
    }

    public function testTryGet_WithValueInMultipleContainers_ReturnsValueFromFirstContainerAdded(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeClassNoConstructor::class,
            'Namespace mismatch. Test would be invalid.'
        );

        $expectedInstance = new FakeClassExtendsNoConstructor();

        $container = $this->createContainer()
            ->addSingletonNamespace(__NAMESPACE__, fn (string $className) => $expectedInstance)
            ->addSingletonContainer($this->getNestedContainer());

        self::assertSame($expectedInstance, $container->get(FakeClassExtendsNoConstructor::class));
    }

    public function testGet_WhenClassNotInContainer_ThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer();

        self::assertThrowsClassNotFoundException(
            FakeClassNoConstructor::class,
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_WhenClassHasCircularDependency_ThrowsWrappedCircularDependencyException(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn (FakeClassNoConstructor $obj) => $obj);

        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            /** @param CircularDependencyException<FakeClassNoConstructor> $exception */
            static fn (CircularDependencyException $exception) => self::assertCircularDependencyException(
                FakeClassNoConstructor::class,
                $exception
            ),
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testHas_WhenClassNotInContainer_ReturnsFalse(): void
    {
        self::assertFalse($this->createContainer()->has(FakeClassNoConstructor::class));
    }

    public function testHas_WhenValueProvidedByInstance_ReturnsTrue(): void
    {
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }

    public function testHas_WhenValueProvidedByFactory_ReturnsTrue(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn () => new FakeClassNoConstructor());

        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }

    public function testHas_WhenValueIsInNestedSingletonContainer_ReturnsTrue(): void
    {
        $container = $this->createContainer()->addSingletonContainer($this->getNestedContainer());

        self::assertTrue($container->has(FakeClassExtendsNoConstructor::class));
    }

    public function testHas_WhenValueIsInNestedTransientContainer_ReturnsTrue(): void
    {
        $container = $this->createContainer()->addTransientContainer($this->getNestedContainer());

        self::assertTrue($container->has(FakeClassExtendsNoConstructor::class));
    }

    public function testHas_WhenValueIsInNamespaceContainer_ReturnsTrue(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonNamespace(__NAMESPACE__)
                ->has(FakeClassNoConstructor::class)
        );
    }

    public function testHas_WhenValueIsInRootNamespaceContainer_ReturnsTrue(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonNamespace('')
                ->has(FakeClassNoConstructor::class)
        );
    }
}
