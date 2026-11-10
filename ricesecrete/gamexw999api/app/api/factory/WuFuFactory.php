<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\GameBetsModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\WuFuCardsModel;
use app\common\model\WuFuContentModel;
use app\common\model\WuFuCountModel;
use app\common\model\WuFuModel;
use app\common\model\WuFuWithdrawalModel;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\WalletService;
use think\facade\Db;
use think\facade\Lang;

class WuFuFactory{

    private $uid = 0;

    public function __construct(){
        //如果登录就获取用户uid
        if( !$this->uid ){
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if( $token ){
                    $this->uid = $token->uid;
                }
            }catch (\Exception $e){}
        }
    }

    //获取五福详情数据
    private function getWuFuData($id = 0, $lang = '', $recCode = ''){
        //获取五福数据
        $time = time();
        $condition = [];
        if( $lang ){
            $condition[] = ['lang', 'in', [$lang, '']];
        }
        $condition[] = ['status', '=', 1];
        $condition[] = ['start_time', '<=', $time];
        $condition[] = ['end_time', '>=', $time];
        if( $id ){
            $condition[] = ['id', '=', $id];
        }
        $whereStr = AgentService::getAgentWhere('agent_ids', true, '', true, $recCode);
        return WuFuModel::where($condition)->where($whereStr)->order('id desc')->find();
    }

    //获取数据
    public function getData(){
        $lang = Lang::getLangSet();
        $wufuDetail = $this->getWuFuData(0, $lang);
        if( !$wufuDetail ){
            return ['data' => [], 'code' => 1];
        }

        //是否设置了内容，如果设置已设置的为准
        $_condition = [];
        $_condition[] = ['w_id', '=', $wufuDetail->id];
        $_condition[] = ['lang', '=', $lang];
        $wufuContentDetail = WuFuContentModel::where($_condition)->find();
        if( $wufuContentDetail ){
            $wufuDetail->name = $wufuContentDetail->name;
            $wufuDetail->image = $wufuContentDetail->image;
            $wufuDetail->losing_lottery_img = $wufuContentDetail->losing_lottery_img;
            $wufuDetail->intro = $wufuContentDetail->intro;
            $wufuDetail->introduce = $wufuContentDetail->introduce;
        }


        $currency = Util::i18n_currency_no_amount();
        $returnData = [
            'id' => $wufuDetail->id,
            'name' => $wufuDetail->name,
            'image' => $wufuDetail->image,
            'losing_lottery_img' => $wufuDetail->losing_lottery_img,
            'intro' => $wufuDetail->intro,
            'amount' => round($wufuDetail->amount, 2),
            'introduce' => $wufuDetail->introduce,
            'phone_list' => (new PddFactory())->getGenerateBrazilianPhoneNumber($this->uid, 24),#随机生成巴西号码
            'num' => '*',
            'currency' => $currency,
            'open_num' => 0,
        ];
        if( !$this->uid ){
            return ['data' => $returnData, 'code' => 1];
        }
        //获取用户数据
        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        if( !$userDetail ){
            return ['data' => $returnData, 'code' => 1];
        }
        //用户已刮奖次数
        $returnData['open_num'] = $this->getNum($this->uid, $wufuDetail->id, false);
        //用户剩余刮卡次数
        $returnData['num'] = $this->getNum($this->uid, $wufuDetail->id);
        //邀请链接
        $returnData['rec_code'] = $userDetail->rec_code;
        $returnData['invite_url'] = MemberService::createShareUrl($userDetail->rec_code);
        //获取卡片列表
        $returnData['card_list'] = $this->getCardsList($this->uid, $wufuDetail->id);
        //获取已提款的三十名名用户
        $limit = 30;
        $withdrawalUserList = WuFuWithdrawalModel::field('username,amount')
            ->page(1, $limit)->order('create_time desc')->select();
        if( $withdrawalUserList ){
            $withdrawalUserList = $withdrawalUserList->toArray();
        }
        //判断是否开启虚假提现记录
        if( env('WUFU_IS_OPEN_FALSE_DATA', 0) && ($withdrawalUserCount = count($withdrawalUserList)) < $limit ){
            //生成假数据
            $falseData = $this->createFalseWithdrawalData($wufuDetail->amount, $limit - $withdrawalUserCount);
            $withdrawalUserList = array_merge($withdrawalUserList, $falseData);
        }
        $returnData['withdrawal_list'] = $withdrawalUserList;

        //当前步骤
        $step = $this->getStep($this->uid, $wufuDetail->id);
        $returnData['step_url'] = $step['url'];
        $returnData['step_name'] = $step['name'];

        //我的记录
        $recordList = WuFuWithdrawalModel::field('username,amount')
            ->where('uid', '=', $this->uid)
            ->order('create_time desc')->select();
        foreach ($recordList as &$v){
            $v->amount = Util::i18n_currency($v->amount);
        }
        $returnData['record_list'] = $recordList;
        return ['data' => $returnData, 'code' => 1];
    }

    //刮奖
    public function open(){
        $params = request()->param();
        $wId = $params['w_id'] ?? 0;
        if( !$wId ){
            ajax_return('缺少重要参数');
        }
        $cardDetail = $this->getCardId($wId, $this->uid);
        if( !$cardDetail ){
            $cardId = -1;
        }else{
            $cardId = $cardDetail->id;
        }
        //获取刮中卡片
        return ['data' => ['card_detail' => $cardDetail, 'card_id' => myEncrypt($cardId)], 'code' => 1];
    }

    //执行刮奖
    public function handleOpen(){
        $params = request()->param();
        $wId = $params['w_id'] ?? 0;
        $cardId = myDecrypt($params['card_id'] ?? '');
        if( !$wId || $cardId === false ){
            ajax_return('缺少重要参数');
        }
        //判断刮奖次数
        if( $this->getNum($this->uid, $wId) < 1 ){
            ajax_return('刮奖次数不足');
        }
        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            ajax_return('请先实名后再参与');
        }
        //获取一条未刮奖的数据来进行刮奖
        $condition = [];
        $condition[] = ['card_id', '=', 0];
        $condition[] = ['w_id', '=', $wId];
        $condition[] = ['uid', '=', $this->uid];
        $wufuCountDetail = WuFuCountModel::where($condition)->order('id asc')->find();
        if( !$wufuCountDetail ){
            ajax_return('刮奖次数不足');
        }
        //判断是否完成刮卡
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['w_id', '=', $wId];
        $condition[] = ['card_id', '=', 0];
        if( WuFuWithdrawalModel::where($condition)->value('id') ){
            ajax_return('您已完成该活动');
        }

        $cardDetail = WuFuCardsModel::where('id', '=', $cardId)->find();
        if( !$cardDetail ){
            $cardId = -1;
            $msgContent = lang('很遗憾，您未中奖').'{:name}';
            $name = '';
        }else{
            $cardId = $cardDetail->id;
            $msgContent = lang('获得一张"{:name}"卡');
            $name = $cardDetail->name;
        }
        Db::startTrans();
        try {
            //修改记录并发送消息
            $wufuCountDetail->save([
                'card_id' => $cardId
            ]);
            //发送消息
            $msgData = ['name' => $name];
            $lang = Lang::getLangSet();
            UserMsgModel::add_msg($this->uid, '五福刮奖', $msgContent, 23, $wufuCountDetail->id, $msgData, $lang);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            ajax_return('网络错误');
        }
        //获取刮中卡片
        return ['data' => $cardDetail, 'code' => 1];
    }

    //获取中奖卡片
    private function getCardId($wId, $uid){
        $wuFuDetail = $this->getWuFuData($wId);
        if( !$wuFuDetail ){
            return [];
        }
        //获取用户已有卡片数，第一次必中注册赠送的卡片
        if( $this->getNum($uid, $wId, false) == 0 ){
            //判断是否有注册赠送的卡片
            $condition = [];
            $condition[] = ['is_register_give', '=', 1];
            $condition[] = ['w_id', '=', $wId];
            $cardDetail = WuFuCardsModel::where($condition)->find();
            if( $cardDetail ){
                return $cardDetail;
            }
        }
        //获取邀请人数
        $peopleNum = UserFactory::getUserInvitePerson($uid, $wuFuDetail->start_time);
        //获取达到邀请人数的卡片
        $condition = [];
        $condition[] = ['w_id', '=', $wId];
        $condition[] = ['people_condition', '>', 0];
        $condition[] = ['people_condition', '<=', $peopleNum];
        $cardDetail = WuFuCardsModel::where($condition)->find();
        if( $cardDetail ){
            //判断当前会员是否有该卡片
            $_condition = [];
            $_condition[] = ['w_id', '=', $wId];
            $_condition[] = ['card_id', '=', $cardDetail->id];
            $_condition[] = ['uid', '=', $uid];
            if( !WuFuCountModel::where($_condition)->value('id') ){
                //没有该卡片的话下次中奖一定中奖该卡片
                return $cardDetail;
            }
        }

        //获取卡片列表
        $cardsList = WuFuCardsModel::where('w_id', '=', $wId)->order('sort desc')->select();
        $startNum = 1;
        //设置每个卡片的中奖的区间值
        $cardOddsNumArr = [];
        foreach ($cardsList as $v){
            $arr = [];
            $arr['start_num'] = $startNum;
            $arr['end_num'] = $startNum + $v['odds'] - 1;
            $startNum = $startNum + $v['odds'];
            $arr['data'] = $v;
            $cardOddsNumArr[] = $arr;
        }
        //获取一个1-100的随机数
        $randomNum = rand(1, 100);
        //随机数再那个数组中则为哪个卡片中奖
        foreach ($cardOddsNumArr as $v){
            if( $randomNum >= $v['start_num'] && $randomNum <= $v['end_num'] ){
                return $v['data'];
            }
        }
        return [];
    }

    //领取卡片奖励
    public function receiveCard(){
        $params = request()->param();
        $wId = $params['w_id'] ?? 0;
        if( !$wId ){
            ajax_return('缺少重要参数');
        }
        $cardId = $params['card_id'] ?? 0;
        //获取用户数据
        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        //获取五福任务
        $wufuDetail = $this->getWuFuData($wId);
        if( !$wufuDetail ){
            ajax_return('任务已关闭');
        }

        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        //判断是否兑换
        if( $this->getNum($this->uid, $wId, 0, WuFuCountModel::USE_TYPE_ALL) ){
            //有集体兑奖的就不能继续兑换
            ajax_return('已领取该任务');
        }

        //判断是否完成刮卡
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['w_id', '=', $wufuDetail->id];
        $condition[] = ['card_id', '=', 0];
        if( WuFuWithdrawalModel::where($condition)->value('id') ){
            ajax_return('您已完成该活动');
        }

        $cardIds = [];
        if( $cardId ){
            //单张卡片领取
            //判断卡片是否有两张或以上
            if( $this->getNum($this->uid, $wId, $cardId, WuFuCountModel::USE_TYPE_NO) < 2 ){
                ajax_return('卡片不足');
            }
        }else{
            //集体兑换
            //获取卡片列表
            $cardList = $this->getCardsList($this->uid, $wufuDetail->id);
            foreach ($cardList as $v){
                //判断每张卡片是否都有未使用的
                if( $v['num'] < 1 ){
                    ajax_return('卡片不足');
                }
                $cardIds[] = $v['id'];
            }
        }
        //执行奖励
        return $this->handleReward($userDetail, $wufuDetail, $cardId, $cardIds);
    }

    //执行奖励
    private function handleReward($userDetail, $wufuDetail, $cardId, $cardIds){
        $userCardIds = [];
        if( $cardId ){
            //获取该卡片未使用的记录id
            $userCardIds[] = $this->getNoUseCardRecordId($userDetail->id, $wufuDetail->id, $cardId);
            $useType = WuFuCountModel::USE_TYPE_ONE;
            $cardDetail = WuFuCardsModel::where('id', '=', $cardId)->find();
            $amount = $cardDetail->amount;
            $savings = $cardDetail->savings;
            $msgContent = lang('五福卡片奖励已到账,卡片{:name}，奖励金额 {:price} {:currency}');
            $name = $cardDetail->name;
        }else{
            //集体领取则每张卡片获取一个未使用的卡片记录id
            foreach ($cardIds as $v){
                $userCardIds[] = $this->getNoUseCardRecordId($userDetail->id, $wufuDetail->id, $v);
            }
            $useType = WuFuCountModel::USE_TYPE_ALL;
            $amount = $wufuDetail->amount;
            $savings = $wufuDetail->savings;
            $msgContent = lang('五福卡片奖励已到账,卡片{:name}，奖励金额 {:price} {:currency}');
            $name = '';
        }
        if( !$userCardIds ){
            ajax_return('卡片不足');
        }
        Db::startTrans();
        try {
            //修改卡片记录为已使用
            WuFuCountModel::where('id', 'in', $userCardIds)->update([
                'use_type' => $useType,
            ]);
            //增加一条提现记录
            $withdrawalId = (new WuFuWithdrawalModel())->insertGetId([
                'uid' => $userDetail->id,
                'username' => hideStr($userDetail->username),
                'w_id' => $wufuDetail->id,
                'card_id' => $cardId,
                'amount' => $amount,
                'savings' => $savings,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            //增加用户余额
            $lang = Lang::getLangSet();
            $currency = Util::i18n_currency_no_amount();
            UserModel::cz_balance($userDetail->id, $amount, "+", true, "五福奖励", 17, $savings, 1, $withdrawalId, true, 101);
            $msgData = ['name' => $name, 'price'=>$amount,'currency'=>$currency];
            UserMsgModel::add_msg($userDetail->id, '五福奖励', $msgContent, 23, $withdrawalId, $msgData, $lang);
            Db::commit();
            return ['data' => [], 'code' => 1, 'msg' => 'success'];
        }catch (\Exception $e){
            Db::rollback();
            ajax_return('网络错误');
        }
    }

    //获取未使用的卡片记录id
    private function getNoUseCardRecordId($uid, $wId, $cardId){
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['w_id', '=', $wId];
        $condition[] = ['card_id', '=', $cardId];
        $condition[] = ['use_type', '=', WuFuCountModel::USE_TYPE_NO];
        return WuFuCountModel::where($condition)->order('id asc')->value('id');
    }


    /**
     * 获取用户剩余卡片数量
     * @param $uid
     * @param $wId
     * @param $cardId 0 代表可刮奖次数，有值代表对应卡片数量, false代表卡片数量
     * @return array|int|mixed
     */
    private function getNum($uid, $wId, $cardId = 0, $useType = WuFuCountModel::USE_TYPE_NO){
        if( !$uid || !$wId ){
            return 0;
        }
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        if( $cardId > 0 ){
            $condition[] = ['card_id', '=', $cardId];
        }elseif ( $cardId === false ){
            $condition[] = ['card_id', '<>', 0];
        }elseif ( $cardId === 0 ){
            $condition[] = ['card_id', '=', $cardId];
        }
        $condition[] = ['use_type', '=', $useType];
        $condition[] = ['w_id', '=', $wId];
        $num = WuFuCountModel::where($condition)->count();
        return $num;
    }

    //获取卡片列表
    private function getCardsList($uid, $wId){
        $_field = 'id,name,image,amount';
        $cardsList = WuFuCardsModel::where('w_id', '=', $wId)
            ->field($_field)->order('sort desc')
            ->select();
        foreach ($cardsList as $k => $v){
            //获取用户卡片数量
            $cardsList[$k]['num'] = $this->getNum($uid, $wId, $v['id']);
            $cardsList[$k]['amount'] = round($v['amount'], 2);
        }
        return $cardsList;
    }

    //生成假提现数据
    private function createFalseWithdrawalData($amount, $num){
        $arr = [];
        for ($i = 1; $i <= $num; $i++){
            $_arr = [];
            $_arr['username'] = hideStr(Util::random(10));
            $_arr['amount'] = $amount;
            $arr[] = $_arr;
        }
        return $arr;
    }

    //获取步骤
    private function getStep($uid, $wId){
        //判断是否有首次充值赠送的卡片
//        if( !$this->judgeUserIsHasTypeCard($uid, $wId, WuFuCountModel::TYPE_FIRST_RECHARGE) ){
//            return ['url' => '/wallet', 'name' => '首次充值赠送一次刮奖'];
//        }
        //判断是否有首次游戏赠送的卡片
//        if( !$this->judgeUserIsHasTypeCard($uid, $wId, WuFuCountModel::TYPE_FIRST_GAME) ){
//            return ['url' => '/all-game', 'name' => '首次完成下注赠送一次刮奖'];
//        }
        //判断是否有任务赠送卡片
//        if( !$this->judgeUserIsHasTypeCard($uid, $wId, WuFuCountModel::TYPE_TASK) ){
//            return ['url' => '/layout/task-center', 'name' => '完成任务赠送刮奖机会'];
//        }
        //否则显示邀请好友
        return ['url' => '/layout/task-center', 'name' => '完成任务赠送刮奖机会'];
//        return ['url' => '/team-center', 'name' => '邀请好友充值赠送一次刮奖'];
    }

    //注册赠送卡片，注册调用
    public function giveCardRegister($uid){
        return false;#暂时关闭
        if( !$uid ){
            return false;
        }
        //获取五福数据
        $wufuDetail = $this->getWuFuData();
        if( !$wufuDetail ){
            return false;
        }
        //获取注册赠送的卡片
        $condition = [];
        $condition[] = ['is_register_give', '=', 1];
        $condition[] = ['w_id', '=', $wufuDetail->id];
        $cardId = WuFuCardsModel::where($condition)->value('id');
        if( !$cardId ){
            return false;
        }
        //赠送卡片
        $countId = (new WuFuCountModel())->insertGetId([
            'uid' => $uid,
            'type' => WuFuCountModel::TYPE_REGISTER,
            'w_id' => $wufuDetail->id,
            'card_id' => $cardId,
            'create_time' => time(),
            'update_time' => time(),
        ]);
        //发送消息
//        $lang = Lang::getLangSet();
//        UserMsgModel::add_msg($this->uid, '五福刮奖', '恭喜您完成注册，赠送一张卡片', 23, $countId, [], $lang);
        return true;
    }

    //注册赠送刮奖次数
    public function giveCardFirstRegister($uid, $recCode = ''){
        if( !$uid ){
            return false;
        }
        //获取五福数据
        $wufuDetail = $this->getWuFuData(0, '', $recCode);
        if( !$wufuDetail ){
            return false;
        }

        //判断该用户是否注册的卡片
        if( $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_REGISTER) ){
            return false;
        }
        $saveAll = [];
        for ($i = 0; $i < $wufuDetail->register_give_count; $i++){
            $arr = [
                'uid' => $uid,
                'type' => WuFuCountModel::TYPE_REGISTER,
                'w_id' => $wufuDetail->id,
                'card_id' => 0,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $saveAll[] = $arr;
        }
        if( $saveAll ){
            //赠送卡片
            (new WuFuCountModel())->saveAll($saveAll);
            //发送消息
//        $lang = Lang::getLangSet();
//        $msg = '恭喜您完成注册，赠送一次抽奖机会';
//        UserMsgModel::add_msg($this->uid, '五福刮奖', $msg, 23, $countId, [], $lang);
            $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_REGISTER, true);
        }
        return true;
    }

    //注册赠送刮奖次数
    public function giveCardAppLogin($uid, $recCode = ''){
        if( !$uid ){
            return false;
        }
        //获取五福数据
        $wufuDetail = $this->getWuFuData(0, '', $recCode);
        if( !$wufuDetail ){
            return false;
        }

        //判断该用户是否注册的卡片
        if( $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_DOWNLOAD_APP) ){
            return false;
        }
        //赠送卡片
        $countId = (new WuFuCountModel())->insertGetId([
            'uid' => $uid,
            'type' => WuFuCountModel::TYPE_DOWNLOAD_APP,
            'w_id' => $wufuDetail->id,
            'card_id' => 0,
            'create_time' => time(),
            'update_time' => time(),
        ]);
        //发送消息
//        $lang = Lang::getLangSet();
//        $msg = '恭喜您完成APP下载，赠送一次抽奖机会';
//        UserMsgModel::add_msg($this->uid, '五福刮奖', $msg, 23, $countId, [], $lang);
        $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_DOWNLOAD_APP, true);
        return true;
    }

    //邀请好友赠送卡片
    public function giveCardInvite($uid, $inviteUid){
        return false;#暂时关闭
        if( !$uid || !$inviteUid ){
            return false;
        }

        //判断该用户是否邀请该用户是否获得过卡片
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['invite_uid', '=', $inviteUid];
        if( WuFuCountModel::where($condition)->value('id') ){
            //邀请用户已赠送则不再赠送
            return false;
        }

        //获取五福数据
        $wufuDetail = $this->getWuFuData();
        if( !$wufuDetail ){
            return false;
        }

        //赠送卡片
        $countId = (new WuFuCountModel())->insertGetId([
            'uid' => $uid,
            'type' => WuFuCountModel::TYPE_INVITE,
            'w_id' => $wufuDetail->id,
            'card_id' => 0,
            'invite_uid' => $inviteUid,
            'create_time' => time(),
            'update_time' => time(),
        ]);
        //发送消息
//        $lang = Lang::getLangSet();
//        $msg = '邀请用户【{:uid}】，获得一次刮奖机会';
//        UserMsgModel::add_msg($this->uid, '五福刮奖', $msg, 23, $countId, ['uid' => $inviteUid], $lang);
        return true;
    }

    //首次充值赠送卡片
    public function giveCardFirstRecharge($uid){
        return false;#暂时关闭
        if( !$uid ){
            return false;
        }
        //获取五福数据
        $wufuDetail = $this->getWuFuData();
        if( !$wufuDetail ){
            return false;
        }
        //赠送卡片
        $countId = (new WuFuCountModel())->insertGetId([
            'uid' => $uid,
            'type' => WuFuCountModel::TYPE_FIRST_RECHARGE,
            'w_id' => $wufuDetail->id,
            'card_id' => 0,
            'create_time' => time(),
            'update_time' => time(),
        ]);
        //发送消息
//        $lang = Lang::getLangSet();
//        $msg = '恭喜您完成首次充值，获得一次刮奖机会';
//        UserMsgModel::add_msg($this->uid, '五福刮奖', $msg, 23, $countId, [], $lang);
        $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_FIRST_RECHARGE, true);
        return true;
    }

    //首次游戏赠送卡片
    public function giveCardFirstGame($uid){
        return false;#暂时关闭
        if( !$uid ){
            return false;
        }
        $apiRedis = new ApiRedis();
        $redisKey = 'first_play_game_give_card_'.$uid;
        if( $apiRedis->has($redisKey) ){
            $apiRedis->set($redisKey, 1, 60 * 5);
            return false;
        }
        $apiRedis->set($redisKey, 1, 60 * 5);
        //获取五福数据
        $wufuDetail = $this->getWuFuData();
        if( !$wufuDetail ){
            return false;
        }
        //判断该用户是否有注单
        if( $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_FIRST_GAME) ){
            return false;
        }
        //赠送卡片
        $countId = (new WuFuCountModel())->insertGetId([
            'uid' => $uid,
            'type' => WuFuCountModel::TYPE_FIRST_GAME,
            'w_id' => $wufuDetail->id,
            'card_id' => 0,
            'create_time' => time(),
            'update_time' => time(),
        ]);
        //发送消息
//        $lang = Lang::getLangSet();
//        $msg = '恭喜您完成首次游戏，获得一次刮奖机会';
//        UserMsgModel::add_msg($this->uid, '五福刮奖', $msg, 23, $countId, [], $lang);
        $this->judgeUserIsHasTypeCard($uid, $wufuDetail->id, WuFuCountModel::TYPE_FIRST_GAME, true);
        return true;
    }

    //判断用户是否有该类型的次数
    private function judgeUserIsHasTypeCard($uid, $wId, $type = WuFuCountModel::TYPE_INVITE, $isDatabase = false){
        $apiRedis=new ApiRedis();
        $redisKey = 'judge_user_is_has_type_card_'.$uid.'_'.$wId.'_'.$type;
        if( $apiRedis->has($redisKey) && !$isDatabase ){
            return $apiRedis->get($redisKey);
        }
        //从数据库获取
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['type', '=', $type];
        $condition[] = ['w_id', '=', $wId];
        $id = WuFuCountModel::where($condition)->value('id') ?: 0;
        $apiRedis->set($redisKey, $id, 86400 * 30);
        return $id;
    }

}