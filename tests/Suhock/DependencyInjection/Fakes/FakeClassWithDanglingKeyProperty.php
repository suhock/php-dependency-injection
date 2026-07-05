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
 * Fakes a misconfigured class with a {@see Key} on a property that is missing the accompanying Inject attribute.
 */
final class FakeClassWithDanglingKeyProperty
{
    /** @phpstan-ignore suhock.keyWithoutInject (deliberately violates the rule to exercise the injector's runtime guard) */
    #[Key('key1')]
    public ?FakeClassNoConstructor $dependency = null;
}
