<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\Disposable\DisposableInterface;

/**
 * Fakes a disposable class that depends on another disposable class, so tests can assert that a dependent is disposed
 * before its dependency. Records its disposal under the name "dependent" when given a log.
 */
final class FakeDisposableClassWithDependency implements DisposableInterface
{
    public int $disposeCount = 0;

    public function __construct(
        public readonly FakeDisposableClass $dependency,
        private readonly ?FakeDisposalLog $log = null,
    ) {}

    public function dispose(): void
    {
        ++$this->disposeCount;
        $this->log?->record('dependent');
    }
}
