<?php

namespace app\common\service;

class UserCjBalanceMxService{

    //类型
    const TYPE = [
        [
            'title' => '充值类型',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['线上入款', '线下入款']
        ],
        [
            'title' => '提现类型',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['余额提现', '提现拒绝']
        ],
        [
            'title' => 'VIP奖金',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['VIP等级彩金', '等级奖励', '返水']
        ],
        [
            'title' => '促销活动',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['注册首存奖励', '再存优惠奖励', '优惠固定奖励', '体验金', '游戏救援金', '推荐奖励', '领取码奖励', '抽奖金额', '中奖金额', '转盘奖励', '签到奖励', '任务奖励', '五福奖励']
        ],
        [
            'title' => '游戏相关',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => ['下注', '结算', '充值到三方平台', ']提现']
        ],
        [
            'title' => '团队收益',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => ['代理返佣', '推广奖励']
        ],
        [
            'title' => '其他变动',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => [],
            'other' => true,
        ],
    ];

    //类型对应备注中模糊查询的值
    const CHILD_TYPE_REMARKS = [
        [
            'title' => '游戏下注',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => ['下注']
        ],
        [
            'title' => '游戏结算',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => ['结算']
        ],
        [
            'title' => '游戏充值',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => ['充值到三方平台']
        ],
        [
            'title' => '游戏提现',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => [']提现']
        ],
        [
            'title' => '集卡活动',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['五福奖励']
        ],
        [
            'title' => '注册首存奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['注册首存奖励']
        ],
        [
            'title' => '再存优惠奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['再存优惠奖励']
        ],
        [
            'title' => '优惠固定奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['优惠固定奖励']
        ],
        [
            'title' => '体验金奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['体验金']
        ],
        [
            'title' => '游戏救援金',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['游戏救援金']
        ],
        [
            'title' => '推荐奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['推荐奖励']
        ],
        [
            'title' => '代理返佣',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['代理返佣']
        ],
        [
            'title' => '推广返佣',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['推广奖励']
        ],
        [
            'title' => '游戏反水',
            'is_equal' => false, //是否相等，用于判断查询和筛选备注
            'remarks' => ['返水']
        ],
        [
            'title' => '等级奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['VIP等级彩金']
        ],
        [
            'title' => '周月礼金',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['等级奖励']
        ],
        [
            'title' => '领取码奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['领取码奖励']
        ],
        [
            'title' => '轮盘抽奖',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['抽奖金额']
        ],
        [
            'title' => '轮盘中奖',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['中奖金额']
        ],
        [
            'title' => 'PDD转盘奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['转盘奖励']
        ],
        [
            'title' => '签到奖励',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['签到奖励']
        ],
        [
            'title' => '任务奖金',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['任务奖励']
        ],
        [
            'title' => '任务奖金',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['任务奖励']
        ],
        [
            'title' => '定期购买',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['定期基金购买']
        ],
        [
            'title' => '活期购买',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['活期基金购买']
        ],
        [
            'title' => '活期转入',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['活期基金转入']
        ],
        [
            'title' => '活期转出',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['活期基金转出']
        ],
        [
            'title' => '定期转入',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['定期基金转入']
        ],
        [
            'title' => '定期转出',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['定期基金转出']
        ],
        [
            'title' => '收益明细',
            'is_equal' => true, //是否相等，用于判断查询和筛选备注
            'remarks' => ['定期本金+收益返还']
        ],
    ];

    //根据备注获得子分类
    public static function getChildTypeByRemarks($remarks){
        foreach (self::CHILD_TYPE_REMARKS as $v){
            if( $v['is_equal'] ){
                //相等查询
                foreach ($v['remarks'] as $_v){
                    if( $_v == $remarks ){
                        return lang($v['title']);
                    }
                }
            }else{
                //模糊查询
                foreach ($v['remarks'] as $_v){
                    if( mb_strpos($remarks, $_v) !== false ){
                        return lang($v['title']);
                    }
                }
            }
        }
        return lang('其他变动');
    }

    //根据备注获得分类
    public static function getTypeByRemarks($remarks){
        foreach (self::TYPE as $v){
            if( $v['is_equal'] ){
                foreach ($v['remarks'] as $_v){
                    //相等查询
                    if( $_v == $remarks ){
                        return lang($v['title']);
                    }
                }
            }else{
                //模糊查询
                foreach ($v['remarks'] as $_v){
                    //相等查询
                    if( mb_strpos($remarks, $_v) !== false ){
                        return lang($v['title']);
                    }
                }
            }
        }
        return lang('其他变动');
    }

    //根据key获取类型条件
    public static function getTypeConditionByKey($key = ''){
        if( $key === 'all' || !self::TYPE[$key] ){
            return '';
        }
        //判断是否是其他查询
        $condition = '';
        if( isset(self::TYPE[$key]['other']) && self::TYPE[$key]['other'] ){
            //其他查询
            foreach (self::TYPE as $v){
                if( $v['is_equal'] ){
                    //相等
                    foreach ($v['remarks'] as $_v){
                        $condition .= " msg <> '".$_v."' and";
                    }
                }else {
                    //模糊
                    foreach ($v['remarks'] as $_v){
                        $condition .= " msg NOT LIKE '%".$_v."%' and";
                    }
                }
            }
            //删掉最后的and
            $condition = mb_substr($condition, 0, -4);

        }else{
            //不是其他查询，根据各分类条件查询
            $type = self::TYPE[$key];
            if( $type['is_equal'] ){
                //相等
                foreach ($type['remarks'] as $v){
                    $condition .= " msg = '".$v."' or";
                }
            }else {
                //模糊
                foreach ($type['remarks'] as $v){
                    $condition .= " msg LIKE '%".$v."%' or";
                }
            }
            //删掉最后的or
            $condition = mb_substr($condition, 0, -3);
        }
        return $condition;
    }

    //获取类型列表
    public static function getTypeList(){
        $arr = [];
        foreach (self::TYPE as $k => $v){
            $arr[$k] = lang($v['title']);
        }
        return $arr;
    }


}