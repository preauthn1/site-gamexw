<?php
namespace app\common\model;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\api\redis\ApiRedis;
use think\Model;
class AgentRakeBackModel extends Model
{
    protected $name = 'agent_rake_back';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id'          => 'int',
        'uid' => 'int',
        'bottom_uid' => 'int',
        'status' => 'int',
        'zd_number' => 'int',
        'money' => 'decimal',
        'remark' => 'varchar',
        'savings' => 'decimal',
        'update_time' => 'int',
        'create_time' => 'bigint'
    ];
    
    /*
     * 处理代理佣金
     */
    public static function commission_one($uid, $rec_id, $money, $zd_number="", $sys=[], $msg="代理返佣", $type="+"){
        $ret = UserModel::cz_balance($rec_id, $money, $type, true, $msg, 6, 0, 1, $uid, true, 101);
        if(!empty($ret)){
            self::add_data($rec_id, $uid, $zd_number, $money, 3, $sys);
        }
    }
    
     /*
     * 处理代理佣金
     */
    public static function commission($uid, $money, $zd_number, $msg="代理返佣", $type="+"){
        $ApiRedis=new ApiRedis();
        $sys=$ApiRedis->systemInfo();
        $user = UserModel::where(['id' => $uid, 'lebal' => 1])->find();
        if($user){
            $tree1 = explode("/", $user['tree']);
            $new_tree = array_filter($tree1);
            $agent_settlement = $sys['agent_settlement'];
            /* 一级代理返佣 */
            $rec_id = isset($new_tree[count($new_tree) - 1])?$new_tree[count($new_tree) - 1]:0;
            if($rec_id > 0){
                $isagent = UserAgentModel::where(['uid'=> $rec_id, 'status' => 3])->count();
                if($isagent > 0){
                    $fy_price = $money*$sys['one_fy']/100;
                    if($agent_settlement == 0){
                        $ret = UserModel::cz_balance($rec_id, $fy_price, $type, true, $msg, 6, 0, 1, $rec_id, true, 101);
                        $status = 3;
                    }else{
                        $status = 1;
                        $ret = true;
                    }
                    if(!empty($ret)){
                        self::add_data($rec_id, $uid, $zd_number, $fy_price, $status, $sys);
                    }
                }
            }
            
            /* 二级代理返佣 */
            $rec_id2 = isset($new_tree[count($new_tree) - 2])?$new_tree[count($new_tree) - 2]:0;
            if($rec_id2 > 0){
                $isagent = UserAgentModel::where(['uid'=> $rec_id2, 'status' => 3])->count();
                if($isagent > 0){
                    $fy_price = $money*$sys['two_fy']/100;
                    if($agent_settlement == 0){
                        $ret = UserModel::cz_balance($rec_id2, $fy_price, $type, true, $msg, 6, 0, 1, $rec_id2, true, 101);
                        $status = 3;
                    }else{
                        $status = 1;
                        $ret = true;
                    }
                    if(!empty($ret)){
                        self::add_data($rec_id2, $uid, $zd_number, $fy_price, $status, $sys);
                    }
                }
            }
            
            /* 三级代理返佣 */
            $rec_id3 = isset($new_tree[count($new_tree) - 3])?$new_tree[count($new_tree) - 3]:0;
            if($rec_id3 > 0){
                $isagent = UserAgentModel::where(['uid'=> $rec_id3, 'status' => 3])->count();
                if($isagent > 0){
                    $fy_price = $money*$sys['three_fy']/100;
                    if($agent_settlement == 0){
                        $ret = UserModel::cz_balance($rec_id3, $fy_price, $type, true, $msg, 6, 0, 1, $rec_id3, true, 101);
                        $status = 3;
                    }else{
                        $status = 1;
                        $ret = true;
                    }
                    if(!empty($ret)){
                        self::add_data($rec_id3, $uid, $zd_number, $fy_price, $status, $sys);
                    }
                }
            }
        }
    }   
    
    /**
     * 添加代理返佣记录
     * @param $uid 享受返佣用户id
     * @param $bottom_uid 下级id
     * @param $zd_number 注单号
    **/
    public static function add_data($uid, $bottom_uid, $zd_number, $money=0, $status=1, $sys=[]){
        $info = self::where(['uid' => $uid, 'zd_number' => $zd_number])->find();
        if(empty($info)){
            $add['uid']=$uid;
            $add['bottom_uid']=$bottom_uid;
            $add['zd_number']=$zd_number;
            $add['money']=$money;
            $add['status']=$status;
            $add['remark']='';
            $add['create_time']=time();
            if($status == 3){
                $add['update_time']=time();
            }
            if(isset($sys['commission_water'])){
                $add['savings'] = $sys['commission_water'] * $money;
            }            
            if ($money!=0) {
                self::insertGetId($add);
                return true;
            }
            return false;
        }else{
            return true;
        }
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'status'=>lang('状态'),
            'update_time'=>lang('派发时间'),
            'money'=>lang('代理返佣'),
        ];
        $set_key = [
            ['status'=>[1=>lang('待处理'),2=>lang('拒绝'),3=>lang('派发'),4=>lang('手动派发')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}