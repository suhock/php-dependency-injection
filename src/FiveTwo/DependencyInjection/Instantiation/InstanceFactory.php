<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

/**
 * @template TDependency
 */
interface InstanceFactory
{
    /**
     * @return TDependency|null
     */
    public function get(): ?object;
}
