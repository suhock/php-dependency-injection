<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;
use Suhock\DependencyInjection\AbstractContainerParameterResolver;
use Suhock\DependencyInjection\ContainerInterface;
use UnitEnum;

use function count;

/**
  * Resolves function parameters using a {@see ContextContainer}.
 */
class ContextContainerParameterResolver extends AbstractContainerParameterResolver
{
    /**
     * @param ContextContainer<ContainerInterface> $container
     */
    public function __construct(
        private readonly ContextContainer $container
    ) {
        parent::__construct($container);
    }

    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$result): bool
    {
        $contextCount = 0;

        try {
            $this->pushContextsFromParameter($contextCount, $rParam);

            return $this->tryGetInstanceFromParameter($rParam, $result);
        } finally {
            $this->popContexts($contextCount);
        }
    }

    private function pushContextsFromParameter(int &$contextCount, ReflectionParameter $rParam): void
    {
        $rFunction = $rParam->getDeclaringFunction();

        if ($rFunction instanceof ReflectionMethod) {
            $rAttributes = $rFunction->getDeclaringClass()->getAttributes(Context::class);
            $this->pushContextFromAttributes($contextCount, $rAttributes);
        }

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
