<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Attribute;

/**
 * Indicates that a method should be autowired when instantiating a class. Also, can be used to indicate that a class's
 * constructor should be autowired when used in conjunction with an {@see AttributeContainer}.
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Autowire
{
}
