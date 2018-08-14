<?php
namespace app\index\common\util;

class WebResult
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
}