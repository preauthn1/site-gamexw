<?php
namespace app\promote\controller;
use app\common\lib\Respons;
use app\common\lib\Uploads;
use think\facade\Request;
class Upload
{	
    //图片上传
    public function upload_img(){
        $rsps=Uploads::upload_img();
        return Respons::run($rsps);
    }

    //文件上传
    public function upload_file(){
        $type = request()->param('type/s');
        if ($type == 'app') {
            $rsps=Uploads::upload_apk();
        } else {
            $rsps=Uploads::upload_excel();
        }
        return Respons::run($rsps);
    }

}