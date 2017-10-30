var ws = {};
var client_id = 0;
var userlist = {};
var GET = getRequest();
var face_count = 19;
var msg_page_size = 10;

$(document).ready(function () {
    //使用原生WebSocket
    if (window.WebSocket || window.MozWebSocket) {
        ws = new WebSocket('ws://im.swoole.com:9503');
    }
    listenEvent();
});

function listenEvent() {
    /**
     * 连接建立时触发
     */
    ws.onopen = function (e) {
        //连接成功
        console.log("connect webim server success.");
        //发送登录信息
        msg = new Object();
        msg.cmd = 'login';
        msg.user_id = user.user_id;
        msg.username = user.username;
        msg.avatar = user.avatar;
        ws.send($.toJSON(msg));
    };

    //有消息到来时触发
    ws.onmessage = function (e) {
        console.log('accept : message >>>>>>>>>>')
        console.log(e.data);
        var message = $.evalJSON(e.data);
        var cmd = message.cmd;
        switch (cmd){
            case 'login' :
                client_id = $.evalJSON(e.data).fd;
                ws.send($.toJSON({cmd : 'getOnline'}));
                break;
            case 'getOnline':
                showOnlineList(message);
                break;
            case 'newUser':
                showNewUser(message);
                break;
            case 'fromMsg':
                showMsg(message);
                break;
            case 'offline':
                var cid = message.fd;
                delUser(cid);
                showNewMsg(message);
                break;
            case 'unreadMessage':
                showUnreadMsg(message);
                break;
            case 'historyMessage':
                console.log(message);
                showHistoryMsg(message);
                break;
            case 'getFriend':
                showFriendList(message);
                break;
            case 'getGroup':
                showGroupList(message);
                break;
        }
    };

    /**
     * 连接关闭事件
     */
    ws.onclose = function (e) {
        console.log('close');
        $(document.body).html("<h1 style='text-align: center'>连接已断开，请刷新页面重新登录。</h1>");
    };

    /**
     * 异常事件
     */
    ws.onerror = function (e) {
        console.log('error');
        $(document.body).html("<h1 style='text-align: center'>服务器[" + webim.server +
            "]: 拒绝了连接. 请检查服务器是否启动. </h1>");
        console.log("onerror: " + e.data);
    };
}

/**
 * @desc 发送消息
 * @param senderId int
 * @param receiverId int
 * @param content string
 * @returns {boolean}
 */
function send(senderId, receiverId, content, channal=1){
    if (!content.length) {
        return false;
    }
    msg = new Object();
    msg.cmd = 'message';
    msg.sender_id = senderId;
    msg.receiver_id = receiverId;
    msg.content = content;
    msg.channal = channal;//0群聊,1私聊,2系统消息
    msg.type = 1;//1文本;2图片
    ws.send($.toJSON(msg));
}

/**
 * @desc 获取消息记录
 * @param sender_id int 发送者ID
 * @param receiver_id int 接受者ID
 * @param page int 页数
 * @param last_msg_id int 最近的消息ID
 */
function getHistoryMsg(sender_id, receiver_id, page=1, last_msg_id=0){
    msg = new Object();
    msg.sender_id = sender_id;
    msg.receiver_id = receiver_id;
    msg.page = page;
    msg.last_msg_id = last_msg_id;
    msg.cmd = 'historyMessage';
    ws.send($.toJSON(msg));
}

/**
 * @desc 标记已读
 * @param senderId int
 * @param receiverId int
 * @param content string
 * @returns {boolean}
 */
function markRead(senderId, receiverId, ids){
    if (!senderId.length || !receiverId.length) {
        return false;
    }
    msg = new Object();
    msg.cmd = 'markRead';
    msg.sender_id = senderId;
    msg.receiver_id = receiverId;
    msg.ids = ids;//0所有;1,2,3
    ws.send($.toJSON(msg));
}

/**
 * 显示新消息
 * @param dataObj
 * @returns {boolean}
 */
