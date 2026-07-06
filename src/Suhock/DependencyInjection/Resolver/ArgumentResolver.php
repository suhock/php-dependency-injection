<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionParameter;
use Suhock\DependencyInjection\Injector;
use function array_key_exists;

/**
 * Resolves an argument list for a reflected function or constructor: each parameter is satisfied by a caller-supplied
 * value (matched by position, then by name) or, failing that, by the {@see ParameterResolverInterface}. Shared by the
 * reflection instantiation path and {@see Injector::call()}.
 *
 * @internal
 */
final class ArgumentResolver
{
    public function __construct(
        private readonly ParameterResolverInterface $resolver
    ) {
    }

    /**
     * @param array<ReflectionParameter> $rParameters
     * @param array<mixed> $params
     *
     * @return list<mixed>
     */
    public function resolve(array $rParameters, array $params): array
    {
        /** @var list<mixed> $values */
        $values = [];

        foreach ($rParameters as $rParam) {
            $values[] = match (true) {
                array_key_exists($rParam->getPosition(), $params) => $params[$rParam->getPosition()],
                array_key_exists($rParam->getName(), $params) => $params[$rParam->getName()],
                default => $this->resolver->resolveParameter($rParam)
            };
        }

        return $values;
    }
}
