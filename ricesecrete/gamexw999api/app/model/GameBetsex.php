<?php


namespace app\model;


use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Utils;
use think\Exception;
use think\Model;

class GameBetsex extends Model {

    protected $name = 'game_betsex';
    protected $pk = 'gbe_id';

    public function getGbeBetinfoAttr($value){
        $arr = [];
        if (!empty($value)) {
            $arr = explode("|||", $value);
            foreach ($arr as $k => $v) {
                try {
                    $arr[$k] = json_decode($v, true);
                } catch (Exception $exception) {
                    $arr[$k] = $v;
                }
            }
        }
        return $arr;
    }

}
