<?php
namespace app\api\factory;
use app\common\model\BannerModel;
use app\common\lib\Util;
use think\facade\Request;
class IndexFactory
{
	private $param;//请求参数
	//验证
	public function __construct($uid=0){
		$this->param=Request::param();
		
	}

	//首页
	public function index_run(){
		$BannerModel=new BannerModel();
		$data['banner']=$BannerModel->where(time()." >= start_time and ".time()." <= end_time")->where("status",1)->field("img,url")->select();
		$rsps['data']=$data;
		$rsps['code']=1;
	    return $rsps;
	}

}