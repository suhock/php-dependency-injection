<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\ContainerInjectorTrait;
use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\InjectorTrait;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;
use UnitEnum;

/**
 * Context-aware injector for injecting dependencies into function and constructor calls.
 *
 * @template TContainer of ContainerInterface
 */
class ContextInjector implements InjectorInterface
{
    use InjectorTrait;
    use ContainerInjectorTrait;

    /**
     * @param ContextContainer<TContainer> $container The container from which dependencies will be resolved
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly ContextContainer $container
    ) {
    }

    /**
     * @psalm-mutation-free
     */
    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @inheritDoc
     */
    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool
    {
        $contextCount = 0;

        try {
            $this->pushContextsFromParameter($contextCount, $rParam);

            return $this->getInstanceFromParameter($rParam, $paramValue);
        } finally {
            $this->popContexts($contextCount);
        }
    }

    private function pushContextsFromParameter(int &$contextCount, ReflectionParameter $rParam): void
    {
        $rFunction = $rParam->getDeclaringFunction();

        if ($rFunction instanceof ReflectionMethod) {
            /** @psalm-suppress ArgumentTypeCoercion Psalm not applying template to getAttributes() call */
            $this->pushContextFromAttributes(
                $contextCount,
                $rFunction->getDeclaringClass()->getAttributes(Context::class)
            );
        }

        /** @psalm-suppress ArgumentTypeCoercion Psalm missing stub for ReflectionFunctionAbstract */
        $this->pushContextFromAttributes($contextCount, $rFunction->getAttributes(Context::class));
        $this->pushContextFromAttributes($contextCount, $rParam->getAttributes(Context::class));
    }

    /**
     * @param int $contextCount
     * @param array<ReflectionAttribute<Context>> $rAttributes
     *
     * @return void
     */
    private function pushContextFromAttributes(int &$contextCount, array $rAttributes): void
    {
        foreach ($rAttributes as $rAttribute) {
            /** @var list<string|UnitEnum> $args */
            $args = $rAttribute->getArguments();

            if (count($args) > 0) {
                $this->container->push(Context::getNameFromStringOrEnum($args[0]));
                $contextCount++;
            }
        }
    }

    private function popContexts(int $contextCount): void
    {
        for ($i = 0; $i < $contextCount; $i++) {
            $this->container->pop();
        }
    }
}
