<?php
namespace Icso\Accounting\Repositories\Utils;

use App\Models\Tenant\Setting;
use Config;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\VarType;

class SettingRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Setting $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public static function getOption($option_name){
        $find = Setting::where(array('meta_key' => $option_name))->first();
        if(!empty($find))
        {
            return $find;
        }
        else {
            return '';
        }
    }

    public static function getOptionValue($option_name){
        $find = self::getOption($option_name);
        if(!empty($find))
        {
            return $find->meta_value;
        }
        else {
            return '';
        }
    }

    public static function setOption($option_name,$option_value,$user_id){
        if(!empty($option_value)){
            $exist = Setting::where(array('meta_key' => $option_name))->first();
            if(empty($exist)) {
                $model = new Setting();
                $model->fill(array('meta_key' => $option_name, 'meta_value' => $option_value, 'created_at' => date('Y-m-d H:i:s'), 'created_by' => $user_id, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id))->save();
            }
            else {
                Setting::where(array('meta_key' => $option_name))->update(array('meta_value' => $option_value, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
            }
        }

    }

    public static function setConfigMail()
    {
        $config = array(
            'driver'     => 'smtp',
            'host'       => self::getOption('smtp_host'),
            'port'       => self::getOption('smtp_port'),
            'from'       => array('address' => self::getOption('smtp_username'), 'name' => 'HRD'),
            'encryption' => self::getOption('smtp_type'),
            'username'   => self::getOption('smtp_username'),
            'password'   => self::getOption('smtp_password')
        );
        Config::set('mail', $config);
    }

    public static function replaceTemplate($userData,$template,$passwd=''){
        //$url_activation = base_url('absen/activate/'.$userData->id.'/'.$userData->activation_code);
       // $anchor = '<a href="'.$url_activation.'">Aktivasi</a>';
        $template = str_replace('{nama_karyawan}',$userData->employee_name,$template);
        $template = str_replace('{kode_karyawan}',$userData->employee_code,$template);
        $template = str_replace('{email}',$userData->email,$template);
        $template = str_replace('{phone}',$userData->phone,$template);
        $template = str_replace('{ktp}',$userData->ktp,$template);
        $template = str_replace('{password}',$passwd,$template);
        //$template = str_replace('{url_link}',$anchor,$template);
        $template = str_replace('{activation_code}',$userData->activation_code,$template);
        return $template;
    }

    public static function getSystemSetting()
    {
        $currency = self::getOptionValue('currency');
        $currencyFormat = self::getOptionValue('currency_format');
        $resetNumber = self::getOptionValue('reset_number');
        $isPkp = false;
        if(tenant()->is_pkp == 'yes')
        {
            $isPkp = true;
        }
        return array(
            'currency' => $currency,
            'currency_format' => $currencyFormat,
            'reset_number' => $resetNumber,
            'is_pkp' => $isPkp,
            'user_id' => tenant()->user_id
        );
    }

    public static function getDefaultCustomer()
    {
        $find = self::getOption(VarType::KEY_DEFAULT_CUSTOMER);
        if(!empty($find)){
            if(!empty($find->meta_value)){
                $getVal = json_decode($find->meta_value);
                return $getVal->id;
            } else {
                return 0;
            }

        }
        return 0;
    }

    public static function getDefaultWarehouse()
    {
        $find = self::getOption(VarType::KEY_DEFAULT_WAREHOUSE);
        if(!empty($find)){
            if(!empty($find->meta_value)){
                $getVal = json_decode($find->meta_value);
                return $getVal->id;
            } else {
                return 0;
            }

        }
        return 0;
    }

    public static function getSeparatorFormat()
    {
        $numberFormat = 0;
        $currencyFormat = SettingRepo::getOptionValue('currency_format');
        if(!empty($currencyFormat)){
            $numberFormat = $currencyFormat;
        }
        return $numberFormat;
    }

    public static function getDetailPerusahaan()
    {

    }

}
