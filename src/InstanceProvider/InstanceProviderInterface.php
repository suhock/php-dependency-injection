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
 * Marks the closed set of records describing how a service's instances are produced. Providers carry configuration
 * only (a class to autowire, a factory closure, an implementation target, a held instance, or a context selector),
 * and the compiled resolution plans execute them; no provider produces instances itself.
 *
 * @template TClass of object
 *
 * @internal The set of instance providers is closed; add services through the {@see \Suhock\DependencyInjection\ContainerBuilder} convenience methods (classes, factories, instances, implementations) instead of implementing this.
 */
interface InstanceProviderInterface {}
