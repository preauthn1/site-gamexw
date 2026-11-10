<?php

namespace app\command;

use app\common\lib\Util;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use PhpOffice\PhpSpreadsheet\IOFactory;
use app\common\model\DefaultHeadModel;
use app\common\model\UserVerifyConfigModel;
use think\facade\Db;
use think\console\input\Argument;
use app\model\SysSets;

// T619用户导入脚本
class ImportT619 extends Command
{
    private $level = [];
    public $title = [];
    public $affs = [];
    public $phones = [];
    public $mails = [];
    public $id = 0;
    public $time = null;
    public $rec_codes = [];
    protected function configure()
    {
        $this->setName('import:t619')
        ->addArgument('is_reset', Argument::OPTIONAL, '是否重置数据')
        ->setDescription('T619用户导入');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->time = strtotime("-1 month");
        $game_id = $this->getBetGid();
        $file = "./assets/t619_move_users.xlsx";
        $isReset = $input->getArgument('is_reset');
        
        $objReader = IOFactory::createReader('Xlsx');
        $objPHPExcel = $objReader->load($file);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $arr = $this->getColumn($highestColumn);
        $data = [];
        $uids = [];
        foreach($arr as $k => $currentColumn){
            $this->title[$currentColumn] = $objPHPExcel->getActiveSheet()->getCell($currentColumn . "1")->getValue() ?? "-";
            for($i = 2; $i <= $highestRow; $i++) {
                $data[$i][$currentColumn] = $objPHPExcel->getActiveSheet()->getCell($currentColumn . $i)->getValue() ?? "";
                if($currentColumn == 'A'){
                    $uids[] = $data[$i][$currentColumn];
                }
            }
        }
        $max_id = max($uids);
        // 生成6000个MC00xx的用户
        /*
        for ($i = 1; $i <= 6000; $i++) {
            $uids[] = $max_id + $i;
            $user = [];
            $user['A'] = $max_id + $i;
            $user['B'] = "aff_moneclicks";
            $user['C'] = 'mc'.str_pad($i, 5, '0', STR_PAD_LEFT);
            $user['D'] = '1';
            $user['E'] = '';
            $user['F'] = '';
            $user['G'] = '';
            $user['H'] = '';
            $user['I'] = '';
            $user['J'] = '';
            $user['K'] = '';
            $user['L'] = '';
            $user['M'] = '';
            $user['N'] = '';
            $user['O'] = '';
            $user['P'] = 'Active';
            $user['Q'] = 'KYC已验证';
            $user['R'] = date("Y-m-d H:i:s");
            $user['S'] = '0.0.0.0';
            $user['T'] = date("Y-m-d H:i:s");    
            $user['U'] = 0;
            $user['V'] = 0;
            $user['W'] = 0;
            $user['X'] = 0;
            $user['Y'] = 0;
            $user['Z'] = '';
            $user['AA'] = '';
            $user['AB'] = '';
            $data[] = $user;
        }*/
        $uids = array_filter($uids);
        if($isReset == 1){
            $this->resetData($uids, $game_id);
            $output->writeln("重置数据完成");
            return;
        }
        $ins_sql = null;
        $keys = null;
        $agent_ins_sql = null;
        $agent_keys = null;
        $ins_data = [];
        $agent_ins_data = [];
        $verify_ins_data = [];
        $verify_keys = null;
        $verify_sql = null;
        $bet_sql = null;
        $bet_keys = null;
        $bet_ins_data = [];
        $withdrawal_ins_data = [];
        $withdrawal_keys = null;
        $withdrawal_sql = null;
        $offline_sql = null;
        $offline_keys = null;
        $offline_ins_data = [];
        $artificial_balance_ins_data = [];
        $artificial_balance_keys = null;
        $artificial_balance_sql = null;
        $phones = Db::name('user')->column('phone');
        $mails = Db::name('user')->column('mail');
        $rec_code = Db::name('user')->column('rec_code');
        // INSERT INTO `dq_artificial_balance` (`id`, `uid`, `a_type`, `mx_id`, `admin_name`, `price`, `water`, `add_time`, `lebal`, `msg`) VALUES (7, 86684157, 3, 72032, '系统管理员', 100.00, 2, 1727698938, 1, '赠送彩金');
        foreach($data as $k => $v) {
            if (!empty($v['C'])) {
                $this->affs[$v['C']] = $v['A'];
            }
            // 过滤重复手机号
            if (in_array($v['N'], $this->phones) || in_array($v['N'], $phones)) {
                $data[$k]['N'] = '';
            } else {
                $this->phones[] = $v['N'];
            }
            // 过滤重复邮箱
            if (in_array($v['I'], $this->mails) || in_array($v['I'], $mails)) {
                $data[$k]['I'] = '';
            } else {
                $this->mails[] = $v['I'];
            }
            // 过滤重复邀请码
            if (in_array($v['A'], $rec_code)) {
                while (true) {
                    $code = Util::random(8, true);
                    if (!in_array($code, $rec_code)) {
                        $this->rec_codes[$v['A']] = $code;
                        break;
                    }
                }
            } else {
                $this->rec_codes[$v['A']] = $v['A'];
            }
        }
        
        foreach($data as $k => $v) {
            // 实名认证信息
            $verifyInfo = $this->verify($v);
            if (empty($verify_keys)) {
                $verify_keys = implode(',', array_keys($verifyInfo));
                $verify_sql = "INSERT INTO `dq_user_verify` ({$verify_keys}) VALUES ";
            }

            // 用户信息
            $info = $this->userinfo($v);
            if (empty($keys)) {
                $keys = implode(',', array_keys($info));
                $ins_sql = "INSERT INTO `dq_user` ({$keys}) VALUES ";
            }

            // 代理信息
            $agent_info = $this->agentinfo($v);
            if (empty($agent_keys)) {
                $agent_keys = implode(',', array_keys($agent_info));
                $agent_ins_sql = "INSERT INTO `dq_user_agent` ({$agent_keys}) VALUES ";
            }
            $ins_data[] = str_replace('""', 'NULL', implode('","', $info));
            $agent_ins_data[] = implode('","', $agent_info);
            $verify_ins_data[] = implode('","', $verifyInfo);

            // 投注信息
            $gb_price = floatval($v['Y']);
            $gb_otherwin = floatval($v['AA'] ?? 0);
            if ($gb_price > 0) {
                $bet_info = [
                    'gb_id' => 0,
                    'gb_tcid' => 0,
                    'gb_gid' => $game_id,
                    'gb_uid' => $info['id'],
                    'gb_intime' => $this->time,
                    'gb_price' => $gb_price,
                    'gb_cjprice' => 0,
                    'gb_sn' => "T619_{$info['id']}",
                    'gb_winlose' => 3,
                    'gb_bettime' => $this->time,
                    'gb_overtime' => $this->time + 10,
                    'gb_winprice' => $gb_price + ($gb_otherwin * -1),
                    'gb_wincjprice' => 0,
                    'gb_backstateks' => 0,
                    'gb_cusfield' => 0,
                    'gb_betinfo' => '',
                    'is_kj_ls' => 0,
                    'gb_cflag' => 0,
                    'gb_cflagt' => 0,
                    'gb_gcflag' => 0,
                    'gb_gcflagt' => 0,
                    'gb_trans_no' => '',
                    'gb_otherwin' => $gb_otherwin * -1,
                    'gb_validwater' => $gb_price,
                ];
                if (empty($bet_keys)) {
                    $bet_keys = implode(',', array_keys($bet_info));
                    $bet_sql = "INSERT INTO `dq_game_bets` ({$bet_keys}) VALUES ";
                }
                $bet_ins_data[] = implode('","', $bet_info);
            }

            // 提现信息
            $withdrawal_info = $this->withdrawalinfo($v);
            if (empty($withdrawal_keys)) {
                $withdrawal_keys = implode(',', array_keys($withdrawal_info));
                $withdrawal_sql = "INSERT INTO `dq_user_withdrawal` ({$withdrawal_keys}) VALUES ";
            }
            if (!empty($withdrawal_info)) {
                $withdrawal_ins_data[] = implode('","', $withdrawal_info);
            }

            // 线下入款信息
            $offline_info = $this->offlineinfo($v);
            if (empty($offline_keys)) {
                $offline_keys = implode(',', array_keys($offline_info));
                $offline_sql = "INSERT INTO `dq_user_offline` ({$offline_keys}) VALUES ";
            }
            if (!empty($offline_info)) {
                $offline_ins_data[] = implode('","', $offline_info);
            }

            // 赠送彩金信息
            $artificial_balance_info = $this->artificial_balanceinfo($v);
            if (empty($artificial_balance_keys)) {
                $artificial_balance_keys = implode(',', array_keys($artificial_balance_info));
                $artificial_balance_sql = "INSERT INTO `dq_artificial_balance` ({$artificial_balance_keys}) VALUES ";
            }
            if (!empty($artificial_balance_info)) {
                $artificial_balance_ins_data[] = implode('","', $artificial_balance_info);
            }
        }
        $time = $this->time;
        Db::startTrans();
        try {
            // dump($ins_data);die;
            $ins_sql .= "(\"".implode('"),("', $ins_data)."\")";
            $agent_ins_sql .= "(\"".implode('"),("', $agent_ins_data)."\")";
            $verify_sql .= "(\"".implode('"),("', $verify_ins_data)."\")";
            $bet_sql .= "(\"".implode('"),("', $bet_ins_data)."\")";
            $withdrawal_sql .= "(\"".implode('"),("', $withdrawal_ins_data)."\")";
            $offline_sql .= "(\"".implode('"),("', $offline_ins_data)."\")";
            $artificial_balance_sql .= "(\"".implode('"),("', $artificial_balance_ins_data)."\")";
            $ret = Db::execute($ins_sql);
            $this->output->info('用户信息done');
            $ret2 = Db::execute($agent_ins_sql);
            $this->output->info('代理信息done');
            $ret3 = Db::execute($verify_sql);
            $this->output->info('验证信息done');
            $ret4 = Db::execute($bet_sql);
            $this->output->info('下注信息done');
            $ret5 = Db::execute($withdrawal_sql);
            $this->output->info('提现信息done');
            $ret6 = Db::execute($offline_sql);
            $this->output->info('充值信息done');
            $ret7 = Db::execute($artificial_balance_sql);
            $this->output->info('优惠信息done');
            // 生成余额明细
            Db::execute("INSERT INTO `dq_user_balance_mx` (SELECT 0 as id, {$time} as add_time,id,0 as old_price,balance as new_price,balance as price, 1 as type, '数据迁移' as msg, 0 as source_id FROM `dq_user` WHERE  id NOT IN(SELECT DISTINCT uid FROM dq_user_balance_mx) and balance > 0);");
            $this->output->info('余额明细信息done');
            // 生成用户扩展信息表
            Db::execute("INSERT INTO `dq_user_extra` (SELECT id as uid, '', '', '', '', ac_id, '' FROM `dq_user` WHERE id NOT IN(SELECT uid FROM dq_user_extra));");
            $this->output->info('扩展信息done');
            $this->tree();
            Db::commit();
            $this->output->writeln("处理完成，共处理".$ret."条用户数据");
        } catch(\Exception $e) {
            Db::rollback();
            $this->output->error("出错了:" . $e->getMessage());
            $this->output->error("出错了:" . $e->getTraceAsString());
        }
    }

