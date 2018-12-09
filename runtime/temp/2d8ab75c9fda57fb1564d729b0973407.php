<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:44:"./application/index/view/index\index_v1.html";i:1521598880;}*/ ?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>首页</title>
    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">

</head>

<body class="gray-bg">
    <div style="position:fixed; right:3px;z-index: 999;padding-top: 3px;">  
        <a href="javascript:location.replace(location.href);" title="刷新" >
            <button class="btn btn-primary btn-circle" type="button" ><i class="fa fa-refresh"></i></button>
        </a> 
    </div>
    <div class="wrapper wrapper-content">
        <div class="row">
            <div class="col-sm-3">
                <div class="ibox float-e-margins ibox-shadow">
                    
                    <div class="ibox-title ibox-border">
                        <span class="label label-success pull-right">月</span>
                        <h5>课程&nbsp;&nbsp;&nbsp;<div class="stat-percent font-bold text-navy"><?php echo $list['course_count']; ?> 
                        </div></h5>
                    </div>
                    <div class="ibox-content">
                        <h1 class="no-margins" style="text-align: center;"><?php echo $list['course_add_count']; ?></h1>
                        <?php if($list['course_status'] == 1): ?>
                            <div class="stat-percent font-bold text-success"><?php echo $list['course_percentage']; ?> <i class="fa fa-level-up"></i>
                            </div>
                        <?php else: ?>
                            <div class="stat-percent font-bold text-danger"><?php echo $list['course_percentage']; ?> <i class="fa fa-level-down"></i>
                            </div>
                        <?php endif; ?>
                        <small>新课程</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="ibox float-e-margins ibox-shadow">
                    <div class="ibox-title ibox-border">
                        <span class="label label-info pull-right">月</span>
                        <h5>新人&nbsp;&nbsp;&nbsp;<div class="stat-percent font-bold text-navy"><?php echo $list['new_count']; ?> 
                        </div></h5>
                    </div>
                    <div class="ibox-content">
                        <h1 class="no-margins" style="text-align: center;"><?php echo $list['new_add_count']; ?></h1>
                        <?php if($list['new_status'] == 1): ?>
                            <div class="stat-percent font-bold text-info"><?php echo $list['new_percentage']; ?> <i class="fa fa-level-up"></i>
                            </div>
                        <?php else: ?>
                            <div class="stat-percent font-bold text-danger"><?php echo $list['new_percentage']; ?> <i class="fa fa-level-down"></i>
                            </div>
                        <?php endif; ?>
                        <small>新增人员</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="ibox float-e-margins ibox-shadow">
                    <div class="ibox-title ibox-border">
                        <span class="label label-primary pull-right">月</span>
                        <h5>增员活动&nbsp;&nbsp;&nbsp;<div class="stat-percent font-bold text-navy"><?php echo $list['activity_count']; ?> 
                        </div></h5>
                    </div>
                    <div class="ibox-content">
                        <h1 class="no-margins" style="text-align: center;"><?php echo $list['activity_add_count']; ?></h1>
                        <?php if($list['activity_status'] == 1): ?>
                            <div class="stat-percent font-bold text-navy"><?php echo $list['activity_percentage']; ?> <i class="fa fa-level-up"></i>
                            </div>
                        <?php else: ?>
                            <div class="stat-percent font-bold text-danger"><?php echo $list['activity_percentage']; ?> <i class="fa fa-level-down"></i>
                            </div>
                        <?php endif; ?>
                        <small>新活动</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="ibox float-e-margins ibox-shadow">
                    <div class="ibox-title ibox-border">
                        <span class="label label-danger pull-right">月</span>
                        <h5>预留&nbsp;&nbsp;&nbsp;<div class="stat-percent font-bold text-navy"><?php echo $list['course_count']; ?> 
                        </div></h5>
                    </div>
                    <div class="ibox-content">
                        <h1 class="no-margins" style="text-align: center;">0</h1>
                        <?php if($list['visitor_status'] == 1): ?>
                            <div class="stat-percent font-bold text-navy">0% <i class="fa fa-level-down"></i>
                            </div>
                        <?php else: ?>
                            <div class="stat-percent font-bold text-danger">0% <i class="fa fa-level-down"></i>
                            </div>
                        <?php endif; ?>
                        <small>预留</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-5" style="padding-top: 30px;">
                <div class="ibox float-e-margins">
                    <div class="ibox-title ibox-heading" style="background-color: #f3f3f4;border-style: none;">
                        <div class="ibox-tools pull-right">
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                            <a class="close-link">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                        <h3><i class="fa fa-envelope-o"></i> 新消息<small>&nbsp;&nbsp;共<?php echo $list['new_log_count']; ?>条消息</small></h3>
                    </div>
                    <div class="ibox-content" style="width:100%;height:500px; overflow-y:scroll; border:0px solid;">
                        <div class="feed-activity-list">
                            <?php if(is_array($new_log) || $new_log instanceof \think\Collection || $new_log instanceof \think\Paginator): if( count($new_log)==0 ) : echo "" ;else: foreach($new_log as $key=>$vo1): ?>
                                <div class="feed-element">
                                    <div>
                                        <small class="pull-right"><?php echo $vo1['log_time_1']; ?></small>
                                        <strong><?php echo $vo1['new_name']; ?></strong>
                                        <div><?php echo $vo1['log_info']; ?>。</div>
                                        <small class="pull-left text-navy"><?php echo $vo1['log_time']; ?></small>
                                    </div>
                                </div>
                            <?php endforeach; endif; else: echo "" ;endif; ?>
                        </div>
                        <a href="<?php echo U('pacificocean/course_list'); ?>"><button class="btn btn-primary btn-block m-t"><span class="glyphicon glyphicon-hand-right" aria-hidden="true" style="padding-right: 20px;"></span> 加载更多...</button></a>
                    </div>
                </div>
            </div>

            <!-- <div class="col-sm-1"></div> -->

            <div class="col-sm-7">
                <div class="wrapper wrapper-content">
                    <div class="row animated fadeInRight">
                        <div class="col-sm-12">
                            <div class="ibox float-e-margins">
                                <div class="text-center float-e-margins p-md">
                                    <span>管理员日志&nbsp;&nbsp;<span class="glyphicon glyphicon-share-alt" aria-hidden="true"></span>&nbsp;点击更换布局风格：&nbsp;&nbsp;</span>
                                    <a href="#" class="btn btn-xs btn-primary" id="lightVersion">浅色</a>
                                    <a href="#" class="btn btn-xs btn-primary" id="darkVersion">深色</a>
                                    <a href="#" class="btn btn-xs btn-primary" id="leftVersion">布局切换</a>
                                </div>
                                <div class="" id="ibox-content" style="width:100%;height:500px; overflow-y:scroll; border:0px solid;">
                                    <div id="vertical-timeline" class="vertical-container light-timeline" style="margin-top: 0em;">
                                        <?php if(is_array($admin_log) || $admin_log instanceof \think\Collection || $admin_log instanceof \think\Paginator): if( count($admin_log)==0 ) : echo "" ;else: foreach($admin_log as $key=>$vo): ?>
                                            <div class="vertical-timeline-block">
                                                <div class="vertical-timeline-icon navy-bg">
                                                    <i class="fa fa-file-text"></i>
                                                </div>
                                                <div class="vertical-timeline-content">
                                                    <h2><?php echo $vo['log_info']; ?></h2>
                                                    <p>操作地址：<?php echo $vo['log_url']; ?></p>
                                                    <a class="btn btn-sm btn-primary">信息记录</a>
                                                    <span class="vertical-date">
                                                        IP：<?php echo $vo['log_ip']; ?> <br>
                                                        <small><?php echo $vo['log_time']; ?></small>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; endif; else: echo "" ;endif; ?>
                                        <div style="text-align: center;margin-left: 60px;">
                                            <a href="<?php echo U('pacificocean/course_list'); ?>" style="width: 538px;display: inline-block;margin: 0 auto;"><button class="btn btn-primary btn-block m-t"><span class="glyphicon glyphicon-hand-right" aria-hidden="true" style="padding-right: 20px;"></span> 加载更多...</button></a>
                                        </div>
                                        
                                    </div>

                                </div>
                            </div>
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

    <!-- jQuery UI -->
    <script src="/blue/public/static/js/plugins/jquery-ui/jquery-ui.min.js"></script>

    <!-- Sparkline线状图插件 -->
    <script src="/blue/public/static/js/plugins/sparkline/jquery.sparkline.min.js"></script>


    <script>
        $(document).ready(function () {
            // Local script for demo purpose only
            $('#lightVersion').click(function (event) {
                event.preventDefault()
                $('#ibox-content').removeClass('ibox-content');
                $('#vertical-timeline').removeClass('dark-timeline');
                $('#vertical-timeline').addClass('light-timeline');
            });

            $('#darkVersion').click(function (event) {
                event.preventDefault()
                $('#ibox-content').addClass('ibox-content');
                $('#vertical-timeline').removeClass('light-timeline');
                $('#vertical-timeline').addClass('dark-timeline');
            });

            $('#leftVersion').click(function (event) {
                event.preventDefault()
                $('#vertical-timeline').toggleClass('center-orientation');
            });
        });
    </script>


</body>

</html>
