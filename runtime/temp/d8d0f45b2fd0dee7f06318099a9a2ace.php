<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:46:"./application/index/view/goods\goods_view.html";i:1544336782;}*/ ?>
<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>培训课程信息</title>

    <link rel="shortcut icon" href="favicon.ico"> <link href="/blue/public/static/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/blue/public/static/css/font-awesome.css?v=4.4.0" rel="stylesheet">
    <link href="/blue/public/static/css/plugins/iCheck/custom.css" rel="stylesheet">
    <link href="/blue/public/static/css/animate.css" rel="stylesheet">
    <link href="/blue/public/static/css/style.css?v=4.1.0" rel="stylesheet">
    <!-- 百度地图 -->
    <!-- <style type="text/css">
    #container {width: 1230px;height: 400px;overflow: hidden;margin:0;font-family:"微软雅黑";}
    </style>
    <script type="text/javascript" src="http://api.map.baidu.com/api?v=2.0&ak=uzZhQDkARtTIbiTOydzupoQE7wQ4WkNn"></script> -->

</head>

<body class="gray-bg">
    <div style="position:fixed; right:3px;z-index: 999;padding-top: 3px;">  
        <a href="javascript:location.replace(location.href);" title="刷新" >
            <button class="btn btn-primary btn-circle" type="button" ><i class="fa fa-refresh"></i></button>
        </a> 
    </div>
    <div class="row  border-bottom white-bg dashboard-header">
        <div class="col-sm-12">
            <div id="container"></div>
        </div>
    </div>
    <div class="wrapper wrapper-content animated fadeInRight">
        <div class="row">
            <div class="col-sm-12">
                <div class="ibox float-e-margins">
                    <div class="ibox-title">
                        <h5>商品信息编辑&nbsp;&nbsp;&nbsp;<small>包括定位和课程信息</small></h5>
                        <div class="ibox-tools">
                            <a href="<?php echo U('pacificocean/course_list'); ?>">
                                <i class="fa fa-reply-all" ></i>
                            </a>
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                        </div>
                    </div>
                    <div class="ibox-content">
                        <form method="post" action="<?php echo U('goods/goods_handle'); ?>" class="form-horizontal m-t" id="signupForm">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">商品名称</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="商品名称" name="title" id="title" class="form-control" required="" aria-required="true" value="<?php echo $info['title']; ?>">
                                    商品名称
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">价格</label>
                                <div class="col-sm-2">
                                    <input name="price" max="9999" type="number" class="form-control" required="" aria-required="true" value="<?php echo $info['price']; ?>">
                                    单位：元（¥）
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">商品详情</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="请输入标题" name="content" class="form-control" required="" aria-required="true" value="<?php echo $info['content']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">商品类别</label>
                                <div class="col-sm-2">
                                    <select class="form-control m-b" name="cate" required="" aria-required="true">
                                        <option value="">请选择类别</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                    </select>
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">商品轮播图片</label>
                                <div class="col-sm-8">
                                    <input id="album" class="file" type="file" name="picture[]" multiple required="" aria-required="true">
                                </div>
                            </div>
                            <!-- <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">地点</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="请输入课程地点" name="place" class="form-control" required="" aria-required="true" value="<?php echo $info['place']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">讲师</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="请输入讲师名称" name="lecturer" class="form-control" required="" aria-required="true" value="<?php echo $info['lecturer']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">课程人数</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="课程人数" name="number" class="form-control" required="" aria-required="true" value="<?php echo $info['number']; ?>">
                                </div>
                            </div>
                            <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">课程简介</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="简介" name="info" class="form-control" required="" aria-required="true" value="<?php echo $info['info']; ?>">
                                </div>
                            </div> -->
                            <!-- <div class="hr-line-dashed"></div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">上架日期范围：</label>
                                <div class="col-sm-10">
                                    <input placeholder="开始日期" class="form-control layer-date" id="start" name="start_time" onclick="laydate({istime: true, format: 'YYYY-MM-DD hh:mm:ss'})" value="<?php echo $info['start_time']; ?>" required="" aria-required="true">
                                    <input placeholder="结束日期" class="form-control layer-date" id="end" name="end_time" onclick="laydate({istime: true, format: 'YYYY-MM-DD hh:mm:ss'})" value="<?php echo $info['end_time']; ?>">
                                </div>
                            </div> -->
                            <div class="hr-line-dashed"></div>
                            <div id="hidden_val">
                                <?php if(is_array($pic_list) || $pic_list instanceof \think\Collection || $pic_list instanceof \think\Paginator): if( count($pic_list)==0 ) : echo "" ;else: foreach($pic_list as $key=>$albumFo): ?>
                                    <input type="hidden" name="picture[]" value="<?php echo $albumFo; ?>"/>
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-4 col-sm-offset-2">
                                    <input type="hidden" name="act" value="<?php echo $act; ?>">
                                    <input type="hidden" name="id" value="<?php echo $info['id']; ?>">
                                    <button class="btn btn-primary" type="submit">提交</button>
                                    <a href="<?php echo U('goods/goods_list'); ?>">
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
        // 百度地图API功能
        // var map = new BMap.Map("allmap");     
        var map = new BMap.Map("container");
        var point = new BMap.Point(116.331398,39.897445);
        map.centerAndZoom(point,12);

        //添加地图类型控件
        var NavigationControl = {type: BMAP_NAVIGATION_CONTROL_ZOOM,offset: new BMap.Size(10, 10)}   //评议缩放控件类型
        map.addControl(new BMap.NavigationControl(NavigationControl));   //平移缩放
        map.addControl(new BMap.GeolocationControl({anchor: BMAP_ANCHOR_BOTTOM_RIGHT}));  //定位
        map.addControl(new BMap.ScaleControl({offset: new BMap.Size(80, 25)}));    //比例尺 
        map.addControl(new BMap.OverviewMapControl());  //缩略地图 
        map.addControl(new BMap.MapTypeControl({        //地图类型
            mapTypes:[
                BMAP_NORMAL_MAP,
                BMAP_HYBRID_MAP
            ]}));     
          
        map.setCurrentCity("北京");          // 设置地图显示的城市 此项是必须设置的
        map.enableScrollWheelZoom(true);     //开启鼠标滚轮缩放

        // 定义一个控件类，即function    
        function ZoomControl() {
            // 设置默认停靠位置和偏移量  
            this.defaultAnchor = BMAP_ANCHOR_TOP_LEFT;
            this.defaultOffset = new BMap.Size(40, 10);
        }
        ZoomControl.prototype = new BMap.Control();
        ZoomControl.prototype.initialize = function(map) {
            // 创建一个DOM元素  
            var div = document.createElement("div");
            // 添加文字说明  
            // var form = div.appendChild(document.createElement("form"));
            var input0 = div.appendChild(document.createElement("input"));
            input0.setAttribute("id", "suggestId");
            input0.setAttribute("placeholder", "搜地点");
            input0.style = "padding: 9px 0;border-left: 10px solid transparent;border-right: 27px solid transparent;line-height: 20px;font-size: 16px;height: 38px;color: #333;position: relative;border-radius: 2px 0 0 2px;";
            var input1 = div.appendChild(document.createElement("div"));
            input1.setAttribute("id", "searchResultPanel");
            input1.style = "border:1px solid #C0C0C0;width:150px;height:auto; display:none;";
            // 设置样式  
            // div.style = "padding: 9px 0;border-left: 10px solid transparent;border-right: 27px solid transparent;line-height: 20px;font-size: 16px;height: 38px;color: #333;position: relative;border-radius: 2px 0 0 2px;";
            // div.style.padding = "9px 0";
            // div.style.border-left = "10px solid transparent";
            div.style.borderRight = "27px solid transparent";
            // div.style.line-height = "20px";
            // div.style.font-size = "16px";
            // div.style.height = "38px";
            // div.style.color = "#333";
            // div.style.position = "relative";
            // div.style.border-radius = "2px 0 0 2px";
            // div.style.cursor = "pointer";
            // div.style.border = "0px solid gray";
            // div.style.backgroundColor = "white";
            map.getContainer().appendChild(div);
            // 将DOM元素返回  
            return div;
        }
        // 创建控件实例    
        var myZoomCtrl = new ZoomControl();    
        // 添加到地图当中    
        map.addControl(myZoomCtrl);
        //单击获取点击的经纬度
        // map.addEventListener("click",function(e){
        //  alert(e.point.lng + "," + e.point.lat);
        // });

        // 百度地图API功能
        function G(id) {
            return document.getElementById(id);
        }
        var ac = new BMap.Autocomplete(    //建立一个自动完成的对象
            {"input" : "suggestId"
            ,"location" : map
        });

        ac.addEventListener("onhighlight", function(e) {  //鼠标放在下拉列表上的事件
        var str = "";
            var _value = e.fromitem.value;
            var value = "";
            if (e.fromitem.index > -1) {
                value = _value.province +  _value.city +  _value.district +  _value.street +  _value.business;
            }    
            str = "FromItem<br />index = " + e.fromitem.index + "<br />value = " + value;
            
            value = "";
            if (e.toitem.index > -1) {
                _value = e.toitem.value;
                value = _value.province +  _value.city +  _value.district +  _value.street +  _value.business;
            }    
            str += "<br />ToItem<br />index = " + e.toitem.index + "<br />value = " + value;
            G("searchResultPanel").innerHTML = str;
        });

        var myValue;
        ac.addEventListener("onconfirm", function(e) {    //鼠标点击下拉列表后的事件
            var _value = e.item.value;
            myValue = _value.province +  _value.city +  _value.district +  _value.street +  _value.business;
            G("searchResultPanel").innerHTML ="onconfirm<br />index = " + e.item.index + "<br />myValue = " + myValue;
            setPlace();
        });

        function setPlace(){
            map.clearOverlays();    //清除地图上所有覆盖物
            function myFun(){
                var pp = local.getResults().getPoi(0).point;    //获取第一个智能搜索的结果
                map.centerAndZoom(pp, 18);
                // var myIcon = new BMap.Icon("/blue/public/static/img/position.png", new BMap.Size(34,32));   //创建新定位图标
                // var marker = new BMap.Marker(pp,{icon:myIcon});  // 创建标注
                var marker = new BMap.Marker(pp);  // 创建标注
                map.addOverlay(marker);              // 将标注添加到地图中
                marker.addEventListener("click",getAttr);  //鼠标点击获取经纬度
                // marker.setAnimation(BMAP_ANIMATION_BOUNCE); //跳动的动画
                marker.enableDragging();           // 不可拖拽
                function getAttr(){
                    var p = marker.getPosition();       //获取marker的位置
                    alert("当前位置是:" + "    " + p.lng + "," + p.lat);   
                }
            }
            var local = new BMap.LocalSearch(map, { //智能搜索
              onSearchComplete: myFun
            });
            local.search(myValue);
        }
    </script>
    <script>
        //日期范围限制
        var start = {
            elem: '#start',
            format: 'YYYY/MM/DD hh:mm:ss',
            min: laydate.now(), //设定最小日期为当前日期
            max: '2099-06-16 23:59:59', //最大日期
            istime: true,
            istoday: false,
            choose: function (datas) {
                end.min = datas; //开始日选好后，重置结束日的最小日期
                end.start = datas //将结束日的初始值设定为开始日
            }
        };
        var end = {
            elem: '#end',
            format: 'YYYY/MM/DD hh:mm:ss',
            min: laydate.now(),
            max: '2099-06-16 23:59:59',
            istime: true,
            istoday: false,
            choose: function (datas) {
                start.max = datas; //结束日选好后，重置开始日的最大日期
            }
        };
        laydate(start);
        laydate(end);
    </script>

    

</body>

</html>
