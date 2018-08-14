<?php
namespace app\index\common\api;

use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Db;
use think\Exception;

class Message implements CommonApi{

    private $log_model = 'Message';
    private $userInfo;

    public $param;

    public function init(){
        if(isset($this->param['lkey'])){
            if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
            else $this->userInfo = Db::table('user')->where('lkey',$this->param['lkey'])->find();
        }
    }


    /**
     * 获取消息列表
     * @return string
     */
    public function getNoticeList(){
        try{
            //查询用户消息
            $map['tno']     = $this->userInfo['userno'];
            $map['status']  = 0;
            $beginDate = time() - (3600 * 24 * 60);
            $map['ctime'] = array('between',array($beginDate,time()));
            $field = "id,title,flag,FROM_UNIXTIME(ctime,'%Y-%m-%d %H:%i') as time,type,left(content,200) as content";
            $list = Db::table('notice')->field($field)->where($map)->order('id desc')->select();

            if(empty($list)) return AppResult::response200('success');
            else return AppResult::response200('success',$list);

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()],$this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取消息详情
     * @return string
     */
    public function getNoticeDetail(){
        try{
            //获取参数
            $params = $this->param;

            //检查必传参数
            $str = 'noticeId';
            if (!Common::checkParams($params, $str, $this->message)) {
                return AppResult::response101($this->message);
            }

            $map['id']  =   $params['noticeId'];
            $field = "title,content,FROM_UNIXTIME(ctime,'%Y-%m-%d %H:%i') as time,type,note";
            $data = Db::table('notice')->where($map)->field($field)->find();

            if(empty($data)) return AppResult::response101('消息不存在');

            //更改消息的状态
            $update['flag'] =   1;
            Db::table('notice')->where($map)->update($update);

            return AppResult::response200('success',$data);

        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()],$this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }

    /**
     * 获取透传
     * @return string
     */
    public function getNotice(){
        try{
            //未登录
            if(empty($this->userInfo)){
                $nowtime = time();
                $map['begintime'] = array('elt',$nowtime);
                $map['endtime'] = array('egt',$nowtime);
                $map['status'] = 2;
                $map['object'] = '';
                $map['sendmark'] = 1;
                $field = "title,content,pic,pic_url,btn_note,btn_url";
                $list = Db::table('notice_new')->where($map)->field($field)->order('begintime desc')->find();
                if(!$list) return AppResult::response200('未查询到相关公告-1');

                return AppResult::response200('success',$list);
            }

            $userInfo = $this->userInfo;
            $user_date = explode(',', $userInfo['notice_date']);
            $nowtime = time();
            $map['begintime'] = array('elt',$nowtime);
            $map['endtime'] = array('egt',$nowtime);
            $map['status'] = 2;
            $list = Db::table('notice_new')->where($map)->order('begintime desc')->select();
            if(!$list) return AppResult::response200('未查询到相关公告-1');

            foreach ($list as $v) {

                $data['title'] = $v['title'];
                $data['content'] = $v['content'];
                $data['pic'] = $v['pic'];
                $data['pic_url'] = $v['pic_url'];
                $data['btn_note'] = $v['btn_note'];
                $data['btn_url'] = $v['btn_url'];

                if(!empty($v['object'])){
                    if($v['object'] == 'dxpush'){
                        $find = Db::table('jpush_mobiles')->where(array('notice_new_id'=>$v['id'],'mobile'=>$userInfo['mobile']))->find();
                        if(!empty($find)){
                            if(in_array($v['id'], $user_date)){
                                return AppResult::response200('已请求成功过-1');
                            }else{
                                $str = $userInfo['notice_date'].','.$v['id'];
                                Db::table('user')->where(array('userno'=>$userInfo['userno']))->setField('notice_date',$str);
                                return AppResult::response200('查询成功',$data);
                            }
                        }else{
                            continue;
                        }
                    }else{
                        $mobile_n = explode(',',rtrim($v['object']));
                        if(in_array($userInfo['mobile'],$mobile_n)){
                            if(in_array($v['id'], $user_date)){
                                return AppResult::response200('已请求成功过-1');
                            }else{
                                $str = $userInfo['notice_date'].','.$v['id'];
                                Db::table('user')->where(array('userno'=>$userInfo['userno']))->setField('notice_date',$str);
                                return AppResult::response200('查询成功',$data);
                            }
                        }else{
                            continue;
                        }
                    }

                }

                switch ($v['system']) {
                    case '2':
                        if(in_array($v['id'], $user_date)){
                            return AppResult::response200('已请求成功过-2');
                        }else{
                            $str = $userInfo['notice_date'].','.$v['id'];
                            Db::table('user')->where(array('userno'=>$userInfo['userno']))->setField('notice_date',$str);
                            return AppResult::response200('查询成功',$data);
                        }
                        break;

                    case '1':
                        if($this->param['system'] == 'IOS'){
                            if(in_array($v['id'], $user_date)){
                                return AppResult::response200('已请求成功过-3');
                            }else{
                                $str = $userInfo['notice_date'].','.$v['id'];
                                Db::table('user')->where(array('userno'=>$userInfo['userno']))->setField('notice_date',$str);
                                return AppResult::response200('查询成功',$data);
                            }
                        }
                        break;

                    case '0':
                        if($this->param['system'] == 'Android'){
                            if(in_array($v['id'], $user_date)){
                                return AppResult::response200('已请求成功过-4');
                            }else{
                                $str = $userInfo['notice_date'].','.$v['id'];
                                Db::table('user')->where(array('userno'=>$userInfo['userno']))->setField('notice_date',$str);
                                return AppResult::response200('查询成功',$data);
                            }
                        }
                        break;

                    default:
                        return AppResult::response200('查询失败-system');
                        break;
                }
                unset($data);
            }
            return AppResult::response200('未查询到相关公告-2');
        }catch (Exception $e){
            //错误信息日志打印
            LogUtil::writeLog(['message'=>$e->getMessage(), 'line'=>$e->getLine()],$this->log_model, __FUNCTION__, '系统错误信息');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
}