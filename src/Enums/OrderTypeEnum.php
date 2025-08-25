<?php

namespace Icso\Accounting\Enums;

enum OrderTypeEnum: string
{
    case Trial = "trial";
    case RENEW = "renew";
    case UPGRADE = "upgrade";

    public function toString(): string
    {
        return $this->value;
    }
}
