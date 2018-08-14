<?php
namespace app\index\controller;

use app\index\common\model\PushModel;
use app\index\common\util\Common;
use app\index\common\util\LogUtil;
use think\Db;

class Push{

    public function userPush()
    {
        //查询推送表的记录
        $sql = "select a.title,a.userno,b.lkey,b.system from user_push a left join user b on a.userno = b.userno where a.status=0";
        $pushList = Db::query($sql);
        if(empty($pushList)) return false;

        $pushModel = new PushModel();

        $userStr = '';
        foreach($pushList as $value){
            $res = $pushModel->getType($value['lkey'],$value['title'],$value['system']);
            $result = json_decode($res,true);
            if(isset($result['sendno']) && $result['sendno'] == '0'){
                $userStr .= $value['userno'] .',';
            }else{
                continue;
            }
        }
        $userStr = rtrim($userStr,',');

        //更新用户推送状态
        $map['userno'] = array('in',$userStr);
        Db::table('user_push')->where($map)->update(['status'=>1,'sendDate'=>Common::getDate()]);

    }
}