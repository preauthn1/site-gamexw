<?php

namespace app\manage\controller;

use app\common\model\TagModel;
use app\common\model\UserModel;
use app\common\model\UserTagModel;
use app\common\service\MemberService;
use app\common\Utils;

class UserTag extends Base {

    protected $middleware = ['ManageCheckLogin',"Language"];
    public function index() {
        $tag_id = $this->request->param('tag_id/s');
        $username = $this->request->param('username/s');
        $where = [];
        if (!empty($tag_id)) {
            $where[] = ["tag_id", "=", $tag_id];
        }
        if (!empty($username)) {
            $where[] = ["uid", "=", MemberService::getUidByKeyword($username)];
        }
        [$data, $total] = $this->getDataList(new UserTagModel(), $where);
        $tag = TagModel::column('name', 'id');
        $users = [];
        foreach ($data AS &$row) {
            if (!isset($users[$row['uid']])) {
                $users[$row['uid']] = UserModel::where('id', $row['uid'])->field('id,username,mail,phone')->find();
            }
            $row['username'] = $users[$row['uid']]['username'] ?? '';
            $row['mail'] = $users[$row['uid']]['mail'] ?? '';
            $row['phone'] = $users[$row['uid']]['phone'] ?? '';
            $row['tag'] = $tag[$row['tag_id']] ?? $row['tag_id'];
            $row = Utils::hideUserInfo($row);
        }
        return $this->success([
            'total' => $total,
            'data' => $data,
            'tags' => Utils::getOptions('tag')
        ]);
    }


    //添加
    public function add() {
        if ($this->request->has('add')) {
            $info = $this->request->param('info/a');
            if (!isset($info['tag_id']) || empty($info['tag_id'])) {
                return $this->error('请选择标签');
            }
            $tag = TagModel::where('id', $info['tag_id'])->find();
            if (empty($tag)) {
                return $this->error('请选择正确的标签');
            }
            $model = new UserTagModel();
            if (!is_numeric($info['uid']) && (!isset($info['id']) || !$info['id'])) {
                $uids = explode(',', $info['uid']);
                $dataSet = [];
                foreach ($uids AS $uid) {
                    $dataSet[] = [
                        'uid' => intval($uid),
                        'tag_id' => $info['tag_id']
                    ];
                    MemberService::clearCacheTagByUid($uid);
                    Utils::addOptLog("用户ID:{$uid},标签:{$tag['name']}", '', 'user_tag', '增加用户标签');
                }
                if (empty($dataSet)) {
                    return $this->error();
                }
                return $this->save($model, $dataSet, true);
            } else {
                $info['uid'] = intval($info['uid']);
                MemberService::clearCacheTagByUid($info['uid']);
                Utils::addOptLog("用户ID:{$info['uid']},标签:{$tag['name']}", '', 'user_tag', '修改用户标签');
                return $this->save($model, $info);
            }
        }
        if ($this->request->has('find')) {
            $id = $this->request->param('id');
            if (empty($id)) {
                return $this->error('数据不存在');
            }
            $model = new UserTagModel();
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
        $model = new UserTagModel();
        $res = $model->where("id", "in", $id)->select();
        $tag = TagModel::column('name', 'id');
        $logs = [];
        foreach ($res AS $row) {
            $logs[] = ["uid" => $row['uid'], 'tag' => $tag[$row['tag_id']] ?? ''];
            MemberService::clearCacheTagByUid($row['uid']);
        }
        Utils::addOptLog($logs, '', 'user_tag', '删除用户标签');

        $model = new UserTagModel();
        $res = $model->where("id", "in", $id)->delete();
        return $res ? $this->success() : $this->error();
    }
}