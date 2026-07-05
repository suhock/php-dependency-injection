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
 * A hook applied to every instance an injector creates, after the instantiation strategy has constructed it and before
 * it is returned to the caller.
 */
interface PostInstantiationHookInterface
{
    /**
     * Applies the hook to a newly constructed instance.
     *
     * @param object $instance The new instance
     *
     * @throws InjectorException If the hook could not be applied to the instance
     */
    public function postInstantiate(object $instance): void;
}
