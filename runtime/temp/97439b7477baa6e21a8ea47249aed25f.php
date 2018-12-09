<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:45:"./application/index/view/admin\role_info.html";i:1520481924;}*/ ?>
<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>管理员信息编辑</title>

    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/iCheck/custom.css" rel="stylesheet">
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
        <div class="row">
            <div class="col-sm-12">
                <div class="ibox float-e-margins">
                    <div class="ibox-title">
                        <h5>编辑角色资料 <small>包括名称和权限</small></h5>
                        <div class="ibox-tools">
                            <a href="<?php echo U('admin/role_list'); ?>">
                                <i class="fa fa-reply-all" ></i>
                            </a>
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                        </div>
                    </div>
                    <div class="ibox-content">
                        <form method="post" action="<?php echo U('admin/role_handle'); ?>" class="form-horizontal m-t" id="signupForm">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">角色名称</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="请输入角色名称" name="role_name" class="form-control" required="" aria-required="true" value="<?php echo $info['role_name']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">角色描述</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="请输入角色描述" name="role_desc" class="form-control" required="" aria-required="true" value="<?php echo $info['role_desc']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            
                            <div class="form-group">
                                <label class="col-sm-2 control-label">权限分配</label>
                                <div class="col-sm-10">
                                    <div class="ncap-account-container" style="border-top:none;">
                                    <h4>
                                        <input id="cls_full" onclick="choosebox(this)" type="checkbox">
                                        <label>全选</label>
                                    </h4>
                                    </div>
                                    <?php if(is_array($modules) || $modules instanceof \think\Collection || $modules instanceof \think\Paginator): if( count($modules)==0 ) : echo "" ;else: foreach($modules as $kk=>$menu): ?>
                                        <div class="ncap-account-container" style="border-top:none;">
                                            <h4>
                                                <label><?php echo $group[$kk]; ?></label>
                                                <input value="1" cka="mod-<?php echo $kk; ?>" type="checkbox">
                                                <label>全部</label>
                                            </h4>
                                            <ul class="ncap-account-container-list">
                                                <?php if(is_array($menu) || $menu instanceof \think\Collection || $menu instanceof \think\Paginator): if( count($menu)==0 ) : echo "" ;else: foreach($menu as $key=>$vv): ?>
                                                    <label class="checkbox-inline">
                                                        <input name="act_list[]" value="<?php echo $vv['id']; ?>" <?php if($vv['enable'] == 1): ?>checked<?php endif; ?> ck="mod-<?php echo $kk; ?>" type="checkbox" ><?php echo $vv['name']; ?>
                                                    </label>
                                                <?php endforeach; endif; else: echo "" ;endif; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; endif; else: echo "" ;endif; ?>
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <div class="col-sm-4 col-sm-offset-2">
                                    <input type="hidden" name="act" value="<?php echo $act; ?>">
                                    <input type="hidden" name="role_id" value="<?php echo $info['role_id']; ?>">
                                    <button class="btn btn-primary" type="submit">保存内容</button>
                                    <a href="<?php echo U('admin/role_list'); ?>">
                                        <button class="btn btn-white" type="button"><i class="fa fa-reply-all" >返回</i></button>
                                    </a>
                                </div>
                            </div>
                        </form>
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
    <script>
        $(document).ready(function () {
            $('.i-checks').iCheck({
                checkboxClass: 'icheckbox_square-green',
                radioClass: 'iradio_square-green',
            });
        });
    </script>
    <script type="text/javascript">
        $(document).ready(function(){
            $(":checkbox[cka]").click(function(){
                var $cks = $(":checkbox[ck='"+$(this).attr("cka")+"']");
                if($(this).is(':checked')){
                    $cks.each(function(){$(this).prop("checked",true);});
                }else{
                    $cks.each(function(){$(this).removeAttr('checked');});
                }
            });
        });

        function choosebox(o){
            var vt = $(o).is(':checked');
            if(vt){
                $('input[type=checkbox]').prop('checked',vt);
            }else{
                $('input[type=checkbox]').removeAttr('checked');
            }
        }
    </script>

</body>

</html>
