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
 * Opt-in companion to {@see InstanceProviderInterface} for providers that can describe the source of the instance
 * they provide, without instantiating it, so that a build-time validator can walk the dependency graph. Providers
 * that do not implement this interface are treated as opaque, trusted leaves; nothing in
 * {@see InstanceProviderInterface} depends on it.
 */
interface IntrospectableInstanceProviderInterface
{
    /**
     * @return DependencySource A description of how this provider constructs its instance
     */
    public function getDependencySource(): DependencySource;
}
