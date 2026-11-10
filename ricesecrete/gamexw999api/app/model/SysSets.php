<?php


namespace app\model;


use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Redis;
use app\common\service\WalletService;
use think\Model;

class SysSets extends Model {

    protected $name = 'sys_sets';
    protected $pk = 'set_id';
    protected static $cacke_key = "system:v1";

    public static function buildCache(){
        $rlts=self::field('set_code,set_type,set_value,set_valuetext')->select()->toArray();
        $rlts=array_column($rlts,null,'set_code');
        $s=[];
        foreach ($rlts as $k=>$v){
            switch ($v['set_type']){
                case 'int':
                    $s[$v['set_code']]=intval($v['set_value']);
                    break;
                case 'uint':
                    $s[$v['set_code']]=abs(intval($v['set_value']));
                    break;
                case 'number':
                    $s[$v['set_code']]=floatval($v['set_value']);
                    break;
                case 'unumber':
                    $s[$v['set_code']]=abs(floatval($v['set_value']));
                    break;
                case 'string':
                    $s[$v['set_code']]=html_entity_decode($v['set_value']);
                    break;
                case 'text':
                    $s[$v['set_code']]=$v['set_valuetext'];
                    break;
                case 'list':
                    $str=empty($v['set_valuetext'])?$v['set_value']:$v['set_valuetext'];
                    if (strpos($str,"|")!==false){
                        //二维数组
                        $str=str_replace("，",",",$str);
                        $strs=explode(",",$str);
                        for($i=0;$i<count($strs);$i++){
                            $strs[$i]=explode("|",$strs[$i]);
                        }
                        $s[$v['set_code']]=$strs;
                    }else{
                        //一维数组
                        $str=str_replace("，",",",$str);
                        $s[$v['set_code']]=explode(",",$str);
                    }
                    break;
                case 'bool':
                    $s[$v['set_code']]=intval($v['set_value'])==0?false:true;
                    break;
                default:
                    $s[$v['set_code']]=empty($v['set_value'])?$v['set_valuetext']:$v['set_value'];
                    break;
            }
        }
        Redis::setex(self::$cacke_key, json_encode($s), 1800);
        return $s;
    }

    /** 清除缓存，修改配置后要执行此操作
     * @author twt326
     * @date 2022-06-11 16:17
     */
    public static function clearCache(){
        $apiRedis=new ApiRedis();
        Redis::del(self::$cacke_key);
        $websiteKey = 'website_key';
        $apiRedis->delete($websiteKey);
    }

    /** 获取所有设置
     * @param bool $cache
     * @return array|mixed
     * @author twt326
     * @date 2022-06-11 16:17
     */
    public static function getSets($cache=true){
        if ($cache){
            $c=Redis::get(self::$cacke_key);
            if ($c){
                return json_decode($c,true);
            }else{
                return self::buildCache();
            }
        }else{
            return self::buildCache();
        }
    }

    /**
     * 根据CODE获取单独的配置值（已处理过的）
     * @param $code
     * @return mixed|null
     * @author twt326
     * @date 2022-06-11 16:17
     */
    public static function getSetByCode($code){
        if( $code == 'sys_cj_support' ){
            $walletSystemData = WalletService::getWalletData();
            return $walletSystemData['wallet_type'];
        }
        $sets=self::getSets();
        return isset($sets[$code])?$sets[$code]:null;
    }
}
