<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use UnitEnum;

use function str_contains;
use function strpos;
use function strtr;
use function substr;

/**
 * Computes and reads the internal storage id of a service. Unkeyed services use the bare class name; keyed services
 * use the class name and key joined by a NUL byte, which cannot occur in a class name, so a keyed id can never
 * collide with an unkeyed one or with a different (class, key) pair. Stateless, so every method is static.
 *
 * @internal
 */
final class DescriptorId
{
    private const SEPARATOR = "\0";

    /**
     * @param class-string $className
     */
    public static function compute(string $className, string|UnitEnum|null $key): string
    {
        if ($key === null) {
            return $className;
        }

        return $className . self::SEPARATOR . Key::getKeyFromStringOrEnum($key);
    }

    /**
     * The string form of the id's key, or <code>null</code> for an unkeyed id.
     */
    public static function keyOf(string $id): ?string
    {
        $separator = strpos($id, self::SEPARATOR);

        return $separator === false ? null : substr($id, $separator + 1);
    }

    /**
     * The id's display form: the class name, plus <code>#key</code> for keyed services.
     */
    public static function display(string $id): string
    {
        return str_contains($id, self::SEPARATOR) ? strtr($id, [self::SEPARATOR => '#']) : $id;
    }
}
