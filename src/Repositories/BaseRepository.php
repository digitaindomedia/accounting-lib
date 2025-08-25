<?php

namespace Icso\Accounting\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface BaseRepository
{
    public function findOne($id,$select_field = [], $with=[]);

    public function findWhere($where,$select_field = [], $with=[]);

    public function getAllDataBy($search, $page, $perpage, array $where = []);

    public function getAllTotalDataBy($search, array $where = []);

    public function create(array $data);

    public function store(Request $request, array $other = []);

    public function update(array $data, $id);

    public function delete($id);

    public function deleteByWhere($where);
    public function countByDate($fieldName, $fieldValue);

    public function findAllByWhere(array $where = [], array $orderby = [], $with=[]);
    public function findAllWithPaginate(array $where = [], array $orderby = [], $paginate=10, $with=[]);
    public static function generateCodeTransaction(Model $model, string $prefix,string $fieldName, string $fieldDate);
}
