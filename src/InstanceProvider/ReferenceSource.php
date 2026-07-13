<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

/**
 * Describes an {@see ImplementationInstanceProvider}: the instance is obtained by requesting {@see $targetId} from
 * the resolution root's container.
 */
final class ReferenceSource implements DependencySource
{
    /**
     * @param class-string $targetId The id of the service to request
     */
    public function __construct(
        public readonly string $targetId
    ) {
    }
}
