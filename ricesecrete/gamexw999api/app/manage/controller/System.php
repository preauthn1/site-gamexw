<?php
namespace app\manage\controller;
use app\common\model\AdminLogModel;
use app\common\model\MailNotifyTemplateModel;
use app\common\model\SystemUpdModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\api\redis\ApiRedis;
use app\common\model\UserAdminLogModel;
use app\common\redis\CommomRedis;
use app\common\Utils;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
use app\model\SysSets;

class System
{	
    protected $middleware = ['ManageCheckLogin',"Language"];
    //编辑
    public function edit(){
    	$param=Request::param();
    	if (isset($param['edit'])) {
            $info=$param['info'];
            if(isset($info['phone_open'])){
                $info['mail_phone_ip_num'] = [
                    'mail_open' => isset($info['mail_open']) && $info['mail_open'] == 1 ? 1 : 0,
                    'mail_time' => intval($info['mail_time'] ?? 0),
                    'mail_ip_num' => intval($info['mail_ip_num'] ?? 0),
                    'phone_open' => isset($info['phone_open']) && $info['phone_open'] == 1 ? 1 : 0,
                    'phone_time' => intval($info['phone_time'] ?? 60),
                    'phone_ip_num' => intval($info['phone_ip_num'] ?? 0),
                ];
            }

            if(isset($info['mail_phone_ip_num'])) {
                $info['mail_phone_ip_num'] = empty($info['mail_phone_ip_num']) ? '' : serialize($info['mail_phone_ip_num']);
            }
            if(isset($info['withdraw_notes'])) {
                $info['withdraw_notes'] = empty($info['withdraw_notes']) ? '' : serialize($info['withdraw_notes']);
            }
            if(isset($info['offline_notes'])) {
                $info['offline_notes'] = empty($info['offline_notes']) ? '' : serialize($info['offline_notes']);
            }
            if(isset($info['detail_notes'])) {
                $info['detail_notes'] = empty($info['detail_notes']) ? '' : serialize($info['detail_notes']);
            }
            if(isset($info['user_verify_notes'])) {
                $info['user_verify_notes'] = empty($info['user_verify_notes']) ? '' : serialize($info['user_verify_notes']);
            }
            if(isset($info['quick_withdrawal'])) {
                $info['quick_withdrawal'] = empty($info['quick_withdrawal']) ? '' : trim($info['quick_withdrawal'], ',');
            }
            if(isset($info['usdt_quick_withdrawal'])) {
                $info['usdt_quick_withdrawal'] = empty($info['usdt_quick_withdrawal']) ? '' : trim($info['usdt_quick_withdrawal'], ',');
            }
            if(isset($info['quick_recharge'])) {
                $info['quick_recharge'] = empty($info['quick_recharge']) ? '' : trim($info['quick_recharge'], ',');
            }
            if(isset($info['hot_quick_recharge'])) {
                $info['hot_quick_recharge'] = empty($info['hot_quick_recharge']) ? '' : trim($info['hot_quick_recharge'], ',');
            }
            if(isset($info['usdt_quick_recharge'])) {
                $info['usdt_quick_recharge'] = empty($info['usdt_quick_recharge']) ? '' : trim($info['usdt_quick_recharge'], ',');
            }
            if(isset($info['area_code'])) {
                $info['area_code'] = empty($info['area_code']) ? '' : trim($info['area_code'], ',');
            }

            $debug = [];
            foreach ($info as $key => $value) {
                $row = SysSets::where('set_code', $key)->find();
                if (empty($row) || $value === '') {
                    $debug[] = $key;
                    continue;
                }
                $old = $row->set_value;
                if (in_array($row->set_type, ['list', 'text'])) {
                    $old = $row->set_valuetext;
                    $row->set_valuetext = $value;
                } elseif (in_array($row->set_type, ['int', 'uint', 'number', 'unumber'])) {
                    $row->set_value = intval($value);
                } elseif (in_array($row->set_type, ['bool'])) {
                    $row->set_value = $value == 1 ? 1 : 0;
                } else {
                    $row->set_value = strval($value);
                }
                if ($old != $value) {
                    AdminLogModel::addLog([
                        'name' => $row->set_title,
                        'key' => $key,
                        'value' => $old,
                    ], [
                        'key' => $key,
                        'value' => $value
                    ], 'sys_sets', '系统设定编辑');
                }
                $row->save();
            }
            SysSets::buildCache();
            return Respons::run(1, [$info]);
    	}
    	if (isset($param['find'])) {
            $admin = SysSets::getSets(false);
            $admin['tournament_notes'] = empty($admin['tournament_notes']) ? []:unserialize($admin['tournament_notes']);
            $admin['withdraw_notes'] = empty($admin['withdraw_notes']) ? []:unserialize($admin['withdraw_notes']);
            $admin['offline_notes'] = empty($admin['offline_notes']) ? []:unserialize($admin['offline_notes']);
            $admin['detail_notes'] = empty($admin['detail_notes']) ? []:unserialize($admin['detail_notes']);
            $admin['user_verify_notes'] = empty($admin['user_verify_notes']) ? []:unserialize($admin['user_verify_notes']);
            $admin['mail_phone_ip_num'] = empty($admin['mail_phone_ip_num']) ? '': unserialize($admin['mail_phone_ip_num']);
            if (is_array($admin['mail_phone_ip_num']) && !empty($admin['mail_phone_ip_num'])) {
                foreach ($admin['mail_phone_ip_num'] AS $key => $val) {
                    $admin[$key] = $val;
                }
            }
            if( isset($admin['COUNT_DATA_TIMEZONE_LIST']) ){
                $admin['COUNT_DATA_TIMEZONE_LIST'] = $this->setListFormat($admin['COUNT_DATA_TIMEZONE_LIST']);
            }
//            $MailNotify = MailNotifyTemplateModel::find() ?? [] ;
//            $admin = array_merge($admin, $MailNotify);
            $admin['pay_list'] = Utils::getOptions('pay_set', ['id', 'pay_name'], [['isdel', '=', 0], ['is_withdra', '=', 1], ['pay_status', '=', 1]]);
            return Respons::run(1, $admin);
    	}
    }

