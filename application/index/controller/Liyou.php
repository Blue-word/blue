<?php
namespace app\index\controller;
use think\Controller;
use think\Session;

class Liyou extends Base{

    public function index(){
        return $this->fetch();
    }

    public function u_paiyang(){
        return $this->fetch();
    }

    public function u_xuanhui(){
        return $this->fetch();
    }

    public function u_paiyang_list(){
        return $this->fetch();
    }

    public function u_xuanhui_list(){
        return $this->fetch();
    }

    public function u_info(){
        return $this->fetch();
    }
}
