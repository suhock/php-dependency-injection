<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        // The rules below are additional conventions the codebase follows.
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => ['statements' => ['return']],
        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => [
            'import_functions' => true,
            'import_constants' => true,
            'import_classes' => true,
        ],
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'nullable_type_declaration_for_default_null_value' => true,
        'single_quote' => true,
        // Comparisons are written subject-first, not Yoda-style.
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('vendor')
            ->in(__DIR__)
    );
