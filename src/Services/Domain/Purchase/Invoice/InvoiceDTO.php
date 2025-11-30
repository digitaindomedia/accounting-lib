<?php

namespace Icso\Accounting\Services\Domain\Purchase\Invoice;

use Illuminate\Http\Request;
use Icso\Accounting\Utils\Utility;

class InvoiceDTO
{
    public string $id;
    public string $invoice_no;
    public string $invoice_date;
    public string $due_date;
    public string $tax_type;
    public string $discount_type;
    public string $invoice_type;
    public string $input_type;
    public string $vendor_id;
    public string $order_id;
    public string $warehouse_id;
    public float  $subtotal;
    public float  $dpp_total;
    public float  $discount_total;
    public float  $tax_total;
    public float  $grandtotal;
    public string $note;
    public array  $items = [];
    public array  $dp = [];
    public array  $receives = [];

    public function __construct(array $data)
    {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public static function fromRequest(Request $request): self
    {
        return new self([
            'id'             => (string) ($request->id ?? ''),
            'invoice_no'     => $request->invoice_no, // diasumsikan sudah di-generate di controller / repo
            'invoice_date'   => Utility::changeDateFormat($request->invoice_date),
            'due_date'       => $request->due_date ? Utility::changeDateFormat($request->due_date) : date('Y-m-d'),
            'tax_type'       => $request->tax_type ?? '',
            'discount_type'  => $request->discount_type ?? '',
            'invoice_type'   => $request->invoice_type,
            'input_type'     => $request->input_type,
            'vendor_id'      => (string) $request->vendor_id,
            'order_id'       => (string) ($request->order_id ?? '0'),
            'warehouse_id'   => (string) ($request->warehouse_id ?? '0'),
            'subtotal'       => (float) Utility::remove_commas($request->subtotal ?? 0),
            'dpp_total'      => (float) ($request->dpp_total ? Utility::remove_commas($request->dpp_total) : 0),
            'discount_total' => (float) ($request->discount_total ? Utility::remove_commas($request->discount_total) : 0),
            'tax_total'      => (float) ($request->tax_total ? Utility::remove_commas($request->tax_total) : 0),
            'grandtotal'     => (float) Utility::remove_commas($request->grandtotal ?? 0),
            'note'           => $request->note ?? '',
            'items'          => $request->orderproduct ?? [],
            'dp'             => $request->dp ?? [],
            'receives'       => $request->receive ?? [],
        ]);
    }
}