<?php

namespace app\common\lib;

use app\common\FastGame\Socket\R;
use app\common\model\ExportReportsModel;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\common\redis\redis;
use app\common\Utils;
use app\model\ThirdConfigs;
use think\db\BaseQuery;
use think\Exception;
use think\facade\Db;
use think\Model;

class rptCol{
    //语言转换
    public const transType_lang='transLang';//翻译 参数：无 | 行数据中存储参数的字段名
    public const transType_thirdname='transThirdname';//三方平台名称转换
    public const transType_gamename='transTypeGamename';//游戏名称
    public const transType_datetime='transDatetime';//时间戳格式转换 参数['timezone'=>,'format'=>]
    public const transType_kvtrans='transKv';//KEY VALUE转换
    public const BuildRowData='==BuildRowData==';//标识此列是为了创建单行数据
    
    public static $thirdNames=[];
    public static $gameNames=[];
    
    public $field;
    public $title;
    public $transtype=null;
    public $transparams=null;


    /**
     * @param string|array $filed 字段名 array为封装使用，调用者请使用string
     * @param string $title 表头名称,仅参与计算，不参与导出的部分不设置title
     * @param $transtype 值转换类型
     * @param $transparams 值转换参数，根据不同转换类型提供（不包含待转换值本身）
     */
    public function __construct($filed,$title=null,$transtype=null,$transparams=null){
        if (is_array($filed)){
          $this->field=$filed['field'];
            $this->title = $filed['title'];
            $this->transtype = $filed['transtype'];
            $this->transparams = $filed['transparams'];
        }else {
            if (strpos($filed,":")>0){
                $tmp=explode(":",$filed);
                $this->field=$tmp[0];
                $this->title=$tmp[1];
            }else {
                $this->field = $filed;
                $this->title = $title;
                $this->transtype = $transtype;
                $this->transparams = $transparams;
            }
        }
    }

    /**
     * 实体保存
     * @return array
     */
    public function toArray(){
        return [
            'field'=>$this->field,
            'title'=>$this->title,
            'transtype'=>$this->transtype,
            'transparams'=>$this->transparams
        ];
    }

    /**
     * @param $value 待转换值
     * @param array $rowdata 当前行数据
     * @return mixed|string
     */
    public function getValue($value,&$rowdata=[]){
        switch ($this->transtype){//TODO 其它可能需要的数据转换可以在此扩展，如自定义转换，可以传入转换方法名或类的静态方法
            case self::transType_lang:
                $vals=isset($rowdata[$this->transparams])?(is_array($rowdata[$this->transparams])?$rowdata[$this->transparams]:json_decode($this->transparams,true)):[];
                return lang($value,$vals);
                //break;
            case self::transType_datetime:
                return $value?Util::localTimeToTimezone($value,$this->transparams['timezone'],$this->transparams['format']):'';
                //break;
            case self::transType_thirdname:
                $value=intval($value);
                if (isset(self::$thirdNames[$value])){
                    return self::$thirdNames[$value];
                }
                $thirdname=ThirdConfigs::where('tc_id', '=', $value)->value('tc_name');
                if ($thirdname){
                    self::$thirdNames[$value]=$thirdname;
                    return $thirdname;
                }else {
                    return $value;
                }
            //break;
            case self::transType_gamename:
                $value=intval($value);
                if (isset(self::$gameNames[$value])){
                    return self::$gameNames[$value];
                }
                $gameNames=GameInfosModel::where('gi_gid',$value)->value('gi_name');
                if ($gameNames){
                    self::$gameNames[$value]=$gameNames;
                    return $gameNames;
                }else {
                    return $value;
                }
            //break;
            case self::transType_kvtrans:
                return $this->transparams[$value]??'无';
                //break;
            case null:
                return $value;
            default://自定义转换
                try {
                    $methodis=explode("::",$this->transtype);
                    //Utils::L('ReportExport',[$this->transtype,$methodis]);
                    if (count($methodis)>0){
                        list($class,$method)=$methodis;
                        //Utils::L('ReportExport',[$class,$method,$value,$class::$method($value,$rowdata)]);
                        if ($value===self::BuildRowData){
                            $rowdata=$class::$method($value, $rowdata, $this->transparams);
                            return null;
                        }else {
                            return $class::$method($value, $rowdata, $this->transparams);
                        }
                    }else{
                        if ($value===self::BuildRowData){
                            $rowdata=$this->transtype($value, $rowdata, $this->transparams);
                            return null;
                        }else {
                            return $this->transtype($value, $rowdata, $this->transparams);
                        }
                    }
                    //return $this->transtype($value, $rowdata);//TODO 调试
                }catch (\Exception $ex){
                    //Utils::L('ReportExport',[$ex->getMessage(),$ex->getTrace()]);
                }
        }
    }
}

