<?php 

class ListNode {
      public $val = 0;
      public $next = null;
      function __construct($val) { $this->val = $val; }
}

class Solution {
    function __construct($val) { $this->val = $val; }
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
    function addTwoNumbers($l1, $l2) {
        $a = new ListNode(0);
        $b = $a;
        $carry = 0;
        while(!$l1 || !$l2 || $carry != 0){
            $sum = ($l1 ? $l1->val : 0)+ ($l2 ? $l2->val : 0) + $carry;
            if($sum > 9){
                $carry = intval($sum / 10);
                $remainder = $sum % 10;
                $final = $remainder;
            }else{
                $carry = 0;
                $final = $sum;
            }
            $b = $b->next = new ListNode($final);
            $l1 = $l1 ->next;
            $l2 = $l2 ->next;
        }
        return $a->next;
    }
}
// $c = new SplDoublyLinkedList();
// $a = new Solution();
// $b = $a->addTwoNumbers([2,4,3],[5,6,4]);
// var_dump($c);

/**
 * 
 */
function test()
{
    $url = 'https://learnku.com/articles/20714';
    $content = file_get_contents($url);
    preg_match_all('/<img.*?\"([^\"]*(jpg|bmp|jpeg|gif|png)).*?>/', $content, $picture);
    var_dump($picture);
    # code...
}

function download_images($url = '', $image_path = 'tmp'){
    $content = file_get_contents($url);
    preg_match_all('/<img.*?\"([^\"]*(jpg|bmp|jpeg|gif|png)).*?>/', $content, $picture);
    // var_dump($picture);die;
    $file_path = getcwd().DIRECTORY_SEPARATOR.$image_path;
    if (!file_exists($file_path)) {
        mkdir($file_path);
    }
    foreach ($picture[1] as $value) {
        var_dump($value);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $value);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        $file_info = pathinfo($value);
        $file_stream = fopen($file_path.DIRECTORY_SEPARATOR.$file_info['basename'], 'w');
        var_dump($res);
        var_dump($file_info);
        // var_dump($file_stream);die;
        if ($res || $file_stream) {
            fwrite($file_stream, $res);
            fclose($file_stream);
        }
        die;
    }

}
// download_images('https://learnku.com/articles/20714');
function test1($arr)
{
    $poker = range(1,13);
    var_dump(max($poker));
    var_dump(min($poker));
    var_dump($poker);
    $poker_flip = array_flip($poker);
    $i = 1;
    while ($i<=5) {
        $rand = rand(0,12);
        var_dump($rand);
        if (isset($poker[$rand])) {
            unset($poker[$rand]);
            $i++;
        }
    }
    var_dump($poker);
}
// test1([2,6,8,9,4]);
/**
 * 最长公共子序列
 */
function test2($str1='', $str2='')
{
    $res = [];
    $max = 0;
    for ($i=0; $i < strlen($str1); $i++) { 
        for ($j=0; $j < strlen($str2); $j++) { 
            if ($str1[$i] == $str2[$j]) {
                $res[$i][$j] = isset($res[$i-1][$j-1]) ? $res[$i-1][$j-1] + 1 : 1;
            }else{
                $res[$i-1][$j] = isset($res[$i-1][$j]) ? $res[$i-1][$j] : 0;
                $res[$i][$j-1] = isset($res[$i][$j-1]) ? $res[$i][$j-1] : 0;
                $res[$i][$j] = max($res[$i-1][$j], $res[$i][$j-1]);
            }
            $max = ($res[$i][$j]>$max) ? $res[$i][$j] : $max;
        }
    }
    for ($i=0; $i < strlen($str1); $i++) { 
        for ($j=0; $j < strlen($str2); $j++) { 
            echo $res[$i][$j];
        }
        echo "<br/>";
    }
    // var_dump($res);
    // var_dump($max);
}
// test2('abcbdab', 'bdcaba');

/**
 * 给定 n 个非负整数 a1，a2，...，an，每个数代表坐标中的一个点 (i, ai) 。在坐标内画 n 条垂直线，垂直线 i 的两个端点分别为 (i, ai) 和 (i, 0)。找出其中的两条线，使得它们与 x 轴共同构成的容器可以容纳最多的水
 * @param Integer[] $height
 * @return Integer
 */
