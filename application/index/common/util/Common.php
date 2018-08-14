<?php
namespace app\index\common\util;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;
use app\index\common\util;
class Common{

    /**
     * 检查必传字段信息
     * @param $param 验证参数
     * @param string $str 多个字段逗号拼接
     * @param $message
     * @return bool|void
     */
    public static function checkParams($param, $str, &$message){
        if(empty($str)) return;

        $arr = explode(',',$str);

        foreach($arr as $key => $value){
            if(!isset($param[$value])){
                $message = "缺少参数" . $value;
                return false;
            }
            if(empty($param[$value]) && $param[$value] !== '0' ){
                $message = "缺少参数" . $value;
                return false;
            }
        }
        return true;
    }

    /**
     *获取日期格式
     */
    public static function getDate(){
        return date("Y-m-d H:i:s",time());
    }

    /**
     * 生成token
     * @param $mobile
     * @return string
     */
    public static function buildToken($mobile){
        return password_hash($mobile.rand(100, 999), PASSWORD_BCRYPT);
    }

    /**
     * 生成lkey
     * @param $userno
     * @return string
     */
    public static function buildLkey($userno){
        return sha1(rand(100, 999) . $userno . time());
    }

    /*
     * 上传照片到指定目录下
     * @static
     * @access public
     * @param  $imageName 接收图片字段名
     * @return mixed
     * */
    public static function uploadImg($imageName) {
        $file = request()->file($imageName);
        // 移动到框架应用根目录/public/uploads/{$imageName}目录下
        if($file){
            //图片大小限制5M 5 * 1024 * 1024 = 5242880
            $info = $file->validate(['size'=>5242880,'ext'=>'jpg,png,gif,jpeg,JPG,PNG,GIF,JPEG'])->move(ROOT_PATH.'public/uploads_temp/'.$imageName);
            if($info){  //上传成功
                // 获取生成图片目录
                // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                $fileName = $info->getSaveName();
                return Config::get('APP_URL').'public/uploads_temp/'.$imageName.'/'.$fileName;
            }else{
                // 上传失败获取错误信息
                $error = $file->getError();
                throw new Exception($imageName."照片上传错误：".$error, '999');
            }
        } else {
            throw new Exception("未接收到{$imageName}图片数据", '999');
        }
    }

    /**
     * 检验url有效性
     * @param $url
     */
    public static function checkImage($url){
        try{
            $result = preg_match('/.*(\.png|\.jpg|\.jpeg|\.gif)$/', $url);
            if(!$result) return false;
            $res = file_get_contents($url);
            if($res) return true;
        }catch (Exception $e){
            throw new Exception("图片链接{$url}无效",'999');
        }
    }

