<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\exception\MessageException;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\model\IpLimitModel;
use app\common\model\PddInviteRecordModel;
use app\common\model\ThirdSetModel;
use app\common\model\UserAgentModel;
use app\common\model\UserExtraModel;
use app\common\model\UserGoogleModel;
use app\common\model\UserLoginModel;
use app\common\model\UserModel;
use app\common\model\UserThirdModel;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\PixelService;
use app\common\service\WebSiteConfigService;
use app\common\thirdparty\Entry;
use Firebase\JWT\JWT;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

class LoginFactory
{
    private $param;//请求参数

    //验证
    public function __construct()
    {
        $this->param = Request::param();
    }

    //验证码
    public function captcha_run()
    {
        $phraseBuilder = new PhraseBuilder(5, '0123456789');
        $builder = new CaptchaBuilder(null, $phraseBuilder);
        $builder->build();
        $base64 = $builder->inline();
        $key = md5($base64);
        $ApiRedis = new ApiRedis();
        $ApiRedis->set($key, $builder->getPhrase(), 300);
        $rsps['data'] = ['base64' => $base64, "key" => $key];
        $rsps['code'] = 1;
        return $rsps;
    }

    //注册
    public function register_run()
    {
        $this->param = Util::common_validation_param($this->param);
        $rsps = [];
        $rsps['code'] = 0;
        $info = $this->param;
        //判断是否关闭注册
        if (env('IS_CLOSE_REGISTER', 0) == 1) {
            throw new MessageException('注册已关闭', 0);
        }
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();

        //是否验证码注册
        $codeRegister = false;
        if (!isset($info['password']) || !$info['password']) {
            //没有密码为验证码注册
            try {
                validate(\app\common\validate\RegisterValidate::class)->scene('create1')->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                throw new MessageException($e->getError(), 0);
            }
            //手机验证码和邮箱验证码取一个
            $code = isset($info['code']) ? $info['code'] ?: $info['mail_code'] : $info['mail_code'];
            $info['password'] = $code . 't0';
            if (empty($info['code']) && empty($info['mail_code'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang('请输入验证码'), 0);
            }
            $redisCode = $ApiRedis->get($info['username'] . "_code");
            if ($code != $redisCode) {
                $ApiRedis->delete($info['code_key']);
                $ApiRedis->delete($info['username'] . "_code");
                throw new MessageException(lang("验证码错误"), 0);
            }
            $codeRegister = true;
        } else {
            //密码注册
            try {
                validate(\app\common\validate\RegisterValidate::class)->scene('create')->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                throw new MessageException($e->getError(), 0);
            }
        }
        $usernameCheckIsPhone = checkPhone($info['username']);
        $usernameCheckIsEmail = strexists($info['username'], '@');

        //是否去除号码前的0
        if (isset($info['phone']) && strpos($info['phone'], '0') === 0 && strpos($info['phone'], '@') === false && env('register_is_del_zero', 0) == 1) {
            $info['phone'] = substr($info['phone'], 1);
        }
        if (isset($info['username']) && $usernameCheckIsPhone && strpos($info['username'], '0') === 0 && strpos($info['username'], '@') === false && env('register_is_del_zero', 0) == 1) {
            $info['username'] = substr($info['username'], 1);
        }

        $lebal = 1;
        $rec = $add = [];
        //根据域名获取上级 TODO 该方法需放到邀请码执行上面，邀请码的会覆盖这个位置
        $agentId = AgentService::getAgentIdByWebSite();
        $agent = UserModel::get_agent_id($agentId);
        $rec_id = $agent[0];
        $rec_id2 = $agent[1];
        $rec_id3 = $agent[2];
        if (!($info['rec_code'] ?? '') && isset($info['share_code_global']) && $info['share_code_global']) {
            //没有邀请码代理邀请码
            $info['rec_code'] = $info['share_code_global'];
        }
        if (!empty($info['rec_code'])) {
            $rec = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where("a.rec_code", $info['rec_code'])
                ->where("b.status", 3)
                ->field("a.id,a.rec_person,a.tree,a.lebal")
                ->find();//要是代理且是通过
            if (!$rec) {
                $ApiRedis->delete($info['code_key']);
                $rec = null;
//                throw new MessageException(lang("邀请码不存在"), 0);
            } else {
                if ($sys['is_lowercs'] == 2 && $rec['lebal'] == 2) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("邀请码不存在"), 0);
                }
            }
            //判断该邀请码是否是该代理下面的
            if ($agentId) {
                $tree = $rec->tree;
                $tree = array_filter(explode('/', $tree));
                if (!in_array($agentId, $tree)) {
                    $rec = null;
                }
            }
        }
        // 默认代理线
        $default_rec_code = env("DEFAULT_REC_CODE");
        if (empty($rec) && !empty($default_rec_code)) {
            $rec = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where("a.rec_code", $default_rec_code)
                ->where("b.status", 3)
                ->field("a.id,a.rec_person,a.tree,a.lebal")
                ->find();
        }
        if (isset($rec['id'])) {
            if ($sys['is_lowercs'] == 1 && $rec['lebal'] == 2) {
                $lebal = 2;
            }
            $agent = UserModel::get_agent_id($rec['id']);
            $rec_id = $agent[0];
            $rec_id2 = $agent[1];
            $rec_id3 = $agent[2];
        }

