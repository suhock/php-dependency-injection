<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Cache\MetadataCache;
use Suhock\DependencyInjection\Instantiation\ArgumentResolver;
use Suhock\DependencyInjection\Instantiation\ChainedInstantiationStrategy;
use Suhock\DependencyInjection\Instantiation\FastPathInstantiationStrategy;
use Suhock\DependencyInjection\Instantiation\InstantiationStrategyInterface;
use Suhock\DependencyInjection\Instantiation\ReflectionInstantiationStrategy;
use function count;

/**
 * Default implementation for {@see InjectorInterface} that resolves missing parameter values using a
 * {@see ParameterResolverInterface}. Instantiation is delegated to an {@see InstantiationStrategyInterface}. By
 * default
 * this is a {@see ChainedInstantiationStrategy} of a fast path (when the resolver supports
 * {@see TypeParameterResolverInterface}) that avoids per-instantiation reflection, followed by full reflection; a
 * custom strategy may be supplied instead.
 */
final class Injector implements InjectorInterface
{
    /** Cache id prefix for the list of {@see Autowire} method names on a class. */
    private const AUTOWIRE_METHODS_CACHE_PREFIX = 'sdi:autowireMethods:';
    private readonly MetadataCache $cache;
    private readonly ArgumentResolver $argumentResolver;
    private readonly InstantiationStrategyInterface $strategy;

    /**
     * @param ParameterResolverInterface $resolver The resolver to use for resolving parameters
     * @param InstantiationStrategyInterface $strategy The instantiation strategy to use.
     * @param CacheInterface|null $sharedCache [optional] Optional shared (L2) metadata cache. The in-process (L1) cache
     *  is always active for the life of this injector; supply an {@see Cache\CacheInterface} here to additionally share
     *  reflected metadata across requests.
     */
    public function __construct(
        ParameterResolverInterface $resolver,
        InstantiationStrategyInterface $strategy,
        ?CacheInterface $sharedCache = null,
    ) {
        $this->argumentResolver = new ArgumentResolver($resolver);
        $this->strategy = $strategy;
        $this->cache = new MetadataCache($sharedCache);
    }

    /**
     * Creates an injector that resolves parameters from the given container and instantiates classes using the default
     * strategy: a fast path (when supported) followed by full reflection. This is the standard way to construct an
     * injector; use the constructor directly only to supply a custom resolver or instantiation strategy.
     *
     * @param ContainerInterface $container The container to resolve parameter values from
     * @param CacheInterface|null $cache [optional] Optional shared (L2) metadata cache; supply an
     * {@see Cache\CacheInterface} to share reflected metadata across requests
     */
    public static function createDefault(ContainerInterface $container, ?CacheInterface $cache = null): self
    {
        $resolver = new ContainerParameterResolver($container);
        $strategy = new ChainedInstantiationStrategy([
            new FastPathInstantiationStrategy($resolver, $cache),
            new ReflectionInstantiationStrategy($resolver)
        ]);

        return new self($resolver, $strategy, $cache);
    }

    public function call(callable $function, array $params = []): mixed
    {
        // A callable normalized to a closure is always reflectable, so this cannot throw ReflectionException.
        $rFunction = new ReflectionFunction($function(...));

        return $rFunction->invokeArgs(
            $this->argumentResolver->resolve($rFunction->getParameters(), $params)
        );
    }

    /**
     * @inheritDoc
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param array<mixed> $params
     *
     * @return TClass
     */
    public function instantiate(string $className, array $params = []): object
    {
        $instance = $this->strategy->tryInstantiate($className, $params);

        if ($instance === null) {
            throw new InjectorException("No instantiation strategy could instantiate $className");
        }

        $this->injectAutowireFunctions($instance);

        return $instance;
    }

    private function injectAutowireFunctions(object $instance): void
    {
        foreach ($this->getAutowireMethods($instance::class) as $methodName) {
            $method = new ReflectionMethod($instance, $methodName);
            $closure = $method->getClosure($instance);
            $this->call($closure);
        }
    }

    /**
     * Returns the names of the public methods on the given class annotated with {@see Autowire}, computing them via
     * reflection on the first request and caching the (flat, scalar) result so subsequent instantiations skip the scan.
     *
     * @param class-string $className
     *
     * @return list<string>
     */
    private function getAutowireMethods(string $className): array
    {
        return $this->cache->get(
            self::AUTOWIRE_METHODS_CACHE_PREFIX . $className,
            /** @return list<string> */
            static function () use ($className) {
                $names = [];

                $class = new ReflectionClass($className);

                foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
                    if (count($rMethod->getAttributes(Autowire::class)) > 0) {
                        $names[] = $rMethod->getName();
                    }
                }

                return $names;
            }
        );
    }
}
