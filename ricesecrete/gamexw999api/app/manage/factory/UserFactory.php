<?php
namespace app\manage\factory;
use app\common\service\GameService;
use app\common\Utils;
use app\api\redis\ApiRedis;
use app\manage\controller\Base;
use think\facade\{Request, Db};
use app\common\lib\{ReportExport, Respons, rptCol, rptParams, Uploads, Util};
use think\Exception;
use app\common\model\{DefaultHeadModel,
    MailNotifyTemplateModel as MailNotifyTemplateModel,
    TagModel,
    UserGoogleModel,
    UserModel,
    UserLoginModel,
    UserLevelModel,
    UserAgentModel,
    UserAdminLogModel,
    ActionApplyModel,
    ActionModel,
    UserCkModel,
    UserOfflineModel,
    UserTagModel,
    UserWithdrawalModel,
    AgentRakeBackModel,
    ArtificialBalanceModel};
use app\common\service\MemberService;
use app\common\redis\CommomRedis;

class UserFactory{

    private $uid;//用户id
    //验证
    public function __construct($token){
        $this->uid=$token->uid;
        $this->name=$token->name;
    }

    public function list() {
        $param=Request::param();
        $UserModel =new UserModel();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $where1 = '1=1';
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }
        if (isset($param['username']) && $param['username']!='') {
            if(is_numeric($param['username'])){
                $where[]=["a.id|a.rec_code|a.phone", "=", $param['username']];
            }else{
                $where[]=["a.rec_code|a.username|a.mail|a.phone", "=", $param['username']];
            }
        }
        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[]=["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[]=["a.id", "IN", function($query) use($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        }
        //是否实名
        if ( isset($param['is_verify']) && $param['is_verify'] !== '' ){
            $statusQuery = function($query) use($param) {
                return $query->name('user_verify')->where('status', '=', 2)->field('uid');
            };
            if( $param['is_verify'] == 1 ){
                $where[]=["a.id", "IN", $statusQuery];
            }else{
                $where[]=["a.id", "NOT IN", $statusQuery];
            }
        }
        if (isset($param['level']) && $param['level']!='') {
            $where[]=["a.level_id", "=", $param['level']];
        }
        if (isset($param['line_id']) && $param['line_id']!=='') {
            $where[]=["a.line_id", "=", $param['line_id']];
        }
        if (isset($param['end_login_ip']) && $param['end_login_ip']!='') {
            $where[]=["a.end_login_ip|a.reg_ip","like","%".$param['end_login_ip']."%"];
        }
        if (isset($param['phone_bind_status']) && $param['phone_bind_status']!='') {
            $where[]=["a.phone_bind_status", "=", $param['phone_bind_status']];
        }
        if (isset($param['mail_bind_status']) && $param['mail_bind_status']!='') {
            $where[]=["a.mail_bind_status", "=", $param['mail_bind_status']];
        }
        if (isset($param['end_ip_count']) && $param['end_ip_count']!='') {
            if (strexists($param['end_ip_count'], "-")) {
                $arr = explode("-", $param['end_ip_count']);
                if (isset($arr[0]) && $arr[0] > 0) {
                    $where[]=["a.login_ip_count", ">=", intval($arr[0])];
                }
                if (isset($arr[1]) && $arr[1] > 0) {
                    $where[]=["a.login_ip_count", "<=", intval($arr[1])];
                }
            } else {
                $where[]=["a.login_ip_count", "=", $param['end_ip_count']];
            }
        }
        if (isset($param['name']) && $param['name']!='') {
            $where[]=["a.username|a.mail|a.phone","like","%".$param['name']."%"];
        }
        if( isset($param['is_recharge']) && $param['is_recharge'] !== '' ){
            if( $param['is_recharge'] == 1 ){
                $where[] = ['a.sc_balance', '>', 0];
            }else{
                $where[] = ['a.sc_balance', '=', 0];
            }
        }

        if( isset($param['is_withdrawal']) && $param['is_withdrawal'] !== '' ){
            $UserWithdrawalModel = new UserWithdrawalModel();
            $user_ids = $UserWithdrawalModel->where('status', 'in', [3, 4])->where('arrival_price', '>', 0)->group('uid')->select()->column('uid');
            if($param['is_withdrawal'] == 1 ){
                $where[] = ['a.id', 'in', implode(',',$user_ids)];
            }else{
                $where[] = ['a.id', 'not in', implode(',',$user_ids)];
            }
        }
        //首充或复充
        $firstField = '';
        if( isset($param['first']) && $param['first'] !== '' ){
            $_condition = [];
            $_condition[] = ['status', 'in', [3, 4]];
            if( $param['first'] == 1 ){
                //查询复充用户sql
                $_condition[] = ['first', '=', 1];
                $userOfflineSql = UserOfflineModel::where($_condition)->group('uid')->field('uid')->buildSql();
                $where1 .= ' and a.id in '.$userOfflineSql;
                $firstField = 'sc_balance';
            }else{
                //查询复充用户sql
                $_condition[] = ['first', '<>', 1];
                $userOfflineSql = UserOfflineModel::where($_condition)->group('uid')->field('uid')->buildSql();
                $where1 .= ' and a.id in '.$userOfflineSql;
                $firstField = 'xc_balance';
            }
        }

        $whereStr1 = '';
        if( isset($param['min_recharge']) && $param['min_recharge'] !== '' && isset($param['max_recharge']) && $param['max_recharge'] !== '' ){
            //有最大金额和最小金额
            if( $firstField ){
                $where[] = [$firstField, 'between', [$param['min_recharge'], $param['max_recharge']]];
            }else{
                $sql = UserOfflineModel::where('status', 'in', '3,4')
                    ->having( 'sum(arrival_price) between '.$param['min_recharge'].' and '.$param['max_recharge'])
                    ->group('uid')->field('uid')->buildSql();
                $whereStr1 = 'a.id in('.$sql.')';
            }
        }elseif(isset($param['min_recharge']) && $param['min_recharge'] !== ''){
            //只有最小金额
            if( $firstField ){
                $where[] = [$firstField, '>=', $param['min_recharge']];
            }else{
                $sql = UserOfflineModel::where('status', 'in', '3,4')
                    ->having('sum(arrival_price) >= ' . $param['min_recharge'])
                    ->group('uid')->field('uid')->buildSql();
                $whereStr1 = 'a.id in('.$sql.')';
            }
        }elseif(isset($param['max_recharge']) && $param['max_recharge'] !== ''){
            //只有最大金额
            if( $firstField ){
                $where[] = [$firstField, '<=', $param['max_recharge']];
            }else{
                $sql = UserOfflineModel::where('status', 'in', '3,4')
                    ->having('sum(arrival_price) <= ' . $param['max_recharge'])
                    ->group('uid')->field('uid')->buildSql();
                $whereStr1 = 'a.id in('.$sql.')';
            }
        }

        if (isset($param['rec_id']) && $param['rec_id']!='') {
            //查询rec_id
            $sqlwhere = [];
            if (is_numeric($param['rec_id'])) {
                $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
            } else {
                $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
            }
            $rec_info = UserModel::where($sqlwhere)->find();
            if (empty($rec_info)) {
                $result['code'] = 0;
                $result['msg'] = '上级UID或邀请码有误';
                return Respons::run($result);
            }
            if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                $where[]=["a.tree", "like", "{$rec_info['tree']}%"];
            } else {
                $where[]=["a.rec_id", "=", $rec_info['id']];
            }
        }
        if (isset($param['rec_top_id']) && $param['rec_top_id']!='') {
            //查询rec_id
            $sqlwhere = [];
            if (is_numeric($param['rec_top_id'])) {
                $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_top_id']];
            } else {
                $sqlwhere[] = ["mail|username", "=", $param['rec_top_id']];
            }
            $rec_top_id = UserModel::where($sqlwhere)->field("id")->value('id');
            if(!empty($rec_top_id)){
                $where[]=["a.tree", "like", "/{$rec_top_id}/%"];
            }
        }



