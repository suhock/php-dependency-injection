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
 * A disposable dependency that records its construction and disposal in a {@see FakeDisposalLog}, so a test can prove
 * that a never-used lazy instance is neither constructed nor disposed when the container is torn down.
 */
final class FakeLazyDisposable implements DisposableInterface
{
    public readonly string $value;

    public function __construct(private readonly FakeDisposalLog $log)
    {
        $this->log->record('constructed');
        $this->value = 'pong';
    }

    public function ping(): string
    {
        // Reads a property so that calling this method triggers lazy initialization, as real usage would.
        return $this->value;
    }

    #[Override]
    public function dispose(): void
    {
        $this->log->record('disposed');
    }
}
