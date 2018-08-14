<?php
namespace app\index\controller;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\util\LogUtil;
use think\Db;
use think\Exception;
use think\Request;

class Boss {


    /**
     * BOSS通道注册认证
     * @param Request $request
     */
    public function normalOperate(Request $request) {
        //异步调用BOSS开通流程，提升用户使用体验
        $userNo = $request->get('userNo', '');
        LogUtil::writeLog($userNo, 'BossApi', 'asyncHttp', '异步开通BOSS通道');
        $result = BossApi::bossRegister($userNo);
        LogUtil::writeLog($result, 'BossApi', 'asyncHttp', '开通结果');
        LogUtil::close();
    }

    public function updateRate(Request $request){
        $userNo = $request->post('userNo');
        $userInfo = Db::table('user')->where(['userno'=>$userNo])->field('byshopno')->find();
        $bossModel = new BossApi('BOSS');
        $rate = $request->post('rate');
        $dRate = $request->post('dRate');
        $res = $bossModel->modifyProductForWk($userNo,$userInfo['byshopno'],$rate,$dRate);
        echo json_encode($res);
    }

}