    //接收图片
    public static function getImage($url,$dir){
        try{
            $url = html_entity_decode($url);
            $img = file_get_contents($url);
            $name = self::my_filename().'.png';
            $path = $dir  . DS . date("Ymd");
            $allPath = ROOT_PATH .'public/uploads_temp/'.$path;
            if(!is_dir($allPath)){
                mkdir($allPath,0777,true);
            }
            $imagePath = $allPath . DS . $name;
            $filePath = $path .DS. $name;
            $file = file_put_contents($imagePath, $img);//返回的是字节数
            return Config::get('APP_URL').'public/uploads_temp/'.$filePath;
        }catch (Exception $e){
            throw new Exception("下载图片失败{$url}",'999');
        }
    }
    //上传图片命名
    public static function my_filename() {
        return md5(sha1(rand(100000,999999).microtime(true))).md5(rand(100000,999999));
    }
    //压缩图片
    public static function tochgimgrand($image){
        try{
            $file = $image;
            $list = getimagesize($image);
            $mime = $list['mime'];
            list($width,$height) = list($newwidth,$newheight) = $list;
            if($width > 900){
                $newwidth = 900;
                $newheight = $height/($width/900);
            }
            switch ($mime){
                case 'image/gif':
                    $src_im = imagecreatefromgif($file);
                    break;
                case 'image/jpeg':
                    $src_im = imagecreatefromjpeg($file);
                    break;
                case 'image/png':
                    $src_im = imagecreatefrompng($file);
                    break;
                default:
                    return false;
                    break;
            }
            $name = explode('/', $image);
            $datePath = $name[count($name)-2].'/';  //日期目录名
            $prefix = $name[count($name)-3].'/';    //图片类型目录名
            $name = $name[count($name)-1];      //图片名
            $dst_im = imagecreatetruecolor($newwidth, $newheight);
            imagecopyresized($dst_im, $src_im, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

            //图片路径地址
            $filePath = ROOT_PATH.'public/uploads/'.$prefix.$datePath;
            $imageName = $prefix.$datePath; //如：backImage/20180117/
            if (!file_exists($filePath)) mkdir($filePath,0777,true);

            $imageName .= $name;
            imagejpeg($dst_im, $filePath.$name, 10); //输出压缩后的图片
            return Config::get('APP_URL').'public/uploads/'.$imageName;
        }catch (Exception $e){
            throw new Exception("压缩图片失败",'999');
        }
    }

    /**
     * * 生成订单号
     * @param string $orderStr 订单头部分
     * @param $mobile   手机号
     * @return string
     */
    public static function getOrderNo($orderStr = ''){
        $orderNo = $orderStr . date('YmdHis') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $tot = 9;
        for ($i=1 ;$i<=5; $i++) {
            srand(self::getMicroseconds());
            $orderNo = substr_replace($orderNo, rand(0, $tot--), -$i, 1);
        }
        return $orderNo;
    }
    //获取毫秒数
    public static function getMicroseconds() {
        $utimestamp = sprintf("%.10f", microtime(true)); // 带微秒的时间戳

        $timestamp = floor($utimestamp); // 时间戳
        $microseconds = round(($utimestamp - $timestamp) * 10000000000); // 微秒
        return $microseconds;
    }


    /**
     * 生成唯一的用户id
     */
    public static function getMemberNo(){
        return date('YmdHis') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }

    /**
     * 检验密码长度
     * @param $password
     * @return bool
     */
    public static function checkPassword($password){
        $length = strlen($password);
        if($length < 8 || $length > 14 || is_numeric($password)) return false;
        else return true;
    }

    public static function deducted($userno ,$shopno,$amount,$name){
        //日志
        LogUtil::writeLog($userno.'-'.$shopno.'-'.$amount.'-'.$name,'Deducted','deducted','扣量信息');
        LogUtil::close();
        $p4 = substr($shopno,0,4);
        $p8 = substr($shopno,0,8);
        $p12 = substr($shopno,0,12);
        $p16 = substr($shopno,0,16);
        $p20 = substr($shopno,0,20);
        $p24 = substr($shopno,0,24);
        $p28 = substr($shopno,0,28);
        $p32 = substr($shopno,0,32);

        //手机号码白名单
        $mobile = substr($userno, 7,11);
        $hmobarr = array('13372560729', '15669061111', '13383861358', '13706772482', '13771790508', '13867105389', '13867105389', '13888221962',
            '13506598619', '18503915868', '18687322983', '18388115211', '15974653497', '13211600568', '13108759890', '13888439673', '13867798591',
            '13830057955', '13393717980', '13523643803', '15687727888', '13838702996', '13271341501', '18827559288', '15514079806', '15516548015',
            '13922722776', '18735653070', '18503915868', '13575837717', '13830057955', '13587583810', '18642603666', '13868677848', '18735653070',
            '18735653070', '13705876103', '13779986937', '13339828899', '17683931931', '15797186598', '17699551879'
        );
        if(in_array($mobile,$hmobarr)) return 0;
        //代理商编码包含
        if($p4 == '1004') return 0;
        if($p4 == '1002') return 0;
        if($p12 == '100110021003' || $p12 == '100310011033' || $p12 == '100310011059' || $p12 == '100210021004') return 0;
        if($p12 == '100310011007' and $p16 != '1003100110071029') return 0;
        if($p12 == '100110021007' || $p20 == '10031001102910101053') return 0;//李思伟

        //代理商编码恒等白名单
        $hsparr = array(
            '1001100210071035',
            '1001100210071004',
            '100310011037100310031007',
            '100310011037100310151007',
            '1003100110371003101210011003',
            '10031001103710051005',
            '10031001103710031009100210091001',
            '10031001103710031012100110041006',
            '1003100110211054',
            '10031001104910131008',
            '1003100110291003',
            '100110021006',
            '100210021005',
            '1001100210091007',
            '10011002100310011044',
            '100110021009',
            '100310011059',
            '1001100210071015100110021002',
            '100110021007103510081025',
            '10031001103710031022100410031003100210021001',
            '10031001103710061010100110031001',
            '1003100110371005100410021004',
            '1003100110371005100410021010',
            '1003100110211057',
            '1003100110491013',
            '100310011037100610141008',
            '1003100110561041',
            '10031001100710291154'
        );

        if(in_array($shopno,$hsparr)) return 0;

        //百分比扣率
        $feeb = 25;
        if($p16 == '1003100110071029') $feeb = 25;//李强
        if($p12 == '100310011037') $feeb = 50;//信掌柜
        if($p12 == '100110021007' || $p20 == '10031001102910101053') $feeb = 10;//李思伟

        //单笔交易金额
        if($amount < 4000) return 0;

        //去除代理手机号码
        $shopMobile = Db::table('shop')->where(['mobile'=>$mobile])->find();
        if($shopMobile) return 0;

        // //去除vip交易
        // $user = m('user') ->where(array('userno'=>$userno)) ->find();
        // if($user['quota'] >= $amount AND $name == '刷卡') return 77;

        //总交易笔数
        $count = Db::table('bill') ->where(array('sta'=>0,'deducted'=>'0','ctime'=>array('gt',strtotime(date('Y-m-d'))),'shopno'=>array('like',$shopno.'%')))->count();
        if($count < 9) return 0;

        //总交易额
        $amount = Db::table('bill') ->where(array('sta'=>0,'ctime'=>array('gt',strtotime(date('Y-m-d'))),'shopno'=>array('like',$shopno.'%')))->sum('amount');
        if($amount < 50000) return 0;

        //交易额百分比
        $deducted = Db::table('bill') ->where(array('deducted'=>'77','ctime'=>array('gt',strtotime(date('Y-m-d'))),'shopno'=>array('like',$shopno.'%')))->sum('amount');
        if(round($deducted/$amount*100) > $feeb) return 0;
        return 77;
    }
}