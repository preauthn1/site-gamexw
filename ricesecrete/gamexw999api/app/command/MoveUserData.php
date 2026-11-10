<?php

namespace app\command;

use app\api\redis\ApiRedis;
use app\common\lib\Encrypt;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\model\UserAgentModel;
use app\common\model\UserBalanceMxModel;
use app\common\model\UserExtraModel;
use app\common\model\UserModel;
use phpseclib3\Crypt\RSA;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class MoveUserData extends Command
{


    public $id = 1000000;
    private $errAgentIds = [];

    private $successUserNum = 0;

    private static $privateKey = 'a94f231119adjufg';

    protected function configure()
    {
        // 指令配置
        $this->setName('moveuser')
            ->addArgument('is_reset', null, Argument::OPTIONAL, '是否重置数据')
            ->setDescription('move user data');
    }

    public $total = 0;
    public $c = 0;
    public $start_time = 0;
    public $mobile = [];

    protected function execute(Input $input, Output $output){
        ini_set('memory_limit', '-1');
        $isReset = $input->getArgument('is_reset');
        $apiRedis = new ApiRedis();
        $moveCount = env('user_move_data_count', 5000);
        $startId = 0;
        $redisKey = 'user_move_start_id_key';
        if( $apiRedis->has($redisKey) && $isReset != 1 ){
            $startId = $apiRedis->get($redisKey);
        }
        $time = $this->start_time = time();
        $this->total = Db::table('user_agent')->count();
        Db::startTrans();
        try {
            $this->handleUserData($output, $moveCount, $startId);
            // 生成余额明细
            Db::execute("INSERT INTO `dq_user_balance_mx` (SELECT 0 as id, {$time} as add_time,id,0 as old_price,balance as new_price,balance as price, 1 as type, '数据迁移' as msg, 0 as source_id FROM `dq_user` WHERE balance > 0);");
            // 生成用户扩展信息表
            Db::execute("INSERT INTO `dq_user_extra` (SELECT id as uid, '', '', '', '' FROM `dq_user`);");
            Db::commit();
        } catch(\Exception $e) {
            Db::rollback();
            dump([$e->getMessage(), $e->getTraceAsString()]);
        }
        $output->writeln("所有数据处理完成,成功处理{$this->successUserNum}条用户数据，失败用户id：" . implode(',', $this->errAgentIds));
    }

    private function handleUserData($output, $moveCount = 50000, $startId = 0){
        $this->runtime();
        $output->writeln("本轮数据开始处理，起始ID：{$startId}");
        $apiRedis = new ApiRedis();
        $redisKey = 'user_move_start_id_key_v1';
        //判断是否存在表user found  user_agent
        $tables = ['user', 'funds', 'user_agent'];
        foreach ($tables as $table) {
            $exist = Db::query("show tables like '{$table}'");
            if (!$exist) {
                $output->error("table {$table} not found");
                return false;
            }
        }
        $sql = <<<SQL
SELECT 
a.id,a.`name` as username,a.`password`, a.salt,a.email,a.telphone_code as qh,a.mobile as phone,a.created,a.shareholder_switch,a.agent_switch,inet6_ntoa(a.ip) as ip,a.last_login,inet6_ntoa(a.login_ip) as login_ip,
b.uid_agent as rec_id,b.`code` as rec_code, b.uid_agents as tree, b.`level`,
c.balance + (select sum(balance) from funds_child where pid = c.id) as balance,c.freeze_withdraw
 FROM `user` a LEFT JOIN user_agent b ON a.id = b.user_id 
LEFT JOIN funds c ON a.wallet_id = c.id WHERE a.id > {$startId} ORDER BY a.id ASC LIMIT $moveCount
SQL;

        $ins_sql = null;
        $keys = null;
        $agent_ins_sql = null;
        $agent_keys = null;
        $agentList = Db::query($sql);
        if (empty($agentList)) {
            return true;
        }
        $ins_data = [];
        $agent_ins_data = [];
        foreach ($agentList as $user){
            //创建用户信息
            $info = $this->userinfo($user);
            if (empty($keys)) {
                $keys = implode(',', array_keys($info));
                $ins_sql = "INSERT INTO `dq_user` ({$keys}) VALUES ";
            }
            $agent_info = $this->agentinfo($user);
            if (empty($agent_keys)) {
                $agent_keys = implode(',', array_keys($agent_info));
                $agent_ins_sql = "INSERT INTO `dq_user_agent` ({$agent_keys}) VALUES ";
            }
            $ins_data[] = implode('","', $info);
            $agent_ins_data[] = implode('","', $agent_info);
            $startId = $user['id'];
        }
        $ins_sql .= "(\"".implode('"),("', $ins_data)."\")";
        $ret = Db::execute($ins_sql);
        $agent_ins_sql .= "(\"".implode('"),("', $agent_ins_data)."\")";
        $ret2 = Db::execute($agent_ins_sql);
        $this->successUserNum += $ret;
        $this->c += $ret;
//        dump($ins_sql);
        $apiRedis->set($redisKey, $startId);
        $output->writeln("本轮数据处理完成，共处理".$ret."条用户数据");
        $output->writeln("等待5秒后开始处理下一批用户数据，起始ID：{$startId}");
        sleep(1);
        $this->handleUserData($output, $moveCount, $startId);
    }

    private function userinfo($user) {
        $rec_id2 = 0;
        $rec_id3 = 0;
        $tree = explode(",", $user['tree']);
        $tree_str = "/".implode('/', array_map(function($a){
                return $this->addId($a);
            }, $tree))."/";
        $l = count($tree);
        if ($l > 2) {
            $rec_id2 = $tree[$l - 3];
            if (isset($tree[$l - 4]) && $tree[$l - 4] != $user['id']) {
                $rec_id3 = $tree[$l - 4];
            }
        }
        $phone = self::RSADecrypt($user['phone']);
        if (!isset($this->mobile[$phone])) {
            $this->mobile[$phone] = 1;
        } else {
            $phone = null;
        }
        $r = [
            'id' => $this->addId($user['id']),
            'username' => $user['username'],
            'password' => $user['password'],
            'qh' => ltrim($user['qh'], '+'),
            'phone' => empty($phone) ? $user['username'] : $phone,
            'salt' => $user['salt'],
            'mail' => $user['email'] ?: $user['username'] . '@example.com',
            'rec_code' => $user['rec_code'],//Util::rec_code(), #邀请码重新生成，他们的有重复
            'head_img' => DefaultHeadModel::getHeadOne(),
            'reg_url' => '',
            'add_time' => strtotime($user['created']),
            'login_time' => intval($user['last_login']),
            'reg_ip' => $user['ip'],
            'end_login_ip' => $user['login_ip'],
            'rec_level' => $user['level'],
            'rec_id' => $this->addId($user['rec_id']),
            'rec_id2' => $this->addId($rec_id2),
            'rec_id3' => $this->addId($rec_id3),
            'balance' => $user['balance'] / 100,
            'savings' => $user['freeze_withdraw'] / 100,
            'tree' => $tree_str,
        ];

        return $r;
    }

    private function agentinfo($user) {
        $tree = explode(",", $user['tree']);
        $tree_str = "/".implode('/', array_map(function($a){
                return $this->addId($a);
            }, $tree))."/";
        //内部代理（股东）
        $agentType = $user['shareholder_switch'] == 1 ? 1 : 0;
        //创建对应代理数据
        $add = [];
        $add['uid'] = $this->addId($user['id']);
        $add['type'] = $agentType;
        $add['apply_ip'] = $user['ip'];
        $add['apply_time'] = strtotime($user['created']);
        $add['status'] = 3;
        $add['tree'] = $tree_str;
        $add['rec_id'] = $this->addId($user['rec_id']);

        return $add;
    }
    private function addId($id) {
        if ($id == 0) {
            return 0;
        }
        return $this->id + $id;
    }

    private function runtime() {
        $time = timediff($this->start_time, time());
        $proc = sprintf4f($this->c / $this->total * 100);
        $timeStr = "{$time['hour']}:{$time['min']}:{$time['sec']}";
        $this->output->warning("{$this->c}/{$this->total}->{$proc}%.运行时间:{$timeStr}");
    }

    //创建用户信息
    private function createUser($output, $user, $parentId = 0 )
    {
        $output->writeln("开始创建用户{$user['id']}信息，上级id为{$parentId}");

        $rec_id = 0;
        $rec_id2 = 0;
        $rec_id3 = 0;
        if ($parentId) {
            $agent = UserModel::get_agent_id($parentId);
            $rec_id = $agent[0];
            $rec_id2 = $agent[1];
            $rec_id3 = $agent[2];
            $parentDetail = UserModel::find($parentId);
            if (!$parentDetail) {
                $output->error("开始创建用户{$user['agent_id']}信息错误，上级数据不存在{$parentId}");
                $this->errAgentIds[] = $user['agent_id'];
                return false;
            }
            $parentTree = $parentDetail['tree'];
        }
        //判断用户是否存在，存在则不处理下面
        $condition = [];
        $condition[] = ['username', '=', $user['name']];
        if( UserModel::where($condition)->value('id') ){
            $output->warning("用户{$user['id']}已存在，上级id{$parentId}");
            return true;
        }
        //判断用户是否存在，存在则不处理下面
        if( $user['email'] ){
            $condition = [];
            $condition[] = ['mail', '=', $user['email']];
            if( UserModel::where($condition)->value('id') ){
                $output->warning("用户{$user['id']}已存在，上级id{$parentId}");
                return true;
            }
        }
        //获取用户余额
        $oldUserBalanceDetail = Db::table('funds')
            ->where('id', $user['wallet_id'])
            ->find();
        $data = [
            'username' => $user['name'],
            'password' => $user['password'],
            'salt' => $user['salt'],
            'mail' => $user['email'] ?: $user['name'] . '@example.com',
            'rec_code' => Util::rec_code(), #邀请码重新生成，他们的有重复
            'head_img' => DefaultHeadModel::getHeadOne(),
            'reg_url' => getAppUrl(false),
            'reg_ip' => Util::get_ip(),
            'rec_id' => $rec_id,
            'rec_id2' => $rec_id2,
            'rec_id3' => $rec_id3,
            'balance' => 0,
            'savings' => 0,
        ];
        //去掉区号前的+号
        $data['qh'] = ltrim($user['telphone_code'], '+');
        if( $user['mobile'] ){
            $data['phone'] = self::RSADecrypt($user['mobile']);
            //判断用户是否存在，存在则不处理下面
            $condition = [];
            $condition[] = ['phone', '=', $data['phone']];
            if( UserModel::where($condition)->value('id') ){
                $output->warning("用户{$user['id']}已存在，上级id{$parentId}");
                return true;
            }
        }
        if( $oldUserBalanceDetail ){
            $data['balance'] = $oldUserBalanceDetail['balance'];
            $data['savings'] = $oldUserBalanceDetail['freeze_withdraw'];
        }
        $this->successUserNum += 1;
        //创建数据
        $userNew = UserModel::create($data);
        if( !$userNew ){
            $output->error("创建{$user['agent_id']}错误，上级id{$parentId}");
            $this->errAgentIds[] = $user['agent_id'];
            return false;
        }
        $id = $userNew->id;
        //有余额的生成一条余额记录
        if( $data['balance'] > 0 ){
            //操作明细
            $res = UserBalanceMxModel::cz_mx($id, '数据迁移', 0, $data['balance'], '+');
            if( !$res ){
                $output->error("创建{$user['agent_id']}得余额日志错误，上级id{$parentId}");
                $this->errAgentIds[] = $user['agent_id'];
                return false;
            }
        }

        if (empty($parentTree)) {
            $tree = "/" . $id . "/";
        } else {
            $tree = $parentTree . $id . "/";
        }
        //添加额外信息表
        UserExtraModel::create(['uid' => $id]);
        $new_tree = array_filter(explode("/", $tree));
        $userNew->tree = $tree;
        $userNew->rec_level = count($new_tree);
        $res = $userNew->save();
        if( !$res ){
            $output->error("修改{$user['agent_id']}错误，上级id{$parentId}");
            $this->errAgentIds[] = $user['agent_id'];
            return false;
        }

        //判断是否是代理
        if( $user['agent_switch'] == 1 || $user['shareholder_switch'] == 1 ){
            $agentType = 0;
            if( $user['shareholder_switch'] == 1 ){
                //内部代理（股东）
                $agentType = 1; //股东
            }
            //创建对应代理数据
            $add = [];
            $add['uid'] = $id;
            $add['type'] = $agentType;
            $add['apply_ip'] = Util::get_ip();
            $add['apply_time'] = time();
            $add['status'] = 3;
            $add['tree']=$tree;
            $add['rec_id']=$rec_id;
            UserAgentModel::insert($add);
        }

        //查询原来的用户下级代理用户信息
        $agentList = Db::table('user_agent')
            ->where('uid_agent', $user['id'])
            ->order('id asc')
            ->select();
        foreach ($agentList as $_agent){
            $_user = Db::table('user')->where('id', $_agent['user_id'])->find();
            if( !$_user ){
                $output->error("代理{$_agent['id']}对应用户数据不存在");
                $this->errAgentIds[] = $_agent['id'];
                return false;
            }
            $_user['agent_id'] = $_agent['id'];
             $this->createUser($output, $_user, $id);
        }
        $output->writeln("用户{$user['id']}这条线数据创建成功，上级id为{$parentId}");
        return true;
    }

    //加密
    public static function RSAEncrypt(string $data = null)
    {
        if (!strlen($data)) {
            return $data;
        }

        return self::Xmcrypt()
            ->encrypt($data) ?? $data;
    }

    //解密
    public static function RSAPatch(array &$data = null, int $handler = Encrypt::DECRYPT)
    {
        if (!$data) {
            return $data;
        }
        foreach ($data as $key => &$datum) {
            $datum = is_object($datum) ? ((array) $datum) : $datum;
            if (is_array($datum)) {
                $datum = self::RSAPatch($datum, $handler);
            } else {
                if (in_array($key, self::NeedFilterKeywords(), true)) {
                    $datum = Encrypt::DECRYPT == $handler ? self::RSADecrypt($datum) : self::RSAEncrypt($datum);
                }
            }
        }

        return $data;
    }


    /**
     * 解密.
     *
     * @param string $data
     *
     * @return null|string
     */
    public static function RSADecrypt(string $data = null)
    {
        if (!strlen($data)) {
            return $data;
        }

        return self::Xmcrypt()
            ->decrypt($data) ?? $data;
    }


    /**
     * @return array
     */
    public static function NeedFilterKeywords()
    {
        return ['email', 'mobile', 'qq', 'weixin', 'skype', 'idcard', 'telephone', 'card'];
    }

    /**
     * openssl 双向加密.
     *
     * @return null|Encrypt
     */
    public static function Xmcrypt()
    {
        global $app;
        static $mt = null;
        if (is_null($mt)) {
            $key = self::$privateKey;
            $mt = new Encrypt($key);
        }

        return $mt;
    }
}