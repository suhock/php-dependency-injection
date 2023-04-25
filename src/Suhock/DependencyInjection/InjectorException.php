<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Exception that indicates an error occurred as part of the dependency injection process (i.e. while resolving or
 * injecting dependencies when calling a function or building an object).
 */
class InjectorException extends DependencyInjectionException
{
}
