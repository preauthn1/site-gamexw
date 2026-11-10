<?php

namespace app\common\upgrade\version;

use app\common\upgrade\interfaces\Ver;
use app\model\SysSets;
use think\facade\Db;

abstract class VersionBase implements Ver {

    /**
     * 判断表名是否存在
     * @param string $full_table_name 表名称带前缀
     * @param string $config 数据库配置
     * @return bool
     */
    public function existTable($full_table_name, $config = "") {
        return $this->existTableFullName($full_table_name, $config);
    }

    public function existTableFullName($full_table_name, $config = "") {
        $exist = Db::connect($config)->query("show tables like '{$full_table_name}'");
        return empty($exist) ? false : true;
    }

    /**
     * 判断表是否存在字段
     * @param string $full_table_name 表名称带前缀
     * @param string $field 字段名称
     * @param string $config 数据库配置
     * @return bool
     */
    public function existTableField($full_table_name, $field, $config = "") {
        $fields = $this->getFields($full_table_name, $config);
        return in_array($field, $fields);
    }

    /**
     * 获取表的所有字段
     * @param type $full_table_name 表名称带前缀
     * @param type $config 数据库配置
     * @return type
     */
    public function getFields($full_table_name, $config = "") {
        $fields = Db::connect($config)->query("show COLUMNS FROM {$full_table_name}");
        $res = [];
        foreach ($fields as $value) {
            $res[] = $value['Field'];
        }

        return $res;
    }

    /**
     * 判断表是否存在字段
     * @param string $full_table_name 表名称带前缀
     * @param string $index 字段名称
     * @param string $config 数据库配置
     * @return bool
     */
    public function existTableIndex($full_table_name, $index, $config = "") {
        $indexs = $this->getIndexs($full_table_name, $config);
        return in_array($index, $indexs);
    }
    /**
     * 获取表的所有索引
     * @param type $full_table_name 表名称带前缀
     * @param type $config 数据库配置
     * @return type
     */
    public function getIndexs($full_table_name, $config = "") {
        $fields = Db::connect($config)->query("show index FROM {$full_table_name}");
        $res = [];
        foreach ($fields as $value) {
            $res[] = $value['Key_name'];
        }

        return $res;
    }

    /**
     * 执行SQL
     * @param type $sql
     * @param type $config 数据库配置
     */
    public function execute($sql, $config = "") {
        Db::connect($config)->execute($sql);
    }

    /**
     * 执行SQL 要插入的数据是否存在
     * @param type $sql
     * @param type $config 数据库配置
     * @return Array
     */
    public function getTableData($sql, $config = "") {
        $ret = Db::connect($config)->query($sql);
        Db::connect($config)->close();
        return $ret;
    }

    /**
     * @param $code
     * @param $title
     * @param $value
     * @param string $type 'int','uint','number','unumber','string','text','list','bool','upload'
     * @return void
     */
    public function addSysSets($code, $title, $value, string $type = 'string', $label = '基础设置', $info = '', $valueText = '') {
        if (!in_array($type, ['int','uint','number','unumber','string','text','list','bool','upload'])) {
            return;
        }
        if( !$info ){
            $info = $title;
        }
        $set = SysSets::where('set_code', $code)->find();
        if (empty($set)) {
            SysSets::create([
                'set_code' => $code,
                'set_label' => $label,
                'set_title' => $title,
                'set_info' => $info,
                'set_value' => $value,
                'set_valuetext' => $valueText,
                'set_type' => $type
            ]);
            // 重新生成缓存
            SysSets::buildCache();
        }
    }

    //判断数据是否存在
    protected function judgeDataIsExist($table, $field, $value){
        if(Db::name($table)->where($field, $value)->find() ){
            return true;
        }
        return false;
    }

    protected function insertData($table, $data){
        return Db::name($table)->insertGetId($data);
    }

    /**
     * 获取节点ID
     * @param $name
     * @return int|mixed
     */
    public function getNodeIdByName($name) {
        $pid = Db::name('admin_node')->where('node_name', $name)->value('id');
        return $pid ?? 0;
    }
    /**
     * 获取节点ID
     * @param $name
     * @return int|mixed
     */
    public function getNodeIdByNodeUrl($url) {
        $pid = Db::name('admin_node')->where('node_url', $url)->value('id');
        return $pid ?? 0;
    }

