<?php

namespace Icso\Accounting\Repositories;

use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class ElequentRepository implements BaseRepository
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function findOne($id, $select_field = [], $with = [])
    {
        // TODO: Implement findOne() method.
        return $this->findWhere(array('id' => $id),$select_field, $with);
    }

    public function findWhere($where, $select_field = [], $with = [])
    {
        // TODO: Implement findWhere() method.
        if (is_array($select_field) AND count($select_field)) {
            return $this->model::select($select_field)->where($where)->when(!empty($with), function ($query) use($with){$query->with($with);})->first();
        } else{
            return $this->model::where($where)->when(!empty($with), function ($query) use($with){$query->with($with);})->first();
        }
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
    }

    public function create(array $data)
    {
        // TODO: Implement create() method.
        $res = new $this->model;
        $res->fill($data)->save();
        return $res;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
    }

    public function update(array $data, $id)
    {
        // TODO: Implement update() method.
        $query = $this->model::findOrFail($id);
        $res = $query->update($data);
        return $res;
    }

    public function delete($id)
    {
        // TODO: Implement delete() method.
        return $this->deleteByWhere(array('id' => $id));
    }

    public function deleteByWhere($where)
    {
        // TODO: Implement deleteByWhere() method.
        $res = $this->model::where($where)->delete();
        return $res;
    }

    public function countByDate($fieldName, $fieldValue)
    {
        // TODO: Implement countByDate() method.
        $count = $this->model::whereDate($fieldName, $fieldValue)->get()->count();
        return $count;
    }

    public function findAllByWhere(array $where = [], array $orderby = [], $with = [])
    {
        // TODO: Implement findAllByWhere() method.
        $dataModel = new $this->model;
        $dataSet = $dataModel->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })
            ->when(!empty($orderby), function ($query) use($orderby){
                $pairs = array_chunk($orderby, 2);
                foreach ($pairs as $pair) {
                    $query->orderBy(...$pair);
                }
            })
            ->when(!empty($with), function ($query) use($with){$query->with($with);});
        return $dataSet->get();
    }

    public function findAllWithPaginate(array $where = [], array $orderby = [], $paginate=10, $with = [])
    {
        // TODO: Implement findAllWithPaginate() method.
        $dataModel = new $this->model;
        $dataSet = $dataModel->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })
            ->when(!empty($orderby), function ($query) use($orderby){
                $pairs = array_chunk($orderby, 2);
                foreach ($pairs as $pair) {
                    $query->orderBy(...$pair);
                }
            })
            ->when(!empty($with), function ($query) use($with){$query->with($with);});
        return $dataSet->simplePaginate($paginate);
    }

    public static function generateCodeTransaction(Model $model, string $prefix, string $fieldName, string $fieldDate)
    {
        // TODO: Implement generateCodeTransaction() method.
        $prefix = SettingRepo::getOptionValue($prefix);
        $string = '';
        if(SettingRepo::getOption('reset_number') == SettingEnum::RESET_NUMBER_YEAR){
            $jumlahData = $model::whereYear($fieldDate, date('Y'))->count();
        } else {
            $jumlahData = $model::whereMonth($fieldDate, date('m'))->count();
        }
        $values = $model::pluck($fieldName)
            ->filter(fn ($item) => ! is_null($item))
            ->map(fn ($item) => Str::upper($item));
        $prefix = str_replace("{tahun}",date('Y'),$prefix);
        $prefix = str_replace("{bulan}",date('m'),$prefix);
        $prefix = str_replace("{tanggal}",date('d'),$prefix);
        while(true){
            $jumlahData = $jumlahData + 1;
            $string = str_replace("{inc}",str_pad($jumlahData, 4, "0", STR_PAD_LEFT),$prefix);
            if (! $values->contains($string)) {
                break;
            }
        }
        return $string;
    }
}
