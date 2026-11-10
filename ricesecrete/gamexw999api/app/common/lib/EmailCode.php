<?php
namespace app\common\lib;
use app\api\redis\ApiRedis;
use app\common\model\EmailConfigs;
use app\common\model\LangModel;
use app\common\model\LangItemsModel;
use app\common\model\LangItemDetailModel;
use app\common\Utils;
use think\Exception;

class EmailCode
{
    /**
     *邮箱验证码发送
     */
    public static function run($email, $code, $type=0){
        $sms = EmailConfigs::GetOne();
        $lang_id = LangModel::where('is_default', 1)->value('id');
        $item_id = 0;
        $sms_content = lang('sms_content');
        if($type == 1){
            $sms_content = lang('sms_content1');
            $item_id = LangItemsModel::where("title",'sms_content1')->value('id');
        } elseif($type == 2) {
            $sms_content = lang('sms_content2');
            $item_id = LangItemsModel::where("title",'sms_content2')->value('id');
        }

        if(empty($item_id)){
            $item_id = LangItemsModel::where("title",'sms_content')->value('id');
        }
        if (strexists($sms_content, 'sms_content')) {
            $sms_content = LangItemDetailModel::where(['lang_id' => $lang_id, 'item_id' => $item_id])->value('text');
        }
        if (strexists($sms_content, 'sms_content')) {
            $sms_content = "Your verification code is:";
        }
        $code=(string) $code;
        $sms_content = $sms_content.$code;

        if(!$sms){
            //没有数据使用默认的，兼容以前的对接方式
            return self::default1($email, $code);
        }
        $run = $sms['iden'];
        $configs = $sms['configs'];
        $configs['code'] = $code;
        //增加发件人和标题
        $webName = (new ApiRedis())->systemInfo()['website_name'] ?? 'Email verification code';
        $configs['email_sender'] = lang('email_sender') == 'email_sender' ? $webName : lang('email_sender');#发件人
        $configs['email_title'] = lang('email_code_title') == 'email_code_title' ? 'Email verification code' : lang('email_code_title');#标题
        return self::$run($email,$sms_content,$configs);
    }

    //默认方法
    public static function default1($email, $sms_content){
        $url = env('email.url', '');
        $data['apikey'] = env('email.apikey', '');
        if( !$url || !$data['apikey'] ){
            return false;
        }
        $data['email'] = $email;
        $data['content']= $sms_content;
        $result=Util::postFromRequest($url,$data);
        if (isset($result['code'])&&$result['code']==1){
            return true;
        }else{
            Utils::L('email-error', [$result]);
            return false;
        }
    }

    //默认方法
    public static function default($email, $sms_content, $configs){
        $url = $configs['url'];
        $data['apikey'] = $configs['apikey'];
        $data['email'] = $email;
        $data['content']= $sms_content;
        $result=Util::postFromRequest($url,$data);
        if (isset($result['code'])&&$result['code']==1){
            return true;
        }else{
            Utils::L('email-error', [$result]);
            return false;
        }
    }

    //额外方法
    public static function frs($email, $sms_content, $configs){
        $url = $configs['url'];
        $data = [];
        $data['account'] = $configs['account'];
        $data['password'] = $configs['password'];
        $data['datetime'] = time();
        //三方请求连接上必须带上上面三个参数
        $data['from'] = $configs['email_sender'];#发件人
        $data['emails'] = $email;
        $data['theme'] = $configs['email_title'];#标题
        $data['content'] = $sms_content;#内容
        $data['sign'] = strtolower(md5($data['account'].$data['password'].$data['datetime']));
        $header=[
            "Content-type: application/x-www-form-urlencoded",
            'User-Agent: '.$data['sign'] #随便填写，三方必须要传该header
        ];
        $result=Util::postFromRequest($url,http_build_query($data),$header);
        if( $result && isset($result['status']) && $result['status'] == 0 ){
            return true;
        }else{
            Utils::L('email-error', [$result,$url,$data]);
            return false;
        }
    }



    public static function  MailGun($email, $sms_content, $configs){
        $domain = $configs['domain'];
        $data = [];
        $username = $configs['username'];
        $password = $configs['password'];
        //三方请求连接上必须带上上面三个参数
        $data['from'] = $configs['from'];#发件人
        $data['to'] = $email; //收件人
        $data['subject'] = $configs['email_title'];#标题
        $data['template'] = 'register';//模板
        $variables = [
            'USERNAME' => $email,
            'code' => $configs['code'],
        ];
        $data['h:X-Mailgun-Variables'] = json_encode($variables);//模板变量
        $header=[
            "Content-Type: multipart/form-data",
            "Authorization: Basic " . base64_encode("{$username}:{$password}")
        ];
        $url = "https://api.mailgun.net/v3/" . $domain . "/messages";
        $result = Util::postFromRequest($url, $data, $header);
        Utils::L('email', [$result,$url,$data]);
        if( $result && isset($result['id'])){
            return true;
        }else{
            Utils::L('email-error', [$result,$url,$data]);
            return false;
        }
    }

    public static function notifyMail($email, $other){
        $w = [];
        $w[] =['notify_status', '=', 1];
        $sms = EmailConfigs::GetOne($w);
        if(!$sms){
            return  false;
        }
        $run = $sms['iden'] . 'Notify';
        $configs = $sms['configs'];
//        $title_key = $other['notify_type'].'_title';
//        $content_key = $other['notify_type'].'_content';
        $webName = (new ApiRedis())->systemInfo()['website_name'] ?? 'Email verification code';
        $configs['email_sender'] = lang('email_sender') == 'email_sender' ? $webName : lang('email_sender');#发件人
//        $configs['email_title'] = (new ApiRedis())->systemInfo()[$title_key] ?? '';#标题
//        $configs['email_content'] = (new ApiRedis())->systemInfo()[$content_key] ?? '';#内容
        $configs['email_title'] = $other['title'] ?? '';#标题
        $configs['email_content'] = $other['content'] ?? '';#内容
        return self::$run($email,$configs, $other);
    }



    //mailgun 的通知类邮件
    //uid 要发送的用户id
    //type 发送的模板类型
    //other 其他参数  例如充值 提现金额
    public  static function MailGunNotify($email, $configs, $other = []){
        //类型
        $domain = $configs['domain'];
        $data = [];
        $username = $configs['username'];
        $password = $configs['password'];
        //三方请求连接上必须带上上面三个参数
        $data['from'] = $configs['from'];#发件人
        if(is_array($email)){
            $data['to'] = implode(',', $email);
        }else{
            $data['to'] = $email; //收件人
        }
        $data['subject'] = $configs['email_title'];#标题
        $data['template'] = $configs['email_content'];//模板
//        if($other['notify_type'] == 'verify'){
//            $variables = [
//                'username' => $email,
//                'message' => $configs['code'],
//                'conversationCode' => $configs['code'],
//            ];
//            $data['h:X-Mailgun-Variables'] = json_encode($variables);//模板变量
//        }

        $header=[
            "Content-Type: multipart/form-data",
            "Authorization: Basic " . base64_encode("{$username}:{$password}")
        ];
        $url = "https://api.mailgun.net/v3/" . $domain . "/messages";
        $result = Util::postFromRequest($url, $data, $header);
//        Utils::L('email', [$result,$url,$data]);
        if( $result && isset($result['id'])){
            return true;
        }else{
            Utils::L('email-error', [$result,$url,$data]);
            return false;
        }
    }
}