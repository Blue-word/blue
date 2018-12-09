<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:44:"./application/index/view/goods\new_list.html";i:1522312484;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新人列表</title>
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
                            <h4 class="example-title">新人列表 <small>包括微信资料和新人调查表信息</small></h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <!-- <a href="<?php echo U('pacificocean/course_info'); ?>">
                                        <button type="button" class="btn btn-outline btn-default">
                                            <i class="glyphicon glyphicon-plus" aria-hidden="true">添加新人</i>
                                        </button>
                                    </a> -->
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
                                            <!-- <th data-field="state" data-checkbox="true"></th> -->
                                            <th data-sortable="true">新人ID</th>
                                            <th data-sortable="true">姓名</th>
                                            <th data-sortable="true">性别</th>
                                            <!-- <th data-sortable="true">班次</th> -->
                                            <!-- <th data-sortable="true">微信资料</th> -->
                                            <th data-sortable="true">注册时间</th>
                                            <!-- <th data-sortable="true">状态</th> -->
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
                                                    <div><?php echo $vo['sex']; ?></div>
                                                </td>
                                                <!-- <td align="center" class="">
                                                    <div><?php echo $vo['class']; ?></div>
                                                </td> -->
                                                <!-- <td align="center" class="">
                                                    <div>　
                                                        <a href="<?php echo U('pacificocean/new_info',array('id'=>$vo['id'])); ?>" style="color: #676a6c">
                                                            <button type="button" class="btn btn-outline btn-default"><span class="glyphicon glyphicon-resize-full" aria-hidden="true"> 查看资料</span></button>
                                                        </a>
                                                    </div>
                                                </td> -->
                                                <td align="center" class="">
                                                    <div><?php echo $vo['time']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <a href="<?php echo U('pacificocean/new_info',array('openid'=>$vo['openid'])); ?>"><button class="btn btn-info btn-sm" ><span class="glyphicon glyphicon-resize-full" aria-hidden="true"> 查看</span></button>
                                                    </a>
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
    
    

</body>


</html>
