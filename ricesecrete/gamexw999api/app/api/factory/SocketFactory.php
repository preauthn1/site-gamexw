<?php
namespace app\api\factory;
use app\common\lib\{TokenCheck, Util};
use think\Exception;
use think\facade\Request;
use app\common\FastGame\Socket\SockPlugin;
use app\common\FastGame\Base\Utils;

class SocketFactory
{
    public function __construct(){
        $this->param = Request::param();
        $this->header = Request::header();
    }

    //注册token
    public function regUser_run(){
        $header = $this->header;
        $uid = 0;
        if(isset($header['usertoken'])){
            try {
                $token = TokenCheck::run($header, []);
                $uid = $token->uid;
            } catch (Exception $e) {
                throw new \app\common\exception\MessageException(lang($e->getMessage()), $e->getCode());
            }
        }
        $token = Utils::random(32);
        $reg_sta = SockPlugin::registerMsgUser($uid, $token);
        $rsps['code'] = 1;
        $rsps['data'] = $token;
        return $rsps;
    }

    public function sendMsg_run(){
        $msg = $this->param['msg'];
        $uid = $this->param['uid'];
        $res = SockPlugin::writeStandardMsg($msg, $uid);
        $rsps['code'] = 1;
        $rsps['data'] = $res;
        return $rsps;
    }

}