function showMsg(dataObj) {

    if(dataObj.channal == 0){
        showGroupMsg(dataObj);
        return ;
    }
    var fromId = dataObj.sender_id;
    var chatDialog = $('#chat-dialog-' + fromId);
    //没有打开对话框显示未读信息
    if(!chatDialog.length){
        unreadTip(fromId);
        return false;
    }
    var direct = chatDialog.find('div.direct-chat-messages');
    var directChat = chatDialog.find('div.direct-chat-messages-frame');
    //显示消息
    if(!displayMsg(dataObj)){
        return ;
    }
    var h = directChat.height();
    direct.scrollTop(h);
    markRead(fromId, user.user_id, 0);
}

/**
 * 显示群消息
 * @param dataObj
 * @return {boolean}
 */
function showGroupMsg(dataObj){
    var fromId = dataObj.receiver_id;
    var chatDialog = $('#chat-dialog-group-' + fromId);
    //没有打开对话框显示未读信息
    if(!chatDialog.length){
        //unreadTip(fromId);
        return false;
    }
    var direct = chatDialog.find('div.direct-chat-messages');
    var directChat = chatDialog.find('div.direct-chat-messages-frame');
    //显示消息
    var fromUser = dataObj.sender;
    var msgHtml = getMsgHtml(fromUser, dataObj);
    directChat.append(msgHtml);
    var h = directChat.height();
    direct.scrollTop(h);
}


/**
 * @desc 显示未读消息
 * @param responseData
 * @returns {boolean}
 */
function showUnreadMsg(responseData) {
    dataObjs = responseData.message_list;
    if(!dataObjs.length){
        return false;
    }
    fromId = dataObjs[0].sender_id
    displayMsg(dataObjs);
    var chatDialog = $('#chat-dialog-' + fromId);
    var loadMore = chatDialog.find('.chat-load-more');
    loadMore.show().attr('attr-data', dataObjs[0].msg_id);
    //标记已读
    markRead(fromId, user.user_id, 0);
    return true;
}

/**
 * @desc 显示历史消息
 * @param responseData
 * @returns {boolean}
 */
function showHistoryMsg(responseData) {
    dataObjs = responseData.message_list;
    var len = dataObjs.length;
    if(!len){
        return false;
    }
    displayMsg(dataObjs, false);
    fromId = dataObjs[0].sender_id;
    var chatDialog = $('#chat-dialog-' + fromId);
    var loadMore = chatDialog.find('.chat-load-more');
    if(len >= msg_page_size){
        loadMore.show().attr('attr-data', dataObjs[0].msg_id);
    }else{
        loadMore.hide().attr('attr-data', dataObjs[0].msg_id);
    }
    loadMore.find('i.fa-refresh').hide();
    if(chatDialog.attr('attr-init') == '1'){
        var direct = chatDialog.find('div.direct-chat-messages');
        var directChat = chatDialog.find('div.direct-chat-messages-frame');
        var h = directChat.height();
        direct.scrollTop(h);
        chatDialog.attr('attr-init', '0');
    }
    return true;
}

/**
 * @desc 显示消息
 * @param fromId
 * @param dataObj
 * @returns {boolean}
 */
function displayMsg(dataObj, is_append=true){
    if(empty(dataObj)){
        return false;
    }
    var fromId = isArray(dataObj) ? dataObjs[0].sender_id : dataObj.sender_id;
    var chatDialog = $('#chat-dialog-' + fromId);


    if(!chatDialog.length){
        return false;
    }
    var direct = chatDialog.find('div.direct-chat-messages');
    var directChat = chatDialog.find('div.direct-chat-messages-frame');
    var fromUser = $.evalJSON(chatDialog.attr('attr-data'));
    var msgHtml = '';
    if(isArray(dataObj)){
        var len = dataObj.length;
        for (var i=0; i<len; i++){
            var msgItem = getMsgHtml(fromUser, dataObjs[i]);
            if(false === msgItem){
                continue;
            }
            msgHtml += msgItem;
        }
    }else{
        msgItem = getMsgHtml(fromUser, dataObj);
        if(false !== msgItem){
            msgHtml = msgItem;
        }
    }
    if(!msgHtml.length){
        return false;
    }
    if(is_append){
        directChat.append(msgHtml);
    }else {
        directChat.prepend(msgHtml);
    }

    return true;
}


