<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\Resolver\ResolvableDependency;

use function var_export;

/**
 * Test suite for the <code>var_export</code> round trip a PHP-file cache relies on to restore a
 * {@see ResolutionPlan} and the value objects it is made of.
 */
final class ResolutionPlanVarExportTest extends TestCase
{
    public function testSetState_WithExportedPlan_RestoresAnEquivalentPlan(): void
    {
        // Arrange
        $plan = new ResolutionPlan(
            FakeClassWithConstructor::class,
            ResolutionPlanKind::AutowiredClass,
            [
                new ResolutionPlanEdge(
                    'obj',
                    new ResolvableDependency([[FakeClassNoConstructor::class]], FakeUnitEnum::Test),
                    soft: false,
                ),
                new ResolutionPlanEdge(
                    'other',
                    new ResolvableDependency([[FakeInterfaceOne::class, FakeClassNoConstructor::class]], 'key1'),
                    soft: true,
                    hasDefault: true,
                    lazy: true,
                ),
                new ResolutionPlanEdge('untyped', null, soft: true, declaredType: 'string'),
            ],
            [new ResolutionPlanEdge('self', null, soft: false, self: true)],
            declaredFactoryReturnType: FakeInterfaceOne::class,
        );

        // Act
        // @phpstan-ignore ergebnis.noEval (restoring var_export output is what a PHP-file cache does)
        $restored = eval('return ' . var_export($plan, true) . ';');

        // Assert
        self::assertEquals($plan, $restored);
    }
}
