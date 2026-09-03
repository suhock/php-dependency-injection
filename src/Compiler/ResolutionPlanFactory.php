<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use Suhock\DependencyInjection\Descriptor;
use Suhock\DependencyInjection\DescriptorId;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviders;
use Suhock\DependencyInjection\Lazy;
use Suhock\DependencyInjection\Resolver\ResolvableDependencyFactory;

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
     * Per-compilation memo of the class-derived parts of plans that construct a class, keyed by class name, since the
     * same class may back several descriptors (e.g. added under multiple keys).
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
            $plans[$id] = $this->compileDescriptor($id, $descriptor);
        }

        return $plans;
    }

    /**
     * @param Descriptor<object> $descriptor
     */
    private function compileDescriptor(string $id, Descriptor $descriptor): ResolutionPlan
    {
        $provider = $descriptor->instanceProvider;

        if (InstanceProviders::isClass($provider)) {
            return $this->compileAutowireClass($provider->className);
        }

        if (InstanceProviders::isClosure($provider)) {
            return $this->compileCallable($id, $provider->className, $provider->factory);
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
    private function compileAutowireClass(string $className): ResolutionPlan
    {
        $parts = $this->classParts($className);

        return new ResolutionPlan(
            $className,
            ResolutionPlanKind::AutowiredClass,
            argumentEdges: $parts['argumentEdges'],
            nonInstantiableMessage: $parts['nonInstantiableMessage'],
        );
    }

    /**
     * @param string $id The descriptor's id, against which a factory parameter naming the service itself is matched
     * @param class-string $className
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    private function compileCallable(string $id, string $className, Closure $factory): ResolutionPlan
    {
        $rFunction = new ReflectionFunction($factory);
        $edges = [];
        $hasSelf = false;

        foreach ($rFunction->getParameters() as $rParam) {
            $edge = self::parameterEdge($rParam);

            if (self::namesService($edge, $id, $className)) {
                $edge = self::selfEdge($edge);
                $hasSelf = true;
            }

            $edges[] = $edge;
        }

        // Only a factory that consumes the service's own instance needs its class constructed, so a plain factory
        // stays free of the class's constructor edges and of any defect in constructing it.
        $parts = $hasSelf ? $this->classParts($className) : null;

        return new ResolutionPlan(
            $className,
            ResolutionPlanKind::Factory,
            argumentEdges: $edges,
            selfConstructorEdges: $parts['argumentEdges'] ?? [],
            nonInstantiableMessage: $parts['nonInstantiableMessage'] ?? null,
            declaredFactoryReturnType: self::declaredReturnClass($rFunction),
        );
    }

    /**
     * Whether an edge names the very service its factory produces: the parameter declares that one class, with no
     * union or intersection, under the key the service was added with. Matching the declared type rather than the
     * resolved disjunction keeps the rule readable at the call site — a union member that happens to resolve to the
     * same service is an ordinary container lookup.
     *
     * @param class-string $className
     */
    private static function namesService(ResolutionPlanEdge $edge, string $id, string $className): bool
    {
        return $edge->dependency?->alternatives === [[$className]]
            && DescriptorId::compute($className, $edge->dependency->key) === $id;
    }

    /**
     * The self edge for a matching parameter. A self edge is never soft: it is satisfied by constructing the class,
     * which does not fail the way a container lookup can, so a nullable or defaulted parameter still receives the
     * instance rather than its fallback. Its default flag and declared type are dropped with the softness they
     * described.
     */
    private static function selfEdge(ResolutionPlanEdge $edge): ResolutionPlanEdge
    {
        return new ResolutionPlanEdge(
            $edge->name,
            $edge->dependency,
            soft: false,
            lazy: $edge->lazy,
            self: true,
        );
    }

    /**
     * The class-derived parts of a plan that constructs the class: its constructor edges plus any guaranteed-failure
     * defect.
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
            // An interface passes `interface_exists` but not `class_exists`, so saying it does not exist would be
            // actively misleading — it exists and simply cannot be constructed.
            return [
                'argumentEdges' => [],
                'nonInstantiableMessage' => interface_exists($className)
                    ? "Interface $className cannot be constructed"
                    : "Class $className does not exist",
            ];
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
