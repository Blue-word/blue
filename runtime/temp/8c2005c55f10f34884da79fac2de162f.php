<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:41:"./application/index/view/liyou\index.html";i:1544346503;}*/ ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="">
  <meta name="keywords" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>BLOG index with sidebar & slider  | Amaze UI Examples</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="Cache-Control" content="no-siteapp"/>
  <link rel="icon" type="image/png" href="/blue/public/static/assets/i/favicon.png">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="icon" sizes="192x192" href="/blue/public/static/assets/i/app-icon72x72@2x.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="Amaze UI"/>
  <link rel="apple-touch-icon-precomposed" href="/blue/public/static/assets/i/app-icon72x72@2x.png">
  <meta name="msapplication-TileImage" content="/blue/public/static/assets/i/app-icon72x72@2x.png">
  <meta name="msapplication-TileColor" content="#0e90d2">
  <link href="/blue/public/static/assets/css/amazeui.min.css" rel="stylesheet">
  <link href="/blue/public/static/assets/css/app.css" rel="stylesheet">
  <style type="text/css">
  	.am-img-thumbnail.am-radius{
  		border-radius: 15px;
  		/*margin-left: 1rem;
  		margin-right: 1rem;*/
  	}
  	.blog-entry-img{
  		padding-left: 1rem;
  		padding-right: 1rem;
  	}
  	.blog-entry-article{
  		margin: 1rem 0;
  	}
  	/*.blog-entry-text{
  		text-align: center;
  	}*/
  	.blog-sidebar{
  		padding: 0 1rem 0 1rem;
  	}
  </style>
</head>

<body id="blog">

<header class="am-g am-g-fixed blog-fixed blog-text-center blog-header">
	<!-- <h2 class="am-hide-sm-only">HTML5</h2> -->
    <div class="am-u-sm-8 am-u-sm-centered">
        <!-- 历优U选 -->
        <img width="200" src="http://s.amazeui.org/media/i/brand/amazeui-b.png" alt="Amaze UI Logo"/>
        <!-- <h2 class="am-hide-sm-only">中国首个开源 HTML5 跨屏前端框架</h2> -->
    </div>
</header>
<hr>
<!-- nav start -->
<nav class="am-g am-g-fixed blog-fixed blog-nav">
<button class="am-topbar-btn am-topbar-toggle am-btn am-btn-sm am-btn-success am-show-sm-only blog-button" data-am-collapse="{target: '#blog-collapse'}" >地区切换<span class="am-sr-only">地区切换</span> <span class="am-icon-street-view"></span></button>

  <div class="am-collapse am-topbar-collapse" id="blog-collapse">
    <ul class="am-nav am-nav-pills am-topbar-nav">
      <li class="am-active"><a href="lw-index.html">南京</a></li>
      <li class="am-active"><a href="lw-index.html">苏州</a></li>
      <li class="am-active"><a href="lw-index.html">合肥</a></li>
      <!-- <li class="am-active"><a href="lw-index.html">首页</a></li>
      <li class="am-active"><a href="lw-index.html">首页</a></li>
      <li class="am-dropdown" data-am-dropdown>
        <a class="am-dropdown-toggle" data-am-dropdown-toggle href="javascript:;">
          首页布局 <span class="am-icon-caret-down"></span>
        </a>
        <ul class="am-dropdown-content">
          <li><a href="lw-index.html">1. blog-index-standard</a></li>         
          <li><a href="lw-index-nosidebar.html">2. blog-index-nosidebar</a></li>
          <li><a href="lw-index-center.html">3. blog-index-layout</a></li>
          <li><a href="lw-index-noslider.html">4. blog-index-noslider</a></li>
        </ul>
      </li>
      <li><a href="lw-article.html">标准文章</a></li>
      <li><a href="lw-img.html">图片库</a></li>
      <li><a href="lw-article-fullwidth.html">全宽页面</a></li>
      <li><a href="lw-timeline.html">存档</a></li> -->
    </ul>
    <!-- <form class="am-topbar-form am-topbar-right am-form-inline" role="search">
      <div class="am-form-group">
        <input type="text" class="am-form-field am-input-sm" placeholder="搜索">
      </div>
    </form> -->
  </div>
</nav>
<hr>
<!-- nav end -->

