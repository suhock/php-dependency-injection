<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\DependencyInjectionException;
use Throwable;

/**
 * @template TDependency
 * @template TActual
 */
class ImplementationException extends DependencyInjectionException
{
    /**
     * @param class-string<TDependency> $expectedClassName
     * @param class-string<TActual> $actualClassName
     * @param Throwable|null $previous
     */
    public function __construct(
        private readonly string $expectedClassName,
        private readonly string $actualClassName,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Class $this->actualClassName is not of type $this->expectedClassName",
            $previous
        );
    }

    /**
     * @return class-string<TDependency>
     */
    public function getExpectedClassName(): string
    {
        return $this->expectedClassName;
    }

    /**
     * @return class-string<TActual>
     */
    public function getActualClassName(): string
    {
        return $this->actualClassName;
    }
}
