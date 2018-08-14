<?php
namespace app\index\common\api;

use app\index\common\model\CardModel;
use app\index\common\model\PayModel;
use app\index\common\model\Shop;
use app\index\common\model\UserModel;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;


class Home implements CommonApi{

    //日志模块名
    const LOG_MODULE = 'Home';

    public $param;
    private $vipMark = false;

    private $userInfo = null;
    private $shopNo = '10011002';

    /**
     * 初始化构造
     * @return string
     */
    public function init()
    {
        if(isset($this->param['lkey']) && !empty($this->param['lkey'])){
            if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
            else $this->userInfo = Db::table('user')->where('lkey', $this->param['lkey'])->find();
            if(!empty($this->userInfo)) $this->shopNo = substr($this->userInfo['byshopno'],0,8);
        }
    }

    /**
     * 记录卡片点击
     * @return string
     */
    public function recordCardLog(){
        try{
            if($this->param['system'] == 'IOS'){
                $add['system'] = 0;
            }else{
                $add['system'] = 1;
            }
            $add['ip']     = $_SERVER['REMOTE_ADDR'];
            $add['date']   = Common::getDate();
            $add['cardId'] = $this->param['cardId'];
            Db::table('user_card_log')->insert($add);
            return AppResult::response200('success');
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取有没有未读消息
     * @return string
     */
    public function getNoticeStatus(){
        try{
            $mark = true;
            //查询该用户有没有消息
            $map['tno']     =   $this->userInfo['userno'];
            $map['flag']    =   0;
            $map['status']  =   0;
            $list = Db::table('notice')->where($map)->count();
            $list > 0 ? $return['isNotice'] = 1 : $return['isNotice'] = 0;
            $return['jumpUrl'] = 'http://www.baidu.com';
            $return['isWeb'] = $mark ? 0 : 1; //是否跳转H5 1：跳转 0不跳转

            return AppResult::response200('success',$return);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    //获取交易类型
    public function getPayType(){
        try{
            //获取当前代理商扣率
            $shopModel = new Shop();
            $shopFeeList = $shopModel->getShopFee($this->shopNo);

            //数据扣率数据模型
            $typeModel = $shopModel->getTypeModel();

            //51卡宝vip
            $vipArray = array('15'=>'0.3','16'=>'0.55','17'=>'0.3','5'=>'0.6');
            if($this->userInfo['quota'] > 0 && $this->userInfo['fee_wk'] == '0.4') $this->vipMark = true;
            //封装返回交易数据类型
            $list = array();
            $length = count($shopFeeList);
            foreach($shopFeeList as $key => $value){
                $tem_key = $typeModel[$value['type']];
                $list[$tem_key]['open']     = $value['open'];             //展示开关 0：展示 1：关闭
                $list[$tem_key]['start']    = (int)$value['start'];       //交易最低金额
                $list[$tem_key]['end']      = (int)$value['end'];         //交易金额上限
                $list[$tem_key]['fee']      = $value['value'];            //扣率
                $list[$tem_key]['dvalue']   = (int)$value['dvalue'];      //代付手续费
                $list[$tem_key]['vipFee']   = $value['value'];            //vip扣率

                if($this->vipMark){
                    $list[$tem_key]['fee']    = $vipArray[$value['type']];
                    $list[$tem_key]['vipFee'] = $vipArray[$value['type']];

                }else{
                    if($value['type'] == '5'){
                        //即时到账
                        $list[$tem_key]['vipFee']   =   Config::get('posFeeVip');
                    }
                    if($value['type'] == '16'){
                        //无卡支付
                        $list[$tem_key]['vipFee']   =   Config::get('cardPayVip');
                        //检查用户的交易金额
                        if($this->userInfo['ctime'] >= strtotime("2018-07-28 00:00:00")){
                            $amount = UserModel::getInstance($this->userInfo['userno'])->checkUserAmount();
                            if($amount < 1000) $list[$tem_key]['open'] = 1;
                        }
                    }
                }
                if($value['type'] == '16'){
                    $list[$tem_key]['open'] = 1;
                }

                if($length-1 == $key && $this->param['system'] == 'Android'){
                    $list['nfc']['open']        =   0;
                    $list['nfc']['start']       =   $list['pos']['start'];
                    $list['nfc']['end']         =   $list['pos']['end'];
                    $list['nfc']['fee']         =   $list['pos']['fee'];
                    $list['nfc']['dvalue']      =   $list['pos']['dvalue'];
                    $list['nfc']['vipFee']      =   $list['pos']['vipFee'];
                }
            }
            if(empty($this->userInfo)){
                //用户未登陆
                $data['is_vip']  = 0;
                $data['quota']   = 0;
                $data['posType'] = '';
            }else{
                //用户登录
                if($this->vipMark){

                }
                $data['is_vip']  = $this->userInfo['quota']  > 0 ? 1 :0;
                if($this->userInfo['quota'] > 0 && $this->userInfo['fee_wk'] == '0.4') $data['is_kb_vip'] = 1;
                else $data['is_kb_vip'] = 0;
                $data['quota']   = $this->userInfo['quota']  > 0 ? $this->userInfo['quota']  : 0;
                $data['posType'] = $this->userInfo['pos'];
                $data['isNfc']   = '1';
            }
            $data['list'] = $list;

            return AppResult::response200('success',$data);

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * 检验交易时间
     * @return string
     */
    public function checkPayDate()
    {
        $payModel = new PayModel();
        $res = $payModel->checkTime();
        //检查交易时间
        if (!$res) return AppResult::response101('不在服务时间段');
        else return AppResult::response200('success');
    }

    /**
     * 获取卡片列表
     */
    public function getCardList(){
        try{
            //过审版本
            if(AuthUtil::$appVersion){
                $return[0]['type'] = 2;
                $list[0]['cardId'] = '9999';
                $list[0]['title'] = '卡片';
                $list[0]['image'] = 'https://kb.skb.zhongmakj.com/Tpl/Public/image/appstorebanner1.png';
                $list[0]['link'] = 'https://tt.zm-skb.com/bannerLink/topic/topic.html';
                $return[0]['list'] = $list;
                $list1[0]['title'] = '卡片';
                $list[0]['cardId'] = '9998';
                $list1[0]['image'] = 'https://kb.skb.zhongmakj.com/Tpl/Public/image/appstorebanner2.png';
                $list1[0]['link'] = 'https://tt.zm-skb.com/bannerLink/article/article.html';
                $return[1]['type'] = 2;
                $return[1]['list'] = $list1;
            }else{
                //查询所有正常状态下的卡片列表
                $field = "a.sort,a.title,a.type,a.isMoreLink,b.contentTitle,b.contentDesc,b.tags,b.buttonContent,b.mixAmount,b.maxAmount,b.isRecommend,b.image,b.link,b.id as cardId";
                $cardList = Db::table('card_category')
                    ->alias('a')
                    ->join('card_config b','a.id=b.cardId')
                    ->where(array('a.status'=>'0','b.state'=>0))
                    ->field($field)
                    ->order('a.sort , b.id')
                    ->select();

                $cardModel = new CardModel();
                $return = array();
                foreach ($cardList as $key => $value){
                    //获取数据模型
                    $modelList = $cardModel->getCardTypeModel($value['type']);
                    $tmpList = array();
                    foreach ($modelList as $k => $v){
                        //筛选出对应type的数据
                        if ($value[$v] != ''){
                            $tmpList[$v] = $value[$v];
                        }
                    }
                    //查看更多跳转
                    if($value['isMoreLink'] != ''){
                        $return[$value['sort']]['isMoreLink'] = $value['isMoreLink'];
                    }
                    $return[$value['sort']]['list'][] = $tmpList;
                    $return[$value['sort']]['type'] = $value['type'];
                }
                $return = array_values($return);
            }
            return AppResult::response200('',$return);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取用户实名认证
     * @return string
     */
    public function getUserRealName(){
        try{
            if(isset($this->param['lkey'])){
                if(!empty($this->userInfo)){
                    $return['isRealName'] = $this->userInfo['status'] == 3 ? 1 : 0;
                }else{
                    $return['isRealName'] = 1;
                }
            }else{
                $return['isRealName'] = 1;
            }
            return AppResult::response200('success',$return);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取导航列表
     * @return string
     */
    public function getNavList(){
        try{
            if(AuthUtil::$appVersion){
                //审核中
                $data['topic']  = 'https://tt.zm-skb.com/bannerLink/topic/topic.html';//话题
                $data['article']= 'https://tt.zm-skb.com/bannerLink/article/article.html';//文章
            }else{
                $data['jiedai'] = 'https://tt.zm-skb.com/quick_get/product.html';    //借贷
                $data['banka']  = 'http://inter.zm-skb.com/wkBank/bank.html';       //办卡
            }
            return AppResult::response200('success',$data);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
}