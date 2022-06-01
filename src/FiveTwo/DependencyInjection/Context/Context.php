<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use Attribute;
use BackedEnum;
use UnitEnum;

/**
 * Pushes the specified context onto the context stack of the {@see ContextContainer} that is being used to inject
 * dependencies. The context will be scoped to the class, function, or function parameter to which it is applied.
 */
#[Attribute(
    Attribute::TARGET_CLASS |
    Attribute::TARGET_FUNCTION |
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PARAMETER |
    Attribute::TARGET_PROPERTY |
    Attribute::IS_REPEATABLE
)]
class Context
{
    private string $name;

    /**
     * @param string|UnitEnum $name The name of the context as a string or an enum
     */
    public function __construct(string|UnitEnum $name)
    {
        $this->name = self::getNameFromStringOrEnum($name);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public static function getNameFromStringOrEnum(string|UnitEnum $name): string
    {
        return match (true) {
            is_string($name) => $name,
            $name instanceof BackedEnum && is_string($name->value) => $name->value,
            default => $name->name
        };
    }
}
