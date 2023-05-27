<?php

use Iamvar\Math;

test('calc', function (string $expression, string $result) {
    expect((new Math())->calc($expression))->toBe($result);
})->with([
    'plain number' => ['1', '1'],
    'cut zeroes' => ['1.0000', '1'],
    'do not cut useful zeroes' => ['10.0', '10'],
    'do not cut zeroes when no decimal' => ['10', '10'],
    'add' => ['1+2', '3'],
    'add decimal' => ['1.0000000001 + 2.1', '3.1000000001'],
    'complex expression' => ['(1.0000000001 + 2.1) * 3 - 2^(1+4/2)', '1.3000000003'],
    'with float' => [
        function () {
            $a = 0.1;
            $b = 2e-7;
            return "$a + $b";
        },
        '0.1000002',
    ],
]);

test('isTrue true-expressions', function (string $expression) {
    expect((new Math())->isTrue($expression))
        ->toBeTrue();
})->with(array_map(static fn($v) => [$v], [
    'simple less then' => '1 < 2',
    'two less then' => '1 < 2 < 3',
    'equals with =' => '1 = 1',
    'equals with ==' => '1 == 1',
    'equals with ===' => '1 === 1',
    'equals with different initial format' => '1.0 == 1',
    'two equals' => '1.0 == 1 === 1.000000000',
]));

test('isTrue false-expressions', function (string $expression) {
    expect((new Math())->isTrue($expression))
        ->toBeFalse();
})->with(array_map(static fn($v) => [$v], [
    'simple grater then' => '1 > 2',
    'two less then' => '1 < 2 > 3',
    'equals with =' => '1 = 2',
    'equals with ==' => '1 == 2',
    'equals with ===' => '1 === 2',
    'equals with many zeroes' => '1.0 == 1.000000000000001',
]));