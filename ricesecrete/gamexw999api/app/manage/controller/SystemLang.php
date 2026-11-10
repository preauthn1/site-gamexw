<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\SystemLangModel;
use app\common\Utils;
use think\facade\Request;

class SystemLang extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'];
        $where = [];
        $systemLangModel = new SystemLangModel();
        if ( isset($param['title']) && $param['title'] !== '' ) {
            $where[] = ["title", "like", "%" . $param['title'] . "%"];
        }
        if( isset($param['key']) && $param['key'] !== '' ){
            $where[] = ["key", "like", "%" . $param['key'] . "%"];
        }
        if( isset($param['text']) && $param['text'] !== '' ){
            $where[] = ["text", "like", "%" . $param['text'] . "%"];
        }
        if( isset($param['type']) && $param['type'] !== '' ){
            $where[] = ["type", "=", $param['type']];
        }
        if( isset($param['lang']) && $param['lang'] !== '' ){
            $where[] = ["lang", "=", $param['lang']];
        }
        $condition1 = '1=1';
        if( isset($param['agent_id']) && $param['agent_id'] !== '' ){
            $condition1 .= ' and find_in_set('.$param['agent_id'].', `agent_ids`)';
        }
        $result = [];
        $result['data'] = $systemLangModel
            ->where($where)->where($condition1)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        $result['code'] = 1;
        $result['langs'] = Utils::getOptions('lang', ['lang', 'lang'], [['status', '=', 1]]);
        $func_switch = Utils::getOptionsByConfig(config('custom.func_switch'));
        foreach ($func_switch as &$item) {
            $item['key'] = $item['value'];
            $item['value'] = 1;
        }
        $result['default_func_switch'] = $func_switch;
        return Respons::run($result);
    }

    //格式化数据
    private function formatData($info){
        switch ($info['type']){
            case SystemLangModel::TYPE_IMAGE:
                //图片
                $webField = 'image';
                break;
            case SystemLangModel::TYPE_TEXT:
                //文本
                $webField = 'text';
                break;
            case SystemLangModel::TYPE_CONTENT:
                //富文本
                $webField = 'content';
                break;
            default:
                $webField = 'text';
        }
        $info['text'] = $info[$webField];
        unset($info['image'],$info['content']);
        return $info;
    }

    //添加
    public function add()
    {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $this->formatData($param['info']);
            unset($info['id']);
            $systemLangModel = new SystemLangModel();
            //判断类目是否添加过
            $condition = [];
            $condition[] = ['key', '=', $info['key']];
            $condition[] = ['lang', '=', $info['lang']];
            if( $systemLangModel->where($condition)->value('id') ){
//                return Respons::run(0, [], '类目重复');
            }
            $info['agent_ids'] = $info['agent_ids']?:0;
            $res = $systemLangModel->insertGetId($info);
            return Respons::run($res ? 1 : 0);
        }
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $this->formatData($param['info']);
            $systemLangModel = new SystemLangModel();
            $detail = $systemLangModel->where('id', '=', $info['id'])->find();
            //判断类目是否添加过
            $condition = [];
            $condition[] = ['key', '=', $info['key']];
            $condition[] = ['lang', '=', $info['lang']];
            $count = $systemLangModel->where($condition)->count();
            if( $count > 0 && ($detail['key'] != $info['key'] || $detail['lang'] != $info['lang']) ){
//                return Respons::run(0, [], '类目重复');
            }
            $info['agent_ids'] = $info['agent_ids']?:0;
            $res = $detail->save($info);
            return Respons::run($res ? 1 : 0);
        }
        if (isset($param['find'])) {
            $systemLangModel = new SystemLangModel();
            $res = $systemLangModel->where("id", $param['id'])->find();
            $res = $this->getDataByType($res);
            return Respons::run(1, $res);
        }
    }

    //返回前端需要数据
    private function getDataByType($detail){
        $value = $detail['text'];
        $detail['image'] = '';
        $detail['text'] = '';
        $detail['content'] = '';
        switch ($detail['type']){
            case SystemLangModel::TYPE_IMAGE:
                //图片
                $webField = 'image';
                break;
            case SystemLangModel::TYPE_TEXT:
                //文本
                $webField = 'text';
                break;
            case SystemLangModel::TYPE_CONTENT:
                //富文本
                $webField = 'content';
                break;
            default:
                $webField = 'text';
        }
        $detail[$webField] = $value;
        return $detail;
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $systemLangModel = new SystemLangModel();
            $res = $systemLangModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0);
        }
    }


    //批量添加默认参数
    public function batch() {
        $list = Request::param('list');
        if (empty($list)) {
            return $this->error('参数有误');
        }
        $func_switch = config('custom.func_switch');
        $langs = Utils::getOptions('lang', ['lang', 'lang'], [['status', '=', 1]]);
        foreach ($list as $row) {
            if (!isset($func_switch[$row['key']])) {
                continue;
            }
            foreach ($langs as $lang) {
                $condition = [];
                $condition[] = ['key', '=', $row['key']];
                $condition[] = ['lang', '=', $lang['label']];
                $info = SystemLangModel::where($condition)->find();
                if (empty($info)) {
                    SystemLangModel::create([
                        'lang' => $lang['label'],
                        'title' => $func_switch[$row['key']],
                        'type' => 1,
                        'key' => $row['key'],
                        'text' => intval($row['value'])
                    ]);
                } else if($info && $info->text != intval($row['value'])) {
                    $info->text = intval($row['value']);
                    $info->save();
                }
            }

        }
        return $this->success([ 'data' => $list ]);
    }
}