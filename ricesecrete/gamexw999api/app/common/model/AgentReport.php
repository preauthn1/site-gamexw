<?php
namespace app\common\model;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\api\redis\ApiRedis;
use think\Model;
class AgentReport extends Model
{
    protected $name = 'agent_report';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id'  => 'int',
        'uid' => 'int',
        'rec_id' => 'int',
        'rec2_id' => 'int',
        'rec3_id' => 'int',
        'start_time' => 'int',
        'end_time' => 'int',
        'order_money' => 'decimal',
        'win_money' => 'decimal',
        'update_time' => 'int',
        'add_time' => 'int',
        'is_rebate' => 'tinyint'
    ];
    
    public static function addReport($uid, $start_time, $end_time, $tz_price, $sy_res){
        $user = UserModel::where(['id' => $uid, 'lebal' => 1])->find();
        $rec_id = $rec2_id = $rec3_id = 0;
        if($user){
            $tree1 = explode("/", $user['tree']);
            $new_tree = array_filter($tree1);
            $rec_id = isset($new_tree[count($new_tree) - 1])?$new_tree[count($new_tree) - 1]:0;
            $rec2_id = isset($new_tree[count($new_tree) - 2])?$new_tree[count($new_tree) - 2]:0;
            $rec3_id = isset($new_tree[count($new_tree) - 3])?$new_tree[count($new_tree) - 3]:0;
        }
        
        $info = self::where(['uid' => $uid, 'start_time' => $start_time, 'end_time' => $end_time])->find();
        if(empty($info)){
            $data = [];
            $data['uid'] = $uid;
            $data['rec_id'] = $rec_id;
            $data['rec2_id'] = $rec2_id;
            $data['rec3_id'] = $rec3_id;
            $data['start_time'] = $start_time;
            $data['end_time'] = $end_time;
            $data['order_money'] = $tz_price;
            $data['win_money'] = $sy_res;
            $data['update_time'] = time();
            $data['add_time'] = time();
            self::insertGetId($data);
        } else {
            self::where(['id' => $info['id']])->inc("order_money", $tz_price)->inc("win_money", $sy_res)->update();
        }
    }
}