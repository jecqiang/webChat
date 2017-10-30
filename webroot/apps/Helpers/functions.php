<?php
/**
 * Created by PhpStorm.
 * User: zhouzeqiang
 * Date: 2017/10/13
 * Time: 上午10:18
 */

/**
 * @desc 记录调试日志到log/debug.log文件
 * @author Jec
 * @date 2017-10-16
 * @param string
 */
function debugLog($msg){
    if(is_array($msg)){
        $msg = json_encode($msg);
    }
    $filename = WEBPATH . '/logs/debug.log';
    file_put_contents($filename, "------------------------------------------\r\n",FILE_APPEND);
    file_put_contents($filename, "time:".date('Y-m-d H:i:s')."\r\n",FILE_APPEND);
    file_put_contents($filename, $msg."\r\n",FILE_APPEND);
    return true;
}

/**
 * @desc 生成唯一ID
 * @return string
 */
function uuid(){
    return strtolower(md5(uniqid(rand(), true)));
}