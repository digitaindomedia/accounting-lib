<?php

namespace Icso\Accounting\Repositories\Master\Product;


use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Repositories\ElequentRepository;

class ProductConvertionRepo extends ElequentRepository
{
    protected $model;

    public function __construct(ProductConvertion $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function convertToSmallestUnit($mark, $unit, $productId)
    {
        $convertionFactor = $this->getConversionToSmallest($unit,$productId);
        if(!empty($convertionFactor)){
            return $this->convertToSmallestUnit($mark * $convertionFactor->nilai, $convertionFactor->base_unit_id, $productId );
        } else {
            return $mark;
        }
    }

    public function getConversionToSmallest($unit,$productId)
    {
        $res = ProductConvertion::where(array('unit_id' => $unit, 'product_id' => $productId))->first();
        if(!empty($res)){
            return $res;
        } else {
            return 0;
        }
    }


}
