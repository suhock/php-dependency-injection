<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Throwable;

class UnresolvedParameterException extends UnresolvedDependencyException
{
    /**
     * @param string $functionName
     * @param string $parameterName
     * @param string|null $parameterType
     * @param Throwable|null $previous
     */
    public function __construct(
        private readonly string $functionName,
        private readonly string $parameterName,
        private readonly ?string $parameterType = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(sprintf(
            'Could not provide a value for required parameter %s$%s in function %s()',
            $parameterType !== null ? $parameterType . ' ' : '',
            $parameterName,
            $functionName
        ), $previous);
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    /**
     * @return string
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * @return string|null
     */
    public function getParameterType(): ?string
    {
        return $this->parameterType;
    }
}
