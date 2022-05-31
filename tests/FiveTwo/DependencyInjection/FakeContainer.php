<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

class FakeContainer implements ContainerInterface
{
    /** @var array<class-string, object|null> */
    public array $classMapping = [];

    /** @psalm-suppress InvalidReturnType */
    public function get(string $className): ?object
    {
        /**
         * @psalm-suppress InvalidReturnStatement Psalm does not support array class mappings
         * @phpstan-ignore-next-line PHPStan does not support array class mappings
         */
        return $this->has($className) ?
            $this->classMapping[$className] :
            throw new UnresolvedClassException($className);
    }

    public function has(string $className): bool
    {
        return array_key_exists($className, $this->classMapping);
    }
}
