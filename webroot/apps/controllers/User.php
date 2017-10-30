<?php
/**
 * Created by PhpStorm.
 * User: zhouzeqiang
 * Date: 2017/10/12
 * Time: 下午3:53
 */

namespace App\Controller;
use Swoole\Validate;

class User extends \Swoole\Controller
{
    public function login(){
        $this->display('user/login.php');
    }

    public function doLogin(){
        $this->session->start();
        if (!empty($_SESSION['isLogin'])){
            $response['code'] = 1;
            exit(json_encode($response));
        }
        $post = $this->request->post; //POST参数
        $response['code'] = 0;
        $error = [];
        $email = isset($post['email']) ? $post['email'] : '';
        $password = isset($post['password']) ? $post['password'] : '';
        $model = model('User');
        if(empty($email) || !Validate::check('email', $email)){
            $error[] = ['msg'=>'邮箱为空或不是有效的邮箱', 'field'=>'email'];
        }else{
            $userInfo = $model->get($email, 'email');
            if(empty($userInfo->getOriginalData())){
                $error[] = ['msg'=>'邮箱或密码错误', 'field'=>'password'];
            }elseif($userInfo->password != md5($password)){
                $error[] = ['msg'=>'邮箱或密码错误', 'field'=>'password'];
            }
        }
        if(!empty($error)){
            $response['data'] = $error;
            exit(json_encode($response));
        }
        $user = $userInfo->getOriginalData();
//        $user['username'] = $userInfo->username;
//        $user['user_id'] = $userInfo->user_id;

        $_SESSION['isLogin'] = 1;
        $_SESSION['user'] = $user;

        $response['code'] = 1;
        exit(json_encode($response));
    }

    public function register(){
        $this->display('user/register.php');
    }

    public function create(){
        $this->session->start();
        $post = $this->request->post; //POST参数
        $response['code'] = 0;
        $error = [];
        $username = isset($post['username']) ? $post['username'] : '';
        $email = isset($post['email']) ? $post['email'] : '';
        $password = isset($post['password']) ? $post['password'] : '';
        $confirm_password = isset($post['confirm_password']) ? $post['confirm_password'] : '';
        $model = model('User');
        if(empty($username) || !Validate::check('nickname', $username)){
            $error[] = ['msg'=>'昵称不能为空且只能中英文字符和数字', 'field'=>'username'];
        }
        if(empty($email) || !Validate::check('email', $email)){
            $error[] = ['msg'=>'邮箱为空或不是有效的邮箱', 'field'=>'email'];
        }else{
            $userInfo = $model->get($email, 'email');
            if(!empty($userInfo->getOriginalData())){
                $error[] = ['msg'=>'邮箱已被占用', 'field'=>'email'];
            }
        }
        if(empty($password) || strlen($password) < 6){
            $error[] = ['msg'=>'密码不能为空且不小于6位', 'field'=>'password'];
        }elseif($password != $confirm_password){
            $error[] = ['msg'=>'两次输入的密码不一致', 'field'=>'confirm_password'];
        }
        if(!empty($error)){
            $response['data'] = $error;
            exit(json_encode($response));
        }

        $data['username'] = $post['username'];
        $data['email'] = $post['email'];
        $data['password'] = md5($post['password']);
        $data['c_time'] = time();
        $data['u_time'] = time();
        $model->put($data);
        $response['code'] = 1;
        exit(json_encode($response));
    }

    public function logout(){
        $this->session->start();
        session_unset();
        $this->http->redirect('/user/login/');
    }

}