<?php

namespace app\manage\factory;

use app\api\redis\ApiRedis;
use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\GameTypeModel;
use app\common\model\RocketActivityRecordModel;
use app\common\model\UserModel;
use app\common\Utils;
use Carbon\Carbon;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Request;

class GameAgentFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function report()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $where2=[];
        if(!empty($param['month'])){
            $where[] = ["a.sff_m", "=", $param['month']];
        }
        if(!empty($param['week'])){
            $where[] = ["a.sff_w", "=", $param['week']];
        }
        if(!empty($param['utype'])){
            $where[] = ["a.sff_utype", $param['utype'] == 1 ? "=" : ">", 1];
            $where2[] = ["sff_utype", $param['utype'] == 1 ? "=" : ">", 1];
        }
        if(isset($param['state']) && $param['state'] !== '' && is_numeric($param['state'])){
            $where[] = ["a.sff_payed", '=', $param['state']];
        }
        if(isset($param['date']) && isArrayAndNotEmpty($param['date']) && count($param['date']) == 2){
            $where[] = ["a.sff_paytime", '>=', Utils::getQueryDateTime($param['date'][0])];
            $where[] = ["a.sff_paytime", '<=', Utils::getQueryDateTime($param['date'][1], true)];
        }
        //缓存查询数据
        $ApiRedis = new ApiRedis();
        if($ApiRedis->has("third_stats_fyfy_month")){
            $month = $ApiRedis->get("third_stats_fyfy_month");
        }else{
            $month = Db::name('third_stats_fyfy')->where($where2)->order("sff_m desc")->group("sff_m")->column("sff_m");
            $ApiRedis->set("third_stats_fyfy_month",$month,300);
        }
        if($ApiRedis->has("third_stats_fyfy_week")){
            $week = $ApiRedis->get("third_stats_fyfy_week");
        }else{
            $week = Db::name('third_stats_fyfy')->where($where2)->order("sff_w desc")->group("sff_w")->column("sff_w");
            $ApiRedis->set("third_stats_fyfy_week",$week,300);
        }
        if(!empty($param['username'])){
            if (is_numeric($param['username'])) {
                $uid = UserModel::where([['id|phone','=',$param['username']]])->value("id");
            } else {
                $uid = UserModel::where([['mail|phone|username','=',$param['username']]])->value("id");
            }
            $where[] = ["a.sff_uid","=", $uid];
        }
        $afield = ["sff_prelosetotal", "sff_bet", "sff_betwin", "sff_betloss", "sff_ks", "sff_payin", "sff_payinfee", "sff_payout", "sff_payoutfee", "sff_yh",
            "sff_back", "sff_tec", "sff_validuser", "sff_base", "sff_baserate", "sff_basereal", "sff_rate", "sff_fy", "sff_losetotal"
        ];
        if (isset($param['filter_zreo']) && $param['filter_zreo'] == 1) {
            $where[] = [implode("|", $afield),">", 0];
        }
        if (isset($param['export']) && $param['export'] == 1) {
            if(!empty($param['utype'])){
                $name = intval($param['utype'])==1?'外部代理返佣':'代理返佣';
                return $this->exportList($where, '',$name);
            }
            $rsps['code']=0;
            return Respons::run($rsps);
        }

        $where[] = ['sff_fy', '>', 0];
        $rsps['count'] = Db::name('third_stats_fyfy')->alias('a')->where($where)->count();
        $lists = Db::name('third_stats_fyfy')->alias('a')->where($where)
            ->join('user b', "a.sff_uid=b.id")
            ->limit((($page-1)*$limit),$limit)
            ->field('a.*,b.username,b.mail,b.phone')
            ->order("sff_fy DESC, sff_uid desc")
            ->select();
        if($lists){
            $lists = $lists->toArray();
            foreach($lists as &$value){
                foreach ($afield as $k) {
                    $value[$k] = floatval($value[$k]);
                }
                $value['sff_paytime'] = empty($value['sff_paytime'])?'':date("Y-m-d H:i:s", $value['sff_paytime']);
                $value = Utils::hideUserInfo($value);
                if (strlen($value['sff_w']) == 6) {
                    // 获取指定年份的第 N 周的第一天和最后一天
                    $year = substr($value['sff_w'], 0, 4);
                    $week2 = substr($value['sff_w'], 4);
                    $startDate = Carbon::parse("{$year}-W{$week2}-1")->startOfDay()->format("Y-m-d");
                    $endDate = Carbon::parse("{$year}-W{$week2}-7")->endOfDay()->format("d");
                    $value['sff_w_text'] = "{$startDate}～{$endDate}";
                }
            }
        }
        if ($page == 1) {
            $stat = Db::name('third_stats_fyfy')->alias('a')->where($where)
                ->join('user b', "a.sff_uid=b.id")
                ->field('SUM(a.sff_fy) AS total,a.sff_payed')
                ->group("sff_payed")
                ->select();
            $sff_payed = ['无效', '未结算', '已结算'];
            $sff_payed_info = ['info', 'danger', 'success'];
            $rsps['stat'] = $stat->map(function ($item) use($sff_payed, $sff_payed_info) {
                $item['label'] = $sff_payed[$item['sff_payed']];
                $item['info'] = $sff_payed_info[$item['sff_payed']];
                return $item;
            });
        }
        $weekOpt = [];
        foreach ($week AS $w) {
            if (strlen($w) == 6) {
                // 获取指定年份的第 N 周的第一天和最后一天
                $year = substr($w, 0, 4);
                $week2 = substr($w, 4);
                $startDate = Carbon::parse("{$year}-W{$week2}-1")->startOfDay()->format("Y-m-d");
                $endDate = Carbon::parse("{$year}-W{$week2}-7")->endOfDay()->format("d");
                $label = "{$startDate}～{$endDate}";
            } else {
                $label = $w;
            }

            $weekOpt[] = ['label' => $label, "value" => $w];
        }
        $rsps['month'] = $month;
        $rsps['week'] = $weekOpt;
        $rsps['data'] = $lists;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function set()
    {
        $param=Request::param();
        $game_type = GameTypeModel::column("name","id");
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];

        $lists = Db::name('third_fyfy_lvlconfig')->where($where)
            ->order("tfc_id asc")->select();

        $rsps['data'] = $lists;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    /**
     * 导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function exportList($where = null, $whereStr = null,$name='代理返佣')
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.sff_fy';
        $params['positionOrder'] = 'DESC';
        $params['muid']=$this->uid;
        $utypeArr = [
            1=>'用户代理',
            2=>'内部顶级代理',
            3=>'内部代理下级',
        ];
        $payedArr = [
            0=>'无效(不予分佣)',
            1=>'未支付',
            2=>'已支付',
        ];
        $rlt = ReportExport::export(new rptParams($name,
            [
                new rptCol('sff_id', 'ID'),
                new rptCol('sff_uid','代理用户','app\common\service\ReportDownloadService::getUserName'),
                new rptCol('sff_w','返佣周','app\common\service\ReportDownloadService::getWeekText'),
                new rptCol('sff_utype','代理类型',rptCol::transType_kvtrans,$utypeArr),
                new rptCol('sff_payed','状态',rptCol::transType_kvtrans,$payedArr),
                'sff_base:占成',
                'sff_fy:收益',
                'sff_ks:客损',
                'sff_prelosetotal:上期负盈',
                'sff_losetotal:本期负盈',
                'sff_subout:外部代理返佣',
                'sff_payin:充值金额',
                'sff_payout:提现金额',
                'sff_payoutfee:提现手续费',
                'sff_yh:优惠额',
                'sff_back:返水额',
                'sff_tec:技术维护费',
                new rptCol('sff_paytime', '派送时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['sff_utype', 'sff_w'],//导出表名称使用的字段信息
            'a.sff_id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        return Db::name('third_stats_fyfy')->alias("a")->where($param['where'])->where($param['whereStr'])
            ->join('user b', "a.sff_uid=b.id")
            ->field('a.*,b.username,b.mail,b.phone');
    }
}