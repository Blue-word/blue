<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:41:"./application/index/view/admin\login.html";i:1520687338;}*/ ?>
<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录</title>
    <link rel="shortcut icon" href="/blue/public/static/img/logo_1.png"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">

    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
    <!--[if lt IE 9]>
    <meta http-equiv="refresh" content="0;ie.html" />
    <![endif]-->
    <script>if(window.top !== window.self){ window.top.location = window.location;}</script>
</head>

<body class="gray-bg" style="background-image: url(/blue/public/static/img/login_bg2.jpg);background-size: 100% 100%;-moz-background-size:100% 100%;-webkit-background-size: 100% 100%;">

    <div class="middle-box text-center loginscreen  animated fadeInDown">
        <div style="padding-top: 80px;">
            <div>
                <h1 class="logo-name" style="">太平人寿</h1>
            </div>
            <h3 style="color: #e6e6e6;padding-top: 20px;">欢迎使用 后台管理系统</h3>

            <form class="m-t" role="form" action="login" method="post">
                <div class="form-group">
                    <input type="text" name="name" class="form-control" placeholder="用户名" required="" style="border-radius: 20px;">
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="form-control" placeholder="密码" required="" style="border-radius: 20px;">
                </div>
                <button type="submit" class="btn btn-primary block full-width m-b">登 录</button>


                <p class="text-muted text-center"> <a href="login.html#"><small>忘记密码了？</small></a> | <a href="register.html">注册一个新账号</a>
                </p>

            </form>
        </div>
    </div>

    <!-- 全局js -->
    <script src="/blue/public/static/js/jquery.min.js?v=2.1.4"></script>
    <script src="/blue/public/static/js/bootstrap.min.js?v=3.3.6"></script>

    

</body>

</html>
