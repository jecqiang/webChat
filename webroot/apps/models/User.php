<?php
/**
 * Created by PhpStorm.
 * User: zhouzeqiang
 * Date: 2017/10/13
 * Time: 下午2:09
 */
namespace App\Model;
class User extends \Swoole\Model
{
    public $table = 'user';
    public $primary = 'user_id';
}