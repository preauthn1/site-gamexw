<?php

namespace app\common\service;

class AgentSettingService{

    //获取设置代理类型条件sql
    public static function getAgentTypeSql($uid){
        //增加代理条件
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($uid);
        //代理不能参与
        $_where = AgentService::getAgentWhere('agent_id', true, '', false, '', $isNeedZeroWhere);
        $_where1 = 'agent_type = 0';
        if( $_where ){
            $whereStr1 = "($_where1 AND ($_where))";
        }else{
            $whereStr1 = $_where1;
        }
        //指定代理才能参与
        $_where = AgentService::getAgentWhere('agent_id', true, '', true, '', $isNeedZeroWhere);
        $_where1 = 'agent_type = 1';
        if( $_where ){
            $whereStr2 = "($_where1 AND ($_where))";
        }else{
            $whereStr2 = $_where1;
        }
        return "($whereStr1 OR $whereStr2)";
    }
}