    private function userinfo($user) {
        $rec_id = $this->affs[$user['B']] ?? (empty($user['B']) ? 0 : intval($user['B']));
        $rec_id2 = 0;
        $rec_id3 = 0;
        $tree = array_filter([$rec_id, $user['A']]);
        $tree_str = "/".implode('/', array_map(function($a){
                return $this->addId($a);
            }, $tree))."/";
        $l = count($tree);
        $qh = $user['M'];
        $phone = $user['N'];
        if (empty($user['R'])) {
            $user['R'] = date("Y-m-d H:i:s");
        }
        if (empty($user['T'])) {
            $user['T'] = date("Y-m-d H:i:s");
        }
        if (empty($user['C'])) {
            return [];
        }
        $r = [
            'id' => $this->addId($user['A']),
            'username' => $user['C'],
            // 'password' => '',
            'qh' => $qh,
            'phone' => $phone,
            'mail' => $user['I'],
            'rec_code' => $user['B'] == 'aff_moneclicks' && strpos($user['C'], 'mc') === 0 ? $user['C'] : ($this->rec_codes[$user['A']] ?? $user['A']),
            'level_id' => $this->getNumber($user['D']),
            'head_img' => DefaultHeadModel::getHeadOne(),
            'add_time' => strtotime(strlen($user['R']) > 10 ? $user['R'] : $user['R'] . " 00:00:00"),
            'login_time' => strtotime(strlen($user['T']) > 10 ? $user['T'] : $user['T'] . " 00:00:00"),
            'reg_ip' => empty($user['S']) ? '0.0.0.0' : $user['S'],
            'end_login_ip' => empty($user['S']) ? '0.0.0.0' : $user['S'],
            'rec_level' => $l,
            'rec_id' => $this->addId($rec_id),
            'rec_id2' => $this->addId($rec_id2),
            'rec_id3' => $this->addId($rec_id3),
            'balance' => floatval($user['X']),
            'tree' => $tree_str,
            'status' => $user['P'] == 'Active' ? 1 : 0,
            'phone_status' => $user['Z'] == '已驗證' && $phone ? 1 : 0,
            'phone_bind_status' => $user['Z'] == '已驗證' && $phone ? 1 : 0,
            'mail_bind_status' =>  $user['I'] ? 1 : 0,
        ];

        return $r;
    }

