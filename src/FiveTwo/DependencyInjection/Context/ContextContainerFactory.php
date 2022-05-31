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

use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\InjectorProvider;

/**
 * Provides factory methods for creating {@see ContextContainer} instances.
 */
class ContextContainerFactory
{
    /**
     * @return ContextContainer<Container> A {@see ContextContainer} using {@see Container}
     */
    public static function createForDefaultContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorProvider $injector) => new Container($injector));
    }
}
