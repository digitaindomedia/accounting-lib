<?php

namespace Icso\Accounting\Enums;

use Illuminate\Support\HtmlString;

abstract class Enum
{
    protected $value = null;

    public function __toString()
    {
        return (string)$this->value;
    }

    public function setValue($val) {
        $this->value = $val;
    }

    public function toHtml()
    {
        return new HtmlString();
    }

}
