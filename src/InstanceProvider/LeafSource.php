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
 * Describes a provider whose instance has no dependencies to walk, such as {@see ObjectInstanceProvider} (the
 * instance already exists) or an auto-bound provider for a container-supplied service.
 */
final class LeafSource implements DependencySource
{
}
