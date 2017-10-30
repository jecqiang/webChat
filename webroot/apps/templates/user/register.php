<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Chat | 注册</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="/static/adminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/static/adminLTE/bower_components/font-awesome/css/font-awesome.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="/static/adminLTE/bower_components/Ionicons/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="/static/adminLTE/dist/css/AdminLTE.min.css">
    <!-- iCheck -->
    <link rel="stylesheet" href="/static/adminLTE/plugins/iCheck/square/blue.css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
</head>
<body class="hold-transition register-page">
<div class="register-box">
    <div class="register-logo">
        <a href="../../index2.html"><b>Chat</b></a>
    </div>
    <div class="register-box-body">
        <p class="login-box-msg">注册账号，开始畅聊吧！</p>

        <form action="register" method="post" id="register_form">
            <div class="form-group has-feedback">
                <input type="text" class="form-control" name="username" id="username" placeholder="昵称">
                <span class="glyphicon glyphicon-user form-control-feedback"></span>
            </div>
            <div class="form-group has-feedback">
                <input type="email" class="form-control" name="email" id="email" placeholder="邮箱">
                <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
            </div>
            <div class="form-group has-feedback">
                <input type="password" class="form-control" name="password" id="password" placeholder="密码">
                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
            </div>
            <div class="form-group has-feedback">
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="确认密码">
                <span class="glyphicon glyphicon-log-in form-control-feedback"></span>
            </div>
            <div class="row">
                <div class="col-xs-8">
                    <a href="login" class="text-center">我已经有账号了</a>
                </div>
                <!-- /.col -->
                <div class="col-xs-4">
                    <button type="submit" class="btn btn-primary btn-block btn-flat" id="register">注册</button>
                </div>
                <!-- /.col -->
            </div>
        </form>


    </div>
    <!-- /.form-box -->
</div>
<!-- /.register-box -->

<!-- jQuery 3 -->
<script src="/static/adminLTE/bower_components/jquery/dist/jquery.min.js"></script>
<!-- Bootstrap 3.3.7 -->
<script src="/static/adminLTE/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<!-- iCheck -->
<script src="/static/adminLTE/plugins/iCheck/icheck.min.js"></script>
<script>
    $(function () {
        $('input').iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%' // optional
        });
        var url = 'http://im.swoole.com/';
        $('#register').click(function(event){
            event.preventDefault();
            $('#register_form').find('div.has-error').removeClass('has-error');
            $('#register_form').find('span.help-block').remove();
            var usernameDom = $('#username');
            var emailDom = $('#email');
            var passwordDom = $('#password');
            var confirmDom = $('#confirm_password');
            var username = usernameDom.val();
            var email = emailDom.val();
            var password = passwordDom.val();
            var confirm_password = confirmDom.val();
            var submit = true;
            if(!username.length){
                usernameDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">昵称不能为空</span>');
                submit = false;
            }
            if(!email.length){
                emailDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">邮箱不能为空</span>');
                submit = false;
            }
            if(password.length < 6){
                passwordDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">密码不能小于6位</span>');
                submit = false;
            }
            if(!confirm_password.length){
                confirmDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">确认密码不能为空</span>');
                submit = false;
            }else if(password != confirm_password){
                confirmDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">昵称不能为空</span>');
                submit = false;
            }
            if(!submit){
                return false;
            }
            var requestData = {
                'username' : username,
                'email' : email,
                'password' : password,
                'confirm_password' : confirm_password
            };
            $.ajax({
                method : "POST",
                dataType : "json",
                data : requestData,
                url : url + "user/create",
            }).done(function(json) {
                if(json.code == 1){
                    window.location.href = url + 'chat/index';
                }else{
                    var error = json.data;
                    var len = json.data.length;
                    for(var i=0; i<len; i++){
                        $('#'+error[i].field).parent('div.form-group').addClass('has-error').append('<span class="help-block">'+error[i].msg+'</span>');
                    }
                }
            });

            return false;
        });

    });
</script>
</body>
</html>
