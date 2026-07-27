<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviders;
use Suhock\DependencyInjection\Lazy;

use function array_slice;
use function class_exists;
use function count;
use function interface_exists;

/**
 * Compiles a set of service descriptors into {@see ResolutionPlan}s (how each service's instance is produced, the
 * dependency edges resolution will satisfy, and the guaranteed-failure defects) from reflection and the closed set
 * of instance providers, without instantiating anything.
 *
 * @internal
 */
final class ResolutionPlanFactory
{
    /**
     * Per-compilation memo of the class-derived parts of autowired plans, keyed by class name, since the same class
     * may back several descriptors (e.g. added under multiple keys).
     *
     * @var array<class-string, array{
     *     argumentEdges: list<ResolutionPlanEdge>,
     *     nonInstantiableMessage: string|null
     *     }>
     */
    private array $classParts = [];

    /**
     * Compiles a plan for every descriptor, keyed by the descriptor's id.
     *
     * @param array<string, Descriptor<object>> $descriptors
     *
     * @return array<string, ResolutionPlan>
     */
    public function compile(array $descriptors): array
    {
        $plans = [];

        foreach ($descriptors as $id => $descriptor) {
            $plans[$id] = $this->compileDescriptor($descriptor);
        }

        return $plans;
    }

    /**
     * @param Descriptor<object> $descriptor
     */
    private function compileDescriptor(Descriptor $descriptor): ResolutionPlan
    {
        $provider = $descriptor->instanceProvider;

        if (InstanceProviders::isClass($provider)) {
            return $this->compileAutowireClass($provider->className, $provider->mutator);
        }

        if (InstanceProviders::isClosure($provider)) {
            return self::compileCallable($provider->className, $provider->factory);
        }

        if (InstanceProviders::isImplementation($provider)) {
            return new ResolutionPlan(
                $descriptor->className,
                ResolutionPlanKind::Implementation,
                implementationTarget: $provider->implementationClassName,
            );
        }

        // The remaining providers of the closed set (held instances and context selectors) have no dependencies.
        return new ResolutionPlan($descriptor->className, ResolutionPlanKind::Leaf);
    }

    /**
     * @param class-string $className
     */
    // @phpstan-ignore missingType.callable (a mutator's parameters are injected)
    private function compileAutowireClass(string $className, ?Closure $mutator): ResolutionPlan
    {
        $parts = $this->classParts($className);
        $mutatorEdges = [];

        if ($mutator !== null) {
            $rFunction = new ReflectionFunction($mutator);

            // The mutator's first parameter receives the new instance; the rest are injected.
            foreach (array_slice($rFunction->getParameters(), 1) as $rParam) {
                $mutatorEdges[] = self::parameterEdge($rParam);
            }
        }

        return new ResolutionPlan(
            $className,
            ResolutionPlanKind::AutowiredClass,
            argumentEdges: $parts['argumentEdges'],
            mutatorEdges: $mutatorEdges,
            nonInstantiableMessage: $parts['nonInstantiableMessage'],
        );
    }

    /**
     * @param class-string $className
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    private static function compileCallable(string $className, Closure $factory): ResolutionPlan
    {
        $rFunction = new ReflectionFunction($factory);
        $edges = [];

        foreach ($rFunction->getParameters() as $rParam) {
            $edges[] = self::parameterEdge($rParam);
        }

        return new ResolutionPlan(
            $className,
            ResolutionPlanKind::Factory,
            argumentEdges: $edges,
            declaredFactoryReturnType: self::declaredReturnClass($rFunction),
        );
    }

    /**
     * The class-derived parts of an autowired plan (constructor edges plus any guaranteed-failure defect),
     * independent of the descriptor's mutator.
     *
     * @param class-string $className
     *
     * @return array{argumentEdges: list<ResolutionPlanEdge>, nonInstantiableMessage: string|null}
     */
    private function classParts(string $className): array
    {
        return $this->classParts[$className] ??= $this->computeClassParts($className);
    }

    /**
     * @param class-string $className
     *
     * @return array{argumentEdges: list<ResolutionPlanEdge>, nonInstantiableMessage: string|null}
     */
    private function computeClassParts(string $className): array
    {
        if (!class_exists($className)) {
            return ['argumentEdges' => [], 'nonInstantiableMessage' => "Class $className does not exist"];
        }

        $rClass = new ReflectionClass($className);

        if (!$rClass->isInstantiable()) {
            return ['argumentEdges' => [], 'nonInstantiableMessage' => "Class $className is not instantiable"];
        }

        $argumentEdges = [];

        foreach ($rClass->getConstructor()?->getParameters() ?? [] as $rParam) {
            $argumentEdges[] = self::parameterEdge($rParam);
        }

        return ['argumentEdges' => $argumentEdges, 'nonInstantiableMessage' => null];
    }

    private static function parameterEdge(ReflectionParameter $rParam): ResolutionPlanEdge
    {
        $dependency = ResolvableDependencyFactory::createFromParameter($rParam);
        $rType = $rParam->getType();
        $hasDefault = $rParam->isDefaultValueAvailable();

        return new ResolutionPlanEdge(
            $rParam->getName(),
            $dependency,
            soft: $hasDefault || $rParam->allowsNull(),
            hasDefault: $hasDefault,
            defaultValue: $hasDefault ? $rParam->getDefaultValue() : null,
            declaredType: $dependency === null && $rType !== null ? (string) $rType : null,
            lazy: count($rParam->getAttributes(Lazy::class)) > 0,
        );
    }

    /**
     * The factory's declared return class, when it declares a single named type naming an existing class or
     * interface. Builtin, composite, absent, and unloadable (e.g. <code>self</code>/<code>static</code>) return
     * types yield <code>null</code>; their compatibility is unknowable without invoking the factory.
     */
    private static function declaredReturnClass(ReflectionFunction $rFunction): ?string
    {
        $rReturnType = $rFunction->getReturnType();

        if (!$rReturnType instanceof ReflectionNamedType || $rReturnType->isBuiltin()) {
            return null;
        }

        $name = $rReturnType->getName();

        return class_exists($name) || interface_exists($name) ? $name : null;
    }
}