    /**
     * 获取节点ID
     * @param $url
     * @param $name
     * @return int|mixed
     */
    public function getNodeIdByNodeUrlName($url,$name) {
        $pid = Db::name('admin_node')->where('node_url', $url)->where('name',$name)->value('id');
        return $pid ?? 0;
    }

    /**
     * 获取节点ID
     * @param $url
     * @param $pid
     * @return int|mixed
     */
    public function getNodeIdByNodeUrlPid($url,$pid) {
        $pid = Db::name('admin_node')->where('node_url', $url)->where('pid',$pid)->value('id');
        return $pid ?? 0;
    }
    //公共添加父节点
    public function addPublicParentNode($nodeName, $nodeUrl, $name, $path = '', $pid = 0, $sort = 0, $isAddChildrenNode = false, $isMenu = 1, $status = 1){
        if(!$path) $path = $nodeUrl;
        $node = Db::name('admin_node')->where('node_url', '=', $nodeUrl)->where('pid', '=', $pid)->find();
        if( !$node ){
            $node = [
                'node_name' => $nodeName,
                'node_url' => $nodeUrl,
                'path' => $path,
                'name' => $name,
                'pid' => $pid,
                'is_menu' => $isMenu,
                'sort' => $sort,
                'status' => $status,
                'create_time' => time(),
                'update_time' => time()
            ];
            $nodeId = Db::name('admin_node')->insertGetId($node);
            //是否添加子节点（添加，编辑，删除）
            if( $isAddChildrenNode ){
                $this->addPublicChildrenNode($nodeId);
            }
        }else{
            $nodeId = $node['id'];
        }
        return $nodeId;
    }

    //批量添加公共子节点（添加，修改，删除节点）
    protected function addPublicChildrenNode($pid){
        $saveAll = [];
        //添加
        $saveAll[] = [
            'node_name' => '添加',
            'node_url' => 'add',
            'path' => 'add',
            'name' => 'add',
            'pid' => $pid,
            'sort' => 0,
            'is_menu' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        //编辑
        $saveAll[] = [
            'node_name' => '编辑',
            'node_url' => 'edit',
            'path' => 'edit',
            'name' => 'edit',
            'pid' => $pid,
            'sort' => 0,
            'is_menu' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        //删除
        $saveAll[] = [
            'node_name' => '删除',
            'node_url' => 'delete',
            'path' => 'delete',
            'name' => 'delete',
            'pid' => $pid,
            'sort' => 0,
            'is_menu' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        //批量添加添加，编辑，删除
        Db::name('admin_node')->insertAll($saveAll);
    }

    //批量添加公共子节点（添加，修改，删除节点）(兼容is_menu)
    protected function addPublicChildrenMenuNode($pid){
        $saveAll = [];
        //添加
        $saveAll[] = [
            'node_name' => '添加',
            'node_url' => 'add',
            'path' => 'add',
            'name' => 'add',
            'pid' => $pid,
            'sort' => 0,
            'is_menu' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        //编辑
        $saveAll[] = [
            'node_name' => '编辑',
            'node_url' => 'edit',
            'path' => 'edit',
            'name' => 'edit',
            'pid' => $pid,
            'sort' => 0,
            'is_menu' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        //删除
        $saveAll[] = [
            'node_name' => '删除',
            'node_url' => 'delete',
            'path' => 'delete',
            'name' => 'delete',
            'pid' => $pid,
            'sort' => 0,
            'is_menu' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        //批量添加添加，编辑，删除
        Db::name('admin_node')->insertAll($saveAll);
    }

    protected function addTableIndexNoExist($tableName, $indexName, $index){
        //先判断是否存在
        $sql = 'SHOW INDEX FROM '.$tableName.' WHERE Key_name = "'.$indexName.'";';
        $res = Db::query($sql);
        if( !$res ){
            //不存在再添加索引
            $sql = 'CREATE INDEX '.$indexName.' ON '.$tableName.'('.$index.');';
            $this->execute($sql);
        }
    }

    //添加钱包参数配置
    protected function addWalletData($field, $defaultValue = 0, $introduce = ''){
        Db::name('wallet_system')->insert([
            'wallet_field' => $field,
            'wallet_value' => $defaultValue,
            'wallet_introduce' => $introduce,
            'create_time' => time(),
            'update_time' => time()
        ]);
    }
}
