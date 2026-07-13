<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

use Closure;
use ReflectionFunction;
use ReflectionParameter;
use Suhock\DependencyInjection\Descriptor\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ImplementationInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Key;
use UnitEnum;

use function get_class;
use function hash_final;
use function hash_init;
use function hash_update;
use function implode;
use function ksort;

/**
 * Computes a stable digest of the validation-relevant shape of a configuration, so that a cache built from a
 * validation run can be safely reused only while the configuration it was built from has not changed in any way
 * that could affect the outcome.
 *
 * The digest is deliberately insensitive to anything the compiler that walks the dependency graph is insensitive to:
 * captured closure variables, held-instance state, and anything else the compiler never inspects.
 *
 * @internal
 */
final class ConfigurationFingerprint
{
    private const SCHEMA_VERSION = 2;

    /**
     * Computes a stable digest of the given configuration, or <code>null</code> if the configuration cannot be
     * fingerprinted — currently, only when a factory or mutator closure's origin cannot be determined (an internal
     * function or one defined in eval'd code), since then no file/line identity exists to hash.
     *
     * The digest is computed incrementally: each descriptor's record is fed to the hash context as it is produced,
     * so peak memory use is independent of the number of descriptors.
     *
     * @param array<string, Descriptor<object>> $descriptors The service descriptors, keyed by descriptor id
     */
    public static function compute(array $descriptors): ?string
    {
        ksort($descriptors);

        $context = hash_init('sha256');
        hash_update($context, self::SCHEMA_VERSION . "\n" . PHP_VERSION);

        foreach ($descriptors as $id => $descriptor) {
            $shape = self::providerShape($descriptor->instanceProvider);

            if ($shape === null) {
                return null;
            }

            hash_update($context, "\n" . implode('|', [
                $id,
                $descriptor->className,
                get_class($descriptor->lifetimeStrategy),
                $descriptor->shouldDispose ? '1' : '0',
                $shape,
            ]));
        }

        return hash_final($context);
    }

    /**
     * @param InstanceProviderInterface<object> $provider
     */
    private static function providerShape(InstanceProviderInterface $provider): ?string
    {
        if ($provider instanceof ClassInstanceProvider) {
            if ($provider->mutator === null) {
                return 'autowire:' . $provider->className . '-';
            }

            $signature = self::closureSignature($provider->mutator);

            return $signature === null ? null : 'autowire:' . $provider->className . $signature;
        }

        if ($provider instanceof ClosureInstanceProvider) {
            $signature = self::closureSignature($provider->factory);

            return $signature === null ? null : 'callable:' . $provider->className . ':' . $signature;
        }

        if ($provider instanceof ImplementationInstanceProvider) {
            return 'reference:' . $provider->implementationClassName;
        }

        // The remaining providers of the closed set — held instances and context selectors — contribute no edges,
        // so their class name is their whole validation-relevant shape.
        return 'leaf:' . get_class($provider);
    }

    /**
     * The validation-relevant identity of a closure: where it is declared and its declared parameter/return
     * signature. Captured variables are deliberately irrelevant — the compiler never invokes closures, so two
     * closures declared at the same site with the same signature always compile to identical dependency edges.
     *
     * @return string|null <code>null</code> if the closure has no file (an internal function or one defined in
     * eval'd code), and so cannot be fingerprinted
     */
    private static function closureSignature(Closure $closure): ?string
    {
        $rFunction = new ReflectionFunction($closure);
        $fileName = $rFunction->getFileName();

        if ($fileName === false) {
            return null;
        }

        $paramEntries = [];

        foreach ($rFunction->getParameters() as $rParam) {
            $paramEntries[] = self::parameterSignature($rParam);
        }

        $rReturnType = $rFunction->getReturnType();

        return $fileName
            . ':' . $rFunction->getStartLine() . '-' . $rFunction->getEndLine()
            . ':' . implode(',', $paramEntries)
            . ':' . ($rReturnType === null ? '' : (string) $rReturnType);
    }

    private static function parameterSignature(ReflectionParameter $rParam): string
    {
        $rType = $rParam->getType();

        return ($rType === null ? '' : (string) $rType)
            . '#' . self::keySignature($rParam)
            . '$' . $rParam->getName();
    }

    private static function keySignature(ReflectionParameter $rParam): string
    {
        foreach ($rParam->getAttributes(Key::class) as $rAttribute) {
            /** @var list<string|UnitEnum> $args */
            $args = $rAttribute->getArguments();

            if (!isset($args[0])) {
                continue;
            }

            $key = $args[0];

            return $key instanceof UnitEnum ? get_class($key) . '::' . $key->name : $key;
        }

        return '-';
    }
}
