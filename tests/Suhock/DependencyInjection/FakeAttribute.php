<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Attribute;

/**
 * A fake attribute
 */
#[Attribute]
class FakeAttribute
{
    public function __construct(
        public readonly string $value
    ) {
    }
}
