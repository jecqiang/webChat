<?php
namespace WebIM;
use Swoole;
class Storage
{
    /**
     * @var \redis
     */
    protected $redis;

    const PREFIX = 'webim';

    function __construct($config)
    {
        $this->redis = \Swoole::getInstance()->redis;
        $this->db = \Swoole::getInstance()->db;
        $this->redis->delete(self::PREFIX.'_online');
        $this->config = $config;
    }

    function login($info){
        $this->redis->set(self::PREFIX . '_user_' . $info['user_id'], json_encode($info));
        $this->redis->sAdd(self::PREFIX . '_online', $info['user_id']);
    }

    function logout($client_id)
    {
        $this->redis->del(self::PREFIX.'_user_'.$client_id);
        $this->redis->sRemove(self::PREFIX.'_online', $client_id);
    }

    /**
     * 用户在线用户列表
     * @return array
     */
    function getOnlineUsers()
    {
        return $this->redis->sMembers(self::PREFIX . '_online');
    }

    /**
     * 批量获取用户信息
     * @param $users
     * @return array
     */
    function getUsers($users)
    {
        $keys = array();
        $ret = array();

        foreach ($users as $v)
        {
            $keys[] = self::PREFIX . '_user_' . $v;
        }

        $info = $this->redis->mget($keys);
        foreach ($info as $v)
        {
            if($v && $v != 'false')
            $ret[] = json_decode($v, true);
        }

        return $ret;
    }

    /**
     * 获取单个用户信息
     * @param $userid
     * @return bool|mixed
     */
    function getUser($userid)
    {
        $ret = $this->redis->get(self::PREFIX . '_user_' . $userid);
        $info = json_decode($ret, true);

        return $info;
    }

    function exists($userid)
    {
        return $this->redis->exists(self::PREFIX . '_user_' . $userid);
    }

    /**
     * @desc 获取未读信息
     * @param $senderId int
     * @param $receiverId int
     * @param $field string
     * @return array
     * @author Jec
     * @date 2017-10-10
     */
    public function getUnreadMsg($senderId, $receiverId, $field='*'){
        $senderId = intval($senderId);
        $receiverId = intval($receiverId);
        if(empty($senderId) || empty($receiverId)){
            return array();
        }
        $object = table('msg_history');
        $object->primary = 'msg_id';
        $params['select'] = $field;
        $params['sender_id'] = $senderId;
        $params['receiver_id'] = $receiverId;
        $params['is_read'] = 0;
        $params['order'] = 'c_time asc';
        $msgList = $object->gets($params);
        return !empty($msgList) ? $msgList : array();
    }

    /**
     * @desc 标记已读信息
     * @param $senderId int
     * @param $receiverId int
     * @param $ids string
     * @return array
     * @author Jec
     * @date 2017-10-10
     */
    public function markRead($senderId, $receiverId, $ids=0){
        $senderId = intval($senderId);
        $receiverId = intval($receiverId);
        if(empty($senderId) || empty($receiverId) || ($ids!=0 && empty($ids))){
            return false;
        }
        $object = table('msg_history');
        if($ids != 0){
            $ids = explode(',', $ids);
            $params['in'] = array(
                'msg_id',
                $ids
            );
        }
        $params['sender_id'] = $senderId;
        $params['receiver_id'] = $receiverId;
        $u_data['is_read'] = 1;
        $u_data['u_time'] = time();
        $rel = $object->sets($u_data, $params);
        return $rel;
    }

    /**
     * @desc 添加聊天记录
     * @param $msg array
     * @return int
     * @author Jec
     * @date 2017-10-10
     */
    function addMsgHistory($msg){
        if(empty($msg)){
            return false;
        }
        $curTime = time();
        $data = [
            'sender_id' => $msg['sender_id'],
            'receiver_id' => $msg['receiver_id'],
            'msg_no' => $msg['msg_no'],
            'content' => $msg['content'],
            'is_read' => 0,
            'type' => empty($msg['type']) ? 1 : intval($msg['type']),
            'c_time' => $curTime,
            'u_time' => $curTime,
        ];
        $lastId = table('msg_history')->put($data);
        return $lastId;
    }


