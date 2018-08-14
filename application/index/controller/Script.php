<?php
namespace app\index\controller;


use think\Db;
use think\Request;

class Script{

    /**
     * 计算平台每日分润
     */
    public function shopFee(){
        $param = Request::instance()->param();
        if(!empty($param['date'])) $date = $param['date'];
        else $date = date("Y-m-d",time());

        $beginDate = date("Y-m-d",strtotime($date) - 24*3600);

        //代理商列表
        $map['byCode'] = '0';
        $shopList = Db::table('partner')->where($map)->select();

        //计算利润
        foreach ($shopList as $key => $value){

            $sql = "select sum(a.amount) as amount,sum(a.rate) as rate,sum(a.payValue) as payValue,count(a.type) as count,b.type,(b.rate-b.costRate) as brate,(b.payRate-b.costPayRate) as bpayRate
                    from user_collection a,partner_biz b where a.status=1 and a.partnerCode like '".$value['partnerCode']."%' and
                    a.createDate > '$beginDate' and a.createDate < '$date' and a.type=b.type and b.partnerCode = '".$value['partnerCode']."'
                    group by b.type";
            $data = Db::query($sql);

            if(empty($date)){
                continue;
            }else{
                $arr = array();
                //处理分润
                foreach ($data as $k => $v) {
                    $arr[$k]['partnerCode'] = $value['partnerCode'];
                    $arr[$k]['type'] = $v['type'];
                    $arr[$k]['feeProfit'] = $v['rate'] - ($v['amount'] * $v['brate'] / 100);
                    $arr[$k]['payValue'] = $v['payValue'] - ($v['bpayRate'] * $v['count']);
                    $arr[$k]['orderAmount'] = $v['amount'];
                    $arr[$k]['count'] = $v['count'];
                    $arr[$k]['date'] = $beginDate;
                    $arr[$k]['partnerLength'] = strlen($value['partnerCode']);
                }
                Db::table('partner_profit')->where(array('partnerCode'=>$value['partnerCode'],'date'=>$beginDate))->delete();
                Db::table('partner_profit')->where(array('partnerCode'=>$value['partnerCode'],'date'=>$beginDate))->insertAll($arr);
                echo 'SUCCESS';
            }
        }
    }

    /**
     * 计算代理商每日分润
     */
    public function partnerFee(){
        $param = Request::instance()->param();
        if(!empty($param['date'])) $date = $param['date'];
        else $date = date("Y-m-d",time());

        $beginDate = date("Y-m-d",strtotime($date) - 24*3600);

        //代理商列表
        $map['byCode'] = array('neq','0');
        $shopList = Db::table('partner')->where($map)->select();

        //计算利润
        foreach ($shopList as $key => $value){

            $sql = "select sum(a.amount) as amount,count(a.type) as count,b.type,(b.rate-b.costRate) as brate,(b.payRate-b.costPayRate) as bpayRate
                    from user_collection a,partner_biz b where a.status=1 and a.partnerCode like '".$value['partnerCode']."%' and
                    a.createDate > '$beginDate' and a.createDate < '$date' and a.type=b.type and b.partnerCode = '".$value['partnerCode']."'
                    group by b.type";
            $data = Db::query($sql);

            if(empty($date)){
                continue;
            }else{
                $arr = array();
                //处理分润
                foreach ($data as $k => $v) {
                    $arr[$k]['partnerCode'] = $value['partnerCode'];
                    $arr[$k]['type'] = $v['type'];
                    $arr[$k]['feeProfit'] = $v['amount'] * $v['brate'] / 100;
                    $arr[$k]['payValue'] = $v['bpayRate'] * $v['count'];
                    $arr[$k]['orderAmount'] = $v['amount'];
                    $arr[$k]['count'] = $v['count'];
                    $arr[$k]['date'] = $beginDate;
                    $arr[$k]['partnerLength'] = strlen($value['partnerCode']);
                }
                Db::table('partner_profit')->where(array('partnerCode'=>$value['partnerCode'],'date'=>$beginDate))->delete();
                Db::table('partner_profit')->where(array('partnerCode'=>$value['partnerCode'],'date'=>$beginDate))->insertAll($arr);
                echo 'SUCCESS';
            }
        }
    }
}