function maxArea($height) {
    $a = 0;
    $b = count($height)-1;
    $maxArea = 0;
    while ($a < $b) {
        $maxArea = max($maxArea, min($height[$a], $height[$b])*($b-$a));
        $height[$a] > $height[$b] ? $b-- : $a++;
    }
    return $maxArea;
}
// maxArea([1,8,6,2,5,4,8,3,7]);

/**
 * 给定 n 个非负整数表示每个宽度为 1 的柱子的高度图，计算按此排列的柱子，下雨之后能接多少雨水
 * @param Integer[] $height
 * @return Integer
 */
function trap($height) {
    $ans = 0;
    $left = 0;
    $right = count($height)-1;
    $left_max = $right_max = 0;
    while ($left < $right) {
        if ($height[$left] < $height[$right]) {
            if ($height[$left] >= $left_max) {
                $left_max = $height[$left];
            }else{
                $ans += $left_max - $height[$left];
            }
            $left++;
        }else{
            if ($height[$right] >= $right_max) {
                $right_max = $height[$right];
            }else{
                $ans += $right_max - $height[$right];
            }
            $right--;
        }
    }
    return $ans;
}
// trap([0,1,0,2,1,0,1,3,2,1,2,1]);

/**
 * 给定一个 m x n 的矩阵，其中的值均为正整数，代表二维高度图每个单元的高度，请计算图中形状最多能接多少体积的雨水
 * @param Integer[][] $heightMap
 * @return Integer
 */
function trapRainWater($heightMap) {
    $stime=microtime(true);
    // 解决方案：BFS广度优先搜索、堆
    $gray_queue = [];
    $level = 1;
    $water_volume = 0;
    // 最小堆（优先队列）
    $obj = new SplMinHeap();
    $count_x = count($heightMap);
    $count_y = count($heightMap[0]);
    // 将最外围加入优先队列
    for ($i=0; $i < $count_x; $i++) { 
        for ($j=0; $j < $count_y; $j++) { 
            $current = $heightMap[$i][$j];
            if ($i == 0 || $i == ($count_x-1) || $j == 0 || $j == $count_y-1) {
                $obj->insert([$heightMap[$i][$j], [$i,$j]]);
                // 加入灰色队列（已扫描过的柱子）
                $gray_queue[$i][$j] = true;
            }
        }
    }
    // bfs扫描四个方向
    $director = [[-1,0],[1,0],[0,-1],[0,1]];
    while ($obj->valid()) {
        list($h, $xy) = $obj->current();
        $obj->next();
        $level = $h;
        foreach ($director as $dir) {
            $new_x = $xy[0]+$dir[0];
            $new_y = $xy[1]+$dir[1];
            // bfs越界或已在灰色队列中
            if ($new_x < 0 || $new_x > $count_x-1 || 
                $new_y < 0 || $new_y > $count_y-1 
                || isset($gray_queue[$new_x][$new_y])) {
                continue;
            }
            if ($heightMap[$new_x][$new_y] < $level) {
                // 储水量计算
                $water_volume += $level - $heightMap[$new_x][$new_y];
                // 计算过的的柱子加入优先队列
                $obj->insert([$level, [$new_x,$new_y]]);
            }else{
                // 扫描到的柱子加入优先队列
                $obj->insert([$heightMap[$new_x][$new_y], [$new_x,$new_y]]);
            }
            // 扫描到的柱子加入灰色队列
            $gray_queue[$new_x][$new_y] = true;
        }
    }
    return $water_volume;
}
// trapRainWater([
//   [1,4,3,1,3,2],
//   [3,2,1,3,2,4],
//   [2,3,3,2,3,1]
// ]);

/**
 * 整数转罗马数字
 * @param Integer $num
 * @return String
 */
function intToRoman($num) {
    $str = '';
    $arr = [
        1000 => 'M',900 => 'CM',500 => 'D',400 => 'CD',
        100 => 'C',90 => 'XC',50 => 'L',40 => 'XL',
        10 => 'X',9 => 'IX',5 => 'V',4 => 'IV',1 => 'I'
    ];
    while ($num > 0) {
        foreach ($arr as $key => $value) {
            if ($num >= $key) {
                $str .= $value;
                $num -= $key;
                break;
            }
        }
    }
    var_dump($str);
    return $str;
}
// intToRoman(2994);

