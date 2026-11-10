<?php

namespace app\manage\controller;

use app\common\model\TagModel;
use app\common\model\UserTagModel;
use app\common\Utils;

class Tag extends Base {
    protected $middleware = ['ManageCheckLogin',"Language"];

    public function index() {
        $name = $this->request->param('name/s');
        $where = [];
        if (!empty($name)) {
            $where[] = ["name", "LIKE", "%{$name}%"];
        }
        $recharge = Utils::getOptions('pay_set', ['id', 'pay_name', 'remarks'], ['pay_status' => 1]);
        [$data, $total] = $this->getDataList(new TagModel(), $where);
        foreach ($data as &$datum) {
            $datum['recharge'] = '';
            if ($datum['is_recharge'] > 0) {
                foreach ($recharge AS $row) {
                    if ($row['value'] == $datum['is_recharge']) {
                        $datum['recharge'] = $row['label'];
                        break;
                    }
                }
            }
        }
        return $this->success([
            'total' => $total,
            'data' => $data,
            'recharge' => $recharge
        ]);
    }


    //添加
    public function add() {
        if ($this->request->has('add')) {
            $info = $this->request->param('info/a');
            $model = new TagModel();
            if (isset($info['id']) && $info['id'] > 0) {
                $old = $model->where('id', $info['id'])->find();
                if ($old) {
                    Utils::addOptLog($old->toArray(), $info, 'tag', '修改标签');
                }
            } else {
                Utils::addOptLog($info, '', 'tag', '增加标签');
            }
            return $this->save($model, $info);
        }
        if ($this->request->has('find')) {
            $id = $this->request->param('id');
            if (empty($id)) {
                return $this->error('数据不存在');
            }
            $model = new TagModel();
            $res = $model->where("id", $id)->find();
            return $this->success(['data' => $res]);
        }
        return $this->success();
    }

    //删除
    public function delete() {
        $id = $this->request->param('id/s');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }
        $model = new TagModel();
        $names = $model->where("id", "in", $id)->column('name');
        Utils::addOptLog($names, '', 'tag', '删除标签');
        $res = $model->where("id", "in", $id)->delete();
        //删除用户对应标签
        UserTagModel::where('tag_id', 'in', $id)->delete();
        return $res ? $this->success() : $this->error();
    }
}