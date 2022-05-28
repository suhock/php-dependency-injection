<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use Attribute;

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

    public function __construct(string $name, string ...$names)
    {
        $this->names = [$name, ...$names];
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
