<?php


namespace app\common\FastGame\Base;


/**
 * 统一定义返回值结构
 * Class RltContent
 * @package app\common
 * @author twt326
 * @date 2022-05-12 21:40
 * 不建议直接赋值属性，请使用构造函数或set方法
 */
class RltContent{
    public $code;
    public $msg=null;
    public $debugmsg=null;
    public $data=null;
    public $errFlag=false;//是否错误

    /**
     * 返回值对象,无参数创建时默认为 code= Rlt::CODE_OK,msg="",可以使用set方法设置各属性值
     * @param string $code 错误代码 Rlt静态类定义的错误值
     * @param string $msg 错误消息
     * @param string $debugmsg 调试消息
     * @param mixed $data 返回数据
     */
    public function __construct($code=null,$msg=null,$debugmsg=null,$data=null){
        $this->code=$code==null?Rlt::CODE_OK:$code;
        $this->msg=$msg==null?"":$msg;
        $this->debugmsg=$debugmsg;
        $this->data=$data;
        $this->errFlag=!(substr($this->code, 0,2)=="00");
    }

    /**
     * 设置返回值对象属性值
     * @param string $code 错误代码 Rlt静态类定义的错误值
     * @param string $msg 错误消息
     * @param string $debugmsg 调试消息
     * @param mixed $data 返回数据
     */
    public function set($code=null,$msg=null,$debugmsg=null,$data=null){
        $this->code=$code==null?Rlt::CODE_OK:$code;
        $this->msg=$msg==null?"":$msg;
        $this->debugmsg=$debugmsg;
        $this->data=$data;
        $this->errFlag=!(substr($this->code, 0,2)=="00");
    }


    public function __toString(){
        return json_encode($this);
    }

    public function toJson(){
        if (!empty($this->msg) &&  is_string($this->msg)) $this->msg=lang($this->msg);
        return json($this);
    }
}


/**
 * 标准化返回值类
 * Class Rlt
 * @package app\common
 * @author twt326
 * @date 2022-05-12 21:40
 */
class Rlt
{
    //----------------------------------------------------------------------------------正常类
    /** 通用正常 */
    public const CODE_OK="00000";
    /** 找不到数据 */
    public const CODE_NO_DATA="00011";
    /** 验证不通过，不返回错误 */
    public const CODE_NOT_MATCH="00020";
    /** 后端返回确认提示 */
    public const CODE_CONFIRM="00090";
    /** 重定向 */
    public const CODE_REDIRECT="00300";
    //----------------------------------------------------------------------------------权限类
    /** 缺乏权限 */
    public const CODE_PERMISSION_LACK="01001";
    /** 登录失效 */
    public const CODE_PERMISSION_LOGOUTED="01002";
    /** 未登录 */
    public const CODE_NOT_LOGIN="01003";
    /** 缺乏文件系统权限     */
    public const CODE_PERMISSION_LACK_FILESYSTEM="01011";
    //----------------------------------------------------------------------------------验证类
    /** 通用验证失败 */
    public const CODE_VERIFY_ERR="02001";
    /** 不符合规则 */
    public const CODE_VERIFY_ERR_NOTMATCH="02002";
    //----------------------------------------------------------------------------------数据获取类
    /** 通用数据错误     */
    public const CODE_DATA_ERR="03001";
    /** 未找到数据     */
    public const CODE_DATA_ERR_NOTFIND="03002";
    /** 数据不合法     */
    public const CODE_DATA_ERR_ILLEGAL="03003";
    /** 数据重复 */
    public const CODE_DATA_ERR_REPEAT="03004";
    /** 数据保存失败 */
    public const CODE_DATA_ERR_SAVE="03005";
    //-----------------------------------------------------------------------------------数据发送类
    /** 通用数据发送错误 */
    public const CODE_DATA_SEND_ERR="04001";
    /** 通用目标已存在 **/
    public const CODE_DATA_SEND_ERR_TARGETEXISTED="04002";
    /** 文件上传失败 */
    public const CODE_DATA_SEND_ERR_FILEUPLAD="04011";
    /** 目标文件已存在 */
    public const CODE_DATA_SEND_ERR_FILEEXISTED="04012";
    /** 目标目录创建失败 */
    public const CODE_DATA_SEND_ERR_MAKE_DIR="04091";
    /** 目标文件移动失败 */
    public const CODE_DATA_SEND_ERR_MOVE_FILE="04092";
    //-----------------------------------------------------------------------------------支付类
    /** 通用支付错误 */
    public const CODE_PAY_ERR="05001";
    /** 下单支付错误 */
    public const CODE_PAY_ORDER="05002";
    /** 查询错误 */
    public const CODE_PAY_QUERY="05003";
    /** 申请退款错误 */
    public const CODE_PAY_REFUND="05004";
    //-----------------------------------------------------------------------------------非法操作类
    /** 通用非法操作错误 */
    public const CODE_HANDLE_ILLEGAL="99001";

    /**
     * 正常返回标准格式的值
     * @param 错误代码 $code
     * @param 错误消息 $msg
     * @param 测试消息 $debugmsg
     * @param 返回数据 $data
     * @return \App\Common\RltContent
     */
    public static function get($code,$msg,$debugmsg=null,$data=null){
        if (env("app.debug")) {
            return (new RltContent($code, lang($msg), json_encode($debugmsg), $data))->toJson();
        }else{
            return (new RltContent($code, lang($msg), null, $data))->toJson();
        }
    }

    /**
     * 重载 直接返回标准格式返回值对象
     * @param RltContent $rlt
     * @return \App\Common\RltContent
     */
    public static function getRltObj(RltContent $rlt){
        if (env("app.debug")) {
            return $rlt;
        }else{
            $rlt->debugmsg="";
            return $rlt;
        }
    }

    /**
     * 快捷返回无错误标准返回值
     * @param 返回数据 $data
     * @return \App\Common\RltContent
     */
    public static function getok($data=null){
        return (new RltContent(Rlt::CODE_OK ,'ok' ,null,$data))->toJson();
    }


}
