<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Override;

/**
 * A dependency that records its own construction in a {@see FakeLazyCounter}, so a test can observe whether and when
 * it was built.
 */
final class FakeLazyService implements FakeLazyInterface
{
    public readonly string $value;

    public function __construct(FakeLazyCounter $counter)
    {
        ++$counter->constructions;
        $this->value = 'pong';
    }

    #[Override]
    public function ping(): string
    {
        // Reads a property so that calling this method triggers lazy initialization, as real usage would.
        return $this->value;
    }
}
