<?php

use Iamvar\Math;

test('calc', function (string $expression, string $expected) {
    expect((new Math())->calc($expression))->toBe($expected);
})->with([
    'negative number' => ['-1.5', '-1.5'],
    'cut zeroes' => ['1.0000', '1'],
    'do not cut useful zeroes' => ['10.0', '10'],
    'do not cut zeroes when no decimal' => ['10', '10'],
    'remove leading zeroes' => ['010', '10'],
    'add' => ['1+2', '3'],
    'add decimal' => ['1.0000000001 + 2.1', '3.1000000001'],
    'complex expression' => ['(1.0000000001 + 2.1) * 3 - 2^(1+4/2)', '1.3000000003'],
    'sqrt' => ['1.69 ^ (1/2)', '1.3'],
    'power of 2' => ['9 ^ (3/2)', '27'],
    'power of 1/4' => ['81 ^ (3/4)', '27'],
    'expressions with negative amount in braces' => ['(1 + 0.2) * -3 + (1-9) * -2', '12.4'],
    '18.6' => ['(3 + 11 + 17 + 28 + 34)/5', '18.6'],
    'sqrt from expression' => ['((1/(5 - 1)) * ((5 - 14.6)^2 + ( 8 - 14.6)^2 + ( 13 - 14.6)^2 + ( 19 - 14.6)^2 + ( 28 - 14.6)^2))^0.5', '9.1815031449104'],
    'with float' => [
        function () {
            $a = 0.1E1;
            $b = 2e-7;
            return "$a + $b";
        },
        '1.0000002',
    ],
    ['10 ^ 20', '100000000000000000000'],
    ['16 ^ 20.5', '4835703278458516698824704'],
    ['65536^0.0625', '2'],
    ['18446744073709551616^0.015625', '2'],
    ['2^2*2', '8'],
    ['2 * 2^2', '8'],
    ['1 - -1', '2'],
    ['2 ^ 4 % 3 ^ 2', '7'],
    ['-1 - -1', '0'],
    ['--1 - -1', '2'],
    ['-0', '0'],
    ['-0.0', '0'],
    ['2^-2', '0.25'],
    ['2^(--2)', '4'],
    ['abs(-2)', '2'],
    ['abs(2)', '2'],
    ['abs(2 - 4)', '2'],
    ['abs(2 - (3+1))', '2'],
    ['min(2, (3+1))', '2'],
    ['max(2, (3+1))', '4'],
    ['min(090.2, 10)', '10'],
    ...array_map(static fn(string $n) => [$n, $n], range('0', '9'))
]);

test('calc Error', function (string $expression) {
    (new Math())->calc($expression);
})->throws(ValueError::class, 'Unsupported expression')
    ->with([
        'only one brace' => '(1 + 2',
        'extra brace' => '(1 + 2))',
        'unclosed brace' => '( (1 + 2)',
        'number with comma' => '1,1 + 2',
        'letters' => '$a + 1',
        'exclamation' => '1!',
        'abs with extra braces' => 'abs(2 - (3+1)',
        ...range('a', 'z')
    ]);

test('isTrue true-expressions', function (string $expression) {
    expect((new Math())->isTrue($expression))
        ->toBeTrue();
})->with([
    'simple less then' => '1 < 2',
    'two less then' => '1 < 2 < 3',
    'grater or equals' => '2 >= 1',
    'equals on grater or equals' => '1.0 >= 1',
    'less or equals' => '1 <= 2',
    'equals on less or equals' => '2 <= 2',
    'equals with =' => '1 = 1',
    'equals with ==' => '1 == 1',
    'equals with ===' => '1 === 1',
    'equals with different initial format' => '1.0 == 1',
    'two equals' => '1.0 == 1 === 1.000000000',
    'expressions' => '1.2 * 3 == 3.6 + 0',
    'expressions with braces' => '(1 + 0.2) * 3 == 3.6 + (1-9) * 0',
    'mix of comparisons' => '1 == 2 - 1 < 2',
    '5/6' => '1/2 + 1/3 = 1*3/2*3 + 1*2/3*2 = 3/6 + 2/6 = 5/6',
    'abs(-1) > 0',
    'min(5,abs(-9)) = max(3, 4, 5)',
]);

test('isTrue false-expressions', function (string $expression) {
    expect((new Math())->isTrue($expression))
        ->toBeFalse();
})->with([
    'simple grater then' => '1 > 2',
    'two less then' => '1 < 2 > 3',
    'grater or equals' => '1 >= 2',
    'less or equals' => '2 <= 1',
    'equals with =' => '1 = 2',
    'equals with ==' => '1 == 2',
    'equals with ===' => '1 === 2',
    'equals with many zeroes' => '1.0 == 1.000000000000001',
]);

test('isTrue Error', function (string $expression) {
    (new Math())->isTrue($expression);
})->throws(ValueError::class, 'Unsupported expression')
    ->with([
        'only one brace' => '(1 < 2',
        'extra brace' => '(1 < 2))',
        'unclosed brace' => '( (1 < 2)',
        'number with comma' => '1,1 < 2',
        'space between less then' => '1 < = 2',
        'letters' => '$a < 1',
        'exclamation' => '1!',
        'no right number' => '1<',
        'no right number equal' => '1 ==',
        'no right number - several comparisons' => '1<2<',
        ...range('a', 'z')
    ]);
