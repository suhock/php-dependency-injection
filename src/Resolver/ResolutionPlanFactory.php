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

/**
 * Compiles a set of service descriptors into {@see ResolutionPlan}s — how each service's instance is produced, the
 * dependency edges resolution will satisfy, and the guaranteed-failure defects — from reflection and each provider's
 * {@see IntrospectableInstanceProviderInterface dependency source}, without instantiating anything. Providers that
 * are not introspectable compile to opaque trusted-leaf plans.
 *
 * #[Inject] member plans are fetched through a {@see MetadataCache} under the same key prefix the runtime member
 * injector uses, so compilation and the standalone injector share the cached computation.
 *
 * @internal
 */
final class ResolutionPlanFactory
{
    private const INJECTION_PLAN_KEY_PREFIX = 'sdi:injectionPlan:';

    private readonly MetadataCache $metadataCache;

    /**
     * Per-compilation memo of the class-derived parts of autowired plans, keyed by class name, since the same class
     * may back several descriptors (e.g. added under multiple keys).
     *
     * @var array<class-string, array{
     *     argumentEdges: list<ResolutionPlanEdge>,
     *     injectMethodEdges: array<string, list<ResolutionPlanEdge>>,
     *     injectPropertyEdges: array<string, ResolutionPlanEdge>,
     *     nonInstantiableMessage: string|null,
     *     invalidInjectMemberMessages: list<string>
     * }>
     */
    private array $classParts = [];

    /**
     * @param CacheInterface|null $cache [optional] A shared cache for reflected metadata, ideally the same instance
     * the runtime injector uses so compiled #[Inject] plans are shared with the standalone injector
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->metadataCache = new MetadataCache($cache);
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
            return new ResolutionPlan($descriptor->className, ResolutionPlanKind::Opaque);
        }

        $source = $provider->getDependencySource();

        if ($source instanceof AutowireClassSource) {
            return $this->compileAutowireClass($source);
        }

        if ($source instanceof CallableSource) {
            return $this->compileCallable($source);
        }

        if ($source instanceof ReferenceSource) {
            return new ResolutionPlan(
                $descriptor->className,
                ResolutionPlanKind::Implementation,
                implementationTarget: $source->targetId
            );
        }

        // LeafSource compiles as an edge-less leaf; an unknown future source kind is trusted like an opaque provider.
        return new ResolutionPlan($descriptor->className, ResolutionPlanKind::Leaf);
    }

    private function compileAutowireClass(AutowireClassSource $source): ResolutionPlan
    {
        $parts = $this->classParts($source->className);
        $mutatorEdges = [];

        if ($source->mutator !== null) {
            $rFunction = new ReflectionFunction($source->mutator);

            // The mutator's first parameter receives the new instance; the rest are injected.
            foreach (array_slice($rFunction->getParameters(), 1) as $rParam) {
                $mutatorEdges[] = self::parameterEdge($rParam);
            }
        }

        return new ResolutionPlan(
            $source->className,
            ResolutionPlanKind::AutowiredClass,
            argumentEdges: $parts['argumentEdges'],
            injectMethodEdges: $parts['injectMethodEdges'],
            injectPropertyEdges: $parts['injectPropertyEdges'],
            mutatorEdges: $mutatorEdges,
            nonInstantiableMessage: $parts['nonInstantiableMessage'],
            invalidInjectMemberMessages: $parts['invalidInjectMemberMessages']
        );
    }

    private function compileCallable(CallableSource $source): ResolutionPlan
    {
        $rFunction = new ReflectionFunction($source->callable);
        $edges = [];

        foreach (array_slice($rFunction->getParameters(), $source->skipLeadingParams) as $rParam) {
            $edges[] = self::parameterEdge($rParam);
        }

        return new ResolutionPlan(
            $source->declaredType,
            ResolutionPlanKind::Factory,
            argumentEdges: $edges,
            declaredFactoryReturnType: self::declaredReturnClass($rFunction)
        );
    }

    /**
     * The class-derived parts of an autowired plan — constructor and #[Inject] member edges plus guaranteed-failure
     * defects — independent of the descriptor's mutator.
     *
     * @param class-string $className
     *
     * @return array{
     *     argumentEdges: list<ResolutionPlanEdge>,
     *     injectMethodEdges: array<string, list<ResolutionPlanEdge>>,
     *     injectPropertyEdges: array<string, ResolutionPlanEdge>,
     *     nonInstantiableMessage: string|null,
     *     invalidInjectMemberMessages: list<string>
     * }
     */
    private function classParts(string $className): array
    {
        return $this->classParts[$className] ??= $this->computeClassParts($className);
    }

    /**
     * @param class-string $className
     *
     * @return array{
     *     argumentEdges: list<ResolutionPlanEdge>,
     *     injectMethodEdges: array<string, list<ResolutionPlanEdge>>,
     *     injectPropertyEdges: array<string, ResolutionPlanEdge>,
     *     nonInstantiableMessage: string|null,
     *     invalidInjectMemberMessages: list<string>
     * }
     */
    private function computeClassParts(string $className): array
    {
        $parts = [
            'argumentEdges' => [],
            'injectMethodEdges' => [],
            'injectPropertyEdges' => [],
            'nonInstantiableMessage' => null,
            'invalidInjectMemberMessages' => [],
        ];

        if (!class_exists($className)) {
            $parts['nonInstantiableMessage'] = "Class $className does not exist";

            return $parts;
        }

        $rClass = new ReflectionClass($className);

        if (!$rClass->isInstantiable()) {
            $parts['nonInstantiableMessage'] = "Class $className is not instantiable";

            return $parts;
        }

        foreach ($rClass->getConstructor()?->getParameters() ?? [] as $rParam) {
            $parts['argumentEdges'][] = self::parameterEdge($rParam);
        }

        try {
            /** @var InjectionPlan $injectionPlan */
            $injectionPlan = $this->metadataCache->get(
                self::INJECTION_PLAN_KEY_PREFIX . $className,
                static fn () => InjectionPlanFactory::create($className)
            );
        } catch (InjectorException $exception) {
            // The class's #[Inject] members are invalid; resolution throws before member injection, so member
            // edges are moot.
            $parts['invalidInjectMemberMessages'][] = $exception->getMessage();

            return $parts;
        }

        foreach ($injectionPlan->methods as $methodName) {
            $edges = [];

            foreach ((new ReflectionMethod($className, $methodName))->getParameters() as $rParam) {
                $edges[] = self::parameterEdge($rParam);
            }

            $parts['injectMethodEdges'][$methodName] = $edges;
        }

        foreach ($injectionPlan->properties as $propertyName => $key) {
            $rType = (new ReflectionProperty($className, $propertyName))->getType();
            $dependency = ResolvableDependencyFactory::createFromType($propertyName, $rType, $key);

            $parts['injectPropertyEdges'][$propertyName] = new ResolutionPlanEdge(
                $propertyName,
                $dependency,
                soft: $rType === null || $rType->allowsNull(),
                declaredType: $dependency === null && $rType !== null ? (string) $rType : null
            );
        }

        return $parts;
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
            declaredType: $dependency === null && $rType !== null ? (string) $rType : null
        );
    }

    /**
     * The factory's declared return class, when it declares a single named type naming an existing class or
     * interface. Builtin, composite, absent, and unloadable (e.g. <code>self</code>/<code>static</code>) return
     * types yield <code>null</code> — their compatibility is unknowable without invoking the factory.
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
