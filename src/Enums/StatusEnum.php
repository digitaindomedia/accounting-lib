<?php

namespace Icso\Accounting\Enums;

class StatusEnum
{
    public const BELUM_LUNAS = 'belum lunas';
    public const LUNAS = 'lunas';
    public const BATAL = 'batal';

    public const OPEN = "open";
    public const BAYAR_SEBAGIAN = "bayar sebagian";
    public const DRAFT = "selesai";

    public const PENDING = 'pending';
    public const PARSIAL_PENERIMAAN = 'parsial penerimaan';
    public const PARSIAL_ORDER = 'parsial order';
    public const PENERIMAAN = 'penerimaan';
    public const INVOICE = 'invoice';
    public const PARSIAL_INVOICE = 'parsial invoice';
    public const SETUJUI = "setujui";
    public const SELESAI = "selesai";

    public const FAKTUR_DITERIMA = 'ya';
    public const FAKTUR_TIDAK_DITERIMA = 'tidak';

    public const PARSIAL_DELIVERY = 'parsial pengiriman';
    public const DELIVERY = 'pengiriman';
    public const TERJUAL = 'terjual';
    public const AKTIF = '1';
    public const TIDAK_AKTIF = '0';
}
