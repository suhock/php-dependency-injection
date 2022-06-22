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

/**
 * Fakes a simple class which specifies contexts at various scopes.
 */
#[FakeAttribute('test')]
class FakeClassWithAttribute
{
    #[Context('context2')]
    public function __construct(
        public readonly string $value = ''
    ) {
    }
}
