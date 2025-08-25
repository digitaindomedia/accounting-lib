<?php

namespace Icso\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    protected $table = 'als_log';
    protected $guarded = [];
    public $timestamps = false;

}
