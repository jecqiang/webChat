<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Chat | 登录</title>
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
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href="/"><b>Chat</b></a>
    </div>
    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg">登录您的账号，开始畅聊！</p>

        <form action="../../index2.html" method="post" id="login_form">
            <div class="form-group has-feedback">
                <input type="email" class="form-control" placeholder="电子邮箱" id="email">
                <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
            </div>
            <div class="form-group has-feedback">
                <input type="password" class="form-control" placeholder="密码" id="password">
                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
            </div>
            <div class="row">
                <div class="col-xs-8">
                    <div class="checkbox icheck">
                        <label>
                            <input type="checkbox"> 记住密码
                        </label>
                    </div>
                </div>
                <!-- /.col -->
                <div class="col-xs-4">
                    <button type="submit" class="btn btn-primary btn-block btn-flat" id="login">登录</button>
                </div>
                <!-- /.col -->
            </div>
        </form>

        <a href="#">我忘记了密码</a><br>
        <a href="register" class="text-center">注册账号</a>

    </div>
    <!-- /.login-box-body -->
</div>
<!-- /.login-box -->

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
    });

    var url = 'http://im.swoole.com/';
    $('#login').click(function(event){
        event.preventDefault();
        $('#login_form').find('div.has-error').removeClass('has-error');
        $('#login_form').find('span.help-block').remove();
        var emailDom = $('#email');
        var passwordDom = $('#password');
        var email = emailDom.val();
        var password = passwordDom.val();
        var submit = true;
        if(!email.length){
            emailDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">邮箱不能为空</span>');
            submit = false;
        }
        if(password.length < 6){
            passwordDom.parent('div.form-group').addClass('has-error').append('<span class="help-block">密码不能小于6位</span>');
            submit = false;
        }
        if(!submit){
            return false;
        }
        var requestData = {
            'email' : email,
            'password' : password,
        };
        $.ajax({
            method : "POST",
            dataType : "json",
            data : requestData,
            url : url + "user/doLogin",
        }).done(function(json) {
            console.log(json);
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

</script>
</body>
</html>