class rptParams{

    /** @var string 全数据加载模式，默认 此模式下$classOrSql传入SQL或是数据获方法的类名，获取的数据是全量导出数据，非此模式下，参数$cols参数的第一个值必须是rptCol::BuildRowData特别的：不管什么模式，只要$COLS第一个参数是rptCol::BuildRowData，都会执行指定的行数据获取方法 */
    public const DataLoadMode_all="DataLoadMode_all";
    /** @var string  单行加载模式之数组模式 此模式下$classOrSql传入简略导出数据的顺序数值索引的二维数组（即不包含全行数据）,如果传入二维数组将默认为此模式，此模式仅用于导出少量行数数据，如需导出大量行数数据，请使用  sql模式 或 DataLoadMode_datarow_methodarray 模式，所有datarow模式下建议$readsize参数不要设置过大*/
    public const DataLoadMode_datarow_array="DataLoadMode_datarowby_array";
    /** @var string 单行加载模式之方法模式，此模式下$classOrSql传入数据据获方法的类名，获取的数据是简略导出数据（即不包含全行数据），比如日期列表等,获取数据的方法需要返回顺序数值索引的二维数组类型，方法传入参数增加 page,pagesize,当page传入0时要求返回数据总条数 */
    public const DataLoadMode_datarow_methodarray="DataLoadMode_datarowby_method_witharray";


    /** @var string|null 文件名 */
    private $name;
    /** @var rptCol[] 列配置  */
    private $cols;
    /** @var string|null 类全名或SQL字符串 */
    private $classOrSql;
    /** @var string|null 方法名 */
    private $method;
    /** @var 数据加载模式 */
    private $mode;
    /** @var array|null 方法查询参数或SQL语句中的替换参数 */
    private $params;
    /** @var array|null 文件名扩展信息部分的参数名列表 */
    private $exnamesbyparams;
    /** @var string|null 定位字段名 */
    private $positionField;
    /** @var string 定位字段排序 */
    private $positionOrder;
    private $positionValue=null;//定位字段中间值
    /** @var string|null  */
    private $nametime=null;//名称中的时间部分
    private $csvheader='';//文件头
    private $inited=0;//是否初始化
    private $ended=0;//是否结束
    private $namepre=null;//文件名前缀（除分段以外的其它部分）

    private $namepart=0;//文件分段标识2
    private $partsize=100000;//单文件记录条数 TODO 配置或加参数
    private $nowpartsize=0;//当前分段已写入条数
    private $readsize=5000;//单次读取数据条数
    private $page=1;//当前数据加载的页数

    private $datacount=0;//总条数
    private $dataprocess=0;//进度
    private $filelist=[];//已生成的文件列表


