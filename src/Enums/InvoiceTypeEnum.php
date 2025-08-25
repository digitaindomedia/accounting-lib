<?php

namespace Icso\Accounting\Enums;

enum InvoiceTypeEnum: string
{
    case ITEM= "item";
    case SERVICE= "service";
    public function toString() : string
    {
        return $this->value;
    }
}
