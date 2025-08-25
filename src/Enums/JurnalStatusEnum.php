<?php

namespace Icso\Accounting\Enums;

use Icso\Accounting\Facades\Html;
use Illuminate\Support\HtmlString;

class JurnalStatusEnum
{
    public const BELUM_LUNAS = '0';
    public const LUNAS = '1';
    public const OK = '1';
    public const BATAL = '404';

    public function toString($status): string
    {
        if($status == self::BELUM_LUNAS) {
            return __('status.invoice.BELUM_LUNAS');
        }
        else if($status == self::LUNAS) {
            return __('status.invoice.LUNAS');
        } else {
            return __('status.invoice.BATAL');
        }
    }

    public function toHtml($status): HtmlString
    {
        if($status == self::BELUM_LUNAS) {
            return Html::tag('span', __('status.invoice.BELUM_LUNAS'), ['class' => 'label-info status-label'])
                ->toHtml();
        }
        else if($status == self::LUNAS) {
            return Html::tag('span', __('status.invoice.LUNAS'), ['class' => 'label-success status-label'])
                ->toHtml();
        } else {
            return Html::tag('span', __('status.invoice.BATAL'), ['class' => 'label-danger status-label'])
                ->toHtml();
        }
    }
}
