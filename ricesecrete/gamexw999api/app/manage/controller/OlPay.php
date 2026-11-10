<?php
namespace app\admin\controller;
use app\common\model\OlPayModel;
use app\common\lib\Respons;
use app\common\Utils;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
class OlPay
{	
    protected $middleware = ['adminCheckLogin',"Language"];

    //列表
    public function list(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page=$param['page'];
            $limit=$param['limit'];
            $where=[];
            $OlPayModel =new OlPayModel();
            if (isset($param['username']) && $param['username']!='') {
                $where[]=["b.username","like","%".$param['username']."%"];
            }
            if (isset($param['pay_price']) && $param['pay_price']!='') {
                $where[]=["a.pay_price","=",$param['pay_price']];
            }
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
                $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
                $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
            }
            $rsps['data']=$OlPayModel
            	->alias("a")
            	->join("dq_user b","a.uid=b.id")
	            ->where($where)
	            ->order("a.id desc")
	            ->field("a.*,b.username,b.lebal")
                ->paginate($limit,false,['query' => $param]);
            foreach($rsps['data'] as &$value){
                $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return View::fetch('ol_pay/list');
    }

    //导出数据
    public function excel(){
        $param=Request::param();
        $where = [];
        if (isset($param['username']) && $param['username']!='') {
            $where[]=["b.username","like","%".$param['username']."%"];
        }
        if (isset($param['pay_price']) && $param['pay_price']!='') {
                $where[]=["a.pay_price","=",$param['pay_price']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        $OlPayModel=new OlPayModel();
        $res = OlPayModel::alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,b.username,b.lebal")
            ->select();

        
        // 实例化
        $spreadsheet = new Spreadsheet();
        // 获取活动单元格
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getCell('A1')->setValue(lang('申请时间'));
        $sheet->getCell('B1')->setValue(lang('用户账号'));
        $sheet->getCell('C1')->setValue(lang('用户标签'));
        $sheet->getCell('D1')->setValue(lang('支付金额'));
        $sheet->getCell('E1')->setValue(lang('支付状态'));
        $sheet->getCell('F1')->setValue(lang('支付类型'));
        $sheet->getCell('G1')->setValue(lang('外包订单号'));
        if (isset($res[0])) {
            // 从 A2 开始填充数据
            foreach ($res as $k => $v) {
                $n = $k + 2;
                $sheet->getCell('A' . $n)->setValue(date("Y-m-d H:i:s",$v['add_time']));
                $sheet->getCell('B' . $n)->setValue($v['username']);
                $sheet->getCell('C' . $n)->setValue(($v['lebal']==1?lang('一般账号'):lang('测试账号')));
                $sheet->getCell('D' . $n)->setValue($v['pay_price']);
                $sheet->getCell('E' . $n)->setValue($v['pay_status']==0?lang('未支付'):lang('已支付'));
                $sheet->getCell('F' . $n)->setValue($v['pay_type']);
                $sheet->getCell('G' . $n)->setValue($v['othr_number']);
            }
        }
        $file_name = lang('线上入款数据').'.xlsx';
        // 实例化导出类
        header('Content-Type:application/vnd.ms-excel');
        header('Content-Disposition:attachment;filename=' . $file_name);
        header('Cache-Control:max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');exit;
    }
    
}