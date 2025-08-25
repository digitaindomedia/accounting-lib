<?php

namespace Icso\Accounting\Models\Master;

use Illuminate\Database\Eloquent\Model;

class TaxGroup extends Model
{
    protected $table = 'als_tax_group';
    protected $guarded = [];
    public $timestamps = false;

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
