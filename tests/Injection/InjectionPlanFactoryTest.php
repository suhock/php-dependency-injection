<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Injection;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Fakes\FakeClassWithDanglingKeyProperty;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectedProperties;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectFunction;
use Suhock\DependencyInjection\Fakes\FakeClassWithStaticInjectMethod;
use Suhock\DependencyInjection\InjectorException;

/**
 * Test suite for {@see InjectionPlanFactory}.
 */
final class InjectionPlanFactoryTest extends TestCase
{
    public function testCreate_WithInjectMethod_PlanContainsMethodName(): void
    {
        // Arrange
        // Act
        $plan = InjectionPlanFactory::create(FakeClassWithInjectFunction::class);

        // Assert
        self::assertSame(['setObj'], $plan->methods);
        self::assertSame([], $plan->properties);
    }

    public function testCreate_WithInjectedProperties_PlanContainsPropertyKeyMap(): void
    {
        // Arrange
        // Act
        $plan = InjectionPlanFactory::create(FakeClassWithInjectedProperties::class);

        // Assert
        self::assertSame([], $plan->methods);
        self::assertSame(
            [
                'publicProperty' => null,
                'protectedProperty' => null,
                'privateProperty' => null,
                'keyedProperty' => 'key1',
                'optionalProperty' => null,
            ],
            $plan->properties,
        );
    }

    public function testCreate_WithStaticInjectMethod_ThrowsInjectorException(): void
    {
        // Arrange
        // Act & Assert
        try {
            InjectionPlanFactory::create(FakeClassWithStaticInjectMethod::class);
            self::fail('Expected ' . InjectorException::class);
        } catch (InjectorException $exception) {
            self::assertStringContainsString('setObj', $exception->getMessage());
        }
    }

    public function testCreate_WithKeyOnPropertyWithoutInject_ThrowsInjectorException(): void
    {
        // Arrange
        // Act & Assert
        try {
            InjectionPlanFactory::create(FakeClassWithDanglingKeyProperty::class);
            self::fail('Expected ' . InjectorException::class);
        } catch (InjectorException $exception) {
            self::assertStringContainsString('dependency', $exception->getMessage());
        }
    }
}
