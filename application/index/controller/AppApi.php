<?php
namespace app\index\controller;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use app\index\common\util\AppResult;
use think\Db;
use think\Exception;
use think\Request;

class AppApi {
    /*
    * 统一接收入口
    * @access public
    * @return mixed
    * */
    /**
     * @param Request $request
     */
    public function receive(Request $request) {
        try {
            register_shutdown_function(array($this, 'writeLogClose'));
            $beginDate = Common::getDate();
            //接收密文
            $postStr = $request->post('enctypeData', '');

            //RSA解密
             $content = AuthUtil::RSADecode($postStr);

             if (empty($content)) $this->reply(AppResult::response500('密文错误'), '500error', $postStr);

            $param = json_decode($content, true);

            //route：接口地址 sign：签名 timestamp：时间戳 version：版本 system：系统 deviceid：设备标识 ip：ip地址 deviceName：设备名称
            $parameters = array(
                'route', 'sign', 'timestamp', 'version', 'system', 'ip', 'requestid' , 'appkey','app_channel'
            );
            foreach ($parameters as $key => $value) {
                if (empty($param[$value])) $this->reply(AppResult::response500("缺少{$value}参数"), '500error', $content);
            }

            //sign验证
            $sign = AuthUtil::checkSign($param);
            if (strlen($param['sign']) != 32 || $param['sign'] != $sign) $this->reply(AppResult::response500('sign验证失败'), '500error', $param);    //传入加密字符串为空或长度非32位

            //超过1分钟的时间戳 请求不再执行
//             if ((time() - $param['timestamp']) > 60) $this->reply(AppResult::response101('请求已超时'));

            //请求重放字段验证
//             $requestFlag = Db::table('app_request_log')->where(['content'=>$param['requestid']])->find();
//             if (!empty($requestFlag)) $this->reply(AppResult::response500('请求重复操作'), '500error', $param);    //报文已被处理

            //解析路由地址
            $route_ary = explode('_', $param['route']);
            if (count($route_ary) != 2) $this->reply(AppResult::response500('路由地址错误'), '500error', $param);

            //接口命名空间定义
            $namespace = '\app\index\common\api\\';
            $class = $namespace . $route_ary[0];    //获取类名称
            $method = $route_ary[1];                //获取方法名称
            //判断类是否存在
            if (!class_exists($class)) $this->reply(AppResult::response500('类错误'), '500error', $param);
            $service = new $class();
            //判断方法是否存在
            if (!method_exists($service, $method)) $this->reply(AppResult::response500('方法错误'), '500error', $param);

            //token验证
            if (!AuthUtil::checkToken($param, $route_ary)) $this->reply(AppResult::response400(), '400error', $param);

            //打印上传参数
            LogUtil::writeLog(json_encode($param, JSON_UNESCAPED_UNICODE), $route_ary[0], $route_ary[1], 'APP上传参数');

            //添加重放字段
//             Db::table('app_request_log')->insert(['content'=>$param['requestid']]);

            //执行业务逻辑
            $service->param = $param;
            //待审核版本
            AuthUtil::checkAppChannel($param);

            $service->init();   //初始化方法
            $res = $service->$method($param);

            //日志显示中文信息
            $retContent = json_encode(json_decode($res, true), JSON_UNESCAPED_UNICODE);

            //统一记录用户信息
            if(!empty(AuthUtil::$userInfo)){
                $userInfo = AuthUtil::$userInfo;
                LogUtil::writeLog($userInfo['userno'],$route_ary[0], $route_ary[1], '用户编号');
            }

            LogUtil::writeLog($retContent, $route_ary[0], $route_ary[1], '返回APP数据');

            //记录时间
            $endDate = Common::getDate();
            $this->apiLog($beginDate,$endDate,$route_ary);
            //组装数据返回APP
            $this->reply($res);
        }catch (Exception $e) {
            //处理发送异常
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine(), 'trace'=>$e->getTraceAsString()], 'AppApi', 'error', '错误信息');
            echo AppResult::response101($e->getMessage());
        }
    }

    /**
     * @access private
     * @param string $responseMsg       正文
     * @param string $errorCode  状态
     * @param array $param
     */
    private function reply($responseMsg, $errorCode = '', $param = array()) {

        if ($errorCode == '500error') { //系统验证日志单独处理
            if (is_array($param)) $param = json_encode($param, JSON_UNESCAPED_UNICODE);
            //日志显示中文信息
            $retContent = json_encode(json_decode($responseMsg, true), JSON_UNESCAPED_UNICODE);
            LogUtil::writeLog($param, 'AppApi', 'receive', 'APP上传参数');
            LogUtil::writeLog($retContent, 'AppApi', 'receive', '返回APP数据');
        }

//        echo $responseMsg;
        echo AuthUtil::RSAEncode($responseMsg);
        exit;
    }

    public function writeLogClose()
    {
        //日志统一写入
        LogUtil::close();
    }

    /**
     * 接口日志
     * @param $beginDate
     * @param $endDate
     * @param $route_ary
     */
    private function apiLog($beginDate,$endDate,$route_ary){
        $exeDate = strtotime($endDate) - strtotime($beginDate);

        if($exeDate <= 2){
            return;
        }
        try{
            $insert['beginDate'] = $beginDate;
            $insert['endDate']   = $endDate;
            $insert['exeDate']   = $exeDate;
            $insert['apiName']   = $route_ary[0].'_'.$route_ary[1];

            Db::table('api_log')->insert($insert);
        }catch (Exception $e){
            return;
        }

    }
}