<?php
namespace app\index\controller;

use app\index\common\util\AppResult;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;

class Bnh{
    private $log_model = 'Bnh';

    private $method = 'bms.account.card.create';
    private $signKey = 'C734D98B9AEC077D37A372AC61717474';
//    private $url = 'api.51bnh.com/gateway';
    private $url = 'pre.51bnh.com/gateway';
    private $appId = '610312644DB3F1745B998FE0FFDA4F58';
    private $type = '18';


    public function __construct()
    {
        register_shutdown_function(array($this,'replay'));
    }

    public function replay(){
        LogUtil::close();
    }

    public function getUserInfo(Request $request){

        try{
            header("Content-Type:application/json");
            //获取用户编号
            $token = $request->post('token');
            LogUtil::writeLog($request->post(),$this->log_model,__FUNCTION__,'获取上传参数');
            if(empty($token)) return AppResult::response101('缺少参数token');

            //获取用户信息
            $field = 'name,userno,mobile,idber,idno,idno_backimg';
            $userList = Db::table('user')->where(['lkey'=>$token])->field($field)->find();
            LogUtil::writeLog($userList,$this->log_model,__FUNCTION__,'用户信息');
            if(empty($userList)) LogUtil::writeLog(Db::table('user')->getLastSql(),$this->log_model,__FUNCTION__,'获取用户信息失败sql');

            //获取用户卡信息
            $cardList = Db::table('user_card')->field("cardno as cardNo,mobile as cardMobile")->where(['userno'=>$userList['userno'],'status'=>2])->select();
            if(empty($cardList)) LogUtil::writeLog(Db::table('user_card')->getLastSql(),$this->log_model,__FUNCTION__,'获取卡列表失败sql');
            //封装返回数据
            $return['cifAccount']['accountNo'] = $userList['userno'];
            $return['cifAccount']['accountName'] = $userList['name'];
            $return['cifAccount']['accountMobile'] = $userList['mobile'];
            $return['cifAccount']['customerNo'] = $userList['idber'];
            $return['cifAccount']['img1'] = $userList['idno'];
            $return['cifAccount']['img2'] = $userList['idno_backimg'];
            $return['cifCards'] = $cardList;

            LogUtil::writeLog($return,$this->log_model,__FUNCTION__,'返回结果信息');
            $data = json_encode($return);
            exit($data);
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),$this->log_model,__FUNCTION__,'系统错误');
        }
    }


    /**
     * 同步帮你还信用卡
     * @param $arr
     */
    public function uploadUserCard($arr){
        $data['appid']      = Config::get('bnh_appid');
        $data['method']     = $this->method;
        $data['version']    = '1.0';
        $data['token']      = $arr['token'];
        $data['accountNo']  = $arr['accountNo'];
        $data['cardNo']     = $arr['cardNo'];
        $data['cardName']   = $arr['cardName'];
        $data['cardMobile'] = $arr['cardMobile'];
        $data['bankName']   = $arr['bankName'];
        //获取签名
        $data = $this->getSign($data);
        LogUtil::writeLog($data,$this->log_model,__FUNCTION__,'请求帮你还参数');

        $res = $this->post_curl($data);
        LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'帮你还返回结果');
        LogUtil::close();
    }

    /**
     * 接收订单信息
     * @param Request $request
     * @return string
     */
    public function uploadOrderInfo(Request $request){
       try{
           register_shutdown_function(array($this, 'replay'));
           $data = $request->post();
           LogUtil::writeLog($data,$this->log_model,__FUNCTION__,'接收参数');

           if(empty($data['orderDetailNo'])) return AppResult::response101('子订单编号不存在');
           //查询用户信息
           $userInfo = $this->getUser($data['accountNo']);
           if(empty($userInfo)) return AppResult::response101('用户身份不存在');
           $detailNo = Db::table('bill_bnh')->where(array('detailNo'=>$data['orderDetailNo']))->find();
           if($detailNo){
               echo 'success';
               exit();
           }
           //插入子表数据
           $insert['userno'] = $data['accountNo'];
           $insert['tabno'] = $data['orderNo'];
           $insert['detailNo'] = $data['orderDetailNo'];
           $insert['orderAmt'] = $data['orderAmt'];
           $insert['amount'] = $data['orderDetailAmt'];
           $insert['cardNo'] = $data['cardNo'];
           $insert['isEnd'] = $data['isEnd'];
           if(empty($data['orderDetailTime'])){
               $insert['create_date'] = Common::getDate();
           }else{
               $insert['create_date'] = $data['orderDetailTime'];
           }

           $id = Db::table('bill_bnh')->insert($insert);
           LogUtil::writeLog($insert,$this->log_model,__FUNCTION__,'添加子表数据');
           if(empty($id)){
               LogUtil::writeLog(Db::table('bill_bnh')->getLastSql(),$this->log_model,__FUNCTION__,'失败记录');
           }
           $amount = $data['orderDetailAmt'];
           $map['shopno'] = substr($userInfo['byshopno'], 0,8);
           $map['type'] = $this->type;
           $ret = Db::table('section')->where($map)->find();
           //手续费计算
           $ret['billitype'] == 0 ? $Ramount = $amount*$ret['value']/100 : $Ramount = $ret['value'];
           //查询bill_biz表
           $bill_biz = Db::table('bill_biz')->where(array('typename' => '帮你还'))->find();
           $bill['userno'] = $userInfo['userno'];
           $bill['shopno'] = $userInfo['byshopno'];
           $bill['shopname'] = $this->getShopName($userInfo['byshopno']);
           $bill['billno'] = Common::getOrderNo('BNH');
           $bill['tabno'] = $data['orderDetailNo'];
           $bill['rechno'] = '0000';//银行返回码，接口返回
           $bill['type'] = $bill_biz['bill_type']; //流水类型
           $bill['typename'] = $bill_biz['typename'];
           $bill['ctime'] = time();
           $bill['cardnos'] = $data['cardNo'];
           $bill['cardno'] = $data['cardNo'];//卡号
           $bill['amount'] = $amount;
           $bill['rate'] = $Ramount;
           $bill['drate'] = $ret['dvalue'];
           $bill['sta'] = 0;
           $bill['code'] = '00';
           $bill['backdata'] = 'All_Amount-'.$amount;
           $bill['fee'] = $ret['value'];
           $deducted = Common::deducted($bill['userno'],$bill['shopno'],$bill['amount'],'帮你还');
           $bill['deducted'] = $deducted;
           Db::table('bill')->insert($bill);
           echo 'success';
           exit();
       }catch (Exception $e){
           LogUtil::writeLog($e->getMessage(),$this->log_model,__FUNCTION__,'系统错误');
           LogUtil::close();
       }
    }

    //获取商户名称
    public function getShopName($byshopno){
        $map = array('shopno'=>$byshopno);
        $name = Db::table('shop')->where($map)->column('name');
        return $name[0];
    }

    public function getUser($userno)
    {
        $list = Db::table('user')->where(array('userno'=>$userno))->find();
        return $list;
    }
    /**
     * 获取签名
     */
    private function getSign($data){
        ksort($data);
        $str = '';
        foreach ($data as $key => $value){
            $str .= $key .'='.$value.'&';
        }
        $data['sign'] = strtoupper(md5($str.'signKey='.$this->signKey));
        return $data;
    }

    /**
     * 发送数据
     * @param $data
     * @return string
     */
    private function post_curl($data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $res = array($return_code, $return_content);
        $des = json_decode($res['1'],true);
        if(empty($res) || empty($des['respCode'])) {
            return json_encode(array('respCode'=>'404','respDesc'=>'请求渠道异常！'), JSON_UNESCAPED_UNICODE);
        }else{
            return $res['1'];
        }
    }


}