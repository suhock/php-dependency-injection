<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Instantiation;

use Suhock\DependencyInjection\InjectorException;

/**
 * The member-injection step an injector applies through {@see \Suhock\DependencyInjection\Injector::injectMembers()}:
 * given a constructed instance, populate its dependencies (by default its {@see \Suhock\DependencyInjection\Inject}
 * members).
 */
interface PostInstantiationHookInterface
{
    /**
     * Populates the dependencies of the given instance.
     *
     * @param object $instance The new instance
     *
     * @throws InjectorException If the hook could not be applied to the instance
     */
    public function postInstantiate(object $instance): void;
}