/**
 * 罗马数字转整数
 * @param String $s
 * @return Integer
 */
function romanToInt($s) {
    $num = 0;
    $arr = [
        'M' => 1000,'CM' => 900,'D' => 500,'CD' => 400,
        'C' => 100,'XC' => 90,'L' => 50,'XL' => 40,
        'X' => 10,'IX' => 9,'V' => 5,'IV' => 4,'I' => 1
    ];
    for ($i=0; $i < strlen($s); $i++) {
        if (isset($arr[substr($s, $i, 2)])) {
            $num += $arr[substr($s, $i, 2)];
            $i++;
        }else{
            $num += $arr[$s[$i]];
        }
    }
    var_dump($num);
    return $num;
}
// romanToInt('MCMXCIV');

/**
 * 最长公共前缀
 * 输入: ["flower","flow","flight"]
 * 输出: "fl"
 * @param String[] $strs
 * @return String
 */
function longestCommonPrefix($strs) {
    for ($i=0; $i < strlen($strs[0]); $i++) { 
        for ($j=1; $j < count($strs); $j++) { 
            if (!isset($strs[$j][$i])) {
                break 2;
            }
            if ($strs[0][$i] != $strs[$j][$i]) {
                break 2;
            }
        }
    }
    $lcp = substr($strs[0], 0, $i);
    var_dump($lcp);
    var_dump($i);
    return $lcp;
}
// longestCommonPrefix(["dog","racecar","car"]);

/**
 * 三数之和
 * 给定一个包含 n 个整数的数组 nums，判断 nums 中是否存在三个元素 a，b，c 
 * 使得 a + b + c = 0 ？找出所有满足条件且不重复的三元组
 * @param Integer[] $nums
 * @return Integer[][]
 */
function threeSum($nums) {
    $res = [];
    sort($nums);
    $count = count($nums);
    if (!$nums || $count < 3) return $res;
    if ($nums[0] <= 0 || $nums[$count-1] >= 0) {
        for ($i=0; $i < $count; $i++) { 
            $left = $i+1;
            $right = $count-1;
            while ($left < $right) {
                if (isset($nums[$i-1]) && $nums[$i] == $nums[$i-1]) break;
                if ($nums[$i] <= 0 && $nums[$right] >= 0) {
                    $sum = $nums[$i] + $nums[$left] + $nums[$right];
                    if ($sum == 0) {
                        $res[] = [$nums[$i], $nums[$left], $nums[$right]];
                        while ($left < $right && $nums[$left] == $nums[$left+1]) $left++;
                        while ($left < $right && $nums[$right] == $nums[$right-1]) $right--;
                        $left++;
                        $right--;
                    } elseif ($sum > 0) {
                        $right--;
                    } else {
                        $left++;
                    }
                } else {
                    break;
                }
            }
        }
    }
    var_dump($res);
    return $res;
}
// threeSum([-2,0,0,2,2]);

/**
 * 最接近的三数之和
 * @param Integer[] $nums
 * @param Integer $target
 * @return Integer
 */
function threeSumClosest($nums, $target) {
    $res = 0;
    sort($nums);
    $count = count($nums);
    if (!$nums || $count < 3) return $res;
    if ($nums[0] <= 0 || $nums[$count-1] >= 0) {
        for ($i=0; $i < $count; $i++) { 
            $left = $i+1;
            $right = $count-1;
            while ($left < $right) {
                $sum = $nums[$i] + $nums[$left] + $nums[$right];
                $new_gap = abs($sum - $target);
                if (!isset($gap)) {
                    $gap = $new_gap;
                }
                if ($sum == $target) {
                    $res = $sum;
                    break 2;
                }
                if ($new_gap <= $gap) {
                    $res = $sum;
                    $gap = $new_gap;
                }
                $sum > $target ? $right-- : $left++;
            }
        }
    }
    var_dump($res);
    return $res;
}
// threeSumClosest([1,1,1,1], -100);

