<?php 
namespace app\admin\controller;

class ListNode {
      public $val = 0;
      public $next = null;
      function __construct($val) { $this->val = $val; }
}

class Test
{
	
	/**
	 * Two Sum
	 * 给定一个整数数组，返回两个数字的索引，使它们相加到特定目标。
	 * 您可以假设每个输入只有一个解决方案，并且您可能不会两次使用相同的元素。
	 * Given nums = [2, 7, 11, 15], target = 9,
	 * Because nums[0] + nums[1] = 2 + 7 = 9,
	 * return [0, 1].
	 * 
     * @param Integer[] $nums
     * @param Integer $target
     * @return Integer[]
     */
    function twoSum($nums=[2, 7, 11, 15], $target=9) {
    	//1
    	// for ($i=0; $i < count($nums); $i++) { 
    	// 	for ($j=$i+1; $j < count($nums); $j++) {
    	// 		if (($nums[$i]+$nums[$j]) == $target) {
    	// 			return [$i,$j];
    	// 		}
    	// 	}
    	// }
    	//2
    	$nums_arr = array_flip($nums);
    	foreach ($nums as $key => $value) {
    		$res = $target-$value;
			unset($nums[$key]);
			if (in_array($res,$nums)){
				return [$key,$nums_arr[$res]];
			}
    	}
		return [];
    }

    /**
     * 您将获得两个非空链表，表示两个非负整数。 数字以相反的顺序存储，每个节点包含一个数字。 添加两个数字并将其作为链接列表返回
     * 您可以假设这两个数字不包含任何前导零，除了数字0本身
     * Input: (2 -> 4 -> 3) + (5 -> 6 -> 4)
     * Output: 7 -> 0 -> 8
     * Explanation: 342 + 465 = 807.
     * 
     * @param ListNode $l1
     * @param ListNode $l2
     * @return ListNode
     */
    function addTwoNumbers($l1=[2,4,3], $l2=[5,6,4]) {
        $l_new = new ListNode(0);

        var_dump($l1->next());
        var_dump($l2);
    }

    /**
     * 给定一个字符串,请你找出其中不含有重复字符的最长子串的长度
     * Input: "abcabcbb"dvdf
     * Output: 3
     * Explanation: The answer is "abc", with the length of 3. 
     * 
     * @param String $s
     * @return Integer
     */
    function lengthOfLongestSubstring($s='pwwkew') {
    	//1. O(n^2)
    	$res_length = 0;
       //  for ($i=0; $i < strlen($s); $i++) {
       //  	$str = substr($s,$i);
       //  	if ($res_length >= strlen($str)) {
       //  		continue;
       //  	}
       //  	$son_arr = [];
       //  	for ($j=0; $j < strlen($str); $j++) { 
       //  		if (in_array($str[$j], $son_arr)) {
    			// 	break;
    			// }
    			// $son_arr[] = $str[$j];
       //  	}
       //  	if (count($son_arr) > $res_length) {
       //  		$res_length = count($son_arr);
       //  	}
       //  }
       //  return $res_length;
        //2. O(2n)
        // $i = $j = $res_length = 0;
        // $son_arr = [];
        // while ($i < strlen($s) && $j < strlen($s)) {
        // 	if (!in_array($s[$j],$son_arr)) {
        // 		$son_arr[] = $s[$j++];
        // 		$res_length = max($res_length,count($son_arr));
        // 	}else{
        // 		unset($son_arr[$i++]);
        // 	}
        // }
        // return $res_length;
        //3. O(n)
        $son_arr = [];
        $res_length = 0;
        for ($i=0, $j=0; $j < strlen($s); $j++) { 
        	if (array_key_exists($s[$j], $son_arr)) {
        		$i = max($i, $son_arr[$s[$j]]);
        	}
        	$res_length = max($res_length, $j-$i+1);
        	$son_arr[$s[$j]] = $j+1;
        }
        return $res_length;
    }

    /**
     * 给定两个大小为 m 和 n 的有序数组 nums1 和 nums2。
     * 请你找出这两个有序数组的中位数，并且要求算法的时间复杂度为 O(log(m + n))。
     * nums1 = [1, 2]
     * nums2 = [3, 4]
     * The median is (2 + 3)/2 = 2.5
     * @param Integer[] $nums1
     * @param Integer[] $nums2
     * @return Float
     */
    function findMedianSortedArrays($nums1=[1, 2], $nums2=[3, 4]) {
    	$m = count($nums1);
    	$n = count($nums2);
    	$len_sum = $m + $n;
    	if ($m > $n) {
    		$temp = $nums1;
    		$nums1 = $nums2;
    		$nums2 = $temp;
    	}
    	if ($len_sum%2 != 0) {
    		return midArr($nums1, 0, $nums2, 0, $len_sum/2 + 1);
    	}else{
    		$left = midArr($nums1, 0, $nums2, 0, $len_sum/2);
            $right = midArr($nums1, 0, $nums2, 0, $len_sum/2 + 1);
            return ($left + $right)/2;
    	}
    }
    function midArr($a,$a_start,$b,$b_start,$k){
    	if($a_start >= count($a)){
            return $b[$b_start + $k - 1];
        }
        if($b_start >= count($b)){
            return $a[$a_start + $k - 1];
        }
        if($k == 1){
            return min($a[$a_start], $b[$b_start]);
        }
        $aMid = $a_start + $k/2 - 1;
        $bMid = $b_start + $k/2 - 1;
        $aVal = $aMid >= count($a) ? 0 : $a[$aMid];
        $bVal = $bMid >= count($B) ? 0 : $b[$bMid];
        if($aVal <= $bVal){
            return midArr($a, $aMid + 1, $b, $b_start, $k - $k/2);
        }else{
            return midArr($a, $a_start, $b, $bMid + 1, $k - $k/2);
        }
    }

