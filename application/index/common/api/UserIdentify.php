<?php
namespace app\index\common\api;

/*用户身份认证，绑定设备模块*/
use app\index\common\model\PayModel;
use app\index\common\model\Shop;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

class UserIdentify implements CommonApi {

    //日志模块名
    const LOG_MODULE = 'UserIdentify';

    //用户数据
    private $userInfo = null;

    private $identifyValue = '0.6';

    public $param;

    /**
     * UserIdentify constructor.
     */
    public function init() {
        //根据token获取user信息
        if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
        else $this->userInfo = Db::table('user')->where('lkey',$this->param['lkey'])->find();
    }


    /**
     * 获取活体认证识别系数
     * @return string
     */
    public function getIdentifyValue(){
        $res = array(
            'identifyValue' => $this->identifyValue
        );
        return AppResult::response200('success',$res);
    }

    /*
     * 用户信息上传
     * @access public
     * @return mixed
     * */
    public function identityCardImg() {
        try {
            //检查必传参数
            $str = 'idCardNo,name,address,validity,cardFrontUrl,cardBackUrl,bodyUrl,type';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //判断用户年龄
            $year = substr($this->param['idCardNo'],6,4);
            $day = substr($this->param['idCardNo'],10,4);

            if(($year + 18) . $day > date('Ymd')){
                return AppResult::response101('暂不支持18岁及以下用户使用!');
            }
            if(($year + 60) . $day <= date('Ymd')){
                return AppResult::response101('暂不支持60岁及以上用户使用！');
            }

            //检验3个图片链接的有效性
//            $image1 = Common::checkImage($this->param['cardFrontUrl']);
//            $image2 = Common::checkImage($this->param['cardBackUrl']);
//            $image3 = Common::checkImage($this->param['bodyUrl']);
//            if(!($image1 && $image2 && $image3)){
//                return AppResult::response101('图片链接有误');
//            }

            //下载压缩身份证正面图片
            $cardFrontUrl = Common::getImage($this->param['cardFrontUrl'],'cardFront');
            LogUtil::writeLog('下载身份证正面照完成'.$cardFrontUrl,'Image','getImage','下载图片');
            $frontImage = Common::tochgimgrand($cardFrontUrl);
            LogUtil::writeLog('压缩身份证正面照完成'.$frontImage,'Image','getImage','压缩');

            //下载压缩身份证反面图片
            $cardBackUrl = Common::getImage($this->param['cardBackUrl'],'cardBack');
            $backImage = Common::tochgimgrand($cardBackUrl);

            //下载压缩活体图片
            $bodyImageUrl = Common::getImage($this->param['bodyUrl'],'bodyImage');
            $bodyImage = Common::tochgimgrand($bodyImageUrl);

            //是否转人工
            $status = $this->param['type'] == '1' ? 3 : 1;
            //更新用户表信息
            $user = array(
                'name'          =>  $this->param['name'],       //身份证名称
                'idber'         =>  $this->param['idCardNo'],   //身份证号码
                'address'       =>  $this->param['address'],    //身份证地址
                'idno_date'     =>  $this->param['validity'],   //身份证有效期
                'idno'          =>  $frontImage,                //身份证正面照
                'idno_backimg'  =>  $backImage,                 //身份证反面照
                'image_content' =>  $bodyImage,                 //活体照片
                'status'        =>  $status,                    //认证状态
                'rtime'         =>  time(),                     //审核时间
                'btime'         =>  time(),                     //绑定时间
            );

            $res1 = Db::table('user')->where(['userno'=>$userNo])->update($user);
            if(empty($res1)) return AppResult::response101('信息上传失败，请重试');

            return AppResult::response200('上传成功');
        } catch (Exception $e) {
            if ($e->getCode() == '999') {   //图片上传失败
                LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '图片上传失败');
                return AppResult::response101($e->getMessage());
            }
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 重新提交认证
     * @return string
     */
    public function reIdentify(){
        try{
            //更新用户认证状态
            $map['userno'] = $this->userInfo['userno'];
            $map['status'] = 2;

            Db::table('user')->where($map)->update(['status'=>0]);
            return AppResult::response200('success');
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 绑定设备
     */
    public function bindPos(){
        try{
            //检查必传参数
            $str = 'posno,postype';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //接收到的设备号
            $posno = $this->param['posno'];

            //查询代理商集合
            $shopModel = new Shop();
            $shopList = $shopModel->getShopList();
            //查询map数组
            $posMap['posno'] = $posno;
            $posMap['left(shopno,8)'] = array('in',$shopList);
            //查询结果
            $pos = Db::table('user_pos')->where($posMap)->find();
            if(empty($pos)) return AppResult::response101('机构号不存在或该终端序列号未被录入');

            //判断设备的状态
            if($pos['status'] == '1' && $pos['userno'] != $this->userInfo['userno']) return AppResult::response101('绑定失败，该终端已被其他会员绑定');

            //判断设备是否可转移
            if($this->userInfo['transferMark'] != 1){
                if($this->userInfo['byshopno'] && $pos['shopno'] != $this->userInfo['byshopno']) return AppResult::response101('该卡头不属于该商户');
            }

            //判断商户号是否存在
            $ShopData = Db::table('shop')->where(array('shopno'=>strval($pos['shopno']),'rank'=>array('neq',1)))->find();
            if(empty($ShopData)) return AppResult::response101('该商户号不存在');

            //绑定商户
            if($this->userInfo['transferMark'] == 1){
                Db::table('user')->where(array('userno'=>$this->userInfo['userno']))->update(array('byshopno'=>strval($ShopData['shopno']),'byshop'=>$ShopData['mobile'],'byappkey'=>$ShopData['appkey'],'transferMark'=>'0'));
            }else{
                Db::table('user')->where(array('userno'=>$this->userInfo['userno']))->update(array('byshopno'=>strval($ShopData['shopno']),'byshop'=>$ShopData['mobile'],'byappkey'=>$ShopData['appkey'],'ite'=>'0'));
            }

            //开启事务
            Db::startTrans();
            try{
                //解绑设备
                Db::table('user_pos')->where(array('userno'=>$this->userInfo['userno']))->update(array('status'=>0,'uname'=>'','userno'=>''));
                //绑定设备
                Db::table('user_pos')->where(array("posno"=>$posno))->update(array("userno"=>$this->userInfo['userno'],"uname"=>$this->userInfo['name'],"status"=>'1',"ktime"=>time()));
                //更新设备类型
                Db::table('user')->where(['userno'=>$this->userInfo['userno']])->update(array('pos'=>$this->param['postype']));
                //提交事务
                Db::commit();
            }catch (Exception $e){
                // 回滚事务
                Db::rollback();
                LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '绑定设备失败原因');
            }

            //BOSS开通产品
            //业务开通判断
            $payModel = new PayModel();
            $userNo = $this->userInfo['userno'];
            if ($this->userInfo['bosssta'] != 3 || !$payModel->checkProduct($userNo)) {
                //异步boss注册
                //异步执行 通道注册、绑卡、产品开通
                LogUtil::writeLog(['msg' => '开始异步请求BOSS', 'url' => Config::get('BOSS_DEAL_URL') . '?userNo=' . $userNo], self::LOG_MODULE, __FUNCTION__, 'BOSS请求');
                async_curl(Config::get('BOSS_DEAL_URL') . '?userNo=' . $userNo);
                LogUtil::writeLog(['msg' => '已完成异步请求BOSS', 'url' => Config::get('BOSS_DEAL_URL') . '?userNo=' . $userNo], self::LOG_MODULE, __FUNCTION__, 'BOSS请求');
            }

            return AppResult::response200('绑定成功');
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('添加失败，请重试');
        }
    }

    /**
     * 获取默认设备列表
     * @return string
     */
    public function getDefaultPosList(){
        try{
            $url = Config::get('APP_IMAGE_URL');
            $data['0']['type'] = 'B';
            $data['0']['name'] = 'ME30蓝牙MPOS';
            $data['0']['img'] =  $url.'ME15.png';
            $data['0']['open'] = '1';
            $data['1']['type'] = 'O';
            $data['1']['name'] = '大趋智能';
            $data['1']['img'] =  $url.'dqz.png';
            $data['1']['open'] = '1';
            return AppResult::response200('获取成功',$data);
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('添加失败，请重试');
        }
    }

}