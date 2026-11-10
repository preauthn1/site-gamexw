<?php


namespace app\common\FastGame\Base;


use think\Model;

/**
 * Class CModel 支持切分表加后缀的Model
 * 特别说明，如果要使用find方法时，必须显式在继承类中指定$pk 主键值
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2023-01-04 21:45
 */
class CModel extends Model
{

    /**
     * CModel constructor.
     * @param null $month 传入月份数据，参数规则同 Utils::getCutTableEx 方法 的 $time参数
     * @param array $data 与model的 data参数一致，比如 'field','op','condition'  或者 [['field','op','condition']]
     * @inheritDoc
     * 使用方法：
     * new ThirdStatsBackModel(202211);
     */
    public function __construct($month=null,array $data = []){
        if (!is_array($month)) {
            //根据参数处理表后缀
            $suf = Utils::getCutTableEx($this->name, $month);
            $this->name .= $suf ? $suf : '';
        }else{
            $data=$month;
        }
        parent::__construct($data);


    }


    /**
     * @param $method
     * @param $args  参数如果为多参数，则第一个为月份，如果是数组参数，则元素0为月份
     * @return mixed
     * @author twt326
     * @date 2023-01-04 21:43
     * @inheritDoc
     * 使用方法：
     * ThirdStatsBackModel::where(202211,[['sb_dt','=','20221103']])->limit(0,1)->select()->toArray();
     * ThirdStatsBackModel::where(202212,'sb_dt','=','20221203')->limit(0,1)->select()->toArray()
     * ThirdStatsBackModel::limit(202211,0,1)->select()->toArray();
     */
    public static function __callStatic($method, $args)
    {
        if (isset(static::$macro[static::class][$method])) {
            return call_user_func_array(static::$macro[static::class][$method]->bindTo(null, static::class), $args);
        }
        $month=null;
        if (!is_array($args[0])){
            if (is_numeric($args[0]) && strlen($args[0])==6){
                $month=$args[0];
                $args=array_slice($args,1);
            }
        }

        $model = new static($month);

        return call_user_func_array([$model->db(), $method], $args);
    }

}
