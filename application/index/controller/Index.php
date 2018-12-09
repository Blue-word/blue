<?php
namespace app\index\controller;
use think\Controller;
use think\Session;

class Index extends Base{

    public function index(){
        return $this->fetch();
    }

    public function index_v1(){
        $nowtime = time();   //获取现在的时间戳
        $starttime = mktime(0,0,0,date("m"),1,date("Y"));   //当月第一天时间戳
        $last_starttime = mktime(0,0,0,date("m")-1,1,date("Y"));   //上月第一天时间戳
        $last_endtime = $starttime-1;   //上月最后一天时间戳
        $map['time'] = array('between',"$starttime,$nowtime");
        $last_map['time'] = array('between',"$last_starttime,$last_endtime");
        //课程
        $course_count = M('new_course')->where('status','gt',0)->count();
        $course_add_count = M('new_course')->where('status','gt',0)->where($map)->count();//这个月
        $course_old_count = M('new_course')->where('status','gt',0)->where($last_map)->count();  //上个月
        $list['course_count'] = $course_count;
        $list['course_add_count'] = $course_add_count;
        $course_percentage = $this->percentage($course_add_count,$course_old_count);
        $list['course_percentage'] = $course_percentage.'%';
        if ($course_percentage >= 0) {
            $list['course_status'] = 1;
        }else{
            $list['course_status'] = 0;
        }
        //新人
        $new_count = M('new_survey')->count();
        $new_add_count = M('new_survey')->where($map)->count();
        $new_old_count = M('new_survey')->where($last_map)->count();  //上个月
        $list['new_count'] = $new_count;
        $list['new_add_count'] = $new_add_count;
        $new_percentage = $this->percentage($new_add_count,$new_old_count);
        $list['new_percentage'] = $new_percentage.'%';
        if ($new_percentage >= 0) {
            $list['new_status'] = 1;
        }else{
            $list['new_status'] = 0;
        }
        //增员活动
        $activity_count = M('new_add')->where('status','gt',0)->count();
        $activity_add_count = M('new_add')->where('status','gt',0)->where($map)->count();
        $activity_old_count = M('new_add')->where('status','gt',0)->where($last_map)->count();  //上个月
        $list['activity_count'] = $activity_count;
        $list['activity_add_count'] = $activity_add_count;
        $activity_percentage = $this->percentage($activity_add_count,$activity_old_count);
        $list['activity_percentage'] = $activity_percentage.'%';
        if ($activity_percentage >= 0) {
            $list['activity_status'] = 1;
        }else{
            $list['activity_status'] = 0;
        }
        //管理员日志
        $admin_log = M('admin_log')->limit(10)->order('log_id desc')->select();
        foreach ($admin_log as $k => $v) {
            $admin_log[$k]['admin_name'] = M('admin')->where('uid',$v['admin_id'])->getField('name');
        }
        //新人日志
        $new_log = M('new_log')->limit(10)->order('log_id desc')->select();
        $list['new_log_count'] = M('new_log')->count();
        foreach ($new_log as $k1 => $v1) {
            $new_log[$k1]['new_name'] = M('new_survey')->where('openid',$v1['new_id'])->getField('name');
            unset($v1['openid']);
            $new_log[$k1]['log_time_1'] = time_change(strtotime($v1['log_time']));
        }

        // dump($list['course_percentage']);
        // dump($course_new_count);
        // dump($admin_log);
        // dump($starttime);
        //或者：
        // $map['publishtime'] = array('between',array($starttime,$nowtime));
        $this->assign('list',$list);
        $this->assign('admin_log',$admin_log);
        $this->assign('new_log',$new_log);
        return $this->fetch();
    }

    public function index_v2(){
        return $this->fetch();
    }

    public function index_v3(){
        return $this->fetch();
    }

    public function index_v4(){
        return $this->fetch();
    }

    public function index_v5(){
        return $this->fetch();
    }
}
