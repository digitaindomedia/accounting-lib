<?php
namespace Icso\Accounting\Enums;

use Icso\Accounting\Facades\Html;
use Illuminate\Support\HtmlString;

class PaymentStatusEnum{
    public const OK = '1';
    public const BATAL = '404';

    public function toString($status): string
    {
        if($status == self::OK) {
            return __('status.payment.OK');
        }
        else {
            return __('status.payment.BATAL');
        }
    }

    public function toHtml($status): HtmlString
    {
        if($status == self::OK) {
            return Html::tag('span', __('status.payment.OK'), ['class' => 'label-success status-label'])
                ->toHtml();
        } else {
            return Html::tag('span', __('status.payment.BATAL'), ['class' => 'label-danger status-label'])
                ->toHtml();
        }
    }
}
