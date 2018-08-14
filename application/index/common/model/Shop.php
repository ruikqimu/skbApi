<?php
namespace app\index\common\model;

use think\Config;
use think\Db;

class Shop{

    public function getShopList(){

        $list = array(10011002,10011004,10011005,10021001,10021002,10021003,10021004,10031001,10011006,10011007,10041001);

        return $list;
    }


    /**
     * 获取指定代理商的扣率信息
     * @param $shopNo
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getShopFee($shopNo){
        $map['a.shopno'] = $shopNo;
        $map['a.type']   = array('in','5,15,16,17');
        $field = "a.*,b.open";
        $list = Db::table('section')
                ->alias('a')
                ->join('bill_biz b','a.type=b.fee_type')
                ->field($field)
                ->where($map)
                ->select();
        return $list;
    }

    /**
     * 支付类型模型
     * @return array
     */
    public function getTypeModel(){
        $data = array(
            '5'     =>  'pos',      //即时到账
            '15'    =>  'wechat',   //微信支付
            '16'    =>  'cardpay',  //无卡支付
            '17'    =>  'alipay'    //支付宝支付
        );
        return $data;
    }


    /**
     * 获取代理商名称
     * @param $shopNo
     * @return mixed
     */
    public static function getShopName($shopNo){
        $shop = Db::table('shop')->where(['shopno'=>$shopNo])->column('name');
        return $shop[0];
    }

    public function getUserByShop($appChannel){
        if($appChannel == 'guanfang'){
            $return['shopNo'] = Config::get('defaultShop');
            $return['appKey'] = Config::get('defaultAppKey');
            $return['byShop'] = Config::get('defaultByShop');
        }else{
            $map['appChannel'] = $appChannel;
            $dlzh = Db::table('app_channel')->where($map)->column('dlzh');
            if(empty($dlzh)){
                $return['shopNo'] = Config::get('defaultShop');
                $return['appKey'] = Config::get('defaultAppKey');
                $return['byShop'] = Config::get('defaultByShop');
            }else{
                $appkey = $dlzh[0];
                $shop = Db::table('shop')->where(['dlzh'=>$appkey])->field('shopno,mobile')->find();
                $return['shopNo'] = $shop['shopno'];
                $return['appKey'] = $appkey;
                $return['byShop'] = $shop['mobile'];
            }
        }
        return $return;
    }

}