<?php
namespace app\index\common\model;

use app\index\common\util\LogUtil;
use think\Config;

class WeChat
{
    private $log_model = 'WeChat';

    public $appId   = 'wx1ad2d1b3a9e13ebb';
    public $mch_id  = '1504240021';
    public $key     = 'zhongmakj2018001zhongmakj2018001';
    public $url     = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    public $buyPosNotify = '';
    public $buyVipNotify = '';

    public function __construct($appChannel = '')
    {
        if($appChannel == 'AppStore'){
            $this->appId  = 'wxdc31dcb02fcc741f';
            $this->mch_id = '1504790381';
            $this->buyPosNotify = Config::get('buyPosNotifyAppStore');
            $this->buyVipNotify = Config::get('buyVipNotifyForAppStore');
        }else{
            $this->buyPosNotify = Config::get('buyPosNotify');
            $this->buyVipNotify = Config::get('buyVipNotify');
        }
    }


    /**
     * 微信预下单
     * @param $data
     * @return bool|mixed
     */
    public function getWeChatPreOrder($data)
    {
        if(empty($data)) return false;

        $data['appid']  =   $this->appId;
        $data['mch_id'] =   $this->mch_id;
        //获取签名
        $data['sign']   =   $this->getSign($data);
        //数组转xml
        $post = $this->arr2xml($data);
        LogUtil::writeLog($data,$this->log_model,__FUNCTION__,'微信支付预下单参数');
        //预下单
        return $this->curlPost($post,$this->url);
    }

    /**
     * 获取签名
     * @param $data
     * @return string
     */
    public function getSign($data)
    {
        ksort($data);
        $signContent = '';
        foreach ($data as $key => $value) {
            if (empty($value)) continue;
            $signContent .= "{$key}={$value}&";
        }
        $signContent .= "key=".$this->key;
        return strtoupper(md5($signContent));
    }

    /**
     * 数组转XML
     * @param $data
     * @param bool|true $root
     * @return string
     */
    public function arr2xml($data, $root = true)
    {
        $str="";
        if($root)$str .= "<xml>";
        foreach($data as $key => $val){
            if(is_array($val)){
                $child = $this->arr2xml($val, false);
                $str .= "<$key>$child</$key>";
            }else{
                $str.= "<$key><![CDATA[$val]]></$key>";
            }
        }
        if($root)$str .= "</xml>";
        return $str;
    }

    public function curlPost($post,$post_url)
    {
        $url = $post_url;

        //初始一个curl会话
        $curl = curl_init();

        //设置url
        curl_setopt($curl, CURLOPT_URL,$url);

        //设置发送方式：post
        curl_setopt($curl, CURLOPT_POST, true);

        //设置发送数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

        //TRUE 将curl_exec()获取的信息以字符串返回，而不是直接输出
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        //执行cURL会话 ( 返回的数据为xml )
        $return_xml = curl_exec($curl);
        //关闭cURL资源，并且释放系统资源
        curl_close($curl);

        libxml_disable_entity_loader(true);

        $value_array = json_decode(json_encode(simplexml_load_string($return_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $value_array;
    }

    /**
     * 解析XML
     * @param $return_xml
     * @return mixed
     */
    public function decodeXml($return_xml)
    {
        libxml_disable_entity_loader(true);
        $value_array = json_decode(json_encode(simplexml_load_string($return_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $value_array;
    }

    //查询微信支付结果
    public function selOrderState($orderNo)
    {
        $post['out_trade_no'] = $orderNo;
        $post['appid'] = $this->appId;
        $post['mch_id'] = $this->mch_id;
        $post['nonce_str'] = time().rand(1000000, 9999999);;
        $post['sign'] = $this->getSign($post);
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $post = $this->arr2xml($post);
        $res = $this->curlPost($post,$url);
        return $res;
    }
}