/**
 * @desc 获取消息HTML
 * @param fromUser
 * @param dataObj obj
 * @return string
 */
function getMsgHtml(fromUser, dataObj){
    if(!fromUser || !dataObj){
        return false;
    }
    var time_str;
    if (dataObj.c_time) {
        time_str = GetDateT(dataObj.c_time)
    } else {
        time_str = GetDateT()
    }
    var content = dataObj.content;
    var msgHtml =
        '<div class="chat-msg-item">' +
        '<div class="direct-chat-msg chat-float-left">' +
        '<div class="direct-chat-info clearfix">' +
        '<span class="direct-chat-name pull-left">'+fromUser.username+'</span>' +
        '<span class="direct-chat-timestamp pull-right" style="display: none;">'+time_str+'</span> ' +
        '</div> ' +
        '<img class="direct-chat-img" src="/static/uploads/'+fromUser.avatar+'" alt="avatar"> ' +
        '<div class="direct-chat-text">'+content+'</div> ' +
        '</div>' +
        '</div>';
    return msgHtml;
}

/**
 * @desc 左边联系人未读提示增加
 * @param fromId
 * @returns {boolean}
 */
function unreadTip(fromId){
    var chatContactUserLi = $('#chat-contact-user-'+fromId);
    if(!chatContactUserLi.length){
        return false;
    }
    var unread = chatContactUserLi.find('small.label');
    if(unread.length){
        unread.text(parseInt(unread.text()) + 1);
        return true;
    }
    var unreadHtml = '<span class="pull-right-container"> <small class="label pull-right bg-red">1</small></span>';
    chatContactUserLi.find('.chat-contact-user').append(unreadHtml);
    return true;
}

/**
 * 显示所有在线列表
 * @param dataObj
 */
function showOnlineList(dataObj) {
    var li = '';
    for (var i = 0; i < dataObj.list.length; i++) {
        userInfo = new Object();
        userInfo.user_id = dataObj.list[i].user_id;
        userInfo.username = dataObj.list[i].username;
        userInfo.avatar = dataObj.list[i].avatar;
        var s = $.toJSON(userInfo);
        li +=
            '<li class="dynamic" data-role="user" id="chat-contact-user-'+dataObj.list[i].user_id+'">' +
              '<a href="#" class="chat-contact-user" attr-data=\''+s+'\'>' +
                '<img src="/static/uploads/' + dataObj.list[i].avatar + '" class="user-image" alt="User Image">' +
                '<i class="fa" style="width: 0px;"></i> ' +
                '<span>' + dataObj.list[i].username + '</span>' +
              '</a>' +
            '</li>';
        userlist[dataObj.list[i].fd] = dataObj.list[i].name;
    }
    $('#contact-user-tab').find('li[attr-data="user"]').attr('attr-init', 1);
    $('#contact-hidden-user').html(li);
    $('#chat-contact').find('li.dynamic').remove();
    $('#chat-contact').append(li);
}

/**
 * 显示所有在线列表
 * @param dataObj
 */
function showFriendList(dataObj) {
    var li = '';
    for (var i = 0; i < dataObj.list.length; i++) {
        userInfo = new Object();
        userInfo.user_id = dataObj.list[i].friend_user_id;
        userInfo.username = dataObj.list[i].username;
        userInfo.avatar = dataObj.list[i].avatar;
        var s = $.toJSON(userInfo);
        li +=
            '<li class="dynamic" data-role="user" id="chat-contact-user-'+dataObj.list[i].friend_user_id+'">' +
              '<a href="#" class="chat-contact-user" attr-data=\''+s+'\'>' +
                '<img src="/static/uploads/' + dataObj.list[i].avatar + '" class="user-image" alt="User Image">' +
                '<i class="fa" style="width: 0px;"></i> ' +
                '<span>' + dataObj.list[i].username + '</span>' +
              '</a>' +
            '</li>';
        userlist[dataObj.list[i].fd] = dataObj.list[i].username;
    }
    $('#contact-user-tab').find('li[attr-data="friend"]').attr('attr-init', 1);
    $('#contact-hidden-friend').html(li);
    $('#chat-contact').find('li.dynamic').remove();
    $('#chat-contact').append(li);
}

