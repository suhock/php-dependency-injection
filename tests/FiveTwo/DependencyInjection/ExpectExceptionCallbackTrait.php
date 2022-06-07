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

use Closure;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

/**
 * @psalm-require-extends \PHPUnit\Framework\TestCase
 */
trait ExpectExceptionCallbackTrait
{
    /**
     * @var Closure|null
     */
    private ?Closure $expectedExceptionCallback = null;

    protected function expectExceptionCallback(callable $callback): void
    {
        $this->expectedExceptionCallback = $callback(...);
    }

    /**
     * @return mixed
     * @throws Throwable
     */
    protected function runTest(): mixed
    {
        try {
            /**
             * @psalm-suppress MixedAssignment TestCase::runTest() has inconsistent return types (void/mixed)
             */
            $testResult = parent::runTest();
        } catch (Throwable $exception) {
            if ($this->expectedExceptionCallback === null) {
                throw $exception;
            }

            ($this->expectedExceptionCallback)($exception);

            return null;
        }

        if ($this->expectedExceptionCallback !== null) {
            /**
             * @psalm-suppress InternalClass
             * @psalm-suppress InternalMethod
             */
            throw new AssertionFailedError('Failed asserting that exception was thrown');
        }

        return $testResult;
    }
}
