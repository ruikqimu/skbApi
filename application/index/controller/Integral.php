<?php
namespace app\index\controller;

use app\index\common\util;

class Integral{

    public function getUrl()
    {
        // 指定允许其他域名访问
        header('Access-Control-Allow-Origin:*');
        // 响应类型
        header('Access-Control-Allow-Methods:POST');
        // 响应头设置
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        $post = I();    //接收参数

        if (empty($post['lkey'])) $this->ajaxReturn(array('msg' => '未获取到用户登录信息', 'error' => '101'));

        //用户信息
        $userInfo = M('user')->where(array('lkey' => $post['lkey']))->field('userno,name,idber,mobile')->find();

        if (empty($userInfo)) $this->ajaxReturn(array('msg' => '用户信息不存在！', 'error' => '101'));

        //封装数据
        $data['sourceUserId'] = $userInfo['userno'];
        $data['userName'] = $userInfo['name'];
        $data['idNo'] = $userInfo['idber'];
        $data['userTel'] = $userInfo['mobile'];

        $json = json_encode($data);

        //初始化加密
        $desModel = new util\Des($this->desKey);
        $des = $desModel->encrypt($json);

        $url = $this->url . '?sys=' . $this->sys . '&params=' . $des;

        $this->ajaxReturn(array('msg' => '获取成功', 'error' => '0', 'url' => $url));
    }


    /**
     * 获取卡列表
     */
    public function getBankList()
    {
        //接收上传参数
        $post = I();
        if (empty($post['sourceUserId'])) $this->returnError('1000', '未接收到sourceUserId');
        //参数解密
        $desModel = new DesModel($this->desKey);
        $userNo = $desModel->decrypt($post['sourceUserId']);
        if (empty($userNo)) $this->returnError('1001', 'sourceUserId解密失败');

        //查询用户银行卡
        $cardMap['userno'] = $userNo;
        $cardMap['type'] = 1;
        $cardMap['status'] = array('neq', '1');
        $cardList = M('user_card')->where($cardMap)->field('cardno,cardname,mobile,main,uname,branchno')->select();
        if (empty($cardList)) $this->returnError('1002', '用户暂无卡列表');

        $return = array();
        //转换输出格式
        foreach ($cardList as $key => &$value) {
            $code = $this->bankList($value['cardname']);
            if (!$code) continue;

            $return[$key]['cardNo'] = $desModel->encrypt($value['cardno']);
            $return[$key]['bankCode'] = $code;
            $return[$key]['resTel'] = $value['mobile'];
            $return[$key]['wdDefFlg'] = $value['main'];
            $return[$key]['cardName'] = $value['uname'];
            $return[$key]['lbnkNo'] = $value['branchno'];
        }

        $return = array_values($return);
        if (empty($return)) $this->returnError('1003', '用户暂无可支持的卡列表');

        $this->returnSuccess('获取卡列表成功', $return);

    }

    /**
     * 成功返回
     * @param string $success
     * @param $data
     */
    private function returnSuccess($success = 'success', $data)
    {
        $return = array(
            'resCode' => '0000',
            'resMsg' => $success,
            'bankInfList' => $data
        );
        echo json_encode($return);
        exit;
    }


    /**
     * 返回错误提示
     * @param string $message
     */
    private function returnError($code = '1000', $message = 'error')
    {
        $return = array(
            'resCode' => $code,
            'resMsg' => $message,
        );
        echo json_encode($return);
        exit;
    }

    /**
     * 银行列表
     */
    private function bankList($bankName)
    {
        $data = array(
            '工商银行' => '102100099996',
            '农业银行' => '103100000026',
            '中国银行' => '104100000004',
            '建设银行' => '105100000017',
            '交通银行' => '301290000007',
            '中信银行' => '302100011000',
            '光大银行' => '303100000006',
            '华夏银行' => '304100040000',
            '民生银行' => '305100000013',
            '广发银行' => '306581000003',
            '平安银行' => '307584007998',
            '招商银行' => '308584000013',
            '兴业银行' => '309391000011',
            '浦东发展银行' => '310290000013',
            '北京银行' => '313100000013',
            '恒丰银行' => '315301000019',
            '浙商银行' => '316100000025',
            '渤海银行' => '318110000014',
            '邮政储蓄银行' => '403100000004'
        );

        foreach ($data as $key => $value) {

            if (strpos($bankName, $key) !== false) {
                return $value;
            }
        }
        return false;

    }
}