        //相同号码或邮箱条件
        $samePhoneWhere = [];
        if ($usernameCheckIsPhone || !empty($info['phone'])) {
            $samePhoneWhere[] = ['phone', '=', $usernameCheckIsPhone ? $info['username'] : $info['phone']];
        }
        $_samePhoneWhere = [];
        if ($usernameCheckIsPhone || !empty($info['phone'])) {
            $_phone = rtrim($usernameCheckIsPhone ? $info['username'] : $info['phone'], '0');
            $_samePhoneWhere[] = ['phone', '=', $_phone];
        }
        $sameEmailWhere = [];
        if ($usernameCheckIsEmail || !empty($info['mail'])) {
            $sameEmailWhere[] = ['mail', '=', $usernameCheckIsEmail ? $info['username'] : $info['mail']];
        }
        $sameUsernameWhere = [];
        if ($info['account_type'] == 3 || !empty($info['username'])) {
            $sameUsernameWhere[] = ['username', '=', $info['username']];
        }
        $agentDomain = AgentService::judgeDomainIsBindAgent(getOriginalDomain());
        if (isset($sys['same_phone_register']) && $sys['same_phone_register'] == 1) {
            if ($agentDomain) {
                //有值则代表分站的数据，查询分站账号
                $_samePhoneWhere[] = ['reg_url', 'in', $agentDomain];
                $samePhoneWhere[] = ['reg_url', 'in', $agentDomain];
                $sameEmailWhere[] = ['reg_url', 'in', $agentDomain];
                $sameUsernameWhere[] = ['reg_url', 'in', $agentDomain];
            } else {
                //没有值则为主站，查询没在分站的账号
                $otherUrl = WebSiteConfigService::getAllWebsiteUrlArr();
                $_samePhoneWhere[] = ['reg_url', 'not in', $otherUrl];
                $samePhoneWhere[] = ['reg_url', 'not in', $otherUrl];
                $sameEmailWhere[] = ['reg_url', 'not in', $otherUrl];
                $sameUsernameWhere[] = ['reg_url', 'not in', $otherUrl];
            }
        }

