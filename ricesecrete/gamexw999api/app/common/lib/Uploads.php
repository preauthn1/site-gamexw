<?php

namespace app\common\lib;

use app\common\Utils;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use thans\jwt\parser\Param;
use think\facade\Filesystem;
use app\common\redis\CommomRedis;
use think\facade\Env;
use think\facade\Log;

class Uploads {

    /**
     * 上传图片
     * @param 数组或其他
     */
    public static function upload_img() {
        $files = request()->file();
        try {
            validate(['files' => ['fileExt' => 'jpg,png,jpeg,gif,webp,svg']])->check($files);
            $rsps['data']['file'] = [];
            foreach ($files['files'] as $file) {
                $ufile = str_replace("\\", "/", Filesystem::disk('public')->putFile('img', $file));
                $rsps['data']['file'][] = self::uploadOss($ufile);
            }
            $rsps['code'] = 1;
            return $rsps;
        } catch (\think\exception\ValidateException $e) {
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
            return $rsps;
        }
    }
    
    private static function uploadOss($path) {
        $s3_url = self::uploadS3($path);
        $oss_url = $path;
        if ($s3_url !== false) {
            $oss_url = $s3_url;
//            @unlink(toMedia($path, 2));
        }
        
        return toMedia($oss_url);
        
    }
    
    /**
     * 上传excel
     * @param 数组或其他
     */
    public static function upload_excel() {
        $file = request()->file();
        try {
            validate(['file' => ['fileExt' => 'xlsx,csv']])->check($file);
            $rsps['data']['file'] = "./storage/" . str_replace("\\", "/", \think\facade\Filesystem::disk('public')->putFile('xlsx', $file['file']));
            $rsps['code'] = 1;
            return $rsps;
        } catch (\think\exception\ValidateException $e) {
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
            return $rsps;
        }
    }

    /**
     * 上传文件
     * @param 数组或其他
     */
    public static function upload_video() {
        $file = request()->file();
        try {
            validate(['file' => ['fileExt' => 'mp4,avi,mkv,wmv,flv,mov,3gp,3g2,mpg,mpeg,mp3']])->check($file);
            $ufile = str_replace("\\", "/", Filesystem::disk('public')->putFile('img', $file['file']));
            $rsps['data']['file'] = self::uploadOss($ufile);
            $name = $file['file']->getOriginalName();
            $rsps['data']['name'] = substr($name, 0, strrpos($name, '.'));
            $rsps['data']['size'] = $file['file']->getSize();
            $rsps['code'] = 1;
            return $rsps;
        } catch (\think\exception\ValidateException $e) {
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
            return $rsps;
        }
    }

    /**
     * 上传图片
     * @param 数组或其他
     */
    public static function api_upload_img() {
        $file = request()->file();
        try {
            validate(['file' => ['fileExt' => 'jpg,png,jpeg,gif,webp']])->check($file);
            $ufile = str_replace("\\", "/", Filesystem::disk('public')->putFile('img', $file['file']));
            $rsps['data']['file'] = self::uploadOss($ufile);
            $rsps['code'] = 1;
            return $rsps;
        } catch (\think\exception\ValidateException $e) {
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
            return $rsps;
        }
    }

    /**
     * 上传文件
     * @param 数组或其他
     */
    public static function upload_apk() {
        $file = request()->file();
        $ty = request()['upload_type'] ?? '';
        try {
            validate(['file' => ['fileExt' => 'apk,mobileconfig,jpg,png,jpeg,gif,webp,ico,icon']])->check($file);
            $f = $file['file'];
            $t = date('Ymd');
            if($ty == 'app') {
                $ufile = str_replace("\\", "/", Filesystem::disk('public')->putFile("img/{$t}", $f, function () use ($f){
                    return str_replace('.'.$f->getOriginalExtension(),'',$f->getOriginalName());
                }));
            }else{
                $ufile = str_replace("\\", "/", Filesystem::disk('public')->putFile("img", $f));
            }
            $rsps['data']['file'] = self::uploadOss($ufile);
//            $rsps['data']['file'] = toMedia($ufile);
            $rsps['code'] = 1;
            return $rsps;
        } catch (\think\exception\ValidateException $e) {
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
            return $rsps;
        }
    }

