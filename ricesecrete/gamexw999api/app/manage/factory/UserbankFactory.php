<?php
namespace app\manage\factory;
use app\common\Utils;
use think\facade\{Request};
use app\common\lib\{Respons, Uploads, Util};
use app\common\model\{UserBankModel};

class UserbankFactory {

    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list() {
        $param=Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $bank_type = $param['type'] ?? 0;
        $where = [];
        if(isset($param['recycle_bin']) && $param['recycle_bin'] == 1){
            $where[] = ["delete_time", '>', '0'];
        } else {
            $where[] = ["delete_time", '=', '0'];
        }
        if(isset($param['keyword']) && !empty($param['keyword'])){
            $where[] = ['a.bank_name|a.bank_number|a.bank_open|a.bank_hm|d.username', '=',$param['keyword']];
        }
        if (isset($param['uid']) && $param['uid']!='' && $param['uid'] > 0) {
            $where[] = ["uid", "=",$param['uid']];
        }
        if (isset($param['bank_id']) && $param['bank_id']!='' && $param['bank_id'] > 0) {
            $where[] = ["a.id", "=",$param['bank_id']];
        }
        if (isset($param['currency_id']) && $param['currency_id']!='' && $param['currency_id'] > 0) {
            $where[] = ["a.currency_id", "=",$param['currency_id']];
        }
        if (isset($param['bank_type']) && $param['bank_type']!='') {
            $where[] = ["a.bank_name", "=",$param['bank_type']];
        }
        if (isset($param['mail_phone']) && $param['mail_phone']!='') {
            $where[]=["d.mail|d.phone","like","%".$param['mail_phone']."%"];
        }
        $where[] = ['bank_type', '=', $bank_type];
        if (isset($param['export']) && $param['export'] == 1) {
            $limit = 999999;
        }
        $rsps['data'] = UserBankModel::where($where)->alias('a')
            ->leftJoin('dq_user d','a.uid=d.id')
            ->with('currency')
            ->field('a.*,d.username, d.login_ip_count, d.mail, d.phone')
            ->order("id desc")
            ->removeOption('soft_delete')
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            $value['delete_time']=$value['delete_time'] ?date("Y-m-d H:i:s",$value['delete_time']):"";
        }
        if (isset($param['export']) && $param['export'] == 1) {
            $list = $rsps['data'];
            switch ($param['type']){
                case 0:
                    $header = [
                        'uid' => lang('用户UID'),
                        'add_time' => lang('添加时间'),
                        'country_code' => lang('货币'),
                        'currency_withdrawal_rate' => lang('提现汇率'),
                        'username' => lang('用户名'),
                        'bank_name' => lang('银行名称'),
                        'bank_number' => lang('银行卡号'),
                        'bank_hm' => lang('开户者姓名'),
                    ];
                    break;
                case 1:
                    $header = [
                        'uid' => lang('用户UID'),
                        'add_time' => lang('添加时间'),
                        'country_code' => lang('货币'),
                        'currency_withdrawal_rate' => lang('提现汇率'),
                        'username' => lang('用户名'),
                        'bank_name' => lang('USDT链接名'),
                        'bank_number' => lang('UST收款地址'),
                    ];
                    break;
                case 2:
                    $header = [
                        'uid' => lang('用户UID'),
                        'add_time' => lang('添加时间'),
                        'country_code' => lang('货币'),
                        'currency_withdrawal_rate' => lang('提现汇率'),
                        'username' => lang('用户名'),
                        'bank_name' => lang('钱包名称'),
                        'bank_number' => lang('钱包收款地址'),
                    ];
                    break;
                case 3:
                    $header = [
                        'uid' => lang('用户UID'),
                        'add_time' => lang('添加时间'),
                        'country_code' => lang('货币'),
                        'currency_withdrawal_rate' => lang('提现汇率'),
                        'username' => lang('用户名'),
                        'bank_name' => lang('类型'),
                        'bank_number' => lang('账号'),
                        'bank_hm' => lang('姓名'),
                        'financial' => lang('税号'),
                    ];
                    break;
                default:
                    throw new \think\Exception('类型错误', 0);
            }
            $file_name = genCsv($header, $list,lang('会员收款账号').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($where)) {
                Utils::addOptLogByHandle('会员收款账号导出');
            }
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function edit_detail() {
        $param=Request::param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("id不能为空"), 0);
        }
        $rsps['data'] = UserBankModel::where(['id'=>$param['id']])->find();
        $rsps['currency'] = Util::getCurrency();
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
    
}
?>
