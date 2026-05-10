<?php

namespace App\Domain\Fulfillment\Orders\ValueObjects;

final class ChargeableWeight
{
    private function __construct(
        private readonly ?float $actualWeightKg,
        private readonly ?float $volumetricWeightKg,
    ) {}

    public static function fromWeights(?float $actual, ?float $volumetric): self
    {
        return new self(
            $actual !== null ? max(0.0, $actual) : null,
            $volumetric !== null ? max(0.0, $volumetric) : null,
        );
    }

    public function actual(): ?float
    {
        return $this->actualWeightKg;
    }

    public function volumetric(): ?float
    {
        return $this->volumetricWeightKg;
    }

    public function value(): ?float
    {
        if ($this->actualWeightKg === null && $this->volumetricWeightKg === null) {
            return null;
        }

        if ($this->actualWeightKg === null) {
            return $this->volumetricWeightKg;
        }

        if ($this->volumetricWeightKg === null) {
            return $this->actualWeightKg;
        }

        return max($this->actualWeightKg, $this->volumetricWeightKg);
    }
}
