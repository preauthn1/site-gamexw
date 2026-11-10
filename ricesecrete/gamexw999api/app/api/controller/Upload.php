<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\UploadFactory;
use think\Request;
class Upload
{	
	
	//上传图片
	public function img(Request $request){
            $rsps=(new UploadFactory($request->token->uid))->img_run();
            return Respons::run($rsps);
	}
	
	
}