/**
 * 电话号码的字母组合
 * 
 * @param String $digits
 * @return String[]
 */
function letterCombinations($digits) {
    $res = [];
    if (!$digits) return $res;
    $arr = [2=>'abc',3=>'def',4=>'ghi',5=>'jkl',6=>'mno',7=>'pqrs',8=>'tuv',9=>'wxyz'];
    $str_arr = str_split($digits);
    foreach ($str_arr as $key => $value) {
        $str_arr[$key] = str_split($arr[$value]);
    }
    $len = count($str_arr);
    $fun = function ($index, $str) use (&$fun, &$res, $len, $str_arr)
    {
        if ($len == $index) {
            $res[] = $str;return;
        }
        for ($i=0; $i < count($str_arr[$index]); $i++) { 
            $fun($index+1, $str.$str_arr[$index][$i]);
        }
    };
    $fun(0, '');
    var_dump($res);
    return $res;
}
// letterCombinations('234');

/**
 * 四数之和
 * 给定一个包含 n 个整数的数组 nums 和目标值 target，判断 nums 中是否存在四个元素 a，b，c ，d
 * 使得 a + b + c + d 的值与 target 相等？找出所有满足条件且不重复的四元组
 * @param Integer[] $nums
 * @param Integer $target
 * @return Integer[][]
 */
function fourSum($nums, $target) {
    $res = [];
    sort($nums);
    $count = count($nums);
    if (!$nums || $count < 4) return $res;
    for ($j=0; $j < $count; $j++) { 
        $new_target = $target - $nums[$j];
        for ($i=$j+1; $i < $count; $i++) { 
            $left = $i+1;
            $right = $count-1;
            while ($left < $right) {
                $sum = $nums[$i] + $nums[$left] + $nums[$right];
                if ($sum == $new_target) {
                    $arr = [$nums[$j], $nums[$i], $nums[$left], $nums[$right]];
                    if (!in_array($arr, $res)) $res[] = $arr;
                    while ($left < $right && $nums[$left] == $nums[$left+1]) $left++;
                    while ($left < $right && $nums[$right] == $nums[$right-1]) $right--;
                    $left++;
                    $right--;
                } elseif ($sum > $new_target) {
                    $right--;
                } else {
                    $left++;
                }
            }
        }
    }
    var_dump($res);
    return $res;
}
// fourSum([-3,-2,-1,0,0,1,2,3],0);

/**
 * 删除链表的倒数第N个节点
 * 
 * @param ListNode $head
 * @param Integer $n
 * @return ListNode
 */
function removeNthFromEnd($head, $n) {
    $temp = new ListNode(0);
    $temp->next = $head;
    $first = $temp;
    $second = $temp;
    for($i=1; $i<=$n+1; $i++){
        $first = $first->next;
    }
    while($first != null){
        $first = $first->next;
        $second = $second->next;
    }
    $second->next = $second->next->next;
    var_dump($temp);
    return $temp->next;
}
// removeNthFromEnd([1,2,3,4,5], 2);

/**
 * 有效的括号
 * 给定一个只包括 '('，')'，'{'，'}'，'['，']' 的字符串，判断字符串是否有效
 * 有效字符串需满足：
 * 左括号必须用相同类型的右括号闭合
 * 左括号必须以正确的顺序闭合
 * @param String $s
 * @return Boolean
 */
function isValid($s) {
    if (!$s) return true;
    $arr = [')'=>'(', '}'=>'{',']'=>'['];
    $s_arr = str_split($s);
    $stack = [];//栈
    $key = array_keys($arr);//结束符
    for ($i=0; $i < count($s_arr); $i++) {
        if (in_array($s_arr[$i], $key) && $stack && $arr[$s_arr[$i]] == end($stack)) {
            array_pop($stack);
        } else {
            array_push($stack, $s_arr[$i]);
        }
        var_dump($stack);
    }
    if (count($stack)) {
        return false;
    }
    return true;
}
// isValid('()[]{}');

/**
 * 将两个有序链表合并为一个新的有序链表并返回
 * 
 * @param ListNode $l1
 * @param ListNode $l2
 * @return ListNode
 */