    /**
     * @param string|array $name 文件名 array为封装使用，调用者请使用文件名
     * @param rptCol[] $cols 列配置，字段名=>[title=>标题名，transtype=>转换类型，transparam=>转换参数（不含待转换值）]，字段名不需要带表别名，特别的如果title=rtpCol::BuildRowData，则可以通过将获取行数据的方法传入transtype,导出时将会在每行数据导出时执行此方法以重新组织一行的数据
     * @param string $classOrSql 数据输出类合名或SQL（SQL方案时如果有定位字段参与则必须加入参数{positionWhere} {positionOrder}注order by 自动加入）
     * @param string $method 如果有输出类名，则设置为输出方法名,要求返回的是SQL字符串或是BaseQuery类的对象（MODEL组合的查询），不含 limit和定位字段查询条件，如果$mode=self::DataLoadMode_datarowby_method_witharray,则方法有特定要求，请参考模式说明
     * @param array $params 输出方法需要的参数或SQL方案时仅可作为文件名组合参数供值使用（也可直接通过name参数命名） 报表申请人ID，请使用muid元素给出
     * @param array $exnamesbyparams 文件名扩展部分字段名(必须包含在$params中）
     * @param string|null $positionField 定位字段名，如果需要,仅支持数值型主键或唯一索引字段,如果使用了表别名，需要带上别名
     * @param string|null $positionOrder 定位字段排序方式，如果需要  asc desc
     * @param int $partsize 文件分割数据条数
     * @param int $readsize 单次读取数据条数，必须与文件分割条数为倍数关系
     * @param string $mode 数据加载模式  default null全数据加载模式
     */
    public function __construct($name=null,$cols=null,$classOrSql=null,$method=null,$params=null,$exnamesbyparams=null,$positionField=null,$positionOrder=null,$partsize=100000,$readsize=5000,$mode=self::DataLoadMode_all)
    {
        if (is_array($name)){
            $this->name = $name['name'];
            $this->cols = $name['cols'];
            $this->classOrSql = $name['classOrSql'];
            $this->method = $name['method'];
            $this->params = $name['params'];
            $this->exnamesbyparams = $name['exnamesbyparams'];
            $this->positionField = $name['positionField'];
            $this->positionOrder = $name['positionOrder'];
            $this->nametime = $name['nametime'];
            $this->datacount=$name['datacount'];
            $this->dataprocess=$name['dataprocess'];
            $this->positionValue=$name['positionValue'];
            $this->namepre=$name['namepre'];
            $this->namepart=$name['namepart'];
            $this->inited=$name['inited'];
            $this->ended=$name['ended'];
            $this->partsize=$name['partsize'];
            $this->readsize=$name['readsize'];
            $this->filelist=$name['filelist'];
            $this->page=$name['page'];
            $this->csvheader=$name['csvheader'];
            $this->mode=isset($name['mode'])?$name['mode']:self::DataLoadMode_all;
        }else {
            //处理列
            for($i=0;$i<count($cols);$i++){
                if (is_string($cols[$i])) $cols[$i]=new rptCol($cols[$i]);
            }
            $this->name = $name;
            $this->cols = $cols;
            $this->classOrSql = $classOrSql;
            $this->method = $method;
            $this->params = $params;
            $this->exnamesbyparams = $exnamesbyparams;
            $this->positionField = $positionField;
            $this->positionOrder = $positionOrder;
            $this->nametime = Util::localTimeToTimezone(time(), null, 'YmdHis');
            $this->partsize=$partsize;
            $this->readsize=$readsize;
            $this->mode=$mode;
            if ($mode != self::DataLoadMode_all && $readsize>500){//控制单行模式下一次处理数据不要太多
                $this->readsize=500;
            }
            $this->generateFileNamePre();
        }
    }

    public function __get($propertyName){
        if (in_array($propertyName,['namepre','datacount','dataprocess','inited','ended','filelist','params'])){
            return $this->$propertyName;
        }else{
            return null;
        }
    }

    private function generateFileNamePre(){
        $this->namepre=$this->name.'_'.$this->nametime.'_'.mt_rand(10000,99999);
        if ($this->exnamesbyparams) {
            for ($i = 0; $i < count($this->exnamesbyparams); $i++) {
                isset($this->params[$this->exnamesbyparams[$i]]) && $this->namepre.="_".$this->params[$this->exnamesbyparams[$i]];
            }
        }
        $this->namepre=str_replace(" ",'',$this->namepre);
    }

