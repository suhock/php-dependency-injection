<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

/**
 * The kinds of guaranteed-failure configuration defects container validation detects. Every kind describes a
 * configuration that cannot resolve successfully at runtime; conditions that merely might fail (factory
 * bodies, per-call injector parameter overrides) are out of validation's scope and are never reported.
 */
enum ValidationIssueKind
{
    /**
     * A required dependency is not resolvable from the container.
     */
    case MissingDependency;

    /**
     * A required keyed dependency is not added under that key.
     */
    case MissingKeyedDependency;

    /**
     * An implementation target is not itself a resolvable service.
     */
    case MissingImplementation;

    /**
     * A required parameter has a type the container is never consulted for (builtin) and no default.
     */
    case UnresolvableParameter;

    /**
     * A factory's declared return type can never satisfy the service class it was added for.
     */
    case FactoryReturnTypeMismatch;

    /**
     * A #[Inject] or #[Key] member is invalid, so resolution throws before member injection.
     */
    case InvalidInjectMember;

    /**
     * The service class can never be instantiated (missing, abstract, or an interface).
     */
    case NonInstantiableClass;

    /**
     * A dependency cycle in which every edge is required, so no member can ever construct.
     */
    case CircularDependency;

    /**
     * A singleton reaches a scoped service through required edges, which always resolves outside a scope.
     */
    case CaptiveDependency;
}
