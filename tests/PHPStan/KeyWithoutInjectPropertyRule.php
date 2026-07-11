<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Suhock\DependencyInjection\Inject;
use Suhock\DependencyInjection\Key;

use function strcasecmp;

/**
 * Reports a {@see Key} attribute on a property that is missing the accompanying {@see Inject}: a key alone does not
 * mark a property for injection, so it would otherwise be silently inert until the injector's runtime check. Promoted
 * constructor properties are parameters in the syntax tree and never reach this rule; a Key there qualifies the
 * constructor parameter and is valid.
 *
 * @implements Rule<Property>
 */
final class KeyWithoutInjectPropertyRule implements Rule
{
    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @param Property $node
     *
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->hasAttribute($node, Key::class) || $this->hasAttribute($node, Inject::class)) {
            return [];
        }

        $className = $scope->isInClass() ? $scope->getClassReflection()->getDisplayName() : '?';
        $errors = [];

        foreach ($node->props as $rProp) {
            $errors[] = RuleErrorBuilder::message(
                "Property $className::\$" . $rProp->name->toString() .
                    ' has a #[Key] attribute but no #[Inject]; a key alone does not mark a property for injection.'
            )
                ->identifier('suhock.keyWithoutInject')
                ->build();
        }

        return $errors;
    }

    /**
     * @param class-string $attributeClassName
     */
    private function hasAttribute(Property $node, string $attributeClassName): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (strcasecmp($attr->name->toString(), $attributeClassName) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
