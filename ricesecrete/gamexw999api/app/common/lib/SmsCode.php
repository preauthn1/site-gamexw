<?php
namespace app\common\lib;
use app\common\model\LangModel;
use app\common\model\LangItemsModel;
use app\common\model\LangItemDetailModel;
use app\common\model\SmsConfigs;
use app\common\lib\Util;
use app\common\lib\Twilio;
use app\api\redis\ApiRedis;
use think\facade\Log;

class SmsCode
{
    public static function run($qh, $phone, $param = '', $time=60, $type=0){
        $ApiRedis = new ApiRedis();
        if(substr($phone,0,1) == 0){
            $phone = substr_replace($phone,"",0,1);
        }
        $phone = $qh.$phone;
        $code = rand(123456,999999);
        $time += 600;
        $ApiRedis->set($param."_code", $code, $time);
        $sms = SmsConfigs::GetOne($qh);
        if($sms){
            $lang_id = LangModel::where('is_default', 1)->value('id');
            $item_id = 0;
            if($type == 1){
                $item_id = LangItemsModel::where("title",'sms_content1')->value('id');
            } elseif($type == 2) {
                $item_id = LangItemsModel::where("title",'sms_content2')->value('id');
            }

            if(empty($item_id)){
                $item_id = LangItemsModel::where("title",'sms_content')->value('id');
            }
            $sms_content = LangItemDetailModel::where(['lang_id' => $lang_id, 'item_id' => $item_id])->value('text');
            $content = urldecode($sms_content.(string)$code);
            $run = $sms['iden'];
            return self::$run($phone,$content,$sms['configs']);
        }else{
            return false;
        }
    }

    public static function wftqm($phone,$content,$configs){
        $data['appkey'] = $configs['appkey'];
        $data['secretkey'] = $configs['secretkey'];
        $data['phone']=$phone;
        $data['content'] = $content;
        Log::write(['Submit_create',"wftqm",$data], "sms");
        $result=Util::postFromRequest($configs['url'],$data);
        Log::write(['Return_create',"wftqm",$result], "sms");
        if ($result['code']==0) {
            return true;
        }
        return false;
    }

    public static function paasoo($phone,$content,$configs){
        $data['key'] = $configs['key'];
        $data['secret'] = $configs['secret'];
        $data['from'] = $phone;
        $data['to']=$phone;
        $data['text'] = $content;
        Log::write(['Submit_create',"paasoo",$data], "sms");
        $result=Util::postFromRequest($configs['url'],$data);
        Log::write(['Return_create',"paasoo",$result], "sms");
        if ($result['status']==0) {
            return true;
        }
        return false;
    }

    public static function batch($phone,$content,$configs){
        $data['appkey'] = $configs['appkey'];
        $data['appcode'] = $configs['appcode'];
        $data['appsecret'] = $configs['appsecret'];
        $data['phone']=$phone;
        $data['msg'] = $content;
        Log::write(['Submit_create',"batch",$data], "sms");
        $result=Util::postFromRequest($configs['url'],$data);
        Log::write(['Return_create',"batch",$result], "sms");
        if ($result['code'] == '00000') {
            return true;
        }
        return false;
    }

    public static function xyz($phone,$content,$configs){
        $appId = $configs['xyz_appId'];
        $timestamp = time()*1000;
        $data['phones'] = $phone;
        $data['content'] = $content;
        $data['sendAddr'] = 'starlink';
        $header = [
            'Content-Type: application/json; charset=utf-8;',
            'appId:'.$appId,
            'timestamp:'.$timestamp,
            'sign:'.md5($appId.$configs['xyz_secretkey'].$timestamp),
        ];
        Log::write(['Submit_create',"xyz",$data,$header], "sms");
        $result=Util::postFromRequest($configs['url'],json_encode($data),$header);
        Log::write(['Return_create',"xyz",$result], "sms");
        if ($result['code'] == '0') {
            return true;
        }
        return false;
    }

