<?php
namespace app\manage\controller;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\common\model\SystemExcelModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\Utils;
use app\manage\factory\AgentListFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use app\common\lib\Respons;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
use app\common\redis\CommomRedis;
class AgentList
{   
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $rsps = (new AgentListFactory($request->token->uid))->lists();
        return $rsps;
    }

    //导出数据
    public function excel(){        
        $param = Request::param();
        $where=[["b.status","=",3]];//通过的代理  
        $where1 = $where2 = [];
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $where1[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
        $where1[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        $where2[]=["a.update_time",">=", Utils::getQueryDateTime($date[0])];
        $where2[]=["a.update_time","<=", Utils::getQueryDateTime($date[1], true)];
        
        if (isset($param['username']) && $param['username']!='') {
            $where[] = ["a.id|a.username|a.mail|a.phone", "=", $param['username']];
        }

        if (isset($param['rec_code']) && $param['rec_code']!='') {
            $where[]=["a.rec_code","like","%".$param['rec_code']."%"];
        }

        $where_str = "";
        $rec_user = null;
        if (isset($param['id']) && !empty($param['id'])) {
            $rec_user = UserModel::where("id",$param['id'])->find();//查看用户是否存在
            if (!isset($rec_user['id'])) {
               return Respons::run(0,[],'账号不存在');
            }
            $agent_sql = unLevelSql($rec_user['id']);
            $where_str = "a.id in(".$agent_sql.")";
        }
            
        $data = UserModel::alias("a")->leftJoin("dq_user_agent b","a.id=b.uid")->where($where)->where($where_str)->order("id desc")->field("a.*")->select();
        $arr=[];

        $where1[] = ["a.lebal","=",1];//正式用户
        $where2[] = ["a.lebal","=",1];//正式用户
		
        foreach ($data as $key => $value) {
            $agent_sql = unLevelSql($value['id']);
            $arr[$key]['count_pnumber'] = UserModel::alias("a")->where("a.id in(".$agent_sql.") and a.lebal=1")->count() - 1;#减去自身
            $arr[$key]['new_reg'] = UserModel::alias("a")->where("a.id in(".$agent_sql.")")->where($where1)->count();
            $arr[$key]['recharge_pnumber'] = UserOfflineModel::getRechargeUserCount($where2,"a.uid in(".$agent_sql.")");//充值人数
            $recharge = UserOfflineModel::getRechargeUserAmount($where2,"a.uid in(".$agent_sql.")");//充值人数
            $arr[$key]['recharge_amount']=$recharge??0;            
            $arr[$key]['first_recharge_pnumber'] =  UserOfflineModel::getRechargeUserCount($where2, "a.uid in(".$agent_sql.") and a.first=1"); //首充总人数     
            $arr[$key]['first_recharge_zb'] = round((($arr[$key]['recharge_pnumber']!=0?$arr[$key]['first_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
            $arr[$key]['two_recharge_pnumber'] =  UserOfflineModel::getRechargeUserCount($where2, "a.uid in(".$agent_sql.") and a.first<>1");  //复充总人数
            $arr[$key]['two_recharge_zb'] = round((($arr[$key]['recharge_pnumber']!=0?$arr[$key]['two_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
            $arr[$key]['withdrawal_pnumber'] = UserWithdrawalModel::getWithdrawUserCount($where1,"a.uid in(".$agent_sql.")");
            $arr[$key]['withdrawal_amount'] = UserWithdrawalModel::getWithdrawUserAmount($where1,"a.uid in(".$agent_sql.")")[2];
            $arr[$key]['zd_price'] = 0;
            $arr[$key]['zd_pnumber'] = 0;
            if (!empty($rec_user)) {
                $arr[$key]['max_level'] = $value['rec_level'] - $rec_user['rec_level'];
            }else{
                $arr[$key]['max_level'] = $value['rec_level'];
            }
            $arr[$key]['rec_code'] = $value['rec_code'];
            $arr[$key]['username'] = !is_null($value['mail']) ? $value['mail'] : (!is_null($value['phone']) ? $value['phone'] : $value['username']);
            $arr[$key]['balance'] = $value['balance'];
        }

        // 实例化
        $spreadsheet = new Spreadsheet();
        // 获取活动单元格
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getCell('A1')->setValue(lang('用户名'));
        $sheet->getCell('B1')->setValue(lang('下层总数'));
        $sheet->getCell('C1')->setValue(lang('新注册人数'));
        $sheet->getCell('D1')->setValue(lang('充值人数'));
        $sheet->getCell('E1')->setValue(lang('充值金额'));
        $sheet->getCell('F1')->setValue(lang('首充人数'));
        $sheet->getCell('G1')->setValue(lang('复充占比'));
        $sheet->getCell('H1')->setValue(lang('复充人数'));
        $sheet->getCell('I1')->setValue(lang('复充占比'));
        $sheet->getCell('J1')->setValue(lang('提现金额'));
        $sheet->getCell('K1')->setValue(lang('提现人数'));
        $sheet->getCell('L1')->setValue(lang('投注金额'));
        $sheet->getCell('M1')->setValue(lang('投注人数'));
        $sheet->getCell('N1')->setValue(lang('总层级'));
        $sheet->getCell('O1')->setValue(lang('邀请码'));
        $sheet->getCell('P1')->setValue(lang('用户余额'));
        // 从 A2 开始填充数据
        foreach ($arr as $k => $v) {
            $n = $k + 2;
            $sheet->getCell('A'.$n)->setValue($v['username']);
            $sheet->getCell('B'.$n)->setValue($v['count_pnumber']);
            $sheet->getCell('C'.$n)->setValue($v['new_reg']);
            $sheet->getCell('D'.$n)->setValue($v['recharge_pnumber']);
            $sheet->getCell('E'.$n)->setValue($v['recharge_amount']);
            $sheet->getCell('F'.$n)->setValue($v['first_recharge_pnumber']);
            $sheet->getCell('G'.$n)->setValue($v['first_recharge_zb']);
            $sheet->getCell('H'.$n)->setValue($v['two_recharge_pnumber']);
            $sheet->getCell('I'.$n)->setValue($v['two_recharge_zb']);
            $sheet->getCell('J'.$n)->setValue($v['withdrawal_amount']);
            $sheet->getCell('K'.$n)->setValue($v['withdrawal_pnumber']);
            $sheet->getCell('L'.$n)->setValue($v['zd_price']);
            $sheet->getCell('M'.$n)->setValue($v['zd_pnumber']);
            $sheet->getCell('N'.$n)->setValue($v['max_level']);
            $sheet->getCell('O'.$n)->setValue($v['rec_code']);
            $sheet->getCell('P'.$n)->setValue($v['balance']);
        }
        $file_name = lang('代理列表').'.xlsx';
        // 实例化导出类
        header('Content-Type:application/vnd.ms-excel');
        header('Content-Disposition:attachment;filename=' . $file_name);
        header('Cache-Control:max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');exit;
    }
}