        if (isset($param['ip']) && $param['ip'] > 0) {
            $sql = UserLoginModel::where(['login_ip'=>$param['ip']])->field('uid')->buildSql();
            $where1 .= " and a.id in(".$sql.")";
        }

        if (isset($param['logins']) && $param['logins'] != '') {
            $where[]=["a.login_time","<", time() - 86400 * $param['logins']];
            $where[]=["a.login_time",">", 0];
        }
        //是否参与活动
        if( isset($param['is_join_activity']) && $param['is_join_activity'] !== '' ){
            $activityWhere = [];
            if( isset($param['activity_type']) && $param['activity_type'] !== '' ){
                $activityWhere[] = ['aa.base_type', '=', $param['activity_type']];
            }
            $activitySql = ActionApplyModel::alias('aaa')
                ->join('action aa', 'aa.id=aaa.action_id')
                ->where($activityWhere)
                ->field('aaa.uid')
                ->buildSql();
            if( $param['is_join_activity'] == 1 ){
                //参与过活动
                $where1 .= ' and a.id in('.$activitySql.')';
            }else{
                //未参与过活动
                $where1 .= ' and a.id not in('.$activitySql.')';
            }
        }
        //有无lineID
        if (isset($param['has_line_id']) && $param['has_line_id']!=='') {
            if( $param['has_line_id'] == 1 ){
                //有
                $where[] = ['a.line_id', '<>', ''];
            }else{
                //无
                $where1 .= ' and (a.line_id is null or a.line_id = "")';
            }
        }

