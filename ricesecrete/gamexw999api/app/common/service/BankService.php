<?php

namespace app\common\service;

class BankService{

    //支付类型排序
    public static function payMethodTypeSort($payment_method_type){
        //
        $pay_type_first = env('pay_type_first',0); // 充提方式排序
        $newArr = [];
        foreach ($payment_method_type as $k=>$v){
            if( $k == $pay_type_first ){
                $newArr[] = lang($v);
                unset($payment_method_type[$k]);
            }
        }
        foreach ($payment_method_type as $v ){
            $newArr[] = lang($v);
        }
        return $newArr;
    }

    //支付类型排序(名称类型)
    public static function payMethodTypeSortArr($payment_method_type){
        //
        $pay_type_first = env('pay_type_first',0); // 充提方式排序
        $newArr = [];
        foreach ($payment_method_type as $k=>$v){
            if( $k == $pay_type_first ){
                $newArr[] = [
                    'name'=>lang($v),
                    'type'=>$k
                ];
                unset($payment_method_type[$k]);
            }
        }
        foreach ($payment_method_type as $k=>$v ){
            $newArr[] = [
                'name'=>lang($v),
                'type'=>$k
            ];
        }
        return $newArr;
    }
}