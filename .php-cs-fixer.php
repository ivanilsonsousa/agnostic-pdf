<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
  ->in(__DIR__)
  ->exclude(['bootstrap/cache', 'storage', 'vendor', 'node_modules'])
  ->name('*.php')
  ->ignoreDotFiles(true)
  ->ignoreVCS(true);

return (new Config())
  ->setRiskyAllowed(true)
  ->setRules([
    'indentation_type'       => true,
    'array_indentation'      => true,
    'trim_array_spaces'      => true,
    'array_syntax'           => ['syntax' => 'short'],
    'binary_operator_spaces' => [
      'default' => 'align_single_space_minimal',
    ],
    'ordered_imports'                          => ['sort_algorithm' => 'alpha'],
    'no_unused_imports'                        => true,
    'single_import_per_statement'              => true,
    'concat_space'                             => ['spacing' => 'one'],
    'no_superfluous_phpdoc_tags'               => true,
    'method_chaining_indentation'              => true,
    'no_useless_else'                          => true,
    'align_multiline_comment'                  => true,
    'assign_null_coalescing_to_coalesce_equal' => true,
    'blank_line_after_namespace'               => true,
    'blank_line_after_opening_tag'             => true,
    'blank_line_between_import_groups'         => true,
    'blank_line_before_statement'              => [
      'statements' => [
        'case',
        'continue',
        'declare',
        'default',
        'do',
        'exit',
        'for',
        'foreach',
        'goto',
        'if',
        'include',
        'include_once',
        'phpdoc',
        'require',
        'require_once',
        'return',
        'switch',
        'throw',
        'try',
        'while',
        'yield',
        'yield_from'
      ],
    ],
    'braces' => [
      'allow_single_line_anonymous_class_with_empty_body' => true,
      'allow_single_line_closure'                         => true,
      'position_after_anonymous_constructs'               => 'same',
      'position_after_control_structures'                 => 'same',
      'position_after_functions_and_oop_constructs'       => 'next',
    ],
    'cast_spaces'                 => true,
    'class_attributes_separation' => [
      'elements' => [
        'const'        => 'none',
        'property'     => 'none',
        'method'       => 'one',
        'trait_import' => 'none',
        'case'         => 'none'
      ]
    ],
    'class_definition' => [
      'multi_line_extends_each_single_line' => true,
      'single_item_single_line'             => true,
      'single_line'                         => true,
      'space_before_parenthesis'            => true,
      'inline_constructor_arguments'        => true,
    ],
    'class_keyword' => true
  ])
  ->setIndent('  ')
  ->setFinder($finder);