        // 图形验证吗
        if (isset($sys['code_check']) && in_array($sys['code_check'], [1, 3])) {
            $ApiRedis = new ApiRedis();
            $code = $ApiRedis->get($info['code_key']);
            if ($code != $info['code_value']) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang('图形验证码错误'), 0);
            }
        }

        if ($info['account_type'] != 3) {
            //不等于3才验证手机号或邮箱
            if ($sys['phone_bind'] == 2 && $sys['is_reg_type'] == 1) {
                //删除后面的0再判断一次
                if (($usernameCheckIsPhone || $info['phone']) && UserModel::where($_samePhoneWhere)->value('id') > 0) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("手机号已存在"), 0);
                }
                if (isset($info['phone']) && !empty($info['phone']) && UserModel::where($samePhoneWhere)->value('id') > 0) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("手机号已存在"), 0);
                } else {
                    if (empty($info['phone'])) {
                        $ApiRedis->delete($info['code_key']);
                        throw new MessageException(lang("手机号码必传"), 0);
                    }
                    if (!preg_match("/^[0-9]+$/", $info['phone'])) { // 限制手机号格式
                        $ApiRedis->delete($info['code_key']);
                        throw new MessageException(lang("手机号码格式错误"), 0);
                    }
                    if (!checkPhone($info['phone'])) {
                        $ApiRedis->delete($info['code_key']);
                        throw new MessageException(lang("手机号码格式错误"), 0);
                    }
                }
            } else if ($sys['phone_bind'] == 1 && $sys['is_reg_type'] == 2) {
                if (isset($info['mail']) && !empty($info['mail']) && UserModel::where($sameEmailWhere)->value('id') > 0) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("邮箱已存在"), 0);
                } else {
                    if (empty($info['mail'])) { //设置了需要绑定邮箱  邮箱字段必传
                        $ApiRedis->delete($info['code_key']);
                        throw new MessageException(lang("邮箱号码必传"), 0);
                    }
                    if (!preg_match("/^([a-zA-Z0-9_.-])+@([a-zA-Z0-9_-])+(.[a-zA-Z0-9_-])+/i", $info['mail'])) {
                        $ApiRedis->delete($info['code_key']);
                        throw new MessageException(lang("邮箱格式错误"), 0);
                    }
                }
            }
        }
        $ip = Util::get_ip();
        $xzip = IpLimitModel::where(["ip" => $ip, 'api_status' => 1])->count();
        if ($xzip > 0) {
            $ApiRedis->delete($info['code_key']);
            throw new MessageException("forbidden ip：" . $ip, 101);
        }
        $ip_num_limit = isset($sys['ip_num_limit']) ? $sys['ip_num_limit'] : 2;
        if ($ip_num_limit > 0) {
            $num_reg_ip = \app\common\service\MemberService::isRepeatByIP($ip);
            if ($num_reg_ip >= $ip_num_limit) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException("IP注册已达上限", 0);
            }
        }

        // 1 手机 2 邮箱 3 用户名
        if (!isset($info['account_type']) || !in_array($info['account_type'], [1, 2, 3])) {
            throw new MessageException(lang('参数有误'), 0);
        }

        // phone_bind = 3 同时支持邮箱/手机注册 TODO account_type为3时，为后面加的用户名在注册，不验证手机号/邮箱
        if ($info['account_type'] != $sys['phone_bind'] && $sys['phone_bind'] != 3 && $info['account_type'] != 3) {
            throw new MessageException(lang('账号类型不支持'), 0);
        }
        if ($info['account_type'] == 1 && (!isset($info['phone']) || !$info['phone'])) {
            $info['phone'] = $info['username'];
        } else if ($info['account_type'] == 2 && (!isset($info['mail']) || !$info['mail'])) {
            $info['mail'] = $info['username'];
        }
        // 1 手机 2 邮箱
        if ($info['account_type'] == 1) { //设置了需要绑定手机  手机号码字段必传
            if (empty($info['phone'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("手机号码必传"), 0);
            }
            $redis_code_key = $info['phone'] . "_code";
        } else if ($info['account_type'] == 2) {
            if (empty($info['mail'])) { //设置了需要绑定邮箱  邮箱字段必传
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("邮箱号码必传"), 0);
            }
            $redis_code_key = $info['mail'] . "_code";
        } else if ($info['account_type'] == 3) {
            if (empty($info['username'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("用户名不能为空"), 0);
            }
            // 检查字符串是否只包含数字或字符
            if (!preg_match('/^[a-zA-Z0-9]+$/', $info['username'])) {
                throw new MessageException(lang("用户名为字母或数字组合"), 0);
            }
        }

        //手机号和邮箱提出来判断，可能会出现同时有填写另外一个有重复情况
        if (!empty($info['phone'])) {
            if (!preg_match("/^[0-9]+$/", $info['phone'])) { // 限制手机号格式
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("手机号码格式错误"), 0);
            }
            if (!checkPhone($info['phone'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("手机号码格式错误"), 0);
            }
            if (isset($info['phone'])) {
                if (isset($info['phone']) && !empty($info['phone']) && UserModel::where($samePhoneWhere)->value('id') > 0) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("手机号已存在"), 0);
                }
            }
        }
        //判断邮箱
        if (!empty($info['mail'])) {
            if (UserModel::where($sameEmailWhere)->value('id') > 0) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("邮箱已存在"), 0);
            }
            if (!preg_match("/^([a-zA-Z0-9_.-])+@([a-zA-Z0-9_-])+(.[a-zA-Z0-9_-])+/i", $info['mail'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("邮箱格式错误"), 0);
            }
            if (empty($info['mail'])) { //设置了需要绑定邮箱  邮箱字段必传
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("邮箱号码必传"), 0);
            }
        }


        if (isset($info['username']) && !empty($info['username']) && UserModel::where($sameUsernameWhere)->value('id') > 0) {
            $ApiRedis->delete($info['code_key']);
            throw new MessageException(lang("用户名已存在"), 0);
        }


        // 校验验证码
        if ($sys['phone_check'] == 1 && $info['account_type'] != 3) {
            if (empty($info['code'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang('请输入验证码'), 0);
            }
            $redisCode = $ApiRedis->get($redis_code_key);
            if ($info['code'] != $redisCode) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang("验证码错误"), 0);
            }
        }

        if (!checkString($info['password'])) {
            $ApiRedis->delete($info['code_key']);
            throw new MessageException(lang("密码只能数字和字母组合"), 0);
        }

        if (isset($sys['promotion_code']) && $sys['promotion_code'] == 1) {
            if (!isset($info['rec_code']) || empty($info['rec_code'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang('请输入推广码'), 0);
            }
        }
        if (!$ApiRedis->lock("reg_" . $info['username'])) {
            $ApiRedis->delete($info['code_key']);
            ajax_return(lang("请勿频繁访问"), 0);
        }
        if (isset($rec['id'])) {
            if ($sys['is_lowercs'] == 1 && $rec['lebal'] == 2) {
                $lebal = 2;
            }
            $agent = UserModel::get_agent_id($rec['id']);
            $rec_id = $agent[0];
            $rec_id2 = $agent[1];
            $rec_id3 = $agent[2];
        }
        $qh = null;
        if (isset($this->param['areacode'])) {
            $qh = $this->param['areacode'];
        }

        if (isset($this->param['qh'])) {
            $qh = $this->param['qh'];
        }
        //获取站点配置
        $agentSiteConfig = WebSiteConfigService::getWebsiteConfigSetLang($rec_id);
        if ($rec_id && isset($agentSiteConfig['parent_allot']) && $agentSiteConfig['parent_allot'] == 1) {
            //开启上级分配
            #1、二级为当前代理
            $rec_id2 = $rec_id;
            #2、获取该代理的下级id
            $rec_id = AgentService::getChildAgentUserId($rec_id);
            if (!$rec_id) {
                //如果没有下级上级则为自己
                $rec_id = $rec_id2;
                //重新获取上级
                $rec_id2 = UserModel::where(['id' => $rec_id])->value('rec_id') ?: 0;
            }
            #3、获取该代理的上级
            $rec_id3 = UserModel::where(['id' => $rec_id2])->value('rec_id') ?: 0;
        }

        $rec_code = Util::rec_code();
        $checkType = $info['check_type'] ?? 0;
        $tree = '';
        if ($rec_id) {
            $tree = UserModel::where('id', '=', $rec_id)->value('tree');
        }
        Db::startTrans();
        try {
            if ($checkType == 1) {
                //号码验证
                $add['mail_bind_status'] = 0;
                $add['phone_bind_status'] = 1;
            } elseif ($checkType == 2) {
                //邮箱验证
                $add['mail_bind_status'] = 1;
                $add['phone_bind_status'] = 0;
            }
            $add['reg_ip'] = Util::get_ip();
            $add['add_time'] = time();
            $add['lebal'] = $lebal;
            $add['rec_id'] = $rec_id;//推广人id
            $add['rec_id2'] = $rec_id2;
            $add['rec_id3'] = $rec_id3;
            $add['qh'] = $qh;
            if (isset($info['phone']) && !empty($info['phone'])) {
                $add['phone'] = $info['phone'];
                $add['phone_bind_status'] = 2;
            }
            if (isset($info['mail']) && !empty($info['mail'])) {
                $add['mail'] = $info['mail'];
                $add['mail_bind_status'] = 2;
            }
            $add['is_check_phone'] = 1;
            $add['password'] = Util::userMd5Password($info['password']);
            if (!empty($info['username'])) {
                if (!$usernameCheckIsPhone && !$usernameCheckIsEmail) {
                    //既不是手机号也不是邮箱
                    $add['username'] = $info['username'];
                } else {
                    $add['username'] = Util::random(30);
                }
            } else {
                $add['username'] = Util::random(30);
            }
//            $add['head_img'] = env('DEFAULT_AVATAR_URL', $sys['domain']."/api/img/default_head.png");
            $add['head_img'] = DefaultHeadModel::getHeadOne();//默认头像
            $add['ac_id'] = isset($info['acid']) ? intval($info['acid']) : 0;
            $add['reg_url'] = getAppUrl();
            $add['rec_code'] = $rec_code;
            $add['line_id'] = $info['lineId'] ?? '';
            $add['real_name'] = $info['actual_name'] ?? '';
            $user = UserModel::create($add);
            $id = $user->id;
            if (empty($tree)) {
                $tree = "/" . $id . "/";
            } else {
                $tree = $tree . $id . "/";
            }

            // Scaleo ClickId
            $cid = $info['cid'] ?? '';
            //添加额外信息表
            UserExtraModel::create(['uid' => $id, 'click_id' => $cid, 'ac_id' => $add['ac_id']]);

            $tree1 = explode("/", $tree);
            $new_tree = array_filter($tree1);

            $user->tree = $tree;
            $user->rec_level = count($new_tree);
            $res = $user->save();
            if ($res) {
                if (!empty($rec)) {
                    UserModel::rec_person_add($rec_id, $sys['tg_condition']);
                    PddInviteRecordModel::createData($rec_id, $user);
                }
                $agentType = $info['agent_type'] ?? 0;#代理类型
                if ($sys['agent_apply_verify'] == 2 || $agentType == 1) {
                    $add = [];
                    $add['uid'] = $id;
                    $add['apply_ip'] = Util::get_ip();
                    $add['apply_time'] = time();
                    $add['status'] = $agentType == 1 ? 1 : 3;#内部代理需要审核，外部代理不需要
                    $add['tree'] = $tree;
                    $add['rec_id'] = $rec_id;
                    $add['type'] = $agentType;
                    UserAgentModel::insert($add);
                }
            }

            // 发布用户注册事件
            event(new \app\event\UserRegisterEvent($user));
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $ApiRedis->delete($info['code_key']);
            throw new MessageException($e->getMessage(), 0);
        }
        $ApiRedis->delete($info['code_key']);
        $ApiRedis->unlock("reg_" . $info['username']);//释放锁
        if ($rsps['code'] == 1) {
            $result = $this->dologin($info['username'], $codeRegister ? false : $info['password'], $info['code_key'], '', $checkType, $info['device_type'] ?? '无', $qh);

            if (isset($info['register_type']) && $info['register_type'] == 0) {
                //注册成功领取五福卡片,TODO 该次赠送一定要在登录后执行，登录里面有赠送卡片次数，注册赠送卡片里面有判断只赠送一次
                (new WuFuFactory())->giveCardRegister($id);
            }
            return $result;
        } else {
            return $rsps;
        }
    }

    /**
     * 添加成为代理
     * @param $id
     * @param $rec_id
     */
    public function _addAgent($id, $rec_id)
    {
        $UserAgentModel = new UserAgentModel();
        $temp = $UserAgentModel->where('uid', $id)->find();
        if ($temp) {
            return;
        }
        $tree = "/$id/";
        $proxy_tree = '';
        $proxy_topuid = $proxy_upuid = 0;
        $new_proxy_tree = [];
        //开通微商
        $add_agent['uid'] = $id;
        $add_agent['apply_ip'] = Util::get_ip();
        $add_agent['apply_time'] = time();
        $add_agent['status'] = 3;
        $add_agent['tree'] = $tree;
        $add_agent['rec_id'] = $rec_id;
        $add_agent['proxy_upuid'] = $proxy_upuid;
        $add_agent['proxy_topuid'] = $proxy_topuid;
        $add_agent['proxy_level'] = count($new_proxy_tree);
        $add_agent['proxy_tree'] = $proxy_tree;
        $add_agent['type'] = 0;
        $add_agent['rate'] = 0;
        $add_agent['admin_name'] = "系统默认";
        $add_agent['website_auth'] = 0;
        $UserAgentModel->insertGetId($add_agent);
    }

    //登录
    public function login_run()
    {
        $info = $this->param;
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        if (in_array($sys['code_check'], [1, 2])) {
            $ApiRedis = new ApiRedis();
            $code = $ApiRedis->get($info['code_key']);
            if ($code != $info['code_value']) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang('验证码错误'), 0);
            }
        }
        //是否验证码注册
        $codeRegister = false;
        if (!isset($info['password']) || !$info['password']) {
            try {
                validate(\app\common\validate\RegisterValidate::class)->scene('login1')->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                throw new MessageException($e->getError(), 0);
            }
            //没有密码为验证码注册
            if (empty($info['code'])) {
                $ApiRedis->delete($info['code_key']);
                throw new MessageException(lang('请输入验证码'), 0);
            }
            $redisCode = $ApiRedis->get($info['username'] . "_code");
            if ($info['code'] != $redisCode) {
                $ApiRedis->delete($info['code_key']);
                $ApiRedis->delete($info['username'] . "_code");
                throw new MessageException(lang("验证码错误"), 0);
            }
            $codeRegister = true;
        } else {
            try {
                validate(\app\common\validate\RegisterValidate::class)->scene('login')->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                throw new MessageException($e->getError(), 0);
            }
        }
        $info['google_code'] = !isset($info['google_code']) ? '' : $info['google_code'];
        return $this->dologin($info['username'], $codeRegister ? false : $info['password'], $info['code_key'], $info['google_code'], $info['check_type'] ?? 0, $info['device_type'] ?? '无',$info['qh']);
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $code_key
     * @param string $google_code
     * @param int $checkType 是否验证（1号码验证，2邮箱验证）
     * @param string $deviceType 登录类型（APP，IPHONE，WINDOWS，MAC）
     * @param string $qh 手机号区号
     * @return array
     * @throws MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function dologin($username, $password, $code_key, $google_code = "", $checkType = 0, $deviceType = '', $qh = '')
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
//        switch ($sys['phone_bind']) {
//            case 1:
//                $login_field = "username|phone";
//                break;
//            case 2:
//                $login_field = "username|mail";
//                break;
//            default:
        $login_field = "username|phone|mail";
