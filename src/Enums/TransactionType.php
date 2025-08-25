<?php

namespace Icso\Accounting\Enums;

class TransactionType
{
    public const PURCHASE_ORDER = 'Order Pembelian';
    public const PURCHASE_DP = 'Uang Muka Pembelian';
    public const PURCHASE_RECEIVE = 'Penerimaan Pembelian';
    public const PURCHASE_INVOICE = 'Invoice Pembelian';
    public const PURCHASE_PAYMENT = 'Pelunasan Pembelian';
    public const PURCHASE_RETUR = 'Retur Pembelian';
}
