<?php
namespace app\index\common\api;

use app\index\common\BOSSPlatform\BossApi;
use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Config;
use think\Db;
use think\Exception;

/*结算卡接口*/
class DebitCard implements CommonApi {

    //日志模块名
    const LOG_MODULE = 'DebitCard';

    //用户数据
    private $userInfo = null;

    /**
     * DebitCard init.
     */
    public function init() {
        //根据token获取user信息
        if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
        else $this->userInfo = Db::table('user')->where('lkey',$this->param['lkey'])->find();
    }

    /**
     * 获取推荐银行
     * @return string
     */
    public function getInfo(){
        $list['bankList'] = '工行，农行，中行，建行，交行，招行，平安，民生，浦发，兴业，光大，中信，广发';
        return AppResult::response200('success',$list);
    }

    /*
     * 结算卡添加
     * @access public
     * @return mixed
     * */
    public function addCard() {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'cardNo,bankName,branchName,branchNo,mobile';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //检测银行卡号格式
            if (!preg_match("/^[1-9]\\d{15,19}$/", $params['cardNo'], $matches)) return AppResult::response101('卡号格式错误');

            //检测手机号码格式
            if (!preg_match("/^1[3-9]\\d{9}$/", $params['mobile'], $matches)) return AppResult::response101('手机号码格式错误');


            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //卡bin表获取卡信息
            $cardInfo = Db::query("SELECT CardName, CardType FROM cardbintb WHERE CardLen = ? AND LEFT(?, BinLen) = BIN", [strlen($params['cardNo']), $params['cardNo']]);
            if (empty($cardInfo[0]["CardName"])) return AppResult::response101('无法识别的卡类型！');
            $CardName = $cardInfo[0]["CardName"];

            //判断是否为贷记卡
            if ($cardInfo[0]['CardType'] == '贷记卡') return AppResult::response101('结算卡不支持绑定信用卡');
            //过滤非借记卡
            if ($cardInfo[0]['CardType'] != '借记卡') return AppResult::response101('结算卡号错误');

            //判断卡号是否已添加
            $cardMap['userno'] = $userNo;
            $cardMap['cardno'] = $params['cardNo'];
            $cardMap['status'] = array('neq','1');
            $cardMap['type'] = 1;
            $card = Db::table('user_card')->where($cardMap)->find();
            if (!empty($card)) return AppResult::response101('该卡号已添加');

            //四要素验证
            $data = [
                'name'      => $userInfo['name'],
                'cardNo'    => $params['cardNo'],
                'idno'      => $userInfo['idber']
            ];
            //调用boss四要素接口
            $bossModel = new BossApi();
            $res = $bossModel->id4for($data);
            if($res['respCode'] != '00') return AppResult::response101($res['respDesc']);

            //接收结算卡图片
            $imgUrl = Common::uploadImg('debitImage');

            //压缩图片
            $imgUrl = Common::tochgimgrand($imgUrl);
            LogUtil::writeLog('结算卡图片上传&压缩完成', self::LOG_MODULE, __FUNCTION__, '结算卡图片处理');

            //查询是否是首次绑卡
            $isMain = 0;
            $mainMap['status'] = array('neq',1);
            $mainMap['type'] = 1;
            $mainMap['userno'] = $userNo;
            $mainMap['main'] = 1;
            $cardList = Db::table('user_card')->where($mainMap)->find();
            if(empty($cardList)) $isMain = 1;

            //卡图标
            $cardLogo = $this->getBankImage($params['bankName']);

            //添加结算卡
            $add = [
                'userno'        => $userNo,
                'cardno'        => $params['cardNo'],
                'cardname'      => $params['bankName'],
                'uname'         => $userInfo['name'],
                'idno'          => $userInfo['idber'],
                'branch'        => $params['branchName'],
                'branchno'      => $params['branchNo'],
                'cname'         => $CardName,
                'typename'      => $cardInfo[0]['CardType'],
                'cardimg'       => $cardLogo,
                'mobile'        => $params['mobile'],
                'bankcard_img'  => $imgUrl,
                'main'          => $isMain,
                'type'          => 1,
                'ctime'         => time(),
            ];
            $ret = Db::table('user_card')->insert($add);
            if (empty($ret)) return AppResult::response101('结算卡添加失败');

            if ($isMain === 1) {  //首次认证绑卡
                //异步执行 通道注册、绑卡、产品开通
                LogUtil::writeLog(['msg'=>'开始异步请求BOSS', 'url'=>Config::get('BOSS_DEAL_URL').'?userNo='.$userNo], self::LOG_MODULE, __FUNCTION__, 'BOSS请求');
                async_curl(Config::get('BOSS_DEAL_URL').'?userNo='.$userNo);
                LogUtil::writeLog(['msg'=>'已完成异步请求BOSS', 'url'=>Config::get('BOSS_DEAL_URL').'?userNo='.$userNo], self::LOG_MODULE, __FUNCTION__, 'BOSS请求');
            }
            return AppResult::response200('结算卡添加成功');
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
    /*
     * 设置结算卡主卡
     * @access public
     * @return mixed
     * */
    public function setMain() {
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

            //查找主卡信息
            $mianMap['userno'] = $userNo;
            $mianMap['main'] = 1;
            $mianMap['type'] = 1;
            $mianMap['status'] = array('neq','1');
            $mainCard = Db::table('user_card')->where($mianMap)->find();

            //该卡号已被设置为主卡
            if ($mainCard['cardno'] == $params['cardNo']) return AppResult::response101('已为主卡，请勿重复设置');

            //查找当前卡号是否存在
            $nowCardMap['userno'] = $userNo;
            $nowCardMap['cardno'] = $params['cardNo'];
            $nowCardMap['status'] = array('neq','1');
            $nowCardMap['type'] = 1;
            $nowCard = Db::table('user_card')->where($nowCardMap)->find();
            if (empty($nowCard)) return AppResult::response101('无效卡号，请检查');

            //开启事务
            Db::startTrans();
            //设置旧结算主卡为非主卡
            Db::table('user_card')->where(['id'=>$mainCard['id']])->update(['main'=>0,'utime'=>time()]);
            //设置该结算卡为主卡
            Db::table('user_card')->where(['id'=>$nowCard['id']])->update(['main'=>1,'utime'=>time()]);

            //调用第三方接口 更换结算主卡
            $boss = new BossApi('setMainCard');
            $arr = [
                'cardname'  => $nowCard['cardname'],  //总行名称
                'branch'    => $nowCard['branch'],  //支行名称
                'branchno'  => $nowCard['branchno'],  //支行联行号
                'idber'     => $nowCard['idno'],  //持卡人身份证号
                'mobile'    => $nowCard['mobile'],  //银行预留手机号
                'name'      => $nowCard['uname'],  //持卡人姓名
                'cardno'    => $nowCard['cardno'],  //银行卡号(新卡)
                'oldCardNo' => $mainCard['cardno'], //银行卡号(旧卡)
                'userNo'    => $userNo,  //外部客户号
            ];
            $bdata = $boss->changeCard($arr);
            if ($bdata['respCode'] != '00') {
                LogUtil::writeLog($bdata, self::LOG_MODULE, __FUNCTION__, 'BOSS更换主卡失败');
                // 回滚事务
                Db::rollback();
                return AppResult::response101('主卡设置失败，请稍后重试');
            }else{
                // 提交事务
                Db::commit();
                return AppResult::response200('结算卡更换成功');
            }

        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
    /*
     * 结算卡列表
     * @access public
     * @return mixed
     * */
    public function cardList() {
        try {
            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //搜索用户已添加结算卡信息
            $map['userno'] = $userNo;
            $map['type'] = 1;
            $map['status'] = array('neq','1');
            $field = "cardno,cardname,main,concat('".Config::get('BANK_PIC_URL')."',cardimg) as cardimg";
            $list = Db::table("user_card")->where($map)->field($field)->order('main desc,ctime desc')->select();
            if (empty($list)) return AppResult::response200('未搜索到结算卡信息',array());

            return AppResult::response200('结算卡列表获取成功', $list);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
    /*
     * 获取开户银行
     * @access public
     * @return mixed
     * */
    public function bankList() {
        try {
            $list = Db::table("bank")->field('CODE as bankCode, NAME as bankName')->select();

            return AppResult::response200('银行获取成功', $list);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
    /*
     * 根据关键字获取支行
     * @access public
     * @return mixed
     * */
    public function branchInfo() {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'bankCode,keyword';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //搜索支行信息
            $list = Db::query("SELECT NAME as branchName, CODE as branchCode FROM bank_branch WHERE BANK_CODE = ? AND NAME LIKE ?", [$params['bankCode'], "%{$params['keyword']}%"]);
            if (empty($list)) return AppResult::response101('未搜索到支行信息');
            return AppResult::response200('支行信息获取成功', $list);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
    /*
     * 根据卡号识别银行名称
     * @access public
     * @return mixed
     * */
    public function identifyBankName() {
        try {
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'cardNo';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }
            //卡bin表获取银行名称
            $card = Db::query("SELECT bankName FROM cardbintb WHERE CardLen = ? AND LEFT(?, BinLen) = BIN", array(strlen($params['cardNo']), $params['cardNo']));
            if (empty($card[0]['bankName'])) return AppResult::response101('银行名称识别失败');

            //获取银行规范名称
            $bank = Db::query("SELECT NAME as bankName, CODE as bankCode FROM bank WHERE NAME LIKE ?", ["%".$card[0]['bankName']."%"]);
            if (empty($bank)) return AppResult::response101('银行名称识别失败');

            return AppResult::response200('银行名称获取成功', ['bankName'=>$bank[0]['bankName'], 'bankCode'=>$bank[0]['bankCode']]);
        } catch (Exception $e) {
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取当前结算卡
     */
    public function getCurrentCard(){
        $card = Db::table('settlement_card')->field('cardNo,bankName')->where(['userNo'=>$this->userInfo['userNo'],'isMain'=>1,'isBind'=>0])->select();
        if(empty($card)) return AppResult::response200('结算卡获取成功');
        //查找银行对应logo图片
        foreach ($card as $key => $value) {
            $pic = Db::table("bank")->where(['NAME'=>$value['bankName']])->field("picName as icon")->find();
            //不存在LOGO图标 默认返回银联图片
            if (empty($pic)) $card[$key]['icon'] = Config::get('BANK_PIC_URL').'bank_yl.png';
            else $card[$key]['icon'] = Config::get('BANK_PIC_URL').$pic['icon'];
        }
        return AppResult::response200('结算卡获取成功',$card);
    }

    /**
     * 解绑卡
     */
    public function unBindCard(){
        try{
            //获取参数
            $params = $this->param;

            $userInfo = $this->userInfo;
            //获取用户编号
            $userNo = $userInfo['userno'];

            //检查必传参数
            $str = 'cardNo';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            //查询卡信息
            $cardMap['cardno'] = $params['cardNo'];
            $cardMap['type']   = 1;
            $cardMap['status'] = array('neq',1);
            $cardMap['userno'] = $userNo;
            $cardInfo = Db::table('user_card')->where($cardMap)->find();

            if(empty($cardInfo)) return AppResult::response101('卡号信息有误！');

            $bind['status'] = 1;
            $bind['utime'] = time();
            $res = Db::table('user_card')->where(['id'=>$cardInfo['id']])->update($bind);

            if(empty($res)) return AppResult::response101('解绑失败，请重试');
            else return AppResult::response200('解绑成功');
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()], self::LOG_MODULE, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }

    }

    private function getBankImage($cardname){
        $list = Db::table('bank_pic')->where("LOCATE(keyword,'".$cardname."')")->find();
        if($list) return $list['picName'];
        return 'bank_yl.png';
    }
}