        if (isset($param['login_time']) && $param['login_time']!='') {
            $date = explode(' - ',$param['login_time']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        //是否是选择所有用户
        if( isset($param['select_all_user']) && $param['select_all_user'] !== '' ){
            $rsps['data'] = $UserModel->alias("a")
                ->where($where)->where($where1)
                ->order($order)->column($param['select_all_user']);
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        $UserLevelModel = new UserLevelModel();
        $user_level_data = $UserLevelModel->column('level_name', 'id');

        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportList($where,$where1,$whereStr1);
//            if (empty($where) && empty($where1)) {
//                $result['code'] = 0;
//                $result['msg'] = '导出数据太大，请增加更多筛选条件';
//                return Respons::run($result);
//            }
//            $rsps['data'] = $UserModel
//                ->alias("a")
//                ->where($where)
//                ->where($whereStr1)
//                ->where($where1)
//                ->order($order)
//                ->field("a.id,a.username,a.login_ip_count,a.mail,a.lebal,a.rec_code,a.add_time,a.login_time,a.balance,a.phone,a.qh,a.tree,"
//                    ."a.set_fund_money,a.rec_id,a.live_fund_money,a.integral,a.level_id,a.status,a.end_login_ip,a.reg_ip,a.original_level_id,a.line_id,a.phone_bind_status,a.mail_bind_status")
//                ->select();
        } else {
            $rsps['data'] = $UserModel
                ->alias("a")
                ->where($where)
                ->where($whereStr1)
                ->where($where1)
                ->order($order)
                ->field("a.id,a.username,a.login_ip_count,a.mail,a.lebal,a.rec_code,a.add_time,a.login_time,a.balance,a.cjbalance,a.phone,a.qh,a.tree,"
                    ."a.set_fund_money,a.rec_id,a.live_fund_money,a.integral,a.level_id,a.status,a.end_login_ip,a.reg_ip,a.phone_bind_status,a.mail_bind_status,a.savings,a.cjsavings,"
                    ."(select id from dq_user where rec_id=a.id limit 1) as count_pnumber,a.original_level_id,a.line_id")
                ->paginate($limit,false,['query' => $param]);
        }
        $rec_names = [];
        foreach($rsps['data'] as $k => $value){
            $rsps['data'][$k]['thirds_balance'] = MemberService::getUserThirdsBalance($value['id']);
            $rsps['data'][$k]['recharge'] = MemberService::getRechargeByUid($value['id']);
            $rsps['data'][$k]['withdraw'] = MemberService::getWithdrawByUid($value['id']);
            $rsps['data'][$k]['level_name'] = $user_level_data[$value['level_id']] ?? $value['level_id'];
            $rsps['data'][$k]['mail'] = $value['mail'] == null ||  $value['mail'] == 'undefined' ? '' : $value['mail'];
            $rsps['data'][$k]['phone'] = $value['phone'] == null ||  $value['phone'] == 'undefined' ? '' : $value['phone'];
            $rsps['data'][$k]['add_time'] = empty($value['add_time']) ? '':date("Y-m-d H:i:s",$value['add_time']);
            $rsps['data'][$k]['login_time'] = empty($value['login_time']) ? '':date("Y-m-d H:i:s",$value['login_time']);
            $rsps['data'][$k]['rec_top_user_id'] = MemberService::getTopAgentByTree($value['tree'], true);
            $rsps['data'][$k]['original_vip_name'] = '';
            if( $value['original_level_id'] > 0 ){
                $rsps['data'][$k]['original_vip_name'] = UserLevelModel::where('id', '=', $value['original_level_id'])->value('level_name');
            }

            if (isset($param['export']) && $param['export'] == 0) {
//                $rsps['data'][$k]['login_rep'] = MemberService::isRepeatByIP($value['end_login_ip']);
//                if($value['reg_ip'] == $value['end_login_ip']){
//                    $rsps['data'][$k]['reg_rep'] = $value['login_rep'];
//                }else{
//                    $rsps['data'][$k]['reg_rep'] = MemberService::isRepeatByIP($value['reg_ip']);
//                }
                $rsps['data'][$k]['tags'] = MemberService::getTagByUid($value['id']);
                $rsps['data'][$k]['reg_ip'] = $value['reg_ip'] . "<br>" . ip2location($value['reg_ip']);
                $rsps['data'][$k]['end_login_ip'] = !empty($value['end_login_ip'])?$value['end_login_ip'] . "<br>" . ip2location($value['end_login_ip']):'';

//                if (!isset($rec_names[$value['rec_id']])) {
//                    $rec_names[$value['rec_id']] = MemberService::getUsernameByUid($value['rec_id']);
//                }
//                if (!isset($rec_names[$rsps['data'][$k]['rec_top_user_id']])) {
//                    $rec_names[$rsps['data'][$k]['rec_top_user_id']] = MemberService::getUsernameByUid($rsps['data'][$k]['rec_top_user_id']);
//                }
                $rsps['data'][$k]['rec_top_user'] = "UID:{$rsps['data'][$k]['rec_top_user_id']}";
                $rsps['data'][$k]['rec_name'] = "UID:{$value['rec_id']}";
            } else {
                $rsps['data'][$k]['rec_name'] = $value['rec_id'];
            }
            $rsps['data'][$k]['fund_money'] = $value['set_fund_money'] + $value['live_fund_money'];
            $rsps['data'][$k] = Utils::hideUserInfo($rsps['data'][$k]);
        }

        if (isset($param['export']) && $param['export'] == 1) {
            $list = $rsps['data'];
            $checkStatus = [0 => '未绑定', 1 => '已验证', 2 => '未验证'];
            foreach ($list as $value) {
                $value['level_name'] = $user_level_data[$value['level_id']];
                $value['lebal']      = $value['lebal'] == 1 ? lang('普通用户') : lang('测试用户');
                $value['status']     = $value['status'] == 1 ? lang('启用') : lang('禁用');
                $value['phone_bind_status'] = $checkStatus[$value['phone_bind_status']];
                $value['mail_bind_status'] = $checkStatus[$value['mail_bind_status']];
            }

            $header = [
                'id' => lang('UID'),
                'line_id' => lang('Line ID'),
                'phone' => lang('手机号'),
                'phone_bind_status' => lang('号码状态'),
                'qh' => lang('区号'),
                'mail' => lang('邮箱'),
                'mail_bind_status' => lang('邮箱状态'),
                'lebal' => lang('用户标签'),
                'rec_code' => lang('推广码'),
                'rec_name' => lang('上级代理UID'),
                'rec_top_user_id' => lang('顶级代理UID'),
                'level_name' => lang('会员等级'),
                'recharge' => lang('总充值'),
                'withdraw' => lang('总提现'),
                'add_time' => lang('注册时间'),
                'reg_ip' => lang('注册ip'),
                'login_time' => lang('登录时间'),
                'end_login_ip' => lang('登录ip'),
                'balance' => lang('余额'),
                'cjbalance' => lang('彩金余额'),
                'status' => lang('状态'),
            ];
            $file_name = genCsv($header, $list,lang('会员记录').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($where)) {
                Utils::addOptLogByHandle('会员记录导出');
            }
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }
        $tags = [
            ['label' => '一般用户', 'value' => -1],
            ['label' => '测试用户', 'value' => -2],
            ['label' => '三方登录用户', 'value' => -3],
        ];
//        $ApiRedis = new ApiRedis();
//        $sys = $ApiRedis->systemInfo();
//        $email_arr = [
//            [
//                'label' => $sys['verify_title'] ?? '',
//                'value' => 'verify_title',
//            ],
//            [
//                'label' => $sys['off_title'] ?? '',
//                'value' => 'off_title',
//            ],
//            [
//                'label' => $sys['with_title'] ?? '',
//                'value' => 'with_title',
//            ],
//            [
//                'label' => $sys['template1_title'] ?? '',
//                'value' => 'template1_title',
//            ],
//            [
//                'label' => $sys['template2_title'] ?? '',
//                'value' => 'template2_title',
//            ],
//            [
//                'label' => $sys['template3_title'] ?? '',
//                'value' => 'template3_title',
//            ],
//        ];
        $MailNotifyTemplateModel = app()->make(MailNotifyTemplateModel::class);
        $m_w = [];
        $m_w[] = ['state', '=', 1];
        $m_w[] = ['sign', '=', 'admin'];
        $email_arr = $MailNotifyTemplateModel->where($m_w)->field('id, title')->select();
        $email_arr2 = [];
        foreach ($email_arr as $kk => $vv){
            $email_arr2[$kk]['label'] = $vv['title'];
            $email_arr2[$kk]['value'] = $vv['id'];
//            if(empty($vv['label'])){
//                unset($email_arr[$kk]);
//            }
        }
        $action_base_type = config('activity.action_base_type');
        $rsps['activity_type'] = Utils::getOptionsByConfig($action_base_type);
        $rsps['code']=1;
        $rsps['tags'] = array_merge($tags, Utils::getOptions('tag'));
        $rsps['checkbox_tags'] = Utils::getOptions('tag');
        $rsps['user_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['email_arr'] = $email_arr2;

        return Respons::run($rsps);
    }

    //添加
    public function add()
    {
        $param = Request::param();
        $info = $param;
        $info['phone']=str_replace(' ','',$info['phone']);
        $info['mail']=str_replace(' ','',$info['mail']); 
        $info['username']=str_replace(' ','',$info['username']);
        try {
            validate(\app\common\validate\UserValidate::class)->scene('create')->check($info);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败 输出错误信息
            return Respons::run(0, [], $e->getError());
        }

        if (!checkString($info['password'])) {
            return Respons::run(0, [], "密码只能数字和字母组合");
        }

        if ($info['username'] == $info['password']) {
            return Respons::run(0, [], "密码不能与用户名相同");
        }
        if (empty($info['mail']) && empty($info['phone'])) {
            return Respons::run(0,[], "邮箱和手机号必填一个");
        }
        $userIds = $info['userTagIds'];
        unset($info['userTagIds']);

        Db::startTrans();
        $UserModel = new UserModel();
        try {
            $UserAgentModel = new UserAgentModel();
            $tree           = "";
            $proxy_tree     = "";
            $is_agent       = $info['is_agent'];
            if (!empty($info['top_code'])) {
                $top       = $UserModel->where("rec_code", $info['top_code'])->find();
                if (empty($top)) {
                    throw new \think\Exception(lang("上级推广码错误"), 0);
                }
                $tree = $top['tree'];
                $useragent = $UserAgentModel->where(['uid' => $top['id']])->find();
                if (!empty($useragent)) {
                    $proxy_tree = $useragent['proxy_tree'];
                    if ($useragent['type'] == 0 && $info['agent_type'] == 1) {
                        throw new \think\Exception(lang("外部代理号不能开通内部代理下属账号！"), 0);
                    }
                    if (isset($info['rate']) && ($useragent['rate'] * 100 < $info['rate'])) {
                        throw new \think\Exception(lang("下级返佣比例不能超过上级！"), 0);
                    } else {
                        $info['rate'] = 0;
                    }
//                    if($useragent['proxy_level'] >= 3 && $info['agent_type'] == 1){
//                        throw new \think\Exception(lang("内部账号只能开通三级，当前账号属第三级，无法开通下级账号！"), 0);
//                    }
                } else {
                    if ($is_agent == 1) {
                        throw new \think\Exception(lang("不是代理无法开通下级代理号！"), 0);
                    }
                }
            }
            $user = $UserModel->where("username", $info['username'])->find();
            if (!empty($user)) {
                throw new \think\Exception(lang("账号已存在！"), 0);
            }
            if (!empty($info['mail'])) {
                $mail=$UserModel->where("mail",$info['mail'])->find();
                $info['mail_bind_status'] = 2;
                if (!empty($mail)) {
                    throw new \think\Exception(lang("邮箱已存在！"), 0);
                }
            } else {
                unset($info['mail']);
            }
            if (!empty($info['phone'])) {
                $phone=$UserModel->where("phone",$info['phone'])->find();
                $info['phone_bind_status'] = 2;
                if (!empty($phone)) {
                    throw new \think\Exception(lang("手机号已存在！"), 0);
                }
            } else {
                unset($info['phone']);
            }

            $top_code   = $info['top_code'];
            $agent_type = $info['agent_type'];
            $agent_rate = isset($info['rate']) ? floatval($info['rate']) / 100 : 0;
            $webSiteAuth = $info['website_auth'] ?? 0;
            unset($info['top_code']);
            unset($info['rate']);
            unset($info['is_agent']);
            unset($info['agent_type']);
            unset($info['website_auth']);
            $info['add_time'] = time();
            $info['reg_ip']   = Util::get_ip();
            $info['password'] = Util::userMd5Password($info['password']);
            $info['head_img'] = DefaultHeadModel::getHeadOne();//默认头像

            $id = $UserModel->insertGetId($info);
            if (empty($tree)) {
                $tree = "/" . $id . "/";
            } else {
                $tree = $tree . $id . "/";
            }

            $tree1            = explode("/", $tree);
            $new_tree         = array_filter($tree1);
            $rec_id           = $new_tree[count($new_tree) - 1] ?? 0;
            $rec_id2          = $new_tree[count($new_tree) - 2] ?? 0;
            $rec_id3          = $new_tree[count($new_tree) - 3] ?? 0;
            $upd['id']        = $id;
            $upd['tree']      = $tree;
            $upd['rec_id']    = $rec_id;
            $upd['rec_id2']   = $rec_id2;
            $upd['rec_id3']   = $rec_id3;
            $upd['rec_level'] = count($new_tree);
            $upd['rec_code']  = Util::rec_code($id);
            $UserModel->update($upd);

            if ($is_agent == 1) {
                if ($agent_type == 1) {
                    if (empty($proxy_tree)) {
                        $proxy_tree = "/" . $id . "/";
                    } else {
                        $proxy_tree = $proxy_tree . $id . "/";
                    }

                    $proxy_tree1    = explode("/", $proxy_tree);
                    $new_proxy_tree = array_filter($proxy_tree1);
                    $proxy_upuid    = $new_proxy_tree[count($new_proxy_tree) - 1] ?? 0;
                    $proxy_rec_id2  = $new_proxy_tree[count($new_proxy_tree) - 2] ?? 0;
                    $proxy_rec_id3  = $new_proxy_tree[count($new_proxy_tree) - 3] ?? 0;
                    if ($proxy_rec_id3 > 0) {
                        $proxy_topuid = $proxy_rec_id3;
                    } else if ($proxy_rec_id2 > 0) {
                        $proxy_topuid = $proxy_rec_id2;
                    } else {
                        $proxy_topuid = ($proxy_upuid == 0) ? $id : $proxy_upuid;
                    }
                } else {
                    $proxy_tree     = '';
                    $proxy_topuid   = $proxy_upuid = 0;
                    $new_proxy_tree = [];
                }
                //开通微商
                $add_agent['uid']          = $id;
                $add_agent['apply_ip']     = Util::get_ip();
                $add_agent['apply_time']   = time();
                $add_agent['status']       = 3;
                $add_agent['tree']         = $tree;
                $add_agent['rec_id']       = $rec_id;
                $add_agent['proxy_upuid']  = $proxy_upuid;
                $add_agent['proxy_topuid'] = $proxy_topuid;
                $add_agent['proxy_level']  = count($new_proxy_tree);
                $add_agent['proxy_tree']   = $proxy_tree;
                $add_agent['type']         = $agent_type;
                $add_agent['admin_name']   = $this->name;
                $add_agent['rate']   = $agent_rate;
                $add_agent['website_auth']   = $webSiteAuth;
                $UserAgentModel->insertGetId($add_agent);
            }
            //添加用户标签
            if( $userIds ){
                $userTagSaveAll = [];
                //添加用户标签
                foreach ($userIds as $v){
                    if( !$v ){
                        continue;
                    }
                    $userTagSaveAll[] = [
                        'uid' => $id,
                        'tag_id' => $v,
                        'create_time' => time(),
                        'update_time' => time(),
                    ];
                }
                if( $userTagSaveAll ){
                    (new UserTagModel())->saveAll($userTagSaveAll);
                    MemberService::clearCacheTagByUid($id);
                }
            }
            UserAdminLogModel::add_log($id, "会员添加", $this->name);
            // 提交事务
            Db::commit();
            $info['id']       = $id;
            $info['is_agent'] = $is_agent;
            $info['top_code'] = $top_code;
            return Respons::run($id ? 1 : 0, '', '', '', $info, $UserModel, lang('会员添加'));
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return Respons::run(0, [], $e->getMessage());
        }
    }

    public function edit()
    {
        $param = Request::param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        //用户标签
        $userIds = $param['userTagIds'];
        unset($param['userTagIds']);
        $info = $param;
        $info['phone']=str_replace(' ','',$info['phone']);
        $info['mail']=str_replace(' ','',$info['mail']);
        $UserModel = new UserModel();
        $data      = $UserModel->where('id', $info['id'])->withoutField('rec_id')->find();
        if (empty($data)) {
            return Respons::run(0, [], '用户信息不存在');
        }
        if (!empty($info['new_password'])) {
            $upd['password'] = Util::userMd5Password($info['new_password']);
        }
        if (!empty($info['withdrawal_pwd'])) {
            $upd['withdrawal_psd'] = Util::winMd5Password($info['withdrawal_pwd']);
        }
        if (!empty($info['is_check_phone'])) $upd['is_check_phone'] = $info['is_check_phone'];
        if (!empty($info['phone'])){
            $upd['phone'] = $info['phone'];
            $phone=UserModel::where('id', '<>', $info['id'])->where("phone", $info['phone'])->find();
            if (!empty($phone)) {
                return Respons::run(0, [], '手机号已存在');
            }
        }
        if (!empty($info['qh'])) $upd['qh'] = $info['qh'];
        if (!empty($info['mail'])){
            $upd['mail'] = $info['mail'];
            $phone=UserModel::where('id', '<>', $info['id'])->where("mail", $info['mail'])->find();
            if (!empty($phone)) {
                return Respons::run(0, [], '邮箱已存在');
            }
        }
        if (!empty($info['real_name'])) $upd['real_name'] = $info['real_name'];
        if (!empty($info['limit_min'])) $upd['limit_min'] = $info['limit_min'];
        if (!empty($info['limit_max'])) $upd['limit_max'] = $info['limit_max'];
        if (!empty($info['limit_sum_max'])) $upd['limit_sum_max'] = $info['limit_sum_max'];
        if (!empty($info['lebal'])) $upd['lebal'] = $info['lebal'];
        if (!empty($info['level_id'])) $upd['level_id'] = $info['level_id'];
        if (empty($info['phone_bind_status'])){
            $upd['phone_bind_status'] = 0;
        }else{
            $upd['phone_bind_status'] = $info['phone_bind_status'];
        }
        if (empty($info['mail_bind_status'])){
            $upd['mail_bind_status'] = 0;
        }else{
            $upd['mail_bind_status'] = $info['mail_bind_status'];
        }
        if (isset($info['status']) && $info['status'] !== '') $upd['status'] = $info['status'] == 1 ? 1 : 0;
        if (isset($info['remark'])) $upd['remark'] = $info['remark'];
        if (!empty($info['sc_date'])) $upd['sc_date'] = $info['sc_date'];
        $upd['id']             = $info['id'];


        $CommomRedis=new CommomRedis();
        $CommomRedis->set("userPwdErrorCount" . $info['id'], 0, 86400);
        $rs        = $UserModel->update($upd);

        //删除所有用户标签
        UserTagModel::where(['uid' => $info['id']])->delete();
        //添加用户标签
        if( $userIds ){
            $userTagSaveAll = [];
            //添加用户标签
            foreach ($userIds as $v){
                if( !$v ){
                    continue;
                }
                $userTagSaveAll[] = [
                    'uid' => $info['id'],
                    'tag_id' => $v,
                    'create_time' => time(),
                    'update_time' => time(),
                ];
            }
            if( $userTagSaveAll ){
                (new UserTagModel())->saveAll($userTagSaveAll);
            }
        }
        MemberService::clearCacheTagByUid($info['id']);

        //设置会员登出
        if ($rs && $info['status'] != '1') {
            $CommomRedis->set($data['usertokenkey'], 'loginout', 600);
        }

        UserAdminLogModel::add_log($upd['id'], lang("会员修改"), $this->name);
        return Respons::run(1, '', '', $data, $info, $UserModel, lang('会员修改'));
    }

    /**
     * 批量修改用户状态
     * @return \think\response\Json
     * @throws Exception
     */
    public function status(){
        $param = Request::param();
        if (!isset($param['ids'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        if(!in_array($param['status'],[0,1])){
            throw new \think\Exception(lang("用户状态有误"), 0);
        }
        $UserModel = new UserModel();
        $up_data = [
            'status'=>$param['status']
        ];
        $ret = $UserModel->whereIn('id',$param['ids'])->update($up_data);
        if($param['status']==1){
            $str = "批量解封";
        }else{
            $str = "批量禁用";
        }
        if($ret===false){
            return Respons::run(0,'',lang($str.'失败'));
        }
        return Respons::run(1,'',lang($str.'成功'));
    }

    public function edit_detail() {
        $param = Request::param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $UserModel =new UserModel();
        $res = $UserModel->where("id",$param['id'])->withoutField('rec_id')->find();
        if (empty($res)) {
            throw new \think\Exception(lang("用户信息不存在"), 0);
        }
        $res['mail'] = $res['mail'] == null || $res['mail'] == 'undefined' ? '' : $res['mail'];
        $res['phone'] = $res['phone'] == null || $res['phone'] == 'undefined' ? '' : $res['phone'];
        $res['rec_id'] = $UserModel->where('id',$res['rec_id'])->value('username');
        $res['add_time']=date("Y-m-d H:i:s",$res['add_time']);
        $res['login_time']=date("Y-m-d H:i:s",$res['login_time']);
        $res['new_password']="";//新密码
        $res['user_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $res['checkbox_tags'] = Utils::getOptions('tag');
        //会员标签
        $res['userTagIds'] = UserTagModel::where(['uid' => $param['id']])->column('tag_id');
        return Respons::run(1, Utils::hideUserInfo($res));
    }

    public function detail()
    {
        $param = Request::param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $UserModel           = new UserModel();
        $user                 = $UserModel->where("id", $param['id'])->find();
        $user['mail']         = $user['mail'] == null || $user['mail'] == 'undefined' ? '' : $user['mail'];
        $user['phone']        = $user['phone'] == null || $user['phone'] == 'undefined' ? '' : $user['phone'];
        $user['add_time']     = date("Y-m-d H:i:s", $user['add_time']);
        $user['login_time']   = date("Y-m-d H:i:s", $user['login_time']);
        $user['level_name'] = UserLevelModel::where('id', $user['level_id'])->value('level_name');
        $user['google_secret_sta'] = UserGoogleModel::where('uid', $user['id'])->value('google_secret') ? 1 : 2;
        $user['new_password'] = "";//新密码
//        $total_activityaward = 0;
        $total_activityaward = ActionApplyModel::where(["uid" => $param['id'], 'ps_status' => 1])->sum(Db::raw('tyj_amount+gd_amount+ck_hl_amount'));
//        foreach ($apply as $v) {
//            $base_type = ActionModel::where(['id' => $v['action_id']])->value('base_type');
//            if ($base_type == 1 || $base_type == 2) {
//                $total_activityaward += UserCkModel::where(["id" => $v['user_ck_id'], 'ck_status' => 1])->value('ck_hl_amount') ?? 0;
//            } elseif ($base_type == 3) {
//                $total_activityaward += $v['gd_amount'];
//            } elseif ($base_type == 4) {
//                $total_activityaward += $v['tyj_amount'];
//            }
//        }
        $tree_uid = explode('/', trim($user['tree'], '/'));
        array_reverse($tree_uid);
        $tree_users = [];
        $tree_username = UserModel::whereIn('id', $tree_uid)->field('id,username,mail,phone,rec_code')->select();
        foreach ($tree_username AS $item) {
            if ($item['id'] == $user['id']) {
                continue;
            }
            $tree_users[$item['id']] = $item->toArray();
        }
        $user['tree_users'] = array_values($tree_users);
        $game_bets = GameService::statGameBetByGlobal($param['id']);
        $user['data'] = [
            //充值总额
            'total_recharge'      => sprintf("%.2f", UserOfflineModel::where([['uid', '=', $param['id']], ['status', 'in', '3,4']])->sum('arrival_price')),
            //提现总额
            'total_withdraw'      => sprintf("%.2f", UserWithdrawalModel::where([['uid', '=', $param['id']], ['status', 'in', '3,4']])->sum('arrival_price')),
            //总输赢
            'total_profit'        => $game_bets['total_win_lost'],
            //总有效流水
            'total_betslip'        => $game_bets['bet_valid_amount'],
            //代理返佣总额
            'total_agentrebate'   => sprintf("%.2f", AgentRakeBackModel::where([['uid', '=', $param['id']], ['status', 'in', '3,4']])->sum('money')),
            //人工增加总额
            'total_increase'      => sprintf("%.2f", ArtificialBalanceModel::alias("a")->where([['a.uid', '=', $param['id']], ['a.a_type', '=', '1']])->sum('a.price')),
            //人工赠送彩金总额
            'total_jackpot'       => sprintf("%.2f", ArtificialBalanceModel::alias("a")->where([['a.uid', '=', $param['id']], ['a.a_type', '=', '3']])->sum('a.price')),
            //人工赠减少总额
            'total_reduce'        => sprintf("%.2f", ArtificialBalanceModel::alias("a")->where([['a.uid', '=', $param['id']], ['a.a_type', '=', '2']])->sum('a.price')),
            //活动奖励总额
            'total_activityaward' => sprintf("%.2f", $total_activityaward),
        ];
        //三方余额
        $user['third_balance'] = MemberService::getThirdAllBalance($user['id']);
        //会员标签
        $user['tags'] = MemberService::getTagByUid($user['id']);

        $res = \app\common\model\UserVerifyModel::where("uid", $param['id'])->find();
        if(isset($res['content']) && !empty($res['content'])){
            $user['user_verify'] = json_decode($res['content'],true);
        }else{
            $user['user_verify'] = [];
        }
        //提现
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $userWithdrawal = UserModel::uinfo($user['id'], ",a.savings,a.water_balance,a.withdrawal_psd,b.min_amount,b.max_amount,b.day_limit");//查看当前用户流水和最低提现金额
        $date = [];
        //获取提现规则和可提现余额（根据提现那边修改）
        $data['withdrawal_rules'] = $sys['withdrawal_rules'];
        // 提现是否必须要求有充值 0 为必须，1为不必须
        $UserOffline = env('WITHDRAW_NOT_REQ_RECHARGE', 0);
        if ($UserOffline == 0) {
            $UserOffline = UserOfflineModel::where([['uid', "=", $userWithdrawal['id']], ['status', "in", "3,4"]])->value("id") ? 1 : 0;
        }
        $userWithdrawalAmount = $UserOffline > 0 ? $userWithdrawal['balance'] : 0;
        if ($userWithdrawal['savings'] > 0 && $UserOffline > 0 && $data['withdrawal_rules'] == 1) {
            $userWithdrawalAmount = $userWithdrawal['balance'] - $userWithdrawal['savings'];
            //判断提现0流水限制
            if (isset($sys['saving_zero_withdrawal']) && $sys['saving_zero_withdrawal'] == 1) {
                $userWithdrawalAmount = 0;
            }
        }
        $user['user_withdrawal_amount'] = max([$userWithdrawalAmount, 0]); // 小于0时显示0
        return Respons::run(1, Utils::hideUserInfo($user));
    }

    //会员报表
    public function reports(){
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $where = [];

        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["uid|phone", "=", $param['username']];
            } else {
                $where[]=["username|mail|phone", "=", $param['username']];
            }
        }

        $fields = ['deposit_nums', 'deposit_total', 'withdrawal_nums', 'withdrawal_total'];

        foreach ($fields AS $field) {
            if (isset($param[$field]) && $param[$field] !== '') {
                if (strexists($param[$field], "-")) {
                    $arr = explode("-", $param[$field]);
                    if (isset($arr[0]) && $arr[0] > 0) {
                        $where[]=["{$field}", ">=", intval($arr[0])];
                    }
                    if (isset($arr[1]) && $arr[1] > 0) {
                        $where[]=["{$field}", "<=", intval($arr[1])];
                    }
                } else {
                    $where[]=["{$field}", "=", $param[$field]];
                }
            }
        }

        if (isset($param['reg_time']) && $param['reg_time']!='') {
            $date = explode(' - ',$param['reg_time']);
            $where[]=["reg_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["reg_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['first_deposit_time']) && $param['first_deposit_time']!='') {
            $date = explode(' - ',$param['first_deposit_time']);
            $where[]=["first_deposit_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["first_deposit_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['recent_withdraw_money_time']) && $param['recent_withdraw_money_time']!='') {
            $date = explode(' - ',$param['recent_withdraw_money_time']);
            $where[]=["recent_withdraw_money_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["recent_withdraw_money_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['recent_deposit_money_time']) && $param['recent_deposit_money_time']!='') {
            $date = explode(' - ',$param['recent_deposit_money_time']);
            $where[]=["recent_deposit_money_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["recent_deposit_money_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['login_time']) && $param['login_time']!='') {
            $date = explode(' - ',$param['login_time']);
            $where[]=["login_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["login_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = ''.$param['field'].' '. ($param['order'] == 'DESC' ? 'DESC' : 'ASC');
        } else {
            $order = 'uid desc';
        }
        if (isset($param['export']) && $param['export'] == 1) {
            $total = Db::name("user_reports")->where($where)->count();
            if (empty($where) || $total > 50000) {
                $result['code'] = 0;
                $result['msg'] = '导出数据太大，请增加更多筛选条件';
                return Respons::run($result);
            }
            $data = Db::name("user_reports")
                ->where($where)
                ->order($order)
                ->select();
            $list = [];
            foreach($data as $k => &$value) {
                $value['reg_time'] = empty($value['reg_time']) ? '' : date("Y-m-d H:i:s", $value['reg_time']);
                $value['first_deposit_time'] = empty($value['first_deposit_time']) ? '' : date("Y-m-d H:i:s", $value['first_deposit_time']);
                $value['recent_withdraw_money_time'] = empty($value['recent_withdraw_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_withdraw_money_time']);
                $value['recent_deposit_money_time'] = empty($value['recent_deposit_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_deposit_money_time']);
                $value['login_time'] = empty($value['login_time']) ? '' : date("Y-m-d H:i:s", $value['login_time']);
                $list[] = $value;
            }

        } else {
            $list = Db::name("user_reports")
                ->where($where)
                ->order($order)
                ->limit($offset, $limit)
                ->select();
            $rsps['total'] = Db::name("user_reports")->where($where)->count();
            $data = [];
            foreach($list as $k => $value) {
                $value['reg_time'] = empty($value['reg_time']) ? '' : date("Y-m-d H:i:s", $value['reg_time']);
                $value['first_deposit_time'] = empty($value['first_deposit_time']) ? '' : date("Y-m-d H:i:s", $value['first_deposit_time']);
                $value['recent_withdraw_money_time'] = empty($value['recent_withdraw_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_withdraw_money_time']);
                $value['recent_deposit_money_time'] = empty($value['recent_deposit_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_deposit_money_time']);
                $value['login_time'] = empty($value['login_time']) ? '' : date("Y-m-d H:i:s", $value['login_time']);
                $data[] = $value;
            }
            $rsps['data'] = $data;
        }


        if (isset($param['export']) && $param['export'] == 1) {
            $header = [
                'uid' => lang('UID'),
                'phone' => lang('手机号'),
                'mail' => lang('邮箱'),
//                'lebal' => lang('用户标签'),
                'reg_time' => lang('注册时间'),
                'first_deposit_time' => lang('首存时间'),
                'recent_withdraw_money_time' => lang('最近出金时间'),
                'recent_deposit_money_time' => lang('最近入金时间'),
                'login_time' => lang('最近登陆时间'),
                'deposit_nums' => lang('入金次数'),
                'deposit_total' => lang('入金总额'),
                'withdrawal_nums' => lang('出金次数'),
                'withdrawal_total' => lang('出金总额'),
                'total_gift' => lang('赠送金额'),
                'action_discount' => lang('活动优惠'),
                'lottery_total' => lang('抽奖'),
                'rebate_total' => lang('返佣'),
                'rebate1_total' => lang('返水'),
                'promotion_bonus' => lang('推广奖金'),
                'mission_rewards' => lang('任务奖励'),
                'vip_bonus' => lang('VIP周月礼金'),
            ];
            $file_name = genCsv($header, $list,lang('用户报表记录').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($where)) {
                Utils::addOptLogByHandle('用户报表导出');
            }
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function exportList($where=null, $where1 = null, $where2 = null){
        $params=Request::param();
        $params['where']=$where;
        $params['where1']=$where1;
        $params['where2']=$where2;
        $params['positionField']='a.id';
        $params['positionOrder']='asc';
        $params['muid']=$this->uid;

        //会员等级
        $UserLevelModel = new UserLevelModel();
        $user_level_data = $UserLevelModel->column('level_name', 'id');
        //号码状态
        $checkStatus = [0 => '未绑定', 1 => '已验证', 2 => '未验证'];
        //标签列表
        $lebalArr = [0 => '测试用户', 1 => '普通用户'];
        $statusArr = [0 => '禁用', 1 => '启用'];
        $rlt=ReportExport::export(new rptParams('会员列表',
            [
                'id:UID',
                'username:用户名',
                'phone:手机号',
                new rptCol('phone_bind_status','号码状态',rptCol::transType_kvtrans,$checkStatus),#911无
                'qh:区号',
                'mail:邮箱',
                new rptCol('mail_bind_status','邮箱状态',rptCol::transType_kvtrans,$checkStatus),#911无
                new rptCol('lebal','用户标签','app\common\service\ReportDownloadService::getUserLabelByUser'),
                'rec_code:推广码',
                new rptCol('tree','顶级代理UID','app\common\service\ReportDownloadService::getTopId'),
                'rec_id:上级代理UID',
                new rptCol('level_id','会员等级',rptCol::transType_kvtrans,$user_level_data),
                new rptCol('id','总充值','app\common\service\ReportDownloadService::getRecharge'),
                new rptCol('id','总提现','app\common\service\ReportDownloadService::getWithdrawal'),
                new rptCol('add_time','注册时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                'reg_ip:注册ip',
                new rptCol('login_time','登录时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                'end_login_ip:登录ip',
                'balance:余额',
                'cjbalance:彩金余额',
                new rptCol('status','状态',rptCol::transType_kvtrans,$statusArr),
            ],
            __CLASS__,'exportListMethod',
            $params,
            ['phone_bind_status','mail_bind_status','is_recharge','level', 'login_time'],
            'id','asc'
        ));
        if ($rlt){
            return Respons::run(1);
        }else{
            return Respons::run(0,[],'创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $q=(new UserModel())
            ->alias("a");
        if ($param['where']) $q=$q->where($param['where']);
        if ($param['where1']) $q=$q->where($param['where1']);
        if ($param['where2']) $q=$q->where($param['where2']);
        return $q->field("a.*");
    }
}
