<?php
namespace WebIM;
use Swoole;
use Swoole\Filter;

class Server extends Swoole\Protocol\CometServer
{
    /**
     * @var Store\File;
     */
    protected $storage;
    protected $users;
    /**
     * 上一次发送消息的时间
     * @var array
     */
    protected $lastSentTime = array();

    const MESSAGE_MAX_LEN     = 1024; //单条消息不得超过1K
    const WORKER_HISTORY_ID   = 0;

    function __construct($config = array())
    {
        //将配置写入config.js
        $config_js = <<<HTML
var webim = {
    'server' : '{$config['server']['url']}'
}
HTML;
        file_put_contents(WEBPATH . '/config.js', $config_js);

        //检测日志目录是否存在
        $log_dir = dirname($config['webim']['log_file']);
        if (!is_dir($log_dir))
        {
            mkdir($log_dir, 0777, true);
        }
        if (!empty($config['webim']['log_file']))
        {
            $logger = new Swoole\Log\FileLog($config['webim']['log_file']);
        }
        else
        {
            $logger = new Swoole\Log\EchoLog(true);
        }
        $this->setLogger($logger);   //Logger

        /**
         * 使用文件或redis存储聊天信息
         */
        $this->storage = new Storage($config['webim']['storage']);
        $this->origin = $config['server']['origin'];
        parent::__construct($config);
    }

    /**
     * 下线时，通知所有人
     */
    function onExit($client_id)
    {
        $userInfo = $this->storage->getUser($client_id);
        if ($userInfo)
        {
            $resMsg = array(
                'cmd' => 'offline',
                'fd' => $client_id,
                'from' => 0,
                'channal' => 0,
                'data' => $userInfo['username'] . "下线了",
            );
            $this->storage->logout($client_id);
            unset($this->users[$client_id]);
            //将下线消息发送给所有人
            $this->broadcastJson($client_id, $resMsg);
        }
        $this->log("onOffline: " . $client_id);
    }

    function onTask($serv, $task_id, $from_id, $data)
    {
        $req = unserialize($data);
        if ($req)
        {
            switch($req['cmd'])
            {
                case 'getHistory':
                    $history = array('cmd'=> 'getHistory', 'history' => $this->storage->getHistory());
                    if ($this->isCometClient($req['fd']))
                    {
                        return $req['fd'].json_encode($history);
                    }
                    //WebSocket客户端可以task中直接发送
                    else
                    {
                        $this->sendJson(intval($req['fd']), $history);
                    }
                    break;
                case 'addHistory':
                    if (empty($req['msg']))
                    {
                        $req['msg'] = '';
                    }
                    $this->storage->addHistory($req['fd'], $req['msg']);
                    break;
                default:
                    break;
            }
        }
    }

    function onFinish($serv, $task_id, $data)
    {
        $this->send(substr($data, 0, 32), substr($data, 32));
    }

    /**
     * 获取在线列表
     */
    function cmd_getOnline($client_id, $msg)
    {
        $resMsg = array(
            'cmd' => 'getOnline',
        );
        $users = $this->storage->getOnlineUsers();
        $info = $this->storage->getUsers(array_slice($users, 0, 100));
//        $info = $this->storage->getAllUsers();
//        $resMsg['users'] = [1,2,4];
        $resMsg['list'] = $info;
        $this->sendJson($client_id, $resMsg);
    }


    /**
     * @desc 获取好友
     * @param $client_id
     * @param $msg
     * @author Jec
     * @date 2017-10-01
     */
    function cmd_getFriend($client_id, $msg){
        $resMsg = array(
            'cmd' => 'getFriend',
        );
        $user_id = intval($msg['user_id']);
        $friends = $this->storage->getFriend($user_id, 1, 'f.user_id,f.friend_user_id,u.username,u.avatar');
        $resMsg['list'] = $friends;
        $this->sendJson($client_id, $resMsg);
    }