/**
 * 显示群组
 * @param dataObj
 */
function showGroupList(dataObj) {
    //console.log(dataObj);return false;
    var li = '';
    for (var i = 0; i < dataObj.list.length; i++) {
        groupInfo = new Object();
        groupInfo.group_id = dataObj.list[i].group_id;
        groupInfo.group_name = dataObj.list[i].group_name;
        groupInfo.group_avatar = dataObj.list[i].group_avatar;
        groupInfo.user_count = dataObj.list[i].user_count;
        var g = $.toJSON(groupInfo);
        li +=
            '<li class="dynamic" data-role="group" id="chat-contact-group-'+dataObj.list[i].group_id+'">' +
            '<a href="#" class="chat-contact-group" attr-data=\''+g+'\'>' +
            '<img src="/static/uploads/' + dataObj.list[i].group_avatar + '" class="user-image" alt="avatar">' +
            '<i class="fa" style="width: 0px;"></i> ' +
            '<span>' + dataObj.list[i].group_name + '</span>' +
            '</a>' +
            '</li>';
    }
    $('#contact-user-tab').find('li[attr-data="group"]').attr('attr-init', 1);
    $('#contact-hidden-group').html(li);
    $('#chat-contact').find('li.dynamic').remove();
    $('#chat-contact').append(li);
}

/**
 * @desc 当有一个新用户连接上来时
 * @param dataObj
 */
function showNewUser(dataObj) {
    if (!userlist[dataObj.fd]) {
        userlist[dataObj.fd] = dataObj.username;
    }
    noticeOnLine(dataObj.username);
}



function delUser(userid) {
    $('#user_' + userid).remove();
    $('#inroom_' + userid).remove();
    delete (userlist[userid]);
}

/**
 * @desc 通知上线
 * @author Jec
 * @param msg
 */
function noticeOnLine(msg){
    toastr.options.closeMethod = 'fadeOut';
    toastr.options.preventDuplicates = true;
    toastr.options.closeDuration = 600;
    toastr.options.progressBar = true;
    toastr.options.closeButton = true;
    toastr.options.showMethod = 'fadeIn';
    toastr.info(msg + " 上线了!");
}


/** ----------------------  页面dom操作  -------------------------- **/

/**
 * @desc com处理
 */
