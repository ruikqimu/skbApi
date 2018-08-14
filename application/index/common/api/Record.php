<?php
namespace app\index\common\api;

use app\index\common\util\AppResult;
use app\index\common\util\AuthUtil;
use app\index\common\util\LogUtil;
use think\Db;
use think\Exception;

class Record implements CommonApi{

    private $message = '';
    private $log_model = 'Record';
    private $userInfo;

    public function init(){
        if (!empty(AuthUtil::$userInfo)) $this->userInfo = AuthUtil::$userInfo;
        else $this->userInfo = Db::table('user')->where('lkey', $this->param['lkey'])->find();
    }

    /**
     * 获取流水记录
     */
    public function getBillRecord(){
        try{
            //查询订单数据
            $map['userno'] = $this->userInfo['userno'];

            $list = Db::table('bill')->where($map)->field('ctime,sta,typename,amount,type,code,tabno,sign')->order('ctime desc')->select();

            if(empty($list)) return AppResult::response200('success',array());

            $return = array();

            foreach ($list as $key =>$value){
                $return['name'] = $value['typename'];
                $return['date'] = date('m月d日 H:i',$value['ctime']);
                $date =date('Y年m月',$value['ctime']);
                $return['sign']  = $value['sign'];
                $return['amount'] = $value['amount'];
                $return['typename'] = $value['typename'];
                switch($value['sta']){
                    case 0:
                        $return['statusname'] = '成功';
                        break;
                    case 1:
                        $return['statusname'] = '失败';
                        break;
                    case 2:
                        $return['statusname'] = '处理中';
                        break;
                }

                if($value['type'] != 2 && $value['type'] != 6 && $value['type'] != 10 && $value['type'] != 18){
                    if($value['code'] == '00'){
                        $return['statusname'] = '成功';
                    }else{
                        $return['statusname'] = '失败';
                    }
                }
                if($value['type'] == 5) $return['name'] = '实时收款';

                $nlist[$date]['date'] = $date;
                $nlist[$date]['list'][] = $return;

            }
            $returnArray = array_values($nlist);



            return AppResult::response200('success',$returnArray);


        }catch (Exception $e){
            LogUtil::writeLog(array('message'=>$e->getMessage(),'line'=>$e->getLine()),$this->log_model,__FUNCTION__,'系统错误');
            return AppResult::response101('系统错误，请联系客服');
        }
    }
}