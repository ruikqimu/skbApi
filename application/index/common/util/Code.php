<?php
namespace app\index\common\util;

use app\index\common\BOSSPlatform\BossApi;
use think\Db;
use think\Exception;

class Code{

    /**
     * 短信模板
    // "content": "校验码：{}，您的{}银行卡将付款{}元，任何人向您索取校验码均为诈骗，泄漏会导致资金损失！",
    // "type": "152072",

    // "content": "您绑定的主卡已经同步，请10分钟后再进行刷卡操作，谢谢您的配合！",
    // "type": "152070",

    // "content": "尊敬的会员，您的注册验证码是{}，若非本人操作，请忽略！",
    // "type": "152069",

    // "content": "注册成功！ APP中的[支付密码]默认与[登录密码]相同，您可以在设置中分别修改。",
    // "type": "152068",

    // "content": "您已通过身份认证，如有疑问请联系客服，感谢您的使用！",
    // "type": "152067",

    // "content": "您的身份信息未通过审核，请登录APP重新上传身份信息，如有疑问请联系客服。触发",
    // "type": "152065",

    // "content": "您正在找回登录密码，您的验证码是{}，请勿泄露!",
    // "type": "152064",

    // "content": "带芯片的银行卡请插入IC口读取刷卡（芯片卡按磁条卡的方式刷卡属于降级交易，会报错）。",
    // "type": "152063",

    // "content": "您正在重置登录密码，您的验证码是{}，请勿泄露!",
    // "type": "152061",

    // "content": "您正在重置交易密码，您的验证码是{}，请勿泄露!",
    // "type": "152060",

    //"content": "您的校验码是{code}，请勿泄露！",
    //"type": "155807",

    //"content": "尊敬的会员，您的注册校验码是{code}，若非本人操作，请忽略！",
    //"type": "155808",

    //"content": "您正在找回登录密码，您的校验码是{code}，请勿泄露！",
    //"type": "155809",

     */

    /**
     * @param $mobile
     * @param $code
     * @param $type
     * @return string
     */
    public static function getCode($mobile,$code,$type){
        try{
            $bossModel = new BossApi('Code');

            //封装短信数组
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['type'] = $type;

            return $bossModel->sendMessage($data);
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),'Code',__FUNCTION__,'系统错误');
        }
    }


    /**
     * 记录短信记录
     * @param $code
     * @param $mobile
     * @param $appkey
     * @param $type 1：注册获取验证码 2：登录页忘记密码 3：密码管理忘记密码
     * @return bool
     */
    public static function recordCode($code,$mobile,$appkey,$type){
        try{
            //短信记录写入数据库
            $data['code'] = $code;
            $data['mobile'] = $mobile;
            $data['time'] = Common::getDate();
            $data['ip'] = $_SERVER["REMOTE_ADDR"];
            $data['appkey'] = $appkey;
            $data['type'] = $type;
            $res = Db::table('mobile_code')->insert($data);
            if($res) return true;
            else return false;
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),'Code',__FUNCTION__,'记录短信失败');
        }
    }

    /**
     * 获取最近的一个验证码
     * @param $mobile
     * @param $appkey
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getNewCode($mobile , $appkey , $type){
        //获取最近的一个验证码
        $beginDate      = date("Y-m-d H:i:s", time() - 60);
        $map['mobile']  = $mobile;
        $map['time']    = array('between', array($beginDate, Common::getDate()));
        $map['appkey']  = $appkey;
        $map['type']    = $type;
        $map['state']   = 0;
        return Db::table('mobile_code')->where($map)->order('id desc')->limit(1)->find();
    }

    /**
     * 更新状态码为已使用状态
     * @param $mobile
     * @param $appkey
     * @param $code
     * @throws Exception
     */
    public static function updateCodeState($mobile , $appkey , $code){
        //更新状态码为已使用状态
        $map['mobile'] = $mobile;
        $map['appkey'] = $appkey;
        $map['state'] = 0;
        $map['code'] = $code;
        $update['state'] = 1;
        Db::table('mobile_code')->where($map)->update($update);
    }
}