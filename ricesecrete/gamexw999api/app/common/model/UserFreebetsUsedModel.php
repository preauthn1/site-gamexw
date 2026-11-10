<?php
namespace app\common\model;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Utils;
use app\model\GameBetsex;
use think\facade\Db;
use think\Model;
use think\Exception;

class UserFreebetsUsedModel extends Model
{
    protected $name = 'user_freebets_used';
    protected $pk = 'ufu_id';


    /** 核销用户免费注单
     * @param UserFreebetsModel $fb
     * @param string $gbsn
     * @return bool
     */
    public static function VerifcationFreeBet($fb,$gbsn){
        //查询核销记录
        $rlt=self::where('ufu_gbsn',$gbsn)->find();
        if ($rlt){
            return false;//已核销过
        }else{
            //核销记录入库
            $rlt=self::insertGetId([
                'ufu_ufgid'=>$fb->ufg_id,
                'ufu_gbsn'=>$gbsn,
                'ufu_tcid'=>$fb->ufg_tcid,
                'ufu_gid'=>$fb->ufg_gid,
                'ufu_uid'=>$fb->ufg_uid
            ]);
            return !!$rlt;
        }
    }

    /** 更新免费注单核销信息任务，分钟级
     * @return void
     */
    public static function updateUsedinfoTask(){
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        Db::startTrans();
        try{
            $sql="update dq_user_freebets_used,dq_game_bets
set ufu_gbid=gb_id,ufu_overtime=gb_overtime,ufu_winprice=gb_winprice
where ufu_overtime is null and ufu_gbsn=gb_sn and ufu_gid=gb_gid and gb_winlose>1";
            Db::execute($sql);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            Utils::L('freebetsErr',['-----更新核销信息任务出错-----',$sql,$e->getMessage(),$e->getTraceAsString()]);
        }
    }

    
}