    /**
     * 初始化数据
     * @return void
     */
    private function initData(){
        if ($this->inited==0) {
            $this->dataprocess=0;
            $this->nowpartsize=0;
            $this->filelist=[];
            $this->page=1;
            //文件名
            /*$this->namepre=$this->name.'_'.$this->nametime;
            if ($this->exnamesbyparams) {
                for ($i = 0; $i < count($this->exnamesbyparams); $i++) {
                    isset($this->params[$this->exnamesbyparams[$i]]) && $this->namepre.="_".$this->params[$this->exnamesbyparams[$i]];
                }
            }*/
            $this->namepart=1;
            //csv头
            if (empty($this->csvheader)){
                //Utils::L('ReportExport',$this->cols);
                foreach($this->cols as $k=>$v){
                    //Utils::L('ReportExport',$v);
                    if (is_numeric($k) && (is_array($v)?$v['field']:$v->field)!=rptCol::BuildRowData) $this->csvheader.=(empty($this->csvheader)?'':',').(is_array($v)?$v['title']:$v->title);
                }
            }
            //数据总条数
            if (is_array($this->classOrSql) || $this->mode==self::DataLoadMode_datarow_array){
                $this->datacount=count($this->classOrSql);
            }elseif (class_exists($this->classOrSql)){
                $method=$this->method;
                if ($this->mode==self::DataLoadMode_datarow_methodarray) {
                    $count = $this->classOrSql::$method(array_merge($this->params, ['page' => 0, 'pagesize' => 0]));
                }else{
                    $this->datacount = $this->classOrSql::$method($this->params);
                    //call_user_func(array($this->classOrSql,$this->method),$this->params);
                    $count = $this->datacount->count();
                }
                //Utils::L('ReportExport',$this->datacount->getLastSql());
                //var_dump($this->datacount);
                $this->datacount=$count;//$this->datacount->count();
                //Utils::L('ReportExport',$this->datacount);
            }else{
                //替换标签
                $sql=str_replace(['{positionWhere}','{positionOrder}'],[' 1=1 ',' '],$this->classOrSql);
                $idx=strpos(strtoupper($sql),'FROM');
                $sql="select count(*) as num ".substr($sql,$idx);
                $rlt=Db::query($sql);
                $count=$rlt[0]['num'];
                $this->datacount=$count;
                //Utils::L('ReportExport',[$sql,$rlt,$count]);
            }
            $this->inited=1;
        }
    }

    private function getFileName(){
        $part=floor($this->dataprocess/$this->partsize)+1;
        $zeros=ceil($this->datacount/$this->partsize);
        $part=str_pad($part,strlen($zeros)+1,'0',STR_PAD_LEFT);
        //return str_replace(' ','',$this->namepre."_".$part).'.csv';
        return $this->namepre."_".$part.'.csv';
    }

    /**
     * @param $file
     * @return false|resource
     */
    private function createFile($file){
        $save_path = public_path();
        $dir='storage/exportReports';
        if (!file_exists($save_path . $dir)) {
            @mkdir($save_path . $dir,0777,true);
        }
        $fw = fopen($save_path.$dir.'/'.$file,'a');
        return $fw;
    }

