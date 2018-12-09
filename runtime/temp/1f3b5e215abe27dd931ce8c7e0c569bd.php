<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:51:"./application/index/view/pacificocean\new_info.html";i:1522046814;}*/ ?>
<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>微信资料</title>

    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/iCheck/custom.css" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
    

</head>

<body class="gray-bg"> 
    <div style="position:fixed; right:3px;z-index: 999;padding-top: 3px;">
        <a href="javascript:history.go(-1)" title="返回" >
            <button class="btn btn-primary btn-circle" type="button"><i class="fa fa-reply"></i></button>
        </a>
        <!-- <a href="<?php echo U('pacificocean/new_list'); ?>" title="返回新人表" >
            <button class="btn btn-primary btn-circle" type="button" style="width: 50px;height: 30px;order-radius: 11px;"><i class="fa fa-reply"></i>新人表</button>
        </a> -->
        <a href="javascript:location.replace(location.href);" title="刷新" >
            <button class="btn btn-primary btn-circle" type="button" ><i class="fa fa-refresh"></i></button>
        </a>  
    </div>
    <div class="wrapper wrapper-content">
        <div class="row animated fadeInRight">
            <div class="col-sm-4">
                <div class="ibox float-e-margins">
                    <div class="ibox-title">
                        <h5>个人资料</h5>
                    </div>
                    <div>
                        <div class="ibox-content profile-content">
                            <!-- <img alt="image" class="img-responsive" src="/blue/public/static/img/profile_big.jpg"> -->
                            <h4><strong><?php echo $info['name']; ?></strong></h4>
                            <div class="row">
                                <div class="col-sm-4">
                                    <img alt="image" class="img-responsive" src="<?php echo $info['wx']['headimgurl']; ?>">
                                </div>
                                <div class="col-sm-6">
                                    <span class="glyphicon glyphicon-user" aria-hidden="true">微信昵称：<?php echo $info['wx']['nickname']; ?></span>
                                </div>
                                <div class="col-sm-6" style="padding-top: 10px;">
                                    <i class="fa fa-transgender"> 性别：<?php echo $info['sex']; ?></i>
                                </div>
                                <div class="col-sm-6" style="padding-top: 10px;">
                                    <i class="fa fa-phone"> 电话 : <?php echo $info['phone']; ?> </i>
                                </div>
                                <div class="col-sm-6" style="padding-top: 10px;">
                                    <span class="glyphicon glyphicon-fire" aria-hidden="true">班次：<?php echo $info['class']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="ibox-content profile-content">
                            <h4><i class="fa fa-map-marker"></i> <?php echo $info['wx']['address']; ?></h4>
                            <h5>
                                    关于我
                                </h5>
                            <p>
                                <?php echo $info['other_content']; ?>。
                            </p>
                            <p>注册时间：<?php echo $info['time']; ?></p>
                            <div class="row m-t-lg">
                                <div class="col-sm-4">
                                    <span class="bar">5,3,9,6,5,9,7,3,5,2</span>
                                    <h5><strong>169</strong> 文章</h5>
                                </div>
                                <div class="col-sm-4">
                                    <span class="line">5,3,9,6,5,9,7,3,5,2</span>
                                    <h5><strong>28</strong> 关注</h5>
                                </div>
                                <div class="col-sm-4">
                                    <span class="bar">5,3,2,-1,-3,-2,2,3,5,2</span>
                                    <h5><strong>240</strong> 关注者</h5>
                                </div>
                            </div>
                            <div class="user-button">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <button type="button" class="btn btn-primary btn-sm btn-block"><i class="fa fa-envelope"></i> 发送消息</button>
                                    </div>
                                    <div class="col-sm-6">
                                        <button type="button" class="btn btn-default btn-sm btn-block"><i class="fa fa-coffee"></i> 赞助</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-8">
                <div class="ibox float-e-margins">
                    <div class="ibox-title">
                        <h5>最新动态</h5>
                        <div class="ibox-tools">
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                            <a class="close-link">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="ibox-content" style="width:100%;height:580px; overflow-y:scroll; border:0px solid;">
                        <div>
                            <div class="feed-activity-list">
                                <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                  <div class="feed-element">
                                        <a href="profile.html#" class="pull-left">
                                            <img class="img-circle" src="<?php echo $info['wx']['headimgurl']; ?>">
                                        </a>
                                        <div class="media-body ">
                                            <small class="pull-right text-navy"><?php echo $vo['log_time_1']; ?></small>
                                            <strong><?php echo $vo['new_name']; ?></strong>
                                            <br>
                                            <small class="text-muted">ip: <?php echo $vo['log_ip']; ?></small>
                                            <div class="well">
                                                <?php echo $vo['log_info']; ?>。
                                            </div>
                                            <div class="actions">
                                                <span class="glyphicon glyphicon-time" aria-hidden="true"></span>&nbsp;<?php echo $vo['log_time']; ?>
                                                <!-- <a class="btn btn-xs btn-danger"><i class="fa fa-heart"></i> 收藏</a> -->
                                            </div>
                                        </div>
                                    </div>  
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </div>
                            <button class="btn btn-primary btn-block m"><span class="glyphicon glyphicon-console" aria-hidden="true" style="padding-right: 50px;"><</span>已经到底啦...</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- 全局js -->
    <script src="/blue/public/static/js/jquery.min.js?v=2.1.4"></script>
    <script src="/blue/public/static/js/bootstrap.min.js?v=3.3.6"></script>

    <!-- 自定义js -->
    <script src="/blue/public/static/js/content.js?v=1.0.0"></script>

    <!-- jQuery Validation plugin javascript-->
    <script src="/blue/public/static/js/plugins/validate/jquery.validate.min.js"></script>
    <script src="/blue/public/static/js/plugins/validate/messages_zh.min.js"></script>
    <script src="/blue/public/static/js/demo/form-validate-demo.js"></script>
    <!-- layerDate plugin javascript -->
    <script src="/blue/public/static/js/plugins/layer/laydate/laydate.js"></script>
    <!-- iCheck -->
    <script src="/blue/public/static/js/plugins/iCheck/icheck.min.js"></script>
    <!-- Peity -->
    <script src="/blue/public/static/js/plugins/peity/jquery.peity.min.js"></script>
    <!-- Peity -->
    <script src="/blue/public/static/js/demo/peity-demo.js"></script>
    <script>
        $(document).ready(function () {
            $('.i-checks').iCheck({
                checkboxClass: 'icheckbox_square-green',
                radioClass: 'iradio_square-green',
            });
        });
    </script>
    <script>
        //日期范围限制
        var start = {
            elem: '#start',
            format: 'YYYY/MM/DD hh:mm:ss',
            min: laydate.now(), //设定最小日期为当前日期
            max: '2099-06-16 23:59:59', //最大日期
            istime: true,
            istoday: false,
            choose: function (datas) {
                end.min = datas; //开始日选好后，重置结束日的最小日期
                end.start = datas //将结束日的初始值设定为开始日
            }
        };
        var end = {
            elem: '#end',
            format: 'YYYY/MM/DD hh:mm:ss',
            min: laydate.now(),
            max: '2099-06-16 23:59:59',
            istime: true,
            istoday: false,
            choose: function (datas) {
                start.max = datas; //结束日选好后，重置开始日的最大日期
            }
        };
        laydate(start);
        laydate(end);
    </script>

    

</body>

</html>