<!-- banner start -->
<div class="am-g am-g-fixed blog-fixed am-u-sm-centered blog-article-margin">
    <div data-am-widget="slider" class="am-slider am-slider-b1" data-am-slider='{&quot;controlNav&quot;:false}' >
    <ul class="am-slides">
      <li>
            <img src="/blue/public/static/assets/i/b1.jpg">
            <div class="blog-slider-desc am-slider-desc ">
                <div class="blog-text-center blog-slider-con">
                    <span><a href="" class="blog-color">Article &nbsp;</a></span>               
                    <h1 class="blog-h-margin"><a href="">总在思考一句积极的话</a></h1>
                    <p>那时候刚好下着雨，柏油路面湿冷冷的，还闪烁着青、黄、红颜色的灯火。
                    </p>
                    <span class="blog-bor">2015/10/9</span>
                    <br><br><br><br><br><br><br>                
                </div>
            </div>
      </li>
      <li>
            <img src="/blue/public/static/assets/i/b2.jpg">
            <div class="am-slider-desc blog-slider-desc">
                <div class="blog-text-center blog-slider-con">
                    <span><a href="" class="blog-color">Article &nbsp;</a></span>               
                    <h1 class="blog-h-margin"><a href="">总在思考一句积极的话</a></h1>
                    <p>那时候刚好下着雨，柏油路面湿冷冷的，还闪烁着青、黄、红颜色的灯火。
                    </p>
                    <span>2015/10/9</span>                
                </div>
            </div>
      </li>
      <li>
            <img src="/blue/public/static/assets/i/b3.jpg">
            <div class="am-slider-desc blog-slider-desc">
                <div class="blog-text-center blog-slider-con">
                    <span><a href="" class="blog-color">Article &nbsp;</a></span>               
                    <h1 class="blog-h-margin"><a href="">总在思考一句积极的话</a></h1>
                    <p>那时候刚好下着雨，柏油路面湿冷冷的，还闪烁着青、黄、红颜色的灯火。
                    </p>
                    <span>2015/10/9</span>                
                </div>
            </div>
      </li>
      <li>
            <img src="/blue/public/static/assets/i/b2.jpg">
            <div class="am-slider-desc blog-slider-desc">
                <div class="blog-text-center blog-slider-con">
                    <span><a href="" class="blog-color">Article &nbsp;</a></span>               
                    <h1 class="blog-h-margin"><a href="">总在思考一句积极的话</a></h1>
                    <p>那时候刚好下着雨，柏油路面湿冷冷的，还闪烁着青、黄、红颜色的灯火。
                    </p>
                    <span>2015/10/9</span>                
                </div>
            </div>
      </li>
    </ul>
    </div>
</div>
<!-- banner end -->
<!-- <ul class="am-nav am-nav-pills am-nav-justify">
  <li class="am-active"><a href="#">1</a></li>
  <li><a href="#">2</a></li>
  <li><a href="#">3</a></li>
  <li><a href="#">4</a></li>
  <li><a href="#">5</a></li>
  <li><a href="#">6</a></li>
</ul> -->

<!-- <ul class="am-nav am-nav-tabs am-nav-justify">
  <li class="am-active"><a href="#u-remen" onclick="u_chang('u-remen')">热门</a></li>
  <li><a href="#u-paiyang" onclick="u_chang('u-paiyang')">U选派样</a></li>
  <li><a href="#u-xuanhui" onclick="u_chang('u-xuanhui')">U选惠</a></li>
</ul> -->
<ul class="am-nav am-nav-tabs am-nav-justify">
  <li class="am-active"><a href="#u-remen">热门</a></li>
  <li><a href="<?php echo U('liyou/u_paiyang'); ?>">U选派样</a></li>
  <li><a href="<?php echo U('liyou/u_xuanhui'); ?>">U选惠</a></li>