    /**
     * 生成文件
     * @return boolean 数据导出是否结束
     */
    public function writeData(){
        $this->initData();
        if ($this->ended>0) return true;
        //获取一段数据
        if (is_array($this->classOrSql)){
            $datas=array_slice($this->classOrSql,($this->page-1)*$this->readsize,$this->readsize);
        }elseif (class_exists($this->classOrSql)){
            $method=$this->method;
            if ($this->mode==self::DataLoadMode_datarow_methodarray) {
                $datas=$this->classOrSql::$method(array_merge($this->params,['page'=>$this->page,'pagesize'=>$this->readsize]));
            }else{
                /** @var BaseQuery $query */
                $query = $this->classOrSql::$method($this->params);
                if ($this->positionField) {
                    if ($this->positionValue) {
                        $query = $query->where($this->positionField, $this->positionOrder == 'asc' ? '>' : '<', $this->positionValue);
                    }
                    if ($this->positionOrder) {
                        $query = $query->order($this->positionField, $this->positionOrder);
                    }
                    $query->limit($this->readsize);
                } else {
                    $query->limit(($this->page - 1) * $this->readsize, $this->readsize);
                }
                $datas = $query->select()->toArray();
            }
            //Utils::L('ReportExport',[$query->getLastSql(),$datas]);
        }else{
            //替换标签
            $sql=$this->classOrSql;
            if ($this->positionField){
                if ($this->positionValue){
                    $sql=str_replace('{positionWhere}',$this->positionField.($this->positionOrder=='asc'?'>':'<').$this->positionValue,$sql);
                }
                if ($this->positionOrder){
                    $sql=str_replace('{positionOrder}','order by '.$this->positionField." ".$this->positionOrder,$sql);
                }
                $sql.=" limit ".$this->readsize;
            }else{
                $sql.=" limit ".(($this->page-1)*$this->readsize).",".$this->readsize;
            }
            $sql=str_replace(['{positionWhere}','{positionOrder}'],[' 1=1 ',' '],$sql);//防止首次没有定位字段值的时候标签未被替换
            //Utils::L('ReportExport',[$sql]);
            $datas=Db::query($sql);
            //Utils::L('ReportExport',[$datas]);
        }
        if (count($datas)>0){
            $file=$this->getFileName();
            if (count($this->filelist)==0 || $this->filelist[count($this->filelist)-1]!=$file) array_push($this->filelist,$file);
            //Utils::L('ReportExport',$file);
            $fw=$this->createFile($file);
            if ($fw==false){
                Utils::L('ReportExport',['==============创建文件失败=================',$file]);
                return;
            }
            foreach ($datas as $row){//逐条写入文件
                if ($this->nowpartsize==0){
                    fwrite($fw,"\xEF\xBB\xBF".$this->csvheader);
                }
                $r=[];
                //Utils::L('ReportExport',$row);
                for($i=0;$i<count($this->cols);$i++){

                    if (isset($this->cols[$i])){
                        //TODO 该日志过大，正式服不需要该日志
//                        Utils::L('ReportExport',[$this->cols[$i]]);
                        /*if (array_key_exists($this->cols[$i]->field,$row)) {
                            Utils::L('ReportExport',$row[$this->cols[$i]->field]);
                            //处理列数据
                            array_push($r, "\t" .$this->cols[$i]->getValue($row[$this->cols[$i]->field],$row));
                        }*/
                        if (array_key_exists($this->cols[$i]->field,$row) || $this->cols[$i]->field==rptCol::BuildRowData) {
                            //TODO 该日志过大，正式服不需要该日志
//                            Utils::L('ReportExport', [$this->cols[$i]->field, array_key_exists($this->cols[$i]->field, $row) ? $row[$this->cols[$i]->field] : $this->cols[$i]->field]);
                            //处理列数据
                            $colvalue=$this->cols[$i]->getValue(array_key_exists($this->cols[$i]->field, $row) ? $row[$this->cols[$i]->field] : $this->cols[$i]->field, $row);
                            //rptCol::BuildRowData列不写入数据
                            if ($this->cols[$i]->field!=rptCol::BuildRowData) array_push($r, "\t" .$colvalue );
                        }
                    }else{
                        break;
                    }
                }
                //Utils::L('ReportExport',$r);

                /*foreach ($row as $k=>$v){

                    if (isset($this->cols[$k])) {
                        //处理列数据
                        array_push($r, "\t" .$this->cols[$k]->getValue($v,$row));
                    }
                }*/
                fwrite($fw,"\n".implode(",",$r));
                $this->nowpartsize++;
                $this->dataprocess++;
                if ($this->nowpartsize>=$this->partsize){
                    //array_push($this->filelist,$file);//保存文件列表
                    fclose($fw);
                    $file=$this->getFileName();
                    if (count($this->filelist)==0 || $this->filelist[count($this->filelist)-1]!=$file) array_push($this->filelist,$file);
                    $fw=$this->createFile($file);
                    if ($fw==false){
                        Utils::L('ReportExport',['==============创建文件失败=================',$file]);
                        return;
                    }
                    $this->nowpartsize=0;
                    Utils::L('ReportExport',$file);
                }
            }

            if ($this->positionField){
                $pf=explode('.',$this->positionField);
                $pf=count($pf)==1?$pf[0]:$pf[1];
                $this->positionValue=$datas[count($datas)-1][$pf];
            }else{
                $this->page++;
            }
        }else{
            //数据加载结束
            $this->dataprocess=$this->datacount;
            $this->ended=1;
        }

        //$this->dataprocess+=count($datas);
        $this->datacount=$this->datacount<$this->dataprocess?$this->dataprocess:$this->datacount;
        return $this->ended==1;






    }

