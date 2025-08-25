<?php
namespace Icso\Accounting\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'als_country';
    protected $guarded = [];
    public $timestamps = false;
}
