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

use FiveTwo\DependencyInjection\Context\Context;
use RuntimeException;
use Throwable;

#[Context('context1')]
class FakeContextAwareClass
{
    public readonly RuntimeException $runtimeException;

    #[Context('context2')]
    public function __construct(
        #[Context('context3')]
        public readonly Throwable $throwable,
        #[Context('context4')]
        RuntimeException $runtimeException
    ) {
        $this->runtimeException = $runtimeException;
    }
}
