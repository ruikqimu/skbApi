<?php
namespace app\index\common\model;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;

class PayModel{

    CONST PAY_TYPE_WK       = '16';   //无卡支付
    CONST PAY_TYPE_WECHAT   = '15';   //微信支付
    CONST PAY_TYPE_ALI      = '17';   //支付宝支付
    CONST PAY_TYPE_D0       = '5';    //即时到账


    /**
     * 获取交易金额限制
     * @param $userInfo
     * @param $type
     * @return int
     */
    public function getPayStartAmount($userInfo,$type){
        $map['shopno'] =    substr($userInfo['byshopno'],0,8);
        $map['type']   =    $type;
        $startAmount   = Db::table('section')->where($map)->column('start');
        return (int)$startAmount[0];
    }

    /**
     * 检查交易时间
     * @return bool
     */
    public function checkTime(){
        date_default_timezone_set('Etc/GMT-8');
        $time = date("Hi", time());
        if ($time >= 2245 || $time <= 700) {
            return false;
        }else{
            return true;
        }
    }


    /**
     * 返回描述转换
     * @param $desc
     * @return string
     */
    public function payDescChange($desc){
        if(strpos($desc,'身份证年龄低于') !== false)           $desc = '暂不支持18岁及以下用户使用';
        if(strpos($desc,'银行账户未通过系统校验') !== false)   $desc = '请尝试更换储蓄卡,如多次未成功,可能已被风控拒绝,如有疑问,请联系客服:400-877-8571';
        if(strpos($desc,'黑名单') !== false)                   $desc = '已被风控拒绝';
        return $desc;

    }

    /**
     * 检验刷卡用户年龄
     * @param $idno
     * @return bool
     */
    public function checkUserAge($idno){
        $age = (substr($idno,6,4)+18) . (substr($idno,10,4));

        if($age > date("Ymd")) return false;
        else return true;
    }

    /**
     * 判断交易卡类型
     * @param $cardNo
     * @return int
     */
    public function checkCardType($cardNo){
        $sql = "SELECT CardType FROM cardbintb WHERE BIN = SUBSTRING(".$cardNo.", 1, BinLen) AND  CardLen = LENGTH(".$cardNo.")";
        $cardType = Db::query($sql);

        if($cardType[0]['CardType'] == '借记卡'){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 判断用户刷卡设备
     * @param $userNo
     * @param $posNo
     * @return bool
     */
    public function checkUserPos($userNo,$posNo){
        if(strpos($posNo,'nfc') !== false) return true;
        $map['userno']  =   $userNo;
        $map['posno']   =   $posNo;
        $map['status']  =   1;

        $posList = Db::table('user_pos')->where($map)->find();

        if(empty($posList)) return false;
        else return true;
    }


    /**
     * 检查用户产品开通
     * @param $userNo
     * @return bool
     * @throws \think\Exception
     */
    public function checkProduct($userNo){
        //查询有没有过刷卡交易
        $map['userno'] = $userNo;
        $map['sta']    = 0;
        $map['type']   = 5;
        $bill = Db::table('bill')->where($map)->find();

        if(empty($bill)){
            $bossModel = new BossApi('productQuery');
            $ret = $bossModel->productQuery($userNo);
            if($ret['respCode'] == '00'){
                $biz = array();
                foreach ($ret['respData']['productEntities'] as $v) {
                    $biz[] = $v['productCode'];
                }
                $bizCode = Config::get('D0_pay_code');
                if(!in_array($bizCode,$biz)){
                    return false;
                }else{
                    return true;
                }
            }else{
                return false;
            }
        }else{
            return true;
        }
    }

    /**
     * 获取用户交易主卡
     * @param $userNo
     * @return mixed
     */
    public static function getUserMainCard($userNo){
        $map['userno'] = $userNo;
        $map['main']   = 1;
        $map['type']   = 1;
        $map['status'] = array('neq','1');
        $card = Db::table('user_card')->where($map)->column('cardno');
        return $card[0];
    }

    /**
     * 交易后检验用户vip
     * @param $userno
     * @throws \think\Exception
     */
    public static function checkUserVip($userno){
        $quota = Db::table('user')->where(['userno'=>$userno])->column('quota');

        if($quota[0] <= 0) {
            $update['level']    =   0;
            $update['vip_new']  =   0;
            Db::table('user')->where(['userno'=>$userno])->update($update);
        }
    }

    /**
     * 判断是否增加用户因交易产生的积分
     * @param $userno
     * @param $amount
     * @param $type
     * @throws \think\Exception
     */
    public static function scoreHandle($userno,$amount,$type){
        LogUtil::writeLog($userno.'--'.$amount.'--'.$type,'PayModel','scoreHandle','积分判断');
        LogUtil::close();
        $date = date('Y-m-d');
        $sql = "SELECT * FROM `user_score_log` WHERE ( `userno` = '".$userno."' ) AND ( `type` IN ('3','4') ) AND (DATE_FORMAT(`date`,'%Y-%m-%d') = '".$date."' )";
        $res = Db::query($sql);

        if(empty($res) && $amount >= 10000){
            $score = Db::table('score_type')->where(array('state'=>0,'type'=>$type))->column('score');
            if(!empty($score)) $score = $score[0];
            Db::table('user_score_log')->insert(array('userno'=>$userno,'score'=>$score,'date'=>date('Y-m-d H:i:s'),'type'=>$type));
            $userScore = Db::table('user_info')->where(array('userno'=>$userno))->column('score');
            $userScore = $userScore[0];
            if(!empty($userScore)){
                Db::table('user_info')->where(array("userno"=>$userno))->setInc('score',$score);
            }else{
                Db::table('user_info')->insert(array('userno'=>$userno,'score'=>$score));
            }
        }
    }


}