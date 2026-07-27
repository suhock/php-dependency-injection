<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Override;
use Suhock\Disposable\DisposableInterface;

/**
 * Fakes a disposable service class that a decorator can extend, so tests can exercise a factory that wraps the
 * instance the container constructed for it.
 *
 * @phpstan-ignore ergebnis.final (Test fake intentionally left extensible for decoration by
 *     {@see FakeDisposableDecorator})
 */
class FakeDisposableBaseClass implements DisposableInterface
{
    public int $disposeCount = 0;

    public function __construct(
        private readonly ?FakeDisposalLog $log = null,
        private readonly string $name = 'inner',
    ) {}

    #[Override]
    public function dispose(): void
    {
        ++$this->disposeCount;
        $this->log?->record($this->name);
    }
}
