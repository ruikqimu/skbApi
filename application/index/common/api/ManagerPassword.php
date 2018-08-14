<?php
namespace app\index\common\api;

use app\index\common\util\AppResult;
use app\index\common\util\Code;
use think\Db;
use think\Exception;
use app\index\common\util\LogUtil;
use app\index\common\util\Common;

class ManagerPassword implements CommonApi{

    private $message = '';
    private $log_model = 'ManagerPassword';
    private $userInfo = array();

    /**
     * 初始化接口
     */
    public function init() {
        $this->userInfo = Db::table('user')->where('lkey',$this->param['lkey'])->find();
    }

    /**
     * 检查原始密码
     */
    public function checkPassword(){
        try{
            //检查必传参数
            $str = 'pwd';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            if($this->userInfo['pwd'] != $this->param['pwd']){
                return AppResult::response101('原始密码错误');
            }else{
                return AppResult::response200('验证成功');
            }
        }catch (Exception $e){
            LogUtil::writeLog(array('message'=>$e->getMessage(),'line'=>$e->getLine()),$this->log_model,__FUNCTION__,'系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 重置登录密码
     */
    public function resetPassword(){

        try{
            //检查必传参数
            $str = 'pwd';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            $map['mobile'] = $this->userInfo['mobile'];
            $data['pwd'] = md5($this->param['pwd']);
            Db::table('user')->where($map)->update($data);
            return AppResult::response200('重置密码成功');
        }catch (Exception $e){
            LogUtil::writeLog(array('message'=>$e->getMessage(),'line'=>$e->getLine()),$this->log_model,__FUNCTION__,'系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 找回密码返回手机号
     */
    public function getMobile(){
        return AppResult::response200('获取成功',array('mobile'=>$this->userInfo['mobile']));
    }

    /**
     * 找回密码获取验证码
     */
    public function getCode(){
        try{
            //查询手机号时间
            $map['mobile'] = $this->userInfo['mobile'];
            $map['appkey'] = $this->userInfo['appkey'];
            $map['state'] = 0;
            $res = Db::table('mobile_code')->where($map)->order('id desc')->limit(1)->find();

            if(empty($res)){

            }elseif(time() - strtotime($res['time']) < 60){
                return AppResult::response101('请稍后再获取验证码');
            }

            //发送短信验证
            $code = rand(100000,999999);
            $codeRes = Code::getCode($this->userInfo['mobile'],$code,'155809');
            if($codeRes['respCode'] != '00') return AppResult::response101($codeRes['respDesc']);

            //短信记录写入数据库
            Code::recordCode($code,$this->userInfo['mobile'],$this->userInfo['appkey'],3);

            return AppResult::response200('发送短信成功',['code'=>$code]);
        }catch (Exception $e){
            LogUtil::writeLog(array('message'=>$e->getMessage(),'line'=>$e->getLine()),$this->log_model,__FUNCTION__,'系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 验证验证码
     */
    public function checkCode(){

        try{
            //获取最近的一个验证码
            $code = Code::getNewCode($this->userInfo['mobile'],$this->param['appkey'] , 3);
            if(empty($code)){
                return AppResult::response101('验证码已失效');
            }elseif($code['code'] != $this->param['code']){
                return AppResult::response101('验证码错误');
            }else{
                //更新验证码状态
                Code::updateCodeState($this->userInfo['mobile'],$this->param['appkey'],$this->param['code']);
                return AppResult::response200('验证验证码成功');
            }
        }catch (Exception $e){
            LogUtil::writeLog(array('message'=>$e->getMessage(),'line'=>$e->getLine()),$this->log_model,__FUNCTION__,'系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }




}