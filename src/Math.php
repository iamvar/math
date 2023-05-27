<?php

declare(strict_types=1);

namespace Iamvar;

class Math
{
    private const DEFAULT_PRECISION = 15;
    private const NUMBER_REGEXP = '\d+(?:\.\d+)?';

    private readonly ?int $scale;

    public function __construct(?int $scale = null)
    {
        $this->scale = bcscale() === 0 ? self::DEFAULT_PRECISION : $scale;
    }

    /**
     * Calculates expression with bcmath extension,
     * e.g. "1 + 1.2 * 3" will return "4.6"
     */
    public function calc(string $expression, bool $cutTrailingZeroes = true): string
    {
        $expression = preg_replace('~\s+~', '', $expression);

        while (str_contains($expression, '(')) {
            $expression = preg_replace_callback(
                '~\(([^()]+)\)~',
                fn(array $matches) => $this->calcExpressionWithoutBraces($matches[1]),
                $expression
            );
        }
        $result = $this->calcExpressionWithoutBraces($expression);

        if ($cutTrailingZeroes && str_contains($result, '.')) {
            return rtrim(rtrim($result, '0'), '.');
        }

        return $result;
    }

    private function calcExpressionWithoutBraces(string $expression): string
    {
        // first multiply, divide, power, mod
        $numberRegex = self::NUMBER_REGEXP;
        while (preg_match("~[*/^%]~", $expression, $m)) {
            $expression = preg_replace_callback(
                "~($numberRegex)([*/^%])($numberRegex)~",
                [$this, 'bcOperation'],
                $expression
            );
        }

        // then add and substract
        while (preg_match("~[+-]~", $expression, $m)) {
            $expression = preg_replace_callback(
                "~($numberRegex)([+-])($numberRegex)~",
                [$this, 'bcOperation'],
                $expression
            );
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
        $expression = preg_replace('~\s+~', '', $expression);

        $matches = preg_split('~(<|>|={1,3})~', $expression, -1, PREG_SPLIT_DELIM_CAPTURE);

        $i = 1;
        while (isset($matches[$i])) {
            [$left, $operator, $right] = [$matches[$i - 1], $matches[$i], $matches[$i + 1]];
            $compare = match ($operator) {
                '>' => 1,
                '<' => -1,
                '=', '==', '===' => 0,
            };

            if (bccomp($left, $right, $this->scale) !== $compare) {
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
            '^' => bcpow($left, (string)(int)$right, $this->scale),
            '+' => bcadd($left, $right, $this->scale),
            '-' => bcsub($left, $right, $this->scale),
        };
    }
}
