<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:46:"./application/index/view/admin\admin_info.html";i:1522308058;}*/ ?>
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
                        <h5>编辑管理员资料 <small>包括密码和角色</small></h5>
                        <div class="ibox-tools">
                            <a href="<?php echo U('admin/admin_list'); ?>" placeholder="返回管理员列表">
                                <i class="fa fa-reply-all" ></i>
                            </a>
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                        </div>
                    </div>
                    <div class="ibox-content">
                        <form method="post" action="<?php echo U('admin/admin_handle'); ?>" class="form-horizontal m-t" id="signupForm">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">用户名</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="请输入用户名" name="name" class="form-control" required="" aria-required="true" value="<?php echo $info['name']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Email</label>
                                <div class="col-sm-10">
                                    <input placeholder="请输入邮箱地址" name="email" class="form-control" type="email" required="" aria-required="true" value="<?php echo $info['email']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">描述</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="管理员描述" name="info" class="form-control" required="" aria-required="true" value="<?php echo $info['info']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <?php if($act == add): ?>
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">登录密码</label>
                                    <div class="col-sm-10">
                                        <input type="password" placeholder="请输入管理员密码" name="password" class="form-control" required="" aria-required="true" value="<?php echo $info['password']; ?>">
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">登录密码</label>
                                    <div class="col-sm-10">
                                        <input type="password" placeholder="请输入管理员密码" name="password" class="form-control" value="<?php echo $info['password']; ?>">
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="hr-line-dashed"></div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">所属角色</label>
                                <div class="col-sm-2">
                                    <select class="form-control m-b" name="role_id" required="" aria-required="true">
                                        <?php if(is_array($role) || $role instanceof \think\Collection || $role instanceof \think\Paginator): $i = 0; $__LIST__ = $role;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i;?>
                                            <option value="<?php echo $vo['role_id']; ?>" <?php if($vo[role_id] == $info[role_id]): ?> selected="selected"<?php endif; ?> ><?php echo $vo['role_name']; ?></option>
                                        <?php endforeach; endif; else: echo "" ;endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">上级管理员</label>
                                <div class="col-sm-2">
                                    <select class="form-control m-b" name="pid">
                                        <?php if(is_array($pid_admin) || $pid_admin instanceof \think\Collection || $pid_admin instanceof \think\Paginator): $i = 0; $__LIST__ = $pid_admin;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$item): $mod = ($i % 2 );++$i;?>
                                            <option value="<?php echo $item['uid']; ?>" <?php if($item[uid] == $info[pid]): ?> selected="selected"<?php endif; ?> ><?php echo $item['info']; ?></option>
                                        <?php endforeach; endif; else: echo "" ;endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <div class="col-sm-4 col-sm-offset-2">
                                    <input type="hidden" name="act" value="<?php echo $act; ?>">
                                    <input type="hidden" name="uid" value="<?php echo $info['uid']; ?>">
                                    <button class="btn btn-info" type="submit">保存内容</button>
                                    <a href="<?php echo U('admin/admin_list'); ?>">
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
    
</body>

</html>
