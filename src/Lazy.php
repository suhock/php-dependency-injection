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
 * Marks a parameter whose dependency is injected lazily. The container resolves and constructs the dependency only
 * when the injected object is first used. Applies to constructor and factory parameters.
 *
 * The lazy value is a PHP native lazy object of the resolved type: a ghost when the container constructs the dependency
 * itself, or a proxy when a factory produces it.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Lazy {}
