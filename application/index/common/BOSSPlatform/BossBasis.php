<?php
namespace app\index\common\BOSSPlatform;

use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

abstract class BossBasis {
    const LOG_MODULE = 'BossApi';
    public $logFileName = '';

//    protected $appId        = 'F9A5E94FA17652BF437F9579230F3F62';           //生产
//    protected $partnerCode  = 'P606618031900000001';                        //生产
//    private   $memberNo     = '';
//    private   $httpUrl      = 'https://openhome.zhongmakj.com/gateway.do';  //生产
//    private   $imgURl       = 'https://openhome.zhongmakj.com/file/sfs/upload'; //图片上传接口地址 生产

    private  $appId            ;
    private  $partnerCode      ;
    private  $customerOutNo    ;
    private  $httpUrl          ;
    private  $imgUrl           ;

    /**
     * 日志名传入
     * BossBasis constructor.
     * @param $logFileName
     */
    public function __construct($logFileName) {
        $this->logFileName = $logFileName;

        //相关参数赋值
        $this->appId         =     Config::get('appId');
        $this->partnerCode   =     Config::get('partnerCode');
        $this->customerOutNo =     Config::get('customerOutNo');
        $this->httpUrl       =     Config::get('httpUrl');
        $this->imgUrl        =     Config::get('imgUrl');
    }