    //设置list的数据格式
    private function setListFormat($value){
        $str = '';
        foreach ($value as $v){
            if( $str ){
                $str .= ','.implode('|', $v);
            }else{
                $str = implode('|', $v);
            }
        }
        return $str;
    }
    
    //维护列表
    public function upd_list(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page=$param['page'];
            $limit=$param['limit'];
            $SystemUpdModel =new SystemUpdModel();
            $rsps['data']=$SystemUpdModel
                ->order("id desc")
                ->paginate($limit,false,['query' => $param]);
            foreach($rsps['data'] as &$value){
                $value['start_time'] = date("Y-m-d H:i:s",$value['start_time']);
                $value['end_time'] = date("Y-m-d H:i:s",$value['end_time']);
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return View::fetch('system/upd_list');
    }

    //添加维护
    public function upd_add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info=$param['info'];

            if (!($info['start_time'] || $info['end_time'])) {
                return Respons::run(0,[],'请输入时间');
            }
            if (!($info['title'])) {
                return Respons::run(0,[],'请输入公告标题');
            }
            if (!($info['content'])) {
                return Respons::run(0,[],'请输入公告内容');
            }
            $add['admin_name']=Session::get("adminInfo.name");
            $add['status']=$info['status'];
            $add['title']=$info['title'];
            $add['content']=$info['content'];
            $add['start_time']=strtotime($info['start_time']);
            $add['end_time']=strtotime($info['end_time']);
            $SystemUpdModel=new SystemUpdModel();
            $res=$SystemUpdModel->insertGetId($add);
            return Respons::run($res?1:0);
        }
        return View::fetch('system/upd_add');
    }

    public function upd_edit_status(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $info['admin_name']=Session::get("adminInfo.name");
            $SystemUpdModel=new SystemUpdModel();
            $res=$SystemUpdModel->update($info);
            return Respons::run($res?1:0);
        }
    }

    public function clear_cache() {
        $CommomRedis=new CommomRedis();
        //锁
        if (!$CommomRedis->lock("clear_cache", 60)) {
            ajax_return("请勿频繁访问", 0);
        }
        SysSets::clearCache();
        return Respons::run(1);
    }
}
