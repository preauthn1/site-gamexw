<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver243 extends VersionBase {

    public function run() {
        //姓
        if ( !$this->existTableField("dq_user_agent", "last_name") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `last_name` varchar(20) NULL COMMENT '姓';";
            $this->execute($sql);
        }
        //名
        if ( !$this->existTableField("dq_user_agent", "name") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `name` varchar(20) NULL COMMENT '名';";
            $this->execute($sql);
        }
        //生日
        if ( !$this->existTableField("dq_user_agent", "birthday") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `birthday` varchar(100) NULL COMMENT '生日';";
            $this->execute($sql);
        }
        //国家
        if ( !$this->existTableField("dq_user_agent", "country") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `country` varchar(20) NULL COMMENT '国家';";
            $this->execute($sql);
        }
        //地址
        if ( !$this->existTableField("dq_user_agent", "address") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `address` varchar(255) NULL COMMENT '地址';";
            $this->execute($sql);
        }
        //省份
        if ( !$this->existTableField("dq_user_agent", "province") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `province` varchar(100) NULL COMMENT '省份';";
            $this->execute($sql);
        }
        //市
        if ( !$this->existTableField("dq_user_agent", "city") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `city` varchar(100) NULL COMMENT '市区';";
            $this->execute($sql);
        }
        //区县
        if ( !$this->existTableField("dq_user_agent", "district") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `district` varchar(100) NULL COMMENT '区县';";
            $this->execute($sql);
        }
        //邮政编码
        if ( !$this->existTableField("dq_user_agent", "zipcode") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `zipcode` int(10) NULL COMMENT '邮政编码';";
            $this->execute($sql);
        }
        //号码1
        if ( !$this->existTableField("dq_user_agent", "phone1") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `phone1` varchar(30) NULL COMMENT '号码1';";
            $this->execute($sql);
        }
        //号码2
        if ( !$this->existTableField("dq_user_agent", "phone2") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `phone2` varchar(30) NULL COMMENT '号码2';";
            $this->execute($sql);
        }
        //公司名称
        if ( !$this->existTableField("dq_user_agent", "company_name") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `company_name` int(100) NULL COMMENT '公司名称';";
            $this->execute($sql);
        }
        //公司注册号码
        if ( !$this->existTableField("dq_user_agent", "company_reg_no") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `company_reg_no` int(100) NULL COMMENT '公司注册号码';";
            $this->execute($sql);
        }
        //skype账号
        if ( !$this->existTableField("dq_user_agent", "skype") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `skype` int(100) NULL COMMENT 'skype账号';";
            $this->execute($sql);
        }
        //红利代码
        if ( !$this->existTableField("dq_user_agent", "bonus_code") ) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `bonus_code` int(100) NULL COMMENT '红利代码';";
            $this->execute($sql);
        }
    }

}
