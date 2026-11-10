<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\UserFreebetsModel;
use think\facade\Request;

class FreeGameFactory{

    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }



    //处理form表单数据
    public function handleForm($formdata,$gid = 0){
        $data = ['title' => '', 'columns' => [], 'dialogModel' => [], 'count_num' => []];
        $data['dialogModel']['gid'] = '';
        if( $gid ){
            //增加默认前端的gid数据
            $data['columns']['gid'] = [
                'type' => 'slot',
                'label' => '游戏ID',
                'placeholder' => '游戏ID',
                'tip' => '游戏ID,只能选择一个',
                'value' => '',
                'prop' => 'gid',
                'name' => 'gid',
            ];
            $data['dialogModel']['gid'] = $gid;
        }
        foreach ($formdata as $k => $v){
            if( $k == 'apicfg' ){
                //配置跳出
                $data['count_num'] = $v ?: [];
            }else if( $k == 'forminfo' ){
                $data['title'] = $v;
            }else{
                //判断类型是什么
                if( in_array($k, ['freebetStarttime', 'freebetEndtime']) ){
                    //时间
                    $data['columns'][$k] = [
                        'type' => 'time',
                        'label' => $v['title'],
                        'placeholder' => $v['title'],
                        'tip' => $v['tip'],
                        'value' => $v['value'] ?? '',
                        'prop' => $k,
                    ];
                }else{
                    //其他暂时为输入框，后面有需要再扩展
                    $data['columns'][$k] = [
                        'type' => 'input',
                        'label' => $v['title'],
                        'placeholder' => $v['title'],
                        'tip' => $v['tip'],
                        'value' => $v['value'] ?? '',
                        'prop' => $k,
                    ];
                }
                //增加dialogModel值
                $data['dialogModel'][$k] = $v['value'] ?? '';
            }
        }


        //增加默认前端的gid数据
        $data['columns']['uid'] = [
            'type' => 'slot',
            'label' => '赠送用户id',
            'placeholder' => '赠送用户id',
            'value' => '',
            'prop' => 'uid',
            'name' => 'uid',
            'show' => false,
        ];
        $data['dialogModel']['uid'] = '';
        return $data;
    }

    /**
     * 导出数据
     * @param null $where
     * @return \think\response\Json
     */
    public function exportList($where = null)
    {

        $params = Request::param();
        $params['where'] = $where;
        $params['positionField'] = 'f.ufg_id';
        $params['positionOrder'] = 'asc';
        $params['muid'] = request()->token->uid;
        $statusArr = ['失败', '成功'];
        $rlt = ReportExport::export(new rptParams('免费游戏赠送记录',
            [
                'uid:UID',
                'username:用户名',
                'mail:邮箱',
                'phone:手机号',
                'rec_code:邀请码',
                'tc_name:平台名称',
                'gi_name:游戏名称',
                'ufg_pricecount:单线赠送金额',
                new rptCol('ufg_state','赠送状态',rptCol::transType_kvtrans,$statusArr),
                new rptCol('ufg_endfreetime', '有效期', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('ufg_intime', '赠送时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s'])
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['username', 'tc_name', 'g_name', 'status'],//导出表名称使用的字段信息
            $params['positionField'], $params['positionOrder']
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $userFreeBetsModel = new UserFreebetsModel();
        return $userFreeBetsModel->alias('f')
            ->join('dq_user u',"u.id=ufg_uid")
            ->join('dq_game_infos g','ufg_gid=gi_gid','left')
            ->join('dq_third_configs t','ufg_tcid=tc_id')
            //->join('dq_game_bets','ufg_usedbygbid=gb_id','left')
            ->where($param['where'])
            ->field('f.*,u.username,u.id as uid,u.mail,u.phone,u.rec_code,g.gi_name,t.tc_name');
    }
}
