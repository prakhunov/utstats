<?php
// `just fmt` / `just fmt-check`. PSR-12 layout with non-risky rules only, so formatting
// never changes behavior. Inline HTML outside PHP tags is left as-is.

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'assets', 'logs', 'renderings', '.verify', 'docker'])
    ->name(['*.php', '*.inc']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
