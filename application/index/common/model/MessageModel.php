<?php
namespace app\index\common\model;

use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Db;
use think\Exception;

class MessageModel{

    private $userNo = '';

    private $log_model = "UserMessage";

    public function __construct($userNo)
    {
        $this->userNo = $userNo;
    }


    /**
     * 添加推送消息
     * @param $title
     * @param $content
     * @param int $type
     * @return bool
     */
    public function addNotice($title,$content,$type = 1)
    {
        try{
            $insert['title']   = $title;
            $insert['content'] = $content;
            $insert['ctime']   = time();
            $insert['flag']    = 0;
            $insert['puser']   = 'admin';
            $insert['type']    = $type;
            $insert['tno']     = $this->userNo;
            LogUtil::writeLog($insert,$this->log_model,__FUNCTION__,'消息添加');
            $res = Db::table('notice')->insert($insert);
            if($res) return $res;
            else return false;
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),$this->log_model,__FUNCTION__,'系统错误');
            return false;
        }
    }

    /**
     * 添加推送消息
     * @param $title
     * @param $content
     * @param $noticeId
     * @return bool
     */
    public function addPushNotice($title,$content,$noticeId)
    {
        try{
            $insert['userno']   =   $this->userNo;
            $insert['title']    =   $title;
            $insert['content']  =   $content;
            $insert['noticeId'] =   $noticeId;
            $insert['createDate']=   Common::getDate();

            LogUtil::writeLog($insert,$this->log_model,__FUNCTION__,'推送消息添加');

            $res = Db::table('user_push')->insert($insert);
            if($res) return true;
            else return false;
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),$this->log_model,__FUNCTION__,'系统错误');
            return false;
        }

    }
}