//                break;
//        }
        if ($password === false) {
            //验证码登录则不能username登录
            $login_field = "phone|mail";
        }
//        //注册账号类型为手机/邮箱，额外信息是否时查询所有账号类型
//        if( in_array($sys['phone_bind'], [1, 2]) && isset($sys['is_reg_type']) && $sys['is_reg_type'] == 0 ){
//            $login_field = "username|phone|mail";
//        }
        $condition = [];
        $condition[] = [$login_field, '=', $username];
        $agentDomain = AgentService::judgeDomainIsBindAgent(getOriginalDomain());
        if (isset($sys['same_phone_register']) && $sys['same_phone_register'] == 1) {
            if ($agentDomain) {
                //有值则代表分站的数据，查询分站账号
                $condition[] = ['reg_url', 'in', $agentDomain];
            } else {
                //没有值则为主站，查询没在分站的账号
                $otherUrl = WebSiteConfigService::getAllWebsiteUrlArr();
                $condition[] = ['reg_url', 'not in', $otherUrl];
            }
        }
        if (!empty($qh)) {
            $condition[] = ['qh', '=', $qh];
        }

        $user = UserModel::where($condition)->order('id asc')->find();
        if (empty($user) || !$user['id']) {
            $ApiRedis->delete($code_key);
            throw new MessageException(lang("账号或密码错误"), 0);
        }

        if (empty($user['password'])) {
            $ApiRedis->delete($code_key);
            throw new MessageException(lang("请先重置密码"), 619);
        }
        if (
            $password &&
            $user['password'] != Util::userMd5Password($password) &&
            $user['password'] != Util::userMd5Password1($password, $user['salt'])
        ) {
            $ApiRedis->delete($code_key);
            throw new MessageException(lang("账号或密码错误"), 0);
        }

        if (isset($sys['user_google_status']) && $sys['user_google_status'] == 1) {
            $google = UserGoogleModel::verification($user['id'], 'login_check', $google_code);
            if ($google) {
                throw new MessageException($google, 110);
            }
        }

        $ip = Util::get_ip();
        $xzip = IpLimitModel::where(["ip" => $ip, 'api_status' => 1])->count();
        if ($xzip > 0) {
            $ApiRedis->delete($code_key);
            throw new MessageException(lang('账号已封停'), 101);
        }

        if ($user['status'] != 1) {
            $ApiRedis->delete($code_key);
            throw new MessageException(lang('账号已封停'), 101);
        }

        //验证码验证类型
        $user['mail_bind_status'] = $user['mail_bind_status'] ?? 0;
        $user['phone_bind_status'] = $user['phone_bind_status'] ?? 0;
        if ($checkType == 1) {
            //号码验证
            $user['phone_bind_status'] = 1;
        } elseif ($checkType == 2) {
            //邮箱验证
            $user['mail_bind_status'] = 1;
        }
        //设备类型
        $deviceType = strtoupper($deviceType);
        $user['device_type'] = in_array($deviceType, UserLoginModel::getDeviceTypeArr()) ? $deviceType : '无';

        //APP，IPHONE登录赠送五福次数
        if (in_array($user['device_type'], ['ANDROID', 'IPHONE', 'IPHONE'])) {
//            (new WuFuFactory())->giveCardAppLogin($user['id'], $user['rec_code']);#APP下载
            (new TaskFactory($user['id']))->pushMessageToFinishTaskUser($user['id'], true);#推送用户完成任务
        }

        $key = Config::get("custom.jwt.jwt_key");//加密key
        $token = [
            "iss" => "",  //签发者 可以为空
            "aud" => "", //面象的用户，可以为空
            "iat" => Config::get("custom.jwt.iat"), //签发时间
            "nbf" => Config::get("custom.jwt.nbf"), //在什么时候jwt开始生效
            "exp" => Config::get("custom.jwt.exp"), //token 过期时间
            "uid" => $user['id'], //记录的user的信息
            "sign" => md5($user['id'] . $user['password']),
        ];
        if (isset($user['usertokenkey']) && $user['usertokenkey']) {
            $ApiRedis->delete($user['usertokenkey']);//删除key
        }

        $jwt = JWT::encode($token, $key, "HS256"); //根据参数生成了 token
        $user['usertokenkey'] = md5("tokenkey" . $jwt);
        $user['usertoken'] = $jwt;
        event(new \app\event\UserLogin($user));
        // 自动参与累计充值活动
        MemberService::activityDiscountTrigger($user['id']);

        //检测用户VIP升级
