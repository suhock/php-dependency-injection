<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Attribute;

/**
 * Marks an injection point that the injector fills when instantiating a class. On a method, the injector invokes the
 * method, providing all its arguments. On a property, the injector resolves the property's type and assigns it. Methods
 * and properties may have any visibility, but methods must not be static. To qualify a dependency by key, apply
 * {@see Key} to the property or to the individual method parameter.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Inject
{
}