</ul>
<!-- content srart -->
<div id="u-remen" display='none'>
  <div class="am-g am-g-fixed blog-fixed">
      <div class="am-u-md-8 am-u-sm-12">
      	<div class="am-u-lg-6 am-u-md-12 am-u-sm-12 blog-entry-text">
              <!-- <span style="color: red;">正在派送</span> -->
              <!-- <h2 class="blog-text-center blog-title"><span>正在派送</span></h2> -->
              <h3 class="blog-title" style="margin-top: 1rem;">正在派送
              	<span style="float: right;font-size: 14px;"> <a href="<?php echo U('liyou/u_xuanhui_list'); ?>" class="blog-color">@查看更多>></a></span>
              </h3>
              <!-- <span style="float: right;"> <a href="" class="blog-color">@查看更多>></a></span> -->
              <!-- <p>我们一直在坚持着，不是为了改变这个世界，而是希望不被这个世界所改变。</p> -->
              
          </div>

          <article class="am-g blog-entry-article">
          	<h1 style="text-align: center;">111我本楚狂人，凤歌笑孔丘</h1>
              <div class="am-u-lg-6 am-u-md-12 am-u-sm-12 blog-entry-img">
                  <img src="/blue/public/static/assets/i/f10.jpg" alt="" class="am-img-thumbnail am-radius">
              </div>
              <div class="am-u-lg-6 am-u-md-12 am-u-sm-12 blog-entry-text">
                  <span><a href="" class="blog-color">价格 &nbsp;</a></span>
                  <span> 地点@南京 &nbsp;</span>
                  <span>time:2015/10/9</span>
                  <!-- <h1><a href="">我本楚狂人，凤歌笑孔丘</a></h1> -->
                  <p>简述：我们一直在坚持着，不是为了改变这个世界，而是希望不被这个世界所改变。
                  </p>
                  <p><a href="" class="blog-continue">continue reading</a></p>
              </div>
          </article>
          
          <!-- <ul class="am-pagination">
  		  	<li class="am-pagination-prev"><a href="">&laquo; Prev</a></li>
  		  	<li class="am-pagination-next"><a href="">Next &raquo;</a></li>
  		</ul> -->
      </div>
      <div class="am-u-md-4 am-u-sm-12 blog-sidebar">
          <div class="blog-sidebar-widget blog-bor">
              <h2 class="blog-text-center blog-title">
                <span style="text-align: center;">U选活动</span>
                
              </h2>
              <span style="float: right;font-size: 14px;padding-top: 6px;padding-right: 0px;"> <a href="" class="blog-color">@查看更多>></a></span>
              <img src="/blue/public/static/assets/i/f16.jpeg" alt="about me" class="am-img-thumbnail am-radius" >
              <p>简述：我不想成为一个庸俗的人。十年百年后，当我们死去，质疑我们的人同样死去，后人看到的是裹足不前、原地打转的你，还是一直奔跑、走到远方的我？</p>
          </div>
          <div class="blog-sidebar-widget blog-bor">
              <h2 class="blog-text-center blog-title"><span>Contact ME</span></h2>
              <p>
                  <a href=""><span class="am-icon-qq am-icon-fw am-primary blog-icon"></span></a>
                  <a href=""><span class="am-icon-github am-icon-fw blog-icon"></span></a>
                  <a href=""><span class="am-icon-weibo am-icon-fw blog-icon"></span></a>
                  <a href=""><span class="am-icon-reddit am-icon-fw blog-icon"></span></a>
                  <a href=""><span class="am-icon-weixin am-icon-fw blog-icon"></span></a>
              </p>
          </div>
          <div class="blog-clear-margin blog-sidebar-widget blog-bor am-g ">
              <h2 class="blog-title"><span>TAG cloud</span></h2>
              <div class="am-u-sm-12 blog-clear-padding">
              <a href="" class="blog-tag">amaze</a>
              <a href="" class="blog-tag">妹纸 UI</a>
              <a href="" class="blog-tag">HTML5</a>
              <a href="" class="blog-tag">这是标签</a>
              <a href="" class="blog-tag">Impossible</a>
              <a href="" class="blog-tag">开源前端框架</a>
              </div>
          </div>
          <div class="blog-sidebar-widget blog-bor">
              <h2 class="blog-title"><span>么么哒</span></h2>
              <ul class="am-list">
                  <li><a href="#">每个人都有一个死角， 自己走不出来，别人也闯不进去。</a></li>
                  <li><a href="#">我把最深沉的秘密放在那里。</a></li>
                  <li><a href="#">你不懂我，我不怪你。</a></li>
                  <li><a href="#">每个人都有一道伤口， 或深或浅，盖上布，以为不存在。</a></li>
              </ul>
          </div>
      </div>

      <!-- <div class="am-u-md-4 am-u-sm-12 blog-sidebar">
          <div class="blog-sidebar-widget blog-bor">
              <h2 class="blog-text-center blog-title"><span>About ME</span></h2>
              <img src="assets/i/f14.jpg" alt="about me" class="blog-entry-img" >
              <p>妹纸</p>
              <p>
          我是妹子UI，中国首个开源 HTML5 跨屏前端框架
          </p><p>我不想成为一个庸俗的人。十年百年后，当我们死去，质疑我们的人同样死去，后人看到的是裹足不前、原地打转的你，还是一直奔跑、走到远方的我？</p>
          </div>
          <div class="blog-sidebar-widget blog-bor">
              <h2 class="blog-text-center blog-title"><span>Contact ME</span></h2>
              <p>
                  <a href=""><span class="am-icon-qq am-icon-fw am-primary blog-icon"></span></a>
                  <a href=""><span class="am-icon-github am-icon-fw blog-icon"></span></a>
                  <a href=""><span class="am-icon-weibo am-icon-fw blog-icon"></span></a>
                  <a href=""><span class="am-icon-reddit am-icon-fw blog-icon"></span></a>
                  <a href=""><span class="am-icon-weixin am-icon-fw blog-icon"></span></a>
              </p>
          </div>
          <div class="blog-clear-margin blog-sidebar-widget blog-bor am-g ">
              <h2 class="blog-title"><span>TAG cloud</span></h2>
              <div class="am-u-sm-12 blog-clear-padding">
              <a href="" class="blog-tag">amaze</a>
              <a href="" class="blog-tag">妹纸 UI</a>
              <a href="" class="blog-tag">HTML5</a>
              <a href="" class="blog-tag">这是标签</a>
              <a href="" class="blog-tag">Impossible</a>
              <a href="" class="blog-tag">开源前端框架</a>
              </div>
          </div>
          <div class="blog-sidebar-widget blog-bor">
              <h2 class="blog-title"><span>么么哒</span></h2>
              <ul class="am-list">
                  <li><a href="#">每个人都有一个死角， 自己走不出来，别人也闯不进去。</a></li>
                  <li><a href="#">我把最深沉的秘密放在那里。</a></li>
                  <li><a href="#">你不懂我，我不怪你。</a></li>
                  <li><a href="#">每个人都有一道伤口， 或深或浅，盖上布，以为不存在。</a></li>
              </ul>
          </div>
      </div> -->
  </div>
