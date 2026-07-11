<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Throwable;

/**
 * Implemented by every exception thrown by the dependency injection library, so that all of them can be caught with a
 * single type regardless of whether they extend {@see DependencyInjectionException} (a logic error in how the container
 * or injector was configured or used) or {@see DependencyInjectionRuntimeException} (a failure surfaced while resolving
 * or injecting at run time).
 */
interface DependencyInjectionExceptionInterface extends Throwable
{
    /**
     * @return DependencyInjectionExceptionInterface|null The dependency injection exception that was passed in as
     * previous but was consolidated into this instance, or <code>null</code>
     */
    public function getConsolidatedException(): ?DependencyInjectionExceptionInterface;
}
