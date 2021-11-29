<?php

$finder = PhpCsFixer\Finder::create()
    ->in('app')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'visibility_required' => false,
        'no_extra_blank_lines' => true,
        'binary_operator_spaces' => ['operators' => ['=' => 'single_space']]
    ])
    ->setFinder($finder)
;