    public function toSaveJson(){
        //处理列
        $cols=[];
        foreach($this->cols as $k=>$v){
            if (is_numeric($k)) {
                $cols[$k] = $this->cols[$k]->toArray();
            }
        }
        return json_encode([
            'name'=>$this->name,
            'cols'=>$cols,
            'classOrSql'=>$this->classOrSql,
            'method'=>$this->method,
            'params'=>$this->params,
            'exnamesbyparams'=>$this->exnamesbyparams,
            'positionField'=>$this->positionField,
            'positionOrder'=>$this->positionOrder,
            'positionValue'=>$this->positionValue,
            'datacount'=>$this->datacount,
            'dataprocess'=>$this->dataprocess,
            'nametime'=>$this->nametime,
            'namepre'=>$this->namepre,
            'namepart'=>$this->namepart,
            'inited'=>$this->inited,
            'ended'=>$this->ended,
            'filelist'=>$this->filelist,
            'page'=>$this->page,
            'csvheader'=>$this->csvheader,
            'partsize'=>$this->partsize,
            'readsize'=>$this->readsize,
            'mode'=>$this->mode
        ]);
    }


    public static function fromJson($json){
        $json=json_decode($json,true);
        //处理列
        $cnt=count($json['cols']);
        for($i=0;$i<$cnt;$i++){
            $json['cols'][$i]=new rptCol($json['cols'][$i]);
            $json['cols'][$json['cols'][$i]->field]=$json['cols'][$i];
        }
        //Utils::L('ReportExport',["加了KEY的",$json['cols']]);
        $obj=new rptParams($json);
        return $obj;
    }
}

/**
 * 报表导出封装类
 */
class ReportExport
{

