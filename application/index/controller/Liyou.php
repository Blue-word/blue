<?php
namespace app\index\controller;
use think\Controller;
use think\Session;

class Liyou extends Common{

    public function index(){
        $position = input('post.position',1);
        //活动
        $activity_where['status'] = array('in','1,2,3');
        $activity_where['is_delete'] = 0;
        $activity_list = M('activity')->where($where)->order('id desc')->limit(3)->select();
        if ($activity_list) {
            foreach ($activity_list as $key => $value) {
                $activity_list[$key]['start_time'] = date('Y-m-d H:i',$value['start_time']);
                $activity_list[$key]['end_time'] = date('Y-m-d H:i',$value['end_time']);
                $activity_list[$key]['picture'] = $this->imageChange($value['picture']);
            }
        }
        //选惠
        $tao_where['is_delete'] = 0;
        $tao_list = M('tao_goods')->where($tao_where)->order('id desc')->limit(3)->select();
        if ($tao_list) {
            foreach ($tao_list as $key => $value) {
                $tao_list[$key]['category_name'] = M('category')->where('id',$value['id'])->getField('name');
                $category_name_first = $this->getcFirstCategory('category',3,$value['category']);
                if (!$category_name_first['code']) {
                    $tao_list[$key]['category_name_first'] = $category_name_first['info']['name'];
                }else{
                    $tao_list[$key]['category_name_first'] = '';
                }
                $tao_list[$key]['add_time'] = date('Y-m-d H:i',$value['add_time']);
                $tao_list[$key]['picture'] = $this->imageChange($value['picture']);
            }
        }
        $this->assign('activity_list',$activity_list);
        $this->assign('tao_list',$tao_list);
        return $this->fetch();
    }

    public function u_paiyang(){
        return $this->fetch();
    }

    public function u_xuanhui(){
        return $this->fetch();
    }

    public function u_activity_list(){
        $p = I('p',1);
        $activity_where['status'] = array('in','1,2,3');
        $activity_where['is_delete'] = 0;
        $activity_list = M('activity')->where($where)->order('id desc')->page($p,10)->select();
        if ($activity_list) {
            foreach ($activity_list as $key => $value) {
                $activity_list[$key]['start_time'] = date('Y-m-d H:i',$value['start_time']);
                $activity_list[$key]['end_time'] = date('Y-m-d H:i',$value['end_time']);
                $activity_list[$key]['picture'] = $this->imageChange($value['picture']);
                $activity_list[$key]['content'] = mb_substr($value['content'], 0, 10,'utf-8').'...';
            }
        }
        $count = M('activity')->where($where)->count();
        $page = new Page($count,10);
        $show = $page->show();
        $this->assign('list',$activity_list);
        $this->assign('page',$page);
        $this->assign('show',$show);
        return $this->fetch();
    }

    public function u_paiyang_list(){
        $category = input('post.category');
        if ($category) {
            $where['category'] = $category;
        }
        $where['is_delete'] = 0;
        $list = M('goods')->where($where)->select();
        if ($list) {
            foreach ($list as $key => $value) {
                $list[$key]['category_name'] = M('category')->where('id',$value['category'])->getField('name');
                $list[$key]['add_time'] = date('Y-m-d H:i',$value['add_time']);
                $list[$key]['picture'] = explode(',', $value['picture']);
                $category_name_first = $this->getcFirstCategory('category',3,$value['category']);
                if (!$category_name_first['code']) {
                    $list[$key]['category_name_first'] = $category_name_first['info']['name'];
                }else{
                    $list[$key]['category_name_first'] = '';
                }
            }
        }
        // dump($list);
        $this->assign('list',$list);
        return $this->fetch();
    }

    public function u_xuanhui_list(){
        $category = input('post.category');
        if ($category) {
            $where['category'] = $category;
        }
        $where['is_delete'] = 0;
        $list = M('tao_goods')->where($where)->select();
        if ($list) {
            foreach ($list as $key => $value) {
                $list[$key]['category_name'] = M('category')->where('id',$value['id'])->getField('name');
                $list[$key]['add_time'] = date('Y-m-d H:i',$value['add_time']);
            }
        }
        return $this->fetch();
    }

    public function u_info(){
        $id = I('id');
        $info = M('goods')->where('id',$id)->find();
        if ($info) {
            $info['category_name'] = M('category')->where('id',$info['category'])->getField('name');
            $info['add_time'] = date('Y-m-d H:i',$info['add_time']);
            $info['picture'] = explode(',', $info['picture']);
            $category_name_first = $this->getcFirstCategory('category',3,$info['category']);
            if (!$category_name_first['code']) {
                $info['category_name_first'] = $category_name_first['info']['name'];
            }else{
                $info['category_name_first'] = '';
            }
        }
        dump($info);
        $this->assign('info',$info);
        return $this->fetch();
    }
}
