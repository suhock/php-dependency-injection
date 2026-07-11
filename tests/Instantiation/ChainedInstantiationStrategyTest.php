<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Instantiation;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ChainedInstantiationStrategy}.
 */
final class ChainedInstantiationStrategyTest extends AbstractDependencyInjectionTestCase
{
    public function testTryInstantiate_ReturnsFirstNonNullResultAndSkipsLaterStrategies(): void
    {
        // Arrange
        $producing = new class () implements InstantiationStrategyInterface {
            public function tryInstantiate(string $className, array $params): object
            {
                return new $className();
            }
        };
        $later = new class () implements InstantiationStrategyInterface {
            public bool $consulted = false;

            public function tryInstantiate(string $className, array $params): ?object
            {
                $this->consulted = true;

                return null;
            }
        };
        $chain = new ChainedInstantiationStrategy([$producing, $later]);

        // Act
        $result = $chain->tryInstantiate(FakeClassNoConstructor::class, []);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $result);
        self::assertFalse($later->consulted);
    }

    public function testTryInstantiate_ReturnsNullWhenAllStrategiesDecline(): void
    {
        // Arrange
        $declining = new class () implements InstantiationStrategyInterface {
            public function tryInstantiate(string $className, array $params): ?object
            {
                return null;
            }
        };
        $chain = new ChainedInstantiationStrategy([$declining, $declining]);

        // Act & Assert
        self::assertNull($chain->tryInstantiate(FakeClassNoConstructor::class, []));
    }
}
