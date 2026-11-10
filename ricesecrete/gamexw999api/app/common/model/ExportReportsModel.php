<?php
declare (strict_types = 1);

namespace app\common\model;

use app\common\lib\Util;
use think\Model;
class ExportReportsModel extends Model
{
    protected $name = 'export_reports';
    protected $pk = 'er_id';

    public static function getlist($page=1,$limit=20,$muid=null){
//        $timezone = gettimezone(null);
        $timezone = env('DEFAULT_TIMEZONE', 'Asia/Shanghai');
        $query=self::join("dq_admin","id=er_muid",'left')->
        order('er_id','desc')->field("er_id,er_filename,er_muid,er_intime,er_downloadurl,er_process,er_datacount,er_started,er_ended,er_endtime,er_invalidtime,er_password,username");
        if ($muid !== null){
            $query=$query->where('er_muid',$muid);
        }
        $datas=clone $query;
        $count=$query->count();
        $datas=$datas
            ->filter(function($dt) use($timezone){

                $dt['er_intime']=$dt['er_intime']==0?'':Util::localTimeToTimezone($dt['er_intime'],$timezone);
                $dt['er_endtime']=$dt['er_endtime']==0?'':Util::localTimeToTimezone($dt['er_endtime'],$timezone);
                $dt['er_invalidtime']=$dt['er_invalidtime']==0?'':Util::localTimeToTimezone($dt['er_invalidtime'],$timezone);
                $dt['per']=$dt['er_datacount']==0?'0':(number_format($dt['er_process']/$dt['er_datacount']*100,3,'.',''));
            })->limit(($page-1)*$limit,$limit)->select()->toArray();
        return [
            'data'=>$datas,
            'total'=>$count,
            'limit'=>$limit,
            'page'=>$page
        ];
    }
}
