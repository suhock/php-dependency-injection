<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use RuntimeException;
use Suhock\DependencyInjection\Context\Context;
use Throwable;

/**
 * Fakes a simple class which specifies contexts at various scopes.
 */
#[Context('context1')]
class FakeClassWithContexts
{
    public readonly Throwable $throwable;

    #[Context('context2')]
    public function __construct(
        #[Context('context3')]
        Throwable $throwable,
        #[Context('context4')]
        public readonly RuntimeException $runtimeException
    ) {
        $this->throwable = $throwable;
    }
}
