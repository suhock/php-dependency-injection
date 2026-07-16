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
use BackedEnum;
use UnitEnum;

use function is_string;

/**
 * Binds the annotated constructor parameter to the service added under this key.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Key
{
    private readonly string $key;

    /**
     * @param string|UnitEnum $key The service key as a string or an enum
     */
    public function __construct(string|UnitEnum $key)
    {
        $this->key = self::getKeyFromStringOrEnum($key);
    }

    /**
     * @return string The service key as a string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string|UnitEnum $key The service key as a string or an enum
     *
     * @return string The service key as a string
     */
    public static function getKeyFromStringOrEnum(string|UnitEnum $key): string
    {
        return match (true) {
            $key instanceof BackedEnum && is_string($key->value) => $key->value,
            $key instanceof UnitEnum => $key->name,
            default => $key,
        };
    }
}
