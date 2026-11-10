<?php
namespace app\common\model;
use app\common\FastGame\Base\Utils;
use app\model\GameBetsex;
use think\facade\Db;
use think\Model;
use think\Exception;

class UserFreebetsModel extends Model
{
    protected $name = 'user_freebets';
    protected $pk = 'ufg_id';

    /** 核销用户免费下注
     * @param int|UserFreebetsModel $idsn
     * @param string $gbsn
     * @return true
     */
    public static function VerificationFreeBet($idsn,$gbsn){
        try{
            if (is_int($idsn)) {
                $fb = self::find($idsn);
            }else{
                $fb=$idsn;
            }
            if ($fb) {
                //核销免费下注
                $rlt=UserFreebetsUsedModel::VerifcationFreeBet($fb,$gbsn);
                if ($rlt){
                    $fb->ufg_usedrounds++;
                    $fb->save();
                    return true;
                }else{
                    Utils::L('freebetsErr',['-----记录已被核销-----','ufg_id'=>$fb->ufg_id,'gb_sn'=>$gbsn]);
                    return false;
                }
                /*if ($fb->ufg_usedbygbsn==null) {
                    $fb->ufg_usedbygbsn = $gbsn;
                }else{
                    $fb->ufg_usedbygbsn.= ','.$gbsn;
                }
                $fb->save();
                return true;*/
            }else{
                Utils::L('freebetsErr',['-----未找到待核销记录-----','ufg_id'=>$idsn,'gb_sn'=>$gbsn]);
                return false;
            }
        }catch(Exception $e){
            Utils::L('freebetsErr',['=====核销执行错误=====',$e->getMessage(),$e->getTraceAsString()]);
            return false;
        }
    }

    public static function getBonusBalance($uid,$tcid){
        $m=self::where('ufg_uid',$uid)->where('ufg_tcid',$tcid)->sum(Db::raw('ufg_roundspricecount-ufg_usedprice'));
        return $m??0;
    }


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'template_data'=>lang('赠送模板数据'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}