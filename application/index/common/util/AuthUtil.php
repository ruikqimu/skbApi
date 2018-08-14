<?php
namespace app\index\common\util;

use think\Config;
use think\Db;

class AuthUtil {
    const SIGN_KEY = '9c21d929df788f4fa5eb6329a2a8a485';  //MD5加密字符串
    static $userInfo = null;
    static $appVersion = null;
    static $byte = 2048 / 8;
    /*
     * RSA公钥加密
     * @static
     * @access public
     * @param  $data    待加签明文字符串
     * @return String   密文
     * */
    public static function RSAEncode($data) {
        $pu_key = file_get_contents(dirname(__FILE__) . '/rsa/web_public.pem');
        $crypto = '';
        $chunk = '';
        foreach (str_split($data,self::$byte - 11) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $pu_key);
            $crypto .= $encryptData;
        }
        return base64_encode($crypto);
    }
    /*
     * RSA私钥解密
     * @static
     * @access public
     * @param  $data    报文密文
     * @return String   明文
     * */
    public static function RSADecode($data) {
        $private_key = file_get_contents(dirname(__FILE__).'/rsa/app_private.pem');
        $crypto = '';
        $chunk = '';
        foreach (str_split(base64_decode($data), self::$byte) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $private_key);
            $crypto .= $decryptData;
        }
        return $crypto;
    }
    /*
     * sign拼接
     * @static
     * @access public
     * @param  $data  数据组装数组
     * @return String
     * */
    public static function checkSign($data) {
        ksort($data);	//数组按key升序排序，即ASCII升序
        //数组拼接字符串
        $encodedStr = '';
        foreach ($data as $key => $value) {
            if ($key == 'sign') continue;
            $encodedStr .= "{$key}={$value}&";
        }
        $encodedStr .= "key=".self::SIGN_KEY;
        return strtolower(md5($encodedStr));
    }
    /*
     * TOKEN检测
     * @static
     * @access public
     * @param  $data
     * @return bool
     * */
    public static function  checkToken($data, $route) {
        //免TOKEN验证接口列表
        $whiteList = [
            'User'  => ['versionInfo'=>true],
	        'Login' => ['login'=>true,'getCode'=>true,'checkCode'=>true,'resetPassword'=>true,'getRegistCode'=>true,'regist'=>true,'getDefaultConfig'=>true],
            'Home'  => ['recordCardLog'=>true,'getNavList'=>true],
            'Pay'   => ['getAdvList'=>true],
        ];
        if (!empty($whiteList[$route[0]][$route[1]])) return true;

        if (empty($data['lkey'])) return false;

        $res = Db::query("SELECT * FROM user WHERE lkey = ?", [$data['lkey']]);
        if (empty($res)) return false;

        self::$userInfo = $res[0];
        return true;

        //判断TOKEN是否超时
//        $standard = Config::get('TOKEN_DEADLINE');
//        $deadLine = time() - strtotime($res[0]['tokenDate']);
//        if (($deadLine / 3600) > $standard) return false;
//        return true;
    }

    /**
     * 检验审核版本
     * @param $params
     * @return bool
     */
    public static function checkAppChannel($params){
        $appChanel = Db::table('app_channel')->where(['status'=>0])->field('appChannel,version')->select();
        if(empty($appChanel)) return true;

        $versionArray = array();
        foreach($appChanel as $value){
            $versionArray[$value['appChannel']] = $value['version'];
        }

        if(!array_key_exists($params['app_channel'],$versionArray)){
            return true;
        }

        $appVersion = $versionArray[$params['app_channel']];

        if(version_compare($params['version'],$appVersion) == 0) self::$appVersion = true;

        return true;
    }

    /**
     * 检验黑名单用户
     * @param string $mobile
     * @param string $idno
     * @return bool
     */
    public static function checkBlackList($mobile = '',$idno = '')
    {
        if(empty($mobile) && empty($idno)){
            return false;
        }elseif(empty($mobile) && !empty($idno)){
            $sql = "select * from blacklist where enabled = 1 and idno = '{$idno}'";
        }elseif(!empty($mobile) && empty($idno)){
            $sql = "select * from blacklist where enabled = 1 and mobile = $mobile";
        }else{
            $sql = "select * from blacklist where enabled = 1 and (mobile = $mobile or idno = '{$idno}')";
        }
        //检验黑名单
        $result = Db::query($sql);
        if($result){
            LogUtil::writeLog($sql,'Black','black');
            return true;
        }else{
            return false;
        }
    }
}