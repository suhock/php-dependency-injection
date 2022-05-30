<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

/**
 * Provides instances of one class.
 *
 * @template TClass of object
 */
interface InstanceFactory
{
    /**
     * @return TClass|null An instance of the class or <code>null</code>
     */
    public function get(): ?object;
}
