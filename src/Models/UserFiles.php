<?php

namespace Icso\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class UserFiles extends Model
{
    protected $table = 'als_user_files';
    protected $guarded = [];
    public $timestamps = false;
}
