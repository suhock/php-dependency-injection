<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Exception that indicates a service was requested from an invalid scope: a scoped service was resolved with no scope
 * active (including a scoped dependency reached from a singleton's dependency graph), or a service was requested from a
 * scope that has been disposed.
 */
class ScopeException extends ContainerException
{
}