    public static function testExportList($where=null){
        $params=[];
        $params['where']=$where;
        $params['positionField']='a.gb_id';
        $params['positionOrder']='asc';
        $params['date']='2022-12-12 - 2024-03-15';
        $params['muid']=2;
        $class=true;
        ReportExport::export(new rptParams('会员游戏记录',
            [
                //如果导出数据的每一行都需要获取或预处理数据，请勿必保证此列定义在第一行，要求处理数据的方法返回获取或处理的数据（要求数组）
                //使用此种模式时classOrSql语句或方法必须返回一个数值顺序索引的二维数组作为导出数据和种子行
                new rptCol(rptCol::BuildRowData,'','获取单行数据的方法全名，请参考下方的testGetRowData方法','自定义参数'),
                new rptCol('gb_id','ID'),
                new rptCol('gb_gid','游戏名称',rptCol::transType_gamename),
                new rptCol('gb_tcid','游戏平台',rptCol::transType_thirdname),
                'gb_uid:UID',
                'mail:用户邮箱',
                'username:用户名',
                'phone:手机号',
                'gb_sn:注单号',
                new rptCol('gb_bettime','投注时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('gb_overtime' ,'结算时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                'gb_price:投注金額',
                'gb_validwater:有效投注',
                'gb_otherwin:输赢',
                new rptCol('gb_winlose','狀態',rptCol::transType_kvtrans,[0=>'无效', 1 => '未结算', 2 => '已结算', 3 => '已结算', 4 => '已结算'])
            ],
            $class?'app\manage\factory\GameBetsFactory':'SELECT a.gb_id, a.gb_tcid, a.gb_gid, a.gb_uid, a.gb_price, a.gb_sn, a.gb_winlose, a.gb_bettime, a.gb_overtime, a.gb_winprice, a.gb_trans_no, a.gb_otherwin, a.gb_validwater,
                b.username,b.mail,b.phone FROM dq_game_bets a LEFT JOIN dq_user b ON b.id=a.gb_uid WHERE  {positionWhere} and a.gb_bettime >= 1696906800  AND a.gb_bettime <=1996906800 {positionOrder}',$class?'exportListMethod':null,
            $params,
            ['username','agent_name','date','tcid'],
            'gb_id','asc',10000,50,rptParams::DataLoadMode_datarow_methodarray
        ));//注 rptParams::DataLoadMode_datarow_methodarray 参数只为体现使用方式，本演示中的app\manage\factory\GameBetsFactory::exportListMethod 并未支持，请参考下方的 testDataLoadMode_datarow_methodarray 方法演示
    }

    /**
     * DataLoadMode_datarow_methodarray 模式下的方法实现演示
     * @param $params
     * @return array[]|int
     */
    public static function testDataLoadMode_datarow_methodarray($params){
        //可以是任何方式得到的一个二维数组表示的要导出的非全量数据
        $list=[
            ['dt'=>'2023-12-12'],
            ['dt'=>'2023-12-13'],
            ['dt'=>'2023-12-14'],
            ['dt'=>'2023-12-15'],
            ['dt'=>'2023-12-16']
        ];
        if ($params['page']==0){
            return count($list);
        }else{
            return array_slice($list,($params['page']-1)*$params['pagesize'],$params['pagesize']);
        }
    }

    /**
     * 自定义列转换方法演示（用于datarow模式下获取一行数据）
     * @param $value
     * @param $datarow
     * @param $transparams
     * @return null
     */
    public static function testGetRowData($value,$datarow,$transparams){
        //value固定是等于rptCol::BuildRowData的
        $key=$datarow['dt'];
        //获取一行数据
        $data=[];
        $datarow=array_merge($datarow,$data);
        return null; //固定返回null

    }

    /** @var rptParams  */
    public static $task=null;
    /** @var Model  */
    public static $dbtask=null;
    public static $ziped=false;
    public static $uploaded=false;

    public static $erid=0;

    //public static function export($name,$coloums,$classOrSql,$method,$params,$exnamesbyparams,$positionField,$positionOrder){
    /**
     * 写入导出报表任务
     * @param rptParams $pms 报表参数
     * @return void
     */
    public static function export($pms){
        $rlt= ExportReportsModel::create([
            'er_muid'=>isset($pms->params['muid'])?$pms->params['muid']:0,
            'er_intime'=>time(),
            'er_filename'=>$pms->namepre,
            'er_taskjson'=>$pms->toSaveJson()
        ]);
        if ($rlt){
            return true;
        }else{
            return false;
        }

    }

    /**
     * 加载新任务或中断的任务
     */
    private static function loadTask(){
        $task=ExportReportsModel::where('er_id','>',self::$erid)->where('er_ended','<',3)->order('er_id','asc')->limit(1)->find();
        Utils::L('ReportExport',['拿到任务',$task]);
        if ($task){
            self::$erid=$task->er_id;
            self::$dbtask=$task;
            self::$task=rptParams::fromJson($task->er_taskjson);
            self::$ziped=$task->er_ended==2;
            self::$uploaded=$task->er_ended==3;
            return true;
        }else{
            return false;
        }
    }

    /**
     * 保存任务状态
     */
    private static function saveTask(){
        self::$dbtask->er_started=1;
        self::$dbtask->er_taskjson=self::$task->toSaveJson();
        self::$dbtask->er_ended=self::$uploaded?3:(self::$ziped?2:(self::$task->ended==1?1:0));
        //TODO 处理失效时间
        self::$dbtask->er_endtime=self::$dbtask->er_endtime>0?self::$dbtask->er_endtime:(self::$task->ended==1?time():0);
        self::$dbtask->er_process=self::$task->dataprocess;
        self::$dbtask->er_datacount=self::$task->datacount;
        self::$dbtask->save();
    }

    /**
     * 压缩文件
     * @return void
     * @throws Exception
     */
    private static function zipFile(){
        $zip=new \ZipArchive();
        $path=public_path().'storage/exportReports/';
        $file=$path.self::$task->namepre.".zip";
        $fileEncrypt = $path.self::$task->namepre."encrypt.zip";
        Utils::L('ReportExport',['准备生成ZIP文件',$file]);
        if (file_exists($file)){
            $flag=\ZipArchive::OVERWRITE;
        }else{
            $flag=\ZipArchive::CREATE;
        }
        $password = rand(100000, 99999999);
        if ($zip->open($file,$flag)){
            $filelist=self::$task->filelist;
            for($i=0;$i<count($filelist);$i++){
                $zip->addFile($path.$filelist[$i],$filelist[$i]);
            }
            $zip->close();
            // 该命令需要windows支持zip，服务器自带支持
            exec("zip -j -rP$password $fileEncrypt $file");
        }else{
            throw new Exception('ZIP文件创建失败');
        }
        self::$ziped=true;
        self::$dbtask->er_ended=2;
        self::$dbtask->er_password=$password;
        self::$dbtask->save();
        for($i=0;$i<count($filelist);$i++){
            unlink($path.$filelist[$i]);
        }
    }

    /**
     * 上传文件
     * @return void
     * @throws Exception
     */
    private static function uploadS3(){
        //$path=public_path().'storage/exportReports/';
        $path='exportReports/';
        $file=$path.self::$task->namepre.".zip";
        $fileEncrypt=$path.self::$task->namepre."encrypt.zip";
        //没有支持加密的则用原来的
        if( file_exists(public_path().'storage/'.$fileEncrypt) ){
            $file = $fileEncrypt;
        }
        $url=Uploads::uploadS3($file);
        Utils::L('ReportExport',['上传S3',$url]);
        if (!empty($url)) {
            self::$uploaded = true;
            self::$dbtask->er_ended = 3;
            self::$dbtask->er_downloadurl=$url;
            self::$dbtask->save();
            unlink(public_path().'storage/'.$file);
        }else{
            throw new Exception('uploads3错误');
        }
        //TODO 通知下载
    }

    /**
     * 生成报表的任务  分钟任务
     */
    public static function generateReports(){
        Utils::L('ReportExport',['开始分钟任务']);
        $lock=redis::get('generateReports');
        Utils::L('ReportExport',['获取防多开标志',$lock]);
        if ($lock) return;
        redis::set('generateReports',time());
        try {
            $hastask = true;//1
            while ($hastask) {
                try {
                    $hastask = self::loadTask();
                    if ($hastask) {

                        Utils::L('ReportExport', ['获取任务', self::$task->toSaveJson()]);
                        $rlt = false;
                        while (!$rlt) {
                            $rlt = self::$task->writeData();
                            Utils::L('ReportExport', ['导出数据', self::$task->toSaveJson()]);
                            self::saveTask();
                            if ($rlt) {
                                Utils::L('ReportExport', ['准备ZIP']);
                                !self::$ziped && self::zipFile();
                                Utils::L('ReportExport', ['准备UPLOADS3']);
                                !self::$uploaded && self::uploadS3();
                                Utils::L('ReportExport', ['结束任务']);
                            }
                        }

                    }
                }catch (\Exception $ex){
                    echo $ex->getMessage();
                    Utils::L('ReportExport',['============当前任务出错，进行下一任务========',$ex->getMessage(),$ex->getTrace()]);
                }
            }
        }catch (\Exception $ex){
            echo $ex->getMessage();
            Utils::L('ReportExport',[$ex->getMessage(),$ex->getTrace()]);
        }
        redis::del('generateReports');
    }
}