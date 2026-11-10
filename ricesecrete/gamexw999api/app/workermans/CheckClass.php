<?php
namespace app\workermans;
use app\common\lib\TokenCheck;
use think\facade\Config;
class CheckClass
{	
    // 检查
    public static function run($connection,$data){
        //获取客户端IP
        $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
        try {
            if ($data['m']=="admin") {
                if (!isset($data['info']['key'])) {
                   throw new \Exception("无权访问", 0);
                }
                if ($data['info']['key']!=Config::get("custom.socketKey")) {
                   throw new \Exception("无权访问", 0);
                }
                $obj= "\app\workermans\\".$data['m']."\\".$data['c'];
                $action=$data['a'];
                $rdata=(new $obj())->$action($data);
            }elseif($data['m']=="api"){
                $token=TokenCheck::run($data['header']);
                $connection->token=$token;
                $obj= "\app\workermans\\".$data['m']."\\".$data['c'];
                $action=$data['a'];
                $rdata=(new $obj())->$action($data,$token);
            }else{
                throw new \Exception("请求类型错误", 0);
            }
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), 0);
        }
        return $rdata;
    }
}