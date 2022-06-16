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
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturn($container);
        $container->method('has')->willReturn(true);

        return $container;
    }

    public function testBuild(): void
    {
        $container = $this->createContainer();
        $builder = self::createMock(FakeBuilder::class);
        $builder->expects(self::once())
            ->method('build')
            ->with($container);

        /** @psalm-var FakeBuilder $builder Psalm gets confused by the union with the MockObject type here */
        $container->build($builder->build(...));
    }

    public function testRemove(): void
    {
        self::assertFalse(
            $this->createContainer()
                ->addSingletonClass(FakeClassNoConstructor::class)
                ->remove(FakeClassNoConstructor::class)
                ->has(FakeClassNoConstructor::class)
        );
    }

    public function testTryGet_FactoryPrioritizedOverContainer(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeClassNoConstructor::class,
            'Namespace mismatch. Test would be invalid.'
        );

        $goodInstance = new FakeClassExtendsNoConstructor();

        self::assertSame(
            $goodInstance,
            $this->createContainer()
                ->addSingletonContainer($this->getNestedContainer())
                ->addSingletonFactory(
                    FakeClassExtendsNoConstructor::class,
                    fn () => $goodInstance
                )
                ->get(FakeClassExtendsNoConstructor::class)
        );
    }

    public function testTryGet_FirstContainerPrioritized(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeClassNoConstructor::class,
            'Namespace mismatch. Test would be invalid.'
        );

        $goodInstance = new FakeClassExtendsNoConstructor();

        self::assertSame(
            $goodInstance,
            $this->createContainer()
                ->addSingletonNamespace(
                    __NAMESPACE__,
                    fn (string $className) => $goodInstance
                )
                ->addSingletonContainer($this->getNestedContainer())
                ->get(FakeClassExtendsNoConstructor::class)
        );
    }

    public function testGet_Exception_UnresolvedClass(): void
    {
        $container = $this->createContainer();

        self::assertUnresolvedClassException(
            FakeClassNoConstructor::class,
            fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_Exception_CircularDependency(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn (FakeClassNoConstructor $obj) => $obj);

        self::assertCircularDependencyException(
            FakeClassNoConstructor::class,
            '',
            fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testHas_ReturnsFalseWhenMissing(): void
    {
        self::assertFalse($this->createContainer()->has(FakeClassNoConstructor::class));
    }

    public function testHas_FromInstance(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
                ->has(FakeClassNoConstructor::class)
        );
    }

    public function testHas_FromFactory(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonFactory(FakeClassNoConstructor::class, fn () => new FakeClassNoConstructor())
                ->has(FakeClassNoConstructor::class)
        );
    }

    public function testHas_FromSingletonContainer(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonContainer($this->getNestedContainer())
                ->has(FakeClassExtendsNoConstructor::class)
        );
    }

    public function testHas_FromTransientContainer(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addTransientContainer($this->getNestedContainer())
                ->has(FakeClassExtendsNoConstructor::class)
        );
    }

    public function testHas_FromNamespace(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonNamespace(__NAMESPACE__)
                ->has(FakeClassNoConstructor::class)
        );
    }

    public function testHas_FromRootNamespace(): void
    {
        self::assertTrue(
            $this->createContainer()
                ->addSingletonNamespace('')
                ->has(FakeClassNoConstructor::class)
        );
    }
}