    public static function onbuka($phone,$content,$configs){
        $ApiKey = $configs['onbuka_apiKey'];
        $data['appId'] = $configs['onbuka_appId'];
        $data['numbers'] = $phone;
        $data['content'] = $content;
        $data['senderId'] = $configs['onbuka_senderId'];
        $timestamp = time();
        $header = [
            'Content-Type: application/json; charset=utf-8;',
            'Sign:'.md5($ApiKey.$configs['onbuka_apisecret'].$timestamp),
            'Timestamp:'.$timestamp,
            'Api-Key:'.$ApiKey,
        ];
        Log::write(['Submit_create',"onbuka",$data,$header], "sms");
        $result=Util::postFromRequest($configs['url'],json_encode($data),$header);
        Log::write(['Return_create',"onbuka",$result], "sms");
        if ($result['status'] == '0') {
            return true;
        }
        return false;
    }

    public static function SpC($phone,$content,$configs){
        $data['SpCode'] = $configs['SpCode'];
        $data['LoginName'] = $configs['LoginName'];
        $data['Password'] = $configs['Password'];
        $data['MessageContent'] = $content;
        $data['UserNumber'] = '00'.$phone;
        Log::write(['Submit_create',"SpC",$data], "sms");
        $result=Util::postFromRequest($configs['url'],$data);
        Log::write(['Return_create',"SpC",$result], "sms");
        if ($result['result']=="0") {
            return true;
        }
        return false;
    }

    public static function kmi($phone,$content,$configs){
        $data['accessKey'] = $configs['accessKey'];
        $data['secretKey'] = $configs['secretKey'];
        $data['to'] = $phone;
        $data['message'] = $content;
        $header = [
            'Content-Type: application/json; charset=utf-8;',
        ];
        Log::write(['Submit_create',"kmi",$data], "sms");
        $result=Util::postFromRequest($configs['url'],json_encode($data),$header);
        Log::write(['Return_create',"kmi",$result], "sms");
        if (isset($result['code']) && $result['code'] == 200) {
            return true;
        }
        return false;
    }

    public static function itnio($phone,$content,$configs){
        $data['appId'] = $configs['app_id'];
        $data['numbers'] = $phone;
        $data['content'] = $content;
        $apiKey = $configs['api_key'];
        $apiSecret = $configs['api_secret'];
        $timeStamp = time();
        $sign = md5($apiKey.$apiSecret.$timeStamp);
        $header = [
            'Content-Type:application/json;charset=UTF-8',"Sign:$sign","Timestamp:$timeStamp","Api-Key:$apiKey"
        ];
        Log::write(['Submit_create',"itnio",$data], "sms");
        $result=Util::postFromRequest($configs['url'],json_encode($data),$header);
        Log::write(['Return_create',"itnio",$result], "sms");
        if (isset($result['status']) && $result['status'] == 0) {
            return true;
        }
        return false;
    }

    public static function mtsend($phone,$content,$configs){
        $data['appkey'] = $configs['appkey'];
        $data['secretkey'] = $configs['secretkey'];
        $data['phone'] = $phone;
        $data['content'] = $content;
        Log::write(['Submit_create',"mtsend",$data], "sms");
        $result=Util::postFromRequest($configs['url'],$data);
        Log::write(['Return_create',"mtsend",$result], "sms");
        if (isset($result['code']) && $result['code'] == 0) {
            return true;
        }
        return false;
    }

    public static function qixin($phone,$content,$configs){
        $data['action'] = $configs['action'];
        $data['account'] = $configs['account'];
        $data['password'] = $configs['password'];
        $data['mobileContentKvp'] = [
            $phone => $content
        ];
        $data['extno'] = $configs['extno'];
        $header = [
            'Content-Type: application/json; charset=utf-8;',
        ];
        Log::write(['Submit_create',"qixin",$data], "sms");
        $result=Util::postFromRequest($configs['url'],json_encode($data), $header);
        Log::write(['Return_create',"qixin",$result], "sms");
        if (isset($result['status']) && $result['status'] == 0) {
            return true;
        }
        return false;
    }

    public static function twilio($phone,$content,$configs){
        $Twilio = new Twilio($configs);
        $phone = '+' . $phone;
        Log::write(['Submit_create',"twilio",$phone], "sms");
        $result = $Twilio->sendSms($phone);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    public static function twilio_check($phone, $code, $configs){
        $Twilio = new Twilio($configs);
        $phone = '+' . $phone;
        Log::write(['Submit_checkCode', "twilio", $phone], "check");
        $result = $Twilio->checkCode($phone, $code);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

}