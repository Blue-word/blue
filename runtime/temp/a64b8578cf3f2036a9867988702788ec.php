<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:48:"./application/index/view/report\detail_list.html";i:1522306521;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>明细表</title>
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
                            <h4 class="example-title">明细列表</h4>
                            <div class="example">
                                <div class="btn-group hidden-xs" id="exampleTableEventsToolbar" role="group">
                                    <form method="post" action="<?php echo U('report/detail_list_export'); ?>" enctype="multipart/form-data">
                                       <button type="submit" class="btn btn-outline btn-default">
                                            <i class="glyphicon glyphicon-plus" aria-hidden="true">导出签到表</i>
                                        </button>
                                    </form>
                                </div>
                                <table id="exampleTableEvents" data-height="560" data-mobile-responsive="true" data-sort-name="First" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <!-- <th data-field="state" data-checkbox="true"></th> -->
                                            <th data-sortable="true">中支</th>
                                            <th data-sortable="true">四级机构</th>
                                            <th data-sortable="true">营业部</th>
                                            <th data-sortable="true">营业组</th>
                                            <th data-sortable="true">新人姓名</th>
                                            <th data-sortable="true">身份证</th>
                                            <th data-sortable="true">提交问卷时间</th>
                                            <th data-sortable="true">签到是否有效</th>
                                            <th data-sortable="true">推荐人代码</th>
                                            <th data-sortable="true">推荐人姓名</th>
                                            <th data-sortable="true">推荐人职级</th>
                                            <th data-sortable="true">是否缴费</th>
                                            <th data-sortable="true">是否愿意拥有保险销售资格</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(is_array($list) || $list instanceof \think\Collection || $list instanceof \think\Paginator): if( count($list)==0 ) : echo "" ;else: foreach($list as $key=>$vo): ?>
                                            <tr>
                                                <!-- <td data-field="state" data-checkbox="true"></td> -->
                                                <td align="center" class="">
                                                    <div><?php echo $vo['organization']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['level4_organization']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_unit']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['business_group']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['new_name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['id_number']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['sign_time']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['sign_status']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['recommend_code']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['recommend_name']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['recommend_rank']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['is_payment']; ?></div>
                                                </td>
                                                <td align="center" class="">
                                                    <div><?php echo $vo['is_insurance_zige']; ?></div>
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
