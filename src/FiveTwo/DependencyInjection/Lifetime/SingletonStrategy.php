<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

/**
 * @template TClass of object
 * @extends LifetimeStrategy<TClass>
 */
class SingletonStrategy extends LifetimeStrategy
{
    /** @var TClass|null */
    private ?object $instance = null;

    private bool $isSet = false;

    /**
     * @inheritDoc
     */
    public function get(callable $factory): ?object
    {
        if (!$this->isSet) {
            $this->instance = $factory();
            $this->isSet = true;
        }

        return $this->instance;
    }
}
