<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\DependencyInjectionException;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeStringBackedEnum;

/**
 * Test suite for {@see ContainerValidationException} and {@see ValidationIssue}.
 */
final class ContainerValidationExceptionTest extends TestCase
{
    public function testConstruct_WithIssues_ExposesThemAndBuildsOneLinePerIssue(): void
    {
        $issues = [
            new ValidationIssue(
                FakeClassNoConstructor::class,
                null,
                ValidationIssueKind::MissingDependency,
                'required parameter $a (Foo) is not resolvable'
            ),
            new ValidationIssue(
                FakeInterfaceOne::class,
                'admin',
                ValidationIssueKind::CaptiveDependency,
                'singleton requires the scoped service Bar'
            ),
        ];

        $exception = new ContainerValidationException($issues);

        self::assertSame($issues, $exception->getIssues());
        self::assertStringContainsString('Container validation found 2 issues:', $exception->getMessage());
        self::assertStringContainsString(
            '[MissingDependency] ' . FakeClassNoConstructor::class . ': required parameter $a (Foo) is not resolvable',
            $exception->getMessage()
        );
        self::assertStringContainsString(
            '[CaptiveDependency] ' . FakeInterfaceOne::class . '#admin: singleton requires the scoped service Bar',
            $exception->getMessage()
        );
        self::assertNull($exception->getPrevious());
    }

    public function testConstruct_WithOneIssue_UsesSingularMessageHeader(): void
    {
        $exception = new ContainerValidationException([
            new ValidationIssue(
                FakeClassNoConstructor::class,
                null,
                ValidationIssueKind::NonInstantiableClass,
                'not instantiable'
            ),
        ]);

        self::assertStringContainsString('Container validation found 1 issue:', $exception->getMessage());
    }

    public function testException_IsADependencyInjectionException(): void
    {
        $exception = new ContainerValidationException([
            new ValidationIssue(
                FakeClassNoConstructor::class,
                null,
                ValidationIssueKind::MissingDependency,
                'message'
            ),
        ]);

        self::assertInstanceOf(DependencyInjectionException::class, $exception);
    }

    public function testServiceId_WithEnumKey_RendersTheStringForm(): void
    {
        $issue = new ValidationIssue(
            FakeClassNoConstructor::class,
            FakeStringBackedEnum::Test,
            ValidationIssueKind::MissingKeyedDependency,
            'message'
        );

        self::assertSame(FakeClassNoConstructor::class . '#' . FakeStringBackedEnum::Test->value, $issue->serviceId());
    }
}
