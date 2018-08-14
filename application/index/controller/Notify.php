<?php
namespace app\index\controller;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\model\PayModel;
use app\index\common\model\UserModel;
use app\index\common\model\WeChat;
use app\index\common\util\AppResult;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Db;
use think\Exception;
use think\Request;

class Notify{

    private $log_model = 'Notify';

    /**
     * 支付异步处理
     * @param Request $request
     */
    public function payNotify(Request $request){
        try{
            $post = $request->post('enctypeData');
            if(empty($post)){
                echo 'FAIL';
                exit();
            }
            $bossModel = new BossApi('notify');

            $res = json_decode($bossModel->notifyMsgDecrypt($post), true);

            LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'异步解密结果');

            //获取订单信息
            $map['billno']  = $res['orderNo'];
            $orderList = Db::table('bill')->where($map)->find();

            //更新订单信息
            if($res['respCode'] == '00'){
                //更新订单信息
                $orderMap['billno'] = $res['orderNo'];
                $orderMap['sta']    = 2;
                $update['code']     = $res['respCode'];
                $update['backdata'] = $res['respDesc'];
                $update['sta']      = 0;
                $ressult = Db::table('bill')->where($orderMap)->update($update);

                if($res['respDesc'] == '订单支付成功代付结果'){
                    $ressult = Db::table('bill')->where(['billno'=>$res['orderNo'],'sta'=>1])->update($update);
                }

                if($ressult){
                    //订单成功后相关操作
                    $deducted = Common::deducted($orderList['userno'],$orderList['shopno'],$orderList['amount'],'无卡');
                    Db::table('bill')->where(['billno'=>$res['orderNo']])->update(['deducted'=>$deducted]);

                    $feeArray = array('0.42','0.4','0.3');
                    if(in_array($orderList['fee'],$feeArray)){
                        Db::table('user')->where(['userno'=>$orderList['userno']])->setDec('quota',$orderList['amount']);
                    }

                    //检验额度
                    PayModel::checkUserVip($orderList['userno']);

                    //判断是否增加用户因交易产生的积分
                    PayModel::scoreHandle($orderList['userno'],$orderList['amount'],4);

                }

            }else{
                $orderMap['billno']     = $res['orderNo'];
                $orderMap['sta']        = 2;
                $update['code']         = $res['respCode'];
                $update['backdata']     = $res['respDesc'];
                $update['sta']          = 1;
                $ressult = Db::table('bill')->where($orderMap)->update($update);
            }
            //更新失败直接提示boss失败
            if(empty($ressult)){
                LogUtil::writeLog(Db::table('bill')->getLastSql(),$this->log_model,'payNotifyFail','更新订单状态失败');
            }
        }catch (Exception $e){
            LogUtil::writeLog(['msg'=>$e->getMessage(), 'line'=>$e->getLine(), 'trace'=>$e->getTrace()],$this->log_model,__FUNCTION__,'系统出错');
            LogUtil::close();
        }
        LogUtil::close();
        exit('SUCCESS');
    }

    /**
     * 刷卡异步回调
     * @param Request $request
     */
    public function payD0Notify(Request $request){
        try{
            $post = $request->post('enctypeData');
            if(empty($post)){
                echo 'FAIL';
                exit();
            }
            $bossModel = new BossApi('notify');

            $res = json_decode($bossModel->notifyMsgDecrypt($post), true);

            LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'异步解密结果');

            //获取订单信息
            $map['billno']  = $res['orderNo'];
            $orderList = Db::table('bill')->where($map)->find();

            //更新订单信息
            if($res['respCode'] == '00'){
                //更新订单信息
                $orderMap['billno'] = $res['orderNo'];
                $orderMap['sta']    = 2;
                $update['code']     = $res['respCode'];
                $update['backdata'] = $res['respDesc'];
                $update['sta']      = 0;
                $update['rechno']   = isset($data['referNo']) ? $data['referNo'] : '';
                $ressult = Db::table('bill')->where($orderMap)->update($update);

                if($ressult){
                    //订单成功后相关操作
                    $deducted = Common::deducted($orderList['userno'],$orderList['shopno'],$orderList['amount'],'刷卡');
                    Db::table('bill')->where(['billno'=>$res['orderNo']])->update(['deducted'=>$deducted]);

                    if($orderList['fee'] == '0.52'){
                        Db::table('user')->where(['userno'=>$orderList['userno']])->setDec('quota',$orderList['amount']);
                    }

                    //检验额度
                    PayModel::checkUserVip($orderList['userno']);

                    //判断是否增加用户因交易产生的积分
                    PayModel::scoreHandle($orderList['userno'],$orderList['amount'],4);

                }

            }elseif($res['respCode'] == '2011'){
                //更新订单信息
                $orderMap['billno'] = $res['orderNo'];
                $orderMap['sta']    = 2;
                $update['code']     = $res['respCode'];
                $update['backdata'] = $res['respDesc'];
                $ressult = Db::table('bill')->where($orderMap)->update($update);
            }else{
                $orderMap['billno']     = $res['orderNo'];
                $orderMap['sta']        = 2;
                $update['code']         = $res['respCode'];
                $update['backdata']     = $res['respDesc'];
                $update['sta']          = 1;
                $ressult = Db::table('bill')->where($orderMap)->update($update);
            }
            //更新失败直接提示boss失败
            if(empty($ressult)){
                LogUtil::writeLog(Db::table('bill')->getLastSql(),$this->log_model,'payNotifyFail','更新订单状态失败');
            }
        }catch (Exception $e){
            LogUtil::writeLog(['msg'=>$e->getMessage(), 'line'=>$e->getLine(), 'trace'=>$e->getTrace()],$this->log_model,__FUNCTION__,'系统出错');
            LogUtil::close();
        }
        LogUtil::close();
        exit('SUCCESS');
    }

    /**
     * 开通产品boss异步处理
     * @param Request $request
     */
    public function bossNotify(Request $request){
        try{
            $post = $request->post('enctypeData');
            if(empty($post)){
                echo 'FAIL';
                exit();
            }
            $bossModel = new BossApi('notify');

            $res = json_decode($bossModel->notifyMsgDecrypt($post), true);
            if(empty($res)){
                echo 'FAIL';
                exit();
            }
            LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'开通产品参数解密');

            $map['userno'] = $res['customerOutNo'];
            if($res['respCode'] != '00'){
                $update['bosssta'] = 2;
                $update['boosRespDesc'] = $res['respDesc'];
                $update['boosRespCode'] = $res['respCode'];
                Db::table('user')->where($map)->update($update);
            }else{
                $update['boosRespDesc'] = '开通成功';
                $update['boosRespCode'] = $res['respCode'];
                $update['lklmemberNo'] = $res['customerOutNo'];
                Db::table('user')->where($map)->update($update);
            }
        }catch (Exception $e){
            LogUtil::writeLog(['msg'=>$e->getMessage(), 'line'=>$e->getLine(), 'trace'=>$e->getTrace()],$this->log_model,__FUNCTION__,'系统出错');
            LogUtil::close();
        }
        LogUtil::close();
        exit('SUCCESS');

    }

    /**
     * 设备购买微信异步回调
     * @throws Exception
     */
    public function buyPosNotify()
    {
        $weChatModel = new WeChat();
        $post = file_get_contents("php://input");
        $resArr = $weChatModel->decodeXml($post);
        LogUtil::writeLog($resArr,$this->log_model,__FUNCTION__,'异步回调');
        if($resArr['return_code'] == 'SUCCESS' && $resArr['appid'] == $weChatModel->appId && $resArr['mch_id'] == $weChatModel->mch_id){
            $pos_order = Db::table('pos_order')->where(array('out_trade_no'=>$resArr['out_trade_no'],'status'=>0))->find();
            $total_fee = $pos_order['amount']*100;
            $res = $weChatModel->selOrderState($resArr['out_trade_no']);
            LogUtil::writeLog($res,'Notify',__FUNCTION__,'微信支付结果查询');
            if($res['trade_state'] == 'SUCCESS' && $res['total_fee'] == $total_fee){
                //支付成功
                $id = Db::table('pos_order')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>1,'time'=>time(),'buyer_id'=>$res['openid']));
                //更改抵用券状态
                $state = UserModel::getInstance($pos_order['userno'])->updateCouponState($pos_order['sourceId']);
                if(!$state) LogUtil::writeLog($pos_order['userno'].'-------'.$pos_order['sourceId'],$this->log_model,__FUNCTION__,'抵用券更新失败');
            }elseif($res['trade_state'] == 'PAYERROR' && $res['total_fee'] == $total_fee){
                //支付失败
                $id = Db::table('pos_order')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>2,'time'=>time(),'buyer_id'=>$res['openid']));
            }else{
                $id = false;
            }
            if($id){
                $returnArr['return_code'] = 'SUCCESS';
                $returnArr['return_msg'] = 'OK';
            }else{
                $returnArr['return_code'] = 'FAIL';
            }
            $xml = $weChatModel->arr2xml($returnArr);
            echo $xml;
        }
        LogUtil::close();
    }

    public function buyPosNotifyForAppStore()
    {
        $weChatModel = new WeChat('AppStore');
        $post = file_get_contents("php://input");
        $resArr = $weChatModel->decodeXml($post);
        LogUtil::writeLog($resArr,$this->log_model,__FUNCTION__,'异步回调');
        if($resArr['return_code'] == 'SUCCESS' && $resArr['appid'] == $weChatModel->appId && $resArr['mch_id'] == $weChatModel->mch_id){
            $pos_order = Db::table('pos_order')->where(array('out_trade_no'=>$resArr['out_trade_no'],'status'=>0))->find();
            $total_fee = $pos_order['amount']*100;
            $res = $weChatModel->selOrderState($resArr['out_trade_no']);
            LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'微信支付结果查询');
            if($res['trade_state'] == 'SUCCESS' && $res['total_fee'] == $total_fee){
                //支付成功
                $id = Db::table('pos_order')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>1,'time'=>time(),'buyer_id'=>$res['openid']));
                //更改抵用券状态
                $state = UserModel::getInstance($pos_order['userno'])->updateCouponState($pos_order['sourceId']);
                if(!$state) LogUtil::writeLog($pos_order['userno'].'-------'.$pos_order['sourceId'],$this->log_model,__FUNCTION__,'抵用券更新失败');
            }elseif($res['trade_state'] == 'PAYERROR' && $res['total_fee'] == $total_fee){
                //支付失败
                $id = Db::table('pos_order')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>2,'time'=>time(),'buyer_id'=>$res['openid']));
            }else{
                $id = false;
            }
            if($id){
                $returnArr['return_code'] = 'SUCCESS';
                $returnArr['return_msg'] = 'OK';
            }else{
                $returnArr['return_code'] = 'FAIL';
            }
            $xml = $weChatModel->arr2xml($returnArr);
            echo $xml;
        }
        LogUtil::close();
    }

    /**
     * Vip购买微信异步回调
     * @throws Exception
     */
    public function buyVipNotify()
    {
        $weChatModel = new WeChat();
        $post = file_get_contents("php://input");
        $resArr = $weChatModel->decodeXml($post);
        if($resArr['return_code'] == 'SUCCESS' && $resArr['appid'] == $weChatModel->appId && $resArr['mch_id'] == $weChatModel->mch_id){
            $allocation_bill = Db::table('allocation_bill')->where(array('out_trade_no'=>$resArr['out_trade_no'],'status'=>0))->find();
            if($allocation_bill['discount'] != 0){
                $total_fee = round($allocation_bill['price'] - $allocation_bill['discount'],2)*100;
            }else{
                $total_fee = $allocation_bill['price']*100;
            }
            $res = $weChatModel->selOrderState($resArr['out_trade_no']);
            LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'微信支付结果查询');
            if($res['trade_state'] == 'SUCCESS' && $res['total_fee'] == $total_fee){
                //支付成功
                $id = Db::table('allocation_bill')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>1,'time'=>time(),'buyer_id'=>$res['openid']));
                //用户购买vip成功后去升级套餐，更改扣率
                UserModel::getInstance($allocation_bill['userno'])->updateUserQuota($allocation_bill['id']);

            }elseif($res['trade_state'] == 'PAYERROR' && $res['total_fee'] == $total_fee){
                //支付失败
                $id = Db::table('pos_order')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>2,'time'=>time(),'buyer_id'=>$res['openid']));
            }else{
                $id = false;
            }
            if($id){
                $returnArr['return_code'] = 'SUCCESS';
                $returnArr['return_msg'] = 'OK';
            }else{
                $returnArr['return_code'] = 'FAIL';
            }
            $xml = $weChatModel->arr2xml($returnArr);
            echo $xml;
        }
        LogUtil::close();
    }

    public function buyVipNotifyForAppStore()
    {
        $weChatModel = new WeChat('AppStore');
        $post = file_get_contents("php://input");
        $resArr = $weChatModel->decodeXml($post);
        LogUtil::writeLog($resArr,$this->log_model,__FUNCTION__,'异步参数');
        if($resArr['return_code'] == 'SUCCESS' && $resArr['appid'] == $weChatModel->appId && $resArr['mch_id'] == $weChatModel->mch_id){
            $allocation_bill = Db::table('allocation_bill')->where(array('out_trade_no'=>$resArr['out_trade_no'],'status'=>0))->find();
            if($allocation_bill['discount'] != 0){
                $total_fee = round($allocation_bill['price'] - $allocation_bill['discount'],2)*100;
            }else{
                $total_fee = $allocation_bill['price']*100;
            }
            $res = $weChatModel->selOrderState($resArr['out_trade_no']);
            LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'微信支付结果查询');
            if($res['trade_state'] == 'SUCCESS' && $res['total_fee'] == $total_fee){
                //支付成功
                $id = Db::table('allocation_bill')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>1,'time'=>time(),'buyer_id'=>$res['openid']));
                //用户购买vip成功后去升级套餐，更改扣率
                UserModel::getInstance($allocation_bill['userno'])->updateUserQuota($allocation_bill['id']);

            }elseif($res['trade_state'] == 'PAYERROR' && $res['total_fee'] == $total_fee){
                //支付失败
                $id = Db::table('pos_order')->where(array('out_trade_no'=>$res['out_trade_no'],'status'=>0))->update(array('status'=>2,'time'=>time(),'buyer_id'=>$res['openid']));
            }else{
                $id = false;
            }
            if($id){
                $returnArr['return_code'] = 'SUCCESS';
                $returnArr['return_msg'] = 'OK';
            }else{
                $returnArr['return_code'] = 'FAIL';
            }
            $xml = $weChatModel->arr2xml($returnArr);
            echo $xml;
        }
        LogUtil::close();
    }

}