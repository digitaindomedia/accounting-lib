<?php

namespace Icso\Accounting\Enums;

class TypeEnum
{
    public const HAS_TAX = 'yes';
    public const DISCOUNT_TYPE_PERCENT = 'percent';
    public const DISCOUNT_TYPE_FIX = 'fix';
    public const TAX_TYPE_INCLUDE = 'include';
    public const TAX_TYPE_EXCLUDE = 'exclude';

    public const IS_LABA_RUGI = '1';
    public const IS_NERACA = '1';
    public const LABA_RUGI_TYPE_PENDAPATAN = 'pendapatan';
    public const LABA_RUGI_TYPE_BIAYA_OPERASIONAL = 'biaya_operasional';
    public const LABA_RUGI_TYPE_BIAYA_OTHER = 'biaya_other';

    public const FAKTUR_ACCEPTED = 'yes';

    public const DPP = 'dpp';
    public const PPN = 'ppn';
    public const TAX_TYPE = 'tax_type';
    public const TAX_SIGN = 'tax_sign';
    public const TAX_SIGN_TYPE_PUNGUT = 'plus';
    public const TAX_SIGN_TYPE_POTONG = 'minus';
}
