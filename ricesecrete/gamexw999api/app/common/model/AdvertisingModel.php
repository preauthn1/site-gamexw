<?php

namespace app\common\model;

use app\common\service\AgentService;
use think\Model;

class AdvertisingModel extends Model
{
    protected $name = 'advertising';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'title' => 'varchar',
        'images' => 'varchar',
        'agent_id' => 'varchar',
        'jump_url' => 'varchar',
        'condition' => 'tinyint',
        'btn_text' => 'varchar',
        'status' => 'tinyint',
        'start_time' => 'int',
        'end_time' => 'int',
        'add_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'lang' => lang('语言种类'),
            'title' => lang('标题'),
            'images' => lang('图片'),
            'agent_id' => lang('领取代理id'),
            'jump_url' => lang('关联功能'),
            'condition' => lang('条件'),
            'btn_text' => lang('按钮文字'),
            'status' => lang('状态'),
            'start_time' => lang('开始时间'),
            'end_time' => lang('结束时间'),
            'add_time' => lang('添加时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
            ['condition' => [1 => lang('首充'), 2 => lang('复充')]],
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }

    /**
     * 获取广告信息
     * @param int $first 1 首充  2复充
     * @param int $uid
     * @return AdvertisingModel|array|mixed|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getAdvInfo($first = 1, $uid = 0)
    {
        $map = [
            'condition' => $first
        ];
        $time = time();
        //后台调用的不能加语言，否则一直是中文，语言只能由前端判断
        $data = self::alias('a')->where(AgentService::getAgentWhereUid('agent_id', true, 'a', $uid))->where($map)->where('start_time', '<=', $time)->where('end_time', '>=', $time)->find();
        if (empty($data)) {
            return false;
        }
        return [
            'condition' => $first
        ];
    }
//    //关联活动内容
//    public function news()
//    {
//        return $this->hasMany(NewsModel::class, 'action_id', 'id');
//    }
}