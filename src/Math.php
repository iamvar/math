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
     * Calculates expression, using bcmath extension,
     * e.g. "1.1 + 1.2 * 3" will return "4.7"
     */
    public function calc(string $expression, bool $cutTrailingZeroes = true): string
    {
        // remove all whitespaces
        $expression = preg_replace('~\s+~', '', $expression);
        $expression = $this->replaceFloat($expression);
        $this->validate($expression);

        //No operator before brace - multiply, e.g. 2(1+1) = 2*(1+1) or (1+1)(1+1) = (1+1)*(1+1)
        $expression = preg_replace('~(\d|\))\(~', '$1*(', $expression);

        while (preg_match('~(?<!min|max|abs)\(.+\)~', $expression)) {
            $expression = preg_replace_callback(
                '~(?<!min|max|abs)\(([^()]+)\)~',
                fn(array $matches) => $this->calcExpressionInBraces($matches[1]),
                $expression
            );
        }

        $expression = $this->replaceFunctions($expression);

        $result = $this->calcExpressionInBraces($expression);

        $this->checkIsNumber($result);

        if ($cutTrailingZeroes) {
            $result = $this->trimTrailingZeroes($result);
        }

        if (bccomp($result, '0', $this->scale) === 0) {
            return '0';
        }

        //remove useless leading zeroes, e.g. 09.1 will become 9.1
        $result = ltrim($result, '0');
        if (str_starts_with($result, '.')) {
            $result = '0' . $result;
        }

        return $result;
    }

    private function calcExpressionInBraces(string $expression): string
    {
        if (str_starts_with($expression, '--')) {
            $expression = substr($expression, 2);
        }
        // first power, mod, multiply, divide. Then add, subtract
        $numberRegex = self::NUMBER_REGEXP;
        foreach (["\^", "%", "[*/]", "[+-]"] as $operations) {
            $regexp = "~($numberRegex)($operations)($numberRegex)~";
            while (preg_match($regexp, $expression)) {
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
        $matches = preg_split('~(>=|<=|<|>|={1,3})~', $expression, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!isset($matches[2])) {
            throw new \ValueError('Condition expression is missing');
        }

        $i = 1;
        while (isset($matches[$i])) {
            [$left, $operator, $right] = [$matches[$i - 1], $matches[$i], $matches[$i + 1] ?? null];

            if (!($right)) {
                throw new \ValueError("Condition after '$operator' is missing");
            }

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
        [$left, $operator, $right] = [$matches[1], $matches[2], $matches[3]];
        return match ($operator) {
            '*' => bcmul($left, $right, $this->scale),
            '/' => bcdiv($left, $right, $this->scale),
            '%' => bcmod($left, $right, $this->scale),
            '^' => $this->pow($left, $right),
            '+' => bcadd($left, $right, $this->scale),
            '-' => bcsub($left, $right, $this->scale),
        };
    }

    /**
     * Replaces float numbers in the expression
     * e.g. 2.1E-1 will be replaced with 0.21
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

    private function replaceFunctions(string $expression): string
    {
        while (preg_match('~(abs|min|max)(?<R>\((?:[^()]+|(?&R))*\))~', $expression, $matches)) {
            [$operation, $matchedExpression] = [$matches[1], $matches[2]];
            $matchedExpression = substr($matchedExpression, 1, -1);

            if ($operation === 'abs') {
                $replace = ltrim($this->calc($matchedExpression), '-');
                $expression = str_replace($matches[0], $replace, $expression);
                continue;
            }

            $expressionParts = explode(',', $matchedExpression);
            $expressionParts = array_map([$this, 'calc'], $expressionParts);
            $replace = match ($operation) {
                'min' => min($expressionParts),
                'max' => max($expressionParts),
            };

            $expression = str_replace($matches[0], $replace, $expression);
        }

        return $expression;
    }

    private function validate(string $expression): void
    {
        if (substr_count($expression, '(') !== substr_count($expression, ')')) {
            throw new \ValueError('Uneven Braces');
        }
    }
}
