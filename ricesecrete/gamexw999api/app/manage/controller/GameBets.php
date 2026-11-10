<?php
namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\GameBetFeeModel;
use app\common\Utils;
use app\manage\factory\GameBetsFactory;
use think\Request;

class GameBets extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(){
        $rsps = (new GameBetsFactory($this->user_id))->lists();
        return $rsps;
    }

    //用户游戏记录，投注记录，注单记录
    public function user_list(){
        $rsps = (new GameBetsFactory($this->user_id))->user_list();
        return $rsps;
    }

    public function detail() {
        $gb_id = $this->request->param('gb_id/d');
        $detail_url = \app\common\FastGame\Base\Game::getBetDetail($gb_id);

        if ($detail_url) {
            return $this->success(['data' => $detail_url]);
        } else {
            return $this->success(['data' => '']);
        }
    }

    //中奖手续费列表
    public function fee_list(Request $request){
        $params = $this->request->param();

        $page= $params['page'] ?? 1;
        $limit= $params['limit'] ?? 10;
        $condition = [];
        if (isset($params['username']) && $params['username'] !== '') {
            if (is_numeric($params['username'])) {
                $condition[]=['u.id|u.phone|u.rec_code', "=", $params['username']];
            } else {
                $condition[]=['u.id|u.username|u.mail|u.phone', "=", $params['username']];
            }
        }
        if( isset($params['g_name']) && $params['g_name'] !== '' ){
            $condition[]=['g.gi_name', "like", "%{$params['g_name']}%"];
        }
        if( isset($params['tc_id']) && $params['tc_id'] !== '' ){
            $condition[]=['f.tcid', "=", $params['tc_id']];
        }
        if( isset($params['create_time']) && $params['create_time'] !== '' ){
            $dateTime = explode(' - ', $params['create_time']);
            $condition[] = ['f.create_time', 'between', [strtotime($dateTime[0]),strtotime($dateTime[1])]];
        }

        if( isset($params['export']) && $params['export'] == 1 ){
            return (new GameBetsFactory($request->token->uid))->betFeeExportList($condition);
        }


        $baseModel = GameBetFeeModel::alias('f')
            ->join('dq_user u',"u.id=f.uid")
            ->join('dq_game_infos g','f.gid=gi_gid','left')
            ->join('dq_third_configs t','f.tcid=tc_id')
            ->where($condition);

        $data= $baseModel
            ->field('f.*,u.username,u.id as uid,u.mail,u.phone,g.gi_name,t.tc_name')
            ->page($page,$limit)
            ->order('f.id desc')
            ->select()->toArray();
        $platformList = Utils::getOptions('third_configs', ['tc_id', 'tc_name'], [['tc_status', '=', 1]]);
        //统计
        #总手续费
        $statistics['total_fee'] = $baseModel->sum('fee');
        return Respons::run(1, [
            'data' => $data,
            'count' => $baseModel->count(),
            'platform_list' => $platformList,
            'statistics' => $statistics,
        ]);
    }

}