    /**
     * 根据上传参数
     * @param file_name 文件名
     */
    public static function name_upload($file_name) {
        $files = $_FILES['files'];
        self::check_ext($files["name"][$file_name]);
        $save_path = "api_upload/" . date("Y-m-d");
        if (!is_dir($save_path)) {
            $res = mkdir(iconv("UTF-8", "GBK", $save_path), 0777, true);
            if (!$res) {
                throw new \think\Exception('目录创建失败', 0);
            }
        }
        $save_path .= "/" . self::get_file_name($files["name"][$file_name]);
        //移动文件
        move_uploaded_file($files["tmp_name"][$file_name], $save_path);
        $sys = CommomRedis::systemInfo();
        return $sys['domain'] . "/" . $save_path;
    }

    /**
     * base64
     * @param file_name 文件名
     */
    public static function base64_upload($base64) {
        $base64 = explode(',', $base64); //截取data:image/png;base64, 这个逗号后的字符
        $save_path = "api_upload/" . date("Y-m-d");
        if (!is_dir($save_path)) {
            $res = mkdir(iconv("UTF-8", "GBK", $save_path), 0777, true);
            if (!$res) {
                throw new \think\Exception('目录创建失败', 0);
            }
        }
        $image_content = base64_decode($base64[1]);
        $save_path .= "/" . self::get_file_name("base64.png");
        file_put_contents($save_path, $image_content);
        //移动文件
        $sys = CommomRedis::systemInfo();
        return $sys['domain'] . "/" . $save_path;
    }

    //扩展验证
    private static function check_ext($arr_file) {
        $ext = substr($arr_file, strrpos($arr_file, '.') + 1);
        if (!($ext == "png" || $ext == "jpg" || $ext == "jpeg")) {
            throw new \think\Exception('上传类型错误，只能上传图片', 0);
        }
    }

    //保存名称
    private static function get_file_name($file) {
        return md5(time() . $file) . "." . substr($file, strrpos($file, '.') + 1);
    }


    /**
     * 上传文件到AWS
     * @param type $file
     * @return boolean
     */
    public static function uploadS3($file, $object_key = null) {
        $s3_config = [
            'version' => 'latest',
            'region' => Env::get('S3.REGION'),
            'suppress_php_deprecation_warning' => true,
            'credentials' => [
                'key' => Env::get('S3.CREDENTIALS_KEY'),
                'secret' => Env::get('S3.CREDENTIALS_SECRET'),
            ],
        ];
        $s3_domain = Env::get('S3.DOMAIN');
        $s3_bucket = Env::get('S3.BUCKET');

        if (strexists($file, public_path())) {
            $file = str_replace(public_path() . "storage/", "", $file);
        }
        $file_path = toMedia($file, 2);
        if (is_null($object_key)) {
            $object_key = str_replace(public_path(), "", $file_path);
        }
        if ($s3_bucket && isset($s3_config['credentials']['key']) && $s3_config['credentials']['key'] != '') {
            $s3 = S3Client::factory($s3_config);
            try {
                $result = $s3->putObject([
                    'Bucket' => $s3_bucket,
                    'Key'    => $object_key,
                    'Body'   => fopen($file_path, 'r'),
                    'ACL'    => 'public-read',
                ]);
                if ($s3_domain && strexists($s3_domain, 'https://')) {
                    return rtrim($s3_domain, '/') . '/' . $object_key;
                }
                return $result['ObjectURL'];
            } catch (S3Exception $e) {
                Log::error($e->getMessage());
            }
        }
        
        return false;
    }
    
}
