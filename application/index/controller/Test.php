<?php
namespace app\index\controller;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\model\UserModel;
use app\index\common\model\WeChat;
use app\index\common\util\CardVerify;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use app\index\common\util\AuthUtil;
use app\index\common\util\OcrVerify;
use CURLFile;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;
use app\index\common\util\Verify;

header('Content-type:text/html;charset=UTF-8');

class Test
{
    public function test(Request $request) {
//        $weChatModel = new WeChat();
//        $returnArr['appid']          = 'wx1ad2d1b3a9e13ebb';
//        $returnArr['partnerid']      = '1504240021';
//        $returnArr['prepayid']       = 'wx13210726089127fbd59b89e52575689680';
//        $returnArr['packageValue']   = 'Sign=WXPay';
//        $returnArr['timestamp']      = '1528893996';
//        $returnArr['noncestr']       = '15288939967877527';
//        $returnArr['sign']           = $weChatModel->getSign($returnArr);

//        echo json_encode($returnArr);
//        $userNo = '93263918868180474';
//        $bossModel = new BossApi('productQuery');
//        $ret = $bossModel->productQuery($userNo);

//        echo json_encode($ret);

        $bossModel = new BossApi('changeRate');
        $res = $bossModel->modifyProductForD0('91562518868180474',0.47);
        echo json_encode($res);
    }


    public function testApi(Request $request)
    {
        $param = $request->post();
        $param['sign'] = AuthUtil::checkSign($param);
        $data_string = $this->contentEncode(json_encode($param, JSON_UNESCAPED_UNICODE));
        $url = Config::get('HTTP') . 'skbApi/receive';
        $post = array(
            'enctypeData' => $data_string,
        );
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_SSL_VERIFYHOST	=> FALSE,
        );
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
//        echo $result;exit();
        echo $this->contentDecode($result);
    }

    public function contentEncode($data)
    {
        $pu_key = file_get_contents(dirname(__FILE__) . '/../common/util/rsa/app_public.pem');
        $crypto = '';
        $chunk = '';
        foreach (str_split($data, 244) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $pu_key);
            $crypto .= $encryptData;
        }
        return base64_encode($crypto);
    }

    public function contentDecode($data)
    {
        $private_key = file_get_contents(dirname(__FILE__) . '/../common/util/rsa/web_private.pem');
        $crypto = '';
        $chunk = '';
        foreach (str_split(base64_decode($data), 256) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $private_key);
            $crypto .= $decryptData;
        }
        return $crypto;
    }

    public function bossTest(Request $request)
    {

        $bossModel = new BossApi();
        $data = array(
            'name'    =>    '陶正亮',
            'idno'    =>    '340221199302088733',
            'cardNo'  =>    '6212261202041686859'
        );
//        $data = array(
//            'mobile' => '15189752925',
//            'code'=> '123456',
//            'type'    =>'180552'
//        );
        $res = $bossModel->getMessage();
//        $res = $bossModel->sendMessage($data);
//        LogUtil::close();
//        $res = $bossModel->id4for($data);
        echo json_encode($res);exit;
/*
        $userNo = '2018031614523010198100';
        $user = Db::table('user')->where('userNo', $userNo)->find();
        $userInfo = Db::table('user_info')->where('userNo', $userNo)->find();

       上传图片
        $res1 = BossApi::fileUpload($userNo, $userInfo['idCardFrontImage']);
        $res2 = BossApi::fileUpload($userNo, $userInfo['idCardBackImage']);
        LogUtil::writeLog($res1);
        LogUtil::writeLog($res2);

        var_dump($res1);
        var_dump($res2);

        LogUtil::close();
        exit;

        if(!empty($res1)) $update['bossFrontCardImage'] = $res1['respData'];
        if(!empty($res2)) $update['bossBackCardImage'] = $res2['respData'];
        $update['bossStatus'] = 1;
        Db::table('user')->where('userNo',$userInfo['userNo'])->update($update);
        LogUtil::close();
        exit;*/
        //商户注册
//        $data['idCardNo'] = $user['idCardNo'];
//        $data['mobile'] = $user['mobile'];
//        $data['name'] = $user['name'];
//        $data['userNo'] = $user['userNo'];
//        $data['bossFrontCardImage'] = $user['bossFrontCardImage'];
//        $data['bossBackCardImage'] = $user['bossBackCardImage'];
//        $data['gpsCity'] = '浙江省,杭州市';
//        $res = BossApi::mRegister($data);
//        var_dump($res);exit;
//        if($res['respCode'] == '00') $update['bossStatus'] = 2;
        //绑定结算卡
//        $map['userNo'] = $user['userNo'];
//        $map['isMain'] = 1;
//        $card = Db::table('settlement_card')->where($map)->find();
//        $data['bankName'] = $card['bankName'];
//        $data['branchNo'] = $card['branchNo'];
//        $data['idCardNo'] = $user['idCardNo'];
//        $data['mobile'] = $card['mobile'];
//        $data['name'] = $user['name'];
//        $data['cardNo'] = $card['cardNo'];
//        $data['userNo'] = $user['userNo'];
//        $data['branchName'] = $card['branchName'];
//        $res = BossApi::setCard($data);
//        var_dump($res);exit;
        //修改结算卡

        //开通业务
//        $arr = array();
//        $productEntities['productStatus'] = '1';
//        $productEntities['customerEndAmt'] = '1000';
//        $productEntities['customerStartAmt'] = '0';
//        $productEntities['productRate'] = '0.38';
//        $productEntities['productFixrate'] = '0.01';
//        $productEntities['productCode'] = '121000';//支付宝
//        $arr['productEntities'][] = $productEntities;
//        $productEntities['productCode'] = '122000';
//        $arr['productEntities'][] = $productEntities;//微信
//        $productEntities['productCode'] = '123000';
//        $arr['productEntities'][] = $productEntities;//无卡
//        $arr['customerOutNo'] = $user['userNo'];
//        echo json_encode($arr);exit;
////        $res = BossApi::productCreate($arr);
//        echo $res;exit;
//
//        //查询已开通产品
//        $res = BossApi::productQuery($user['userNo']);
//        echo $res;exit;
        //产品下单
//        $data['userNo'] = $user['userNo'];
//        $data['orderNo'] = '2018011512345679';
//        $data['transAmt'] = '111';
//        $data['orderPayAmount'] = '111';
//        $data['orderDesc'] = 'test';
//        $res = BossApi::orderCreate($data);
//        var_dump($res);exit;

        //无卡支付
//        $map['isMain'] = 1;
//        $map['userNo'] = $user['userNo'];
//        $card = Db::table('payment_card')->where($map)->find();
//        $data['orderNo'] = '2018011512345679';
//        $data['cardNo'] = $card['cardNo'];
//        $data['mobile'] = $card['mobile'];
//        $data['name'] = $user['name'];
//        $data['idCardNo'] = $user['idCardNo'];
//        $data['subject'] = 'test';
//        $data['notifyUrl'] = 'http://www.baidu.com';
//        $data['returnUrl'] = 'http://www.baidu.com';
//        $res = BossApi::wukaLkl($data);
//        var_dump($res);
    }

}