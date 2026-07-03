<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\DependencyInjection\Key;

/**
 * Fakes a class whose sole constructor dependency is resolved by a {@see Key}.
 */
final class FakeClassWithKeyedDependency
{
    public function __construct(
        #[Key('key1')]
        public readonly FakeClassNoConstructor $dependency
    ) {
    }
}
