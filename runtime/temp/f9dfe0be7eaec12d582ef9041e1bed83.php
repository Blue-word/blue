<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:46:"./application/index/view/admin\right_list.html";i:1520847602;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>权限列表</title>
    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/bootstrap-table/bootstrap-table.min.css" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
    <!-- Sweet Alert -->
    <link href="/blue/public/static/css/plugins/sweetalert/sweetalert.css" rel="stylesheet">
    <!-- mycss -->
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
                            <h4 class="example-title">权限资源管理</h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <a href="<?php echo U('admin/right_info'); ?>">
                                        <button type="button" class="btn btn-outline btn-default">
                                            <i class="glyphicon glyphicon-plus" aria-hidden="true">添加权限</i>
                                        </button>
                                    </a>
                                    <button type="button" class="btn btn-outline btn-default">
                                        <i class="glyphicon glyphicon-heart" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline btn-default">
                                        <i class="glyphicon glyphicon-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <table id="exampleTableEvents" data-height="465" data-mobile-responsive="true" style="table-layout:fixed;" data-sort-name="First" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <th data-field="state" data-checkbox="true"></th>
                                            <th data-sortable="true">名称</th>
                                            <th data-sortable="true">所属分组</th>
                                            <th data-sortable="true">权限码</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                            <tr>
                                                <td data-field="state" data-checkbox="true"></td>
                                                <td>
                                                    <div><?php echo $vo['name']; ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo $vo['group']; ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo $vo['right']; ?></div>
                                                </td>
                                                <td>
                                                    <a href="<?php echo U('admin/right_info',array('id'=>$vo['id'])); ?>"><button class="btn btn-danger btn-sm demo4" >编辑</button>
                                                    </a>
                                                    <button class="btn btn-warning btn-sm demo6" value="<?php echo $vo['id']; ?>">删除</button>
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
            $('#exampleTableEvents').on('click', '.demo6', function (event) {
                swal(
                    {
                        title: "您确定要『删除』此权限吗",
                        text: "删除后数据将无法恢复！",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "是的，我『删除』！",
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
                                url: 'right_handle',
                                dataType: 'json',
                                data: {id: id,act: 'del'},
                                success: function(index){
                                    console.log(index);
                                    $(event.currentTarget).parent().parent().remove();
                                    swal("删除成功", "您已经删除此权限。", "success");
                                },
                                error:function(index) {
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
