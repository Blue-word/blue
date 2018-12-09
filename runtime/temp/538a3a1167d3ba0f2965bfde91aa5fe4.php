<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:44:"./application/index/view/admin\log_list.html";i:1520490744;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员日志</title>
    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/bootstrap-table/bootstrap-table.min.css" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
    <!-- Sweet Alert -->
    <link href="/blue/public/static/css/plugins/sweetalert/sweetalert.css" rel="stylesheet">
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
                            <h4 class="example-title">管理员日志 <small>系统设置日志列表</small></h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <a href="<?php echo U('admin/role_info'); ?>">
                                        <button type="button" class="btn btn-outline btn-default">
                                            <i class="glyphicon glyphicon-plus" aria-hidden="true">添加角色</i>
                                        </button>
                                    </a>
                                    <button type="button" class="btn btn-outline btn-default">
                                        <i class="glyphicon glyphicon-heart" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline btn-default">
                                        <i class="glyphicon glyphicon-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <table id="exampleTableEvents" data-height="465" data-mobile-responsive="true" data-sort-name="First" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <th data-field="state" data-checkbox="true"></th>
                                            <th data-sortable="true">管理员名称</th>
                                            <th data-sortable="true">日志信息</th>
                                            <th data-sortable="true">登录ip</th>
                                            <th data-sortable="true">操作地址</th>
                                            <th data-sortable="true">时间</th>
                                            <!-- <th width="50">操作</th> -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                            <tr>
                                                <td data-field="state" data-checkbox="true"></td>
                                                <td>
                                                    <div><?php echo $vo['admin_name']; ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo $vo['log_info']; ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo $vo['log_ip']; ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo $vo['log_url']; ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo $vo['log_time']; ?></div>
                                                </td>
                                                <!-- <td align="center" class="">
                                                    <a href="<?php echo U('admin/role_info',array('role_id'=>$vo['role_id'])); ?>"><button class="btn btn-danger btn-sm demo4" >编辑</button>
                                                    </a>
                                                    <button class="btn btn-warning btn-sm demo5" value="<?php echo $vo['role_id']; ?>">删除</button>
                                                </td> -->
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
    <!-- Sweet alert -->
    <script src="/blue/public/static/js/plugins/sweetalert/sweetalert.min.js"></script>
    
    <script>
        $(document).ready(function () {

            $('.demo1').click(function () {
                swal({
                    title: "欢迎使用SweetAlert",
                    text: "Sweet Alert 是一个替代传统的 JavaScript Alert 的漂亮提示效果。"
                });
            });

            $('.demo2').click(function () {
                swal({
                    title: "太帅了",
                    text: "小手一抖就打开了一个框",
                    type: "success"
                });
            });

            $('.demo3').click(function () {
                swal({
                    title: "您确定要删除这条信息吗",
                    text: "删除后将无法恢复，请谨慎操作！",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "删除",
                    closeOnConfirm: false
                }, function () {
                    swal("删除成功！", "您已经永久删除了这条信息。", "success");
                });
            });

            // $('.demo4').click(function () {
            //     swal({
            //         title: "您确定要『授理』此用户提现吗",
            //         text: "企业支付到零线后将无法撤回！",
            //         type: "warning",
            //         showCancelButton: true,
            //         confirmButtonColor: "#DD6B55",
            //         confirmButtonText: "是的，我『授理』！",
            //         cancelButtonText: "让我再考虑一下…",
            //         closeOnConfirm: false,
            //         closeOnCancel: false
            //     },
            //     function (isConfirm) {
            //         if (isConfirm) {
            //             var id = $(".demo4").val();
            //             $.ajax({
            //                 type: 'POST',
            //                 url: 'withdraw_audit',
            //                 dataType: 'json',
            //                 data: {id: id,act: 'success'},
            //                 success: function(index){
            //                     //console.log(id);
            //                     console.log(index);
            //                     swal("授理成功！", "您已经授理此提现。", "success");
            //                 },
            //                 error:function(index) {
            //                     // console.log(data);
            //                     console.log('data');
            //                     swal("授理失败！", "您的授理出现错误！", "error");
            //                 },
            //             });
            //         } else {
            //             swal("已取消", "您取消了授理操作！", "error");
            //         }
            //     });
            // });

            $('.demo5').click(function () {
                swal({
                    title: "您确定要『驳回』此用户提现吗",
                    text: "驳回后可重新审核该提现！",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "是的，我『驳回』！",
                    cancelButtonText: "让我再考虑一下…",
                    closeOnConfirm: false,
                    closeOnCancel: false
                },
                function (isConfirm) {
                    if (isConfirm) {
                        var id = $(".demo5").val();
                        $.ajax({
                            type: 'POST',
                            url: 'role_handle',
                            dataType: 'json',
                            data: {id: id,act: 'error'},
                            success: function(index){
                                //console.log(id);
                                console.log(index);
                                swal("驳回成功！", "您已经驳回此提现。", "success");
                            },
                            error:function(index) {
                                // console.log(data);
                                console.log('data');
                                swal("驳回失败！", "您的驳回出现错误！", "error");
                            },
                        });
                    } else {
                        swal("已取消", "您取消了授理操作！", "error");
                    }
                });
            });


        });
    </script>

</body>


</html>
