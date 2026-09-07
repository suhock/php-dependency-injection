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
use Suhock\DependencyInjection\Compiler\ContainerCompilerInterface;
use Suhock\DependencyInjection\Compiler\DependencyGraph;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeConfigurator;
use Suhock\DependencyInjection\Fakes\FakeInvokableBaseClass;
use Suhock\DependencyInjection\Fakes\FakeInvokableFactory;
use Suhock\DependencyInjection\Fakes\FakeStaticFactory;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Throwable;

use function array_keys;

/**
 * Test suite for {@see ContainerBuilder}: the add methods, the mutable configuration surface, duplicate detection,
 * pre-build removal, and the configuration it hands to its compiler. The compile pipeline itself is covered by
 * {@see Compiler\ContainerCompilerTest}.
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

    public function testBuild_WithConfiguredServices_PassesTheDescriptorsToTheCompiler(): void
    {
        // Arrange
        $expectedContainer = self::createBuilder()->build();
        $compiler = $this->createMock(ContainerCompilerInterface::class);
        $compiler->expects($this->once())
            ->method('compile')
            ->with(self::callback(static fn(array $descriptors): bool => array_keys($descriptors) === [
                FakeClassNoConstructor::class,
                DescriptorId::compute(FakeClassNoConstructor::class, 'key1'),
            ]))
            ->willReturn($expectedContainer);
        $builder = new ContainerBuilder($compiler);

        // Act
        $container = $builder->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertSame($expectedContainer, $container);
    }

    public function testExportDependencyGraph_WithConfiguredServices_PassesTheDescriptorsToTheCompiler(): void
    {
        // Arrange
        $expectedGraph = new DependencyGraph([], []);
        $compiler = $this->createMock(ContainerCompilerInterface::class);
        $compiler->expects($this->once())
            ->method('exportGraph')
            ->with(self::callback(
                static fn(array $descriptors): bool
                    => array_keys($descriptors) === [FakeClassNoConstructor::class],
            ))
            ->willReturn($expectedGraph);
        $builder = new ContainerBuilder($compiler);

        // Act
        $graph = $builder->addSingleton(FakeClassNoConstructor::class)->exportDependencyGraph();

        // Assert
        self::assertSame($expectedGraph, $graph);
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
}
