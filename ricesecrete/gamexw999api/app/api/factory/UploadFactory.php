<?php
namespace app\api\factory;
use app\common\lib\Uploads;
use app\common\lib\Util;
use think\facade\Request;
class UploadFactory
{
	private $param;//请求参数
	private $uid;//用户id
	
	// 验证
	public function __construct($uid=0){
            $this->param=Request::param();
            $this->uid=$uid;
	}

	// 首页
	public function img_run(){
            $rsps=Uploads::api_upload_img();
            return $rsps;
	}

}