<?php


namespace Icso\Accounting\Repositories\Pembelian\Received;


use App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceivedProduct;
use App\Repositories\ElequentRepository;
use App\Repositories\Tenant\Pembelian\Received\Interface\ReceiveProductInterface;
use App\Utils\Utility;
use Illuminate\Http\Request;

class ReceiveProductRepo extends ElequentRepository
{

    protected $model;

    public function __construct(PurchaseReceivedProduct $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }


    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $orderProductId = $request->order_product_id;
        $qty = $request->qty;
        $price = Utility::remove_commas($request->price);
        $taxId = $request->tax_id;
        $discountAmount = $request->discount;
        $discountType = $request->discount_type;

    }
}
