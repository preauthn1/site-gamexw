<?php
namespace app\workermans;
define('HEARTBEAT_TIME', 10); // 定义一个心跳间隔10秒
use think\worker\Server;
use Workerman\Lib\Timer;
use app\common\lib\ResponsSocket;
use app\workermans\CheckClass;
use app\common\model\UserModel;
class Socket extends Server
{	
	protected $socket = 'websocket://0.0.0.0:9988';
	public function onMessage($connection,$data){
            $connection->lastMessageTime = time();
            $connection->data = json_decode($data,true);
            try {
                if (!isset($connection->data['m'])) {
                    unset($connection->data);
                    throw new \Exception("请求类型错误", 0);
                }
                if(!($connection->data['m']=="admin" || $connection->data['m']=="api")){
                    unset($connection->data);
                    throw new \Exception("请求类型错误", 0);
                }

	    	$rsps['data']=CheckClass::run($connection,$connection->data);
	    	$rsps['code']=1;
	    } catch (\Throwable $e) {
	    	$rsps['code']=0;
	    	$rsps['msg']=$e->getMessage();
	    }

	    $connection->send(ResponsSocket::run($rsps));
	}

	//连接断开
	public function onClose($connection){	
	    //给用户做下线处理
	    try {
			if($connection->data['m']=="api"){
				//前端用户下线
				UserModel::is_ol($connection->token->uid,0);
			}
	    } catch (\Throwable $e) {
	    	
	    }
	}

	//连接错误
	public function onError($connection, $code, $msg){
		$rsps['code']=0;
	    $rsps['msg']=$msg;
	    $connection->send(ResponsSocket::run($rsps));
	}

	//心跳
	public function onWorkerStart($worker) {
	    Timer::add(2, function()use($worker){
	        $time_now = time();
	        foreach($worker->connections as $connection) {
	            // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
	            if (empty($connection->lastMessageTime)) {
	                $connection->lastMessageTime = $time_now;
	                continue;
	            }else{
                        $rsps['code']=2;
	                $connection->send(ResponsSocket::run($rsps));//让前端刷新
	            }
	            // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
	            if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
	                $connection->close();
	            }
	        }
	    });
	}
}