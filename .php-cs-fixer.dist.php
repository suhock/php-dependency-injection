<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,

        // Imports
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_functions' => true,
            'import_constants' => true,
            'import_classes' => true,
        ],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],

        // PHPDoc
        'no_empty_phpdoc' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_line_span' => [
            'const' => 'single',
            'property' => 'single',
            'method' => 'multi',
        ],
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,

        // Whitespace & layout
        'blank_line_before_statement' => ['statements' => ['return']],
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
            ],
        ],
        'method_chaining_indentation' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'no_extra_blank_lines' => true,

        // Language constructs & cleanup
        'get_class_to_class_keyword' => true, // RISKY
        'no_alias_functions' => true, // RISKY
        'no_empty_statement' => true,
        'no_unneeded_braces' => true,
        'no_unneeded_control_parentheses' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'nullable_type_declaration' => true, // RISKY
        'nullable_type_declaration_for_default_null_value' => true,
        'self_accessor' => true, // RISKY
        'self_static_accessor' => true,
        'ternary_to_null_coalescing' => true,

        // Casing
        'class_reference_name_casing' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'native_type_declaration_casing' => true,

        // Style preferences
        'heredoc_to_nowdoc' => true,
        'single_line_comment_style' => true,
        'single_quote' => true,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('vendor')
    );
