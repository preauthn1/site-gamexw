<?php
namespace app\common\model;
use think\Model;

class BaseModel extends Model
{
    //模型事件 更新完成后触发  在basemodel中定义该方法 可以在继承模型中触发该事件  用来记录日志
    public static function onAfterUpdate($model){
        //halt($model->toArray()); //拿到更新后的数据
        //halt($model->getpk()()); //获取主键
        //halt($model->getOrigin()); //全部原始数据
        //halt($model->getOrigin('name'));//单个字段

    }

}