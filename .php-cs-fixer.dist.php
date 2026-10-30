<?php

declare(strict_types=1);

$roots = array_values(array_filter(
    [__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/tools', __DIR__ . '/public', __DIR__ . '/bin', __DIR__ . '/templates'],
    'is_dir',
));

$finder = PhpCsFixer\Finder::create()->in($roots)->name('*.php')->name('console');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'parameters']],
        'no_empty_comment' => true,
        'void_return' => true,
    ])
    ->setFinder($finder);
