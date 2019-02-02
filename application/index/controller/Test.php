<?php
namespace app\index\controller;
use think\Controller;
use think\Db;
use think\Model;
use think\db\Query;
use think\Hook;

class Test extends Base{

    public function _initialize(){

    }

     public function test02(){
        $res = $this->exportExcel_2();
        dump($res);
    }

    // 创说会新人签到表导出
    public function test01(){
        $user = M('new_survey_copy')->field($field)->select();
        $subject = "创说会新人签到表导出";
        $title = array("新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID","新人openID");
        $asd = $this->exportExcel($user,$title,$subject); 
        // dump($asd);
    }

	public function test(){
		$user = M('new_survey_copy')->select();
        $this->assign('user',$user);
		return $this->fetch();
	}

	public function test1(){
		$user = M('new_survey_copy')->select();
        $this->assign('user',$user);
        // $this->display("/index");
		return $this->fetch();
	}

    /**
     * 导出
     */
    public function export(){
    	$field = 'id,name,id_number,phone,recommend_number,class,sex,other_content';
        $user = M('new_survey_copy')->field($field)->select();
        // dump($data);
        $subject = "Excel导出测试";
        $title = array("id","姓名","身份证","手机号","推荐工号","班次","性别","其他内容");
        $asd = $this->exportExcel($user,$title,$subject); 
        dump($asd);
    }
    /**
     * 导入
     */
    public function import(){
        $tableName = "new_survey_copy";
        $title = array("id","name","id_number","phone","recommend_number","class","sex","other_content");
        $result = $this->importExcel($tableName,$title);
        Db::startTrans();
        $model = model('NewSurveyCopy');
        if ($result['status'] == true) {   //success
        	// dump($result['data']);
        	$res = $model->saveAll($result['data']);
        	if ($res) {
        		Db::commit(); 
        		$this->success('导入成功');
        	}else{
        		Db::rollback();
        		$this->success('导入成功');
        	}
        }else{
        	$this->error($result['data']);
        }
        // dump($result);
        // $this->success($result);
    }

    public function asdzxc(){
        $data = input('post.');
        $res = M('character_test')->save($data);
        // $res = M('character_test')->select();
        dump($data);
        dump($res);
    }
    public function asdzxcd(){
        // $data = input('post.');
        // if ($data = input('post.')) {
        //     $res = M('character_test')->add($data);
        // // $res = M('character_test')->select();
        //     dump($data);
        //     dump($res);
        // }
        $list = M('character_test')->where('pid',0)->select();
        foreach ($list as $k => $v) {
            $list[$k]['option'] = M('character_test')->where('pid',$v['id'])->select();
        }
        
        dump($list);
        return $this->fetch();
    }

    public function test03()
    {
        // Hook::add('action_begin','app\index\behavior\Test');
        // Hook::add('app_init','app\\index\\behavior\\Test');
        // Hook::add('app_begin','app\index\behavior\Test');
        $params = 'asd';
        // Hook::listen('app_init',$params);   //添加行为侦听
        
        // Hook::listen('module_init',$params);   //添加行为侦听
        
        // Hook::listen('action_begin',$params);   //添加行为侦听
        // Hook::listen('app_begin',$params);   //添加行为侦听

        echo "<br/>";
        echo "end";

    }
    


}