    /**
     * 给定一个字符串 s，找到 s 中最长的回文子串
     * Input: "babad"
     * Output: "bab"
     * Note: "aba" is also a valid answer.
     * @param String $s
     * @return String
     */
    function longestPalindrome($s='abb') {
        $len = strlen($s);
        $max = 1;
        $index = 0;
        for ($i=0; $i < $len; $i++) {
        	$new1 = $this->getPalindrome($s,$i,$i);
        	$new2 = $this->getPalindrome($s,$i,$i+1);
        	$new = max($new1,$new2);
        	if ($new > $max) {
        		$index = $i - floor(($new-1)/2);
        		$max = $new;
        	}
        }
        return substr($s,$index,$max);
    }
    function getPalindrome($s,$j,$k)
    {	
    	while ( $j>=0 && $k<strlen($s) && ($s[$j]==$s[$k]) ) {
    		$j--;
    		$k++;
    	}
    	$new_len = $k-$j-1;
    	return $new_len;
    }

    /**
     * 将一个给定字符串根据给定的行数，以从上往下、从左到右进行 Z 字形排列
     * 之后，你的输出需要从左往右逐行读取，产生出一个新的字符串
     * LEETCODEISHIRING   n=3
     * L   C   I   R
     * E T O E S I I G
     * E   D   H   N
     * Output: "LCIRETOESIIGEDHN"
     * @param String $s
     * @param Integer $numRows
     * @return String
     */
    function convert($s="PAYPALISHIRING", $numRows='4') {
    	$len = strlen($s);
        $n = $numRows;
        if ($n == 1 || $n == $len) {
        	return $s;
        }
        $arr = [];
        // i->行,k->索引
        for ($i=0; $i < $n; $i++) { 
        	$k = $j= 0;
        	while (true) {
        		if ($i == 0 || $i == $n-1) {
        			$k = $j*(2*$n-2)+$i;
        		}else{
        			if ($j == 0) {
        				$k += $i;
        			}else{
        				if ($j%2!=0) {
	        				$k += 2*$n-2-2*$i;
	        			}else{
	        				$k += 2*$i;
	        			}
        			}
        		}
        		if ($k >= $len) {
        			break;
        		}
        		$arr[] = $s[$k];
        		$j++;
        	}
        }
        $str = implode($arr);
        var_dump($str);
        return $str;
    }

    /**
     * 给出一个 32 位的有符号整数，你需要将这个整数中每位上的数字进行反转
     * Input: -123
     * Output: -321
     * @param Integer $x
     * @return Integer
     */
    function reverse($x=-123) {
    	//1.
        // $arr = str_split($x);
        // $res = [];
        // if (!is_numeric($arr[0])) {
        // 	$head = array_shift($arr);
        // }
        // $count = count($arr);
        // for ($i=0; $i < $count; $i++) { 
        // 	$res[] = array_pop($arr);
        // }
        // $str = (Integer)implode($res);
        // if ($str < pow(2,31)-1) {
        // 	$str = $head.$str;
        // 	return $str;
        // }
        // return 0;
        //3.
        $symbol = 1;
        if ($x < 0) {
        	$symbol = -1;
        	$x *= $symbol;
        }
        $res = 0;
        while ($x > 0) {
        	$remainder = $x%10;
        	$res = $res*10+$remainder;
        	$x = floor($x/10);
        }
        $res = $res+$x;
        var_dump(floor($x/10));
        if ($res < pow(2,31)-1) {
        	return $symbol*$res;
        }
        return 0;
    }

