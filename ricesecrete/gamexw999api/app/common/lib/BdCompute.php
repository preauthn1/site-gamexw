<?php
namespace app\common\lib;
use think\facade\Request;
use app\common\model\AdminLogModel;
class BdCompute
{	
	
    /**
	 * 反波胆赔率计算
	 * @param bd[][yshl_pl] 正赔率
	 * @param bd[][zd] 主队分数
	 * @param bd[][kd] 客队分数
	 * @param bd[][fbd_pl] 反波胆赔率-默认没有
	 * @return Array
	*/
    public static function fbd_fp($bd){
        $arr=[];
        //分类统计输赢平三项
        foreach ($bd as $key => $value) {
                if ($value['zd']>$value['kd']) {
                        //主队胜客队
                        $arr['win'][]=$value;
                }
                if ($value['zd']==$value['kd']) {
                        //平
                        $arr['flat'][]=$value;
                }
                if ($value['zd']<$value['kd']) {
                        //主队负客队
                        $arr['lose'][]=$value;
                }
        }
        //高赔率转换低赔率反波胆
        //低赔率转换反波胆高赔率
        $max_key=count($arr['win']);
        foreach ($arr['win'] as $key => $value) {
                $wkey=($max_key-($key+1));
                $bl=$arr['win'][$key];
                $arr['win'][$key]['yshl_pl']=$arr['win'][$wkey]['yshl_pl'];
                $arr['win'][$wkey]['yshl_pl']=$bl['yshl_pl'];
                $arr['win'][$key]['fbd_pl']=$arr['win'][$key]['yshl_pl'];
                $arr['win'][$wkey]['fbd_pl']=$arr['win'][$wkey]['yshl_pl'];
                if ($key>=($max_key/2)) {
                        break;
                }
        }
        $max_key=count($arr['flat']);
        foreach ($arr['flat'] as $key => $value) {
                $wkey=($max_key-($key+1));
                $bl=$arr['flat'][$key];
                $arr['flat'][$key]['yshl_pl']=$arr['flat'][$wkey]['yshl_pl'];
                $arr['flat'][$wkey]['yshl_pl']=$bl['yshl_pl'];
                $arr['flat'][$key]['fbd_pl']=$arr['flat'][$key]['yshl_pl'];
                $arr['flat'][$wkey]['fbd_pl']=$arr['flat'][$wkey]['yshl_pl'];
                if ($key>=($max_key/2)) {
                        break;
                }
        }
        $max_key=count($arr['lose']);
        foreach ($arr['lose'] as $key => $value) {
                $wkey=($max_key-($key+1));
                $bl=$arr['lose'][$key];
                $arr['lose'][$key]['yshl_pl']=$arr['lose'][$wkey]['yshl_pl'];
                $arr['lose'][$wkey]['yshl_pl']=$bl['yshl_pl'];
                $arr['lose'][$key]['fbd_pl']=$arr['lose'][$key]['yshl_pl'];
                $arr['lose'][$wkey]['fbd_pl']=$arr['lose'][$wkey]['yshl_pl'];
                if ($key>=($max_key/2)) {
                        break;
                }
        }
        // 返回数据
        foreach ($bd as $key => $value) {
                foreach ($arr['lose'] as $key1 => $value1) {
                        if ($value['zd']==$value1['zd'] && $value['kd']==$value1['kd']) {
                                $bd[$key]['fbd_pl']=$value1['fbd_pl'];
                                break;
                        }
                }
                foreach ($arr['win'] as $key1 => $value1) {
                        if ($value['zd']==$value1['zd'] && $value['kd']==$value1['kd']) {
                                $bd[$key]['fbd_pl']=$value1['fbd_pl'];
                                break;
                        }
                }
                foreach ($arr['flat'] as $key1 => $value1) {
                        if ($value['zd']==$value1['zd'] && $value['kd']==$value1['kd']) {
                                $bd[$key]['fbd_pl']=$value1['fbd_pl'];
                                break;
                        }
                }
        }

        // 反波胆赔率1.015-1.03
        //根据字段进行降序排列
        $new_bd=$bd;
        $last_names = array_column($new_bd,'fbd_pl');
        array_multisort($last_names,SORT_DESC,$new_bd);
        $max_pl=$new_bd[0]['fbd_pl'];//最高赔率
        foreach ($new_bd as $key => $value) {
                //占比多少
                $new_bd[$key]['fbd_pl']=round(101.5+round(($value['fbd_pl']/$max_pl)*100,2)*0.015,2);
        }
        foreach ($bd as $key => $value) {
                foreach ($new_bd as $key1 => $value1) {
                        if ($value['zd']==$value1['zd'] && $value['kd']==$value1['kd']) {
                                $bd[$key]['fbd_pl']=$value1['fbd_pl'];
                                break;
                        }
                }
        }
        return $bd;
    }

}
