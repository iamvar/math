<?php

use Iamvar\Math;

if (!function_exists('calc')) {
    function calc(string $expression): string
    {
        return (new Math())->calc($expression);
    }
}

if (!function_exists('isTrue')) {
    function isTrue(string $expression): string
    {
        return (new Math())->isTrue($expression);
    }
}
