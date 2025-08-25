<?php

namespace Icso\Accounting\Utils;

class VarType
{
    const PENAMBAHAN = "penambahan";
    const PENGURANGAN = "pengurangan";
    const PELUNASAN = "pelunasan";

    const SHORTCODE_YEAR = "{tahun}";
    const SHORTCODE_MONTH = "{bulan}";
    const SHORTCODE_MONTH_ROMAWI = "{bulan_romawi}";
    const SHORTCODE_DATE = "{tanggal}";

    const TAX_TYPE_SINGLE = 'single';
    const TAX_TYPE_GROUP = 'group';
    const TAX_SIGN_PENAMBAH = 'plus';
    const TAX_SIGN_PEMOTONG = 'minus';

    const TRANSACTION_DATE = 'transaction_date';
    const TRANSACTION_TYPE = 'transaction_type';
    const TRANSACTION_NO = 'transaction_no';
    const TRANSACTION_ID = 'transaction_id';
    const ADJUSTMENT_TYPE_QTY = 'qty';
    const ADJUSTMENT_TYPE_VALUE = 'value';

    const CONNECT_DB_SUPPLIER = '1';
    const CONNECT_DB_CUSTOMER = '2';
    const CONNECT_DB_CUSTOM = '3';
    const CONNECT_DB_PERSEDIAAN = '4';

    const SUSUTKAN_SEKARANG = '1';
    const KEY_DEFAULT_CUSTOMER = 'DEFAULT_CUSTOMER';
    const KEY_DEFAULT_WAREHOUSE = 'DEFAULT_WAREHOUSE';
    const COA_POSITION_DEBET = 'debet';
    const COA_POSITION_KREDIT = 'kredit';

    const DISCOUNT_TYPE_PERCENT = 'percent';
    const DISCOUNT_TYPE_FIX = 'fix';

    const MUTATION_TYPE_IN = 'mutation_in';
    const MUTATION_TYPE_OUT = 'mutation_out';
}
