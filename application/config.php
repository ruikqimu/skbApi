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

return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用命名空间
    'app_namespace'          => 'app',
    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => false,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => [],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => '',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'index',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => true,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'          => false,
    // 请求缓存有效期
    'request_cache_expire'   => null,
    // 全局请求缓存排除规则
    'request_cache_except'   => [],

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => [],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],

    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------

    'cache'                  => [
        // 驱动方式
        'type'   => 'File',
        // 缓存保存目录
        'path'   => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
    ],

    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],

    //分页配置
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],

    //业务配置参数
    // 是否自动转换URL中的控制器和操作名
    'HTTP'                       => 'http://localhost/',
    'APP_URL'                    => 'http://localhost/skbApi/',
    //银行LOGO图片 URL
    'BANK_PIC_URL'               => 'http://kg.zhongmakj.com/skbApi/public/static/bankImages/',
    'APP_IMAGE_URL'              => 'http://kg.zhongmakj.com/skbApi/public/static/image/',   //通用图片目录
    'BOSS_DEAL_URL'              => 'http://kg.zhongmakj.com/skbApi/Boss',	//BOSS本地注册调用
    //安卓IOS省市区联动json串
    'JSON_FOR_IOS'              =>  'http://kg.zhongmakj.com/skbApi/public/static/areaJson/address.json',
    'JSON_FOR_AND'              =>  'http://kg.zhongmakj.com/skbApi/public/static/areaJson/province_data.json',
    //水印照片基本分数
    'WATERMARK_GRADE'            => '0',
    //客服电话
    'CUSTOMER_SERVICE_PHONE'     => '400-877-8571',

    //token过期时间
    'TOKEN_DEADLINE'             => '48',

    /********************异步回调地址**************************************************************/
    'pay_notify'                => 'http://kg.zhongmakj.com/skbApi/payNotify',  //快捷支付回调url
    'pay_notify_d0'             => 'http://kg.zhongmakj.com/skbApi/payD0Notify',//刷卡异步回调
    'boss_notify'               => 'http://kg.zhongmakj.com/skbApi/bossNotify',  //boss开通产品异步回调
    'buyPosNotify'              => 'http://kg.zhongmakj.com/skbApi/buyPosNotify',  //购买设备微信异步回调地址
    'buyPosNotifyAppStore'      => 'http://kg.zhongmakj.com/skbApi/buyPosNotifyForAppStore',  //购买设备微信异步回调地址
    'buyVipNotify'              =>  'http://kg.zhongmakj.com/skbApi/buyVipNotify',//购买vip微信异步回调
    'buyVipNotifyForAppStore'   =>  'http://kg.zhongmakj.com/skbApi/buyVipNotifyForAppStore',//购买vip微信异步回调
    /******************************************************************************************************/

    'default_image'				=> 'http://kg.zhongmakj.com/skbApi/public/uploads/image/yangzhang.jpg',//默认图片
    'url_convert'               => false,

    //调试模式
    'app_debug'                 => true,
    'show_error_msg'            =>  true,
    // 应用Trace
    'app_trace'                 => false,

    //用户注册默认机构
    'defaultShop'               =>      '10011002',
    'defaultAppKey'             =>      '88800310002',
    'defaultByShop'             =>      '15990094883',

    //用户注册默认机构 APPStore
    'defaultAppStoreShop'       =>      '1001100210181007',
    'defaultAppStoreAppKey'     =>      '8880042032',
    'defaultAppStoreByShop'     =>      '18357739989',

    //微信分享相关参数
    'shareHtml'                 =>      'http://kg.zhongmakj.com/51LklCs/51SKB_share/preregister.html?key=',
    'shareTitle'                =>      '收单就用51收款宝',
    'shareDesc'                 =>      '51收款宝，一款好用的收单APP',
    'shareImgUrl'               =>      'http://kg.zhongmakj.com/51LklCs/klapi/Tpl/Public/image/share.png',

    //小能Id
    'xnId'                      =>      'kf_10200_1524714476471',

    //帮你还相关参数正式
    'bnh_appid'                 =>      '610312644DB3F1745B998FE0FFDA4F58',
    'bnh_sign_key'              =>      'C734D98B9AEC077D37A372AC61717474',


    //Boss 相关参数测试
    'customerOutNo'             =>      '88888888',
    'appId'                     =>      '6D2CE03E3FE7E7DC1B8603ED2D57D022',
    'partnerCode'               =>      'P606617061300000001',
//    'httpUrl'                   =>      'https://to.zhongmafu.com:48080/gateway.do',
    'httpUrl'                   =>      'localhost/test/test.php',
    'imgUrl'                    =>      'https://to.zhongmafu.com:40443/file/sfs/upload',

    //Boss 相关参数正式
//    'customerOutNo'             =>      '111115445151',
//    'appId'                     =>      '0A9DF8E4CB87FED5DB30CA7BD6D8C314',
//    'partnerCode'               =>      'P606617062900000002',
//    'httpUrl'                   =>      'https://openhome.zhongmakj.com/gateway.do',
//    'imgUrl'                    =>      'https://openhome.zhongmakj.com/file/sfs/upload',

    //vip费率相关
    'posFeeVip'                 =>     '0.52',
    'cardPayVip'                =>     '0.55',

    //vip红包时间
    'vip_activity_time'         =>     '2018-07-24 23:59:59',

    //vip页面地址
    'vipUrl'                    =>  'http://kg.zhongmakj.com/51LklCs/VIPCombo/buy.html',

    //优惠券测试
    'coupon'                    => 'http://kg.zhongmakj.com/51LklCs/coupon/center.html',
    //优惠券正式
//    'coupon'                    =>  'https://tt.zm-skb.com/coupon/center.html',

    //签到测试
    'signin'                    =>  'http://kg.zhongmakj.com/51LklCs/calendar/calendarSign.html',
    //签到正式
//    'signin'                    =>  'https://tt.zm-skb.com/calendar/calendarSign.html',

    //积分测试
    'integer'                   =>  'http://kg.zhongmakj.com/51LklCs/membership/integral.html',
    //积分正式
//    'integer'                   =>  'https://tt.zm-skb.com/membership/integral.html',

    //无卡H5链接测试
    'cardPayH5'                 =>  'http://kg.zhongmakj.com/lft/wukaCollection/inAmount.html',

    //无卡H5链接正式
//    'cardPayH5'                 =>  'https://tt.zm-skb.com/wukaCollection/inAmount.html',


    //4种支付类型产品码
    'wk_pay_code'               =>     '663006',
    'wx_pay_code'               =>     '122000',
    'al_pay_code'               =>     '121000',
    'D0_pay_code'               =>     '124000',
];