    /**
     * @desc 获取群组
     * @param $client_id
     * @param $msg
     * @author Jec
     * @date 2017-10-01
     */
    function cmd_getGroup($client_id, $msg){
        $resMsg = array(
            'cmd' => 'getGroup',
        );
        $user_id = intval($msg['user_id']);
        $groups = $this->storage->getGroup($user_id, 1, 'gu.group_id,g.group_name,g.group_avatar');
        foreach($groups as $k=>$v){
            $count = $this->storage->getGroupUserCount($v['group_id'], 1);
            $groups[$k]['user_count'] = $count;
        }
        $resMsg['list'] = $groups;
        $this->sendJson($client_id, $resMsg);
    }

    /**
     * 获取历史聊天记录
     */
    function cmd_getHistory($client_id, $msg)
    {
        $task['fd'] = $client_id;
        $task['cmd'] = 'getHistory';
        $task['offset'] = '0,100';
        //在task worker中会直接发送给客户端
        $this->getSwooleServer()->task(serialize($task), self::WORKER_HISTORY_ID);
    }

    /**
     * 登录
     * @param $client_id
     * @param $msg
     */
    function cmd_login($client_id, $msg)
    {
        $user_id = intval($msg['user_id']);
        $info['user_id'] = $user_id;
        $info['username'] = Filter::escape(strip_tags($msg['username']));
        $info['avatar'] = Filter::escape($msg['avatar']);
        //回复给登录用户
        $resMsg = array(
            'cmd' => 'login',
            'fd' => $client_id,
            'user_id' => $user_id,
            'username' => $info['username'],
            'avatar' => $info['avatar'],
        );
        $isOnline = isset($this->users[$user_id]);
        //把会话存起来
        $this->users[$user_id] = $resMsg;
        $this->storage->login($resMsg);
        $this->sendJson($client_id, $resMsg);
        if(!$isOnline){
            //广播给其它在线用户
            $resMsg['cmd'] = 'newUser';
            $this->broadcastJson($user_id, $resMsg);
        }
    }

    /**
     * @desc 历史消息
     * @param $client_id
     * @param $msg
     */
    public function cmd_historyMessage($client_id, $msg){
        if(!Swoole\Validate::checkLacks($msg, ['sender_id','receiver_id'])){
            $this->sendErrorMessage($client_id, 201, '参数不正确');
            return ;
        }
        $msgList = $this->storage->getMsgHistory($msg, 'msg_id,sender_id,receiver_id,type,content,c_time');
        $resMsg['cmd'] = 'historyMessage';
        $resMsg['message_list'] = $msgList;
        $this->sendJson($client_id, $resMsg);
    }

    /**
     * @desc 未读消息
     * @param $client_id
     * @param $msg
     */
    public function cmd_unreadMessage($client_id, $msg){
        $senderId = intval($msg['sender_id']);
        $receiverId = intval($msg['receiver_id']);
        $unreadMsgList = $this->storage->getUnreadMsg($senderId, $receiverId, 'msg_id,sender_id,receiver_id,type,content,c_time');
        $resMsg['cmd'] = 'unreadMessage';
        $resMsg['message_list'] = $unreadMsgList;
        $this->sendJson($client_id, $resMsg);
    }

    /**
     * @desc 标记已读
     * @param $client_id
     * @param $msg
     */
    public function cmd_markRead($client_id, $msg){
        $senderId = intval($msg['sender_id']);
        $receiverId = intval($msg['receiver_id']);
        $rel = $this->storage->markRead($senderId, $receiverId, $msg['ids']);
        $resMsg['cmd'] = 'markRead';
        $resMsg['success'] = $rel;
        $this->sendJson($client_id, $resMsg);
    }


