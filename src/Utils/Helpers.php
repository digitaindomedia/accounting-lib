<?php

namespace Icso\Accounting\Utils;


use Exception;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\User;
use Icso\Accounting\Models\UserInfo;
use Icso\Accounting\Repositories\Master\TaxRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;

class Helpers
{
    public static function hitungProporsi($totalAtas, $totalBawah, $diskonFix)
    {
        $bagi = $totalAtas / $totalBawah;
        $total = $diskonFix * $bagi;
        return $total;
    }

    public static function hitungIncludeTax($persenTax, $nomimal){
        $addPpn = (100 + $persenTax) / 100;
        $divPpn = $persenTax / 100;
        $dpp = $nomimal / $addPpn;
        $ppn = $dpp * $divPpn;
        return array(
            TypeEnum::DPP => $dpp,
            TypeEnum::PPN => $ppn
        );
    }

    public static function hitungIncludeTaxDppNilaiLain($persenTax, $nomimal){

        $hitungDpp = $nomimal/1.11;
        $dppNilaiLain = $hitungDpp * (11/$persenTax);
        $hitungPpn = $dppNilaiLain * ($persenTax/100);
        return array(
            TypeEnum::DPP => $dppNilaiLain,
            TypeEnum::PPN => $hitungPpn
        );
    }

    public static function hitungExcludeTaxDppNilaiLain($persenTax, $nomimal){
        $dppNilaiLain = $nomimal * (11/$persenTax);
        $hitungPpn = $dppNilaiLain * ($persenTax/100);
        return array(
            TypeEnum::DPP => $dppNilaiLain,
            TypeEnum::PPN => $hitungPpn
        );
    }

    public static function hitungExcludeTax($persenTax, $nomimal){
        $hitungPpn = ($persenTax/100) * $nomimal;
        return array(
            TypeEnum::DPP => $nomimal,
            TypeEnum::PPN => $hitungPpn
        );
    }

