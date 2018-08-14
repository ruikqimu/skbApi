<?php
namespace app\index\common\model;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

class UserModel{

    //用户编号
    private $userNo;
    private $shopNo;
    private $quota;

    //查询参数
    private $map;

    private static $instance;

    private function __construct($userNo)
    {
        $this->userNo = $userNo;
        $this->map['userno'] = $this->userNo;
    }

    /**
     * 初始化用户单列模型
     * @param $userNo
     * @return UserModel
     */
    public static function getInstance($userNo){

        if(!self::$instance instanceof self){
            self::$instance = new self($userNo);
        }

        return self::$instance;
    }


    /**
     * 获取用户积分
     * @return int
     */
    public function getUserScore(){
        $count = Db::table('user_info')->where($this->map)->column('score');

        if(empty($count)) return 0;
        else return $count[0];
    }

    /**
     * 获取用户星级
     * @return int
     */
    public function getUserStar(){
        $star = Db::table('user_level_star')->where($this->map)->column('star');

        if(empty($star)) return 0;
        else return $star[0];
    }

    /**
     * 判断用户今日是否已签到
     * @return int
     */
    public function getUserSignIn(){
        $date = date("Y-m-d");
        $map['left(date,10)'] = $date;
        $map['userno']        = $this->userNo;
        $map['type']          = 1;
        $score = Db::table('user_score_log')->where($map)->find();

        if(empty($score)) return 0;
        else return 1;
    }


    /**
     * 获取当前结算卡和信用卡张数
     * @return mixed
     */
    public function getCardNums(){
        $return['payCount']     =   0;  //信用卡
        $return['debitCount']   =   0;  //结算卡

        //查询数据
        $res = Db::query("select count(*) as count,type from user_card where userno=? and status != ? group by type",[$this->userNo,1]);

        if(!empty($res)){
            foreach($res as $key => $value){
                if($value['type'] == '1'){
                    $return['debitCount'] = $value['count'];
                }
                if($value['type'] == '3'){
                    $return['payCount'] = $value['count'];
                }
            }
        }

        return $return;
    }

    /**
     * 检验帮你还资格
     * @return bool
     */
    public function checkUserForBnh(){
        $posno = Db::table('user_pos')->where(array('userno'=>$this->userNo,'status'=>1))->column('posno');
        if(empty($posno[0])) return false;
        $amount = Db::table('bill')->where(array('userno'=>$this->userNo,'type'=>'5','sta'=>0))->sum('amount');

        if(empty($amount)){
            return false;
        }
        $amount = (int)$amount;
        if($amount < 100){
            return false;
        }
        return true;
    }

    //更新用户vip额度时间
    public function updateUserQuota($id){
        try{
            LogUtil::writeLog($this->userNo,'Vip','vipNotify','购买vip成功回调开始');
            $vipDate = $this->getUserVipDate();

            //订单id
            $allocation = Db::table('allocation_bill')->where(['id'=>$id])->find();

            //更新用户扣率
            $bossModel = new BossApi('vipNotify');
            //无卡扣率更新
            $res = $bossModel->modifyProductForWk($this->userNo,$this->shopNo,Config::get('cardPayVip'));
            if($res['respCode'] == '00' ) $update['fee_wk']   = $allocation['fee_wk'];
            //刷卡扣率更新
            $res = $bossModel->modifyProductForD0($this->userNo,Config::get('posFeeVip'));
            if($res['respCode'] == '00') $update['fee']      = $allocation['fee'];

            //更新用户等级
            $update['level']    = $allocation['id'];
            $update['vipDate']  = $vipDate;
            $update['vip_new']  = 1;
            $update['quota']    = $this->quota + $allocation['quota'] * 10000;
            Db::table('user')->where($this->map)->update($update);

            //更新用户的红包使用状态
            //更新用户的红包或抵用券使用状态--201800725
            $orderMark = substr($allocation['out_trade_no'],0,1);
            switch ($orderMark) {
                case 'A':
                    $id = Db::table('user_encourage')->where(array('id'=>$allocation['sourceId'], 'status'=>0))->update(array('status'=>1, 'out_trade_no'=>$allocation['out_trade_no'], 'usedTime'=>date('Y-m-d H:i:s')));
                    if(!$id) LogUtil::writeLog($allocation['userno'],'Vip','buyVipNotify','红包更新失败');
                    unset($id);
                    break;
                case 'B':
                    $id = Db::table("score_goods_log")->where(array('id'=>$allocation['sourceId'],'state'=>0))->update(array('state'=>1,'useTime'=>date('Y-m-d H:i:s')));
                    if(!$id) LogUtil::writeLog($allocation['userno'],'Vip','buyVipNotify','抵用券更新失败');
                    unset($id);
                    break;

                default:
                    # code...
                    break;
            }

            //更新用户积分
            $this->scoreHandle(2);

        }catch (Exception $e){
            LogUtil::writeLog($this->userNo.$e->getMessage(),'Vip','vipNotify','购买vip回调处理失败');
            LogUtil::close();
        }

    }

