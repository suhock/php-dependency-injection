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
    public const DEFAULT = '';

    /** @var list<string> */
    private readonly array $names;

    /**
     * @param string|UnitEnum $name
     * @param string|UnitEnum ...$names
     */
    public function __construct(string|UnitEnum $name, string|UnitEnum ...$names)
    {
        /** @var list<string> $names */
        $this->names = array_map(fn (string|UnitEnum $name): string => match (true) {
            is_string($name) => $name,
            $name instanceof BackedEnum && is_string($name->value) => $name->value,
            default => $name->name
        }, [$name, ...$names]);
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
