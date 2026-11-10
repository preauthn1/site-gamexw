<?php
namespace app\common\model;
use app\common\FastGame\Base\Utils;
use app\model\GameBetsex;
use think\facade\Db;
use think\Model;
use think\Exception;

class GameBetsModel extends Model
{
    protected $name = 'game_bets';
    protected $pk = 'gb_id';


    public function insertGetId(array $data) {
        $gb_betinfo = null;
        if (isset($data['gb_winlose']) && $data['gb_winlose'] != 1) {
            if (!isset($data['gb_winprice'])) {
                $data['gb_winprice'] = 0;
            }
            if (!isset($data['gb_price'])) {
                $data['gb_price'] = 0;
            }
            // 实际输赢
            $data['gb_otherwin'] = $data['gb_winprice'] - $data['gb_price'];
            // 实际有效流水
            if( env('VALIDWATER_MODE',0) == 1 ){
                //为1按下注金额来
                $data['gb_validwater'] = $data['gb_price'];
            }else{
                $data['gb_validwater'] = $data['gb_winprice'] > $data['gb_price'] ?  $data['gb_price'] : $data['gb_price'] - $data['gb_winprice'];
            }
        }
        $gb_id = $this->insert($data, true);
        //热表迁移已经有处理
//        if (isset($data['gb_betinfo'])) {
//            $gb_betinfo = $data['gb_betinfo'];
//            unset($data['gb_betinfo']);
//        }
//        if (!empty($gb_betinfo)) {
//            GameBetsex::create([
//                'gbe_id' => $gb_id,
//                'gbe_inmonth' => date("Ym", $data['gb_bettime']),
//                'gbe_betinfo' => $gb_betinfo
//            ]);
//        }
        return $gb_id;
    }

    public static function onBeforeUpdate($model) {
//        if (isset($model['gb_betinfo']) && $model['gb_betinfo']) {
//            try {
//                GameBetsex::where('gbe_id', $model['gb_id'])->update(['gbe_betinfo' => Db::raw("CONCAT(gbe_betinfo, '|||', '{$model['gb_betinfo']}')")]);
//            } catch (Exception $exception) {
//                Utils::L('GameBetsexError', [$model['gb_id'], $model['gb_betinfo']], true, true);
//            }
//            unset($model['gb_betinfo']);
//        }
        if (!isset($model['gb_winprice']) || !isset($model['gb_price'])) {
            $info = Db::table('dq_game_bets')->where('gb_id', $model['gb_id'])->field('gb_winprice,gb_price')->find();
            if (!isset($model['gb_winprice'])) {
                $model['gb_winprice'] = $info['gb_winprice'];
            }
            if (!isset($model['gb_price'])) {
                $model['gb_price'] = $info['gb_price'];
            }
        }
        // 实际输赢
        $model['gb_otherwin'] = $model['gb_winprice'] - $model['gb_price'];
        // 实际有效流水
        if( env('VALIDWATER_MODE',0) == 1 ){
            //为1按下注金额来
            $model['gb_validwater'] = $model['gb_price'];
        }else{
            $model['gb_validwater'] = $model['gb_winprice'] > $model['gb_price'] ?  $model['gb_price'] : $model['gb_price'] - $model['gb_winprice'];
        }
    }
}