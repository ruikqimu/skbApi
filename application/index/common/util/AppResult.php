<?php
namespace app\index\common\util;

class AppResult
{

    /**
     * 成功返回 code 200
     * @param string $data
     * @param string $message
     * @return string
     */
    public static function response200($message = 'success', $data = null)
    {
        return json_encode(array(
            'respData' => $data,
            'respCode' => '200',
            'respMsg' => $message
        ));
    }

    /**
     * 失败返回 code 101
     * @param string $message
     * @return string
     */
    public static function response101($message = 'error')
    {
        return json_encode(array(
            'respData' => null,
            'respCode' => '101',
            'respMsg' => $message
        ));
    }

    /**
     * 需要重新登录 code 400
     * @return string
     */
    public static function response400($message = '身份信息已过期，请重新登陆')
    {
        return json_encode(array(
            'respData' => null,
            'respCode' => '400',
            'respMsg' => $message
        ));
    }

    /*
     * 验证错误
     * */
    public static function response500($message = 'error')
    {
        return json_encode(array(
            'respData' => null,
            'respCode' => '500',
            'respMsg' => $message
        ));
    }

    /*
     * 账号锁定 需要进入忘记密码
     * */
    public static function response600($message = 'error')
    {
        return json_encode(array(
            'respData' => null,
            'respCode' => '600',
            'respMsg' => $message
        ));
    }
}