    public static function hitungTaxDpp($subtotal, $taxId, $taxType, $taxPercentage,$taxGroup=''): array|string
    {
        $taxRepo = new TaxRepo(new Tax());
        $findTax = $taxRepo->findOne($taxId,array(),['taxgroup','taxgroup.tax']);
        $getData = "";
        if(!empty($findTax)){
            if($findTax->tax_type == VarType::TAX_TYPE_SINGLE){
                if($taxType == TypeEnum::TAX_TYPE_INCLUDE){
                    $getData = self::hitungIncludeTax($taxPercentage, $subtotal);
                    $getData[TypeEnum::TAX_TYPE] = VarType::TAX_TYPE_SINGLE;
                    $getData[TypeEnum::TAX_SIGN] = $findTax->tax_sign;
                    $getData['purchase_coa_id'] = $findTax->purchase_coa_id;
                    $getData['sales_coa_id'] = $findTax->sales_coa_id;
                } else {
                    $ppn = ($taxPercentage/100) * $subtotal;
                    $getData = array(
                        TypeEnum::DPP => $subtotal,
                        TypeEnum::PPN => $ppn,
                        TypeEnum::TAX_TYPE => VarType::TAX_TYPE_SINGLE,
                        TypeEnum::TAX_SIGN => $findTax->tax_sign,
                        'purchase_coa_id' => $findTax->purchase_coa_id,
                        'sales_coa_id' => $findTax->sales_coa_id
                    );
                }
            } else {
                if(empty($taxGroup)){
                    if(!empty($findTax->taxgroup)){
                        if(count($findTax->taxgroup) > 0){
                            $taxGroup = $findTax->taxgroup;
                            $ppn = 0;
                            $dpp = $subtotal;
                            $purchaseCoaId = array();
                            $salesCoaId = array();
                            foreach ($taxGroup as $item){
                                $tx = $item->tax;
                                if($taxType == TypeEnum::TAX_TYPE_INCLUDE){
                                    $getData = self::hitungIncludeTax($taxPercentage, $subtotal);
                                    if($tx->tax_sign == VarType::TAX_SIGN_PENAMBAH){
                                        $ppn = $ppn + $getData[TypeEnum::PPN];
                                    } else {
                                        $ppn = $ppn - $getData[TypeEnum::PPN];
                                    }
                                    $dpp = $dpp - $ppn;
                                } else {
                                    $hitungPpn = ($taxPercentage/100) * $subtotal;
                                    if($tx->tax_sign == VarType::TAX_SIGN_PENAMBAH){
                                        $ppn = $ppn + $hitungPpn;
                                    } else {
                                        $ppn = $ppn - $hitungPpn;
                                    }

                                }
                                $purchaseCoaId[] = $tx->purchase_coa_id;
                                $salesCoaId[] = $tx->sales_coa_id;

                            }
                            $getData = array(
                                TypeEnum::DPP => $dpp,
                                TypeEnum::PPN => $ppn,
                                TypeEnum::TAX_TYPE => VarType::TAX_TYPE_GROUP,
                                'purchase_coa_id' => $purchaseCoaId,
                                'sales_coa_id' => $salesCoaId
                            );
                        }
                    }
                }
                else{
                    //jika terjadi edit / detail / data sdh tersimpan biar persentase pajak tidak gerak
                    $decTaxGroup = json_decode($taxGroup);
                    if(!empty($decTaxGroup)){
                        $ppn = 0;
                        $dpp = $subtotal;
                        $purchaseCoaId = array();
                        $salesCoaId = array();
                        foreach ($decTaxGroup as $item){
                            if($taxType == TypeEnum::TAX_TYPE_INCLUDE){
                                $getData = self::hitungIncludeTax($item->tax_percentage, $subtotal);
                                if($item->tax_sign == VarType::TAX_SIGN_PENAMBAH){
                                    $ppn = $ppn + $getData[TypeEnum::PPN];
                                } else {
                                    $ppn = $ppn - $getData[TypeEnum::PPN];
                                }
                                $dpp = $dpp - $ppn;
                            } else {
                                $hitungPpn = ($item->tax_percentage/100) * $subtotal;
                                if($item->tax_sign == VarType::TAX_SIGN_PENAMBAH){
                                    $ppn = $ppn + $hitungPpn;
                                } else {
                                    $ppn = $ppn - $hitungPpn;
                                }

                            }
                            $purchaseCoaId[] = $item->purchase_coa_id;
                            $salesCoaId[] = $item->sales_coa_id;
                        }
                        $getData = array(
                            TypeEnum::DPP => $dpp,
                            TypeEnum::PPN => $ppn,
                            TypeEnum::TAX_TYPE => VarType::TAX_TYPE_GROUP,
                            'purchase_coa_id' => $purchaseCoaId,
                            'sales_coa_id' => $salesCoaId
                        );
                    }
                }

            }
        }
        return $getData;
    }

    public static function hitungSubtotal($qty,$price,$discount,$discountType): mixed
    {
        $subtotal = $qty * $price;
        if(!empty($discount)){
            if($discountType == TypeEnum::DISCOUNT_TYPE_PERCENT){
                $discount = ($discount/100) * $subtotal;
            }
        }
        $subtotal = $subtotal - $discount;
        return $subtotal;
    }

    public static function hitungGrandTotal($subtotal,$discount,$discountType): mixed
    {
        if(!empty($discount)){
            if($discountType == TypeEnum::DISCOUNT_TYPE_PERCENT){
                $discount = ($discount/100) * $subtotal;
            }
        }
        $subtotal = $subtotal - $discount;
        return ['grandtotal' => $subtotal, 'discount' => $discount];
    }

    public static function getNamaUser($idUser)
    {
        $res = tenancy()->central(function ($tenant) use($idUser) {
            $resDataUser = User::where(array('id' => $idUser))->first();
            $namaUser = "";
            if(!empty($resDataUser)) {
                $namaUser = $resDataUser->name;
            }
            return $namaUser;
        });
        return $res;
    }

