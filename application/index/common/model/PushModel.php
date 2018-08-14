<?php
namespace app\index\common\model;
use app\index\common\util\LogUtil;

class PushModel{
    private $log_model = 'JPush';

    public $audience = '';

    //官方秘钥测试
//    private $appKey = '8e4a912c09ab5b8a29a25381';
//    private $secret = '81910ace872ea0ef5808fca1';

    //官方秘钥正式
    private $appKey = '5f6ec1af134d21c90971909a';
    private $secret = '908d39568eb3a64daf3950cc';

    //App Store秘钥测试
//    private $appKeyStore = 'a98d8c78d7386ff1b0cb9df8';
//    private $secretStore = 'a012daf33808bf44a0c99a63';

//    //App Store秘钥正式
    private $appKeyStore = 'af0e07892a8b2be5bbef2566';
    private $secretStore = '83a559e549e093f0857305e7';


    /**
     * 推送消息
     * @param $lkey
     * @param $title
     * @param $system
     */
    public function getType($lkey,$title,$system){
        $arr['object'] = $lkey;
        $arr['title'] = $title;
        $this->audience = '{"alias" : ["'.$arr['object'].'"]}';
        $notification_content = '"alert":"'.$arr['title'].'"';
        if($system == 'Android'){
            return $this->postSend($notification_content);
        }else{
            return $this->postSendForStore($notification_content);
        }
    }

    /**
     * @param $platform  string 平台类型：all,ios,android
     * @param $audience  string 设备指定 平台为all时设为all
     * @param $notification_name string  推送方式 message-透传，notification-通知
     * @param $notification_content string  推送内容参数
     */
    public function postSend($notification_content){
        $audience = $this->audience;
        $url = 'https://api.jpush.cn/v3/push';
        $appkey = $this->appKey;
        $masterSecret = $this->secret;
        $header = array('Authorization:Basic '.base64_encode($appkey.':'.$masterSecret),'Content-Type:application/json');
        $options = array(
            'http' => array(
                'method' => 'GET',
                'content' => http_build_query(array()),
                'timeout' => '500',
                'header'=>$header
            ),
        );
        $result = json_decode(file_get_contents('https://api.jpush.cn/v3/push/cid?count=3&type=push', false, stream_context_create($options)),true);
        $json = '
        {
            "cid": "'.$result['cidlist'][0].'",
            "platform": "all",
            "audience": '.$audience.',
            "notification": {'.$notification_content.'},
            "options": {
                "time_to_live": 60,
                "apns_production": true,
                "apns_collapse_id":"jiguang_test_201706011100"
            }
        }';
        $res = $this->tocurl($url,$header,$json);
        LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'推送结果记录');
        LogUtil::close();
        return $res;

    }

    /**
     * @param $platform string 平台类型：all,ios,android
     * @param $audience string  设备指定 平台为all时设为all
     * @param $notification_name string  推送方式 message-透传，notification-通知
     * @param $notification_content string 推送内容参数
     */

    public function postSendForStore($notification_content){
        $audience = $this->audience;
        $url = 'https://api.jpush.cn/v3/push';
        $appkey = $this->appKeyStore;
        $masterSecret = $this->secretStore;
        $header = array('Authorization:Basic '.base64_encode($appkey.':'.$masterSecret),'Content-Type:application/json');
        $options = array(
            'http' => array(
                'method' => 'GET',
                'content' => http_build_query(array()),
                'timeout' => '500',
                'header'=>$header
            ),
        );
        $result = json_decode(file_get_contents('https://api.jpush.cn/v3/push/cid?count=3&type=push', false, stream_context_create($options)),true);
        $json = '
        {
            "cid": "'.$result['cidlist'][0].'",
            "platform": "all",
            "audience": '.$audience.',
            "notification": {'.$notification_content.'},
            "options": {
                "time_to_live": 60,
                "apns_production": false,
                "apns_collapse_id":"jiguang_test_201706011100"
            }
        }';
        $res = $this->tocurl($url,$header,$json);
        LogUtil::writeLog($res,$this->log_model,__FUNCTION__,'推送结果记录');
        LogUtil::close();
        return $res;
    }

    //post请求
    private function tocurl($url, $header, $content){

        $ch = curl_init();
        if(substr($url,0,5)=='https'){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

        $response = curl_exec($ch);
        if($error=curl_error($ch)){
            die($error);
        }
        curl_close($ch);
        return $response;
    }
}