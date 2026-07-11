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

/**
 * Fakes a class with protected and private inject methods.
 */
final class FakeClassWithNonPublicInjectMethods
{
    public ?FakeClassNoConstructor $protectedSetterValue = null;

    public ?FakeClassNoConstructor $privateSetterValue = null;

    #[Inject]
    protected function setProtected(FakeClassNoConstructor $obj): void
    {
        $this->protectedSetterValue = $obj;
    }

    #[Inject]
    private function setPrivate(FakeClassNoConstructor $obj): void
    {
        $this->privateSetterValue = $obj;
    }
}