    private function agentinfo($user) {
        $rec_id = $this->affs[$user['B']] ?? $user['B'];
        $tree = array_filter([$rec_id, $user['A']]);
        $tree_str = "/".implode('/', array_map(function($a){
                return $this->addId($a);
            }, $tree))."/";
        //创建对应代理数据
        $add = [];
        $add['uid'] = $this->addId($user['A']);
        $add['type'] = strpos($user['C'], 'aff_') === 0 ? 1 : 0;
        $add['apply_time'] = $user['R'] ? strtotime(strlen($user['R']) > 10 ? $user['R'] : $user['R'] . " 00:00:00") : $this->time;
        $add['status'] = 3;
        $add['tree'] = $tree_str;
        $add['rec_id'] = $this->addId($rec_id);

        return $add;
    }

    private function verify($user) {
        $config = UserVerifyConfigModel::order('sort', 'asc')->select();
        $verifyInfo = [];
        $value_key = [
            "First_Name" => 'F',
            "Last_name" => 'E',
            "Nickname" => 'G',
            "Address_Details" => 'L',
            "City" => 'K',
            "Postal_Code" => 'J',
            "Sex" => 'H',
            "Birthday" => 'O',
        ];

          
        foreach($config as $k => $v) {
            if (!isset($value_key[$v['keyv']]) || empty($user[$value_key[$v['keyv']]])) {
                $verifyInfo[] = [
                    'name' => $v['name'],
                    'key' => $v['keyv'],
                    'type' => $v['type'],
                    'value' => '',
                ];
            } else {
                $verifyInfo[] = [
                    'name' => $v['name'],
                    'key' => $v['keyv'],
                    'type' => $v['type'],
                    'value' => $user[$value_key[$v['keyv']]],
                ];
            }
        }
        return [
            "uid" => $this->addId($user['A']),
            "content" => $verifyInfo ? addslashes(json_encode($verifyInfo, JSON_UNESCAPED_UNICODE)) : '',
            "status" => $user['Q'] == 'KYC已验证' ? 2 : 0,
            "create_time" => $this->time,
            "update_time" => $this->time,
        ];
    }
    private function addId($id) {
        if ($id == 0 || empty($id)) {
            return 0;
        }
        return $this->id + $id;
    }
    private function getColumn($highestColumn) {
        $columns = [];
        $length = strlen($highestColumn);
        $letters = range('A', 'Z');

        // 将列名转换为数字
        $number = 0;
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($highestColumn[$i]) - ord('A') + 1);
        }

        // 生成所有列名
        for ($n = 1; $n < $number; $n++) {
            $columnName = '';
            $dividend = $n;
            while ($dividend > 0) {
                $modulo = ($dividend - 1) % 26;
                $columnName = $letters[$modulo] . $columnName;
                $dividend = floor(($dividend - $modulo) / 26);
            }
            $columns[] = $columnName;
        }

        return $columns;
    }


    protected function tree() {
        $lists = Db::name('user')->order("id ASC")->column('id');
        if (!empty($lists)) {
            foreach ($lists as $userid) {
                $this->level = [];
                $this->getLeader1_1($userid);
                unset($this->level['rec_id']);
                if ($this->level) {
                    $tree = "/".implode('/', array_reverse(array_values($this->level)))."/{$userid}/";
                }else{
                    $tree = "/{$userid}/";
                }
                $trees = array_reverse(explode("/", trim($tree, '/')));
                
                $update = [
                    'tree' => $tree,
                    'rec_id2' => isset($trees[2]) && $trees[2] != $userid ? $trees[2] : 0,
                    'rec_id3' => isset($trees[3]) && $trees[3] != $userid ? $trees[3] : 0,
                    'rec_level' => count($trees),
                ];
                Db::name('user')->where("id", $userid)->update($update);
                $this->output->writeln("{$userid}===" . date("Y-m-d H:i:s") . "=={$tree}== ");
            }
        }
        $this->output->info("done！");
    }

    /**
     * 获取用户上级ID
     * @param type $id
     */
    private function getLeader1_1($id, $level = 0) {
        $leader1 = Db::name('user')->where('id', $id)->find();
        $this->level["leader{$level}"] = $leader1['id'];
        if ($leader1['rec_id'] > 0) {
            $level++;
            return $this->getLeader1_1($leader1['rec_id'], $level);
        }
    }

    private function getNumber($str) {
        preg_match_all('/\d+/', $str, $matches);
        return $matches[0][0] ?? 1;
    }

    private function getBetGid() {
        $name = 'T619迁移投注量';
        $game = Db::name('games')->where('g_code', $name)->find();
        if (empty($game)) {
            Db::name('games')->insert([
                'g_tcid' => 0,
                'g_web_tcid' => 0,
                'g_code' => $name,
                'g_tag' => $name,
                'g_icon' => '',
                'g_state' => 0,
                'g_backstatus' => 0,
                'g_fystatus' => 0,
                'g_show' => 0,
            ]);
            $game_id = Db::name('games')->getLastInsID();
        } else {
            $game_id = $game['g_id'];
        }
        $game_info = Db::name('game_infos')->where('gi_gid', $game_id)->find();
        if (empty($game_info)) {
            Db::name('game_infos')->insert([
                'gi_gid' => $game_id,
                'gi_lang' => 'CN',
                'gi_name' => $name,
            ]);
        }
        
        return $game_id;
    }

    private function withdrawalinfo($user) {
        $w_price = floatval($user['W']);
        if ($w_price <= 0) {
            return [];
        }
        return [
            'uid' => $this->addId($user['A']),
            'order_sn' => $user['A'],
            'channel' => 0,
            'w_price' => $w_price,
            'arrival_price' => $w_price,
            'arrival_prices' => $w_price,
            'method_id' => 0,
            'current_currency' => 'USD',
            'arrival_currency' => 'USD',
            'exchange_rate' => 1,
            'w_service' => 0,
            'w_remarks' => 'T619迁移',
            'status' => 4,
            'lebal' => 1,
            'pay_order_sn' => '',
            'pay_time' => $this->time,
            'pay_type' => 0,
            'reason' => '',
            'operator' => '',
            'update_time' => $this->time ,
            'add_time' => $this->time,
            'currency_name' => 'USD',
            'type' => 0,
            'third_err_remarks' => '',
            'user_remarks' => '',
            'is_lock' => 0,
            'check_time' => $this->time,
        ];
    }
    private function offlineinfo($user) {
        $price = floatval($user['U']);
        if ($price <= 0) {
            return [];
        }
        return [
            'type' => 0,
            'channel' => 0,
            'order_sn' => $user['A'],
            'uid' => $this->addId($user['A']),
            'bank_id' => 0,
            'bank_sk_id' => 0,
            'bankname' => '',
            'bankcrad' => '',
            'realname' => '',
            'paydate' => '',
            'apply_price' => $price,
            'arrival_price' => $price,
            'pay_id' => 0,
            'pay_currency' => 'USD',
            'arrival_currency' => 'USD',
            'exchange_rate' => 1,
            'pay_type' => 0,
            'pay_ip' => '0.0.0.0',
            'othr_number' => '',
            'remarks' => 'T619迁移',
            'status' => 4,
            'admin_name' => '',
            'update_time' => $this->time,
            'add_time' => $this->time,
            'tree' => '',
            'lebal' => 1,
            'first' => 0,
            'receive' => 0,
            'credentials' => '',
            'set_content' => '',
            'currency_name' => 'USD',
            'not_auto_action' => 0,
            'rebate' => 0,
            'user_remarks' => '',
        ];
    
    }

    private function artificial_balanceinfo($user) {
        $price = floatval($user['AB'] ?? 0);
        if ($price <= 0) {
            return [];
        }
        return [
            'uid' => $this->addId($user['A']),
            'a_type' => 3,
            'mx_id' => 0,
            'admin_name' => '系统管理员',
            'price' => $price,
            'water' => 0,
            'add_time' => $this->time,
            'lebal' => 1,
            'msg' => 'T619迁移优惠金额',
        ];
    }

    private function resetData($uids, $game_id) {
        if (empty($uids)) {
            return;
        }
        Db::execute("DELETE FROM `dq_user` WHERE id IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_user_balance_mx` WHERE uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_user_extra` WHERE uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_user_agent` WHERE uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_user_verify` WHERE uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_user_withdrawal` WHERE uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_user_offline` WHERE uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_game_bets` WHERE gb_gid = {$game_id};");
        Db::execute("DELETE FROM `dq_artificial_balance` WHERE uid IN(".implode(',', $uids).");");
        // third_stats_global
        Db::execute("DELETE FROM `dq_third_stats_global` WHERE su_uid IN(".implode(',', $uids).");");
        Db::execute("DELETE FROM `dq_third_stats_users` WHERE su_uid IN(".implode(',', $uids).");");
        // 清理多时区统计表
        $zones = SysSets::getSetByCode('COUNT_DATA_TIMEZONE_LIST');
        if (!empty($zones)) {
            for($i=0;$i<count($zones);$i++){
                $ex=strtolower($zones[$i][0]);
                if ($ex!="default"){
                    $table = 'dq_third_stats_global';
                    $full_table_name=$table.'_'.$ex;
                    $rlt=Db::query("show tables like '{$full_table_name}'");
                    if (!empty($rlt)){
                        Db::execute("DELETE FROM `{$full_table_name}` WHERE su_uid IN(".implode(',', $uids).");");
                    }
                    $table = 'dq_third_stats_users';
                    $full_table_name=$table.'_'.$ex;
                    $rlt=Db::query("show tables like '{$full_table_name}'");
                    if (!empty($rlt)){
                        Db::execute("DELETE FROM `{$full_table_name}` WHERE su_uid IN(".implode(',', $uids).");");
                    }
                }
            }
        }
    }
}