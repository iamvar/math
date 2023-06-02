<?php

declare(strict_types=1);

namespace Iamvar;

class Math
{
    private const DEFAULT_PRECISION = 15;
    private const NUMBER_REGEXP = '-?\d+(?:\.\d+)?';

    private readonly int $scale;

    public function __construct(?int $scale = null)
    {
        if ($scale === null) {
            $scale = bcscale() === 0 ? self::DEFAULT_PRECISION : bcscale();
        }

        $this->scale = $scale;
    }

    /**
     * Calculates expression with bcmath extension,
     * e.g. "1 + 1.2 * 3" will return "4.6"
     */
    public function calc(string $expression, bool $cutTrailingZeroes = true): string
    {
        $expression = $this->prepare($expression);

        while (preg_match('~\(.*\)~', $expression)) {
            $expression = preg_replace_callback(
                '~\(([^()]+)\)~',
                fn(array $matches) => $this->calcExpressionWithoutBraces($matches[1]),
                $expression
            );
        }

        $result = $this->calcExpressionWithoutBraces($expression);

        $this->checkIsNumber($result);

        if ($cutTrailingZeroes) {
            return $this->trimTrailingZeroes($result);
        }

        return $result;
    }

    private function calcExpressionWithoutBraces(string $expression): string
    {
        // first multiply, divide, power, mod. Then add, subtract
        $numberRegex = self::NUMBER_REGEXP;
        foreach (["[*/^%]", "[+-]"] as $operations) {
            $regexp = "~($numberRegex)($operations)($numberRegex)~";
            while (preg_match($regexp, $expression, $m)) {
                $expression = preg_replace_callback(
                    $regexp,
                    [$this, 'bcOperation'],
                    $expression
                );
            }
        }

        return $expression;
    }

    /**
     * Checks if expression is true,
     * e.g. "1.2 * 3 == 3.6" will return true
     * as well as 1 < 2 < (1 - 3 + 5)
     * Comparison is done with bccomp
     */
    public function isTrue(string $expression): bool
    {
        $expression = $this->prepare($expression);

        $matches = preg_split('~(>=|<=|<|>|={1,3})~', $expression, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!isset($matches[2])) { //check we have right operand
            throw new \ValueError('Unsupported expression');
        }

        $i = 1;
        while (isset($matches[$i])) {
            [$left, $operator, $right] = [$matches[$i - 1], $matches[$i], $matches[$i + 1]];
            $left = $this->calc($left);
            $right = $this->calc($right);

            $bccomp = bccomp($left, $right, $this->scale);
            $passCompare = match ($operator) {
                '>=' => $bccomp >= 0,
                '<=' => $bccomp <= 0,
                '>' => $bccomp === 1,
                '<' => $bccomp === -1,
                '=', '==', '===' => $bccomp === 0,
            };

            if (!$passCompare) {
                return false;
            }

            $i += 2;
        }

        return true;
    }

    private function bcOperation(array $matches): string
    {
        [$left, $right] = [$matches[1], $matches[3]];
        return match ($matches[2]) {
            '*' => bcmul($left, $right, $this->scale),
            '/' => bcdiv($left, $right, $this->scale),
            '%' => bcmod($left, $right, $this->scale),
            '^' => $this->pow($left, $right),
            '+' => bcadd($left, $right, $this->scale),
            '-' => bcsub($left, $right, $this->scale),
        };
    }

    private function prepare(string $expression): string
    {
        // remove all whitespaces
        $expression = preg_replace('~\s+~', '', $expression);

        return $this->replaceFloat($expression);
    }

    /**
     * replace float numbers, like 2.1E-1 with 0.21
     */
    private function replaceFloat(string $expression): string
    {
        return preg_replace_callback(
            '~\d+(?:\.\d+)?E[-+]?\d+~',
            function (array $matches) {
                [$num, $exponent] = explode('E', $matches[0]);

                if ($exponent === '0') {
                    return $num;
                }

                return bcmul($num, bcpow('10', $exponent, $this->scale), $this->scale);
            },
            $expression
        );
    }

    private function checkIsNumber(string $subject): void
    {
        $numberRegExp = self::NUMBER_REGEXP;
        if (!preg_match("~^$numberRegExp$~", $subject)) {
            throw new \ValueError('Unsupported expression');
        }
    }

    /**
     * bcpow supports only integer $exponent o_O
     * @link https://stackoverflow.com/questions/33486170/php-how-to-raise-number-to-tiny-fractional-exponent
     */
    public function pow(string $number, string $exponent): string
    {
        if (bccomp($number, '0', $this->scale) === 0) {
            return '0';
        }

        [$integerPart, $fractionalPart] = explode('.', "$exponent.0");
        $result = bcpow($number, $integerPart, $this->scale);

        if ($fractionalPart !== '0') {
            $exponent = $this->trimTrailingZeroes('0.' . $fractionalPart);
            $float = (float)$number ** (float)$exponent;
            $minorPart = $this->replaceFloat((string)($float));
            $result = bcmul($result, $minorPart, $this->scale);
        }

        return $result;
    }

    private function trimTrailingZeroes(string $number): string
    {
        if (str_contains($number, '.')) {
            return rtrim(rtrim($number, '0'), '.');
        }

        return $number;
    }
}
