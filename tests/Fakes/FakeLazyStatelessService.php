<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

/**
 * A property-less class. PHP cannot represent it as a lazy object (there is no state to defer), so requesting it
 * lazily is a build-time defect.
 */
final class FakeLazyStatelessService
{
    public function ping(): string
    {
        return 'pong';
    }
}
