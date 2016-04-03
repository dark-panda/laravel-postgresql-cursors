<?php

require_once 'vendor/autoload.php';

$finder = Symfony\Component\Finder\Finder::create()
    ->files()
    ->in('src')
    ->in('tests')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$fixers = [
    'ordered_use',
    'php_unit_construct',
    'short_array_syntax',
    'strict',
    'strict_param',
];

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
    ->fixers($fixers)
    ->finder($finder);
