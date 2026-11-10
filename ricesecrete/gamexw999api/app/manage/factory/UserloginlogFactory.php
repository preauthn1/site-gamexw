<?php
namespace app\manage\factory;
use app\common\Utils;
use think\facade\Request;
use app\common\lib\Respons;
use app\common\model\UserLoginModel;
use UAParser\Parser;
class UserloginlogFactory {

    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list() {
        $param=Request::param();
        $uid = $param['uid'] ?? 0;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $UserLoginModel = new UserLoginModel();
        if ($uid > 0) {
            $where[] = ["a.uid", '=', $uid];
        }
        if (isset($param['key']) && $param['key']!='') {
            $where[] = ["b.rec_code|a.login_ip|b.username|b.mail|b.phone", '=', $param['key']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["a.login_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.login_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        $rsps['data']=$UserLoginModel
            ->alias('a')
            ->join('dq_user b', 'a.uid=b.id')
            ->where($where)
            ->order("id desc")
            ->field('a.*,b.username,b.mail,b.phone')
            ->paginate($limit,false,['query' => $param]);
        $parser = Parser::create();
        foreach($rsps['data'] as &$value){
            if ($value['device']) {
                $result = $parser->parse($value['device']);
                $value['device'] = $result->toString();
            }
            $value['login_time'] = date("Y-m-d H:i:s",$value['login_time']);
        }
        $rsps['code']=1;

        //优化慢查询数据
        //设备统计
        $deviceType = UserLoginModel::where('device_type', '>', '')
            ->group('device_type')
            ->column('device_type');
        $deviceArr = [];
        foreach ($deviceType as $k => $v){
            $arr = [];
            $arr['index'] = $k;
            $arr['name'] = $v;
            $arr['num'] = UserLoginModel::alias('a')
                ->join('dq_user b', 'a.uid=b.id')
                ->where('device_type', '=', $v)
                ->where($where)
                ->count();
            $deviceArr[] = $arr;
        }

        $rsps['device_type'] = $deviceArr;
        return Respons::run($rsps);
    }
    
}
?>