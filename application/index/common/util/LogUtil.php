<?php
namespace app\index\common\util;

class LogUtil {
    static $logArr = array();
    static $logRootPath = '';
    /*
     * 日志内容保存
     * @static
     * @access public
     * @param  $logName  日志模块名称
     * @param  $content  日志内容
     * @param  $logTitle 日志内容头部
     * @return void
     * */
    public static function writeLog($content, $logName = 'Common', $logFile = 'common', $logTitle = '') {
        //获取日志文件目录
        if (empty(self::$logRootPath)) self::$logRootPath = realpath(ROOT_PATH.'Log') . DS;

        if (empty(self::$logArr[$logName][$logFile])) {
            self::$logArr[$logName][$logFile] = '----------------start---------------------'.PHP_EOL;
        }
        if (is_array($content)) $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        if (!empty($logTitle)) $logTitle .= '：';
        self::$logArr[$logName][$logFile] .= (date('Y-m-d H:i:s')."======>>>>>>>{$logTitle}{$content}".PHP_EOL.PHP_EOL);
    }

    public static function close() {
        if (empty(self::$logArr)) return;
        $logPath = self::$logRootPath.date('Y_m_d');

        //创建日志文件目录
        if (!file_exists($logPath)) mkdir($logPath);

        //逐个日志文件写入
        foreach (self::$logArr as $key => $value) {
            $logDir = $logPath.DS.$key;
            if (!file_exists($logDir)) mkdir($logDir);
            self::logEnd($value, $logDir);
        }
        self::$logArr = null;
    }
    public static function logEnd($data, $logDir) {
        foreach ($data as $k => $v) {
            //获取日志文件名 避免单个日志文件太大
            $count = 1;
            while(true) {
                //生成日志文件名
                $logFile = "{$logDir}".DS."{$k}_{$count}.log";

                //第一次写入日志
                if (!is_file($logFile)) break;

                //日志文件未大于1M
                $file = filesize($logFile) / 1024;
                if ($file < 1024 * 100) break;

                $count++;
            }
            $v = rtrim($v);
            $v .= PHP_EOL.'-----------------end----------------------'.PHP_EOL.PHP_EOL;

            error_log($v, 3, $logFile);
//            $ch = fopen($logFile, 'ab');
//            fwrite($ch, $v);
//            fclose($ch);
        }
    }
}