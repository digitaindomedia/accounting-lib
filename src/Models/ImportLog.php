<?php

namespace Icso\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $table = 'als_import_log';
    protected $guarded = [];
    public $timestamps = false;

    public function details()
    {
        return $this->hasMany(ImportLogDetail::class, 'import_log_id');
    }
}
