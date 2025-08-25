<?php

namespace Icso\Accounting\Repositories\Akuntansi\SaldoAwal\Interface;

use Icso\Accounting\Repositories\BaseRepository;
use Illuminate\Http\Request;

interface SaldoAwalInterface extends BaseRepository
{
    public function deleteAdditionalData($id);
    public function storeSaldoAkun(Request $request);
}
