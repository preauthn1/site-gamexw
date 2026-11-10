<?php

declare (strict_types=1);

namespace app\manage\controller;

use app\BaseController;
use app\admin\model\Admin;
use app\common\lib\Respons;
use think\Db;
use think\facade\Log;
use think\facade\Session;
use think\Model;

class Base extends BaseController {

    public $user = null;
    public $user_id = null;

    // 初始化
    protected function initialize() {
        if ($this->request->middleware('auth_middleware')) {
            $this->user_id = Session::get('adminInfo.id');
            $this->user = Session::get('adminInfo');
        }
    }

    /**
     * 获取列表数据
     * @param Model|Db $model
     * @param array $where
     * @param array $whereOr
     * @return array
     */
    protected function getDataList($model, $where = null, $whereOr = null, $orderby = "id DESC") {
        $page = $this->request->param('page/d', 1);
        $size = $this->request->param('limit/d', 10);
        $offset = ($page - 1) * $size;

        $result = $model->where($where)->whereOr($whereOr)->limit($offset, $size)->order($orderby)->select();
        $total = $model->where($where)->whereOr($whereOr)->count();
        
        return [$result, $total];
    }

    /**
     * 保存数据
     * @param $model
     * @param $info
     * @return \think\response\Json
     */
    protected function save($model, $info, $isAll = false, $pk = 'id'): \think\response\Json {
        if (isset($info[$pk]) && $info[$pk] > 0) {
            $data = $model->where($pk, $info[$pk])->find();
            if (empty($data)) {
                return $this->error('数据不存在');
            }
            $res = $model->update($info);
        } else {
            if ($isAll) {
                $res = $model->saveAll($info);
            } else {
                $res = $model->save($info);
            }
        }
        return $res ? $this->success() : $this->error();
    }

    public function success(array $data = [], $msg = '操作成功'): \think\response\Json {
        return $this->result(1, $msg, $data);
    }

    public function error($msg = '操作失败', $data = []): \think\response\Json {
        return $this->result(0, $msg, $data);
    }

    public function result($code, $msg, $data = []): \think\response\Json {
        $data['code'] = $code;
        $data['msg'] = $msg;
        return Respons::run($data);
    }
}
