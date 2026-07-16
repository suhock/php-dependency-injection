<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suhock\DependencyInjection\Fakes\FakeDisposableClass;

/**
 * Test suite for {@see Disposables}.
 */
final class DisposablesTest extends TestCase
{
    public function testUsing_ReturnsCallbackResult(): void
    {
        $result = Disposables::using(new FakeDisposableClass(), static fn() => 'result');

        self::assertSame('result', $result);
    }

    public function testUsing_PassesDisposableToCallback(): void
    {
        $disposable = new FakeDisposableClass();

        $received = Disposables::using($disposable, static fn(FakeDisposableClass $d) => $d);

        self::assertSame($disposable, $received);
    }

    public function testUsing_DisposesAfterCallback(): void
    {
        $disposable = new FakeDisposableClass();

        Disposables::using($disposable, static fn() => null);

        self::assertSame(1, $disposable->disposeCount);
    }

    public function testUsing_DisposesWhenCallbackThrows(): void
    {
        $disposable = new FakeDisposableClass();
        $exception = null;

        try {
            Disposables::using($disposable, static fn() => throw new RuntimeException('boom'));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('boom', $exception->getMessage());
        self::assertSame(1, $disposable->disposeCount);
    }
}
