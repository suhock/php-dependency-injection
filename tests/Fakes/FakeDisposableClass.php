<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\DependencyInjection\DisposableInterface;

/**
 * Fakes a disposable class. Counts how many times it has been disposed and, if given a log, records its disposal in
 * order under the given name.
 */
final class FakeDisposableClass implements DisposableInterface
{
    public int $disposeCount = 0;

    public function __construct(
        private readonly ?FakeDisposalLog $log = null,
        private readonly string $name = 'dependency',
    ) {}

    public function dispose(): void
    {
        ++$this->disposeCount;
        $this->log?->record($this->name);
    }
}
