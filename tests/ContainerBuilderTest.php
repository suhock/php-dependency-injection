<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use LogicException;
use RuntimeException;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeCache;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithKeyedDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithStringDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithUnionDependency;
use Suhock\DependencyInjection\Fakes\FakeConfigurator;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\Fakes\FakeInvokableBaseClass;
use Suhock\DependencyInjection\Fakes\FakeInvokableFactory;
use Suhock\DependencyInjection\Fakes\FakeStaticFactory;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\DependencyGraphEdge;
use Suhock\DependencyInjection\Validation\ValidationIssue;
use Suhock\DependencyInjection\Validation\ValidationIssueKind;
use Throwable;

use function array_map;

/**
 * Test suite for {@see ContainerBuilder}: the add methods, the mutable configuration surface, duplicate detection,
 * pre-build removal, and the compile-validate-produce pipeline of {@see ContainerBuilder::build()}.
 */
final class ContainerBuilderTest extends AbstractDependencyInjectionTestCase
{
    public function testAddSingleton_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingleton_WithSelfParameterFactory_GetReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(
            FakeClassNoConstructor::class,
            function (FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddSingleton_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassExtendsBaseClass::class)
            ->addSingleton(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $sameInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingleton_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addSingleton(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddSingleton_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addSingleton(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddSingleton_WithStringNamingNeitherClassNorFunction_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore argument.type (the unresolvable source name is the case under test)
        $fn = static fn() => $builder->addSingleton(FakeBaseClass::class, 'Suhock\\NoSuchClass');

        // Assert
        // @phpstan-ignore argument.type (the unresolvable source name is the case under test)
        self::assertThrowsImplementationException(FakeBaseClass::class, 'Suhock\\NoSuchClass', $fn);
    }

    public function testAddSingleton_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(
            FakeBaseClass::class,
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $sameInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingleton_WhenFactoryReturnsNull_GetThrowsInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addSingleton(FakeClassNoConstructor::class, fn() => null)
            ->build();

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                null,
                $exception,
            ),
            $fn,
        );
    }

    public function testAddSingleton_WhenFactoryReturnTypeIsWrong_GetThrowsInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addSingleton(
            FakeClassNoConstructor::class,
            fn() => new LogicException(),
        )
            ->build();

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                LogicException::class,
                $exception,
            ),
            $fn,
        );
    }

    public function testAddSingleton_WithInstance_GetReturnsInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassNoConstructor::class, new FakeClassNoConstructor())
            ->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingleton_WhenInstanceIsWrongType_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.instanceType (the mismatched instance is the case under test)
        $fn = static fn() => $builder->addSingleton(
            FakeClassExtendsBaseClass::class,
            new FakeClassNoConstructor(),
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddSingleton_WithClosureAcceptingSupertypeOfClass_UsesClosureAsFactory(): void
    {
        // Arrange
        $expectedInstance = new FakeClassExtendsBaseClass();
        $container = self::createBuilder()->addSingleton(FakeBaseClass::class, new FakeClassExtendsBaseClass())
            ->addSingleton(
                FakeClassExtendsBaseClass::class,
                fn(FakeBaseClass $inner) => $expectedInstance,
            )
            ->build();

        // Act
        $result = $container->get(FakeClassExtendsBaseClass::class);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testAddSingleton_WithCallableString_UsesItAsFactory(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addSingleton(FakeBaseClass::class, FakeStaticFactory::class . '::create')
            ->build();

        // Act
        $result = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $result);
    }

    public function testAddSingleton_WithCallableArray_UsesItAsFactory(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addSingleton(FakeBaseClass::class, [new FakeStaticFactory(), 'make'])
            ->build();

        // Act
        $result = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $result);
    }

    public function testAddSingleton_WithInvokableObjectNotOfClass_UsesItAsFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeBaseClass::class, new FakeInvokableFactory())->build();

        // Act
        $result = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $result);
    }

    public function testAddSingleton_WithInvokableInstanceOfClass_UsesItAsTheInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeInvokableBaseClass();
        $container = self::createBuilder()->addSingleton(FakeBaseClass::class, $expectedInstance)->build();

        // Act
        $result = $container->get(FakeBaseClass::class);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testAddSingleton_WithFirstClassCallableOfInvokableInstance_UsesItAsFactory(): void
    {
        // Arrange
        $instance = new FakeInvokableBaseClass();
        $container = self::createBuilder()->addSingleton(FakeBaseClass::class, $instance(...))->build();

        // Act
        $result = $container->get(FakeBaseClass::class);

        // Assert
        self::assertNotSame($instance, $result);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $result);
    }

    public function testAddScoped_WithClassName_GetReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassNoConstructor::class)->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class);
        $sameScopeInstance = $scope->get(FakeClassNoConstructor::class);
        $otherScopeInstance = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameScopeInstance);
        self::assertNotSame($instance, $otherScopeInstance);
    }

    public function testAddScoped_WithSelfParameterFactory_GetReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(
            FakeClassNoConstructor::class,
            function (FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $result = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddScoped_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassExtendsBaseClass::class)
            ->addScoped(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($scope->get(FakeClassExtendsBaseClass::class), $instance);
    }

    public function testAddScoped_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addScoped(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddScoped_WithFactory_GetReturnsValueFromFactoryWithScopeDependencies(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassNoConstructor::class)
            ->addScoped(
                FakeClassWithConstructor::class,
                fn(FakeClassNoConstructor $obj) => new FakeClassWithConstructor($obj),
            )
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassWithConstructor::class);

        // Assert
        self::assertSame($scope->get(FakeClassNoConstructor::class), $instance->obj);
    }

    public function testAddTransient_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(FakeClassNoConstructor::class)->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $newInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransient_WithSelfParameterFactory_GetReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(
            FakeClassNoConstructor::class,
            function (FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddTransient_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(FakeClassExtendsBaseClass::class)
            ->addTransient(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $newInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransient_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addTransient(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddTransient_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addTransient(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddTransient_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(
            FakeBaseClass::class,
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $newInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransient_WhenFactoryReturnsNull_GetThrowsWrappedInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addTransient(FakeClassNoConstructor::class, fn() => null)
            ->build();

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                null,
                $exception,
            ),
            $fn,
        );
    }

    public function testAddTransient_WhenFactoryReturnTypeIsWrong_GetThrowsWrappedInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addTransient(
            FakeClassNoConstructor::class,
            fn() => new LogicException(),
        )
            ->build();

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                LogicException::class,
                $exception,
            ),
            $fn,
        );
    }

    public function testAddKeyedSingleton_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $sameInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassExtendsBaseClass::class)
            ->addKeyedSingleton(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $sameInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingleton(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $sameInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithInstance_GetReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
            ->build();

        // Act
        $result = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testAddKeyedSingleton_WithSelfParameterFactory_GetByKeyReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingleton(
            FakeClassNoConstructor::class,
            'key1',
            function (#[Key('key1')] FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $sameInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WhenInstanceIsWrongType_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.instanceType (the mismatched instance is the case under test)
        $fn = static fn() => $builder->addKeyedSingleton(
            FakeClassExtendsBaseClass::class,
            'key1',
            new FakeClassNoConstructor(),
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddKeyedScoped_WithValidClass_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedScoped(FakeClassNoConstructor::class, 'key1')->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($instance, $scope->get(FakeClassNoConstructor::class, 'key1'));
        self::assertFalse($scope->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyedScoped_WithSelfParameterFactory_GetByKeyReturnsConfiguredPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedScoped(
            FakeClassNoConstructor::class,
            'key1',
            function (#[Key('key1')] FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertSame($instance, $scope->get(FakeClassNoConstructor::class, 'key1'));
    }

    public function testAddKeyedScoped_WithImplementation_GetByKeyReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassExtendsBaseClass::class)
            ->addKeyedScoped(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($scope->get(FakeClassExtendsBaseClass::class), $instance);
    }

    public function testAddKeyedScoped_WithFactory_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedScoped(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class, 'key1');
        $otherScopeInstance = $container->createScope()->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $scope->get(FakeBaseClass::class, 'key1'));
        self::assertNotSame($instance, $otherScopeInstance);
    }

    public function testAddKeyedTransient_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedTransient(FakeClassNoConstructor::class, 'key1')->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $newInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransient_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(FakeClassExtendsBaseClass::class)
            ->addKeyedTransient(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransient_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedTransient(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransient_WithSelfParameterFactory_GetByKeyReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedTransient(
            FakeClassNoConstructor::class,
            'key1',
            function (#[Key('key1')] FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $newInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAdd_WithDuplicateClass_ThrowsContainerException(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act & Assert
        $this->expectException(ContainerException::class);
        $builder->addSingleton(FakeClassNoConstructor::class);
    }

    public function testAddKeyed_WithValidClass_ProductHasKeyedService(): void
    {
        // Arrange & Act
        $container = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyed_WithDuplicateKey_ThrowsContainerException(): void
    {
        // Arrange
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act & Assert
        $this->expectException(ContainerException::class);
        $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');
    }

    public function testAddKeyed_WhenUnkeyedServiceExists_AddsIndependentKeyedDescriptor(): void
    {
        // Arrange & Act
        $container = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertNotSame(
            $container->get(FakeClassNoConstructor::class),
            $container->get(FakeClassNoConstructor::class, 'key1'),
        );
    }

    public function testConfigure_WithCallback_InvokesCallbackWithSelf(): void
    {
        // Arrange
        $builder = self::createBuilder();
        $configurator = $this->createMock(FakeConfigurator::class);
        $configurator->expects($this->once())
            ->method('configure')
            ->with($builder);

        // Act
        $builder->configure($configurator->configure(...));
    }

    public function testRemove_BeforeBuild_ProductLacksService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testRemove_WithKey_RemovesOnlyKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key2');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, 'key1')->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key2'));
    }

    public function testRemove_WithoutKey_DoesNotRemoveKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testRemove_WithEnumKey_RemovesKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test);

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, FakeUnitEnum::Test)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, FakeUnitEnum::Test));
    }

    public function testRemove_ThenReAddUnderSameKey_ProductResolvesTheReplacement(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
            ->build();

        // Assert
        self::assertSame($expectedInstance, $container->get(FakeClassNoConstructor::class, 'key1'));
    }

    public function testBuild_WithDefectiveConfiguration_ThrowsAggregatedValidationException(): void
    {
        // Arrange: two independent defects, a missing required dependency and an unresolvable builtin parameter.
        $builder = self::createBuilder()->addTransient(FakeClassWithDependencies::class)
            ->addTransient(FakeClassWithStringDependency::class);

        // Act
        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException $exception) {
            // Assert
            $kinds = array_map(
                static fn(ValidationIssue $issue) => $issue->kind,
                $exception->getIssues(),
            );
            self::assertContains(ValidationIssueKind::MissingDependency, $kinds);
            self::assertContains(ValidationIssueKind::UnresolvableParameter, $kinds);
        }
    }

    public function testBuild_AfterFixingAFailedBuild_Succeeds(): void
    {
        // Arrange: FakeClassWithDependencies requires Throwable and RuntimeException.
        $builder = self::createBuilder()->addTransient(FakeClassWithDependencies::class);

        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException) {
            // The builder must remain fully usable after the failed build.
        }

        // Act
        $container = $builder->addSingleton(Throwable::class, static fn(): RuntimeException => new RuntimeException())
            ->addSingleton(RuntimeException::class, static fn(): RuntimeException => new RuntimeException())
            ->build();

        // Assert
        self::assertInstanceOf(
            FakeClassWithDependencies::class,
            $container->get(FakeClassWithDependencies::class),
        );
    }

    public function testBuild_CalledTwice_ProducesIndependentProducts(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $first = $builder->build();
        $second = $builder->build();

        // Assert: each product caches its own singleton.
        self::assertNotSame($first, $second);
        self::assertNotSame(
            $first->get(FakeClassNoConstructor::class),
            $second->get(FakeClassNoConstructor::class),
        );
    }

    public function testBuild_AfterBuild_AddedServicesDoNotAffectEarlierProducts(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $first = $builder->build();
        $second = $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')->build();

        // Assert
        self::assertFalse($first->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($second->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testBuild_WithCache_StoresTheCompiledGraphUnderTheConfigurationFingerprint(): void
    {
        // Arrange
        $cache = new FakeCache();

        // Act
        ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertCount(1, $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testBuild_WithIdenticalConfigurationAndWarmCache_ReusesTheStoredGraph(): void
    {
        // Arrange: two builders, same configuration shape, same cache.
        $cache = new FakeCache();
        ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)->build();
        $storesAfterFirstBuild = count($cache->storedIds);

        // Act
        $container = ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)
            ->build();

        // Assert: the second build stored nothing new and its product still resolves.
        self::assertCount($storesAfterFirstBuild, $cache->storedIds);
        self::assertInstanceOf(FakeClassNoConstructor::class, $container->get(FakeClassNoConstructor::class));
    }

    public function testBuild_WithChangedConfiguration_StoresASecondGraph(): void
    {
        // Arrange
        $cache = new FakeCache();
        ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)->build();

        // Act
        ContainerBuilder::createDefault($cache)->addTransient(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertCount(2, $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testBuild_WithDefectiveConfigurationAndCache_StoresNoGraph(): void
    {
        // Arrange
        $cache = new FakeCache();
        $builder = ContainerBuilder::createDefault($cache)->addTransient(FakeClassWithStringDependency::class);

        // Act
        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException) {
        }

        // Assert
        self::assertSame([], $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testBuild_WithUnfingerprintableConfiguration_BuildsWithoutStoringAGraph(): void
    {
        // Arrange: a factory from an internal function has no definition site to fingerprint. Its parameter is
        // optional and its return is unchecked, so the configuration still validates.
        $cache = new FakeCache();

        // Act
        // @phpstan-ignore suhock.factoryReturnType (an internal function has no definition site to fingerprint)
        $container = ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class, phpversion(...))
            ->build();

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertSame([], $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testExportDependencyGraph_WithLinearChain_ExportsTheEdge(): void
    {
        // Arrange: FakeClassWithConstructor requires FakeClassNoConstructor via parameter $obj.
        $builder = self::createBuilder()->addSingleton(FakeClassWithConstructor::class)
            ->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeClassWithConstructor::class, $edge?->sourceId);
        self::assertSame(FakeClassNoConstructor::class, $edge->targetId);
        self::assertTrue($edge->required);
        self::assertSame('parameter $obj of __construct()', $edge->injectionPoint);
    }

    public function testExportDependencyGraph_ServiceIds_IncludeUserServicesAndAutoBindings(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertContains(FakeClassNoConstructor::class, $graph->serviceIds);
        self::assertContains(ContainerInterface::class, $graph->serviceIds);
        self::assertContains(ScopeFactoryInterface::class, $graph->serviceIds);
    }

    public function testExportDependencyGraph_WithKeyedDependency_RendersTheKeyedTargetId(): void
    {
        // Arrange: FakeClassWithKeyedDependency injects FakeClassNoConstructor under 'key1'.
        $builder = self::createBuilder()->addTransient(FakeClassWithKeyedDependency::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        self::assertSame(FakeClassNoConstructor::class . '#key1', ($graph->edges[0] ?? null)?->targetId);
        self::assertContains(FakeClassNoConstructor::class . '#key1', $graph->serviceIds);
    }

    public function testExportDependencyGraph_WithImplementation_ExportsTheImplementationEdge(): void
    {
        // Arrange
        $builder = self::createBuilder()->addTransient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class)
            ->addTransient(FakeClassImplementsInterfaces::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeInterfaceOne::class, $edge?->sourceId);
        self::assertSame(FakeClassImplementsInterfaces::class, $edge->targetId);
        self::assertSame('the implementation class', $edge->injectionPoint);
    }

    public function testExportDependencyGraph_WithSatisfiedSoftDependency_ExportsANonRequiredEdge(): void
    {
        // Arrange: the factory's nullable parameter is soft, but its dependency is added, so the edge exists.
        $builder = self::createBuilder()->addSingleton(
            FakeClassWithConstructor::class,
            static fn(?FakeClassNoConstructor $obj): FakeClassWithConstructor
                    => new FakeClassWithConstructor($obj ?? new FakeClassNoConstructor()),
        )
            ->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        self::assertFalse(($graph->edges[0] ?? null)?->required);
    }

    public function testExportDependencyGraph_WithUnionDependency_ExportsOnlyTheChosenEdge(): void
    {
        // Arrange: the union FakeInterfaceOne|FakeInterfaceTwo always chooses its first resolvable member.
        $builder = self::createBuilder()->addTransient(FakeClassWithUnionDependency::class)
            ->addTransient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class)
            ->addTransient(FakeInterfaceTwo::class, FakeClassImplementsInterfaces::class)
            ->addTransient(FakeClassImplementsInterfaces::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        $unionTargets = [];

        foreach ($graph->edges as $edge) {
            if ($edge->sourceId === FakeClassWithUnionDependency::class) {
                $unionTargets[] = $edge->targetId;
            }
        }

        // Assert
        self::assertSame([FakeInterfaceOne::class], $unionTargets);
    }

    public function testExportDependencyGraph_WithDefectiveConfiguration_StillExportsWithoutTheBrokenEdge(): void
    {
        // Arrange: FakeClassWithDependencies is missing its required dependencies, so build() would throw.
        $builder = self::createBuilder()->addTransient(FakeClassWithDependencies::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertContains(FakeClassWithDependencies::class, $graph->serviceIds);
        self::assertSame([], $graph->edges);
    }

    public function testExportDependencyGraph_RootsAreDerivable(): void
    {
        // Arrange: the roots (services nothing injects) are the ids that appear as no edge's target.
        $builder = self::createBuilder()->addSingleton(FakeClassWithConstructor::class)
            ->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();
        $targets = array_map(static fn(DependencyGraphEdge $edge) => $edge->targetId, $graph->edges);
        $roots = array_values(array_diff($graph->serviceIds, $targets));

        // Assert: the auto-bindings surface as roots too; the user's root is the chain head.
        self::assertContains(FakeClassWithConstructor::class, $roots);
        self::assertNotContains(FakeClassNoConstructor::class, $roots);
    }
}
