<?php

namespace Icso\Accounting\Traits;


use Icso\Accounting\Utils\Helpers;

trait CreatedByName
{
    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

}
