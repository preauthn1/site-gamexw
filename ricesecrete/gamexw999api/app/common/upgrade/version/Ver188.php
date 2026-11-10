<?php

namespace app\common\upgrade\version;

use app\common\model\UserOfflineModel;
use think\facade\Db;

class Ver188 extends VersionBase
{

    public function run()
    {
        //判断用户充值表是否存在索引status和channal索引，不存在添加
        if ( $this->existTableField("dq_user_offline", "status") ) {
            $this->addTableIndexNoExist('dq_user_offline', 'idx_status', 'status');
        }
        if ( $this->existTableField("dq_user_offline", "channel") ) {
            $this->addTableIndexNoExist('dq_user_offline', 'idx_channel', 'channel');
        }
        if ( $this->existTableField("dq_user_offline", "channel") && $this->existTableField("dq_user_offline", "status") ){
            $this->addTableIndexNoExist('dq_user_offline', 'idx_channel_status', 'channel,status');
        }
    }
}