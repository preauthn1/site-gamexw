<?php

namespace app\common\model;

use app\api\redis\ApiRedis;
use think\Model;

class DefaultHeadModel extends Model
{
    protected $name = 'default_head';

    protected $schema = [
        'id' => 'int',
        'name' => 'varchar',
        'url' => 'varchar',
        'sort' => 'tinyint',
        'status' => 'tinyint',
        'add_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'name' => lang('名称'),
            'url' => lang('头像地址'),
            'sort' => lang('排序'),
            'status' => lang('状态'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }

    /**
     * 获取一条头像链接
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getHeadOne()
    {
        $map = [
            'status' => 1
        ];
        $defaultHead = 'https://d11fblhq41757n.cloudfront.net/storage/head_img/T/1.png';
        $apiRedis = new ApiRedis();
        if( $apiRedis->has('default_head_list') ){
            $list = json_decode($apiRedis->get('default_head_list'), true);
        }else{
            $list = DefaultHeadModel::where($map)->field('id,name,url')->select()->toArray();
            $apiRedis->set('default_head_list', json_encode($list));
        }
        if( $list ){
            $index = rand(0, count($list) - 1);
            if(isset($list[$index])){
                $defaultHead = $list[$index]['url'];
            }
        }
        return $defaultHead;
    }

}