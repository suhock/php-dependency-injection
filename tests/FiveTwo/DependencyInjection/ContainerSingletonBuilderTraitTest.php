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

use DateTime;
use FiveTwo\DependencyInjection\Provision\InstanceTypeException;
use LogicException;

/**
 * Test suite for {@see ContainerSingletonBuilderTrait}.
 */
class ContainerSingletonBuilderTraitTest extends DependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return new Container();
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param Container $container
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|null $implementationClassName
     *
     * @return void
     */
    private function assertSingleton(
        Container $container,
        string $className,
        ?string $implementationClassName = null
    ): void {
        $implementationClassName ??= $className;

        $expectedInstance = $container->get($className);
        self::assertInstanceOf($implementationClassName, $expectedInstance);
        self::assertSame($expectedInstance, $container->get($className));
    }

    public function testAddSingletonClass_WithValidClassName_GetReturnsInstanceOfClass(): void
    {
        $container = $this->createContainer()->addSingletonClass(FakeClassNoConstructor::class);

        $this->assertSingleton($container, FakeClassNoConstructor::class);
    }

    public function testAddSingletonClass_WithMutator_GetReturnsMutatedInstance(): void
    {
        $container = $this->createContainer()
            ->addSingletonClass(
                FakeClassNoConstructor::class,
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                }
            );

        self::assertSame('test', $container->get(FakeClassNoConstructor::class)->string);
    }

    public function testAddSingletonImplementation_WithSubclass_GetReturnsInstanceOfSubclass(): void
    {
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassExtendsNoConstructor::class)
            ->addSingletonImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class);

        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonImplementation_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        $container = $this->createContainer();

        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => $container->addSingletonImplementation(
                FakeClassNoConstructor::class,
                FakeClassNoConstructor::class
            )
        );
    }

    public function testAddSingletonImplementation_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        $container = $this->createContainer();

        self::assertThrowsImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => $container->addSingletonImplementation(
                FakeClassExtendsNoConstructor::class,
                FakeClassNoConstructor::class
            )
        );
    }

    public function testAddSingletonFactory_WithFactory_GetReturnsValueFromFactory(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(
                FakeClassNoConstructor::class,
                fn () => new FakeClassExtendsNoConstructor()
            );

        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonFactory_WhenFactoryReturnsNull_GetThrowsInstanceTypeException(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn () => null);

        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            /** @param InstanceTypeException<FakeClassNoConstructor> $exception */
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                null,
                $exception
            ),
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonFactory_WhenReturnTypeIsWrong_GetThrowsInstanceTypeException(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(
                FakeClassNoConstructor::class,
                fn () => new LogicException()
            );

        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            /** @param InstanceTypeException<FakeClassNoConstructor> $exception */
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                LogicException::class,
                $exception
            ),
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonInstance_WithValidInstance_GetReturnsInstance(): void
    {
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddSingletonInstance_WhenInstanceIsWrongType_ThrowsInstanceTypeException(): void
    {
        $container = $this->createContainer();

        self::assertThrowsInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => $container->addSingletonInstance(
                FakeClassExtendsNoConstructor::class,
                new FakeClassNoConstructor()
            )
        );
    }

    public function testAddSingletonContainer_WithContainer_GetReturnsValueFromContainer(): void
    {
        $container = $this->createContainer()
            ->addSingletonContainer(
                new FakeContainer([FakeClassNoConstructor::class => fn () => new FakeClassNoConstructor()])
            );

        $this->assertSingleton($container, FakeClassNoConstructor::class);
    }

    public function testAddSingletonContainer_WhenClassNotInContainer_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()
            ->addSingletonContainer(
                new FakeContainer([
                    FakeClassExtendsNoConstructor::class => fn () => new FakeClassExtendsNoConstructor()
                ])
            );

        self::assertThrowsClassNotFoundException(
            FakeClassNoConstructor::class,
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonNamespace_WithValidNamespace_GetReturnsInstance(): void
    {
        $container = $this->createContainer()->addSingletonNamespace(__NAMESPACE__);

        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddSingletonNamespace_WithClassNotInNamespace_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()->addSingletonNamespace(__NAMESPACE__);

        self::assertThrowsClassNotFoundException(
            DateTime::class,
            static fn () => $container->get(DateTime::class)
        );
    }

    public function testAddSingletonInterface_WithValidImplementation_GetReturnsInstance(): void
    {
        $container = $this->createContainer()->addSingletonInterface(FakeClassNoConstructor::class);

        $this->assertSingleton(
            $container,
            FakeClassExtendsNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonInterface_WhenImplementationNotSubclass_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()->addSingletonInterface(FakeClassNoConstructor::class);

        self::assertThrowsClassNotFoundException(DateTime::class, static fn () => $container->get(DateTime::class));
    }

    public function testAddSingletonAttribute_WhenClassHasAttribute_GetReturnsInstance(): void
    {
        $container = $this->createContainer()->addSingletonAttribute(FakeAttribute::class);

        $this->assertSingleton($container, FakeClassWithAttribute::class);
    }

    public function testAddSingletonAttribute_WhenClassDoesNotHaveAttribute_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()->addSingletonAttribute(FakeAttribute::class);

        self::assertThrowsClassNotFoundException(DateTime::class, static fn () => $container->get(DateTime::class));
    }
}
