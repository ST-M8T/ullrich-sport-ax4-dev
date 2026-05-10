<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders\ValueObjects;

final class PackageDimensions
{
    public function __construct(
        private readonly int $lengthMillimetres,
        private readonly int $widthMillimetres,
        private readonly int $heightMillimetres,
    ) {
        $this->assertPositive($lengthMillimetres, 'length');
        $this->assertPositive($widthMillimetres, 'width');
        $this->assertPositive($heightMillimetres, 'height');
    }

    public static function fromMillimetres(int $length, int $width, int $height): self
    {
        return new self($length, $width, $height);
    }

    public function length(): int
    {
        return $this->lengthMillimetres;
    }

    public function width(): int
    {
        return $this->widthMillimetres;
    }

    public function height(): int
    {
        return $this->heightMillimetres;
    }

    /**
     * @return array{length:int,width:int,height:int,unit:string}
     */
    public function toArray(string $unit = 'mm'): array
    {
        return [
            'length' => $this->length(),
            'width' => $this->width(),
            'height' => $this->height(),
            'unit' => $unit,
        ];
    }

    private function assertPositive(int $value, string $attribute): void
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'Package %s must be positive integer (got %d)',
                $attribute,
                $value
            ));
        }
    }
}
