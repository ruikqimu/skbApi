<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;
Route::rule('receive','index/AppApi/receive');
Route::rule('getUserInfo','index/Bnh/getUserInfo');     //帮你还获取用户信息
Route::rule('uploadOrderInfo','index/Bnh/uploadOrderInfo'); //帮你还接收订单信息


Route::rule('queryMember','index/Query/queryMember');
Route::rule('queryBill','index/Query/queryBill');
Route::rule('payNotify','index/Notify/payNotify');
Route::rule('payD0Notify','index/Notify/payD0Notify');
Route::rule('Boss', 'index/Boss/normalOperate');    //BOSS注册、认证、通道开通
Route::rule('bossNotify','index/Notify/bossNotify');
Route::rule('buyPosNotify','index/Notify/buyPosNotify');//购买机具微信回调
Route::rule('buyPosNotifyForAppStore','index/Notify/buyPosNotifyForAppStore');//购买机具微信回调AppStore
Route::rule('buyVipNotify','index/Notify/buyVipNotify');//购买Vip微信回调
Route::rule('buyVipNotifyForAppStore','index/Notify/buyVipNotifyForAppStore');//购买Vip微信回调AppStore

Route::rule('getUserInfo','index/Bnh/getUserInfo');//帮你还获取用户信息
Route::rule('uploadOrderInfo','index/Bnh/uploadOrderInfo');//帮你还推送订单

Route::rule('userPush','index/Push/userPush');//51卡宝极光推送



return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];
