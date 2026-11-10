<?php
namespace app\common\lib;
use think\facade\Request;
use app\common\model\AdminLogModel;
class ResponsSocket
{	
	
	private static $codes=[
		0 => '操作错误',
		1 => '操作成功',
		2 => '执行刷新',
	];

	/**
	*数据返回
	* @param 数组或其他
	*/
	public static function run($code=0,$data=[],$msg=''){

		if (is_array($code)) {
			//数组
			$data=$code;
			$rsps['code']=isset($data['code'])?$data['code']:0;
			$rsps['data']=isset($data['data'])?$data['data']:[];
			$rsps['msg']=(isset($data['msg']) && $data['msg']!='')?$data['msg']:(isset(self::$codes[$data['code']])?self::$codes[$data['code']]:$msg);

			foreach ($data as $key => $value) {
				if ($key!='code' || $key!='data' || $key!='msg') {
					$rsps[$key]=$value;
				}
			}
		}else{
			//不是数组
			$rsps['code']=$code;
			$rsps['data']=$data;
			$rsps['msg']=$msg!=''?$msg:self::$codes[$code];
		}

		return json_encode($rsps);
	}

}