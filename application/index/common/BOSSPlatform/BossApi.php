<?php
namespace app\index\common\BOSSPlatform;

use app\index\common\util\AppResult;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

class BossApi extends BossBasis{

    /**
     * 日志名传入
     * BossApi constructor.
     * @param $logFileName
     */
    public function __construct($logFileName = 'BOSS') {
        parent::__construct($logFileName);
    }


    /**
     * BOSS注册
     * @param $userNo
     * @return string
     */
    public static function bossRegister($userNo) {
        //处理BOSS涉及逻辑
        $user = Db::table('user')->where(['userno'=>$userNo])->find();
        $boss = new BossApi('register');
        try {
            if ($user['bosssta'] == null) { //上传图片
                if($user['byshopno'] == '100110021020' && $user['status'] == 3){
                    //更新图片及标识值；
                    Db::table('user')->where(['userno'=>$userNo])->update(array('bosssta'=>0));

                    //重新获取新数据
                    $user = Db::table('user')->where(['userno'=>$userNo])->find();
                }else{
                    $cardInfo = Db::table('user_card')->where(array('main'=>1,'userno'=>$userNo))->find();
                    $res1 = $boss->fileUpload($user['idno']);   //身份证正面照
                    $res2 = $boss->fileUpload($user['idno_backimg']);    //身份证反面照
                    $res3 = $boss->fileUpload($cardInfo['bankcard_img']);    //银行卡照片
                    $res4 = $boss->fileUpload($user['idno_backimg']); //手持身份证照
                    //照片上传失败
                    if ($res1['respCode'] != '00' || $res2['respCode'] != '00' || $res3['respCode'] != '00' || $res4['respCode'] != '00') {
                        LogUtil::writeLog(['idno'=>$res1, 'idno_backimg'=>$res2,'image_content'=>$res3,'bankcard_img'=>$res4], self::LOG_MODULE, $boss->logFileName, $userNo."--上传照片失败");
                        return "照片上传失败";
                    }
                    $update = [
                        'imgid1'     => $res1['respData'],
                        'imgid2'     => $res2['respData'],
                        'imgid3'     => $res3['respData'],
                        'imgid4'     => $res4['respData'],
                        'bosssta'    => 0
                    ];
                    //更新图片及标识值；
                    Db::table('user')->where(['userno'=>$userNo])->update($update);

                    //重新获取新数据
                    $user = Db::table('user')->where(['userno'=>$userNo])->find();
                }

            }

            if ($user['bosssta'] == 0) { //进行商户注册
                $data = array();
                $data['name']       = $user['name'];
                $data['userNo']     = $user['userno'];
                $data['idCardNo']   = $user['idber'];
                $data['mobile']     = $user['mobile'];
                $data['imgid1']     = $user['imgid1'];
                $data['imgid2']     = $user['imgid2'];
                $data['imgid3']     = $user['imgid3'];
                $data['imgid4']     = $user['imgid4'];
                if(empty($user['address'])){
                    $data['address'] = '浙江省杭州市文一路115号';
                }else{
                    $data['address'] = $user['address'];
                }
                $res = $boss->mRegister($data);
                if ($res['respCode'] != '00') {
                    LogUtil::writeLog($userNo."--商户注册失败", self::LOG_MODULE, $boss->logFileName);
                    return "商户注册进件失败";
                }
                LogUtil::writeLog($userNo."--商户注册成功", self::LOG_MODULE, $boss->logFileName);

                //更新标识值
                Db::table('user')->where(['userno'=>$userNo, 'bosssta'=>0])->update(['bosssta'=>1]);

                //重新获取新数据
                $user = Db::table('user')->where(['userno'=>$userNo])->find();
            }

            if ($user['bosssta'] == 1) { //进行绑卡操作
                //查找预设主卡信息
                $map = array();
                $map['userno'] = $userNo;
                $map['main'] = 1;
                $map['type'] = 1;
                $map['status'] = array('neq',1);
                $card = Db::table('user_card')->where($map)->find();

                $data = array();
                $data['name']     = $user['name'];
                $data['bankName'] = $card['cardname'];
                $data['branchNo'] = $card['branchno'];
                $data['idCardNo'] = $card['idno'];
                $data['mobile']   = $card['mobile'];
                $data['cardNo']   = $card['cardno'];
                $data['userNo']   = $user['userno'];
                $data['branchName'] = $card['branch'];
                $res = $boss->setCard($data);

                if ($res['respCode'] != '00') {
                    LogUtil::writeLog($userNo."--商户绑卡失败", self::LOG_MODULE, $boss->logFileName);
                    return "商户绑卡失败";
                }
                LogUtil::writeLog($userNo."--商户绑卡成功", self::LOG_MODULE, $boss->logFileName);

                //更新标识值
                Db::table('user')->where(['userno'=>$userNo, 'bosssta'=>1])->update(['bosssta'=>2]);

                //重新获取新数据
                $user = Db::table('user')->where(['userno'=>$userNo])->find();
            }

            if ($user['bosssta'] == 2 || $user['bosssta'] == 3) { //进行业务开通操作

                $res = $boss->productCreate($userNo);
                if ($res['respCode'] != '00') {
                    LogUtil::writeLog($userNo."--商户业务开通失败", self::LOG_MODULE, $boss->logFileName);
                    return "商户业务开通失败";
                }
                LogUtil::writeLog($userNo."--商户业务开通成功", self::LOG_MODULE, $boss->logFileName);
                //更新标识值
                Db::table('user')->where(['userno'=>$userNo, 'bosssta'=>2])->update(['bosssta'=>3,'lklmemberNo'=>$userNo]);
            }
            return "SUCCESS";
        } catch (Exception $e) {
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine(), 'trace'=>$e->getTraceAsString()], $boss->logFileName, __FUNCTION__, '系统出错');
        }
    }




    /**
     * 已开通产品查询
     * @param $userNo
     * @return mixed
     * @throws Exception
     */
    public function productQuery($userNo){
        try{
            $data['customerOutNo'] = $userNo;
            $data['method'] = 'zm.customer.product.create.query';
            return $this->reuseCode($data);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 修改结算卡
     * @param $arr
     * @return string
     * @throws Exception
     */
    public function changeCard($arr) {
        try{
            $data = array(
                'cardBankName'=> $arr['cardname'],  //总行名称
                'cardBankNo'=> $arr['branchno'],    //支行联行号
                'cardCertNo'=> $arr['idber'],       //持卡人身份证号
                'cardMobile'=> $arr['mobile'],      //银行预留手机号
                'cardRealName'=> $arr['name'],      //持卡人姓名
                'cardNumber'=> $arr['cardno'],      //银行卡号(新卡)
                'oldCardNumber'=> $arr['oldCardNo'],//银行卡号(旧卡)
                'customerOutNo'=> $arr['userNo'],   //外部客户号
                'cardUse'=> '2',                    //固定值: 2(主结算银行卡)
                'method'=> 'zm.customer.bank.modify',
                'cardBranchBankName'=> $arr['branch'],  //支行名称
            );
            return $this->reuseCode($data);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }


    //四要素验证
    public function id4for($mdata)
    {
        try{
            if(isset($mdata['mobile'])){
                $data['phoneNo'] = $mdata['mobile'];
            }
            $data['customerName'] = $mdata['name'];
            $data['cerdId'] = $mdata['idno'];
            $data['acctNo'] = $mdata['cardNo'];
            $data['method'] = 'zm.pay.gateway.transaction';
            $data['customerOutNo'] = Config::get('customerOutNo');
            $data['productCode'] = '706099';
            return $this->reuseCode($data);//数据格式 {"respCode":"404","respDesc":"请求渠道异常！"}
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }
    public function getMessage(){
        $data['method'] = 'zm.product.customer.query.product807099';
        return $this->reuseCode($data);
    }


    /**
     * 发送短信验证码
     * @param $arr
     * @return string
     * @throws Exception
     */
    public function sendMessage($arr){
        $data['productCode']        =   '807099';
        $data['phoneNo']            =   $arr['mobile'];
        $data['customerOutNo']      =   Config::get('customerOutNo');
        $data['method']             =   'zm.pay.gateway.transaction';
        $data['type']               =   $arr['type'];
        $data['signName']           =   '51收款宝';
        $data['code']               =   $arr['code'];
        return $this->reuseCode($data);
    }

    /**
     * 无卡api
     * @param $data
     * @param $code
     * @return string
     * @throws Exception
     */
    public function wukaApi($data,$code) {
        try{
            $data['method'] = 'zm.pay.gateway.transaction';
            if(!empty($code)){
                $data['verificationCode'] = $code;//短信验证码
            }
            return $this->reuseCode($data);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 无卡支付跳转
     * @param $data
     * @return string
     * @throws Exception
     */
    public function wukaLkl($data){
        try{
            $post = array(
                'orderNo' => $data['orderNo'],
                'productCode' => '123000',
                'acctNo'  => $data['cardNo'],
                'phoneNo' => $data['mobile'],
                'customerName' => $data['name'],
                'cerdId' => $data['idCardNo'],
                'subject' => $data['subject'],
                'notifyUrl'=>$data['notifyUrl'],
                'returnUrl' => $data['returnUrl'],
                'combo' =>  '1',
                'method' => 'zm.pay.gateway.transaction'
            );
            return $this->reuseCode($post);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 产品下单
     * @param $arr
     * @return string
     * @throws Exception
     */
    public function orderCreate($arr) {
        try{
            $data = array(
                'customerNo' => $arr['userNo'],             //外部客户号
                'outOrderNo' => $arr['orderNo'],            //外部订单号
                'orderAmount' => $arr['transAmt'],          //订单金额
                'orderPayAmount' => $arr['orderPayAmount'], //实付金额
                'orderDesc' => $arr['orderDesc'],           //订单描述
                'method' => 'zm.product.order.create',
            );
            return $this->reuseCode($data);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 微信和支付宝支付
     * @param $arr
     */
    public function payForTwoType($arr){
        try{
            $data = $arr;
            $data['method'] = 'zm.pay.gateway.transaction';
            return $this->reuseCode($data);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 用户修改无卡费率
     * @param $userNo
     * @param $shopNo
     * @param $rate
     * @return string
     * @throws Exception
     */
    public function modifyProductForWk($userNo,$shopNo,$rate ,$dRate = 3){
        $shopno = substr($shopNo,0,8);
        $fee = Db::table('section')->where(['shopno'=>$shopno,'type'=>'16'])->find();
        $arr['appId']           = Config::get('appId');
        $arr['partnerCode']     = Config::get('partnerCode');
        $arr['customerNo']      = $userNo;
        $arr['productCode']     = '663006';//产品码
        $arr['productFixrate']  = $dRate;//代付
        $arr['productRate']     = $rate;//扣率
        $arr['customerStartAmt']= $fee['start'];
        $arr['customerEndAmt']  = $fee['end'];
        $arr['notifyUrl']       = Config::get('boss_notify');
        $arr['method']          = 'zm.customer.product.modify.risk';

        return $this->reuseCode($arr , 5);
    }

    /**
     * 单个用户修改费率--实时
     * @param $userNo
     * @param $rate
     * @return string
     * @throws Exception
     */
    public function modifyProductForD0($userNo,$rate){
        $arr['cifCustomer']['appId']            = Config::get('appId');
        $arr['cifCustomer']['partnerCode']      = Config::get('partnerCode');
        $arr['cifCustomer']['customerOutNo']    = $userNo;
        $arr['productCode']                     = Config::get('D0_pay_code');//产品码
        $arr['productFixrate']                  = '2';//代付
        $arr['productRate']                     = $rate;//扣率
        $arr['method']                          = 'zm.product.productCustomer.modifyOrgRate';

        return $this->reuseCode($arr ,5);
    }







}