$(function(){
    var dialogContainer = $('#chat-dialog-container');
    var temp = $('#chat-dialog-template');
    //聊天框主题设置
    var chatTheme = new Array();
    chatTheme[0] = {"color":"light-blue", "theme":"primary"};
    chatTheme[1] = {"color":"red", "theme":"danger"};
    chatTheme[2] = {"color":"green", "theme":"success"};
    chatTheme[3] = {"color":"yellow", "theme":"warning"};

    //在线用户/好友/群组切换
    $('#contact-user-tab').on('click', 'li', function(){
        $('#contact-user-tab').find('.active').removeClass('active');
        $(this).addClass('active');
        var chatContact = $('#chat-contact');
        var role = $(this).attr('attr-data');
        var isInit = $(this).attr('attr-init');
        chatContact.find('li.dynamic').remove();
        if(isInit == 1){
            chatContact.append($('#contact-hidden-'+role).html());
        }else{
            if(role == 'friend'){
                msg = new Object();
                msg.user_id = user.user_id;
                msg.cmd = 'getFriend';
                ws.send($.toJSON(msg));
            }else if(role == 'group'){
                msg = new Object();
                msg.user_id = user.user_id;
                msg.cmd = 'getGroup';
                ws.send($.toJSON(msg));
            }
        }
    });


    //点击联系人创建聊天框
    $('#chat-contact').on('click', 'a.chat-contact-user', function(){
        var curUser = $.evalJSON($(this).attr('attr-data'));
        var dialog = dialogContainer.find('#chat-dialog-' + curUser.user_id);
        if(dialog.length){
            dialog.find('input').focus();
            return ;
        }
        var chatItem = dialogContainer.find('.chat-item');
        var index = chatItem.length % 4;
        dialogContainer.prepend(temp.html());
        var curChar = dialogContainer.find('.chat-item:first');
        curChar.attr('id','chat-dialog-' + curUser.user_id).attr('attr-data', $.toJSON(curUser));
        curChar.find('.box-title').text(curUser.username);
        curChar.find('.box').removeClass('box-primary direct-chat-primary').addClass('box-'+chatTheme[index].theme + ' direct-chat-'+chatTheme[index].theme);
        curChar.find('button.chat-send').removeClass('btn-primary').addClass('btn-'+chatTheme[index].theme);
        curChar.find('span.badge').removeClass('bg-light-blue').addClass('bg-'+chatTheme[index].color);
        curChar.find('[data-toggle="tooltip"]').tooltip();
        curChar.find('.box').boxWidget();
        curChar.find('input.chat-message').focus();

        //1.未读消息清0; 2.请求未读消息
        var pullContainer = $(this).find('span.pull-right-container');
        if(pullContainer.length){
            pullContainer.remove();
            msg = new Object();
            msg.sender_id = curUser.user_id;
            msg.receiver_id = user.user_id;
            msg.cmd = 'unreadMessage';
            ws.send($.toJSON(msg));
        }else {
            getHistoryMsg(curUser.user_id, user.user_id);
        }
        return true;
    });

    //创建群聊天室
    $('#chat-contact').on('click', 'a.chat-contact-group', function(){
        var curGroup = $.evalJSON($(this).attr('attr-data'));
        var dialog = dialogContainer.find('#chat-dialog-group-' + curGroup.group_id);
        if(dialog.length){
            dialog.find('input').focus();
            return ;
        }
        var index = 2;//主题色绿色
        dialogContainer.prepend(temp.html());
        var curChar = dialogContainer.find('.chat-item:first');
        curChar.attr('id','chat-dialog-group-' + curGroup.group_id).attr('attr-data', $.toJSON(curGroup)).attr('attr-role','group');
        curChar.find('.box-title').text(curGroup.group_name+' ('+curGroup.user_count+')');
        curChar.find('.box').removeClass('box-primary direct-chat-primary').addClass('box-'+chatTheme[index].theme + ' direct-chat-'+chatTheme[index].theme);
        curChar.find('button.chat-send').removeClass('btn-primary').addClass('btn-'+chatTheme[index].theme);
        curChar.find('span.badge').removeClass('bg-light-blue').addClass('bg-'+chatTheme[index].color);
        curChar.find('[data-toggle="tooltip"]').tooltip();
        curChar.find('.box').boxWidget();
        curChar.find('input.chat-message').focus();
        //getHistoryMsg(curUser.user_id, user.user_id);
        return true;
    });

    //发送消息
    dialogContainer.on('click', '.chat-send', function(){
        var dialog = $(this).parents('div.chat-item:first');
        var role = dialog.attr('attr-role');
        var curData = $.evalJSON(dialog.attr('attr-data'));
        var direct = dialog.find('div.direct-chat-messages');
        var directChat = dialog.find('div.direct-chat-messages-frame');
        var msg = dialog.find('input.chat-message').val();
        var time = GetDateT();
        if(!msg.length){
            return false;
        }
        msg = htmlEncode(msg);//转义html
        dialog.find('input.chat-message').val('');

        var msgHtml =
            '<div class="chat-msg-item">' +
              '<div class="direct-chat-msg chat-float-right right">' +
                '<div class="direct-chat-info clearfix">' +
                  '<span class="direct-chat-name pull-right">'+user.username+'</span>' +
                  '<span class="direct-chat-timestamp pull-left" style="display: none;">'+time+'</span> ' +
                '</div> ' +
                '<img class="direct-chat-img" src="/static/uploads/'+user.avatar+'" alt="avatar"> ' +
                '<div class="direct-chat-text">'+msg+'</div> ' +
              '</div>' +
            '</div>';
        directChat.append(msgHtml);
        var h = directChat.height();
        direct.scrollTop(h);
        if(role == 'group'){
            send(user.user_id, curData.group_id, msg, 0);
        }else{
            send(user.user_id, curData.user_id, msg, 1);
        }
        //webSocket发送消息到服务器
        //send(user.user_id, curUser.user_id, msg);

    });

    //加载更多历史消息
    dialogContainer.on('click', '.chat-load-more span', function(){
        var dialog = $(this).parents('div.chat-item:first');
        var curUser = $.evalJSON(dialog.attr('attr-data'));
        var direct = dialog.find('div.direct-chat-messages');
        var last_msg_id = $(this).parent('.chat-load-more').attr('attr-data');
        $(this).find('i.fa-refresh').show();
        //webSocket发送消息到服务器
        getHistoryMsg(curUser.user_id, user.user_id, 0, last_msg_id);

    });

    //鼠标经过消息显示消息时间
    dialogContainer.on('mouseenter', '.direct-chat-text', function(){
        $(this).parent('.direct-chat-msg').find('span.direct-chat-timestamp').show();
    });

    //鼠标离开消息隐藏消息时间
    dialogContainer.on('mouseleave', '.direct-chat-text', function(){
        $(this).parent('.direct-chat-msg').find('span.direct-chat-timestamp').hide();
    });

    //按下回车键发送消息
    dialogContainer.on('keydown', 'input.chat-message', function(e){
        if (e.keyCode == 13) {
            dialogContainer.find('.chat-send').click();
        }
        return true;
    });
});
/** ----------------------  end  -------------------------- **/


