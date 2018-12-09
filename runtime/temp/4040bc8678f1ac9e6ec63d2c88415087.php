<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:39:"./application/index/view/test\test.html";i:1521187889;}*/ ?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="utf-8">
<title>Excel导入</title>
<link href="/blue/public/static/css/bootstrap.min.css" rel="stylesheet">
<style type="text/css">
    * {padding: 10px}
</style>
</head>
<body>
<h2>Excel导入--DEMO</h2>
<div class='wst-tbar' style="height:50px">
<form method="post" action="<?php echo U('test/import'); ?>" enctype="multipart/form-data">
   <input type="file" name="file" style="width:200px">
   <button type="submit" class="btn btn-success glyphicon glyphicon-plus">导入</button>
</form>
</div>
<div class="table-responsive">
   <table class="table table-bordered">
   <thead>
     <tr> 
     	<th>id</th>
        <th>姓名</th> 
        <th>身份证</th>
        <th>手机号</th>
        <th>推荐工号</th>
        <th>班次</th>
        <th>性别</th>
        <th>其他内容</th>
    </tr>
   </thead>
   <tbody>
    <?php if(is_array($user) || $user instanceof \think\Collection || $user instanceof \think\Paginator): $key = 0; $__LIST__ = $user;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($key % 2 );++$key;?>
     <tr>
        <td><?php echo $vo['id']; ?></td>
        <td><?php echo $vo['name']; ?></td>
        <td><?php echo $vo['id_number']; ?></td>
        <td><?php echo $vo['phone']; ?></td>
        <td><?php echo $vo['recommend_number']; ?></td>
        <td><?php echo $vo['class']; ?></td>
        <td><?php echo $vo['sex']; ?></td>
        <td><?php echo $vo['other_content']; ?></td>
    </tr>
    <?php endforeach; endif; else: echo "" ;endif; ?>
   </tbody>
 </table>
</div>
</body>
</html>   