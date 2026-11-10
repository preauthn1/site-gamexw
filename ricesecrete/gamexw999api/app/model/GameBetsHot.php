<?php


namespace app\model;


use think\Model;

class GameBetsHot extends Model
{

    protected $name = 'game_bets_hot';
    protected $pk = 'gb_id';

    public function insertGetId(array $data)
    {
        $gb_betinfo = null;
        if (isset($data['gb_betinfo'])) {
            $gb_betinfo = $data['gb_betinfo'];
            unset($data['gb_betinfo']);
        }
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
            $data['gb_validwater'] = $data['gb_winprice'] > $data['gb_price'] ? $data['gb_price'] : $data['gb_price'] - $data['gb_winprice'];
        }
        $gb_id = $this->insert($data, true);
        if (!empty($gb_betinfo)) {
            GameBetsex::create([
                'gbe_id' => $gb_id,
                'gbe_inmonth' => date("Ym", $data['gb_bettime']),
                'gbe_betinfo' => $gb_betinfo
            ]);
        }
        return $gb_id;
    }
}
