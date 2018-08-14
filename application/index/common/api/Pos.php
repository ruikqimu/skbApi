<?php
namespace app\index\common\api;

use app\index\common\model\WeChat;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

class Pos implements CommonApi
{

    private $log_model = 'Pos';
    public $message;

    public $param;

    private $userInfo;


    public function init()
    {
        if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
        else $this->userInfo = Db::table('user')->where('lkey', $this->param['lkey'])->find();
    }

    /**
     * 获取设备列表
     * @return string
     */
    public function getPosList()
    {
        try {
            //设备列表
            $posList = Db::table('good_pos')->where(['zfpb' => 0])->field('zfpb', true)->select();

            if (empty($posList)) return AppResult::response101('未查询到有效机具信息');

            return AppResult::response200('success', $posList);

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * 获取用户地址
     * @return string
     */
    public function getAddress()
    {
        try {
            //获取用户编号
            $userNo = $this->userInfo['userno'];

            //查询用户地址
            $map['userno'] = $userNo;
            $map['status'] = 0;
            $address = Db::table('user_address')->field('id,userno,status', true)->where($map)->find();

            if (empty($address)) return AppResult::response200('success');

            return AppResult::response200('suceess', $address);

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * 获取省市区联动json
     * @return string
     */
    public function getAreaJson()
    {
        try {
            //IOS返回IOS格式json
            if ($this->param['system'] == 'IOS') {
                $data = file_get_contents(Config::get('JSON_FOR_IOS'));
            } else {
                $data = file_get_contents(Config::get('JSON_FOR_AND'));
            }
            return AppResult::response200('success', $data);

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 添加或更改收货地址
     * @return string
     */
    public function uploadUserAddress()
    {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'name,mobile,prov,city,area,address';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            $data = array(
                'name' => $params['name'],    //收件人姓名
                'mobile' => $params['mobile'],  //收件人电话
                'prov' => $params['prov'],    //省
                'city' => $params['city'],    //市
                'area' => $params['area'],    //区
                'address' => $params['address']     //详细地址
            );

            //查询有没有添加收货地址
            $map['userno'] = $this->userInfo['userno'];
            $map['status'] = 0;
            $address = Db::table('user_address')->where($map)->find();

            if (empty($address)) {
                //添加
                $data['userno'] = $this->userInfo['userno'];
                $res = Db::table('user_address')->insert($data);
            } else {
                //更新
                $res = Db::table('user_address')->where(['userno' => $this->userInfo['userno']])->update($data);
            }

            if ($res) return AppResult::response200('success');
            else return AppResult::response101('添加或更新失败');

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 检验用户是否使用抵扣券
     * @return string
     */
    public function checkOrderIsCoupon()
    {
        try{
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'posId,num';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            if (!is_numeric($params['num'])) return AppResult::response101('机具数量只能传整数');

            //获取pos信息
            $pos = Db::table('good_pos')->where(['id' => $params['posId'], 'zfpb' => 0])->find();
            if (empty($pos)) return AppResult::response101('机具信息有误！');

            //判断该用户存在可用抵用券--20180724
            $date = date('Y-m-d').' 23:59:59';
            $goods = DB::query("SELECT g.num,l.id FROM score_goods g, score_goods_log l WHERE l.userno = '".$this->userInfo['userno']."' AND g.id = l.gid AND l.state = 0  AND g.categoryId = 2 AND l.invalidTime >= '".$date."' ORDER BY g.num desc LIMIT 1");

            if(empty($goods[0])){
                $data['isCoupon']       = 0;
                $data['price']          = number_format($params['num'] * $pos['price']);
                $data['couponPrice']    = 0;
            }else{
                $data['isCoupon']       = 1;
                $data['price']          = number_format($params['num'] * $pos['price'] - $goods[0]['num'],2);
                $data['couponPrice']    = $goods[0]['num'];
            }
            return AppResult::response200('success',$data);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 购买设备微信支付
     * @return string
     */
    public function createOrder()
    {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'posId,num';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            if (!is_numeric($params['num'])) return AppResult::response101('机具数量只能传整数');

            //获取pos信息
            $pos = Db::table('good_pos')->where(['id' => $params['posId'], 'zfpb' => 0])->find();
            if (empty($pos)) return AppResult::response101('机具信息有误！');

            //获取收货地址
            $address = Db::table('user_address')->where(['userno' => $this->userInfo['userno'], 'status' => 0])->find();
            if (empty($address)) return AppResult::response101('用户未填写收货信息！');

            //实例化WeChat
            $weChatModel = new WeChat($this->param['app_channel']);

            //判断该用户存在可用抵用券--20180724
            $date = date('Y-m-d').' 23:59:59';
            $goods = DB::query("SELECT g.num,l.id FROM score_goods g, score_goods_log l WHERE l.userno = '".$this->userInfo['userno']."' AND g.id = l.gid AND l.state = 0  AND g.categoryId = 2 AND l.invalidTime >= '".$date."' ORDER BY g.num desc LIMIT 1");

            //微信下单业务参数
            $data['nonce_str']       = time() . rand(1000000, 9999999);   //随机字符串，不长于32位
            $data['spbill_create_ip']= $this->userInfo['ip'];
            $data['notify_url']      = $weChatModel->buyPosNotify;
            $data['trade_type']      = 'APP';

            if(empty($goods[0])){
                $data['out_trade_no']    = Common::getOrderNo('W');
                $data['body']            = '51收款宝设备购买';
                $data['total_fee']       = $params['num'] * $pos['price'] * 100;
                $sourceId = null;
            }else{
                $data['out_trade_no']    = Common::getOrderNo('BW');
                $data['body']            = "现金券（抵扣".intval($goods[0]['num']).'元）51收款宝设备购买';
                $data['total_fee']       = (round($params['num'] * $pos['price'] - $goods[0]['num'],2)) * 100;
                $sourceId = $goods[0]['id'];
            }


            $result = $weChatModel->getWeChatPreOrder($data);
            LogUtil::writeLog($result,$this->log_model,__FUNCTION__,'微信预下单返回');

            //处理返回信息
            if($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS'){
                $returnArr['appid']          = $weChatModel->appId;
                $returnArr['partnerid']      = $weChatModel->mch_id;
                $returnArr['prepayid']       = $result['prepay_id'];
                $returnArr['package']        = 'Sign=WXPay';
                $returnArr['timestamp']      = time();
                $returnArr['noncestr']       = time().rand(1000000, 9999999);
                $returnArr['sign']           = $weChatModel->getSign($returnArr);
                $returnArr['packageValue']   = $returnArr['package'];
                unset($returnArr['package']);
                $amount = $data['total_fee'] / 100;
                $res = $this->addPosBill($data['out_trade_no'],$pos,$address,$sourceId,$amount);
                if(!$res) return AppResult::response101('添加流水失败，请联系客服');
                return AppResult::response200('下单成功',$returnArr);
            }else if($result['return_code'] == 'SUCCESS' && $result['result_code'] != 'SUCCESS'){
                return AppResult::response101($result['err_code_des']);
            }else{
                return AppResult::response101($result['return_msg']);
            }
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * pos订单
     * @param $orderNo
     * @param $pos
     * @param $address
     * @param $sourceId
     * @param $total_fee
     * @return bool
     */
    private function addPosBill($orderNo, $pos, $address , $sourceId ,$total_fee)
    {
        $data['userno']         =   $this->userInfo['userno'];
        $data['out_trade_no']   =   $orderNo;
        $data['Gshop']          =   $this->userInfo['byshopno'];
        $data['code']           =   $pos['code'];
        $data['price']          =   $pos['price'];
        $data['amount']         =   $total_fee;
        $data['consignee']      =   $address['name'];
        $data['mobile']         =   $address['mobile'];
        $data['address']        =   $address['prov'] . $address['city'] . $address['area'] . $address['address'];
        $data['number']         =   $this->param['num'];
        $data['ctime']          =   time();
        $data['status']         =   0;
        $data['sourceId']       =   $sourceId;

        $id = Db::table('pos_order')->insert($data);
        if(empty($id)) return false;
        else return true;
    }


    /**
     * 获取购买设备记录
     * @return string
     */
    public function getBuyPosRecord()
    {
        try{
            $map['a.userno'] = $this->userInfo['userno'];
            $map['status']   = array('in','1,4');
            $field = "b.img,b.name,FROM_UNIXTIME(a.ctime,'%Y-%m-%d') as time,a.price,a.amount,a.status,a.number,md5(a.out_trade_no) as orderNo";
            $list = Db::table('pos_order')
                    ->alias('a')
                    ->join('good_pos b','a.code=b.code')
                    ->field($field)
                    ->where($map)
                    ->order('a.id desc')
                    ->select();
            if(empty($list)) return AppResult::response200('success');

            return AppResult::response200('success',$list);

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 查询订单物流信息
     * @return string
     */
    public function getLogisticsRecord(){
        try{
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'orderNo';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //查询物流信息
            $field = "FROM_UNIXTIME(ctime,'%Y-%m-%d %H:%m:%s') as time,express,waybillNum";
            $data = Db::table('pos_order')->where(['md5(out_trade_no)'=>$params['orderNo']])->field($field)->find();

            if(empty($data)) return AppResult::response101('订单号有误！');

            return AppResult::response200('success',$data);

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

}