<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\InjectorTrait;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class ContextInjector implements InjectorInterface
{
    use InjectorTrait;

    public function __construct(
        private readonly ContextContainer $container
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool
    {
        $contextStack = ContextContainer::DEFAULT_CONTEXT_STACK;
        $rFunction = $rParam->getDeclaringFunction();

        if ($rFunction instanceof ReflectionMethod)
        {
            self::addAttributes($contextStack, $rFunction->getDeclaringClass()->getAttributes(Context::class));
        }

        /** @psalm-suppress ArgumentTypeCoercion Psalm missing stub for ReflectionFunctionAbstract */
        self::addAttributes($contextStack, $rFunction->getAttributes(Context::class));
        self::addAttributes($contextStack, $rParam->getAttributes(Context::class));

        if ($rParam->getType() instanceof ReflectionNamedType &&
            !$rParam->getType()->isBuiltin() &&
            $this->container->has($rParam->getType()->getName(), $contextStack)) {
            $paramValue = $this->container->get($rParam->getType()->getName(), $contextStack);

            return true;
        }

        return false;
    }

    /**
     * @param array<string> $contextStack
     * @param array<ReflectionAttribute<Context>> $rAttributes
     *
     * @return void
     */
    private static function addAttributes(array &$contextStack, array $rAttributes): void
    {
        foreach (array_reverse($rAttributes) as $rAttribute) {
            foreach (array_reverse($rAttribute->getArguments()) as $name) {
                /** @var string $name */
                $contextStack[] = $name;
            }
        }
    }
}
