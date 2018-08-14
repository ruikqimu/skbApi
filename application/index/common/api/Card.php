<?php
namespace app\index\common\api;

/*支付卡逻辑类*/
use app\index\common\BOSSPlatform\BossApi;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Code;
use app\index\common\util\LogUtil;
use app\index\common\util\Common;
use app\index\controller\Bnh;
use think\Config;
use think\Db;
use think\Exception;

class Card implements CommonApi
{

    //日志模块名
    const LOG_MODULE = 'Card';

    //用户数据
    private $userInfo = null;

    public $param;

    private $bankName;

    /**
     * PayCard constructor.
     */
    public function init()
    {
        //根据token获取user信息
        if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
        else $this->userInfo = Db::table('user')->where('lkey', $this->param['lkey'])->find();
    }

    /**
     * 添加信用卡获取验证码
     * @return string
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

            //查询手机号时间
            $map['state'] = 0;
            $res = Db::table('mobile_code')->where($map)->order('id desc')->limit(1)->find();

            if (empty($res)) {

            } elseif (time() - strtotime($res['time']) < 60) {
                return AppResult::response101('请稍后再获取验证码');
            }

            //发送验证码
            $code = rand(100000, 999999);
            $codeRes = Code::getCode($params['mobile'], $code, '155807');
            if ($codeRes['respCode'] != '00') return AppResult::response101($codeRes['respDesc']);

            //短信记录写入数据库
            Code::recordCode($code, $params['mobile'], $params['appkey'], 5);

            return AppResult::response200('发送短信成功');

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('添加失败，请重试');
        }
    }


    /*
     * 添加支付卡
     * @access public
     * @return mixed
     * */
    public function addCard()
    {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'cardNo,mobile,code';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //检测银行卡号格式
            if (!preg_match("/^[1-9]\\d{15,19}$/", $params['cardNo'], $matches)) {
                return AppResult::response101('卡号格式错误');
            }

            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');

            //验证验证码
            //获取最近的一个验证码
            $code = Code::getNewCode($this->param['mobile'], $this->param['appkey'], 5);
            if (empty($code)) {
                return AppResult::response101('验证码已失效');
            } elseif ($code['code'] != $this->param['code']) {
                return AppResult::response101('验证码错误');
            } else {
                //更新验证码状态
                Code::updateCodeState($this->param['mobile'], $this->param['appkey'], $this->param['code']);
            }

            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //判断卡号是否已添加
            $cardMap['userno'] = $userNo;
            $cardMap['cardno'] = $params['cardNo'];
            $cardMap['type'] = 3;
            $cardMap['status'] = array('neq', 1);
            $card = Db::table('user_card')->where($cardMap)->find();
            if (!empty($card)) return AppResult::response101('卡号已添加');

            //获取银行名称
            if(!$this->identifyBankName($params['cardNo'])) return AppResult::response101('卡号未识别');

            //查找卡bin表获取卡类型名称
            $cardType = Db::query("SELECT CardName,CardType FROM cardbintb WHERE bankName = ? AND CardLen = ? AND LEFT(?, BinLen) = BIN", [$this->bankName, strlen($params['cardNo']), $params['cardNo']]);
            if (empty($cardType[0]['CardType'])) return AppResult::response101('卡类型识别失败');
            $CardName = $cardType[0]["CardName"];
            $cardType = $cardType[0]['CardType'];

            if (strpos($cardType, '贷记卡') === false) return AppResult::response101('请添加信用卡');

            //四要素验证
            $data = [
                'name' => $userInfo['name'],
                'cardNo' => $params['cardNo'],
                'mobile' => $params['mobile'],
                'idno' => $userInfo['idber']
            ];
            //调用boss四要素
            $bossModel = new BossApi();
            $res = $bossModel->id4for($data);
            if ($res['respCode'] != '00') return AppResult::response101($res['respDesc']);


            //获取卡图标
            $cardLogo = $this->getBankImage($this->bankName);

            //添加信用卡
            $add = [
                'userno'    => $userNo,
                'cardno'    => $params['cardNo'],
                'cardname'  => $this->bankName,
                'uname'     => $userInfo['name'],
                'idno'      => $userInfo['idber'],
                'mobile'    => $params['mobile'],
                'type'      => 3,
                'cardimg'   => $cardLogo,
                'typename'  => $cardType,
                'cname'     => $CardName,
                'ctime'     => time(),
                'status'    => 2
            ];
            Db::table('user_card')->insert($add);


            //调用帮你还接口，同步卡号
//            $Bnh = new Bnh();
//            $cardBnh['accountNo']   = $this->userInfo['userno'];
//            $cardBnh['cardNo']      = $params['cardNo'];
//            $cardBnh['cardName']    = $this->userInfo['name'];
//            $cardBnh['cardMobile']  = $params['mobile'];
//            $cardBnh['bankName']    = $this->bankName;
//            $cardBnh['token']       = $params['lkey'];
//            $Bnh->uploadUserCard($cardBnh);

            return AppResult::response200('信用卡添加成功');
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('添加失败，请重试');
        }
    }

    /*
     * 根据卡号识别银行名称
     * @access public
     * @return mixed
     * */
    private function identifyBankName($cardNo)
    {
        //识别卡名称
        $card = Db::query("SELECT bankName FROM cardbintb WHERE CardLen = ? AND LEFT(?, BinLen) = BIN", [strlen($cardNo), $cardNo]);
        if (empty($card)) return false;

        $this->bankName = $card[0]['bankName'];
        return true;
    }

    /*
     * 支付卡列表获取
     * @access public
     * @return mixed
     * */
    public function cardList()
    {
        try {
            //获取参数
            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //搜索用户已添加支付卡信息
            $cardMap['userno'] = $userNo;
            $cardMap['type'] = 3;
            $cardMap['status'] = array('neq',1);
            $field = "cardno,cardname,concat('" . Config::get('BANK_PIC_URL') . "',cardimg) as cardimg";
            $list = Db::table("user_card")->where($cardMap)->field($field)->order('ctime desc')->select();
            if (empty($list)) return AppResult::response200('未搜索到支付卡信息',array());

            return AppResult::response200('支付卡列表获取成功', $list);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }


    /*
     * 支付卡解绑删除
     * @access public
     * @return mixed
     * */
    public function unbind()
    {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'cardNo';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //判断卡号是否存在
            $cardMap['userno'] = $userNo;
            $cardMap['cardno'] = $params['cardNo'];
            $cardMap['status'] = array('neq',1);
            $card = Db::table('user_card')->where($cardMap)->find();
            if (empty($card)) return AppResult::response101('卡号不存在');

            //进行解绑操作
            $ret = Db::table('user_card')->where(['id' => $card['id']])->update(['status' => 1]);
            if (empty($ret)) return AppResult::response101('解绑失败，请重试');
            return AppResult::response200('解绑成功');
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message' => $e->getMessage(), 'line' => $e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取银行卡图标
     * @param $cardname
     * @return mixed|string
     */
    private function getBankImage($cardname)
    {
        $list = Db::table('bank_pic')->where("LOCATE(keyword,'" . $cardname . "')")->find();
        if ($list) return $list['picName'];
        return 'bank_yl.png';
    }
}