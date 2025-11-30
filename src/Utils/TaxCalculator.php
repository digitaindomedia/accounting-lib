<?php

namespace Icso\Accounting\Utils;

use Icso\Accounting\Enums\TypeEnum;

class TaxCalculator
{
    public static function calcSingle(
        float $percentage,
        float $baseAmount,
        string $taxType,
        bool $isDppNilaiLain = false
    ): array {
        // wrapper saja di atas Helpers supaya gampang di-test
        if ($taxType === TypeEnum::TAX_TYPE_INCLUDE) {
            if ($isDppNilaiLain) {
                return Helpers::hitungIncludeTaxDppNilaiLain($percentage, $baseAmount);
            }
            return Helpers::hitungIncludeTax($percentage, $baseAmount);
        }

        // exclude
        if ($isDppNilaiLain) {
            return Helpers::hitungExcludeTaxDppNilaiLain($percentage, $baseAmount);
        }
        return Helpers::hitungExcludeTax($percentage, $baseAmount);
    }
}