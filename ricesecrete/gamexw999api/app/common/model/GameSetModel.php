<?php
namespace app\common\model;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
class GameSetModel extends Model
{
    protected $name = 'game_set';

    protected $schema = [
        'id'          => 'int',
        'game_sys_sign' =>  'text',
        'game_name' =>  'text',
        'game_status' =>  'int',
        'game_param' =>  'text',
        'min_transfer' =>  'decimal',
        'max_transfer' =>  'decimal',
        'sort' =>  'int',
        'icon' =>  'varchar',
        'wallet_type' =>  'tinyint',
        'game_code' =>  'varchar',
        'type' =>  'int',
        'content' =>  'varchar',
        'tc_id' =>  'int',
        'is_hot' =>  'tinyint',
        
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id' => 'id',
            'game_sys_sign' => lang('游戏标识'),
            'game_name' => lang('游戏名称'),
            'game_status' => lang('状态'),
            'game_param' => lang('游戏参数'),
            'min_transfer' => lang('最小转账金额'),
            'max_transfer' => lang('最大转账金额'),
            'sort' => lang('排序'),
            'icon' => lang('封面'),
            'wallet_type' => lang('钱包模式'),
            'game_code' => lang('游戏代号'),
            'type' => lang('游戏分类'),
            'content' => lang('游戏说明'),
            'tc_id' => lang('游戏平台'),
        ];
        $set_key = [
            ['game_status' => [1 => lang('开启'), 0 => lang('关闭')]],
            ['wallet_type' => [1 => lang('共享钱包'), 0 => lang('转账模式')]],
        ];
        return ['field' => $field,'set_key' => $set_key,'table' => $this->name];
    }

    
    //获取配置秘钥信息
    public static function get_config($field){
        $game_set = self::where("game_sys_sign",$field)->find();
        $game_set['game_param'] = json_decode($game_set['game_param'],true);
        $game_config = [];
        foreach ($game_set['game_param'] as $key => $value) {
            $game_config[$value['key_name']] = $value['key_value'];
        }
        $game_set['config'] = $game_config;
        return $game_set;
    }

    public static function get_game_platform($gid, $game_name = ''){
        $where = [];
        $where[] = ["game_status","=",1];
        if ($gid>0){
            $where[] = ["id","=",$gid];
        }
        if ($game_name != ''){
            $where[] = ["game_name","=",$game_name];
        }

        $game=self::where($where)->order("sort desc")->find();//随机渠道
        if (!isset($game['id'])) {
            ajax_return("游戏不存在或未开启", 0);
        }
        $game['game_param']=json_decode($game['game_param'],true);
        $game_config=[];

        foreach ($game['game_param'] as $key => $value) {
            $game_config[$value['key_name']]=$value['key_value'];
        }
        $game['config']=$game_config;
        return $game;
    }

    /**
     * @param $where
     * @return array|bool|\PDOStatement|string|Model|null
     */
    public function get_game_data($where){
        if(isset($where['gid']) && $where['gid'] == 0 ){
            ajax_return("游戏不存在或未开启", 0);
        }
        $where['game_status'] = 1;
        $game = self::where($where)->filter(function($data){
            $data['game_param'] = json_decode($data['game_param'],true);
            $arr = [];
            foreach ($data['game_param'] as $k => $v) {
                $arr[$v['key_name']]=$v['key_value'];
            }
            $data['config'] = $arr;
        })->find();
        return $game;
    }

    /**
     * 获取三方游戏数据
     * @param array $where
     * @return object|null $game  游戏数据
     * @throws DbException
     */
    public static function get_game(array $where): ?object
    {
        $res = self::where($where)->find();
        return  $res;
    }

    public function type(){
        return $this->belongsTo(GameTypeModel::class, 'type', 'id')->bind(['type_name' => 'name']);
    }

}