<?php
/**
 * Created by PhpStorm.
 * User: zhouzeqiang
 * Date: 2017/10/12
 * Time: 下午4:39
 */

namespace App\Controller;

class Chat extends \Swoole\Controller
{
    public function index(){
        $this->session->start();
        if (empty($_SESSION['isLogin'])){
            $this->http->redirect('/user/login/');
            return;
        }
        $user = $_SESSION['user'];
        $this->assign('debug', 'true');
        $this->assign('user', $user);
        $this->display('chat/index.php');
    }
}