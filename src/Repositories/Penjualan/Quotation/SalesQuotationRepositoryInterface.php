<?php

namespace Icso\Accounting\Repositories\Penjualan\Quotation;

interface SalesQuotationRepositoryInterface
{
    public function findOne($id, $select_field = []);

    public function getAllDataBy($search, $page, $perpage, array $where = [], $fromDate = null, $untilDate = null);

    public function getAllTotalDataBy($search, array $where = [], $fromDate = null, $untilDate = null);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function deleteAll(array $ids);
}