</div>
<!-- content srart -->




  <footer class="blog-footer">
    <!-- <div class="am-g am-g-fixed blog-fixed am-u-sm-centered blog-footer-padding">
        <div class="am-u-sm-12 am-u-md-4- am-u-lg-4">
            <h3>模板简介</h3>
            <p class="am-text-sm">这是一个使用amazeUI做的简单的前端模板。<br> 博客/ 资讯类 前端模板 <br> 支持响应式，多种布局，包括主页、文章页、媒体页、分类页等<br>嗯嗯嗯，不知道说啥了。外面的世界真精彩<br><br>
            Amaze UI 使用 MIT 许可证发布，用户可以自由使用、复制、修改、合并、出版发行、散布、再授权及贩售 Amaze UI 及其副本。</p>
        </div>
        <div class="am-u-sm-12 am-u-md-4- am-u-lg-4">
            <h3>社交账号</h3>
            <p>
                <a href=""><span class="am-icon-qq am-icon-fw am-primary blog-icon blog-icon"></span></a>
                <a href=""><span class="am-icon-github am-icon-fw blog-icon blog-icon"></span></a>
                <a href=""><span class="am-icon-weibo am-icon-fw blog-icon blog-icon"></span></a>
                <a href=""><span class="am-icon-reddit am-icon-fw blog-icon blog-icon"></span></a>
                <a href=""><span class="am-icon-weixin am-icon-fw blog-icon blog-icon"></span></a>
            </p>
            <h3>Credits</h3>
            <p>我们追求卓越，然时间、经验、能力有限。Amaze UI 有很多不足的地方，希望大家包容、不吝赐教，给我们提意见、建议。感谢你们！</p>          
        </div>
        <div class="am-u-sm-12 am-u-md-4- am-u-lg-4">
              <h1>我们站在巨人的肩膀上</h1>
             <h3>Heroes</h3>
            <p>
                <ul>
                    <li>jQuery</li>
                    <li>Zepto.js</li>
                    <li>Seajs</li>
                    <li>LESS</li>
                    <li>...</li>
                </ul>
            </p>
        </div>
    </div> -->    
    <div class="blog-text-center">© 2015 AllMobilize, Inc. Licensed under MIT license. Made with love By LWXYFER</div>    
  </footer>




<script type="text/javascript">
  function u_chang(type) {
    if (type == 'u-remen') {
      $('#u-paiyang').hide();
      $('#u-xuanhui').hide();
      $('#u-remen').show();
    }else if (type == 'u-paiyang') {
      $('#u-remen').hide();
      $('#u-xuanhui').hide();
      $('#u-paiyang').show();
    }else if (type == 'u-xuanhui') {
      $('#u-remen').hide();
      $('#u-paiyang').hide();
      $('#u-xuanhui').show();
    }
    console.log(13);
  }
</script>
<!--[if (gte IE 9)|!(IE)]><!-->
<script src="/blue/public/static/assets/js/jquery.min.js"></script>
<!--<![endif]-->
<!--[if lte IE 8 ]>
<script src="http://libs.baidu.com/jquery/1.11.3/jquery.min.js"></script>
<script src="http://cdn.staticfile.org/modernizr/2.8.3/modernizr.js"></script>
<script src="assets/js/amazeui.ie8polyfill.min.js"></script>
<![endif]-->
<script src="/blue/public/static/assets/js/amazeui.min.js"></script>
</body>
</html>