/** ----------------------  工具函数  -------------------------- **/
function xssFilter(val) {
    val = val.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\x22/g, '&quot;').replace(/\x27/g, '&#39;');
    return val;
}

function parseXss(val) {
    for (var i = 1; i < 20; i++) {
        val = val.replace('#' + i + '#', '<img src="/static/img/face/' + i + '.gif" />');
    }
    val = val.replace('&amp;', '&');
    return val;
}

function GetDateT(time_stamp) {
    var d;
    d = new Date();

    if (time_stamp) {
        d.setTime(time_stamp * 1000);
    }
    var h, i, s;
    h = d.getHours();
    i = d.getMinutes();
    s = d.getSeconds();

    h = ( h < 10 ) ? '0' + h : h;
    i = ( i < 10 ) ? '0' + i : i;
    s = ( s < 10 ) ? '0' + s : s;
    return h + ":" + i + ":" + s;
}

/**
 * @desc html字符转义
 * @author Jec
 * @param msg
 * @returns {*}
 */
function htmlEncode(msg){
    var spanObj = $('#htmlEncode');
    if(!spanObj.length){
        var spanObj = $('<span id="htmlEncode" style="display: none"></span>');
        $('body').append(spanObj);
    }
    return spanObj.text(msg).html();
}

/**
 * @desc 判断是否是数组
 * @param obj
 * @returns {boolean}
 */
function isArray(obj){
    if(Array.isArray){
        return Array.isArray(obj);
    }else{
        return Object.prototype.toString.call(obj)==="[object Array]";
    }
}

/**
 * @desc php的empty函数
 * @param mixedVar
 * @returns {boolean}
 */
function empty (mixedVar) {
    var undef;
    var key;
    var i;
    var len;
    var emptyValues = [undef, null, false, 0, '', '0'];
    for (i = 0, len = emptyValues.length; i < len; i++) {
        if (mixedVar === emptyValues[i]) {
            return true;
        }
    }
    if (typeof mixedVar === 'object') {
        for (key in mixedVar) {
            if (mixedVar.hasOwnProperty(key)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

function getRequest() {
    var url = location.search; // 获取url中"?"符后的字串
    var theRequest = new Object();
    if (url.indexOf("?") != -1) {
        var str = url.substr(1);

        strs = str.split("&");
        for (var i = 0; i < strs.length; i++) {
            var decodeParam = decodeURIComponent(strs[i]);
            var param = decodeParam.split("=");
            theRequest[param[0]] = param[1];
        }

    }
    return theRequest;
}

/** ----------------------  end  -------------------------- **/