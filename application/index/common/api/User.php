<?php
namespace app\index\common\api;

use app\index\common\model\MessageModel;
use app\index\common\model\UserModel;
use app\index\common\model\WeChat;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

/*用户通用模块*/

class User implements CommonApi
{

    //日志模块名
    const LOG_MODULE = 'User';

    //用户数据
    private $userInfo = null;

    public $param;

    /**
     * User constructor.
     */
    public function init()
    {
        if(isset($this->param['lkey'])){
            //根据token获取user信息
            if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
            else $this->userInfo = Db::table('user')->where('lkey', $this->param['lkey'])->find();
        }

    }

    /*
     * APP更新版本信息获取
     * @access public
     * @return mixed
     * */
    public function versionInfo()
    {
        try {
            $params = $this->param;
            $appChannel = Db::table('app_channel')->where(['appChannel'=>$params['app_channel'],'status'=>1])->find();
            if(!empty($appChannel)){
                //通道版本
                if(version_compare($appChannel['version'],$params['version']) > 0){
                    $return['version'] = $appChannel['version'];
                    $return['link']    = $appChannel['link'];
                    $return['explain'] = $appChannel['explain'];
                    $return['force']   = $appChannel['force'];
                    return AppResult::response200('可升级',$return);
                }else{
                    return AppResult::response200('当前版本已为最新版本！');
                }
            }
            if($params['app_channel'] == 'guanfang'){
                //官方版本
                $versionMap['appkey'] = $params['appkey'];
                $versionMap['zfpb']   = 0;
                $versionMap['system'] = $params['system'] == 'IOS' ? 1 : 0;
                $version = Db::table('app_version') ->where($versionMap)->field("version,link,explain,force")->order("id desc") ->find();
                if(empty($version)) return AppResult::response200('无新版本');

                if (version_compare($version['version'], $params['version']) > 0 ) {
                    return AppResult::response200('可升级',$version);
                }else{
                    return AppResult::response200('当前版本已为最新版本！');
                }
            }else{
                return AppResult::response200('当前版本已为最新版本！');
            }
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /*
     * 获取账号状态信息
     * @access public
     * @return mixed
     * */
    public function requireParams()
    {
        try {
            $userInfo = $this->userInfo;
            //是否绑定设备
            $userPos = Db::table('user_pos')->where(array('userno' => $userInfo['userno']))->order('ktime desc')->find();
            $data['pos'] = empty($userPos) ? "" : $userPos['posno'];

            //用户认证状态
            $data['verifyStatus'] = $userInfo['status'];    //0未审核，1审核中，2审核失败，3已认证

            //是否有主卡
            $data['isMainCard'] =   1;  //默认有主卡
            $cardMap['userno']  =   $userInfo['userno'];
            $cardMap['type']    =   1;
            $cardMap['status']  =   0;
            $cardMap['main']    =   1;
            $userCard = Db::table('user_card')->where($cardMap)->find();
            if(empty($userCard)) $data['isMainCard'] = 0;

            //查询用户是否申请  1:不进件 0：进件
            $data['mccSta'] = 1;
//            if ($userInfo['ctime'] > 1528696800) {
//                $user_info = Db::table('user_info')->where(['userno' => $userInfo['userno']])->find();
//                empty($user_info['merchantName']) ? $data['mccSta'] = 0 : $data['mccSta'] = 1;
//            }

            //获取姓名
            $data['name'] = empty($userInfo['name']) ? "" : $userInfo['name'];
            //获取身份证号码
            $data['idber'] = empty($userInfo['idber']) ? "" : $userInfo['idber'];
            //返回手机号
            $data['mobile'] = $userInfo['mobile'];

            //检验黑名单
            if(AuthUtil::checkBlackList($data['mobile'],$data['idber'])) return AppResult::response400('账户状态异常，请联系客服');

            //当前代理商编号
            $data['shopno'] = md5($userInfo['byshopno']);

            //用户编号
            $data['userNo'] = $userInfo['userno'];

            //交易信息提示
            $data['payNotice'] = '该交易需绑定POS设备（可在线购买），如果您无POS机，我们推荐您使用无卡收款！';

            //判断用户添加推送消息
            if($this->userInfo['quota'] != 0 && $this->userInfo['fee_wk'] == '0.4' && $this->userInfo['wyVip'] == 0){
                //添加推送消息
                $messageModel = new MessageModel($this->userInfo['userno']);

                //消息内容
                $title      = 'VIP已同步成功';
                $content    = "你的51卡宝VIP已成功同步，相应权益已为你转化为五千万元免费额度，费率不变，有效期至".$this->userInfo['vipDate']."，可在“首页-VIP购买”页查看详情。";

                //添加消息
                $noticeId = $messageModel->addNotice($title,$content);
                if(!$noticeId){
                    //记录失败
                    LogUtil::writeLog($this->userInfo['userno'],"PUSH",'addNotice','记录消息失败');
                }

                //添加推送消息
                $res = $messageModel->addPushNotice($title,$content,$noticeId);
                if(!$res){
                    LogUtil::writeLog($this->userInfo['userno'],'PUSH','addPush','记录推送消息失败');
                }

                //更新用户的状态
                Db::table('user')->where(['userno'=>$this->userInfo['userno']])->setField('wyVip','1');
            }

            return AppResult::response200('请求成功', $data);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /*
     * 获取用户基本信息
     * @access public
     * @return mixed
     * */
    public function getUserInfo()
    {
        try {
            $userInfo = $this->userInfo;
            //获取用户编号
            $userno = $userInfo['userno'];

            //实例化对象
            $userModel = UserModel::getInstance($userno);

            //获取用户积分
            $data['userScore'] = $userModel->getUserScore();

            //获取用户星级
            $data['userLevel'] = $userModel->getUserStar();

            //用户今日是否已签到  0:未签到 1:已签到
            $data['isSignIn'] = $userModel->getUserSignIn();

            //获取用卡片数
            $card = $userModel->getCardNums();
            $data['payCount'] = $card['payCount'];  //信用卡张数
            $data['debitCount'] = $card['debitCount'];//结算卡张数

            //优惠券数量
            $coupon = Db::table('score_goods_log')->where(['userno'=>$userno,'state'=>0])->count();
            $data['coupon'] = empty($coupon) ? 0 : $coupon;

            //vip费率
            $data['vip'] = '尊享费率0.52%+2元';

            //用户是否是vip
            $data['is_vip'] = $this->userInfo['quota'] > 0 ? 1 : 0;

            return AppResult::response200('获取成功', $data);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取配置的信息
     * 分享配置、客服配置
     */
    public function getConfigData()
    {
        try {

            //获取推荐好友参数
            $data['shareHtml'] = Config::get('shareHtml');
            $data['shareTitle'] = Config::get('shareTitle');
            $data['shareDesc'] = Config::get('shareDesc');
            $data['shareImgUrl'] = Config::get('shareImgUrl');

            //获取客服配置
            $data['xnId'] = Config::get('xnId');

            //购买vip地址
            $data['vipUrl'] = Config::get('vipUrl');

            //优惠券
            $data['coupon'] = Config::get('coupon');

            //积分
            $data['integer'] = Config::get('integer');

            //签到
            $data['signin'] = Config::get('signin');

            //无卡H5
            $data['cardPayH5'] = Config::get('cardPayH5');

            //是否过审
            $data['isCheck']    = AuthUtil::$appVersion ? 1 : 0;

            return AppResult::response200('获取成功', $data);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取帮你还参数
     * @return string
     */
    public function getBnhConfig(){
        try{
            //帮你还参数
            $data['bnhAppId'] = Config::get('bnh_appid');
            $data['bnhSignKey'] = Config::get('bnh_sign_key');

            $mark = UserModel::getInstance($this->userInfo['userno'])->checkUserForBnh();
            if($mark){
                $data['bbhMark'] = 'bbhsdk';
            }else{
                $data['bbhMark'] = '帮帮还需绑定设备并开通收单功能';
            }
            $data['userNo'] =   $this->userInfo['userno'];
            return AppResult::response200('success',$data);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 判断用户是否登录
     */
    public function judgeLogin()
    {
        try {
            if(!empty($this->userInfo)){
                $data['isLogin'] = 1;
            }else{
                $data['isLogin'] = 0;
            }
            return AppResult::response200('success', $data);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取类目
     * @return string
     */
    public function getCategory()
    {
        try {
            $level_1 = Db::table('level_1')->select();
            $level_2 = Db::table('level_2')->field('id,name,level_1')->select();

            $return = array();
            foreach ($level_1 as $key => $value) {
                foreach ($level_2 as $k => $v) {
                    if ($value['id'] == $v['level_1']) {
                        $return[$key]['name'] = $value['name'];
                        $tmpList['name'] = $v['name'];
                        $tmpList['id'] = $v['id'];
                        $return[$key]['list'][] = $tmpList;
                    }
                }
            }
            return AppResult::response200('success', $return);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 上传商户信息
     * @return string
     */
    public function uploadCategoryInfo()
    {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'merchantName,merchantRegister,categoryId';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //查询类目
            $categoryList = Db::table('level_2')->where(['id' => $params['categoryId']])->find();
            if (empty($categoryList)) return AppResult::response101('类目id有误');

            //接收图片
            $storeImage = Common::uploadImg('storeImage');
            $storeBusinessImage = Common::uploadImg('storeBusinessImage');
            //压缩图片
            $storeImageUrl = Common::tochgimgrand($storeImage);
            $storeBusinessImgUrl = Common::tochgimgrand($storeBusinessImage);


            $data = array(
                'userno' => $this->userInfo['userno'],
                'merchantName' => $params['merchantName'],
                'merchantRegister' => $params['merchantRegister'],
                'category1' => $categoryList['level_1'],
                'category2' => $categoryList['id'],
                'ali_category' => $categoryList['ali_category'],
                'storeImageUrl' => $storeImageUrl,
                'storeBusinessImgUrl' => $storeBusinessImgUrl
            );

            //查询用户有没有用户信息
            $userInfo = Db::table('user_info')->where(['userno' => $this->userInfo['userno']])->find();
            if (empty($userInfo)) {
                //添加
                $res = Db::table('user_info')->insert($data);
            } else {
                //更新
                $res = Db::table('user_info')->where(['userno' => $this->userInfo['userno']])->update($data);
            }

            if ($res) return AppResult::response200('success');
            else return AppResult::response101('商户信息记录失败，请重试！');

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取用户类目信息
     * @return string
     */
    public function getUserCategoryInfo(){
        try{
            $field = "a.merchantName,a.merchantRegister,a.category1,a.category2,a.storeImageUrl,a.storeBusinessImgUrl,b.name as name1,c.name as name2";
            $info = Db::table('user_info')
                    ->alias('a')
                    ->join('level_1 b','a.category1 = b.id')
                    ->join('level_2 c','a.category2 = c.id')
                    ->where(['a.userno'=>$this->userInfo['userno']])->field($field)->find();
            if(empty($info)) return AppResult::response200('没有查询到信息');

            return AppResult::response200('success',$info);

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * 获取购买vip
     * @return string
     */
    public function buyVip()
    {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数 vip套餐id
            $str = 'id';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //卡宝VIP用户不能购买VIP
            if($this->userInfo['fee_wk'] == '0.4' && $this->userInfo['quota'] != '0.00'){
                return AppResult::response101('当前扣率VIP未到期');
            }

            //查询套餐信息
            $allocation = Db::table('allocation')->where(['id' => $params['id'], 'zfpb' => 0])->find();
            if (empty($allocation)) return AppResult::response101('预支付套餐失效');

            //判断该用户存在可用红包或抵用券--20180724
            $chooseHandle = $this->chooseHandle($this->userInfo['userno']);

            if ($chooseHandle['mark'] == 0) {
                $orderNo                = Common::getOrderNo('W');
                $data['body']           = '51收款宝VIP(198套餐)';
                $data['total_fee']      = $allocation['price'] * 100; //订单总金额，单位为分
                $allocation['discount'] = 0;
            } else {
                $data['body']           = "（抵扣" . intval($chooseHandle['amount']) . '元）51收款宝VIP(198套餐)';
                $data['total_fee']      = round($allocation['price'] - $chooseHandle['amount'], 2) * 100;
                $orderNo                = $chooseHandle['orderMark'].Common::getOrderNo('AW');
                $allocation['discount'] = $chooseHandle['amount'];
                $allocation['sourceId'] = $chooseHandle['id'];
            }

            //实例化WeChat
            $weChatModel = new WeChat($this->param['app_channel']);

            //微信下单业务参数
            $data['nonce_str']          = time() . rand(1000000, 9999999);   //随机字符串，不长于32位
            $data['out_trade_no']       = $orderNo;
            $data['spbill_create_ip']   = $this->userInfo['ip'];
            $data['notify_url']         = $weChatModel->buyVipNotify;
            $data['trade_type']         = 'APP';


            $result = $weChatModel->getWeChatPreOrder($data);
            LogUtil::writeLog($result, self::LOG_MODULE, __FUNCTION__, '微信预下单返回');

            //处理返回信息
            if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
                $returnArr['appid']         = $weChatModel->appId;
                $returnArr['partnerid']     = $weChatModel->mch_id;
                $returnArr['prepayid']      = $result['prepay_id'];
                $returnArr['package']       = 'Sign=WXPay';
                $returnArr['timestamp']     = time();
                $returnArr['noncestr']      = time() . rand(1000000, 9999999);
                $returnArr['sign']          = $weChatModel->getSign($returnArr);
                $returnArr['packageValue']  = $returnArr['package'];
                unset($returnArr['package']);
                $res = $this->addVipBill($orderNo, $allocation);
                if (!$res) return AppResult::response101('添加流水失败，请联系客服');
                return AppResult::response200('下单成功', $returnArr);
            } else if ($result['return_code'] == 'SUCCESS' && $result['result_code'] != 'SUCCESS') {
                return AppResult::response101($result['err_code_des']);
            } else {
                return AppResult::response101($result['return_msg']);
            }

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    //红包or抵用券使用判别
    private function chooseHandle($userno)
    {
        $award = Db::table('user_encourage')->where(array('userno'=>$userno, 'status'=>0))->find();
        $date = date('Y-m-d').' 23:59:59';
        $goods = Db::query("SELECT g.num,l.id FROM score_goods g, score_goods_log l WHERE l.userno = '".$userno."' AND g.id = l.gid AND l.state = 0 AND g.categoryId = 1 AND l.invalidTime >= '".$date."' ORDER BY g.num desc LIMIT 1");
        if(empty($award) && empty($goods[0]['num'])) $arr['mark'] = 0;
        if(!empty($award) && empty($goods[0]['num'])){
            if(time () < strtotime(Config::get('vip_activity_time'))){
                $arr = array('id'=>$award['id'],'amount'=>$award['amount'],'orderMark'=>'A','mark'=>2);
            }else{
                $arr['mark'] = 0;
            }
        }
        if(empty($award) && !empty($goods[0]['num'])) {
            $arr = array('id'=>$goods[0]['id'],'amount'=>$goods[0]['num'],'orderMark'=>'B','mark'=>1);
        }
        if(!empty($award) && !empty($goods[0]['num'])){
            if($award['amount'] <= $goods[0]['num']){
                $arr = array('id'=>$goods[0]['id'],'amount'=>$goods[0]['num'],'orderMark'=>'B','mark'=>1);
            }else{
                if(time () < strtotime(Config::get('vip_activity_time'))){
                    $arr = array('id'=>$award['id'],'amount'=>$award['amount'],'orderMark'=>'A','mark'=>2);
                }else{
                    $arr = array('id'=>$goods[0]['id'],'amount'=>$goods[0]['num'],'orderMark'=>'B','mark'=>1);
                }
            }

        }
        LogUtil::writeLog($arr,self::LOG_MODULE,__FUNCTION__,'抵扣判别');
        return $arr;
    }


    /**
     * 生成购买vip流水
     * @param $orderNo
     * @param $allocation
     * @return bool
     */
    private function addVipBill($orderNo, $allocation)
    {
        $data = array(
            'userno'        => $this->userInfo['userno'],
            'out_trade_no'  => $orderNo,
            'Gshop'         => $this->userInfo['byshopno'],
            'payid'         => $allocation['id'],
            'price'         => $allocation['price'],
            'discount'      => $allocation['discount'],
            'fee'           => $allocation['fee'],
            'fee_wk'        => $allocation['fee_wk'],
            'quota'         => $allocation['quota'],
            'status'        => 0
        );
        if(!empty($allocation['sourceId'])) $data['sourceId'] = $allocation['sourceId'];
        $res = Db::table('allocation_bill')->insert($data);
        if($res) return true;
        else return false;
    }
}