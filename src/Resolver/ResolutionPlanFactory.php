<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Cache\MetadataCache;
use Suhock\DependencyInjection\Descriptor\Descriptor;
use Suhock\DependencyInjection\Injection\InjectionPlan;
use Suhock\DependencyInjection\Injection\InjectionPlanFactory;
use Suhock\DependencyInjection\InjectorException;
use Suhock\DependencyInjection\InstanceProvider\AutowireClassSource;
use Suhock\DependencyInjection\InstanceProvider\CallableSource;
use Suhock\DependencyInjection\InstanceProvider\IntrospectableInstanceProviderInterface;
use Suhock\DependencyInjection\InstanceProvider\ReferenceSource;

use function array_slice;
use function class_exists;
use function interface_exists;
use function sprintf;

/**
 * Compiles a set of service descriptors into {@see ResolutionPlan}s — the dependency edges and guaranteed-failure
 * defects of each service — from reflection and each provider's {@see IntrospectableInstanceProviderInterface
 * dependency source}, without instantiating anything. Providers that are not introspectable compile to opaque
 * trusted-leaf plans.
 *
 * #[Inject] member plans are fetched through a {@see MetadataCache} under the same key prefix the runtime member
 * injector uses, so compiling a class warms the cache for runtime injection and vice versa.
 *
 * @internal
 */
final class ResolutionPlanFactory
{
    private const INJECTION_PLAN_KEY_PREFIX = 'sdi:injectionPlan:';

    private readonly MetadataCache $metadataCache;

    private readonly DependencyDescriber $describer;

    private readonly InjectionPlanFactory $injectionPlanFactory;

    /**
     * Per-compilation memo of the class-derived parts of autowired plans, keyed by class name, since the same class
     * may back several descriptors (e.g. added under multiple keys).
     *
     * @var array<class-string, array{list<ResolutionPlanEdge>, string|null, list<string>}>
     */
    private array $classParts = [];

    /**
     * @param CacheInterface|null $cache [optional] A shared cache for reflected metadata, ideally the same instance
     * the runtime injector uses so compiled #[Inject] plans are shared with runtime member injection
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->metadataCache = new MetadataCache($cache);
        $this->describer = new DependencyDescriber();
        $this->injectionPlanFactory = new InjectionPlanFactory();
    }

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

        if (!$provider instanceof IntrospectableInstanceProviderInterface) {
            return new ResolutionPlan($descriptor->className, [], opaque: true);
        }

        $source = $provider->getDependencySource();

        if ($source instanceof AutowireClassSource) {
            return $this->compileAutowireClass($source);
        }

        if ($source instanceof CallableSource) {
            return $this->compileCallable($source);
        }

        if ($source instanceof ReferenceSource) {
            $edge = new ResolutionPlanEdge(
                'the implementation class',
                new ResolvableDependency('implementation', [[$source->targetId]]),
                soft: false,
                isImplementation: true
            );

            return new ResolutionPlan($descriptor->className, [$edge]);
        }

        // LeafSource and any future source kinds compile as edge-less leaves.
        return new ResolutionPlan($descriptor->className, []);
    }

    private function compileAutowireClass(AutowireClassSource $source): ResolutionPlan
    {
        [$edges, $nonInstantiableMessage, $invalidInjectMemberMessages] = $this->classParts($source->className);

        if ($source->mutator !== null) {
            $rFunction = new ReflectionFunction($source->mutator);

            // The mutator's first parameter receives the new instance; the rest are injected.
            foreach (array_slice($rFunction->getParameters(), 1) as $rParam) {
                $edges[] = $this->parameterEdge(
                    $rParam,
                    sprintf('parameter $%s of the mutator', $rParam->getName())
                );
            }
        }

        return new ResolutionPlan(
            $source->className,
            $edges,
            nonInstantiableMessage: $nonInstantiableMessage,
            invalidInjectMemberMessages: $invalidInjectMemberMessages
        );
    }

    private function compileCallable(CallableSource $source): ResolutionPlan
    {
        $rFunction = new ReflectionFunction($source->callable);
        $edges = [];

        foreach (array_slice($rFunction->getParameters(), $source->skipLeadingParams) as $rParam) {
            $edges[] = $this->parameterEdge(
                $rParam,
                sprintf('parameter $%s of the factory', $rParam->getName())
            );
        }

        return new ResolutionPlan(
            $source->declaredType,
            $edges,
            declaredFactoryReturnType: $this->declaredReturnClass($rFunction)
        );
    }

    /**
     * The class-derived parts of an autowired plan — constructor and #[Inject] member edges plus guaranteed-failure
     * defects — independent of the descriptor's mutator.
     *
     * @param class-string $className
     *
     * @return array{list<ResolutionPlanEdge>, string|null, list<string>}
     */
    private function classParts(string $className): array
    {
        return $this->classParts[$className] ??= $this->computeClassParts($className);
    }

