<?php

namespace app\api\factory;

use app\common\lib\Util;
use app\common\model\GonggaoModel;
use app\common\model\GonggaoTypeModel;
use app\common\service\AgentService;
use think\facade\Request;

class NoticeFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //公告列表
    public function list_run()
    {
        $show_loca = $this->param['show_loca'] ?? "all";
        $type = isset($this->param['type']) ? intval($this->param['type']) : 0;
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $typeId = $this->param['type_id'] ?? 0;
        $lang = strtoupper(\think\facade\Lang::getLangSet());

        $where = [];
        if (!($show_loca == 1 || $show_loca == 2 || $show_loca == 'all')) {
            ajax_return("类型错误", 0);
        }
        if ($show_loca == 1 || $show_loca == 2) {
            $where[] = ['show_loca', '=', $show_loca];
        }
//        if($type == 0 || $type == 1 || $type == 2){
//            //因历史遗留问题 可能会出现 弹窗传的是2 这里进行转换
//            if($type == 2){
////                $type = 0;#后台设置弹框存储到数据库的是2，去掉转换
//            }
//            $where[] = ['show_type', '=', $type];
//        }
        if (!empty($type)) {
            $where[] = ['show_type', '=', $type];
        }
        if (!empty($lang)) {
            $where[] = ["lang", "=", $lang];
        }
        if ($typeId) {
            $where[] = ['type_id', '=', $typeId];
        }
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($this->uid);
        $where_str = AgentService::getAgentWhere('agent_id', true, '', true, '', $isNeedZeroWhere);

        $data = GonggaoModel::where("status", 1)
            ->where($where)
            ->where($where_str)
            ->where(time() . " >= start_time and " . time() . " <= end_time")
            ->field("id,add_time,title,content,type_id,photo,jump_url,show_type,is_show_time")
            ->limit((($pagenow - 1) * $limit), $limit)
            ->order('sort desc')
            ->select();

        foreach ($data as $key => $value) {
            $data[$key]['add_time'] = Util::i18n_datetime2str($value['add_time']);
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //公告详情
    public function detail_run()
    {
        $id = $this->param['id'] ?? 0;
        if (empty($id)) {
            ajax_return("参数错误", 0);
        }

        $data = GonggaoModel::where("id", $id)->find();

        if (empty($data)) {
            ajax_return('数据不存在', 0);
        }

        $data['add_time'] = Util::i18n_datetime2str($data['add_time']);
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //类型
    public function type_run()
    {
        $data = GonggaoTypeModel::where("status", 1)->order("sort desc,id desc")->select();
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }
}