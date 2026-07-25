<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

use Suhock\DependencyInjection\Key;
use UnitEnum;

/**
 * One guaranteed-failure defect found while validating a container's configuration.
 *
 * @template TClass of object
 */
final class ValidationIssue
{
    /**
     * @param class-string<TClass> $className The class of the service the issue was found on
     * @param string|UnitEnum|null $key The key the service was added under, if any
     * @param ValidationIssueKind $kind The kind of defect
     * @param string $message A human-readable description of the defect
     */
    public function __construct(
        public readonly string $className,
        public readonly string|UnitEnum|null $key,
        public readonly ValidationIssueKind $kind,
        public readonly string $message,
    ) {}

    /**
     * @return string The service's display id: the class name, plus <code>#key</code> for keyed services
     */
    public function serviceId(): string
    {
        return $this->key === null
            ? $this->className
            : $this->className . '#' . Key::getKeyFromStringOrEnum($this->key);
    }
}