    /**
     * 发送信息请求
     */
    function cmd_message($client_id, $msg)
    {
        $resMsg = $msg;
        $resMsg['cmd'] = 'fromMsg';

        if(!Swoole\Validate::checkLacks($msg, ['sender_id'])){
            $this->sendErrorMessage($client_id, 102, '发送参数有误');
            return;
        }

        if (strlen($msg['content']) > self::MESSAGE_MAX_LEN) {
            $this->sendErrorMessage($client_id, 102, 'message max length is '.self::MESSAGE_MAX_LEN);
            return;
        }

        $now = time();
        //上一次发送的时间超过了允许的值，每N秒可以发送一次
//        if ($this->lastSentTime[$client_id] > $now - $this->config['webim']['send_interval_limit'])
//        {
//            $this->sendErrorMessage($client_id, 104, 'over frequency limit');
//            return;
//        }

        $receiver = $this->users[$msg['receiver_id']];
        //记录本次消息发送的时间
        $this->lastSentTime[$client_id] = $now;

        //表示群发
        if ($msg['channal'] == 0)
        {
            //群发
            $this->broadMsg($resMsg);
            $this->broadcastJson($client_id, $resMsg);
//            $this->getSwooleServer()->task(serialize(array(
//                'cmd' => 'addHistory',
//                'msg' => $msg,
//                'fd'  => $client_id,
//            )), self::WORKER_HISTORY_ID);
        }
        //表示私聊
        elseif ($msg['channal'] == 1)
        {
            $resMsg['msg_no'] = $msg['msg_no'] = uuid();
            $resMsg['c_time'] = time();
            $this->sendJson($receiver['fd'], $resMsg);
            $this->storage->addMsgHistory($msg);
        }
    }

    /**
     * 接收到消息时
     * @see WSProtocol::onMessage()
     */
    function onMessage($client_id, $ws)
    {
        $this->log("onMessage #$client_id: " . $ws['message']);
        $msg = json_decode($ws['message'], true);
        debugLog($msg);
        if (empty($msg['cmd'])) {
            $this->sendErrorMessage($client_id, 101, "invalid command");
            return;
        }
        $func = 'cmd_'.$msg['cmd'];
        if (method_exists($this, $func)) {
            $this->$func($client_id, $msg);
        } else {
            $this->sendErrorMessage($client_id, 102, "command $func no support.");
            return;
        }
    }

    /**
     * 发送错误信息
    * @param $client_id
    * @param $code
    * @param $msg
     */
    function sendErrorMessage($client_id, $code, $msg)
    {
        $this->sendJson($client_id, array('cmd' => 'error', 'code' => $code, 'msg' => $msg));
    }

    /**
     * 发送JSON数据
     * @param $client_id
     * @param $array
     */
    function sendJson($client_id, $array)
    {
        $msg = json_encode($array);
        if ($this->send($client_id, $msg) === false) {
            $this->close($client_id);
        }
    }

    /**
     * 广播JSON数据
     * @param $client_id
     * @param $array
     */
    function broadcastJson($user_id, $array)
    {
        $msg = json_encode($array);
        $this->broadcast($user_id, $msg);
    }

    function broadcast($current_user_id, $msg)
    {
        foreach ($this->users as $user_id => $userInfo)
        {
            if ($current_user_id != $user_id)
            {
                $this->send($userInfo['fd'], $msg);
            }
        }
    }

    /**
     * @desc 群发
     * @param $client_id
     * @param $array
     */
    function broadMsg($msg){
        $group_id = intval($msg['receiver_id']);
        $senderId = intval($msg['sender_id']);
        $groupUser = $this->storage->getGroupUserId($group_id);
        $senderInfo = $this->storage->getUser($senderId);
        debugLog($senderInfo);
        $userArr = array();
        foreach($groupUser as $k=>$v){
            $userArr[] = $v['user_id'];
        }
        $msg['sender'] = $senderInfo;
        $userInfo = $this->storage->getUsers($userArr);
        foreach($userInfo as $k=>$v){
            $this->sendJson($v['fd'], $msg);
        }
    }
}

