<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\DependencyInjection\Inject;
use Suhock\DependencyInjection\Key;

/**
 * Fakes a class with injected properties of varying visibility, including a keyed and a nullable one.
 */
final class FakeClassWithInjectedProperties
{
    #[Inject]
    public FakeClassNoConstructor $publicProperty;

    #[Inject]
    protected FakeClassNoConstructor $protectedProperty;

    #[Inject]
    private FakeClassNoConstructor $privateProperty;

    #[Inject]
    #[Key('key1')]
    public FakeClassNoConstructor $keyedProperty;

    #[Inject]
    public ?FakeInterfaceOne $optionalProperty = null;

    public function __construct()
    {
        // The non-nullable properties are seeded here so the type checker sees them initialized; property injection
        // overwrites them, or throws for a required property it cannot resolve.
        $placeholder = new FakeClassNoConstructor();
        $this->publicProperty = $placeholder;
        $this->protectedProperty = $placeholder;
        $this->privateProperty = $placeholder;
        $this->keyedProperty = $placeholder;
    }

    public function getProtectedProperty(): FakeClassNoConstructor
    {
        return $this->protectedProperty;
    }

    public function getPrivateProperty(): FakeClassNoConstructor
    {
        return $this->privateProperty;
    }
}
