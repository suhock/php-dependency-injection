<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\InjectorTrait;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Provides context-aware methods for injecting dependencies into function and constructor calls.
 */
class ContextInjector implements InjectorInterface
{
    use InjectorTrait;

    /**
     * @param ContextContainer $container The container from which dependencies will be resolved
     */
    public function __construct(
        private readonly ContextContainer $container
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool
    {
        $contextCount = 0;

        try {
            $rFunction = $rParam->getDeclaringFunction();

            if ($rFunction instanceof ReflectionMethod) {
                $contextCount += self::addAttributes(
                    $rFunction->getDeclaringClass()->getAttributes(Context::class)
                );
            }

            /** @psalm-suppress ArgumentTypeCoercion Psalm missing stub for ReflectionFunctionAbstract */
            $contextCount += self::addAttributes($rFunction->getAttributes(Context::class));
            $contextCount += self::addAttributes($rParam->getAttributes(Context::class));

            if ($rParam->getType() instanceof ReflectionNamedType &&
                !$rParam->getType()->isBuiltin() &&
                $this->container->has($rParam->getType()->getName())) {
                $paramValue = $this->container->get(
                    $rParam->getType()->getName()
                );

                return true;
            }

            return false;
        } finally {
            while (--$contextCount >= 0) {
                $this->container->pop();
            }
        }
    }

    /**
     * @param array<ReflectionAttribute<Context>> $rAttributes
     *
     * @return int the number of contexts pushed onto the stack
     */
    private function addAttributes(array $rAttributes): int
    {
        $count = 0;

        foreach (array_reverse($rAttributes) as $rAttribute) {
            /** @var mixed $name */
            foreach (array_reverse($rAttribute->getArguments()) as $name) {
                if (is_string($name)) {
                    $this->container->push($name);
                    $count++;
                } else {
                    while (--$count >= 0) {
                        $this->container->pop();
                    }

                    throw new InvalidArgumentException(
                        "Context arguments must be of type string, got " . gettype($name)
                    );
                }
            }
        }

        return $count;
    }
}
