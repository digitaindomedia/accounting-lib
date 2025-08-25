<?php
namespace Icso\Accounting\Utils;

use App\Models\Tenant\Setting;
use App\Repositories\Tenant\Utils\SettingRepo;
use Illuminate\Database\Eloquent\Model;

class Utility
{
    public static function getDistance($latitude1, $longitude1, $latitude2, $longitude2) {
        $earth_radius = 6371;

        $dLat = deg2rad($latitude2 - $latitude1);
        $dLon = deg2rad($longitude2 - $longitude1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        $d = $earth_radius * $c;

        return $d;
    }

    public static function lastDateMonth()
    {
        $now_date = date("Y-m-d");
        $last_date = date("Y-m-t", strtotime($now_date));
        return $last_date;
    }

    public static function generateRandomString($length = 4, $type='alpha-numeric') {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if($type == 'number') {
            $characters = '0123456789';
        }
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function remove_commas($str)
    {
        $ret = str_replace(',','',$str);
        return $ret;
    }

    public static function randomHex() {
        $chars = 'ABCDEF0123456789';
        $color = '#';
        for ( $i = 0; $i < 6; $i++ ) {
            $color .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $color;
    }

    public static function convert_tanggal($tanggal, $style = 'panjang')
    {
        $exp = explode(" ",$tanggal);

        $tgl = '';
        if(count($exp) > 1)
        {
            $arr = explode("-",$exp[0]);
            $tgl = $arr[2]." ".self::bulan_indo($arr[1],$style)." ".$arr[0]." ".$exp[1];
        }
        else
        {
            $arr = explode("-",$tanggal);
            if(isset($arr[2]))
            {
                $tgl = $arr[2]." ".self::bulan_indo($arr[1],$style)." ".$arr[0];
            }

        }

        return $tgl;
    }

    public static function convert_bulan($tanggal)
    {
        $arr = explode("-",$tanggal);
        $tgl = self::bulan_indo($arr[1],'panjang')." ".$arr[0];
        return $tgl;
    }

    public static function bulan_indo($kodebulan,$style='panjang') {
        $bulan_panjang = '';
        $bulan_pendek = '';
        if($kodebulan == '01')
        {
            $bulan_panjang = 'Januari';
            $bulan_pendek = 'Jan';
        }
        else if($kodebulan == '02')
        {
            $bulan_panjang = 'Februari';
            $bulan_pendek = 'Feb';
        }
        else if($kodebulan == '03')
        {
            $bulan_panjang = 'Maret';
            $bulan_pendek = 'Mar';
        }
        else if($kodebulan == '04')
        {
            $bulan_panjang = 'April';
            $bulan_pendek = 'Apr';
        }
        else if($kodebulan == '05')
        {
            $bulan_panjang = 'Mei';
            $bulan_pendek = 'Mei';
        }
        else if($kodebulan == '06')
        {
            $bulan_panjang = 'Juni';
            $bulan_pendek = 'Jun';
        }
        else if($kodebulan == '07')
        {
            $bulan_panjang = 'Juli';
            $bulan_pendek = 'Jul';
        }
        else if($kodebulan == '08')
        {
            $bulan_panjang = 'Agustus';
            $bulan_pendek = 'Agus';
        }
        else if($kodebulan == '09')
        {
            $bulan_panjang = 'September';
            $bulan_pendek = 'Sep';
        }
        else if($kodebulan == '10')
        {
            $bulan_panjang = 'Oktober';
            $bulan_pendek = 'Okt';
        }
        else if($kodebulan == '11')
        {
            $bulan_panjang = 'Nopember';
            $bulan_pendek = 'Nop';
        }
        else if($kodebulan == '12')
        {
            $bulan_panjang = 'Desember';
            $bulan_pendek = 'Des';
        }
        if($style == 'panjang')
        {
            return $bulan_panjang;
        }
        else
        {
            return $bulan_pendek;
        }
    }

    public static function changeDateFormat($str, $format='Y-m-d')
    {
        $date = date('Y-m-d', strtotime($str));
        return $date;
    }

    public static function generateAutoNumber($keyFormat, Model $model){
        $findFormatNumber = Setting::where(array('meta_key' => $keyFormat))->get();
        if(count($findFormatNumber) > 0) {
            $digit = 4;
            $strInv = $findFormatNumber[0]->meta_value;
            $tahun = date("Y");
            $bln = date('m');
            $tgl = date("d");
            $blnRomawi = self::getRomawi($bln);
            $whereReset = " YEAR(created_at) = '$tahun'";
            if(!empty(SettingRepo::getOption('reset_number'))) {
                if(SettingRepo::getOption('reset_number') == 'bulan'){
                    $whereReset = " MONTH(created_at) = '$bln'";
                }
            }
            $strInv = str_replace(VarType::SHORTCODE_YEAR,$tahun,$strInv);
            $strInv = str_replace(VarType::SHORTCODE_MONTH,$bln,$strInv);
            $strInv = str_replace(VarType::SHORTCODE_MONTH_ROMAWI,$blnRomawi,$strInv);
            $strInv = str_replace(VarType::SHORTCODE_DATE,$tgl,$strInv);
            $maxid = $model::whereRaw($whereReset)->count();
            $maxid = $maxid + 1;
            $maxid_str = str_pad($maxid, $digit, '0', STR_PAD_LEFT);
            $strInv = str_replace("{inc}",$maxid_str,$strInv);
            return $strInv;
        } else {
            return "";
        }
    }

    public static function getRomawi($month){
        switch ($month){
            case 1:
                return "I";
                break;
            case 2:
                return "II";
                break;
            case 3:
                return "III";
                break;
            case 4:
                return "IV";
                break;
            case 5:
                return "V";
                break;
            case 6:
                return "VI";
                break;
            case 7:
                return "VII";
                break;
            case 8:
                return "VIII";
                break;
            case 9:
                return "IX";
                break;
            case 10:
                return "X";
                break;
            case 11:
                return "XI";
                break;
            case 12:
                return "XII";
                break;
        }
    }


}
