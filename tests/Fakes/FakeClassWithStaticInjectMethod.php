<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\DependencyInjection\Inject;

/**
 * Fakes a misconfigured class with an Inject attribute on a static method.
 */
final class FakeClassWithStaticInjectMethod
{
    public static ?FakeClassNoConstructor $obj = null;

    #[Inject]
    public static function setObj(FakeClassNoConstructor $obj): void
    {
        self::$obj = $obj;
    }
}