    /**
     * 获取vip时间
     * @return string
     */
    private function getUserVipDate(){
        $user = Db::table('user')->where($this->map)->field('quota,byshopno,level,vipDate')->find();
        $this->quota = $user['quota'];
        $this->shopNo=$user['byshopno'];
        if(empty($user['level'])) return date('Y-m-d',strtotime("+12 month")).' 23:59:59';
        $vipDate = strtotime($user['vipDate']);
        $diff = $vipDate - time();
        if($diff <= 31536000){
            $vipDate = $vipDate + strtotime("+12 month") - time();
            return date('Y-m-d',$vipDate).' 23:59:59';
        }else{
            return date('Y-m-d',strtotime("+24 month")).' 23:59:59';
        }
    }

    //购买VIP积分添加
    public function scoreHandle($type)
    {
        $score = Db::table('score_type')->where(array('state'=>0,'type'=>$type))->column('score');
        $add['userno'] = $this->userNo;
        $add['score']  = $score[0];
        $add['date']   = Common::getDate();
        $add['type']   = $type;
        Db::table('user_score_log')->insert($add);
        $userScore = Db::table('user_info')->where($this->map)->column('score');
        if(!empty($userScore[0])){
            Db::table('user_info')->where($this->map)->setInc('score',$score[0]);
        }else{
            Db::table('user_info')->insert(array('userno'=>$this->userNo,'score'=>$score[0]));
        }
    }

    /**
     * 检查用户交易总金额
     * @return float|int
     */
    public function checkUserAmount()
    {
        $this->map['sta'] = 1;
        $amount = Db::table('bill')->where($this->map)->sum('amount');
        return $amount;
    }


    /**
     * 更改抵用券状态
     * @param $id
     * @return bool
     */
    public function updateCouponState($id)
    {
        try{
            $update['state']    = 1;
            $update['useTime']  = Common::getDate();
            $res = Db::table('score_goods_log')->where(['id'=>$id,'state'=>0])->update($update);
            if($res) return true;
            else return false;
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),"UserModel",__FUNCTION__,'更改抵用券失败');
            LogUtil::close();
            return false;
        }


    }

    //51卡宝用户送200元设备购买代金券
    public function scoreCouponCheck($shopno)
    {
        $arr = array('100110021020','100110021017');
        $log = Db::table('score_goods_log')->where(array('userno'=>$this->userNo,'gid'=>7))->find();
        if(in_array($shopno,$arr) && empty($log)){
            $data = array(
                'userno'=>$this->userNo,
                'gid'=>7,
                'exchangeTime'=>date('Y-m-d H:i:s'),
                'invalidTime'=>date("Y-m-d",strtotime("+1 month"))." 23:59:59"
            );

            Db::table('score_goods_log')->insert($data);
            LogUtil::writeLog($data,'UserModel',__FUNCTION__,'添加优惠券');
        }
    }
}