<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Exception;
use PHPUnit\Framework\TestCase;

class DependencyInjectionExceptionTest extends TestCase
{
    public function test__construct_NoComposition(): void
    {
        $exception = new DependencyInjectionException("Message1");
        self::assertSame("Message1", $exception->getMessage());
    }

    public function test__construct_Composition(): void
    {
        $exception = new DependencyInjectionException("Message2", new DependencyInjectionException("Message1"));
        self::assertMatchesRegularExpression("/Message2.*Message1/s", $exception->getMessage());
        self::assertNull($exception->getPrevious());
    }

    public function test__construct_NoCompositionForUnrelated(): void
    {
        $exception = new DependencyInjectionException("Message2", $previous = new Exception("Message1"));
        self::assertSame("Message2", $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}
