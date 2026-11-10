<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\{RedPacketLogModel, RedPacketTimeModel};
use think\facade\Request;

class RedPacketFactory
{
    public static $weekDay = [
        '周一' => 1,
        '周二' => 2,
        '周三' => 3,
        '周四' => 4,
        '周五' => 5,
        '周六' => 6,
        '周日' => 7,
    ];
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->uid = $uid;
    }

    /**
     * 初始化红包雨时间区间数据
     * @param $rid
     * @param $model
     * @param $weeks
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function intValidityTime($rid, $model, $weeks)
    {
        $condition[] = ['rid', '=', $rid];
        $data = RedPacketTimeModel::where($condition)->field('id')->find();
        if (empty($data)) {
            $in_data = [
                'rid' => $rid,
                'model' => 1,
                'week' => 0,
                'status' => 1,
                'validity_time' => json_encode([]),
                'create_time' => time(),
                'update_time' => time(),
            ];
            RedPacketTimeModel::insertGetId($in_data);
            foreach (self::$weekDay as $key => $value) {
                $in_data = [
                    'rid' => $rid,
                    'model' => 2,
                    'week' => $value,
                    'status' => 2,
                    'validity_time' => json_encode([]),
                    'create_time' => time(),
                    'update_time' => time(),
                ];
                RedPacketTimeModel::insertGetId($in_data);
            }
        }
        if ($model == 2) {
            $condition[] = ['rid', '=', $rid];
            RedPacketTimeModel::where($condition)->update(['status' => 2]);
            if (!empty($weeks)) {
                //更新状态
                $weekKey = [];
                foreach ($weeks as $v) {
                    $weekKey[] = self::$weekDay[$v];
                }
                $condition[] = ['rid', '=', $rid];
                $condition[] = ['model', '=', $model];
                RedPacketTimeModel::where($condition)->whereIn('week', $weekKey)->update(['status' => 1]);
            }
        }else{
            $condition[] = ['rid', '=', $rid];
            $condition[] = ['model', '=', $model];
            RedPacketTimeModel::where($condition)->update(['status' => 1]);
        }
    }

    /**
     * 添加红包时间区间
     * @param $rid
     * @param $timeArr
     * @param $model
     * @param $week
     * @param $status
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addValidityTime($rid, $timeArr, $model, $week)
    {
        $where = ['rid'=>$rid,'model'=>$model,'week'=>$week];
        $data = $this->_validity_time_rep_arr($timeArr,$where);
        if ($data['code'] == 1) {
            $in_data = [
                'validity_time' => json_encode($timeArr),
                'update_time' => time(),
            ];
            $map = [
                'rid' => $rid,
                'model' => $model,
                'week' => $week,
            ];
            RedPacketTimeModel::where($map)->update($in_data);
            $data = ['code' => 1, 'msg' => '操作成功'];
        }
        return $data;
    }

    /**
     * 验证时间是否重叠
     * @param array $timeArr
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _validity_time_rep_arr($timeArr = [],$where=[])
    {

        foreach ($timeArr as $key=>$value) {
            foreach ($timeArr as $ke=>$val) {
                if($key!=$ke){
                    $is_has = $this->_validity_time_rep($value, $val);
                    if ($is_has == true) {
                        return [
                            'code' => 100,
                            'msg' => lang("时间:") . $value . " ".lang("与 自身设置时间") . $val . lang("时间重叠"),
                        ];
                    }
                }
            }
        }
        $redPacketTime = RedPacketTimeModel::where('model','<>',$where['model'])->field('validity_time')->select();
        $validity_time = [];
        foreach ($redPacketTime as $k => $v) {
            $temp_data = json_decode($v['validity_time'], true);
            foreach ($temp_data as $value) {
                $validity_time[] = $value;
            }
        }
        foreach ($timeArr as $value) {
            foreach ($validity_time as $val) {
                $is_has = $this->_validity_time_rep($value, $val);
                if ($is_has == true) {
                    return [
                        'code' => 101,
                        'msg' => lang("时间:") . $value . " ".lang("与 自身设置时间") . $val . lang("时间重叠"),
                    ];
                }
            }
        }
        return [
            'code' => 1,
            'msg' => '成功'
        ];
    }

    /**
     * 判断两个时间区间是否有重叠
     * @param $sec_time
     * @param $sec1_time
     * @return bool
     */
    private function _validity_time_rep($sec_time, $sec1_time)
    {
        $sec_time_arr = explode('-', $sec_time);
        $sec_start = strtotime($sec_time_arr[0]);
        $sec_end = strtotime($sec_time_arr[1]);
        $sec1_time_arr = explode('-', $sec1_time);
        $sec1_start = strtotime($sec1_time_arr[0]);
        $sec1_end = strtotime($sec1_time_arr[1]);
        $is_has = false;
        if (($sec1_start <= $sec_start && $sec_start <= $sec1_end) || ($sec1_start <= $sec_end && $sec1_end >= $sec_end)) {
            //开始时间在时间区间内容区间内
            $is_has = true;
        }
        return $is_has;
    }

    /**
     * 导出数据
     * @param null $where
     * @return \think\response\Json
     */
    public function exportList($where = null)
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['positionField'] = 'r.id';
        $params['positionOrder'] = 'desc';
        $params['muid'] = $this->uid;

        $rlt = ReportExport::export(new rptParams('红包雨领取列表',
            [
                new rptCol('uid', '用户UID'),
                'r_name:活动名称',
                'award_value:奖励金额',
                new rptCol('create_time', '注册时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s'])
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['id', 'uid'],//导出表名称使用的字段信息
            'r.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $redPacketLogModel = new RedPacketLogModel();
        return $redPacketLogModel
            ->alias('r')
            ->join('red_packet a', 'a.id=r.rid')
            ->where($param['where'])
            ->field("r.*,a.name as r_name");
    }
}

?>