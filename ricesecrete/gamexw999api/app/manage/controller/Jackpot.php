<?php
namespace app\manage\controller;

use app\common\model\JackpotModel;
use app\common\model\JackpotConfigModel;
use app\common\model\JackpotRecordModel;
use app\common\model\UserModel;
use app\common\model\UserLevelModel;
use app\common\model\TagModel;
use app\common\lib\Respons;
use app\common\redis\CommomRedis;
use app\common\Utils;
use think\facade\View;
use think\Request;
use think\facade\Db;

class Jackpot
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //配置
    public function config(Request $request){
        $list = JackpotConfigModel::select();
        foreach ($list as &$v) {
            $v['and_time'] = empty($v['and_time']) ? '-':date('Y-m-d H:i', $v['and_time']);
            $v['end_time'] = empty($v['end_time']) ? '-':date('Y-m-d H:i', $v['end_time']);
            $v['level_name'] = UserLevelModel::where('id', $v['level_id'])->value('level_name') ??lang("不限");
            $v['tag_name'] = TagModel::where('id', $v['tag_id'])->value('name') ??lang("不限");
            $v['agent_id'] = $v['agent_id'] > 0 ? $v['agent_id']:lang("不限");
            $v['next_time'] = empty($v['next_time']) ? '-':date('Y-m-d H:i', $v['next_time']);
        }
        $rsps['code']=1;
        $rsps['data'] = $list;
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['tags']= Utils::getOptions('tag');
        return Respons::run($rsps);
    }


    //配置编辑
    public function configEdit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $da = $param['info'];
            $id = $da['id'];
            unset($da['id']);
            $da['portional_config'] = empty($da['portional_config']) ? []:json_encode($da['portional_config']);
            $da['agent_id'] = empty($da['agent_id']) ? 0 : $da['agent_id'];
            $da['update_time'] = time();
            $da['next_time'] = JackpotModel::getNextStartTime($da['type']);
            $model = new JackpotConfigModel();
            $info = $model->where("id",$id)->find();
            JackpotConfigModel::jackpot_config($id,2,$da);
            $da['id'] = $id;
            return Respons::run(1,'','',$info,$da,$model,lang('排行榜配置修改'));
        }
        if (isset($param['find'])) {
            $res = JackpotConfigModel::where("id",$param['id'])->find();
            if(!empty($res['portional_config'])){
                $res['portional_config'] = json_decode($res['portional_config'], true);
            }
            $res['agent_id'] = $res['agent_id'] > 0 ? $res['agent_id']:'';
            return Respons::run(1,$res);
        }
    }

}
