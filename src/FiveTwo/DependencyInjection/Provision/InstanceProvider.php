<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Provision;

/**
 * Interface for classes that manage the provision of objects.
 *
 * @template TClass of object
 */
interface InstanceProvider
{
    /**
     * @return TClass An instance of the class
     */
    public function get(): object;
}
