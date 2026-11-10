<?php

namespace app\api\factory_v2;

use app\common\model\AffiliateModel;
use app\common\model\AffiliatePlatformModel;
use app\common\model\AgentSellLogModel;
use app\common\model\NewsModel;
use app\common\model\UserLevelModel;
use app\common\model\UserModel;
use app\common\service\MemberService;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;

class AffiliateFactory
{
    private $param;//请求参数
    private $uid;//用户id
    private $user_detail;//用户信息
    private $lang;//语种

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
        $this->lang = strtoupper(Lang::getLangSet());
        $this->user_detail = UserModel::where("id", $this->uid)->find();
    }

    /**
     * 控制板
     * @return array
     */
    public function summary_run()
    {
        //获取邀请链接
        $invite_url = $this->getShareurl($this->user_detail['rec_code']);

        //所有推荐奖励
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $referral_reward = AgentSellLogModel::where($condition)->sum('money');

        //所有佣金
        $condition = [];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $brokerage = Db::name('third_stats_fyfy')
            ->where($condition)->sum('sff_fy');

        //用户等级获取
        $userLevelModel = new UserLevelModel();
        $level = $userLevelModel->where('id', $this->user_detail['level_id'])->field('id,level_name')->find();
        //获取配置文件
        $affiliate = AffiliateModel::where('lang', $this->lang)->find();
        $data = [
            'sta_today' => $this->_summary_data(date("d-m-Y")),
            'today_tip' => $affiliate['today_tip'],
            'all_tip' => $affiliate['all_tip'],
            'sta_all' => $this->_summary_data(),
            'team' => [
                'level' => $level,//用户等级
                'bonus' => $referral_reward + $brokerage,
                'referral_reward' => $referral_reward
            ],
            'share' => [
                'title' => $affiliate['share_title'],
                'platform' => $this->_getPlatform(json_decode($affiliate['share_platform'], true)),
                'intro' => $affiliate['share_intro'],
                'url' => $invite_url
            ],
            'service' => [
                'title' => $affiliate['service_title'],
                'info' => $affiliate['service_info'],
                'tip' => $affiliate['service_tip'],
                'contact' => $affiliate['service_contact'],
                'platform' => $this->_getPlatform($affiliate['service_platform'])
            ],
            'rule' => [
                'title' => $affiliate['rule_title'],
                'info' => $affiliate['rule_info'],
                'brokerage_min' => $affiliate['brokerage_min'],
                'brokerage_max' => $affiliate['brokerage_max'],
                'brokerage_ratio' => $affiliate['brokerage_ratio'],
                'pop_info' => $affiliate['pop_info'],
                'pop_explain' => $affiliate['pop_explain'],
            ]
        ];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 获取推广链接
     * @param $recCode
     * @return string
     */
    private function getShareurl($recCode){
        //是否关闭邀请链接
        if( env('IS_CLOSE_INVITE_URL', 0) == 1 ){
            return '';
        }
        //获取域名
        $domainEnv = env('SHARE_URL', '');
        $domain = getShareDomain();
        if( $domainEnv ){
            $url = $domain.'/register?share_code='.$recCode;
        }else{
            $url = $domain."/#/?share_code=".$recCode . "&modal=LoginRegistration&data=tab-1";
        }
        return $url;
    }
    /**
     * 获取 报告列表
     * @return array
     */
    public function report_run()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $day = date("Y-m-d");
        //获取指定凌晨1点时间戳
        $start_time = strtotime($day) - ((($page - 1) * $limit * (3600 * 24))) + 3600;
        $end_time = $start_time - (3600 * 24 * $limit);
        $list = [];
        for ($time = $start_time; $time > $end_time; $time = $time - (3600 * 24)) {
            $list[] = $this->_report_data(date("d-m-Y", $time));
        }
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 常见问题列表
     * @return array
     */
    public function faq_run()
    {
        $affiliate = AffiliateModel::where('lang', $this->lang)->find();
        $issues_list = [];
        if (!empty($affiliate['issues_list'])) {
            $issues_list = json_decode($affiliate['issues_list'], true);
            $data = NewsModel::whereIn('id', $issues_list)->field('id,lang,news_title,news_content')->select();
            foreach ($data as $key => &$value) {
                $value['add_time'] = date("Y-m-d", $value['add_time']);
//                $value['news_content'] = mb_substr(strip_tags($value['news_content']), 0, 100);
            }
            $issues_list = $data;
        }

        $data = [
            'issues_list' => $issues_list,
            'rule' => [
                'title' => $affiliate['rule_title'],
                'billing' => $affiliate['calculate_title'],
                'info' => $affiliate['rule_info'],
                'brokerage_min' => $affiliate['brokerage_min'],
                'brokerage_max' => $affiliate['brokerage_max'],
                'brokerage_ratio' => $affiliate['brokerage_ratio'],
                'pop_info' => $affiliate['pop_info'],
                'pop_explain' => $affiliate['pop_explain'],
            ]
        ];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 教程详情
     * @return array
     */
    public function course_run()
    {
        $affiliate = AffiliateModel::where('lang', $this->lang)->find();
        $data = [
            'calculate_title' => $affiliate['calculate_title'],
            'calculate_text' => $affiliate['calculate_text']//教程内容 富文本
        ];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 控制面板数据
     * @param string $day
     * @return array
     */
    private function _summary_data($day = '')
    {
        if (empty($day)) {
            //团队指定日期注册人数
            $register = UserModel::where('tree', 'like', '%/' . $this->uid . '/%')->where('rec_level', '>', $this->user_detail->rec_level)->count();

            //新玩家总数
            $condition = [];
            $condition[] = ['u.rec_id', '=', $this->uid];
            $condition[] = ['uo.first', '=', 1];
            $condition[] = ['uo.status', 'in', '3,4'];
            $player = UserModel::alias('u')
                ->join('dq_user_offline uo', 'u.id=uo.uid')
                ->group('uo.uid')
                ->where($condition)->count();

            //有效投注
            $condition = [];
            $condition[] = ['u.rec_id', '=', $this->uid];
            //转化成时区表
            $table = \app\common\service\BetService::getThirdStatsTable();
            $bet = UserModel::alias('u')->join($table['stats_global'].' tsg', 'tsg.su_uid=u.id')->where($condition)->sum('tsg.su_valid_bet');
            //团队总收益
            $condition = [];
            $condition[] = ['sff_w', '=', date("Ymd", strtotime($day))];
            $condition[] = ['sff_uid', '=', $this->uid];
            $condition[] = ['sff_payed', '=', 2];
            $team = Db::name('third_stats_fyfy')->where($condition)->sum('sff_fy');
            return [
                'register' => $register,//注册人数
                'player' => $player,//新玩家
                'bet' => $bet,//有效投注
                'team' => $team//团队奖励
            ];
        }
        $startTime = strtotime($day);
        $endTime = strtotime($day) + (3600 * 24) - 1;
        $condition = [];
        $condition[] = ['add_time', 'between', [$startTime, $endTime]];
        //团队指定日期注册人数
        $register = UserModel::where('tree', 'like', '%/' . $this->uid . '/%')->where($condition)->where('rec_level', '>', $this->user_detail->rec_level)->count();

        //新玩家总数
        $condition = [];
        $condition[] = ['u.rec_id', '=', $this->uid];
        $condition[] = ['uo.first', '=', 1];
        $condition[] = ['uo.status', 'in', '3,4'];
        $condition[] = ['uo.add_time', 'between', [$startTime, $endTime]];
        $player = UserModel::alias('u')
            ->join('dq_user_offline uo', 'u.id=uo.uid')
            ->group('uo.uid')
            ->where($condition)->count();
        //指定日期有效投注金额
        $condition = [];
        $condition[] = ['u.rec_id', '=', $this->uid];
        $condition[] = ['tsg.su_dt', '=', date("Ymd", strtotime($day))];
        $table = \app\common\service\BetService::getThirdStatsTable();
        $bet = UserModel::alias('u')->join($table['stats_global'].' tsg', 'tsg.su_uid=u.id')->where($condition)->sum('tsg.su_valid_bet');


        //指定日期团队收益
        $condition = [];
        $condition[] = ['sff_w', '=', date("Ymd", strtotime($day))];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $team = Db::name('third_stats_fyfy')->where($condition)->sum('sff_fy');
        return [
            'register' => $register,//注册人数
            'player' => $player,//新玩家
            'bet' => $bet,//有效投注
            'team' => $team//团队奖励
        ];
    }

    /**
     * 获取报表数据
     * @param string $day
     * @return array
     */
    private function _report_data($day = '')
    {
        $startTime = strtotime($day);
        $endTime = strtotime($day) + (3600 * 24) - 1;
        $condition = [];
        $condition[] = ['add_time', 'between', [$startTime, $endTime]];
        //团队指定日期注册人数
        $register = UserModel::where('tree', 'like', '%/' . $this->uid . '/%')->where($condition)->where('rec_level', '>', $this->user_detail->rec_level)->count();

        //指定日期有效投注金额
        $condition = [];
        $condition[] = ['u.rec_id', '=', $this->uid];
        $condition[] = ['tsg.su_dt', '=', date("Ymd", strtotime($day))];
        $table = \app\common\service\BetService::getThirdStatsTable();
        $bet = UserModel::alias('u')->join($table['stats_global'].' tsg', 'tsg.su_uid=u.id')->where($condition)->sum('tsg.su_valid_bet');

        //指定日期推荐奖励
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $rec_award = AgentSellLogModel::where($condition)->sum('money');

        //指定日期团队收益
        $condition = [];
        $condition[] = ['sff_w', '=', date("Ymd", strtotime($day))];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $team = Db::name('third_stats_fyfy')->where($condition)->sum('sff_fy');

//        if (empty($register + $bet + $rec_award + $team)) {
//            //如果当天没有数据则不显示
//            return [];
//        }
        return [
            'day' => $day,//统计日期
            'register' => $register,//注册人数
            'bet' => $bet,//有效投注
            'rec_award' => $rec_award,//推荐奖励
            'team' => $team//团队奖励
        ];
    }

    /**
     * 获取平台信息
     * @param array $ids
     * @return AffiliatePlatformModel|AffiliatePlatformModel[]|array|mixed|\think\Collection|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _getPlatform($ids = [])
    {
        if (empty($ids)) {
            return [];
        }
        if (is_array($ids)) {
            $platform_list = AffiliatePlatformModel::whereIn('id', $ids)->select();
        } else {
            $platform_list = AffiliatePlatformModel::where('id', $ids)->find();
        }
        return $platform_list;
    }
}