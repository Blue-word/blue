<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:58:"./application/index/view/pacificocean\class_type_list.html";i:1520303643;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班次类型列表</title>
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
                        <div class="col-sm-8 example-wrap">
                            <h4 class="example-title">班次类型列表&nbsp;&nbsp;&nbsp;<small>提示：删除班次会导致已选择该班次的新人的班次数据置为1（即下表班次ID为1的状态）</small></h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <button type="button" class="btn btn-outline btn-default" data-toggle="modal" data-target="#myModal2">
                                        <i class="glyphicon glyphicon-plus" aria-hidden="true">添加班次</i>
                                    </button>
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
                                            <th data-sortable="true">班次ID</th>
                                            <th data-sortable="true">班次名</th>
                                            <th width="50">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                            <tr>
                                                <td data-field="state" data-checkbox="true"></td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['id']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    
                                                    <?php if($vo['id'] != 1): ?>
                                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal2" onclick="Values('<?php echo $vo['id']; ?>','<?php echo $vo['name']; ?>')">编辑
                                                        </button>
                                                        <button class="btn btn-danger btn-sm demo5" value="<?php echo $vo['id']; ?>">删除</button>
                                                    <?php else: ?>
                                                        <span style="color: #ed5565;">系统保留班次类型，不可操作</span>
                                                    <?php endif; ?>
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
        <!-- modal -->
        <div class="modal inmodal" id="myModal2" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content animated flipInY">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                        <h4 class="modal-title">添加班次</h4>
                    </div>
                    <form method="post" action="<?php echo U('Pacificocean/class_type_handle'); ?>" class="form-horizontal m-t" id="signupForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <div class="col-sm-12">
                                <input type="text" placeholder="请班次名称" id="class_type_name" name="name" class="form-control" type="text" required="" aria-required="true" value="<?php echo $info['name']; ?>">
                                <input type="hidden" id="class_type_id" name="id" value="<?php echo $info['id']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                    </form>
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
        $("#myModal2").modal('hide');
        function Values(ID,NAME){
            $("#class_type_id").val(ID);
            $("#class_type_name").val(NAME);
        }
    </script>
    <script>
        $(document).ready(function () {
            $('#exampleTableEvents').on('click', '.demo5', function (event) {
                swal(
                    {
                        title: "您确定要『删除』此管理员吗",
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
                                url: 'class_type_handle',
                                dataType: 'json',
                                data: {id: id,act: 'del'},
                                success: function(index){
                                    // console.log(index);
                                    $(event.currentTarget).parent().parent().remove();
                                    swal("删除成功", "您已经删除此管理员。", "success");
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
