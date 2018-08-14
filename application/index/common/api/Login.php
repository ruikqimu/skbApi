<?php
namespace app\index\common\api;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\model\Shop;
use app\index\common\model\UserModel;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Code;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Db;
use think\Exception;
use think\Config;

class Login implements CommonApi
{

    private $message = '';
    private $log_model = 'Login';
    private $number = '5';

    public $param;
    public function init()
    {

    }

    /**
     * 注册获取短信验证码
     * @return string
     */
    public function getRegistCode()
    {
        try {
            //获取参数
            $params = $this->param;
            //检查必传参数
            $str = 'mobile';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');

            //检查手机号是否已注册
            $map['mobile'] = $params['mobile'];
            $map['appkey'] = $params['appkey'];

            $userInfo = Db::table('user')->where($map)->find();
            if (!empty($userInfo)) return AppResult::response101('该手机号已注册');
            //查询手机号时间
            $res = Db::table('mobile_code')->where($map)->order('id desc')->limit(1)->find();
            if (empty($res)) {

            } elseif (time() - strtotime($res['time']) < 60) {
                return AppResult::response101('请稍后再获取验证码');
            }
            //发送验证码
            $code = rand(100000, 999999);
            $codeRes = Code::getCode($params['mobile'], $code, '155808');
            if ($codeRes['respCode'] != '00') return AppResult::response101($codeRes['respDesc']);

            //短信记录写入数据库
            Code::recordCode($code, $params['mobile'], $params['appkey'], 1);

            return AppResult::response200('获取验证码成功', ['code' => $code]);

        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 用户注册接口
     */
    public function regist()
    {
        try {
            //获取参数
            $params = $this->param;
            //检查必传参数
            $str = 'mobile,pwd,code';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //查询手机号是否已注册
            $userLogin['mobile'] = $params['mobile'];
            $userList = Db::table('user')->where($userLogin)->find();
            if (!empty($userList)) return AppResult::response101('该账号已存在');

            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');

            //验证验证码
            //获取最近的一个验证码
            $code = Code::getNewCode($this->param['mobile'], $this->param['appkey'] , 1);
            if (empty($code)) {
                return AppResult::response101('验证码已失效');
            } elseif ($code['code'] != $this->param['code']) {
                return AppResult::response101('验证码错误');
            } else {
                //更新验证码状态
                Code::updateCodeState($this->param['mobile'], $this->param['appkey'], $this->param['code']);
            }

            //注册用户数据 生成用户编号 userNo
            $user['mobile'] = $this->param['mobile'];
            $user['pwd'] = $this->param['pwd'];
            $user['zpwd'] = $this->param['pwd'];
            $user['appkey'] = $this->param['appkey'];
            $user['userno'] = rand(100000, 999999) . $this->param['mobile'];
            $user['ctime'] = time();
            $user['ltime'] = time();
            $user['ip'] = $_SERVER['REMOTE_ADDR'];
            $user['gps'] = empty($this->param['gps']) ? '30.290388-120.134746' : $this->param['gps'];
            $user['lkey'] = Common::buildLkey($user['userno']);
            $user['cno'] = Db::table('user')->max('id') + 10000001;
            //默认机构
            $shopModel = new Shop();
            $shop = $shopModel->getUserByShop($this->param['app_channel']);
            $user['byshopno'] = $shop['shopNo'];
            $user['byappkey'] = $shop['appKey'];
            $user['byshop']   = $shop['byShop'];
            $user['app_channel'] = $this->param['app_channel'];

            $user['transferMark'] = '1';
            $res = Db::table('user')->insert($user);
            if ($res) {
                //发送短信验证码
                Code::getCode($this->param['mobile'], '', '152068');
            } else {
                return AppResult::response101('注册失败，请重新注册');
            }
            return AppResult::response200('注册成功');

        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /**
     * 登录接口
     */
    public function login()
    {
        try {
            //获取参数
            $params = $this->param;
            //检查必传参数
            $str = 'mobile,pwd';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');

            //检验黑名单
            if(AuthUtil::checkBlackList($params['mobile'])) return AppResult::response101('账号异常，请联系客服！');

            //检查用户数据是否存在
            $map['mobile'] = $params['mobile'];
            $map['appkey'] = $params['appkey'];
            //检验账号是否已经锁定
            $userStatus = Db::table('user_mobile_status')->where($map)->select();
            $count = count($userStatus);
            if ($count >= $this->number) return AppResult::response600('账号输入次数已达5次');
            $userInfo = Db::table('user')->where($map)->find();
            //登录状态判断
            if (empty($userInfo)) {
                return AppResult::response101('账号不存在');
            } else {
                //检查账号密码
                if ($userInfo['pwd'] != $params['pwd']) {
                    $userMobile['mobile'] = $params['mobile'];
                    $userMobile['password'] = $params['pwd'];
                    $userMobile['time'] = Common::getDate();
                    $userMobile['appkey'] = $params['appkey'];
                    Db::table('user_mobile_status')->insert($userMobile);
                    if ($count >= 4) {
                        return AppResult::response600('账号输入次数已达5次');
                    } else {
                        $message = $this->number - $count - 1;
                        return AppResult::response101('密码错误，还剩' . $message . '次');
                    }
                } else {
                    //清楚登录状态
                    Db::table('user_mobile_status')->where($map)->delete();
                }

                //更新用户lkey
                $user['lkey'] = Common::buildLkey($userInfo['userno']);
                $user['ltime'] = time();
                $user['system'] = $this->param['system'];
                $user['appVersion'] = $this->param['version'];
                Db::table('user')->where($map)->update($user);
                //返回结果给前端
                $return['lkey'] = $user['lkey'];
                $return['userNo'] = $userInfo['userno'];

                //51卡宝用户优惠券
                UserModel::getInstance($userInfo['userno'])->scoreCouponCheck($userInfo['byshopno']);
                return AppResult::response200('登录成功', $return);
            }
        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 忘记密码获取验证码
     */
    public function getCode()
    {
        try {
            //获取参数
            $params = $this->param;
            //检查必传参数
            $str = 'mobile';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            $map['mobile'] = $params['mobile'];
            $map['appkey'] = $params['appkey'];

            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');


            //检查手机号是否已注册
            $userInfo = Db::table('user')->where($map)->find();
            if (empty($userInfo)) return AppResult::response101('该手机号不存在');
            //查询手机号时间
            $map['state'] = 0;
            $res = Db::table('mobile_code')->where($map)->order('id desc')->limit(1)->find();

            if (empty($res)) {

            } elseif (time() - strtotime($res['time']) < 60) {
                return AppResult::response101('请稍后再获取验证码');
            }

            //发送验证码
            $code = rand(100000, 999999);
            $codeRes = Code::getCode($params['mobile'], $code, '155809');
            if ($codeRes['respCode'] != '00') return AppResult::response101($codeRes['respDesc']);

            //短信记录写入数据库
            Code::recordCode($code, $params['mobile'], $params['appkey'], 2);

            return AppResult::response200('发送短信成功', ['code' => $code]);
        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }

    }

    /**
     * 验证验证码
     */
    public function checkCode()
    {

        try {
            //获取最近的一个验证码
            $code = Code::getNewCode($this->param['mobile'], $this->param['appkey'] ,2);
            if (empty($code)) {
                return AppResult::response101('验证码已失效');
            } elseif ($code['code'] != $this->param['code']) {
                return AppResult::response101('验证码错误');
            } else {
                //更新验证码状态
                Code::updateCodeState($this->param['mobile'], $this->param['appkey'], $this->param['code']);
                return AppResult::response200('验证验证码成功');
            }
        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 重置密码
     */
    public function resetPassword()
    {
        try {
            //获取参数
            $params = $this->param;
            //检查必传参数
            $str = 'mobile,pwd';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');

            $map['mobile'] = $params['mobile'];
            $map['appkey'] = $params['appkey'];
            $data['pwd'] = $params['pwd'];
            Db::table('user')->where($map)->update($data);
            //清楚登录状态
            Db::table('user_mobile_status')->where($map)->delete();
            return AppResult::response200('重置密码成功');
        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }

    }

    /*
     * 退出登录
     * */
    public function quitLogin()
    {
        try {
            //检查必传参数
            $str = 'lkey';
            if (!Common::checkParams($this->param, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            $lkey = $this->param['lkey'];
            Db::table('user')->where(['lkey' => $lkey])->update(['lkey' => null]);
            return AppResult::response200();
        } catch (Exception $e) {
            LogUtil::writeLog(array('message' => $e->getMessage(), 'line' => $e->getLine()), $this->log_model, __FUNCTION__, '系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取默认配置信息
     *
     */
    public function getDefaultConfig()
    {
        try {
            //获取默认主题配置
            $appConfig = Db::table('app_config')->where(['state' => 0, 'partnerCode' => $this->appPartner])->find();

            //当前的主题颜色
            $data['color'] = $appConfig['colorId'];

            //首页两个按钮的样式
            $buttonList = Db::table('app_button')->where(['id' => $appConfig['buttonStyleId']])->find();
            $data['payContent'] = $buttonList['payContent'];
            $data['payImgUrl'] = $buttonList['payImgUrl'];
            $data['bnhContent'] = $buttonList['bnhContent'];
            $data['bnhImgUrl'] = $buttonList['bnhImgUrl'];

            //个人推荐样式配置获取
            $recommendList = Db::table('app_recommend')->where(['id' => $appConfig['recommendId']])->find();

            $data['url'] = $recommendList['url'];
            //判断URL类型
            if (strpos($recommendList['url'], 'URL:BUG1001#') === 0) $data['url'] .= '?';   //推荐给好友
            $data['imgUrl'] = $recommendList['imgUrl'];

            //可用银行数据
            $data['theseBank'] = '工行，农行，中行，建行，交行，招行，平安，民生，浦发，兴业，光大，中信，广发';
            //用户协议
            $data['protocolUrl'] = Config::get('HTTP') . "/protocol?appkey=" . $this->param['appkey'];
            //使用手册
            $data['manualUrl'] = Config::get('HTTP') . "/manual?appkey=" . $this->param['appkey'];
            //常见问题
            $data['questionUrl'] = Config::get('HTTP') . "/question";

            return AppResult::response200('success', $data);

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage(), $this->log_model, __FUNCTION__, '启动获取配置失败');
        }
    }

    /*
     * 交易提示时间、限额等信息
     * */
    public function payNoticeInfo()
    {
        try {
            $data[0]['title'] = '到账时间';
            $data[0]['msg'] = '1.信用卡：7：00~ 22：45实时到账';
            $data[1]['title'] = '交易限额';
            $data[1]['msg'] = '1.单笔上限2万@#$2.单笔交易下限500元';
            return AppResult::response200('获取成功', $data);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], $this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

}