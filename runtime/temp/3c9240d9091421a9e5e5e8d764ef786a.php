<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:57:"./application/index/view/pacificocean\act_apply_list.html";i:1522050502;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>活动报名单</title>
    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/bootstrap-table/bootstrap-table.min.css" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
</head>

<body class="gray-bg">
    <div style="position:fixed; right:3px;z-index: 999;padding-top: 3px;">  
        <a href="javascript:location.replace(location.href);" title="刷新" >
            <button class="btn btn-primary btn-circle" type="button" ><i class="fa fa-refresh"></i></button>
        </a> 
    </div>
    <div class="wrapper wrapper-content animated fadeInRight">
        <div class="ibox float-e-margins">
            <div class="ibox-content">
                <div class="row row-lg">
                    <div class="col-sm-12">
                        <!-- Example Events -->
                        <div class="example-wrap">
                            <h4 class="example-title"><?php echo $course_name; ?> —— 新人签到表</h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <div class="col-sm-5">
                                        <a href="<?php echo U('pacificocean/new_add_list'); ?>" >
                                            <button type="button" class="btn btn-outline btn-default"><i class="fa fa-reply-all">返回创说会</i></button>
                                        </a>
                                    </div>
                                    <div class="col-sm-3">
                                        <form method="post" action="<?php echo U('report/new_sign_list_export'); ?>" enctype="multipart/form-data">
                                           <input type="hidden" name="id" value="<?php echo $id; ?>">
                                           <button type="submit" class="btn btn-outline btn-default">
                                                <i class="glyphicon glyphicon-plus" aria-hidden="true">导出签到表</i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-sm-3">
                                        <form id="form-import" method="post" action="<?php echo U('report/new_apply_list_import'); ?>" enctype="multipart/form-data">
                                           <input type="file" name="file">
                                           <input type="hidden" name="id" value="<?php echo $id; ?>">
                                           <button type="button" id="file_upload" class="btn btn-outline btn-default">
                                                <i class="glyphicon glyphicon-plus" aria-hidden="true">导入数据</i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <table id="exampleTableEvents" data-height="465" data-mobile-responsive="true">
                                    <thead>
                                        <tr>
                                            <!-- <th data-field="state" data-checkbox="true"></th> -->
                                            <th>新人ID</th>
                                            <th>名字</th>
                                            <th>经度</th>
                                            <th>纬度</th>
                                            <th>报名时间</th>
                                            <th>状态</th>
                                            <th>是否邀请</th>
                                            <th width="50">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                            <tr>
                                                <!-- <td data-field="state" data-checkbox="true"></td> -->
                                                <td align="center" class="">
                                                    <div><?php echo $vo['id']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['longitude']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['latitude']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['time']; ?></div>
                                                </td>
                                                
                                                <td align="center" class="">
                                                    <?php if($vo['status'] == 1): ?>
                                                        <span class="label label-warning">已签到</span>
                                                    <?php elseif($vo['status'] == 0): ?>
                                                        <span class="label label-default">未签到</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td align="center" class="">
                                                    <?php if($vo['is_invite'] == 1): ?>
                                                        <span class="label label-warning">已邀请</span>
                                                    <?php elseif($vo['is_invite'] == 0): ?>
                                                        <span class="label label-default">未邀请</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td align="center" class="">
                                                    <a href="<?php echo U('pacificocean/new_info',array('openid'=>$vo['openid'])); ?>"><button class="btn btn-info btn-sm demo4" >查看资料</button>
                                                    </a>
                                                    </a>
                                                    <button class="btn btn-danger btn-sm demo5" value="<?php echo $vo['role_id']; ?>">删除</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; else: echo "" ;endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- End Example Events -->
                    </div>
                </div>
            </div>
        </div>
        <!-- End Panel Other -->
    </div>

    <!-- 全局js -->
    <script src="/blue/public/static/js/jquery.min.js?v=2.1.4"></script>
    <script src="/blue/public/static/js/bootstrap.min.js?v=3.3.6"></script>

    <!-- 自定义js -->
    <script src="/blue/public/static/js/content.js?v=1.0.0"></script>


    <!-- Bootstrap table -->
    <script src="/blue/public/static/js/plugins/bootstrap-table/bootstrap-table.min.js"></script>
    <script src="/blue/public/static/js/plugins/bootstrap-table/bootstrap-table-mobile.min.js"></script>
    <script src="/blue/public/static/js/plugins/bootstrap-table/locale/bootstrap-table-zh-CN.min.js"></script>

    <!-- Peity -->
    <script src="/blue/public/static/js/demo/bootstrap-table-demo.js"></script>
    <!-- layer -->
    <script src="/blue/public/static/plugins/layer/layer.min.js"></script>

    <script>
        $(function() {
            // 提交事件
            $('#file_upload').on('click', function() {
                $.ajax({
                    url: '/blue/index.php/index/report/new_apply_list_import',
                    type: "post",
                    data: new FormData($('#form-import')[0]),
                    dataType: "json",
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        layer.load(0, {shade: [0.3, '#393D49']});
                    },
                    success: function(data) {
                        layer.closeAll();
                        console.log(data);
                        if (data == 1) {
                            layer.msg('导入成功，推送消息成功!', {icon:1,time:2000}, function() {
                                
                            });
                        }else {
                            layer.msg('导入失败!', {icon:5,time:2000});
                        }
                    }
                });

            });

        });
    </script>
    

</body>


</html>
