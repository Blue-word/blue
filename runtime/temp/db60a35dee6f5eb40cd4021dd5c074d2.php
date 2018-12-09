<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:55:"./application/index/view/pacificocean\new_add_list.html";i:1522047525;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创说会列表</title>
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
                            <h4 class="example-title">创说会列表</h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <a href="<?php echo U('pacificocean/new_add_info'); ?>">
                                        <button type="button" class="btn btn-outline btn-default">
                                            <i class="glyphicon glyphicon-plus" aria-hidden="true">添加创说会</i>
                                        </button>
                                    </a>
                                    <button type="button" class="btn btn-outline btn-default">
                                        <i class="glyphicon glyphicon-heart" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline btn-default">
                                        <i class="glyphicon glyphicon-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <table id="exampleTableEvents" data-height="560" data-mobile-responsive="true" data-sort-name="First" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <!-- <th data-field="state" data-checkbox="true"></th> -->
                                            <th data-sortable="true">活动ID</th>
                                            <th data-sortable="true">活动标题</th>
                                            <th data-sortable="true">活动地点</th>
                                            <!-- <th data-sortable="true">已报名人数</th>
                                            <th data-sortable="true">总名额</th> -->
                                            <th data-sortable="true">发布时间</th>
                                            <th data-sortable="true">签到人员</th>
                                            <th data-sortable="true">发起者/审核者</th>
                                            <!-- <th data-sortable="true">审核者</th> -->
                                            <th data-sortable="true">状态</th>
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
                                                    <div><?php echo $vo['title']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['place']; ?></div>
                                                </td>
                                                <!-- <td align="center" class="" width="180">
                                                    <?php echo $vo['number']; ?>
                                                </td> -->
                                                <td align="center" class="">
                                                    <div><?php echo $vo['time']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div>　
                                                        <a href="<?php echo U('pacificocean/act_apply_list',array('id'=>$vo['id'])); ?>" style="color: #676a6c">
                                                            <button type="button" class="btn btn-outline btn-default" href="<?php echo U('pacificocean/act_apply_list'); ?>"><?php echo $vo['apply_number']; ?> / 查看名单</button>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['admin_name']; ?> / <?php echo $vo['audit_name']; ?></div>
                                                </td>
                                                <!-- <td align="center" class="">
                                                    <div><?php echo $vo['audit_name']; ?></div>
                                                </td> -->
                                                <td align="center" class="asd">
                                                    <?php if($vo['status'] == 0): ?>
                                                        <span class="label label-warning label1">待审核</span>
                                                    <?php elseif($vo['status'] == 1): ?>
                                                        <span class="label label-primary label2">进行中</span>
                                                    <?php elseif($vo['status'] == 2): ?>
                                                        <span class="label label-default label3">已结束</span>
                                                    <?php elseif($vo['status'] == -1): ?>
                                                        <span class="label label-default label4">已驳回</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td align="center" class="">
                                                    <?php if(($vo['status'] == 0) OR ($vo['status'] == -1)): ?>
                                                        <button class="btn btn-warning btn-sm demo4" value="<?php echo $vo['id']; ?>">审核</button>
                                                    <?php elseif($vo['status'] == 1): ?>
                                                        <button class="btn btn-warning btn-sm demo5" value="<?php echo $vo['id']; ?>">驳回</button>
                                                    <?php endif; ?> 
                                                    <a href="<?php echo U('pacificocean/new_add_view',array('id'=>$vo['id'])); ?>"><button class="btn btn-info btn-sm" >查看</button>
                                                    </a>
                                                    <a href="<?php echo U('pacificocean/new_add_info',array('id'=>$vo['id'])); ?>"><button class="btn btn-primary btn-sm" >编辑</button>
                                                    </a>
                                                    <button class="btn btn-danger btn-sm demo6" value="<?php echo $vo['role_id']; ?>">删除</button>
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

            $('#exampleTableEvents').on('click', '.demo4', function (event) {
                swal(
                    {
                        title: "您确定要『通过』此活动审核吗",
                        text: "活动通过后仍可驳回",
                        type: "warning",
                        showCancelButton: true,
                        showRollButton: true,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "是的，我『通过』！",
                        cancelButtonText: "让我再考虑一下…",
                        closeOnConfirm: false,
                        closeOnCancel: false,
                        allowOutsideClick: true,
                    }, 
                    function (isConfirm) {
                        if (isConfirm) {
                            var id = $(event.currentTarget).attr('value');
                            // console.log(id);
                            // console.log($('.asd span:first'));
                            $.ajax({
                                type: 'POST',
                                url: 'new_add_handle',
                                dataType: 'json',
                                data: {id: id,act: 'ajax',status: 1},
                                success: function(index){
                                    // console.log(id);
                                    $(event.currentTarget).replaceWith($(`<button class="btn btn-warning btn-sm demo5" value="${id}">驳回</button>`));
                                    // $('.label4').replaceWith($(`<span class="label label-primary label2">进行中</span>`));
                                    // $("<span class='label label-default label4'>已驳回</span>").replaceWith($(`<span class="label label-primary label2">进行中</span>`));
                                    swal("授理成功！", "您已经授理此提现。", "success");
                                },
                                error:function(index) {
                                    console.log(index);
                                    swal("授理失败！", "您的授理出现错误！", "error");
                                },
                            });
                        } else {
                            swal("已取消", "您取消了授理操作！", "error");
                        }
                    }
                );
            });

            $('#exampleTableEvents').on('click', '.demo5', function (event) {
                swal(
                    {
                        title: "您确定要『驳回』此活动审核吗",
                        text: "驳回后可重新审核该活动！",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "是的，我『驳回』！",
                        cancelButtonText: "让我再考虑一下…",
                        closeOnConfirm: false,
                        closeOnCancel: false,
                        allowOutsideClick: true,
                    }, 
                    function (isConfirm) {
                        if (isConfirm) {
                            var id = $(event.currentTarget).attr('value');
                            $.ajax({
                                type: 'POST',
                                url: 'new_add_handle',
                                dataType: 'json',
                                data: {id: id,act: 'ajax',status: -1},
                                success: function(index){
                                    // console.log(index);
                                    $(event.currentTarget).replaceWith($(`<button class="btn btn-warning btn-sm demo4" value="${id}">审核</button>`));
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
                    }
                );
            });


            $('#exampleTableEvents').on('click', '.demo6', function (event) {
                swal(
                    {
                        title: "您确定要『删除』此活动吗",
                        text: "删除后数据将无法恢复！",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "是的，我『删除』！",
                        cancelButtonText: "让我再考虑一下…",
                        closeOnConfirm: false,
                        closeOnCancel: false
                    }, 
                    function (isConfirm) {
                        if (isConfirm) {
                            var id = $(event.currentTarget).attr('value');
                            // console.log(id);
                            $.ajax({
                                type: 'POST',
                                url: 'new_add_handle',
                                dataType: 'json',
                                data: {id: id,act: 'del'},
                                success: function(index){
                                    //console.log(index);
                                    $(event.currentTarget).parent().parent().remove();
                                    swal("删除成功！", "您已经删除此活动。", "success");
                                },
                                error:function(index) {
                                    //console.log(index);
                                    swal("删除失败！", "您的删除出现错误！", "error");
                                },
                            });
                        } else {
                            swal("已取消", "您取消了删除操作！", "error");
                        }
                    }
                );
            });
        });
    </script>

</body>


</html>
