<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Instantiation;

use ReflectionClass;
use ReflectionException;
use Suhock\DependencyInjection\InjectorException;
use Suhock\DependencyInjection\Resolver\ArgumentResolver;
use Suhock\DependencyInjection\Resolver\ParameterResolverInterface;

/**
 * Instantiates a class by reflecting its constructor and resolving each parameter through the
 * {@see ParameterResolverInterface}. This is the universal fallback strategy: it applies to every instantiable class and
 * produces the authoritative diagnostics, so it never declines (its {@see tryInstantiate()} narrows the contract to
 * always return an instance or throw).
 */
final class ReflectionInstantiationStrategy implements InstantiationStrategyInterface
{
    private readonly ArgumentResolver $argumentResolver;

    public function __construct(ParameterResolverInterface $resolver)
    {
        $this->argumentResolver = new ArgumentResolver($resolver);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param array<mixed> $params
     *
     * @return TClass
     * @throws InjectorException If the class does not exist, is not instantiable, or cannot be constructed
     */
    public function tryInstantiate(string $className, array $params): object
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore catch.neverThrown (a class-string may reference an unloadable class at runtime) */
        } catch (ReflectionException $e) {
            throw new InjectorException("Class $className does not exist", $e);
        }

        if (!$rClass->isInstantiable()) {
            throw new InjectorException("Class $className is not instantiable");
        }

        try {
            /** @var TClass $instance */
            $instance = $rClass->newInstanceArgs(
                $this->argumentResolver->resolve($rClass->getConstructor()?->getParameters() ?? [], $params)
            );
        } catch (ReflectionException $e) {
            // The check for !isInstantiable() should make this unreachable
            throw new InjectorException("Could not instantiate $className", $e);
        }

        return $instance;
    }
}