    /**
     * 图片上传
     * @static
     * @param  $imgUrl
     * @return mixed|string
     * @throws Exception
     */
    protected function fileUpload($imgUrl) {
        try {
            //非加密
            $arr['fileContent'] = base64_encode(file_get_contents($imgUrl));
            $url = $this->imgUrl;
            $json = json_encode($arr);
            $imgdata = json_decode($this->post_curl_json($json, $url ,30), true);
            return $imgdata;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * 用户注册
     * @param $mdata
     * @return mixed
     * @throws Exception
     */
    protected function mRegister($mdata) {
        try{
            $cifCustomer = array(
                'appId'                 => $this->appId,
                'customerAddress'       => $mdata['address'],
                'customerEmail'         => '',
                'customerIdentityNo'    => $mdata['idCardNo'],
                'customerIdentityType'  => '1',
                'customerMobile'        => $mdata['mobile'],
                'customerName'          => $mdata['name'],
                'customerOutNo'         => $mdata['userNo'],
                'customerZipCode'       => '',
                'partnerCode'           => $this->partnerCode,
            );
            $arr['cifCustomer'] = $cifCustomer;
            $cifCerts[0] = array(
                'certType'  => '1',
                'fileDesc'  => '身份证正面照片',
                'fileNo'    => $mdata['imgid1'],
            );
            $cifCerts[1] = array(
                'certType'  => '2',
                'fileDesc'  => '身份证反面照片',
                'fileNo'    => $mdata['imgid2'],
            );
            $cifCerts[2] = array(
                'certType'  => '4',
                'fileDesc'  => '结算卡正面照片',
                'fileNo'    => $mdata['imgid3'],
            );
            $cifCerts[3] = array(
                'certType'  => '5',
                'fileDesc'  => '手持身份证照片',
                'fileNo'    => $mdata['imgid4'],
            );
            $arr['cifCerts'] = $cifCerts;
            $arr['method'] = 'zm.customer.valid.create';
            return $this->reuseCode($arr);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 绑定结算卡
     * @param $arr
     * @return mixed|string
     * @throws Exception
     */
    protected function setCard($arr) {
        try{
            $data = array(
                'cardBankName'=> $arr['bankName'],  //总行名称
                'cardBankNo'=> $arr['branchNo'],    //支行联行号
                'cardCertNo'=> $arr['idCardNo'],       //持卡人身份证号
                'cardMobile'=> $arr['mobile'],      //银行预留手机号
                'cardRealName'=> $arr['name'],      //持卡人姓名
                'cardNumber'=> $arr['cardNo'],      //银行卡号
                'customerOutNo'=> $arr['userNo'],   //外部客户号
                'cardUse'=> '2',                    //固定值: 2(主结算银行卡)
                'method'=> 'zm.customer.bank.create',
                'cardBranchBankName'=> $arr['branchName'],//支行名称
            );
            return $this->reuseCode($data);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }


    /**
     * 业务开通
     * @param  $userNo
     * @return mixed|string
     * @throws Exception
     */
    protected function productCreate($userNo) {
        try{
            // $where['user.bosssta'] = 2;
            $where['user.userno'] = $userNo;
            $where['section.type'] = array('in','5,15,16,17');
            $field = 'user.id,user.userno,section.start,section.end,section.dvalue,section.value,section.type';
            $mlist = Db::table('user')->join("section","section.shopno = left(user.byshopno,8)")->where($where)->field($field)->order('user.id asc')->select();
//            $posno = Db::table('user_pos')->where(array('userno'=>$userNo))->column('posno');

            //查询51卡宝vip用户
            $userInfo = Db::table('user')->where(['userno'=>$userNo])->field('quota,fee_wk')->find();

            if($userInfo['quota'] > 0 && $userInfo['fee_wk'] == '0.4'){
                $arr = array();
                foreach ($mlist as $v) {
                    $productEntities['productStatus'] = '1';
                    $productEntities['customerEndAmt'] = $v['end'];
                    $productEntities['customerStartAmt'] = $v['start'];
                    $productEntities['productRate'] = $v['value'];
                    if($v['type'] == '5'){
                        if($v['dvalue'] < 2){
                            $v['dvalue'] = 2;
                        }
                        $productEntities['productFixrate'] = $v['dvalue'];
                        $productEntities['productCode'] = '124000';//拉卡拉
                        $arr['productEntities'][] = $productEntities;//点指无卡
                    }
                    if($v['type'] == '16'){
                        $productEntities['productRate'] = '0.4';
                        $productEntities['productFixrate'] = '0';
                        $productEntities['productCode'] = '323000';
                        $arr['productEntities'][] = $productEntities;
                    }
                    if($v['type'] == '15'){
                        $productEntities['productRate'] = '0.3';
                        $productEntities['productFixrate'] = '0';
                        $productEntities['productCode'] = '122000';
                        $arr['productEntities'][] = $productEntities;//微信
                    }
                    if($v['type'] == '17'){
                        $productEntities['productRate'] = '0.3';
                        $productEntities['productFixrate'] = '0';
                        $productEntities['productCode'] = '121000';
                        $arr['productEntities'][] = $productEntities;//支付宝
                    }
                }
            }else{
                $arr = array();
                foreach ($mlist as $v) {
                    $productEntities['productStatus'] = '1';
                    $productEntities['customerEndAmt'] = $v['end'];
                    $productEntities['customerStartAmt'] = $v['start'];
                    $productEntities['productRate'] = $v['value'];
                    if($v['type'] == '5'){
                        if($v['dvalue'] < 2){
                            $v['dvalue'] = 2;
                        }
                        $productEntities['productFixrate'] = $v['dvalue'];
                        $productEntities['productCode'] = '124000';//拉卡拉
                        $arr['productEntities'][] = $productEntities;//点指无卡
                    }
                    if($v['type'] == '16'){
                        $productEntities['productFixrate'] = $v['dvalue'];
                        $productEntities['productCode'] = '323000';
                        $arr['productEntities'][] = $productEntities;
                    }
                    if($v['type'] == '15'){
                        $productEntities['productFixrate'] = $v['dvalue'];
                        $productEntities['productCode'] = '122000';
                        $arr['productEntities'][] = $productEntities;//微信
                    }
                    if($v['type'] == '17'){
                        $productEntities['productFixrate'] = $v['dvalue'];
                        $productEntities['productCode'] = '121000';
                        $arr['productEntities'][] = $productEntities;//支付宝
                    }
                }

            }

            $arr['customerOutNo'] = $userNo;

            $arr['method'] = 'zm.customer.product.create';

            $arr['notifyUrl'] = Config::get('boss_notify');

            //更新用户状态
            Db::table('user')->where(array('userno'=>$userNo))->update(array('boosRespDesc'=>'开通中','boosRespCode'=>'01'));

            return $this->reuseCode($arr);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }


     /**
     * 发起通道请求
     * @param $arr
     * @return string
     * @throws Exception
     */
    protected function reuseCode($arr , $time = 60) {
        try{
            header('Content-type:text/html;charset=utf-8');
            $publicKey = file_get_contents(dirname(__FILE__).'/boss_publickey.pem');//读取公钥
            $privateKey = file_get_contents(dirname(__FILE__).'/boss_privatekey.pem');//读取私钥
            //组装参数
            $data = $arr;

            $data['version'] = '1.0';
            $data['timestamp'] = time().'000';

            LogUtil::writeLog($data, self::LOG_MODULE, $this->logFileName, '请求发送报文');
            //数组转JSON转base64_encode
            $base64_encode = base64_encode(json_encode($data,true));
            //公共参数
            $res['appId'] = $this->appId;
            $res['enctypeData'] = $this->encrypt($base64_encode,$publicKey);//公钥数据加密
            $res['partnerCode'] = $this->partnerCode;
            $res['signature'] = $this->signRsa($base64_encode,$privateKey); //私钥数据签名
            $ressult = $this->post_curl_json(json_encode($res), $this->httpUrl ,$time);   //发起请求
            LogUtil::writeLog($ressult, self::LOG_MODULE, $this->logFileName, '接收返回报文');
            return json_decode($ressult, true);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * CURL通过POST发送JSON数据
     * @param $json
     * @param $url
     * @return string
     */
    private function post_curl_json($json, $url , $time) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($json))
        );
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $time * 1000);
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
    //公钥加密
    private function encrypt($originalData,$publicKey) {
        $crypto = '';
        $chunk = '';
        foreach (str_split($originalData, 245) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $publicKey);
            $crypto .= $encryptData;
        }
        return base64_encode($crypto);
    }
    //私钥签名
    private function signRsa($data,$privatekeyFile) {
        $private_key=openssl_pkey_get_private($privatekeyFile);
        openssl_sign($data,$sign,$private_key,OPENSSL_ALGO_SHA1);
        openssl_free_key($private_key);
        return base64_encode($sign);//最终的签名　　　　
    }
    //私钥解密
    private function decrypt($encryptData,$privateKey){
        $crypto = '';
        $chunk = '';
        foreach (str_split(base64_decode($encryptData), 256) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $privateKey);
            $crypto .= $decryptData;
        }
        return $crypto;
    }
    //异步回调信息解密
    public function notifyMsgDecrypt($encryptData){
        $privateKey = file_get_contents(dirname(__FILE__).'/boss_privatekey.pem');//读取私钥
        return $this->decrypt($encryptData, $privateKey);
    }
}