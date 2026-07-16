<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Static helpers for working with {@see DisposableInterface} instances.
 */
final class Disposables
{
    /**
     * Runs the callback with the given disposable, then disposes it — even if the callback throws — and returns the
     * callback's value. The PHP counterpart of a <code>using</code> block, for a scope, a container, or any other
     * disposable. If both the callback and {@see DisposableInterface::dispose()} throw, the disposal exception
     * supersedes.
     *
     * @template TDisposable of DisposableInterface
     * @template TReturn
     *
     * @param TDisposable $disposable The disposable to dispose once the callback completes
     * @param callable(TDisposable):TReturn $callback Receives the disposable and returns a result
     *
     * @return TReturn The value returned by the callback
     */
    public static function using(DisposableInterface $disposable, callable $callback): mixed
    {
        try {
            return $callback($disposable);
        } finally {
            $disposable->dispose();
        }
    }
}
