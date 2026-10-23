<?php

declare(strict_types=1);

namespace App\Domain\Questions;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A non-negative number of points with exactly two decimals ("1.50").
 *
 * All point arithmetic goes through this class (brick/math BigDecimal), never
 * through floats: there is no float input, and fractions (partial credit) are
 * computed exactly and rounded once, half up, to two decimals.
 */
final readonly class Points implements JsonSerializable, Stringable
{
    public const int SCALE = 2;

    private function __construct(public BigDecimal $value) {}

    /**
     * Exact value: "1.5", "2", 3 or a BigNumber. More than two decimals, a
     * negative value or a non-number is rejected (never silently rounded).
     *
     * @throws InvalidArgumentException
     */
    public static function of(BigNumber|int|string $value): self
    {
        try {
            $decimal = BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::Unnecessary);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Points must be a number with at most '.self::SCALE.' decimals.', previous: $exception);
        }

        if ($decimal->isNegative()) {
            throw new InvalidArgumentException('Points can not be negative.');
        }

        return new self($decimal);
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    public function plus(self $other): self
    {
        return new self($this->value->plus($other->value));
    }

    /**
     * @throws InvalidArgumentException if the result would be negative
     */
    public function minus(self $other): self
    {
        return self::of($this->value->minus($other->value));
    }

    /**
     * This many points times numerator/denominator, computed exactly and then
     * rounded half up to two decimals. A negative ratio counts as 0 (partial
     * credit never goes below zero); a ratio above 1 is allowed (bonus points).
     *
     * @throws InvalidArgumentException if the denominator is not positive
     */
    public function timesFraction(int $numerator, int $denominator): self
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('The denominator must be positive.');
        }

        if ($numerator <= 0) {
            return self::zero();
        }

        $exact = BigRational::of($this->value)->multipliedBy(BigRational::ofFraction($numerator, $denominator));

        return new self($exact->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public function min(self $other): self
    {
        return $this->value->isLessThanOrEqualTo($other->value) ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function equals(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value->isGreaterThan($other->value);
    }

    public function compareTo(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    /**
     * Always two decimals: "0.00", "1.50", "12.00".
     */
    public function toString(): string
    {
        return $this->value->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Serialized as a string, so no JSON consumer turns it into a float.
     */
    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}
