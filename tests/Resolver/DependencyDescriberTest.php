<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Closure;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\Key;

/**
 * Test suite for {@see DependencyDescriber}.
 */
final class DependencyDescriberTest extends TestCase
{
    private function fakeNamedParam(FakeClassNoConstructor $namedParam): void
    {
    }

    private function fakeNullableParam(?FakeClassNoConstructor $nullableParam): void
    {
    }

    private function fakeDefaultedParam(FakeClassNoConstructor $defaultedParam = new FakeClassNoConstructor()): void
    {
    }

    private function fakeBuiltinParam(string $builtinParam): void
    {
    }

    private function fakeUnionParam(FakeInterfaceOne|FakeInterfaceTwo $unionParam): void
    {
    }

    private function fakeIntersectionParam(FakeInterfaceOne&FakeInterfaceTwo $intersectionParam): void
    {
    }

    private function fakeKeyedParam(#[Key('key1')] FakeClassNoConstructor $keyedParam): void
    {
    }

    private function createParameter(Closure $fakeMethod, string $parameterName): ReflectionParameter
    {
        return new ReflectionParameter($fakeMethod, $parameterName);
    }

    public function testDescribeParameter_NamedParam_ReturnsResolvableDependencyForClass(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeNamedParam(...), 'namedParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNotNull($dependency);
        self::assertSame('namedParam', $dependency->name);
        self::assertSame([[FakeClassNoConstructor::class]], $dependency->alternatives);
        self::assertNull($dependency->key);
    }

    public function testDescribeParameter_NullableParam_ReturnsResolvableDependencyForClass(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeNullableParam(...), 'nullableParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert: nullability does not change the described alternatives — it is applied by the caller's fallback.
        self::assertNotNull($dependency);
        self::assertSame([[FakeClassNoConstructor::class]], $dependency->alternatives);
    }

    public function testDescribeParameter_DefaultedParam_ReturnsResolvableDependencyForClass(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeDefaultedParam(...), 'defaultedParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert: a default value does not change the described alternatives either.
        self::assertNotNull($dependency);
        self::assertSame([[FakeClassNoConstructor::class]], $dependency->alternatives);
    }

    public function testDescribeParameter_BuiltinParam_ReturnsNull(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeBuiltinParam(...), 'builtinParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNull($dependency);
    }

    public function testDescribeParameter_UntypedParam_ReturnsNull(): void
    {
        // Arrange: an untyped parameter has no ReflectionType at all, unlike a builtin type.
        $describer = new DependencyDescriber();
        $fakeFunction = static function ($untypedParam): void {
        };
        $rParam = $this->createParameter($fakeFunction, 'untypedParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNull($dependency);
    }

    public function testDescribeParameter_UnionParam_ReturnsOneAlternativePerMember(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeUnionParam(...), 'unionParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNotNull($dependency);
        self::assertSame([[FakeInterfaceOne::class], [FakeInterfaceTwo::class]], $dependency->alternatives);
    }

    public function testDescribeParameter_IntersectionParam_ReturnsSingleAlternativeWithAllMembers(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeIntersectionParam(...), 'intersectionParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNotNull($dependency);
        self::assertSame([[FakeInterfaceOne::class, FakeInterfaceTwo::class]], $dependency->alternatives);
    }

    public function testDescribeParameter_ParamWithKeyAttribute_ReturnsResolvableDependencyWithKey(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeKeyedParam(...), 'keyedParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNotNull($dependency);
        self::assertSame('key1', $dependency->key);
    }

    public function testDescribeParameter_ParamWithoutKeyAttribute_ReturnsResolvableDependencyWithNullKey(): void
    {
        // Arrange
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeNamedParam(...), 'namedParam');

        // Act
        $dependency = $describer->describeParameter($rParam);

        // Assert
        self::assertNotNull($dependency);
        self::assertNull($dependency->key);
    }

    public function testDescribeType_WithNullType_ReturnsNull(): void
    {
        // Arrange
        $describer = new DependencyDescriber();

        // Act
        $dependency = $describer->describeType('name', null, null);

        // Assert
        self::assertNull($dependency);
    }

    public function testDescribeType_GivenKeyDirectly_UsesGivenKeyRegardlessOfAttributes(): void
    {
        // Arrange: describeType takes the key as a plain argument, independent of any attribute on the reflected
        // parameter — the attribute-reading behavior belongs to describeParameter alone.
        $describer = new DependencyDescriber();
        $rParam = $this->createParameter($this->fakeNamedParam(...), 'namedParam');

        // Act
        $dependency = $describer->describeType($rParam->getName(), $rParam->getType(), 'explicitKey');

        // Assert
        self::assertNotNull($dependency);
        self::assertSame('explicitKey', $dependency->key);
        self::assertSame([[FakeClassNoConstructor::class]], $dependency->alternatives);
    }
}