//        Queue::push("User@upgrade", ['id' => $user['id'], 'money' => 0], 'upgrade');

        //老用户登录成功领取注册的五福卡片
        (new WuFuFactory())->giveCardFirstRegister($user['id'], $user['rec_code']);
        $ApiRedis->set($user['usertokenkey'], $user['usertoken'], env('login_expire_time', 3600));
        $rsps['data']['token'] = $user['usertoken'];
        $rsps['data']['token_key'] = $user['usertokenkey'];
        $rsps['code'] = 1;
        return $rsps;
    }

    //计算相同ip登录标记红色
    public function handelSameIpCount($user)
    {
        $ip = $user['end_login_ip'] ?: $user['reg_ip'];#刚注册的取注册的
        if ($ip) {
            $sameIpIdArr = UserModel::where('end_login_ip', '=', $ip)->column('id');
            $userIpCount = count($sameIpIdArr);
            if ($userIpCount > 1) {
                //修改用户登录的ip数量，有新的才更改
                UserModel::where('id', 'in', $sameIpIdArr)
                    ->where('login_ip_count', '<', $userIpCount)
                    ->update(['login_ip_count' => $userIpCount]);
            }
        }
    }


    public function get_third_login_run()
    {
        $ApiRedis = new ApiRedis();
        $redis_key = "third_login_run_list_redis";
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $ThirdSetModel = new ThirdSetModel();
            $data = $ThirdSetModel->field("id, name, icon, third_sys_sign")->where("status", 1)->order("sort desc")->select();
            $ApiRedis->set($redis_key, $data, 1800);
        }


        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function get_third_login_url_run()
    {
        $info = $this->param;
        $id = $info['id'] ?? 0;
        $href = $info['href'] ?? 0;
        $rec_code = $info['rec_code'] ?? '';
        $uid = $info['uid'] ?? '';
        $ThirdSetModel = new ThirdSetModel();
        $ThirdSet = $ThirdSetModel->where("id", $id)->where("status", 1)->find();
        if (!isset($ThirdSet['id'])) {
            throw new MessageException("平台不存在", 0);
        }
        $ThirdSet = $ThirdSet->toArray();
        $Entry = new Entry($ThirdSet);
        $par['href'] = $href;
        $rsps = $Entry->action($ThirdSet['third_sys_sign'], $par, "get_url");
        return $rsps;
    }

    public function third_login_run()
    {
        $info = $this->param;
        $id = $info['id'] ?? 0;
        $rec_code = $info['rec_code'] ?? 0;
        $code = urldecode($info['code'] ?? '');
        if (empty($code)) {
            ajax_return("参数错误", 0);
        }
        $ip = Util::get_ip();
        $xzip = IpLimitModel::where(["ip" => $ip, 'api_status' => 1])->count();
        if ($xzip > 0) {
            ajax_return('账号已封停', 101);
        }
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $ThirdSetModel = new ThirdSetModel();
        $ThirdSet = $ThirdSetModel->where("id", $id)->where("status", 1)->find();
        if (!isset($ThirdSet['id'])) {
            ajax_return("平台不存在", 0);
        }
        $ThirdSet = $ThirdSet->toArray();
        $Entry = new Entry($ThirdSet);
        $res = $Entry->action($ThirdSet['third_sys_sign'], ['code' => $code], "get_userInfo");
        //锁
        if (!$ApiRedis->lock("third_reg_" . $id . '_' . $res['username'])) {
            ajax_return("请勿频繁访问", 0);
        }
        $third_uid = $res['third_uid'];
        $head_img = $res['head_img'];
        $UserThirdModel = new UserThirdModel();
        $wh = [];
        $wh[] = ['third_uid', '=', $third_uid];
        $wh[] = ['third_sys_sign', '=', $ThirdSet['third_sys_sign']];
        $UserThird = $UserThirdModel->where($wh)->find();
        if (!isset($UserThird['id'])) {
            //三方用户 - 用户 关联表没有当前数据的时候  user中存在邮箱 判断为邮箱号码重复
            if (isset($res['email']) && !empty($res['email']) && UserModel::where("mail", $res['email'])->value('id') > 0) {
                ajax_return("邮箱已存在", 0);
            }
//            if(isset($sys['promotion_code']) && $sys['promotion_code'] == 1){
//                if (isset($rec_code) && $rec_code == 0) {
//                    ajax_return('请输入推广码', 0);
//                }
//            }
//            if (isset($res['phone']) && !empty($res['phone']) && UserModel::where("phone",$res['phone'])->value('id') > 0) {
//                throw new MessageException("手机号码已存在", 0);
//            }
        }

        $lebal = 1;
        $UserModel = new UserModel();


        if (!isset($UserThird['id'])) {
            //不存在数据的时候新增 存在的时候 登录
            $add['reg_ip'] = Util::get_ip();
            $add['add_time'] = time();
            $add['qh'] = "";
            $add['phone'] = "";
            $add['mail'] = $res['email'] ?? "";
            $add['username'] = $res['username'] ?? "";
            $add['is_check_phone'] = 1;
            $add['head_img'] = empty($head_img) ? $sys['domain'] . "/api/img/default_head.png" : $head_img;
//                $add['subid'] = isset($info['subid'])?$info['subid']:'';

            if (isset($sys['off_num_freeze']) && $sys['off_num_freeze'] > 0) {
                //未充值x天冻结用户余额的时候 注册状态设为2
                $add['freeze_sta'] = 2;
                $add['freeze_time'] = time() + (86400 * $sys['off_num_freeze']);
            }


            $add['rec_id'] = 0;
            $add['rec_id2'] = 0;
            $add['rec_id3'] = 0;

            //根据域名获取上级 TODO 该方法需放到邀请码执行上面，邀请码的会覆盖这个位置
            $agentId = AgentService::getAgentIdByWebSite();
            $agent = UserModel::get_agent_id($agentId);
            $rec_id = $agent[0];
            $rec_id2 = $agent[1];
            $rec_id3 = $agent[2];
            if (!empty($info['rec_code'])) {
                $rec = UserModel::alias("a")
                    ->join("dq_user_agent b", "a.id=b.uid")
                    ->where("a.rec_code", $info['rec_code'])
                    ->where("b.status", 3)
                    ->field("a.id,a.rec_person,a.tree,a.lebal")
                    ->find();//要是代理且是通过
                if (!$rec) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("邀请码不存在"), 0);
                }
                if ($sys['is_lowercs'] == 2 && $rec['lebal'] == 2) {
                    $ApiRedis->delete($info['code_key']);
                    throw new MessageException(lang("邀请码不存在"), 0);
                }
                //判断该邀请码是否是该代理下面的
                if ($agentId) {
                    //获取该代理的数据
                    $agentUserDetail = UserModel::where("id", $agentId)->find();
                    if ($agentUserDetail) {
                        $tree = $agentUserDetail->tree;
                        $tree = array_filter(explode('/', $tree));
                        if (!in_array($rec['id'], $tree)) {
                            $rec = null;
                        }
                    }

                }
            }
            // 默认代理线
            $default_rec_code = env("DEFAULT_REC_CODE");
            if (empty($rec) && !empty($default_rec_code)) {
                $rec = UserModel::alias("a")
                    ->join("dq_user_agent b", "a.id=b.uid")
                    ->where("a.rec_code", $default_rec_code)
                    ->where("b.status", 3)
                    ->field("a.id,a.rec_person,a.tree,a.lebal")
                    ->find();
            }
            if (isset($rec['id'])) {
                if ($sys['is_lowercs'] == 1 && $rec['lebal'] == 2) {
                    $lebal = 2;
                }
                $agent = UserModel::get_agent_id($rec['id']);
                $rec_id = $agent[0];
                $rec_id2 = $agent[1];
                $rec_id3 = $agent[2];
            }

            //获取站点配置
            $agentSiteConfig = WebSiteConfigService::getWebsiteConfigSetLang($rec_id);
            if ($rec_id && isset($agentSiteConfig['parent_allot']) && $agentSiteConfig['parent_allot'] == 1) {
                //开启上级分配
                #1、二级为当前代理
                $rec_id2 = $rec_id;
                #2、获取该代理的下级id
                $rec_id = AgentService::getChildAgentUserId($rec_id);
                if (!$rec_id) {
                    //如果没有下级上级则为自己
                    $rec_id = $rec_id2;
                    //重新获取上级
                    $rec_id2 = UserModel::where(['id' => $rec_id])->value('rec_id') ?: 0;
                }
                #3、获取该代理的上级
                $rec_id3 = UserModel::where(['id' => $rec_id2])->value('rec_id') ?: 0;
            }

            $tree = '';
            if ($rec_id) {
                $tree = UserModel::where('id', '=', $rec_id)->value('tree');
            }

            if (isset($rec_id) && $rec_id > 0) {
                $add['rec_id'] = $rec_id;
                $add['rec_id2'] = $rec_id2;
                $add['rec_id3'] = $rec_id3;
            }
        }

        Db::startTrans();
        try {
            if (!isset($UserThird['id'])) {
                $add['lebal'] = $lebal;
//                $uid=UserModel::insertGetId($add);
                $user = UserModel::create($add);
                $uid = $user->id;
                if (empty($tree)) {
                    $tree = "/" . $uid . "/";
                } else {
                    $tree = $tree . $uid . "/";
                }
                if (!empty($rec)) {
                    UserModel::rec_person_add($rec['id'], $sys['tg_condition']);
                }

                //添加额外信息表
                UserExtraModel::create(['uid' => $uid]);

                $tree1 = explode("/", $tree);
                $new_tree = array_filter($tree1);

                $upd['id'] = $uid;
                if (empty($add['username'])) {
                    $upd['username'] = ENV('WEB_NAME') . str_pad($uid, 8, '0', STR_PAD_LEFT);
                }

                $upd['tree'] = $tree;
                $upd['rec_level'] = count($new_tree);
                $upd['rec_code'] = Util::rec_code();
                $res = UserModel::update($upd);
                if ($res) {
                    if (!empty($rec)) {
                        PddInviteRecordModel::createData($rec_id, $user);
                    }
                    if ($sys['agent_apply_verify'] == 2) {
                        $add = [];
                        $add['uid'] = $uid;
                        $add['apply_ip'] = Util::get_ip();
                        $add['apply_time'] = time();
                        $add['status'] = 3;
                        $ret = UserAgentModel::insert($add);
                    }
                }
                event(new \app\event\UserRegisterEvent($user));
                //注册赠金
                if (isset($sys['register_handout']) && $sys['register_handout'] > 0) {
                    register_rebate($uid, $sys['register_handout']);
                }

                $add_third_user = [];
                $add_third_user['uid'] = $uid;
                $add_third_user['third_uid'] = $third_uid;
                $add_third_user['third_sys_sign'] = $ThirdSet['third_sys_sign'];
                UserThirdModel::insert($add_third_user);
            } else {
                $uid = $UserThird['uid'];
            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            throw new MessageException($e->getMessage(), 0);
        }
        $ApiRedis->unlock("reg_" . $res['username']);//释放锁
        return $this->third_dologin($uid);
    }


    public function thrid_bind_user()
    {
        //基于用户已经登录 该操作用于绑定三方唯一id与用户
        $info = $this->param;
        $id = $info['id'] ?? 0;
        $uid = $this->uid ?? 0;
        $type = $info['type'] ?? 1; //类型 1 登录 2绑定
        $code = urldecode($info['code'] ?? '');
        if (empty($code) || empty($uid)) {
            ajax_return("参数错误", 0);
        }
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $ThirdSetModel = new ThirdSetModel();
        $ThirdSet = $ThirdSetModel->where("id", $id)->where("status", 1)->find();
        if (!isset($ThirdSet['id'])) {
            ajax_return("平台不存在", 0);
        }
        $ThirdSet = $ThirdSet->toArray();
        $Entry = new Entry($ThirdSet);
        $res = $Entry->action($ThirdSet['third_sys_sign'], ['code' => $code], "get_userInfo");
        //锁
        if (!$ApiRedis->lock("third_reg_" . $id . '_' . $res['username'])) {
            ajax_return("请勿频繁访问", 0);
        }
        $third_uid = $res['third_uid'];
        $UserThirdModel = new UserThirdModel();
        $wh = [];
        $wh[] = ['third_uid', '=', $third_uid];
        $wh[] = ['uid', '=', $uid];
        $wh[] = ['third_sys_sign', '=', $ThirdSet['third_sys_sign']];
        $UserThird = $UserThirdModel->where($wh)->find();
        if ($type == 1) {
            //登录的时候 如果没注册过 直接提示未绑定line
            if (!isset($UserThird['id'])) {
                ajax_return("当前账号未绑定", 0);
            } else {
                $uid = $UserThird['uid'];
            }
            $rsps = $this->third_dologin($uid);
        } else {
            //先删除用户id 和三方id用户信息
            UserThirdModel::whereOr(['uid' => $uid])->whereOr(['third_uid' => $third_uid])->delete();
            //绑定的时候 没绑定过就绑定 绑定过换绑
            $add_third_user = [];
            $add_third_user['uid'] = $uid;
            $add_third_user['third_uid'] = $third_uid;
            $add_third_user['third_sys_sign'] = $ThirdSet['third_sys_sign'];
            $res = UserThirdModel::insert($add_third_user);
            $rsps['code'] = $res ? 1 : 0;
        }
        return $rsps;
    }

    public function unbind_third_user()
    {
        //解除line 用户绑定
        $info = $this->param;
        $uid = $this->uid ?? 0;
        $id = $info['id'] ?? 0;
        if (empty($id) || empty($uid)) {
            ajax_return("参数错误", 0);
        }
        $ThirdSetModel = new ThirdSetModel();
        $ThirdSet = $ThirdSetModel->where("id", $id)->where("status", 1)->find();
        if (!isset($ThirdSet['id'])) {
            ajax_return("平台不存在", 0);
        }
        $wh = [];
        $wh[] = ['uid', '=', $uid];
        $wh[] = ['third_sys_sign', '=', $ThirdSet['third_sys_sign']];
        $res = UserThirdModel::where($wh)->delete();
        $rsps['code'] = $res ? 1 : 0;
        return $rsps;
    }


    public function third_dologin($uid)
    {
        $info = $this->param;
        $ApiRedis = new ApiRedis();
        $UserModel = new UserModel();
        $user = $UserModel->where('id', $uid)->find();
        if ($user['status'] != 1) {
            ajax_return('账号已封停', 101);
        }
        $key = Config::get("custom.jwt.jwt_key");//加密key
        $token = [
            "iss" => "",  //签发者 可以为空
            "aud" => "", //面象的用户，可以为空
            "iat" => Config::get("custom.jwt.iat"), //签发时间
            "nbf" => Config::get("custom.jwt.nbf"), //在什么时候jwt开始生效
            "exp" => Config::get("custom.jwt.exp"), //token 过期时间
            "uid" => $user['id'], //记录的user的信息
            "sign" => md5($user['id'] . $user['password']),
        ];
        if (isset($user['usertokenkey']) && $user['usertokenkey']) {
            $ApiRedis->delete($user['usertokenkey']);//删除key
        }

        $jwt = JWT::encode($token, $key, "HS256"); //根据参数生成了 token
        $user['usertokenkey'] = md5("tokenkey" . $jwt);
        $user['usertoken'] = $jwt;

        //设备类型
        $deviceType = $info['device_type'] ?? '无';
        $deviceType = strtoupper($deviceType);
        $user['device_type'] = in_array($deviceType, UserLoginModel::getDeviceTypeArr()) ? $deviceType : '无';

        //APP，IPHONE登录赠送五福次数
        if (in_array($user['device_type'], ['ANDROID', 'IPHONE', 'IPHONE'])) {
//            (new WuFuFactory())->giveCardAppLogin($user['id'], $user['rec_code']);#APP下载
            (new TaskFactory($user['id']))->pushMessageToFinishTaskUser($user['id'], true);#推送用户完成任务
        }

        event(new \app\event\UserLogin($user));
        // 自动参与累计充值活动
        MemberService::activityDiscountTrigger($user['id']);

        //检测用户VIP升级
//        Queue::push("User@upgrade", ['id' => $user['id'], 'money' => 0], 'upgrade');

        //老用户登录成功领取注册的五福卡片
        (new WuFuFactory())->giveCardFirstRegister($user['id'], $user['rec_code']);
        $ApiRedis->set($user['usertokenkey'], $user['usertoken'], env('login_expire_time', 3600));

        $rsps['data']['token'] = $user['usertoken'];
        $rsps['data']['token_key'] = $user['usertokenkey'];
        $rsps['data']['phone_bind_status'] = empty($user['phone']) ? 0 : 1;
        $rsps['data']['ispass'] = empty($user['password']) ? 0 : 1;
        $rsps['code'] = 1;
        return $rsps;
    }

    //推荐链接点击次数
    public function click_count_run()
    {
        $rec_code = isset($this->param['rec_code']) ? $this->param['rec_code'] : '';
        $acid = isset($this->param['acid']) ? $this->param['acid'] : '';
        if (empty($acid)) {
            PixelService::page($acid);
        }
        if (empty($rec_code)) {
            return ['code' => 1];
        }
        $user = UserModel::where('rec_code', $rec_code)->find();
        if ($user) {
            $user->rec_click = $user->rec_click + 1;
            $user->save();
            return ['code' => 1];
        } else {
            return ['code' => 1];
        }
    }
}