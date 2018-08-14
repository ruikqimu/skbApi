<?php
namespace app\index\common\api;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\model\PayModel;
use app\index\common\model\Shop;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

class Pay implements CommonApi
{

    public $param;

    private $message = '';
    private $log_model = 'Pay';
    private $userInfo;
    private $bossDesc;

    private $orderNo;   // 订单号
    private $rechno;    //通道返回码
    private $payModel;  //支付模型
    private $bossModel; //boss模型

    private $rate;    //扣率
    private $dRate;   //代扣手续费
    private $aRate=0;   //刷卡折扣手续费
    private $payRate=0; //刷卡代付折扣手续费
    private $vipBill = 0; //订单默认非vip订单
    private $accountAmout;//实际到账金额
    private $cardType = 0;//刷卡类型 0信用卡，1储蓄卡

    private $orderDesc = '消费';//支付宝微信订单描述
    private $payUrl;            //支付宝微信通道返回url

    public function init()
    {
        $this->payModel = new PayModel();
        $this->bossModel = new BossApi('Pay');
        if(isset($this->param['lkey'])){
            if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
            else $this->userInfo = Db::table('user')->where('lkey', $this->param['lkey'])->find();
        }
    }

    /**
     * 无卡支付获取验证码
     * @return string
     */
    public function cardPayGetCode()
    {
        try {
            //检查必传参数 amount:金额 cvn2 date mobile cardNo
            $str = 'amount,cvn2,date,mobile,cardNo';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }


            //检查交易时间
            if (!$this->payModel->checkTime()) return AppResult::response101('不在服务时间段');

            //检查金额格式
            if(!$this->checkAmount($this->param['amount'])) return AppResult::response101('金额格式有误');

            //检查限额
            $startAmount = $this->payModel->getPayStartAmount($this->userInfo, PayModel::PAY_TYPE_WK);
            if ($this->param['amount'] < $startAmount) return AppResult::response101('交易金额不能小于' . $startAmount . '元');

            //判断用户的vip费率
            if (!$this->checkUserRateForWk()) return AppResult::response101($this->bossDesc);

            //产品下单
            if (!$this->bossPreOrder('无卡支付-D0')) return AppResult::response101($this->bossDesc);

            //发送短信验证码
            if (!$this->wukaPay()) return AppResult::response101($this->bossDesc);

            //生成订单流水
            if (!$this->createBill(1)) return AppResult::response101('生成订单失败，请联系客服！');

            $return['orderNo'] = $this->orderNo;
            return AppResult::response200('success', $return);

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 无卡发起支付
     * @return string
     */
    public function cardPay()
    {
        try {
            //检查必传参数 orderNo 订单号 code 验证码 cvn2 date mobile cardNo
            $str = 'orderNo,code,cvn2,date,mobile,cardNo';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //查询订单信息
            $orderInfo = Db::table('bill')->where(['billno' => $this->param['orderNo']])->find();
            if (empty($orderInfo)) return AppResult::response101('订单信息有误!');

            //通道支付
            $this->orderNo = $this->param['orderNo'];
            if (!$this->wukaPay()) return AppResult::response101($this->bossDesc);

            return AppResult::response200('success');
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 支付宝支付
     * @return string
     */
    public function aliPay()
    {
        try {
            //检查必传参数 amount:金额 cvn2 date mobile cardNo
            $str = 'amount';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //检查金额格式
            if(!$this->checkAmount($this->param['amount'])) return AppResult::response101('金额格式有误');

            //检查交易时间
            if (!$this->payModel->checkTime()) return AppResult::response101('不在服务时间段');

            //检查交易金额
            if ($this->param['amount'] > 2000) return AppResult::response101('交易金额不能大于2000元');

            //boss预下单
            if (!$this->bossPreOrder($this->orderDesc, 'AL')) return AppResult::response101($this->bossDesc);

            //发起支付生成二维码
            if (!$this->forAliAndWeChatPay(1)) return AppResult::response101($this->bossDesc);

            //检验51卡宝vip扣率
            $this->checkUserRateForWeChat();

            //生成订单
            if (!$this->createBill(2)) return AppResult::response101('生成订单失败，请联系客服！');

            $return['imgUrl'] = file_get_contents("http://codepay.zhongmakj.com/BossDeal/changeEwm?ewmurl=" . $this->payUrl);
            $return['payUrl'] = $this->payUrl;

            return AppResult::response200('success', $return);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 微信支付
     * @return string
     */
    public function weChatPay()
    {
        try {
            //检查必传参数 amount:金额 cvn2 date mobile cardNo
            $str = 'amount';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //今天注册的不让用微信
            $datepb = substr($this->userInfo['imgid4'],0,8);

            $kbArray = array('100110021020','100110021017');
            if($datepb >= '20180615' || in_array($this->userInfo['byshopno'],$kbArray)){
                return AppResult::response101('系统升级，请使用其他的收款方式');
            }

            //检查交易时间
            if (!$this->payModel->checkTime()) return AppResult::response101('不在服务时间段');

            //检查金额格式
            if(!$this->checkAmount($this->param['amount'])) return AppResult::response101('金额格式有误');

            //检查交易金额
            if ($this->param['amount'] > 2000) return AppResult::response101('交易金额不能大于2000元');

            //boss预下单
            if (!$this->bossPreOrder($this->orderDesc, 'WX')) return AppResult::response101($this->bossDesc);

            //发起支付生成二维码
            if (!$this->forAliAndWeChatPay(2)) return AppResult::response101($this->bossDesc);

            //检验51卡宝vip扣率
            $this->checkUserRateForWeChat();

            //生成订单
            if (!$this->createBill(3)) return AppResult::response101('生成订单失败，请联系客服！');

            $return['imgUrl'] = file_get_contents("http://codepay.zhongmakj.com/BossDeal/changeEwm?ewmurl=" . $this->payUrl);
            $return['payUrl'] = $this->payUrl;

            return AppResult::response200('success', $return);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 刷卡支付
     * @return string
     */
    public function payForD0()
    {
        try {
            //检查必传参数 amount:金额 posNo 设备号 cardNo 卡号
            $str = 'amount,posNo,cardNo';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //用户编号
            $userNo = $this->userInfo['userno'];

            //检查交易时间
            if (!$this->payModel->checkTime()) return AppResult::response101('不在服务时间段');

            //检查金额格式
            if(!$this->checkAmount($this->param['amount'])) return AppResult::response101('金额格式有误');

            //检查交易设备
            if (!$this->payModel->checkUserPos($userNo, $this->param['posNo'])) return AppResult::response101('该会员未绑定该设备!');

            //检查用户年龄
            if (!$this->payModel->checkUserAge($this->userInfo['idber'])) return AppResult::response101('交易失败：商户未满18周岁！');

            //检查交易卡类型
            $this->cardType = $this->payModel->checkCardType($this->param['cardNo']);

            if ($this->cardType == 1 && $this->param['amount'] > 2000) return AppResult::response101('储蓄卡单笔交易不可大于2000');

            //业务开通判断
            if ($this->userInfo['bosssta'] != 3 || !$this->payModel->checkProduct($userNo)) {
                //异步boss注册
                //异步执行 通道注册、绑卡、产品开通
                LogUtil::writeLog(['msg' => '开始异步请求BOSS', 'url' => Config::get('BOSS_DEAL_URL') . '?userNo=' . $userNo], $this->log_model, __FUNCTION__, 'BOSS请求');
                async_curl(Config::get('BOSS_DEAL_URL') . '?userNo=' . $userNo);
                LogUtil::writeLog(['msg' => '已完成异步请求BOSS', 'url' => Config::get('BOSS_DEAL_URL') . '?userNo=' . $userNo], $this->log_model, __FUNCTION__, 'BOSS请求');

                return AppResult::response101('通道正在开通，请稍后再试!');
            }

            //检查用户vip扣率
            if (!$this->checkUserRateForD0($this->param['amount'])) return AppResult::response101($this->bossDesc);

            //boss预下单
            if (!$this->bossPreOrder($this->orderDesc, 'D0')) return AppResult::response101($this->bossDesc);

            //生成订单流失
            if (!$this->createBill(4)) return AppResult::response101('生成订单失败，请联系客服！');

            $return['vipDesc']    = '当前vip扣率未生效，此次交易将为你转为普通扣率，不扣除vip额度。';
            $return['notifyUrl']  = Config::get('pay_notify_d0');
            $return['orderNo']    = $this->orderNo;
            $return['amount']     = $this->param['amount'];
            $return['Amt']        = $this->accountAmout;
            $return['rate']       = $this->rate;
            $return['dvalue']     = $this->dRate;
            $return['is_vip']     = $this->vipBill;

            return AppResult::response200('success',$return);

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取卡类型
     * @return string
     */
    public function getCardType(){
        try{
            //检查必传参数 cardNo 卡号
            $str = 'cardNo';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //参数
            $params = $this->param;

            //卡bin表获取卡信息
            $cardInfo = Db::query("SELECT CardName, CardType FROM cardbintb WHERE CardLen = ? AND LEFT(?, BinLen) = BIN", [strlen($params['cardNo']), $params['cardNo']]);
            if (empty($cardInfo[0]["CardName"])) return AppResult::response101('无法识别的卡类型！');

            $cardType = $cardInfo[0]['CardType'];
            //判断是否为贷记卡
            if($cardType == '借记卡'){
                $return['cardMark'] = 1;
                $return['message']  = '储蓄卡交易，需第二个工作日结算';
            }else{
                $return['cardMark'] = 0;
                $return['message']  = '';
            }
            return AppResult::response200('success',$return);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 上传签单照
     * @return string
     */
    public function signImage(){
        try{
            //检查必传参数 orderNo
            $str = 'orderNo';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //接收签单图片
            $imgUrl = Common::uploadImg('signImage');

            //压缩图片
//            $imgUrl = Common::tochgimgrand($imgUrl);
//            LogUtil::writeLog('签单图片上传&压缩完成', $this->log_model, __FUNCTION__, '签单图片处理');

            $map['userno'] = $this->userInfo['userno'];
            $map['billno'] = $this->param['orderNo'];
            $update['sign'] = $imgUrl;

            $res = Db::table('bill')->where($map)->update($update);
            if($res) return AppResult::response200('success');
            else return AppResult::response101('签单上传失败！');

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取支付广告位接口
     * @return string
     */
    public function getAdvList(){
        try{
            //过审版本
            if(AuthUtil::$appVersion){
                $return['homepage'] = '';
                $return['codepay']  = '';
                $return['payover']  = '';
            }else{
                if($this->param['system'] == 'IOS'){
                    $map['system'] = 0;
                }else{
                    $map['system'] = 1;
                }
                $map['status'] = 0;
                $return['homepage'] = Db::table('homepage_banner')->where($map)->field("type,image,link")->select();
                $return['codepay']  = Db::table('card_banner')->where(['type'=>2,'status'=>0])->field('image,link')->order('sort asc')->select();
                $return['payover']  = Db::table('card_banner')->where(['type'=>1,'status'=>0])->field('image,link')->order('sort asc')->select();

            }

            return AppResult::response200('success',$return);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * 检验51卡宝微信和支付宝vip用户费率
     */
    private function checkUserRateForWeChat()
    {
        //51卡宝用户判断
        if($this->userInfo['quota'] > 0 && $this->userInfo['fee_wk'] == '0.4'){
            $this->rate = '0.3';
            $this->vipBill = 1;
        }
    }

    /**
     * 检查无卡费率
     * @return bool
     * @throws Exception
     */
    private function checkUserRateForWk()
    {
        //vip扣率
        $vipRate = Db::table('allocation')->where(['zfpb' => 0])->find();

        //代理商扣率
        $shopno = substr($this->userInfo['byshopno'], 0, 8);
        $shopRate = Db::table('section')->where(['shopno' => $shopno, 'type' => 16])->find();
        $this->rate = $shopRate['value'];

        //51卡宝用户判断
        if($this->userInfo['quota'] > 0 && $this->userInfo['fee_wk'] == '0.4'){
            //51卡宝wyVip标记是否更新过boss
            $this->rate = $vipRate['fee_wk'];
            if($this->userInfo['wyVip'] != 2){
                //更新51卡宝vip用户费率
                //更新费率
                $bossModel = new BossApi('Update51Rate');
                $res = $bossModel->modifyProductForWk($this->userInfo['userno'], $shopno, $this->rate);
                if ($res['respCode'] != '00') {
                    $this->bossDesc = $res['respDesc'];
                    return false;
                } else {
                    //更新用户当前费率
                    Db::table('user')->where(['userno' => $this->userInfo['userno']])->update(['wyVip' => 2]);
                }
            }
            $this->vipBill = 1;
        }else{
            if ($this->userInfo['vip_new'] == 1) {
                //新版vip
                if ($this->userInfo['quota'] >= $this->param['amount']) {
                    $this->rate = $vipRate['fee_wk'];
                    $this->vipBill = 1;
                }
                if ($this->userInfo['fee_wk'] != $this->rate) {
                    //更新费率
                    $bossModel = new BossApi('UpdateRate');
                    $res = $bossModel->modifyProductForWk($this->userInfo['userno'], $shopno, $this->rate);
                    if ($res['respCode'] != '00') {
                        $this->bossDesc = $res['respDesc'];
                        return false;
                    } else {
                        //更新用户当前费率
                        Db::table('user')->where(['userno' => $this->userInfo['userno']])->update(['fee_wk' => $this->rate]);
                    }
                }
            }else{
                //普通用户查看fee_wk字段是否等于0.63
                if($this->userInfo['fee_wk'] != '0.63'){
                    //同步boss费率
                    $bossModel = new BossApi('UpdateUserRate');
                    $res = $bossModel->modifyProductForWk($this->userInfo['userno'], $shopno, $this->rate);
                    if ($res['respCode'] != '00') {
                        $this->bossDesc = $res['respDesc'];
                        return false;
                    } else {
                        //更新用户当前费率
                        Db::table('user')->where(['userno' => $this->userInfo['userno']])->update(['fee_wk' => $this->rate]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * 检查D0费率
     * @param $amount
     * @return bool
     * @throws Exception
     */
    private function checkUserRateForD0($amount)
    {
        //vip扣率
        $vipRate = Db::table('allocation')->where(['zfpb' => 0])->find();

        //代理商扣率
        $shopno = substr($this->userInfo['byshopno'], 0, 8);
        $shopRate = Db::table('section')->where(['shopno' => $shopno, 'type' => 5])->find();
        $this->rate = $shopRate['value'];
        $this->dRate= $shopRate['dvalue'];

        //51卡宝vip用户
        if($this->userInfo['quota'] > 0 && $this->userInfo['fee_wk'] == '0.4'){
            $this->vipBill = 1;
            return true;
        }else{
            //判断当前额度和交易金额
            if ($this->userInfo['quota'] >= $amount) {
                $this->rate = $vipRate['fee'];
                $this->aRate= $vipRate['fee'];
                $this->payRate = $vipRate['dvalue'];
                //判断是否是新购买的vip
                if ($this->userInfo['vip_new'] == 1) $this->vipBill = 1;
            }
            if ($this->userInfo['fee'] != $this->rate) {
                //更新费率
                $bossModel = new BossApi('UpdateRateD0');
                $res = $bossModel->modifyProductForD0($this->userInfo['userno'], $this->rate);
                if ($res['respCode'] != '00') {
                    $this->bossDesc = $res['respDesc'];
                    return false;
                } else {
                    //更新用户当前费率
                    Db::table('user')->where(['userno' => $this->userInfo['userno']])->update(['fee' => $this->rate]);
                }
            }
        }
        return true;
    }

    /**
     * boss预下单
     * @param $orderDesc string 订单描述
     * @param string $orderStr string 订单前缀
     * @param string $bossDesc string 下单日志分类
     * @return bool
     * @throws Exception
     */
    private function bossPreOrder($orderDesc, $orderStr = 'WK')
    {
        $data = array(
            'userNo'            => $this->userInfo['userno'],
            'orderNo'           => Common::getOrderNo($orderStr),
            'transAmt'          => $this->param['amount'],
            'orderPayAmount'    => $this->param['amount'],
            'orderDesc'         => $orderDesc,
        );
        $res = $this->bossModel->orderCreate($data);
        if ($res['respCode'] != '00') {
            $this->bossDesc = $res['respDesc'];
            return false;
        } else {
            $this->orderNo = $data['orderNo'];
            return true;
        }

    }

    /**
     * 无卡boss交易
     * @return bool
     */
    private function wukaPay()
    {
        $data = array(
            'orderNo'           => $this->orderNo,                 //外部订单号
            'productCode'       => Config::get('wk_pay_code'),     //产品码
            'acctNo'            => $this->param['cardNo'],         //银行卡号
            'phoneNo'           => $this->param['mobile'],         //银行预留手机号
            'customerName'      => $this->userInfo['name'],        //持卡人姓名
            'cerdId'            => $this->userInfo['idber'],       //身份证号
            'subject'           => '无卡支付-D0',                  //交易描述
            'cvn2'              => $this->param['cvn2'],           //CVN2
            'expDate'           => $this->param['date'],            //卡有效期
            'returnUrl'         => '',
            'notifyUrl'         => Config::get('pay_notify')
        );
        $code = isset($this->param['code']) ? $this->param['code'] : '';
        $res = $this->bossModel->wukaApi($data, $code);
        if ($res['respCode'] != '00') {
            $this->bossDesc = $this->payModel->payDescChange($res['respDesc']);
            return false;
        } else {
            return true;
        }
    }


    /**
     * 支付宝微信获取支付二维码
     * @param string $type 1：支付宝 2；微信
     * @return bool
     */
    private function forAliAndWeChatPay($type = '1')
    {
        $post['orderNo']        = $this->orderNo;
        $post['productCode']    = $type == 1 ? Config::get('al_pay_code') : Config::get('wx_pay_code');
        $post['notifyUrl']      = Config::get('pay_notify');
        $post['subject']        = $this->orderDesc;
        $post['payTypeCode']    = $type == 1 ? 'ALIPAY_NATIVE' : 'WEIXIN_NATIVE';
        $res = $this->bossModel->payForTwoType($post);
        if ($res['respCode'] != '00') {
            $this->bossDesc = $res['respDesc'];
            return false;
        } else {
            $this->rechno = isset($res['respData']['merOrderNo']) ? $res['respData']['merOrderNo'] : '';
            $this->payUrl = $res['respData']['payUrl'];
            return true;
        }
    }

    /**
     * 生成流水订单
     * @param $billType
     * @return bool
     */
    private function createBill($billType)
    {
        switch ($billType) {
            case 1:
                //无卡支付
                $type               = PayModel::PAY_TYPE_WK;
                $typeName           = '无卡支付-D0';
                $data['cardnos']    = $this->param['cardNo'];
                $data['cardno']     = PayModel::getUserMainCard($this->userInfo['userno']);
                $posno              = Config::get('wk_pay_code');
                break;
            case 2:
                //支付宝
                $type               = PayModel::PAY_TYPE_ALI;
                $typeName           = '支付宝被扫-D0';
                $data['cardnos']    = '支付宝账户';
                $data['cardno']     = '支付宝账户';
                $posno              = Config::get('al_pay_code');
                break;
            case 3:
                //微信
                $type               = PayModel::PAY_TYPE_WECHAT;
                $typeName           = '微信被扫-D0';
                $data['cardnos']    = '微信账户';
                $data['cardno']     = '微信账户';
                $posno              = Config::get('wx_pay_code');
                break;
            case 4:
                //即时到账
                $type               = PayModel::PAY_TYPE_D0;
                $typeName           = '即时到账';
                $data['cardnos']    = $this->param['cardNo'];
                $data['cardno']     = PayModel::getUserMainCard($this->userInfo['userno']);
                $posno              = $this->param['posNo'];
                $data['A_rate']     = sprintf("%.2f",$this->aRate * $this->param['amount'] / 100);
                $data['A_dvalue']   = sprintf("%.2f",$this->payRate);
                $data['cardtype']   = $this->cardType;

                break;
        }
        //查询不同交易类型的扣率
        $map['shopno'] = substr($this->userInfo['byshopno'], 0, 8);
        $map['type'] = $type;
        $section = Db::table('section')->where($map)->find();
        if (empty($section)) return false;

        if (!isset($this->rate)) $this->rate = $section['value'];

        //初始化订单数据
        $data['userno']     = $this->userInfo['userno'];
        $data['shopno']     = $this->userInfo['byshopno'];
        $data['shopname']   = Shop::getShopName($this->userInfo['byshopno']);
        $data['billno']     = $this->orderNo;
        $data['tabno']      = $this->orderNo;
        $data['rechno']     = isset($this->rechno) ? $this->rechno : $this->orderNo;
        $data['drate']      = $section['dvalue'];
        $data['type']       = $type;
        $data['typename']   = $typeName;
        $data['ctime']      = time();
        $data['amount']     = $this->param['amount'];
        $data['rate']       = sprintf("%.2f",$this->rate * $this->param['amount'] / 100);
        $data['sta']        = 2;
        $data['code']       = '';
        $data['backdata']   = '';
        $data['mobile']     = $this->userInfo['mobile'];
        $data['posno']      = $posno;
        $data['ip']         = $_SERVER['REMOTE_ADDR'];
        $data['gps']        = '123.4567-123.4567';
        $data['fee']        = $this->rate;
        $data['is_vip']     = $this->vipBill;
        $billId = Db::table('bill')->insert($data);
        $this->accountAmout = $data['amount'] - $data['rate'] - $data['drate'];
        if (empty($billId)) return false;
        else return true;
    }

    /**
     * 验证金额格式
     * @param $amount
     * @return bool
     */
    private function checkAmount($amount){
        if(is_numeric($amount)){
            return true;
        }else{
            return false;
        }
    }

    public function bossJson(){
        try{
            $data[] = array('缺少参数：pin '=>'交易失败，请重试');
            $data[] = array('获取秘钥数据失败'=>'请重新绑定设备');
            $data[] = array('四要素未通过'=>'确保您在银行预留的姓名、手机号、银行卡号、身份证号与银行卡一致');
            $data[] = array('渠道商户未开通'=>'请尝试更换银行储蓄卡');
            $data[] = array('刷卡提示终端被禁'=>'您已在多个渠道平台注册，身份证注册的账号数超出限制');
            $data[] = array('读取卡片信息失败'=>'无法读取卡片的信息，请重新操作');
            $data[] = array('支付渠道未开通'=>'请重新绑定设备');
            $data[] = array('未开通在线支付功能'=>'请致电银联 95516 开通在线支付功能');
            $data[] = array('渠道商户未开通：身份证号'=>'已被风控拒绝');
            $data[] = array('客户未开通该支付产品'=>'请联系客户或稍后再重试');
            return AppResult::response200('success',$data);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
}