    public static function getTenantId($idUser)
    {
        $res = tenancy()->central(function ($tenant) use($idUser) {
            $resDataUser = User::where(array('id' => $idUser))->first();
            $tenantId = "";
            if(!empty($resDataUser)) {
                $tenantId = $resDataUser->tenant_id;
            }
            return $tenantId;
        });
        return $res;
    }

    public static function formatDateExcel($excelDate)
    {

        try {
            $unix_date = ($excelDate - 25569) * 86400;
            $excelDate = 25569 + ($unix_date / 86400);
            $unix_date = ($excelDate - 25569) * 86400;
            $res = gmdate("Y-m-d", $unix_date);
        }
        catch (Exception $exception){
            $res = $excel_date;
        }

        return $res;
    }

    public static function hasMoreData(int $total, int $page, $data): bool
    {
        return $total > ($page + count($data));
    }

    public static function getDetailTenant()
    {
        $res = tenancy()->central(function ($tenant) {
            $resDataUser = User::where(array('tenant_id' => $tenant->id))->first();
            $resUserInfo = UserInfo::where('tenant_id', $resDataUser->tenant_id)->first();
            return array(
                'user' => $resDataUser,
                'user_info' => $resUserInfo,
            );
        });
        return $res;
    }

    public static function getDiscountString($discount,$discountType)
    {
        $discount= number_format($discount, SettingRepo::getSeparatorFormat());
        if($discountType == VarType::DISCOUNT_TYPE_PERCENT){
            $discount = $discount . "%";
        }
        return $discount;
    }

    public static function getTaxName($taxId,$taxPercentage,$taxGroup='')
    {
        $findTax = Tax::where('id',$taxId)->first();
        if(!empty($findTax)){
            return $findTax->tax_name." (".$taxPercentage."%)";
        }
        return "";
    }

    public static function sumTotalsByTaxId($data)
    {
        // Initialize an empty array to hold the summed totals
        $result = [];

        foreach ($data as $item) {
            $id = $item['id'];

            // Check if the id already exists in the result array
            if (isset($result[$id])) {
                // If it exists, add the current total to the existing total
                $result[$id]['total'] += $item['total'];
            } else {
                // If it does not exist, add a new entry to the result array
                $result[$id] = [
                    'id' => $id,
                    'name' => $item['name'],
                    'total' => $item['total']
                ];
            }
        }

        // Re-index the result array (optional)
        return array_values($result);
    }

    public static function getJsonTaxGroup($taxId)
    {
        $taxRepo = new TaxRepo(new Tax());
        $jsonTaxGroup = "";
        $findTaxIsGroup = $taxRepo->findOne($taxId,array(),['taxgroup','taxgroup.tax']);
        if(!empty($findTaxIsGroup)){
            if($findTaxIsGroup->tax_type == VarType::TAX_TYPE_GROUP){
                if(!empty($findTaxIsGroup->taxgroup)) {
                    if (count($findTaxIsGroup->taxgroup) > 0) {
                        $taxGroup = $findTaxIsGroup->taxgroup;
                        $arrTax = [];
                        foreach ($taxGroup as $item) {
                            $tx = $item->tax;
                            $arrTax[] = array(
                                'id' => $tx->id,
                                'tax_name' => $tx->tax_name,
                                'tax_percentage' => $tx->tax_percentage,
                                'tax_description' => $tx->tax_description,
                                'tax_periode' => $tx->tax_periode,
                                'user_id' => $tx->created_by,
                                'tax_sign' => $tx->tax_sign,
                                'tax_type' => $tx->tax_type,
                                'purchase_coa_id' => $tx->purchase_coa_id,
                                'sales_coa_id' => $tx->sales_coa_id
                            );
                        }
                        if (!empty($arrTax)) {
                            $jsonTaxGroup = json_encode($arrTax);
                        }
                    }
                }
            }
        }
        return $jsonTaxGroup;
    }
}