    /**
     * @param class-string $className
     *
     * @return array{list<ResolutionPlanEdge>, string|null, list<string>}
     */
    private function computeClassParts(string $className): array
    {
        if (!class_exists($className)) {
            return [[], "Class $className does not exist", []];
        }

        $rClass = new ReflectionClass($className);

        if (!$rClass->isInstantiable()) {
            return [[], "Class $className is not instantiable", []];
        }

        $edges = [];

        foreach ($rClass->getConstructor()?->getParameters() ?? [] as $rParam) {
            $edges[] = $this->parameterEdge(
                $rParam,
                sprintf('parameter $%s of __construct()', $rParam->getName())
            );
        }

        try {
            /** @var InjectionPlan $injectionPlan */
            $injectionPlan = $this->metadataCache->get(
                self::INJECTION_PLAN_KEY_PREFIX . $className,
                fn () => $this->injectionPlanFactory->create($className)
            );
        } catch (InjectorException $exception) {
            // The class's #[Inject] members are invalid; resolution throws before member injection, so member
            // edges are moot.
            return [$edges, null, [$exception->getMessage()]];
        }

        foreach ($injectionPlan->methods as $methodName) {
            $rMethod = new ReflectionMethod($className, $methodName);

            foreach ($rMethod->getParameters() as $rParam) {
                $edges[] = $this->parameterEdge(
                    $rParam,
                    sprintf('parameter $%s of %s()', $rParam->getName(), $methodName)
                );
            }
        }

        foreach ($injectionPlan->properties as $propertyName => $key) {
            $rProperty = new ReflectionProperty($className, $propertyName);
            $rType = $rProperty->getType();
            $dependency = $this->describer->describeType($propertyName, $rType, $key);

            $edges[] = new ResolutionPlanEdge(
                sprintf('property $%s', $propertyName),
                $dependency,
                soft: $rType === null || $rType->allowsNull(),
                declaredType: $dependency === null && $rType !== null ? (string) $rType : null
            );
        }

        return [$edges, null, []];
    }

    private function parameterEdge(ReflectionParameter $rParam, string $memberDescription): ResolutionPlanEdge
    {
        $dependency = $this->describer->describeParameter($rParam);
        $rType = $rParam->getType();

        return new ResolutionPlanEdge(
            $memberDescription,
            $dependency,
            soft: $rParam->isDefaultValueAvailable() || $rParam->allowsNull(),
            declaredType: $dependency === null && $rType !== null ? (string) $rType : null
        );
    }

    /**
     * The factory's declared return class, when it declares a single named type naming an existing class or
     * interface. Builtin, composite, absent, and unloadable (e.g. <code>self</code>/<code>static</code>) return
     * types yield <code>null</code> — their compatibility is unknowable without invoking the factory.
     */
    private function declaredReturnClass(ReflectionFunction $rFunction): ?string
    {
        $rReturnType = $rFunction->getReturnType();

        if (!$rReturnType instanceof ReflectionNamedType || $rReturnType->isBuiltin()) {
            return null;
        }

        $name = $rReturnType->getName();

        return class_exists($name) || interface_exists($name) ? $name : null;
    }
}
