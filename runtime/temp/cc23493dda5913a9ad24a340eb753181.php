<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:48:"./application/index/view/report\data_source.html";i:1522326602;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据源</title>
    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/bootstrap-table/bootstrap-table.min.css" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
    <!-- Sweet Alert -->
    <link href="/blue/public/static/css/plugins/sweetalert/sweetalert.css" rel="stylesheet">
    <!-- 自定义css -->
    <link href="/blue/public/static/css/mycss.css" rel="stylesheet">
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
                            <h4 class="example-title">数据源列表</h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <form id="form-import" method="post" action="<?php echo U('report/data_source_import'); ?>" enctype="multipart/form-data">
                                       <input type="file" name="file">
                                       <button type="button" id="file_upload" class="btn btn-outline btn-default">
                                            <i class="glyphicon glyphicon-plus" aria-hidden="true">导入数据</i>
                                        </button>
                                    </form>
                                </div>
                                <table id="exampleTableEvents" data-height="560" data-mobile-responsive="true" data-sort-name="First" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <th data-sortable="true">id</th>
                                            <th data-sortable="true">中支代码</th>
                                            <th data-sortable="true">中支</th>
                                            <th data-sortable="true">营销服务部</th>
                                            <th data-sortable="true">营业区代码</th>
                                            <th data-sortable="true">营业部代码</th>
                                            <th data-sortable="true">营业部</th>
                                            <th data-sortable="true">营业组代码</th>
                                            <th data-sortable="true">营业组名称</th>
                                            <th data-sortable="true">业务员代码</th>
                                            <th data-sortable="true">业务员姓名</th>
                                            <th data-sortable="true">身份证</th>
                                            <th data-sortable="true">职级简称</th>
                                            <th data-sortable="true">分类</th>
                                            <th data-sortable="true">入司时间</th>
                                            <!-- <th width="50">操作</th> -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                            <tr>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['id']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['zhongzhi_code']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['zhongzhi']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['market_server_unit']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_area_code']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_unit_code']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_unit_name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_group_code']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_group_name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['salesman_code']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['salesman_name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['salesman_id_number']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['rank_name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['cate']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['time']; ?></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; else: echo "" ;endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="page"><?php echo $page; ?></div>
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
    <!-- layer -->
    <script src="/blue/public/static/plugins/layer/layer.min.js"></script>
    
    <script>

        $(function() {
            // 提交事件
            $('#file_upload').on('click', function() {
                $.ajax({
                    url: 'data_source_import',
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
                        // console.log(data);
                        if (data == 1) {
                            layer.msg('已导入!', {icon:1,time:2000}, function() {
                                
                            });
                        } else {
                            layer.msg('导入失败!', {icon:5,time:2000});
                        }
                    }
                });

            });

        });


        

    </script>

    <script>
        $(document).ready(function () {

            $('#exampleTableEvents').on('click', '.demo4', function (event) {
                swal(
                    {
                        title: "您确定要『通过』此课程审核吗",
                        text: "课程通过后仍可驳回",
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
                            var $span = $(event.currentTarget).parent().prev().find("span");
                            $.ajax({
                                type: 'POST',
                                url: 'course_handle',
                                dataType: 'json',
                                data: {id: id,act: 'ajax',status: 1},
                                success: function(index){
                                    $(event.currentTarget).replaceWith($(`<button class="btn btn-warning btn-sm demo5" value="${id}">驳回</button>`));
                                    $span.replaceWith('<span class="label label-primary label2">进行中</span>');
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
                        title: "您确定要『驳回』此课程审核吗",
                        text: "驳回后可重新审核该课程！",
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
                            var $span = $(event.currentTarget).parent().prev().find("span");
                            $.ajax({
                                type: 'POST',
                                url: 'course_handle',
                                dataType: 'json',
                                data: {id: id,act: 'ajax',status: -1},
                                success: function(index){
                                    // console.log(index);
                                    $(event.currentTarget).replaceWith($(`<button class="btn btn-warning btn-sm demo4" value="${id}">审核</button>`));
                                    $span.replaceWith('<span class="label label-default label2">已驳回</span>');
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
                        title: "您确定要『删除』此课程提现吗",
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
                                url: 'course_handle',
                                dataType: 'json',
                                data: {id: id,act: 'del'},
                                success: function(index){
                                    // console.log(index);
                                    $(event.currentTarget).parent().parent().remove();
                                    swal("删除成功！", "您已经删除此课程。", "success");
                                },
                                error:function(index) {
                                    console.log(index);
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
