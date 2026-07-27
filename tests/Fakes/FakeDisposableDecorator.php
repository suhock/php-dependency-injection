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

/**
 * Fakes a decorator that wraps an instance of the service it stands in for and does not dispose the inner instance.
 */
final class FakeDisposableDecorator extends FakeDisposableBaseClass
{
    public function __construct(
        public readonly FakeDisposableBaseClass $inner,
        private readonly ?FakeDisposalLog $decoratorLog = null,
    ) {
        parent::__construct();
    }

    #[Override]
    public function dispose(): void
    {
        $this->decoratorLog?->record('decorator');
    }
}
