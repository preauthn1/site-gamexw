<?php


namespace app\model;


use think\Model;

class GameConfigs extends Model {

    protected $name = 'game_configs';
    protected $pk = 'gc_gid';

    /**
     * @param $gid
     * @return GameConfigs|array|mixed|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getConfigs($gid) {
        $configs = self::where('gc_gid', $gid)->find();
        if (!$configs) {
            $configs = new GameConfigs();
            $configs->gc_gid = $gid;
            $configs->save();
        }
        return $configs;
    }
}
