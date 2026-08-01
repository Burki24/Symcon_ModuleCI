<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('tests/stubs')
    ->exclude('docs')
    ->notPath('/libs\/.*\//')
    ->in(__DIR__ . '/../');
return (new PhpCsFixer\Config())->setRules([
        'align_multiline_comment' => [
            'comment_type' => 'all_multiline'
        ],
        'array_indentation' => true,
        'array_syntax' => [
            'syntax' => 'short'
        ],
        'binary_operator_spaces' => [
            'operators' => ['=>' => 'align']
        ],
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => false,
        'single_space_around_construct' => true,
        'declare_parentheses' => true,
        'no_multiple_statements_per_line' => true,
        'braces_position' => [
            'anonymous_functions_opening_brace' => 'next_line_unless_newline_at_signature_end'
        ],
        'statement_indentation' => true,
        'no_extra_blank_lines' => true,
        'cast_spaces' => true,
        'class_attributes_separation' => false,
        'class_definition' => true,
        'concat_space' => [
            'spacing' => 'one'
        ],
        'declare_strict_types' => true,
        'declare_equal_normalize' => true,
        'elseif' => true,
        'encoding' => true,
        'full_opening_tag' => true,
        'function_declaration' => true,
        'type_declaration_spaces' => true,
        'implode_call' => true,
        'include' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'linebreak_after_opening_tag' => true,
        'logical_operators' => true,
        'lowercase_cast' => true,
        'constant_case' => ['case' => 'lower'],
        'lowercase_keywords' => true,
        'lowercase_static_reference' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'method_argument_space' => true,
        'method_chaining_indentation' => true,
        'multiline_comment_opening_closing' => true,
        'multiline_whitespace_before_semicolons' => true,
        'native_function_casing' => true,
        'native_type_declaration_casing' => true,
        'new_with_parentheses' => true,
        'no_alias_functions' => true,
        'no_alternative_syntax' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'blank_lines_before_namespace' => true,
        'no_break_comment' => [
            'comment_text' => 'No break. Add additional comment above this line if intentional'
        ],
        'no_closing_tag' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'echo_tag_syntax' => ['format' => 'long'],
        'no_singleline_whitespace_before_semicolons' => true,
        'no_spaces_after_function_name' => true,
        'no_spaces_around_offset' => false,
        'spaces_inside_parentheses' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_braces' => false,
        'no_useless_else' => false,
        'no_useless_return' => false,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,
        'normalize_index_brace' => true,
        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'ordered_interfaces' => true,
        'pow_to_exponentiation' => false,
        'protected_to_private' => false,
        'return_type_declaration' => true,
        'self_accessor' => true,
        'semicolon_after_instruction' => true,
        'single_class_element_per_statement' => true,
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        'single_quote' => true,
        'single_trait_insert_per_statement' => true,
        'space_after_semicolon' => true,
        'standardize_not_equals' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'ternary_operator_spaces' => true,
        'trailing_comma_in_multiline' => false,
        'trim_array_spaces' => true,
        'unary_operator_spaces' => true,
        'visibility_required' => true,
        'whitespace_after_comma_in_array' => true
    ])
    ->setFinder($finder)
    ->setIndent('    ')
    ->setLineEnding("\n");
