<?php

namespace Icso\Accounting\Enums;

enum OrderItemEnum: string
{
    case PACKAGE = "package";
    case ADDON = "addon";

    case DURATION_MONTH ='month';
    case DURATION_YEAR ='year';
    case PRICE_TYPE_MONTHLY ='monthly';
    case PRICE_TYPE_YEARLY ='yearly';
    public function toString(): string
    {
      return $this->value;
    }
}
