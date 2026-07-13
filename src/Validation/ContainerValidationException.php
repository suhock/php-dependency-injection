<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

use Suhock\DependencyInjection\DependencyInjectionException;

use function count;

/**
 * Aggregates every guaranteed-failure defect container validation found into one exception. Unlike the message
 * consolidation on {@see DependencyInjectionException}, which chains a single wrapped cause, this carries a list of
 * independent issues; it passes no previous exception.
 */
final class ContainerValidationException extends DependencyInjectionException
{
    /**
     * @param non-empty-list<ValidationIssue> $issues The defects found, in discovery order
     */
    public function __construct(
        private readonly array $issues
    ) {
        parent::__construct(self::buildMessage($issues));
    }

    /**
     * @return non-empty-list<ValidationIssue> The defects found, in discovery order
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * @param non-empty-list<ValidationIssue> $issues
     */
    private static function buildMessage(array $issues): string
    {
        $count = count($issues);
        $message = "Container validation found $count issue" . ($count === 1 ? '' : 's') . ':';

        foreach ($issues as $issue) {
            $message .= "\n  [{$issue->kind->name}] {$issue->serviceId()}: $issue->message";
        }

        return $message;
    }
}