function mergeTwoLists($l1, $l2) {
    $list = new ListNode(0);
    $new = $list;
    while ($l1 != null && $l2 != null) {
        $first = $l1->val;
        $second = $l2->val;
        if ($first > $second) {
            $node = new ListNode($second);
            $l2 = $l2->next;
        } else {
            $node = new ListNode($first);
            $l1 = $l1->next;
        }
        $new = $new->next = $node;
        var_dump($first);
        var_dump($second);
        var_dump($list);
    }
    $new->next = $l1 == null ? $l2 : $l1;
    return $list->next;
}
// mergeTwoLists([1,2,4],[1,3,4]);

/**
 * 括号生成
 * 给出 n 代表生成括号的对数，使其能够生成所有可能的并且有效的括号组合
 * @param Integer $n
 * @return String[]
 */
function generateParenthesis($n) {
    $res = [];
    $fun = function ($str, $left, $right) use ($n, &$res, &$fun)
    {
        if (strlen($str) >= 2*$n) {
            $res[] = $str;
            return;
        }
        if ($left < $n) {
            $fun($str.'(', $left+1, $right);
        } 
        if ($right < $left) {
            $fun($str.')', $left, $right+1);
        }
    };
    $fun('', 0, 0);
    var_dump($res);
    return $res;
}
// generateParenthesis(3);

/**
 * 合并K个排序链表
 * 合并 k 个排序链表，返回合并后的排序链表。请分析和描述算法的复杂度
 * @param ListNode[] $lists
 * @return ListNode
 */
function mergeKLists($lists) {
    $list = $head = new ListNode(0);
    $heap = new SplMinHeap();
    for ($i=0; $i < count($lists); $i++) { 
        if ($lists[$i])
            $heap->insert([$lists[$i]->val, $lists[$i]]);
    }
    while ($heap->valid()) {
        list($val, $node) = $heap->current();
        $heap->next();
        $list->next = new ListNode($val);
        $list = $list->next;
        $node = $node->next;
        if ($node) {
            $heap->insert([$node->val, $node]);
        }
    }
    return $head->next;
}
// mergeKLists([[1,4,5],[1,3,4],[2,6]]);

/**
 * 两两交换链表中的节点
 * 给定一个链表，两两交换其中相邻的节点，并返回交换后的链表
 * 你不能只是单纯的改变节点内部的值，而是需要实际的进行节点交换
 * @param ListNode $head
 * @return ListNode
 */
function swapPairs($head) {
    // 递归解法见python
    $res = $list = new ListNode(0);
    $list->next = $head;
    while ($head != null) {
        //举例1234
        $temp = $head->next;//被交换节点（2）——234
        if ($temp == null) break;//无可交互节点，见于奇数个节点的链表如：[123]
        $head->next = $head->next->next;//head链表删除被交换节点——134
        $temp->next = $head;//被交换节点前移——2134
        $list->next = $temp;//组装list链表（temp无法复用）——02134
        $list = $list->next->next;//完成第一组节点交换，节点位置后移两位——134
        $head = $head->next;//head后移一位——34
        //res链表为：02134；02143；
    }
    return $res->next;
}
// swapPairs([1,2,3,4]);

/**
 * K 个一组翻转链表
 * 给你一个链表，每 k 个节点一组进行翻转，请你返回翻转后的链表
 * k 是一个正整数，它的值小于或等于链表的长度
 * 如果节点总数不是 k 的整数倍，那么请将最后剩余的节点保持原有顺序
 * @param ListNode $head
 * @param Integer $k
 * @return ListNode
 */
function reverseKGroup($head, $k) {

    function recursive($head, $k)
    {
        if ($head == null) {
            return $head;
        }
        $pre = new ListNode(null);
        for ($i=0; $i < $k; $i++) { 
            if ($head == null) {
                return $head;
            }
            $current = $head->next;
            $head->next = $pre;
            $pre = $head;
            $head = $current;
        }
        var_dump($head);
        var_dump($pre);
        $pre->next = recursive($head, $k);
        return $pre;
    }
    $asd = recursive($head, $k);
    var_dump($asd);
    
}
reverseKGroup([1,2,3,4,5], 2);