    /**
     * 字符串转换整数
     * Input: "4193 with words"
     * Output: 4193
     * Explanation: Conversion stops at digit '3' as the next character is not a numerical digit.
     * @param String $str
     * @return Integer
     */
    function myAtoi($str="-2147483647") {
    	$res = [];
        for ($i=0; $i < strlen($str); $i++) {
        	if ($str[$i] == ' ' && !$res) {
        		continue;
        	}elseif ($str[$i] == ' ' && $res) {
        		break;
        	}
        	if (($str[$i] == '-' || $str[$i] == '+') && !$res[0]) {
        		$res[] = $str[$i];
        	}elseif (($str[$i] == '-' || $str[$i] == '+') && $res[0]) {
        		break;
        	}elseif (is_numeric($str[$i])) {
        		$res[] = $str[$i];
        	}else{
        		break;
        	}
        }
        if (!$res || (end($res) == '-' || end($res) == '+')) {
        	return 0;
        }
        if (!is_numeric($res[0])) {
        	$remainder = array_shift($res);
        }
        if ($remainder != '-') {
        	$remainder = '';
        }
        $res = (Integer)implode($res);
        if ($res >= pow(2,31)) {
        	if ($remainder == '-') {
        		$res = pow(2,31);
        	}else{
        		$res = pow(2,31)-1;
        	}
        }
        return (Integer)($remainder.$res);
    }

    /**
     * 判断一个整数是否是回文数。
     * 回文数是指正序（从左向右）和倒序（从右向左）读都是一样的整数
     * Input: 121
     * Output: true
     * @param Integer $x
     * @return Boolean
     */
    function isPalindrome($x=1324231) {
    	//1.
    	// if ($x < 0) {
    	// 	return false;
    	// }
    	// $n = $m = floor(log($x,10)+1); //length
    	// $n = $m = strlen($x);
    	// $k = floor($n/2);
     //    for ($i=0; $i < $k; $i++) { 
     //    	if ($x%10 != floor($x/pow(10,$n-2*$i-1))) {
     //    		return false;
     //    	}
     //    	$x = floor(($x%pow(10,$m-1))/10);
     //    	$m = $m-2;
     //    }
     //    return true;
        //2.
        if ($x < 0) return false;
    	$res = 0;
    	$s = $x;
    	while ($s > 0) {
    		$res = $res*10 + $s%10;
    		$s = floor($s/10);
    		var_dump($res);
    	}
    	if ($res == $x) return true;
    	return false;
    }

    /**
     * 给定一个字符串 (s) 和一个字符模式 (p)。实现支持 '.' 和 '*' 的正则表达式匹配
     * '.' 匹配任意单个字符 | '*' 匹配零个或多个前面的元素
     * Input:s = "aab" p = "c*a*b"
     * Output: true
     * @param String $s
     * @param String $p
     * @return Boolean
     */
    function isMatch($s="aaa", $p="aaaa") {
    	$m = strlen($s);
    	$n = strlen($p);
    	for ($i=0; $i <= $n-$m; $i++) { 
    		var_dump($p[$i]);
    		var_dump($s[0]);
    		if ($p[$i] != '.' && $s[0] != $p[$i]) {
    			var_dump("+++");
    			continue;
    		}
    		$falg = true;
    		for ($j=1; $j < $n-$i; $j++) { 	
    			var_dump($p[$i+$j]);
    			var_dump($s[$j]);
    			if ($p[$i+$j] == '.' || $s[$j] == $p[$i+$j]) {
    				continue;
    			}
    			if ($p[$i+$j] == '*' && ($s[$j] == $p[$i+$j-1] || $p[$i+$j-1] == '.')) {
    				continue;
    			}
    			$falg = false;break;
    		}
    		var_dump($falg);
    		if ($falg){
    			return $falg;
    		}else{
    			return $falg;
    		}
    	}
    	return false;
    }

    /**
     * 三步翻转法(字符串)
     * 在原字符串中把字符串尾部的m个字符移动到字符串的头部
     * 要求：长度为n的字符串操作时间复杂度为O(n)，空间复杂度为O(1)
     * Input:s = "Ilovebaofeng" p = "7"
     * Output: "baofengIlove"
     *
     * 输入一个英文句子，翻转句子中单词的顺序，但单词内字符的顺序不变，句子中单词以空格符隔开
     * Input:s = "I am a student."
     * Output: "student. a am I"
     * @param  string $s
     * @return string
     */
    function threeStepReverse($s='I am a student.')
    {
    	//1.
    	// function reverse(&$s, $n, $p)
    	// {
    	// 	while ($n < $p) {
    	// 		$temp = $s[$n];
    	// 		$s[$n++] = $s[$p];
    	// 		$s[$p--] = $temp;
    	// 	}
    	// 	var_dump($s);
    	// }
    	// reverse($s, 0, strlen($s)-$p-1);
    	// reverse($s, strlen($s)-7, strlen($s)-1);
    	// reverse($s, 0, strlen($s)-1);
    	//2.
    	$n = strlen($s);
    	function reverse(&$s, $j, $i)
    	{
    		while ($j < $i) {
    			$temp = $s[$j];
    			$s[$j++] = $s[$i];
    			$s[$i--] = $temp;
    		}
    		var_dump($s);
    	}
		for ($i=0, $j=0; $i < $n; $i++) { 
			if ($s[$i] == ' ') {
				reverse($s, $j, $i-1);
				$j = $i+1;
			}
			if ($i == $n-1) {
				reverse($s, $j, $n-1);
			}
		}
		reverse($s, 0, $n-1);
		var_dump(explode('', 'ABCD'));
    }






}