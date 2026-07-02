<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\ContainerException;
use Suhock\DependencyInjection\DependencyInjectionTestCase;
use Suhock\DependencyInjection\FakeClassNoConstructor;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ContextContainer}.
 */
class ContextContainerTest extends DependencyInjectionTestCase
{
    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorInterface $injector) => new Container($injector));
    }

    public function testGet_ValueInOnePushedContext_ReturnsValueFromContext(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->context('default')
            ->addSingletonInstance(
                FakeClassNoConstructor::class,
                $instance = new FakeClassNoConstructor()
            );
        $container->push('default');

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($instance, $result);
    }

    public function testGet_ValueInTwoPushedContexts_ReturnsValueFromTopOfStack(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeClassNoConstructor::class,
            new FakeClassNoConstructor()
        );
        $container->context('new')->addSingletonInstance(
            FakeClassNoConstructor::class,
            $contextInstance = new FakeClassNoConstructor()
        );

        // Act
        $result = $container
            ->push('default')
            ->push('new')
            ->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($contextInstance, $result);
    }

    public function testGet_ValueAtBottomOfStackOnly_ReturnsValueFromBottomOfStack(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->context('default')
            ->addSingletonInstance(
                FakeClassNoConstructor::class,
                $defaultInstance = new FakeClassNoConstructor()
            );
        $container->context('new');

        // Act
        $result = $container
            ->push('default')
            ->push('new')
            ->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($defaultInstance, $result);
    }

    public function testGet_EmptyStack_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeClassNoConstructor::class,
            new FakeClassNoConstructor()
        );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testContext_RepeatedCallsForSameName_ReturnsSameInstance(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $firstResult = $container->context('default');
        $secondResult = $container->context('default');

        // Assert
        self::assertSame($firstResult, $secondResult);
    }

    public function testContext_CallsForDifferentNames_ReturnDistinctInstances(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $defaultResult = $container->context('default');
        $newResult = $container->context('new');

        // Assert
        self::assertNotSame($defaultResult, $newResult);
    }

    public function testPush_OnEmptyStack_PushesCorrectValue(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $container->push('test1');

        // Assert
        self::assertEquals('test1', $container->pop());
    }

    public function testPush_OnNonEmptyStack_PushesCorrectValue(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->push('test1');

        // Act
        $container->push('test2');

        // Assert
        self::assertEquals('test2', $container->pop());
    }

    public function testPop_WithOneItem_ResultsInEmptyStack(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->push('test1');

        // Act
        $container->pop();

        // Assert
        self::assertSame(0, $container->getStackHeight());
    }

    public function testPop_WithTwoItems_ResultsInStackWithOneItem(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->push('test1');
        $container->push('test2');

        // Act
        $container->pop();

        // Assert
        self::assertSame(1, $container->getStackHeight());
    }

    public function testPop_WithTwoItems_BottomItemLeftInStack(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->push('test1');
        $container->push('test2');
        $container->pop();

        // Act
        $result = $container->pop();

        // Assert
        self::assertSame('test1', $result);
    }

    public function testPop_WithEmptyStack_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act & Assert
        $this->expectException(ContainerException::class);
        $container->pop();
    }

    public function testResetStack_WithStack_ResultsInEmptyStack(): void
    {
        // Arrange
        $container = $this->createContainer();
        $container->push('test1');

        // Act
        $container->resetStack();

        // Assert
        self::assertSame(0, $container->getStackHeight());
    }
}
