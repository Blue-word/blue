<?php 
namespace app\index\behavior;

/**
 * 
 */
class Test
{
	
	public function run(&$params)
	{
		var_dump($params);
	}

	public function actionBegin(&$params)
	{
		var_dump('action_begin');
		var_dump($params);
	}

	// public function appBegin(&$params)
	// {
	// 	var_dump('app_begin');
	// 	var_dump($params);
	// }
	
	public function appInit(&$params)
	{
		var_dump('appInit');
		var_dump($params);
	}


}




 ?>