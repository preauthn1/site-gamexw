<?php

declare (strict_types=1);

namespace app\promote\controller;

use app\BaseController;
use app\admin\model\Admin;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\common\lib\Respons;
use thans\jwt\facade\JWTAuth;
use think\Db;
use think\facade\Log;
use think\Model;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

class Base extends BaseController {

    public $user = null;
    public $user_agent = null;
    public $user_id = null;

    // 初始化
    protected function initialize() {
        if ($this->request->middleware('auth_middleware')) {
            $payload = JWTAuth::getPayload();
            $this->user_id = $payload['client_user_id'];
            $this->user = UserModel::where('id', $this->user_id)->find();
            $this->user_agent = UserAgentModel::where('uid', $this->user_id)->find();
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
    protected function save($model, $info, $isAll = false): \think\response\Json {
        if (isset($info['id']) && $info['id'] > 0) {
            $data = $model->where('id', $info['id'])->find();
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

}