    /**
     * @desc 获取历史消息
     * @param $senderId int
     * @param $receiverId int
     * @param $page int
     * @param $field string
     * @return array
     * @author Jec
     * @date 2017-10-10
     */
    function getMsgHistory($query, $field='*'){
        if(!Swoole\Validate::checkLacks($query, ['sender_id','receiver_id'])){
            debugLog($query);
            return array();
        }
        $lastMsgId = isset($query['last_msg_id']) ? intval($query['last_msg_id']) : 0;
        $page_size  = !empty($query['page_size']) ? intval($query['page_size']) : 10;
        $page  =  !empty($query['page']) ? intval($query['page']) : 1;
        $offset     = ($page - 1) * $page_size;
        $senderId = intval($query['sender_id']);
        $receiverId = intval($query['receiver_id']);
        if(empty($senderId) || empty($receiverId)){
            return array();
        }
        $object = table('msg_history');
        $object->primary = 'msg_id';
        $params['select'] = $field;
        if(0 != $lastMsgId){
            $params['where'] = array('msg_id < '.$lastMsgId);
        }
        $params['sender_id'] = $senderId;
        $params['receiver_id'] = $receiverId;
        $params['order'] = 'msg_id desc';
        $params['page'] = !empty($msg['page']) ? intval($msg['page']) : 1;;
        $msgList = $object->gets($params);
        debugLog($msgList);
        return !empty($msgList) ? array_reverse($msgList) : array();
    }

    /**
     * @desc 获取用户列表
     * @author Jec
     * @date 2017-10-16
     * @throws \Exception
     */
    function getAllUsers(){
        $object = table('user');
        $object->primary = 'user_id';
        $params['select'] = 'user_id, username, avatar';
        $params['status'] = 1;
        $userList = $object->gets($params);
        return !empty($userList) ? $userList : array();
    }

    /**
     * @desc 获取用户信息
     * @param $user_id int
     * @author Jec
     * @date 2017-10-16
     */
    public function getUserById($user_id){
        $object = table('user');
        $object->primary = 'user_id';
        $userInfo = $object->get($user_id);
        $user = $userInfo->getOriginalData();
        return !empty($user) ? $user : array();
    }

    /**
     * @desc 获取好友
     * @param $user_id
     */
    public function getFriend($user_id, $status=1, $field='*'){
        $user_id = intval($user_id);
        $status = intval($status);
        if($user_id < 0){
            return array();
        }
        $sql = "select {$field} from friend as f INNER JOIN user as u on f.friend_user_id=u.user_id WHERE f.user_id={$user_id} and f.status={$status} ";
        $friendList = $this->db->query($sql)->fetchall();
        debugLog($friendList);
        return !empty($friendList) ? $friendList : array();
    }

    /**
     * @desc 获取群组
     * @param $user_id
     */
    public function getGroup($user_id, $status=1, $field='*'){
        $user_id = intval($user_id);
        $status = intval($status);
        if($user_id < 0){
            return array();
        }
        $sql = "select {$field} from group_user as gu INNER JOIN `group` as g on gu.group_id=g.group_id WHERE gu.user_id={$user_id} and gu.status={$status} ";
        $groupList = $this->db->query($sql)->fetchall();
        debugLog($groupList);
        return !empty($groupList) ? $groupList : array();
    }

    /**
     * @desc 群组人员数
     * @param $group_id
     * @param int $status
     * @return array
     * @throws \Exception
     */
    public function getGroupUserCount($group_id, $status=1){
        $object = table('group_user');
        $object->primary = 'gu_id';
        $params['status'] = 1;
        $params['group_id'] = $group_id;
        return $object->count($params);
    }

    /**
     * @desc 群组人员
     * @param $group_id
     * @param int $status
     * @return array
     * @throws \Exception
     */
    public function getGroupUserId($group_id, $status=1){
        $object = table('group_user');
        $object->primary = 'gu_id';
        $params['select'] = 'user_id';
        $params['group_id'] = $group_id;
        $params['status'] = $status;
        $userList = $object->gets($params);
        return !empty($userList) ? $userList : array();
    }

}