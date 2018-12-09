<?php
/**
 * 生产下单跟进管理
 */
namespace Home\Model;

use Think\Model;
use Common\Util\Curl;
use Common\Util\Log;
use Shein\MQ;

class ProduceProjectOrderModel extends Model {
     protected $data = '';   //excel对象
     protected $price_dissent_status = array(
        '1'=>'一次报价',
        '2'=>'已通过',
        '3'=>'已拒绝',
        '4'=>'二次报价',
        '5'=>'报价差异',
    );
     protected $price_dissent_status_apply = array(
        1 => '发起核价异议',
        2 => '通过核价异议',
        3 => '拒绝核价异议',
        4 => '通过一次报价', 
     );
    protected $category_type = array(
        1 => '线上单',
        2 => '线下单',
        3 => 'FOB单',
        4 => 'CMT单',
        6 => 'ODM单',
        7 => '新CMT单',
    );
    protected $prepare_type = array(
        0 => '普通订单',
        1 => 'FBA生产单',
        2 => '美国生产单',
        3 => '大客户订单',
        4 => '特殊备货',
        5 => '亚马逊备货',
    );
    protected $prepare_type_prefix = array(
        1 => 'FBA',
        2 => 'US',
        3 => 'DZ',
        4 => 'B',
        5 => 'Y',
    );
    CONST ORDER_STATUS_FOLLOWUP = 60;
    //订单category枚举
    CONST ORDER_CATEGORY_ONLINE = 1;//线上单
    CONST ORDER_CATEGORY_ODM = 6;//ODM单
    CONST ORDER_CATEGORY_FOB = 3;//FOB单
    CONST ORDER_CATEGORY_CMT = 4;//CMT单
    CONST ORDER_CATEGORY_OFFLINE = 2;//线下单
    CONST ORDER_CATEGORY_NEW_CMT = 7;//新CMT单

    //is_delete枚举
    CONST IS_NOT_DELETE = 0;//未删除
    CONST IS_DELETE = 1;//已删除
    CONST IS_DELETE_YJJ= 1;// 已拒绝
    CONST IS_DELETE_WHXJ = 2;// 无货下架
    
    // 订单status，与订单跟进导航栏一致
    const ORDER_STATUS_XDSH = 4;// 下单审核
    const ORDER_STATUS_YXD = 5;// 已下单
    const ORDER_STATUS_ZFDD = 11;// 作废订单
    const ORDER_STATUS_YJJ = 17;// 已拒绝
    const ORDER_STATUS_WHXJ = 37;// 无货下架

    // 操作记录status，与操作记录文字一致
    const OPERATE_STATUS_YXD = 4;// 已下单
    const OPERATE_STATUS_TGSH = 5;// 通过审核
    const OPERATE_STATUS_CXSH = 11;// 重新审核
    const OPERATE_STATUS_YJJ = 17;// 已拒绝
    const OPERATE_STATUS_WHXJ = 18;// 无货下架
    const OPERATE_STATUS_SHJJ = 69;// 审核拒绝

    /**
     * 加载excel
     * @access public
     * @author 杨尚儒 2015-3-17 13:14:37
     */
    public function _initialize(){
        if(!$this->$data){
            vendor("PHPExcel174.excel_class");
            $this->$data = new \Spreadsheet_Excel_Reader(); //加载excel
        }
    }


    /**
     * 查询生产下单信息
     * @access public
     * @param  array $data_arr 查询的数据
     * @return array
     * @author 翁晨 2014-12-29 15:35:57
     * @modify 陈东 2017-6-5 09:35:33 获取供应商R方法修改
     */
    public function getProduceOrder($data_arr = array())
    {
        $where = isset($data_arr['where']) ? $data_arr['where'] : '';
        $field = isset($data_arr['field']) ? $data_arr['field'] : '';
        $order = isset($data_arr['order']) ? $data_arr['order'] : '';
        $limit = isset($data_arr['limit']) ? $data_arr['limit'] : '';
        $produceOrders = $this->table($this->tablePrefix . 'produce_order')->where($where)->order($order)->limit($limit)->field($field)->select();

        if(isset($produceOrders[0]['supplier_id'])){
            $supplierIds = array_column($produceOrders,'supplier_id');
            //供应商统一使用supplier_id获取
            $supplierIds = unique($supplierIds);
            //根据供应商id获取供应商信息
            $supplierInfo = R('SupplierOpenAPI/getSupplierByIds', array($supplierIds));
            $supplierInfo = $supplierInfo['content'];

            foreach($produceOrders as $index=>$produceOrder){
                $produceOrders[$index]['factory'] = $supplierInfo[$produceOrder['supplier_id']]['title'];
                $produceOrders[$index]['supplier_linkman'] = $supplierInfo[$produceOrder['supplier_id']]['title'];
            }
        }

        return $produceOrders;
    }

    /**
     * 查询生产下单操作记录
     * @access public
     * @param  array  $data_arr   查询的数据
     * @return array
     * @author 翁晨 2014-12-29 15:35:57
     */
    public function getProduceOrderStatus($data_arr = array()){
       $where = isset($data_arr['where']) ? $data_arr['where'] : '';
       $field = isset($data_arr['field']) ? $data_arr['field'] : '';
       $order = isset($data_arr['order']) ? $data_arr['order'] : '';
       $limit = isset($data_arr['limit']) ? $data_arr['limit'] : '';
        return $this -> table($this->tablePrefix.'produce_order_status')->where($where)->order($order)->limit($limit)->field($field)->select();
    }

    /**
     * 下单和美国下单跟进列表信息
     * @access public
     * @param  $filter array 搜索条件
     * @return array
     * @author 游佳 2014-12-29 11:11:52
     * @modify 姚法强 2018-5-7 10:11:00  已查验和已完成的次品数量按订单编号查寻【查询次品接口】
     */
    public function getProduceOrderList($filter){
        $filter = page_and_size($filter);
        $where = '';
        $supplier_arr_id_admin = array();           //采购专员绑定的系统权限中的供应商
        $supplier_arr_id_supplier = array();        //采购专员绑定的供应商管理中跟单员的供应商
        $status_where = '';
        if( $filter['goods_sn'] ){// 根据sku搜
            $where .= " AND ( a.goods_sn LIKE '" . $filter['goods_sn'] . "%' )";
        }
        if( $filter['refer_goods_sn'] ){// 根据参考sku搜
            $where .= " AND ( a.refer_goods_sn LIKE '" . $filter['refer_goods_sn'] . "%' )";
        }
        $admin_role = I("session.admin_role");
        if($admin_role==12){//供应商
            $filter['factory'] = I("session.admin_name");
        }
        if ($filter['factory']) {// 根据加工厂搜 
            if($filter['category']==1){//线上单
                $vague = 2;
            }else{
                $vague = 1;
            }
            //根据供应商名称获取供应商信息
            $supplierInfo = R('SupplierOpenAPI/getSupplierInfoBySupplierName', array($filter['factory'], '', $vague));
            if ($supplierInfo[0]['id']) {
                $supplierIds = array_column($supplierInfo, 'id');
                $where .= " AND  a.supplier_id in(" . join(',', $supplierIds) . ")";
            }else{
                return array ('arr' => array(), 'filter' => $filter, 'page_count' => 0, 'record_count' => 0 );
            }

        }
        if(in_array($admin_role, array(22,42,101,102,103,48,40,53))){
            $res = R("Privilege/getAdminRolePermission",array("getProduceOrderList",$admin_role));
            if($res["success"]){
                //采购专员,采购助理，采购主管
                if(empty($res["content"])){
                    
                }else{
                    if($admin_role==22){
                        $supplier_arr_id_admin = explode(',', $res["content"]);
                    }else{
                        $where .= " and a.".$res["field"]." in (" .$res["content"] . ")";
                    } 
                }
            }
        }
        if($admin_role==22){//当角色为【采购专员】进入运营备货页面时，只能看到供应商管理-跟单员分配的供应商数据
            $supplier_arr_id = array();
            $admin_name = I("session.admin_name");
            $supplier_arr = D('SupplierInfo')->getSupplierInfoByFollower($admin_name,$admin_role);
            if($supplier_arr['error']==0){
                $supplier_arr_id_supplier=array_column($supplier_arr['content'],'id');
            }
            $supplier_arr_id = array_merge($supplier_arr_id_admin,$supplier_arr_id_supplier);
            if($supplier_arr_id){
                $where .= " AND a.supplier_id in ('" . implode("','",$supplier_arr_id) . "')";
            }else{
                $where .= " AND a.supplier_id = ''";
            }
            
        }
        if($filter['independent_supplier']){
            $independent_suppliers = R("SupplierInfo/getAlOrAutonomySupplierInfo",array($filter['independent_supplier']));
            if($independent_suppliers['success']){
                $supplierIdArr = array_column($independent_suppliers['content'],'id');
                $supplierIdArr = implode(",",$supplierIdArr);
                if($filter['independent_supplier'] == 1){//线下A
                    $where .= " AND a.supplier_id in  (". $supplierIdArr.") ";
                }elseif($filter['independent_supplier'] == 2){
                    $where .= " AND a.supplier_id in  (". $supplierIdArr.") ";
                }elseif ($filter['independent_supplier'] == 3){//不是自主供应商
                    $where .= " AND a.supplier_id not in (" . $supplierIdArr . ") " ;
                }
            }else{
                return array ('arr' => array(), 'filter' => $filter, 'page_count' => 0, 'record_count' => 0 );
            }
        }
        if ( isset( $filter['is_first_order'] )) {
            if ( $filter['is_first_order'] == 1 ) {// 是首单
                $where .= " AND a.is_first_order = 1 ";
            } elseif ( $filter['is_first_order'] == 0 ) {// 不是首单
                $where .= " AND a.is_first_order = 0 ";
            }
        }
        $category_fabric = array(3,4,7);        //面料采购员
        if( is_numeric($filter['type']) ){
            $where .= " AND a.type =". $filter['type'];
        }
        //样衣借取
        if( $filter['yangyi_result'] ){
            $where .= " AND a.yangyi_result =". $filter['yangyi_result'];
        }
        if(empty($filter['category'] ) && $filter['category'] !== '0'){
            //没有搜索category
            if($admin_role == 32){
                $where .= " AND a.category in ('" .implode(',',$category_fabric)."')";
            }
        }
        if( '10' == $filter['order_status'] ){// 搜索删除状态
            $where .= " AND a.is_delete = 1";
        }elseif( '11' == $filter['order_status'] ){// 搜索无货下架状态
            $where .= " AND a.is_delete = 2";

        }elseif ($filter['order_status']) { // 搜索有状态
            if($filter["order_status"] == 9){
              $where .= " AND a.status in(9,36) and a.is_delete =0";
            }else{
              $where .= " AND a.status = '" . $filter['order_status'] . "' and a.is_delete =0";
            }
            if( is_numeric($filter['category']) ){// 同时存在
                if($admin_role == 32){
                    if(in_array($filter['category'], $category_fabric)){
                        $where .= " AND a.category = " . $filter['category'];
                    }else{
                        $where .= " AND a.category = ''";
                    }
                }else{
                    $where .= " AND a.category = " . $filter['category'];
                }
            }
        }else{// 搜索所有的
            if( is_numeric($filter['category']) ){// 同时存在
                if($admin_role == 32){
                    if(in_array($filter['category'], $category_fabric)){
                        $where .= " AND a.category = " . $filter['category'];
                    }else{
                        $where .= " AND a.category = ''";
                    }
                }else{
                    $where .= " AND a.category = " . $filter['category'];
                }
                 if( 3 == $filter['category'] || 4 == $filter['category'] || 7 == $filter['category']){
                    $where .= " and a.status in ( 14, 13, 6, 7, 8 ,21,68) ";
                 }elseif( 2 == $filter['category'] || 1 == $filter['category'] || 6 == $filter['category'] ){
                    $where .= " and a.status = 14 ";
                 }
            }
            $where .= " AND a.is_delete = 0";
        }
        if( !empty($filter['start_date']) ){// 开始时间
            $where .= " AND a.add_time >= '" . strtotime($filter['start_date']) . "' ";
        }
        if( !empty($filter['end_date']) ){// 结束时间
            $where .= " AND a.add_time <= '" . strtotime($filter['end_date']) . "' ";
        }
        if( !empty($filter['fenpei_start_date']) || !empty($filter['fenpei_end_date']) ){// 如果搜分配时间需要限制状态
            $status_where .= " AND status IN ( 6, 13, 14 )";
        }
        if( !empty($filter['fenpei_start_date']) ){// 分配开始时间
            $status_where .= " AND add_time >= '" . strtotime($filter['fenpei_start_date']) . "' ";
        }
        if( !empty($filter['fenpei_end_date']) ){// 分配结束时间
            $status_where .= " AND add_time <= '" . strtotime($filter['fenpei_end_date']) . "' ";
        }
        if( !empty($filter['add_order']) ){// 下单人搜索
            $status_where .= " AND status = 4 AND user_name = '" . $filter ['add_order'] . "'";
        }
        if( !empty($filter['check_order']) ){// 维护人搜索
            $status_where .= " AND status = 5 AND user_name = '" . $filter ['check_order'] . "'";
        }
        if( !empty($filter['fabric_purchaser']) ){//面料采购员搜索
            if($filter['fabric_purchaser']=='无'){
                $where .= " AND a.fabric_purchaser = ''";
            }else{
                $where .= " AND a.fabric_purchaser = '" . $filter ['fabric_purchaser'] . "'";
            }
        }
        if($filter['is_printing']==='1'){//印花
                $where .= " AND is_printing = '" . $filter ['is_printing'] . "'";
        }else if($filter['is_printing']==='0'){
            $where .= " AND is_printing = '" . $filter ['is_printing'] . "'";
        }
        if( !empty($filter['stored_start_date']) || !empty($filter ['stored_end_date']) ){// 入仓搜索
            $status_where .= " AND status = 16";
        }
        if( !empty($filter['stored_start_date']) ){
            $status_where .= " AND add_time >= '" . strtotime($filter['stored_start_date']) . "' ";
        }
        if( !empty($filter['stored_end_date']) ){
            $status_where .= " AND add_time <= '" . strtotime($filter['stored_end_date']) . "' ";
        }
        if( !empty($filter['receive_start_date']) || !empty($filter ['receive_end_date']) ){// 收货搜索
            $status_where .= " AND status = 12";
        }
        if( !empty($filter['receive_start_date']) ){
            $status_where .= " AND add_time >= '" . strtotime($filter['receive_start_date']) . "' ";
        }
        if( !empty($filter['receive_end_date']) ){
            $status_where .= " AND add_time <= '" . strtotime($filter['receive_end_date']) . "' ";
        }
        if( !empty($filter['songhuo_start_date']) || !empty($filter ['songhuo_end_date']) ){// 收货搜索
            $status_where .= " AND status = 65";
        }
        if( !empty($filter['songhuo_start_date']) ){
            $status_where .= " AND add_time >= '" . strtotime($filter['songhuo_start_date']) . "' ";
        }
        if( !empty($filter['songhuo_end_date']) ){
            $status_where .= " AND add_time <= '" . strtotime($filter['songhuo_end_date']) . "' ";
        }
        if($status_where){// 根据分配时间去搜索出produce_order_id
            $sql = 'SELECT produce_order_id FROM ' . $this->tablePrefix . 'produce_order_status WHERE 1=1 ' . $status_where;
            $pids = $this->getCol($sql);
            $pids = implode(',', array_unique($pids));
            if(!$pids){
                $pids = 0;
            }
            $where .= " AND a.produce_order_id IN (" . $pids . ')';
        }
        if( !empty($filter['produce_order_id']) ){// 生产单号
            $where .= " AND a.produce_order_id = '" . $filter['produce_order_id'] . "' ";
        }
        if( $filter['is_urgent'] ){// 是否标记紧急
            $where .= " AND is_urgent = '" . $filter['is_urgent'] . "' ";
        }
        if( $filter['whether_billed'] == 'y' ){// 是否生成过账单
            $where .= " AND a.bill_id != 0 ";
        }else{
            $where .= " AND a.bill_id = 0 ";
        }
        if( !empty($filter['produce_merchandiser']) ){//生产跟单员
            $where .= " AND a.produce_merchandiser = '" . $filter['produce_merchandiser'] . "' ";
        }
        if($admin_role == 54){//生产跟单员只能看到自己跟的单
            $where .= " AND a.produce_merchandiser = '" . session('admin_name')."'";
        }
        if($filter['prepare_type']!==''){//备货类型
            $where .= " AND a.prepare_type = '" . $filter['prepare_type'] . "' ";
        }
        if($filter['two_process']){//二次工艺
            if($filter['two_process']==1){
                $where .= " AND a.is_two_process = 1";
            }elseif($filter['two_process']==2){
                $where .= " AND a.is_two_process = 0";
            }
        }
        if($filter['full_set']!==''){//是否齐套
            $where .= " AND a.is_full_set = '" . $filter['full_set'] . "' ";
        }
        //快递单搜索
        if($filter["express_num"]){
            $express_where["express_num"] = $filter["express_num"];
            $ids = M("express_order")->field("purchase_no")->where($express_where)->select();
            if(!empty($ids)){
                $ids = array_column($ids, "purchase_no");
                $order_num = implode(",", $ids);
                $where .= " and a.produce_order_id in ($order_num)";
            }else{
                $where .= " and a.produce_order_id =0";
            }
        }
        if($filter['produce_team']!==''){//生产组
            $where .= " AND a.produce_team = '" . $filter['produce_team'] . "' ";
        }
        if($filter['order_identification']!==''){//订单标识
            $where .= " AND a.order_identification = '" . $filter['order_identification'] . "' ";
        }

        if($filter['exceed_type']!=='all'){//超期类型
            $where .= " AND a.row_color = '". $filter['exceed_type']."' ";
        }
        //不要待审备货的数据
        if ( $filter['order_status'] == 10 ) {
            $where .= " and a.status <> 4 and a.status <> 37 ";
        } elseif ( $filter['order_status'] == 11 ) {
            $where .= " and a.status <> 4 and a.status <> 11 ";
        } else {
            $where .= " and a.status <> 4 and a.status <> 11 and a.status <> 37 ";
        }
        
        $sql = ' SELECT COUNT(*) FROM ' . $this->tablePrefix . 'produce_order AS a WHERE 1=1 ' . $where;
        $record_count = $this->getOne($sql);// 搜出来的个数
        $page_count = $record_count > 0 ? ceil( $record_count / $filter['page_size'] ) : 1;// 分页页数
        $produce_status = array();// 所有的状态
        if(0 == $filter['type']){// 获取本页显示的状态信息
            $sql = ' SELECT a.produce_order_id FROM ' . $this->tablePrefix . "produce_order AS a WHERE 1=1 " . $where. " ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order'].
            " LIMIT " . ($filter['page']-1) * $filter['page_size'] . "," . $filter['page_size'];
            $produce_order_ids = $this->getCol($sql);
            if($produce_order_ids){
                if(count($produce_order_ids) == 1){
                    $produce_order_ids[] = 0;// 防止数据是一条的
                }
                $produce_status = $this->getProduceOrderDeliveryTime( implode(',', $produce_order_ids) );
            }
        }

        $sql = "SELECT a.* FROM " . $this->tablePrefix . "produce_order AS a left join " . $this->tablePrefix . "produce_order_qc_report as b on a.produce_order_id = b.produce_order_id WHERE 1=1 " . $where . " group by a.produce_order_id ORDER BY row_color desc," . $filter['sort_by'] . " " . $filter['sort_order'] .
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . "," . $filter['page_size'];
        $arr = $this->getAll($sql);
        //根据供应商id获取供应商信息
        $supplierIdArr = array_column($arr,'supplier_id');
        $supplierArr = R('SupplierInfo/getSupplierByIds',array($supplierIdArr));
        $supplierArr = $supplierArr['content'];

        //根据goods_sn获取 物料类型 和 针梭织作法的信息
        $goods_sn_arr = array_column($arr, "goods_sn");
        foreach ($goods_sn_arr as $key => $value) {
            if(!$value){
                unset($goods_sn_arr[$key]);
            }
        }
        $develop_info = array();
        $develop_data = D('produceProject')->getDevelopDesignInfoBySku($goods_sn_arr);
        if(!$develop_data['code']){
            $develop_info = $develop_data['data'];
        }
        $process_info = array();
        $process_data = D('produceProject')->getProcessBySku($goods_sn_arr);
        if(!$process_data['code']){
            $process_info = $process_data['data'];
        }
        $produce_info = array();
        foreach ($goods_sn_arr as $value) {
            $produce_info[$value] = array(
                'fabric_type'=>'',
                'needle_method'=>$process_info[$value]['knit_woven_name'],
                'style'        =>$process_info[$value]['design_code']
            );
        }
        // print_r($produce_info);die;
        $sku_arr = array();
        $inventory_param=array();
        foreach($arr as $val) {
            $sku_arr[] = $val['goods_sn'];
            //已查验和已完成信息根据接口获取次品数量
            if($val['status']==30 || $val['status']==9){
                $inventory_param[] = array(
                    "goods_sn" => $val["goods_sn"],//skc
                    "purchase_code" => $val["produce_order_id"],//订单编号
                );

            }
        }
        $res_inferior=D('PurchaseListApi')->getInventoryDetail($inventory_param);
        //根据sku获取维护人
        if($filter['maintain_name']!=='') {
            $maintain_info = $this->getMaintainNameBysku($sku_arr);
            if ($maintain_info['success'] == 1) {
                $maintain_arr = $maintain_info['content'];
            } else {
                $maintain_arr = array();
            }
            foreach ($arr as $k_arr => $v_arr) {
                foreach ($maintain_arr as $k_main => $v_main) {
                    if ($v_arr['goods_sn'] == $v_main['goods_sn']) {
                        $arr[$k_arr]['maintain_name'] = $v_main['maintain_name'];
                    } else {

                    }
                }
            }
            if (!empty ($filter ['maintain_name'])) {
                foreach ($arr as $k_ar => $v_ar) {
                    foreach ($filter as $k_f => $v_f) {
                        if ($k_f == 'maintain_name') {
                            if ($v_ar['maintain_name'] != $v_f) {
                                unset($arr[$k_ar]);
                            }
                        }
                    }
                }
            }
        }

        $res = D('ProduceProject')->getStyle($sku_arr);
        if($res['success']){
            $data = $res['content'];
        }else{
            $data = array();
        }
        $temp = array();// 筛选出时间最新的goods_sn对应的设计款号
        $data_style = array();
        foreach($data as $val){
            if(!array_key_exists($val['goods_sn'], $temp)){
                $temp[$val['goods_sn']]= $val;
            }else{
                if($val['add_time'] > $temp[$val['goods_sn']]['add_time']){
                    $temp[$val['goods_sn']] = $val;
                }
            }
            $data_style[strtolower($val['goods_sn'])] = $temp[$val['goods_sn']]['style'];
        }
        $data_goods_n = array();
        if(( $filter['type'] == 0 && $filter['category'] == 1 ) || ( $filter['type'] == 0 && $filter['category'] == 2 ) || ( $filter['type'] == 0 && $filter['category'] == 6 )){
            $sn = array();// 查sku_supplier
            foreach($arr as $key => $value){
                $sn[$key] = $value['goods_sn'];
            }
            $res = R('Goods/getSupplier', array('goods_sn_arr' => $sn));// 调用商品模块的接口
            if($res['success'] == 1){
                $data_goods_n = $res['content'];
            }
        }
        $sku_arr = array_column($arr,'goods_sn');
        $sku_supplier_arr = $this->getSupplierBindBySku($sku_arr);
        if($sku_supplier_arr['success']){
            $sku_supplier_arr = $sku_supplier_arr['content'];
        }
        $order_identification=D('Prepare')->getOrderIdentification();
        $new_order=array();
        foreach ($order_identification as $k_o=>$v_o){
            $new_order[$v_o['id']]=$v_o['basic_name'];
        }
        //判断供应商一级分类是否是生产部
        $supplier_produce=D('SupplierInfo')->getSupplierByCategory(59);
        $supplier_produce_arr=array_column($supplier_produce['content'],'id');
        $yangyi_result = array('','成功','失败');
        foreach ($arr as $key => $value){
            if(in_array($value['supplier_id'],$supplier_produce_arr)){
                $arr[$key]['is_produce']=1;
            }else{
                $arr[$key]['is_produce']=0;
            }
            //替换掉供应商名称跟加工厂和订单类型
            $arr[$key]['supplier_linkman'] = $supplierArr[$value['supplier_id']]['title'];
            $arr[$key]['factory'] = $supplierArr[$value['supplier_id']]['title'];
            $arr[$key]['category_name'] =$this->category_type[$value['category']];
            //替换掉订单标识
            $arr[$key]['order_identification'] =$new_order[$value['order_identification']];

            //添加物料类型针梭织类型的信息
            $arr[$key]["produce_info"] = isset($produce_info[$value['goods_sn']]) ? $produce_info[$value['goods_sn']] : array();
            if($value['category'] == 3 || $value['category'] == 4 ){
                $arr[$key]['delay'] = $this->getProduceDelay($value['produce_order_id']);
            }
            $order_add_time = strtotime(date('Y-m-d', $arr[$key]['add_time']));
            if ($value['status']!=9){//当状态不是已完成时，用当前时间-下单时间
                $today=strtotime(date('Y-m-d'));
                $arr[$key]['spend_time'] = ceil(($today - $order_add_time) / 60 / 60 / 24);//耗时
            }else{//已完成时间-下单时间
                $over_order_time=M('produce_order_status')->where('produce_order_id = '.$value['produce_order_id'].' and status in(9,26)')->getField('add_time');
                $order_over_time = strtotime(date('Y-m-d', $over_order_time));
                $arr[$key]['spend_time'] = ceil(($order_over_time - $order_add_time) / 60 / 60 / 24);//耗时
            }
            $arr[$key]['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            //新增预计超期
            $plan_date = $this->getNowPlanDate($value);
            //进度总天数
            if(!$plan_date['all_date']){
                $arr[$key]['plan_overdue'] = '无';
            }else{
                $arr[$key]['plan_overdue'] = $plan_date['all_date']-15-($plan_date['day']-$arr[$key]['spend_time']);
                $arr[$key]['plan_overdue'] = $arr[$key]['plan_overdue']>0?$arr[$key]['plan_overdue']:0;
            }
            $goods_thumb = D('GoodsManage')->getImageUrl($value['goods_thumb'],'',$value['goods_sn']);
            $arr[$key]['goods_thumb'] = $goods_thumb;
            if(0 == $value['type']){// 操作信息
                ksort($produce_status[$value['produce_order_id']]['data']);
                $arr[$key]['data'] = $produce_status[$value['produce_order_id']]['data'];
                 $arr[$key]['sku_supplier'] = $data_goods_n[$value['goods_sn']];
                if(9 == $value['status'] || 12 == $value['status']){// 返回货期
                    $arr[$key]['delivery'] = $produce_status[$value['produce_order_id']]['delivery'];
                }
            }else{
                $sql_order_status = " SELECT user_name, add_time FROM " . $this->tablePrefix . "produce_order_status WHERE produce_order_id = '" . intval($arr[$key]['produce_order_id']) . "' AND status = '" . intval($arr[$key]['status']) . "' ORDER BY produce_order_status_id DESC LIMIT 1 ";
                $produce_status = $this->getRow($sql_order_status);

                if(!empty($produce_status)){// 不为空增加操作人和操作时间
                    $arr[$key]['user_name'] = $produce_status['user_name'];
                    $arr[$key]['user_add_time'] = date("Y-m-d H:i:s", $produce_status['add_time']);
                }
            }
            $arr[$key]['total'] = $this->getTotalProduceOrder($value['order_info']);// 拆分size字段
            $arr[$key]['size'] = $this->getTotalProduceOrder($value['order_info'], true);
            array_unshift($arr[$key]['size'],$arr[$key]['box_num']);

            if ( in_array( $value['category'], array( 3, 4, 6 ,7)) )// 生产大货，从质检报告取次品 
            {
                $w = array(
                    'produce_order_id' => $value['produce_order_id'],
                    'type' => 1,// 稽查报告
                );
                $res = $this->getSamplingInspect($value['produce_order_id']);
                $defective_num_arr = $res['data'][0]['defective_detail'];
                $defective_num = '';
                foreach ($defective_num_arr as $k => $v_size) {
                    $defective_num .= $v_size['size'].':'.$v_size['count'].'<br/>';
                }
                if ( $defective_num )
                {
                    $arr[$key]['defective_num'] = $defective_num;
                    $arr[$key]['defective_total'] = $this->getTotalProduceOrder($defective_num);// 拆分size字段
                    $arr[$key]['defective_size'] = $this->getTotalProduceOrder($defective_num, true);
                }
            }
            else
            {
                $w = array(
                    'purchase_code' => $value['produce_order_id'],
                    'goods_sn' => $value['goods_sn'],// 大货QC
                );
                $res = $this->getDefectiveList(array($w));
                if ( !$res['code'] ) {
                    $defective_num_arr = $res['data']['summaries'];
                    $defective_num = '';
                    foreach ($defective_num_arr as $k => $v_size) {
                        $defective_num .= $v_size['size'].':'.$v_size['count'].'<br/>';
                    }
                    $arr[$key]['defective_num'] = $defective_num;
                    $arr[$key]['defective_total'] = $this->getTotalProduceOrder($defective_num);// 拆分size字段
                    $arr[$key]['defective_size'] = $this->getTotalProduceOrder($defective_num, true);
                }
            }
            if($value['status']==30 || $value['status']==9){
                $inferior_num='';
                foreach($res_inferior[$value['produce_order_id']] as $k1=>$v1){
                    $inferior_num.=$k1.':'.$v1.'<br/>';
                }
                $arr[$key]['defective_num'] = $inferior_num;
                $arr[$key]['defective_total'] = $this->getTotalProduceOrder($inferior_num);// 拆分size字段
                $arr[$key]['defective_size'] = $res_inferior[$value['produce_order_id']];
            }
            //供应商货号
            $arr[$key]['sku_supplier'] = '无';
            $arr[$key]['yangyi_result_name'] = $yangyi_result[$value['yangyi_result']];
            if($sku_supplier_arr){
                foreach ($sku_supplier_arr['info'] as $v) {
                    if($value['goods_sn'] == $v['sku']){
                        if($v['productSupplierCode']){
                            $arr[$key]['sku_supplier'] = $v['productSupplierCode'];
                        }else{
                            $arr[$key]['sku_supplier'] = '无';
                        }
                        break;
                    }
                }

            }else{
                $arr[$key]['sku_supplier'] = '无';
            }
            $arr[$key]['ishavefabricinfo'] = $this->isHaveFabricInfo($arr[$key]['produce_order_id']);// 查询是否有相关的面辅料信息
            $arr[$key]['style'] = $produce_info[$value['goods_sn']]['style'];// 组合货号
            if($arr[$key]['box_num']!=0){
                $arr[$key]['received_info']='箱数:'.$arr[$key]['box_num'].'<br/>'.$arr[$key]['received_info'];//
            }
        }
        //拼装sku列表
        foreach ($arr as$k_pro=>$produceOrderObj) {

            $skuList[] = $produceOrderObj['goods_sn'];
        }
        unset($produceOrderObj);
        //拼接材质信息$develop_info['material_name']
        foreach ($arr as &$produceOrderObj) {
            $produceOrderObj['material'] = $develop_info[$produceOrderObj['goods_sn']]['material_name'];
        }
        unset($produceOrderObj);
        //获取订单当日跟进信息
        $redis = redises();
        $redis=$redis->redis();
        $add_time = strtotime(date('Y-m-d',time()));
        //插入之前查重
        foreach($arr as &$produceOrderObj){
            //从缓存中读取是否跟进信息
            $res = $redis->get('produce_order_id'.$produceOrderObj['produce_order_id'].'_follow_time'.$add_time);
            $produceOrderObj['follow_up_today'] = $res;
        }
        unset($produceOrderObj);
        // print_r($arr);die;
        return array ('arr' => $arr, 'filter' => $filter, 'page_count' => $page_count, 'record_count' => $record_count );
    }
    /**
     * 根据sku获取维护人
     * @access public
     * @author 姚法强 2017-10-31
     */
    public function getMaintainNameBysku($sku){
        $where['goods_sn']  = array('in',$sku);
        $date_time = date('Ymd',time());
        $where['date_time'] = $date_time;
        $date=M('Prepare_sku')->where($where)->select();
        if($date){
            return array(
                'success' => 1,
                'content' => $date
            );
        }else{
            return array(
                'success' => 0,
                'message' => '数据获取失败'
            );
        }
    }
    /**
     * 根据sku获取货期
     * @access public
     * @author 姚法强 2017-10-31
     */
    public function getHuoqiBysku($sku){
        $where['goods_sn']  = array('in',$sku);
        $date=M('Prepare_attr')->where($where)->select();
        if($date){
            return array(
                'success' => 1,
                'content' => $date
            );
        }else{
            return array(
                'success' => 0,
                'message' => '数据获取失败'
            );
        }
    }

    /**
     * 根据supplier_id获取合理的存销比
     * @access public
     * @author 姚法强 2017-10-31
     */
    public function getScaleBysupplier($supplier){
        $where['rs_supplier_info.id']  = array('in',$supplier);
        $where['rs_supplier_category.status']  = 1;
        $where['rs_supplier_category.is_delete']  = 0;
        $date=M('Supplier_info')->join('rs_supplier_category ON rs_supplier_info.category_id=rs_supplier_category.id')
            ->where($where)->field('rs_supplier_info.id,rs_supplier_category.reasonable_ratio,rs_supplier_category.status')->select();
        if($date){
            return array(
                'success' => 1,
                'content' => $date
            );
        }else{
            return array(
                'success' => 0,
                'message' => '数据获取失败'
            );
        }
    }

    /**
     * 待审备货订单
     * @access public
     * @author 姚法强 2017-11-1 10:28:08
     */
    public function produceOrderCheckList($filter){
        $filter = page_and_size($filter);
        $where = '';
        $status_where='';
        $sku_where='';
        if($filter['status']==4||$filter['status']==11||$filter['status']==37){
            $where .= " AND a.status = " . $filter['status'];
        }else{
            $where .= " AND a.status in " . $filter['status'];
        }
        // 根据订单编号搜
        if($filter['produce_order_id'] ){
            $where .= " AND a.produce_order_id = " . $filter['produce_order_id'];
        }
        // 根据sku搜
        if( $filter['goods_sn'] ){
            $where .= " AND ( a.goods_sn LIKE '" . $filter['goods_sn'] . "%' )";
        }

        // 根据订单类型搜
        if($filter['prepare_type']!=='' ){
            $where .= " AND a.prepare_type = " . $filter['prepare_type'];
        }
        if ( isset( $filter['is_first_order'] )) {
            if ( $filter['is_first_order'] == 1 ) {// 是首单
                $where .= " AND a.is_first_order = 1 ";
            } elseif ( $filter['is_first_order'] == 0 ) {// 不是首单
                $where .= " AND a.is_first_order = 0 ";
            }
        }
        // 是否标记紧急
        if( $filter['is_urgent'] ){
            $where .= " AND is_urgent = '" . $filter['is_urgent'] . "' ";
        }
        //订单标识
        if($filter['order_identification']!==''){
            $where .= " AND a.order_identification = '" . $filter['order_identification'] . "' ";
        }
        if( !empty($filter['start_date']) ){// 开始时间
            $where .= " AND a.add_time >= '" . strtotime($filter['start_date']) . "' ";
        }
        if( !empty($filter['end_date']) ){// 结束时间
            $where .= " AND a.add_time <= '" . strtotime($filter['end_date']) . "' ";
        }
        if( !empty($filter['songhuo_end_date']) ){
            $status_where .= " AND add_time <= '" . strtotime($filter['songhuo_end_date']) . "' ";
        }
        if( !empty($filter['add_order']) ){// 下单人搜索
            $status_where .= " AND status = 4 AND user_name = '" . $filter ['add_order'] . "'";
        }

        if($status_where){// 根据分配时间去搜索出produce_order_id
            $sql = 'SELECT produce_order_id FROM ' . $this->tablePrefix . 'produce_order_status WHERE 1=1 ' . $status_where;
            $pids = $this->getCol($sql);
            $pids = implode(',', array_unique($pids));
            if(!$pids){
                $pids = 0;
            }
            $where .= " AND a.produce_order_id IN (" . $pids . ')';
        }
        if($filter ['min_sale_num'] !== ''){//至少卖出
            $sku_where .= " and b.total_sale >= ".$filter ['min_sale_num'];
        }
        if(!empty($filter ['max_sale_num'])){//至多卖出
            $sku_where .= " and b.total_sale <= '".$filter ['max_sale_num']."' ";
        }
        if($filter ['min_scale'] != ''){//最小存销比
            $sku_where .= " and b.scale_gz >= '".$filter ['min_scale']."' ";
        }
        if($filter ['max_scale'] != ''){//最大存销比
            $sku_where .= " and b.scale_gz <= '".$filter ['max_scale']."' ";
        }
        //根据供应搜索
        if($filter['supplier_linkman']){
            $supplier_info = M('supplier_info')->where(array('is_delete'=>0,'title'=>array('like',$filter['supplier_linkman'].'%')))->select();
            // echo M()->getLastSql();die;
            if($supplier_info){
                $where .= " and a.supplier_id in ('".implode(',',array_column($supplier_info, 'id'))."')";
            }else{
                return array ('arr' => array(),'get_grade_list' => array(),'filter' => $filter, 'page_count' => 0, 'record_count' => 0 );
            }
        }
        // echo $where;die;
        //警戒库存
        if($filter ['is_jingjie'] != ''){//是否警戒
            if($filter ['is_jingjie']==1){
                $sku_where .= " and b.gz_alter_flag = 1 ";
            }elseif($filter ['is_jingjie']==0){
                $sku_where .= " and b.gz_alter_flag = 0 ";
            }
        }
        //商品层次
        if(!empty($filter['goods_level'])){
            $sku_where .= " and b.goods_level = '".$filter['goods_level']."'";
        }
        //prepare_sku表的添加时间是今天
        $time=date('Ymd',time());
        $on_sku_where = " and b.date_time = '".$time."'";
        //从缓存中读取商品层次
        $get_grade_list = S('Prepare_get_grade_list');
        if(empty($get_grade_list)){
            //商品层次的下拉值，通过接口获取商品中心层次。
            $uri = '/grade/get_grade_list';
            //$res = pdcApiRequest($uri);//向商品中心请求接口
            if(isset($res['data'])){
                S('Prepare_get_grade_list',$res['data'],3600);
            }
            $get_grade_list = $res['data'];
        }
        $where.= " and is_delete = 0 ";
        $sql = ' SELECT COUNT(*) FROM ' . $this->tablePrefix . 'produce_order AS a WHERE 1=1 ' . $where;
        $record_count = $this->getOne($sql);// 搜出来的个数
        $page_count = $record_count > 0 ? ceil( $record_count / $filter['page_size'] ) : 1;// 分页页数
        $produce_status = array();// 所有的状态
        if(0 == $filter['type']){// 获取本页显示的状态信息
            $sql = ' SELECT a.produce_order_id FROM ' . $this->tablePrefix . "produce_order AS a WHERE 1=1 " . $where. " ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order'].
                " LIMIT " . ($filter['page']-1) * $filter['page_size'] . "," . $filter['page_size'];
            $produce_order_ids = $this->getCol($sql);
            if($produce_order_ids){
                if(count($produce_order_ids) == 1){
                    $produce_order_ids[] = 0;// 防止数据是一条的
                }
                $produce_status = $this->getProduceOrderDeliveryTime( implode(',', $produce_order_ids) );
            }
        }
        $start = microtime();
        $startTime = explode(' ',$start);
        $startMicro = $startTime[0];
        $sql = "SELECT a.*,b.total_sale,b.total_sale_info,b.scale_gz,b.goods_level,b.maintain_name FROM " . $this->tablePrefix . "produce_order AS a left join  " . $this->tablePrefix . "prepare_sku as b on a.goods_sn = b.goods_sn " .$on_sku_where. " WHERE 1=1 " . $where . $sku_where . " group by a.produce_order_id ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order'] .
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . "," . $filter['page_size'];
        $arr = $this->getAll($sql);
        $stop = microtime();
        $stopTime = explode(' ',$stop);
        $stopMicro = $stopTime[0];
        $spend =  $stopMicro -  + $startMicro;
        if($spend>1){
            $content = array(
                'content' => M()->_sql() . '执行时间大于一秒',
            );
            Log::err('/saveProduceOrderPrice',$content);
        }
        //根据供应商id获取供应商信息
        $supplierIdArr = array_column($arr,'supplier_id');
        $supplierArr = R('SupplierInfo/getSupplierByIds',array($supplierIdArr));
        $supplierArr = $supplierArr['content'];
        $sku_arr = array();
        foreach($arr as $val) {
            $sku_arr[] = $val['goods_sn'];
            $supplier_arr[]=$val['supplier_id'];
        }
        //根据supplier_id判断存销比是否应该标红
        $scale_info=$this->getScaleBysupplier($supplier_arr);
        if ($scale_info['success'] == 1) {
            $scale_arr= $scale_info['content'];
        } else {
            $scale_arr = array();
        }
        //根据sku获取货期
        $huoqi_info=$this->getHuoqiBysku($sku_arr);
        if ($huoqi_info['success'] == 1) {
            $huoqi_arr = $huoqi_info['content'];
        } else {
            $huoqi_arr = array();
        }

        foreach($arr as $k_a=>$v_a){
            //7天销量
            $arr[$k_a]['total_sale_info'] =D('Prepare')->mathSum($v_a['total_sale_info']);
            foreach ($huoqi_arr as $k_huoqi => $v_huoqi) {
                if ($v_a['goods_sn'] == $v_huoqi['goods_sn']) {
                    $arr[$k_a]['huoqi'] = $v_huoqi['huoqi'];
                    $arr[$k_a]['beihuo'] = $v_huoqi['beihuo'];
                } else {
                }
            }
            //合理存销比
            foreach($scale_arr as $k_scale=>$v_scale){
                if($v_scale['id']==$v_a['supplier_id']){
                    $arr[$k_a]['reasonable_ratio']=$v_scale['reasonable_ratio'];
                }
            }
        }
        //根据sku获取维护人
        $new_name=array();
        if($filter['maintain_name']!=='') {
            $maintain_info = $this->getMaintainNameBysku($sku_arr);
            if ($maintain_info['success'] == 1) {
                $maintain_arr = $maintain_info['content'];
            } else {
                $maintain_arr = array();
            }
            foreach ($arr as $k_arr => $v_arr) {
                    //维护人
                foreach ($maintain_arr as $k_main => $v_main) {
                    if ($v_arr['goods_sn'] == $v_main['goods_sn']) {
                        $arr[$k_arr]['maintain_name'] = $v_main['maintain_name'];
                    } else {

                    }
                }
            }
            if (!empty ($filter ['maintain_name'])) {
                foreach ($arr as $k_ar => $v_ar) {
                    foreach ($filter as $k_f => $v_f) {
                        if ($k_f == 'maintain_name') {
                            if ($v_ar['maintain_name'] == $v_f) {
                                $new_name[]=$v_ar;
                            }
                        }
                    }
                }
            }
            if($new_name){
                $record_count = count($new_name);// 搜出来的个数
                $page_count = $record_count > 0 ? ceil( $record_count / $filter['page_size'] ) : 1;// 分页页数
                $arr=$new_name;
            }
        }

        $res = D('ProduceProject')->getStyle($sku_arr);
        if($res['success']){
            $data = $res['content'];
        }else{
            $data = array();
        }
        $temp = array();// 筛选出时间最新的goods_sn对应的设计款号
        $data_style = array();
        foreach($data as $val){
            if(!array_key_exists($val['goods_sn'], $temp)){
                $temp[$val['goods_sn']]= $val;
            }else{
                if($val['add_time'] > $temp[$val['goods_sn']]['add_time']){
                    $temp[$val['goods_sn']] = $val;
                }
            }
            $data_style[strtolower($val['goods_sn'])] = $temp[$val['goods_sn']]['style'];
        }
        $data_goods_n = array();
        if(( $filter['type'] == 0 && $filter['category'] == 1 ) || ( $filter['type'] == 0 && $filter['category'] == 2 ) || ( $filter['type'] == 0 && $filter['category'] == 6 )){
            $sn = array();// 查sku_supplier
            foreach($arr as $key => $value){
                $sn[$key] = $value['goods_sn'];
            }
            $res = R('Goods/getSupplier', array('goods_sn_arr' => $sn));// 调用商品模块的接口
            if($res['success'] == 1){
                $data_goods_n = $res['content'];
            }
        }
        $sku_arr = array_column($arr,'goods_sn');
        $sku_supplier_arr = $this->getSupplierBindBySku($sku_arr);
        if($sku_supplier_arr['success']){
            $sku_supplier_arr = $sku_supplier_arr['content'];
        }
        $order_identification=D('Prepare')->getOrderIdentification();
        $new_order=array();
        foreach ($order_identification as $k_o=>$v_o){
            $new_order[$v_o['id']]=$v_o['basic_name'];
        }
        foreach ($arr as $key => $value){
            //替换掉备货类型
            $arr[$key]['prepare_type']=$this->prepare_type[$value['prepare_type']];
            //替换掉供应商名称跟加工厂和订单类型
            $arr[$key]['supplier_linkman'] = $supplierArr[$value['supplier_id']]['title'];
            $arr[$key]['factory'] = $supplierArr[$value['supplier_id']]['title'];
            $arr[$key]['category_name'] =$this->category_type[$value['category']];
            //替换掉订单标识
            $arr[$key]['order_identification'] =$new_order[$value['order_identification']];

            $order_add_time = strtotime(date('Y-m-d', $arr[$key]['add_time']));
            if ($value['status']!=9){//当状态不是已完成时，用当前时间-下单时间
                $today=strtotime(date('Y-m-d'));
                $arr[$key]['spend_time'] = ceil(($today - $order_add_time) / 60 / 60 / 24);//耗时
            }else{//已完成时间-下单时间
                $over_order_time=M('produce_order_status')->where('produce_order_id = '.$value['produce_order_id'].' and status in(9,26)')->getField('add_time');
                $order_over_time = strtotime(date('Y-m-d', $over_order_time));
                $arr[$key]['spend_time'] = ceil(($order_over_time - $order_add_time) / 60 / 60 / 24);//耗时
            }
            $arr[$key]['add_time'] = date('Y-m-d H:i:s', $value['add_time']);

            $goods_thumb = D('GoodsManage')->getImageUrl($value['goods_thumb'],'',$value['goods_sn']);
            $arr[$key]['goods_thumb'] = $goods_thumb;
            if(0 == $value['type']){// 操作信息
                ksort($produce_status[$value['produce_order_id']]['data']);
                $arr[$key]['data'] = $produce_status[$value['produce_order_id']]['data'];
                $arr[$key]['sku_supplier'] = $data_goods_n[$value['goods_sn']];
                if(9 == $value['status'] || 12 == $value['status']){// 返回货期
                    $arr[$key]['delivery'] = $produce_status[$value['produce_order_id']]['delivery'];
                }
            }else{
                $sql_order_status = " SELECT user_name, add_time FROM " . $this->tablePrefix . "produce_order_status WHERE produce_order_id = '" . intval($arr[$key]['produce_order_id']) . "' AND status = '" . intval($arr[$key]['status']) . "' ORDER BY produce_order_status_id DESC LIMIT 1 ";
                $produce_status = $this->getRow($sql_order_status);
                if(!empty($produce_status)){// 不为空增加操作人和操作时间
                    $arr[$key]['user_name'] = $produce_status['user_name'];
                    $arr[$key]['user_add_time'] = date("Y-m-d H:i:s", $produce_status['add_time']);
                }
            }
            $arr[$key]['total'] = $this->getTotalProduceOrder($value['order_info']);// 拆分size字段
            $arr[$key]['size'] = $this->getTotalProduceOrder($value['order_info'], true);
            array_unshift($arr[$key]['size'],$arr[$key]['box_num']);
        }
        //拼装sku列表和筛选供应商
        $new_arr=array();
        foreach ($arr as $k_supplier=>$produceOrderObj) {
            $skuList[] = $produceOrderObj['goods_sn'];
            // 根供应商搜
            // if($filter['supplier_linkman']){
            //     if(strpos($produceOrderObj['supplier_linkman'],$filter['supplier_linkman']) === 0){
            //         $new_arr[]=$produceOrderObj;
            //     }
            // }
            //供应商分类
            if(!empty($filter['category_id'])){
                $supplier_list = D('SupplierInfo')->getSupplierByCategory($filter['category_id']);
                $supplier_list_res = array_column( $supplier_list['content'], 'title');
                $supplier_linkman=$produceOrderObj['supplier_linkman'];
                if(in_array($supplier_linkman,$supplier_list_res)){
                    $new_arr[]=$produceOrderObj;
                }
            }
        }
        if($new_arr ||$filter['category_id']){
        $record_count = count($new_arr);// 搜出来的个数
        $page_count = $record_count > 0 ? ceil( $record_count / $filter['page_size'] ) : 1;// 分页页数
        $arr=$new_arr;
        }
        return array ('arr' => $arr,'get_grade_list' => $get_grade_list,'filter' => $filter, 'page_count' => $page_count, 'record_count' => $record_count );
    }

 /**
     * 获取二次工艺跟进信息
     * @access public
     * @param array   $filter   项目数据
     * @return array
     * @author 李永旺 2017-12-02 10:47:41
     */
    public function getProduceSecondaryProcessList($filter = array()){
        $where = " a.is_delete=0 ";//搜索条件
        $table=$this->tablePrefix.'produce_secondary_process as a';
        if (!array_key_exists('page', $filter)) {
            $filter = page_and_size($filter);
        }
        // 工艺单号
        if(!empty($filter['produce_secondary_process_sn'])){
            $where .= " AND ( a.produce_secondary_process_sn = '" .  mysql_like_quote ($filter ['produce_secondary_process_sn']). "' )";
        }

        // 订单编号
        if(!empty($filter['produce_order_id'])){
            $where .= " AND ( a.produce_order_id = '" .  mysql_like_quote ($filter ['produce_order_id']). "' )";
        }

        if(!empty($filter['goods_sn'])){
            $where .= " AND ( b.goods_sn LIKE '" . mysql_like_quote($filter ['goods_sn']) . "%' )";
        }
        // $admin_name=session('admin_name');
        // $admin_info=D('Admin')->getAdmin($admin_name);

        if(!empty($filter['supplier_linkman'])){
               // 通过供应商名称获取供应商id
                $supplier_id = M('supplier_info')->where(array('title'=>$filter['supplier_linkman'],'is_delete'=>array('in','0,1')))->getField('id');
                if ($supplier_id) {
                  $where .= " AND ( b.supplier_id = '" .  mysql_like_quote ($supplier_id). "' )";
                }
        }

        //设计款号
        if(!empty($filter['design_style'])){
            $where .= " AND a.design_style like '" . $filter['design_style'] . "%' ";
        }

        //备货类型
        if($filter['prepare_type']!='all'){
            $where .= " AND b.prepare_type = ".$filter['prepare_type']."";
        }

        //是否首单
        if($filter['is_first_order']!='all'){
            $where .= " AND b.is_first_order = ".$filter['is_first_order']."";
        }

          // 工艺类型
        if(!empty($filter['process_type'])){
            $where .= " AND ( a.process_type LIKE '" . mysql_like_quote($filter ['process_type']) . "%' )";
        }
        //工艺厂
        if(!empty($filter['process_factory'])){
            $where .= " AND a.process_factory like '" . mysql_like_quote($filter['process_factory']) . "%' ";
        }
        if(!empty($filter['status'])){
            $filter_status = $filter['status'];
            $where .= " AND a.status = '" . $filter_status . "' ";
        }
       
        // echo $filter['time_type'];die;
        // $filter['start_date']='2017-12-07 00:00:00';
        if($filter['time_type'] && ((!empty($filter['start_date'])) || (!empty($filter['end_date'])))){ // 时间类型
            $newIds_str='';
            $wheres='';
            $wheres.="status=".$filter['time_type'];

            if(!empty($filter['start_date'])){
                $wheres .= " AND add_time >= '".strtotime($filter['start_date'])."' ";
            }
            if(!empty($filter ['end_date'])){
                $wheres .= " AND add_time <= '".strtotime($filter['end_date'])."' ";
            }

                $res=$this->table($this->tablePrefix . 'produce_secondary_process_status')->where($wheres)->field('produce_process_id')->select();
               
                foreach ($res as $key => $value) {
                    $newId[$key]=$value['produce_process_id'];
                }
                $newIds_str=implode(",",$newId);
                //  echo '<pre>';
                // var_dump($newIds_str);
                // die;
            if (!empty($newIds_str) ){
            $where .= " AND a.id in (" . $newIds_str . " )";
            }else{
                $where .= " AND a.id in ('0')";
            }
        }


        if(!empty($filter['start_days1']) || (!empty($filter['start_days2']))){ // 时间类型
            $newIds_str2='';
            $wheres='';
            $wheres.="status=3";
                // 发起时间
                $res2=$this->table($this->tablePrefix . 'produce_secondary_process_status')->where($wheres)->field('produce_process_id,add_time,status')->select();
                $newId2='';
                foreach ($res2 as $k => $v) {
                    //完成时间
                    $process_status_finish_time = $this->table($this->tablePrefix . 'produce_secondary_process_status')->where('produce_process_id="'.$v['produce_process_id'].'" and status=4')->field('user_name,add_time')->order('add_time desc')->find();
                        if ($process_status_finish_time['add_time']) {
                            $dateBetween=floor(($process_status_finish_time['add_time']-$v['add_time'])/86400);
                        }else{
                            $dateBetween=floor((time()-$v['add_time'])/86400);  
                        }  
                        if(!empty($filter['start_days1']) && empty($filter['start_days2'])){
                             if ($dateBetween >= $filter['start_days1']) {
                                $newId2[$k]=$v['produce_process_id'];
                             }else{
                                $newId2[$k]='0';
                            }
                        }elseif(empty($filter['start_days1'])&& !empty($filter['start_days2'])){
                            if ($dateBetween <= $filter['start_days2']) {
                                $newId2[$k]=$v['produce_process_id'];
                            }else{
                                $newId2[$k]='0';
                            }
                        }elseif(!empty($filter['start_days1'])&& !empty($filter['start_days2'])){
                            if (($dateBetween >= $filter['start_days1']) && ($dateBetween <= $filter['start_days2'])) {
                                $newId2[$k]=$v['produce_process_id'];
                            }else{
                                $newId2[$k]='0';
                            }
                        }
                     
                }
                $newIds_str2=implode(",",$newId2);
           // echo '<pre>';
           // print_r($newIds_str);die;
        }
        if (!empty($newIds_str2) ){
           // $newIds_str.=','.$newIds_str2;
            // $newIds_str .= ','."$newIds_str2";
            $where .= " AND a.id in (" . $newIds_str2 . " )";
        }
        

        if ($filter['is_urgent'] != 'all'){
            $where .= " AND b.is_urgent =  " . $filter['is_urgent'] . " ";
        }
        //从给我货的生产跟进传过来的供应商id  给我货加工厂过滤条件
        if (isset($filter['supplier_id']) && $filter['pagefrom']==1){
            $where .= " AND b.supplier_id =  " . $filter['supplier_id'] . " ";
            $where .= " AND b.status <> 5 AND ((b.category=4 AND b.status <> 13 ) OR (b.category in(3,7)))";
        }
        //从给我货的生产跟进传过来的工艺厂id  给我货工艺厂过滤条件
        if (isset($filter['supplier_id']) && $filter['pagefrom']==2){
           $where .= " AND a.status in (2,3,4) AND a.sec_supplier_id = ". $filter['supplier_id'] ." ";
        }
        $where .= " AND b.is_delete=0";
        //  //给我货传过来只能是FOB和CMT单
        // if($filter['geiwohuo']==1){
        //     $where .= " AND b.status <> 5 AND ((b.category=4 AND b.status <> 13 ) OR b.category=3)";
        // }

        $produce_secondary_process=$this->tablePrefix."produce_secondary_process";
        $produce_order=$this->tablePrefix."produce_order";
        
        $on="a.produce_order_id=b.produce_order_id";
        // $sql="select count(a.id) from $table where $where";

        // echo $sql;die;
        // $record_count=$this->getOne($sql);
        $table.=" left join $produce_order as b";
        $sql="select count(a.id) from $table on $on where $where";
         $record_count=$this->getOne($sql);
        $filter['start_date'] = I('start_date', '');//开始时间

        $page_count = $record_count > 0 ? ceil ($record_count / $filter ['page_size']) : 1;
        $sql="select a.*,b.produce_id,b.order_info,b.prepare_type,b.goods_sn,b.goods_thumb,b.supplier_id,b.status as order_status,b.category,b.produce_merchandiser from $table on $on where $where order by ".$filter['sort_by']." ".$filter['sort_order']." limit ".($filter['page']-1)*$filter['page_size'].",".$filter['page_size'];
        $arr=$this->getAll($sql);
        if($arr){
            foreach($arr as $key => $value){
                 $arr[$key]['goods_thumb'] = D('GoodsManage')->getImageUrl($value['goods_thumb'],'',$value['goods_sn']);
                if($value['status']==3) {
                    $process_status = $this->table($this->tablePrefix . 'produce_secondary_process_status')->where('produce_process_id="' . $value['id'] . '" and status=3')->field('user_name,add_time')->order('add_time desc')->find();
                        $time=time();
                        if ($process_status['add_time']) {
                           $date=floor(($time-$process_status['add_time'])/86400);
                           $arr[$key]['set_days'] = $date;
                        }else{
                           $arr[$key]['set_days'] = ''; 
                        }

                }elseif ($value['status']==4) {
                    //发起时间
                     $process_status_launch = $this->table($this->tablePrefix . 'produce_secondary_process_status')->where('produce_process_id="' . $value['id'] . '" and status=3')->field('user_name,add_time')->order('add_time desc')->find();
                     //完成时间
                     $process_status_finish = $this->table($this->tablePrefix . 'produce_secondary_process_status')->where('produce_process_id="' . $value['id'] . '" and status=4')->field('user_name,add_time')->order('add_time desc')->find();

                        if ($process_status_launch['add_time'] && $process_status_finish['add_time']) {
                           $date=floor(($process_status_finish['add_time']-$process_status_launch['add_time'])/86400);
                           $arr[$key]['set_days'] = $date;
                        }else{
                           $arr[$key]['set_days'] = ''; 
                        }
                }else{
                    $arr[$key]['set_days'] = '';
                }



                // $isFirstOrder=$this->isFirstOrder($value['goods_sn'],$value['produce_id'], $value['produce_order_id']);

                // $arr[$key]['is_first_order'] = $isFirstOrder['isFirstOrderStatus'];
                // if ($filter ['is_first_order']!='all'){
                //     if ($arr[$key]['is_first_order']!=$filter ['is_first_order']) {
                //         unset($arr[$key]);
                //     }
                // }

                $supplier_id=$value['supplier_id'];
                //页面供应商统一使用supplier_id获取
                $supplierIds = array($supplier_id);

                //根据供应商名称获取供应商信息
                 $supplierInfo=$this->getSupplierByIds($supplierIds);  
                
                $supplierInfo = $supplierInfo['content'];
                $arr[$key]['supplier_linkman'] = $supplierInfo[$supplier_id]['title'];


            }
        }
        $arr_current=$arr;
        return array (
            'arr' => $arr_current,
            'filter' => $filter,
            'page_count' => $page_count,
            'record_count' => $record_count
            );
    }
    /**
     * 获得供应商信息，二次工艺跟进调用
     * @access public
     * @author 李永旺 2017-12-02 12:10:52
     */
    public function getSupplierByIds($ids,$index = array('id'))
    {
        if (empty($ids)) {
            return array('success' => '0', 'message' => '供应商名称为空');
        }

        $res = D('supplierInfo')->getSupplierInfoAPI(array(
                'where' => array(
                    'id' => array('in', $ids)
                ),
                'field' => 'id,title,company,contact_address')
        );

        return array('success' => '1', 'content' => formart_arr_index($res,$index));
    }

        /**
     * 获取二次工艺进度信息订单跟进中显示
     * @param $produce_order_id 生产单id
     * @access public
     * @author 李永旺 2014-12-12 11:11:52
     */
    public function getrProduceSecondaryProcessStatusInfo($produce_order_id){
        $process_status = $this->getAll ( "select * from " . $this->tablePrefix . "produce_secondary_process where produce_order_id = '" . $produce_order_id . "' order by id asc" );
        return array ('process_status' => $process_status);
    }
    /**
     * 获得生产管理各个状态的订单数量
     * @access public
     * @string $where sql条件语句
     * @author 游佳 2014-12-29 11:11:52
     * @modify 李永旺 2017-12-26 10:36:04 增加获取新CMT单默认数量
     */
    public function getPoNum($type){
        if($type == 0) {
            $where ="and type = 0 ";
            if(session('admin_role')==54){//生产跟单员只能看到自己跟的单
                $where .= " and produce_merchandiser='".session('admin_name')."'";
            }
            $content['shouhuo_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE status = 12  and is_delete = 0 $where ");
            $content['songhuo_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE status = 35  and is_delete = 0 $where ");
            $content['wancheng_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order WHERE status = 9  and is_delete = 0 and bill_id = 0 $where ");
            //下单未分类数量
            $content['xiadan_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE status = 5 and category = 0 and is_delete = 0 $where ");

            //删除订单的数量
            $content['delete_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order WHERE is_delete = 1 $where ");
            //无货下架订单的数量
            $content['soldout_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order WHERE is_delete = 2 $where ");
            //线上单数量
            $content['online_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 1 and status = 14 and is_delete = 0 $where ");
            //线下单的数量
            $content['offline_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 2 and status = 14 and is_delete = 0 $where ");
            //ODM单的数量
            $content['odm_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 6 and status = 14 and is_delete = 0 $where ");
            //FOB单的数量
            $content['fob_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 3 and status IN(14,6,13,7,8,21) and is_delete = 0 $where ");
            $arr_FOB = array(
                'fob_14_num' => '14',
                'fob_6_num' => '6',
                'fob_13_num' => '13',
                'fob_7_num' => '7',
                'fob_8_num' => '8',
                'fob_21_num' => '21'
            );
            // 循环遍历出OEM类下面的操作状态数量
            foreach ($arr_FOB as $key => $value) {
                $content[$key] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 3 and status = $value and is_delete = 0 $where ");
            }
            //OEM单的数量
            $content['oem_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 4 and status IN(14,13,7,6,8,21) and is_delete = 0 $where ");
            $arr_OEM = array(
                'oem_14_num' => '14',
                'oem_13_num' => '13',
                'oem_7_num' => '7',
                'oem_6_num' => '6',
                'oem_8_num' => '8',
                'oem_21_num' => '21'
            );
            // 循环遍历出OEM类下面的操作状态数量

            foreach ($arr_OEM as $key => $value) {
                $content[$key] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 4 and status = $value and is_delete = 0 $where ");
            }
            //已查验单的数量
            $content['verified_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE   status =30 and is_delete = 0 $where ");
            //已退货单的数量
            $content['return_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE   status = 34 and is_delete = 0 $where ");
            //新CMT单的数量
            $content['new_cmt_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 7 and status IN(14,13,7,68,8,21) and is_delete = 0 $where ");
            $arr_new_CMT = array(
                'new_cmt_14_num' => '14',
                'new_cmt_13_num' => '13',
                'new_cmt_7_num' => '7',
                'new_cmt_68_num' => '68',
                'new_cmt_8_num' => '8',
                'new_cmt_21_num' => '21'
            );
            // 循环遍历出新CMT单类下面的操作状态数量
            foreach ($arr_new_CMT as $key => $value) {
                $content[$key] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE category = 7 and status = $value and is_delete = 0 $where ");
            }

        }
        $content['bill_3_num']  = $this->getOne("select count(bill_id) from ".$this->tablePrefix."bill where bill_status=3  and type=1");
        if($type == 1){
            $content['xiadan_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE status = 5 and is_delete = 0 and category=0 and type = 1 ");
            $content['fenpei_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE status = 6 and is_delete = 0 and category=0 and type = 1 ");
            $content['wancheng_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order  WHERE status = 9 and is_delete = 0 and category=0 and type = 1 ");

            //删除订单的数量
            $content['delete_num'] = $this->getOne("SELECT count(produce_order_id) from " . $this->tablePrefix . "produce_order WHERE is_delete = 1  and category=0 and type = 1 ");
        }
        return $content;
    }

    /**
     * 更新生产订单状态 入仓登记如果是供应商是“伙伴类型”不进行生成账单
     * @param $produce_order_id 生产单号
     * @param $status 生产状态
     * @access public
     * @return array
     * @author 李健 2014-12-31 15:17:22
     * @modify 曹禺 2017-11-2 17:51:43 自动产生样衣待借数据
     * @modify 姚法强 2017-11-15 15:41:42 增加CMT工厂交货时间
     * @modify 李永旺 2017-12-27 10:20:00 修改新CMT单是否齐套规则
     */
    public function updateProduceOrder($produce_order_id, $status, $category = 0,$is_pda=false){
        $time = time();// 处理时间
        $delivery = '';// 货期信息
        $return = array(
                    'msg' => '',
                    'status' => '1',
                    'content' => array()
                  );
        $sql = "/*master*/select * from ".$this->tablePrefix.'produce_order where produce_order_id = '.$produce_order_id;
        $produce_order = $this->getRow($sql);
        if(empty($produce_order)){
            $return['msg'] = '无此项目';
        }
        //权限控制
        $result = $this->checkOrderRole($produce_order_id, $status);
        if($result !== true){
            $return['status'] = 1;
            $return['msg'] =  $result;
            return $return;
        }
        //供应商统一使用supplier_id获取
        $supplierIds = array($produce_order['supplier_id']);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        $produce_order['factory'] = $supplierInfo[$produce_order['supplier_id']]['title'];


        if($category == 1 || $category == 2 || $category == 6 || $status == 13 || $status == 8){// 线上单、线下单完成，以及FOB单、OEM单点击下一步，检查实价
            $data['where'] = "produce_order_id = '".$produce_order_id."'";
            $arr = $this->getProduceOrder($data);
            $order_price = $arr[0]['order_price'];
            if($order_price == ''){// 没有实价
                $return['msg'] = '必须添加实价才能进行下一步操作';
            }
        }
        if($produce_order['status'] != 12 && !$produce_order['category'] && !$produce_order['type']){
            $return['msg'] = '广州单请先分类';
        }
        $produce_status = $this->getProduceOrderStatusName($status);
        if(0 == $produce_order['type'] && ($produce_order['status'] != 12 && $produce_order['status'] != 35 && ($produce_order['status']<30 || $produce_order['status']>34))){
            $produce_order_line = $this->produceOrderStatusLine($produce_order['category']);// 验证顺序 
            $new_produce_order_line = array_flip($produce_order_line);
            $next_status = $produce_order_line[$new_produce_order_line[$produce_order['status']]+1];
            if($produce_order['status'] == 35){
                if($status != 12){
                    $next_produce_status = $this->getProduceOrderStatusName(12);
                    $return['msg'] = "下一步只能执行".$next_produce_status;
                }
            }else{
                if($next_status != $status){
                    $next_produce_status = $this->getProduceOrderStatusName($next_status);
                    $return['msg'] = "下一步只能执行".$next_produce_status;
                }
            }
        }
        if($produce_order['status'] == $status){
            $return['msg'] = "已操作过$produce_status,请勿重复点击";
        }
        if($status == 8 && empty($produce_order['cut_info'])){// 如果是上车位，必须有裁床数量
            $return['msg'] = '没有添加裁床数量！';
        }
        if(($status == 9 ||$status == 12) && empty($produce_order['received_info'])){// 如果是到货或者已完成，必须有到货数量
            $return['msg'] = '没有添加到货数量！';
        }
        if($status == 9){// 已收货状态的任何订单类型在操作已完成按钮时，需判断入仓数量是否为空
            if(empty($produce_order['stored_num'])){
                $return['msg'] = '完成前，请先填写入仓数量！';
            }

        }
        if(($status == 13 && $produce_order['category']==3)||($produce_order['category']==4&&$status==7)){
            if($produce_order['supplier_id'] == 0){
                $return['msg'] = '请确定已分配加工厂';
            }
            if(empty($produce_order['produce_merchandiser'])||empty($produce_order['produce_merchandiser'])=='无'){
                $return['msg'] = '请先分配生产跟单员';
            }
        }
        $data = array();
        //是否批色
        if($status == 6 && $produce_order['status'] == 13){
            if($produce_order['is_apply_color'] != 1){
                $return['msg'] = '请先进行批色操作';
            }
            //CMT分配中
            //存储二次工艺
            if($produce_order['is_two_process'] == 1){
                $two_process = D('ProduceProject')->getTwoProcess(array($produce_order['produce_order_id']=>$produce_order['goods_sn']));
                if($two_process){
                    $two_process_info = '';
                    foreach ($two_process[$produce_order['produce_order_id']] as $key => $value) {
                        $two_process_info .= $value['specification'].'(金额:'.round($value['unit_price'],2).')<br>';
                    }
                    $data['two_process_info'] = $two_process_info ? $two_process_info : '';
                }
            }
            
        }
        //是否齐套
        if($status == 6 && $produce_order['status'] == 13 && $category!=7){
            if($produce_order['is_full_set'] != 1){
                $return['msg'] = '订单已齐套才能进行下一步';
            }
        }
        //验证已送货点击已收货 初检不能为退货
        if($status == 12 && $produce_order['status'] == 35){
            $report = M('produce_order_qc_report')->where(array('produce_order_id'=>$produce_order_id,'type'=>2))->find();
            if(!empty($report) && $report['status'] == 2){
                $return['msg'] = '此单为退货拒收，不能进行收货' ;
            }
        }
        $data['status'] = $status;
        $data['handle_time'] = $time;
        if($status == 9){// 如果是已完成状态，将紧急标志变为0
            $data['is_urgent'] = 0;
        }
        //增加cmt单工厂交货时间
        if($status ==7 && $category==''){
            $cate_info=M('produce_order')->where("produce_order_id = $produce_order_id")->find();
            if($cate_info['category']==4){
                $new_cate=$cate_info['category'];
            }
        }
          if(($status ==7 && $category==4)||$new_cate==4){

              // 生产总时效 来自基础档案款式生产信息的生产总时效
                $goods_sn['goods_sn']=$produce_order['goods_sn'];
                $goods_sn['is_delete']=0;
                $produce_time = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
                  if($produce_time['produce_time']==''){
                      $produce_time['produce_time']=0;
                  }
            if($produce_order['prepare_type']==4){
                $data['factory_work_time']=$produce_order['back_time'];//特殊备货  预计收货-2
            }else{
                $data['factory_work_time']=time()+(($produce_time['produce_time']-7)*86400);//非特殊备货 生产总时效-7+当前日期
            }
        }


          $admin_name = I('session.admin_name');
        if($return['msg'] == ''){
            $commit_data = array();
            $this->startTrans();// 事务开启
            $commit_data[] = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->save($data);// 更新状态
            if(($status == 12 && ($produce_order['category'] == 1 || $produce_order['category'] == 2 || $produce_order['category'] == 6) && $produce_order['status'] != 35) || $produce_order['status'] == 34){
                $commit_data[] = $this->addProduceOrderStatus($produce_order_id, 65);// 线上单、线下单、ODM单操作【已收货】时，同时生成已送货的记录及已收货的记录，已送货的时间与已收货的时间一致。
            }
            //如果是cmt到分配中或者新cmt到发料中,向面辅料erp发送数据
           if(($produce_order['category'] == 4 && $status == 6) || ($produce_order['category'] == 7 && $status == 68)){
                $post_wms_data = array(
                    'factory'=>$produce_order['factory'] ? $produce_order['factory'] : '',
                    'produce_order_id'=>array($produce_order_id),
                );
                $post_wms_url = C('URL_SEND_PRODUCE_INFO').'index.php/Api/RequisitionOrder/updateDistributeStatus';
                file_put_contents('Public/text_log_wms.txt', json_encode($post_wms_data));
                curlPost($post_wms_url,$post_wms_data,true);
           }
           // Fob分配中、cmt分配中、新cmt发料中推送数据到mes系统
           if (in_array($category, array(3,4,7)) && ( $status == 6 || $status==68 )) {
              $this->setMesByCmtOrder($produce_order_id,$category,'new');
           }

            if($status == 6 || $status==68 ){
                if ($status==68) {
                    $data_a['produce_id'] = $produce_order['produce_id']; //进程管理ID
                    $data_a['produce_order_id'] = $produce_order['produce_order_id'];//下单跟进ID
                    $data_a['goods_sn'] = $produce_order['goods_sn'];//sku
                    $data_a['is_print'] = true;//是否打印，这里和配料单打印调用一个接口，而接口中需要判断权限，故添加一个打印标识
                    $content = D('ProduceProjectOrder')->produceOrderFabricList($data_a);
                    foreach ($content['content'] as $key_c => $value_c) {
                        if ($value_c['name']==1) {
                            $data_back = array();
                            $data_back['produce_order_id']=$produce_order['produce_order_id'];
                            $data_back['fabric_info_id']=$value_c['fabric_info_id'];
                            if (!empty($data_back)) {
                                 D('ProduceProjectOrderApi')->updateIsBack($data_back);
                            }
                        }
                    }

                }

                $w = array(
                    'goods_sn' => $produce_order['goods_sn'],
                    'produce_order_id'=>array('NEQ',$produce_order['produce_order_id']),
                    'is_delete' => 0,
                );
                $res = M('produce_order')->where($w)->select();         
                if ($res) {
                    $is_first_order = 0;
                } else {
                    $is_first_order = 1;
                }
                $where_s = array(
                    'goods_sn' => $produce_order['goods_sn'],
                    'produce_order_id'=>array('NEQ',$produce_order['produce_order_id']),
                    'supplier_id' => $produce_order['supplier_id'],
                    'is_delete' => 0,
                    'add_time'=>array('GT',time()-86400*180),
                );
                $res2 = M('produce_order')->where($where_s)->select();
                 // 当订单状态变更为：FOB-分配中、CMT-分配中、新CMT-发料中时，若此订单为首单或180天内此加工厂未生产过此SKU，只要满足任一条件，则向样衣管理传送一条借衣数据。
                if ( $is_first_order == 1 || (empty($res2)) ) {
                    // $commit_data[] = $this->addProduceOrderStatus($produce_order_id, $status);// 增加操作记录
                    $this->getYangyiResult($produce_order,1,session('admin_name'));
                }               
            }
            if($status == 35){
                $commit_data[] = $this->addProduceOrderStatus($produce_order_id, 65);// 增加操作记录

                //当生产订单变为已送货时，则自动产生状态为待借未配衣的待借样衣数据
                $this->getYangyiResult($produce_order,3,$produce_order['factory']);

            }else{
                $commit_data[] = $this->addProduceOrderStatus($produce_order_id, $status);// 增加操作记录
            }
            if(in_array($status,array(12,21)) && in_array($produce_order['category'], array(3, 4,6,7))){// 如果下一步是已收货，则生成或者更新质检报告
                $report_type = $status==12?1:2;//1稽查质检报告在后整中->已收货，2初检质检报告车缝中->后整中
                $qc_data = array(// 质检报告数据
                    "produce_order_id" => $produce_order_id,
                    "status" => 0,
                    "user_name" => $admin_name,
                    "add_time" => $time,
                    "type" =>$report_type,
                );
                $report_arr = $this->produceReportAddOrUpdate($qc_data);
                $commit_data = array_merge($commit_data,$report_arr);
            }
            if($status==9&&$is_pda){
                $add_bill= $this->batchProduceOrderBill(array($produce_order_id),false);//添加新账单  如果是供应商是“伙伴类型”不进行生成账单
                if($add_bill['success']){
                    $commit_data[]=true;
                }else{
                    $commit_data[]=false;
                    db_commit($commit_data);
                    $return['msg'] = $add_bill['msg'];
                    return $return;
                }
            }
            db_commit($commit_data);// 事务提交

            $return['status'] = 0;
            if(9 == $status || 12 == $status){// 计算货期
                $produce_order_delivery = $this->getProduceOrderDeliveryTime($produce_order_id);
                foreach($produce_order_delivery['delivery'] as $k=>$v){
                    if('full' == $k){// 全程
                        $delivery .= "全程 : <font color='red'>".$v."</font><br/>";
                    }elseif('cmt' == $k){
                        if($produce_order['category'] >2 && $produce_order['category'] != 6){// CMT
                            $delivery .= "CMT : <font color='red'>".$v."</font><br/>";
                        }
                    }elseif('sewing' == $k){
                        if($produce_order['category'] == 4){// oem 车缝
                            $delivery .= "车缝 : <font color='red'>".$v."</font><br/>";
                        }
                    }
                }
            }
            $time_format = empty($produce_order['type']) ? "m-d H:i" : "Y-m-d H:i:s";
            $return['content'] = array("produce_order_id"=>$produce_order_id, "admin_name"=>I('session.admin_name'), "user_add_time"=>date($time_format, $time), "status"=>$status, 'category'=>$produce_order['category'], 'data'=>$produce_status, 'delivery'=>$delivery);
        }
        return $return;
    }

    /**
     * 检查 odm 已完成 FOB、CMT单-车缝中的【下一步】的权限
     *
     * @param number $produce_order_id
     * @param number $status
     * @author 周阳阳 2017年6月16日 上午11:28:40
     */
    public function checkOrderRole($produce_order_id = 0, $status = 0)
    {
        //不是12  无需校验权限
        if ($status != 12) {
            return true;

        }

        $where = array(
            'produce_order_id' => $produce_order_id,
            'is_delete' => 0
        );

        $order_info = $this->table($this->tablePrefix . 'produce_order')
            ->field('status,category')
            ->where($where)
            ->find();
            $is_check = false;
        // odm 订单
        if ($order_info['category'] == 6) {
            $is_check = true;
        } elseif ($order_info['status'] == 21 || in_array($order_info['category'], array(1,2,6,3,4))) { // FOB、CMT单-车缝中
            $is_check = true;
        }
        if($is_check){
            if(!D('Privilege')->checkRoleBool('produce_order_received')){
                return '没有操作[已收货]权限!';
            }
        }

        return true;
    }

    /**
     * 回滚生产订单状态
     * @access public
     * @param $produce_order_id 生产单号
     * @param $status 生产状态
     * @author 杨尚儒 2015-1-27 22:49:27
     * @modify 陈东 2016-9-19 10:55:53 fob回滚到已下单时清除实价
     */
    public function updateProduceRollbackOrder($produce_order_id, $status){
        $time = time();// 处理时间
        $delivery = '';// 货期信息
        $return = array('msg' => '',
                        'status' => '1',
                        'content' => array()
                  );
        $produce_order = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->find();
        if(empty($produce_order)){
            $return['msg'] = '无此项目';
        }
        if($produce_order['status'] != 12 && !$produce_order['category'] && !$produce_order['type']){
            $return['msg'] = '广州单请先分类';
        }
        $produce_status = '';// 当前状态
        $produce_status = $this->getProduceOrderStatusName($status);
        if(0 == $produce_order['type']){
            if($produce_order['status'] != 12){
                // 验证顺序
                if($produce_order['category'] == 3){
                      $produce_order_line = array(5, 6, 13, 7, 8, 21);
                }else if($produce_order['category'] == 4){
                      $produce_order_line = array(5, 13, 6, 7, 8, 21);
                }else if($produce_order['category'] == 7){
                      $produce_order_line = array(5, 13, 68, 7, 8, 21);
                }else if($produce_order['category'] == 1 || $produce_order['category'] == 2 || $produce_order['category'] == 6) {
                     $produce_order_line = array(5, 14);
                }
                $new_produce_order_line = array_flip($produce_order_line);
                $next_status = $produce_order_line[$new_produce_order_line[$produce_order['status']]-1];
                if($next_status != $status){
                    $next_produce_status = $this->getProduceOrderStatusName($next_status);
                    $return['msg'] = "只能回滚到：".$next_produce_status;
                }
            }
        }
        if($produce_order['status'] == $status){
           $return['msg'] = "已操作过$produce_status, 请勿重复点击";
        }
        $data = array();// UPDATE操作需要的数组
        $data['status'] = $status;
        $data['handle_time'] = $time;
        if($produce_order['status']==6 && $produce_order['category']==3){
            $data['category'] = 0;
            $data['order_price'] = '';//fob单回滚到已下单时清除实价
            // $this->setMesByCmtOrder($produce_order_id,$produce_order['category'],'cancel');//Fob分配中上一步时向mes传送取消订单数据 
        }
        if($produce_order['status']==13 && $produce_order['category']==4){
            $data['category'] = 0;
        }
        if($produce_order['status']==13 && $produce_order['category']==7){
            $data['category'] = 0;
            $data['order_price'] = '';//新CMT单回滚到已下单时清除实价
        }
        if($produce_order['status']==14 && ($produce_order['category']==1 || $produce_order['category']==2 || $produce_order['category']==6)){
            $data['category'] = 0;
        }
        if($produce_order['status']==6 && $produce_order['category']==4){
            // $this->setMesByCmtOrder($produce_order_id,$produce_order['category'],'cancel');//cmt分配中上一步时向mes传送取消订单数据
        }
        if($produce_order['status']==68 && $produce_order['category']==7){
            $this->setMesByCmtOrder($produce_order_id,$produce_order['category'],'cancel');//新cmt发料中上一步时向mes传送取消订单数据
        }
        if($return['msg'] == ''){
            $commit_data = array();
            $this->startTrans();// 开启事务
            $commit_data[] = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->save($data);// 更新状态
            $commit_data[] = $this->addProduceOrderStatus($produce_order_id, $status);// 写入操作记录
            db_commit($commit_data);// 提交事务
            $return['status'] = 0;
            $time_format = empty($produce_order['type']) ? "m-d H:i" : "Y-m-d H:i:s";
            $return['content'] = array("produce_order_id"=>$produce_order_id,"admin_name"=>I('session.admin_name'),"user_add_time"=>date($time_format, $time),"status"=>$status,'category'=>$produce_order['category'],'data'=>$produce_status,'delivery'=>$delivery);
        }
        return $return;
    }

    /**
     * 删除、恢复生产订单(可以批量)
     * @access public
     * @param $ids 修改的状态
     * @param $is_delete 删除类型（1是删除2是无货下架0是没有删除）
     * @return void
     * @author 李健 2014-12-31 10:59:45
     * @modify 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function isdeleteProduceOrder($ids,$is_delete=1){
        //重新审核状态11
        if($is_delete == 11){
            $status = 11;       //重新审核
            $edit_is_delete = 0;
            $data['is_delete'] = 0;
            $data['status'] = 11;
        }elseif($is_delete == 37){
            $status = 11;       //重新审核
            $edit_is_delete = 0;
            $data['is_delete'] = 0;
            $data['status'] = 37;
        }else{
            $data['is_delete'] = $is_delete;
        }
        if($is_delete == 2){
            $status = 18;       //无货审核
            $edit_is_delete = 0;
        }
        if($is_delete == 1){
            $status = 17;       //已删除
            $edit_is_delete = 0;
        }
        if($is_delete == 0){
            $status = 22;       //已恢复
            $edit_is_delete = 1;
        }
        if(is_array($ids)&& $ids){
            if(!$is_delete){
                //批量恢复删除或者批量恢复无货下架
                $where = 'produce_order_id IN('.join(',',$ids).') AND is_delete IN(1,2)'; //传数组则可以删除或者恢复多个。
            }else{
                //批量删除
                if($is_delete ==1 || $is_delete == 11 || $is_delete == 37){
                    //删除的时候查询判断订单类型不为FBA且入仓数量不为 空
                    $produce_list = M('produce_order')->where(array('produce_order_id'=>array('in',implode(',',$ids))))->select();
                    foreach($produce_list as $v){
                        $isHaveStoredNum = $this->isHaveStoredNum( $v['stored_num'] );
                        if($v['prepare_type']!=1 && $isHaveStoredNum ){
                            if($is_delete ==1){
                                return array('code'=>1,'msg'=>'入仓数量不为空，删除失败');
                            }elseif ($is_delete ==11||$is_delete ==37){
                                return array('code'=>1,'msg'=>'入仓数量不为空，重新审核失败');
                            }
                        }
                    }
                }
                $where = 'produce_order_id IN('.join(',',$ids).') AND is_delete = '.$edit_is_delete; //传数组则可以删除多个。
            }

        }else{
            if($is_delete==1 || $is_delete == 11|| $is_delete == 37){
                $res = M('produce_order')->where(array('produce_order_id'=>$ids))->find();
            }

            //订单类型不为FBA且入仓数量不为 空
            $isHaveStoredNum = $this->isHaveStoredNum( $res['stored_num'] );
            if($res['prepare_type']!=1 && $isHaveStoredNum ){
                if($is_delete ==1){
                    return array('code'=>1,'msg'=>'入仓数量不为空，删除失败');
                }elseif ($is_delete ==11||$is_delete ==37){
                    return array('code'=>1,'msg'=>'入仓数量不为空，重新审核失败');
                }
            }
            if($res['bill_id']==0){
                if(!$is_delete){//单个恢复删除或者恢复无货下架
                    $where = "produce_order_id = $ids AND is_delete IN(1,2)";
                }else{
                    $where = "produce_order_id = $ids AND is_delete = ".$edit_is_delete;
                }
            }else{
                return array('code'=>1,'msg'=>'此订单已生成账单不可删除，请核实');
            }

        }
        $time = time();
        $add_data['status'] = $status;
        $add_data['user_name'] = I('session.admin_name');
        $add_data['add_time'] = $time;
        if(is_array($ids)&& $ids){
            foreach($ids as $item){
                $add_data['produce_order_id'] = $item;
                $this->table($this->tablePrefix.'produce_order_status')->add($add_data);
            }
        }else{
            $add_data['produce_order_id'] = $ids;
            $this->table($this->tablePrefix.'produce_order_status')->add($add_data);
        }
        $result = $this->table($this->tablePrefix."produce_order")->where($where)->save($data);
        if($result){
            return array('code'=>0,'content'=>$result,'msg'=>'操作成功');
        }else{
            return array('code'=>1,'content'=>$result,'msg'=>'操作失败');
        }
    }

    /**
     * 是否有入仓数量
     *
     * @return bool
     * @author 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function isHaveStoredNum( $storedNumStr )
    {
        if ( ! $storedNumStr )
        {
            return false;
        }
        $tmp = explode( '<br/>', $storedNumStr );
        foreach ( $tmp as $tmp2 ) {
            $tmp2 = explode( ':', $tmp2 );
            $num = $tmp2[1];
            if ( (int) $num > 0 ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 操作审核生产订单
     * @access public
     * @param $ids 修改的状态
     * @param $is_delete 删除类型（1是删除2是无货下架0是没有删除）
     * @return void
     * @author 姚法强 2014-12-31 10:59:45
     * @modify 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function do_action($ids,$status,$remark=''){
        if ( is_array($ids) && $ids ) {
            ;
        } else {
            $ids = array( $ids );
        }
        if ( $status == 5 ) {// 通过
            $act = 'pass';
        } elseif ( $status == 17 ) {// 拒绝
            $act = 'refuse';
        } else {
            return array('code'=>1, 'msg'=>'不支持的操作类型，请联系张权' );
        }
        // $ids已被转换为数组
        $this->startTrans();
        $commit = array();
        $orders = M('produce_order')->where(array('produce_order_id' => array( 'in', $ids )))
                    ->limit(count($ids))->select();
        if ( count( $orders ) == 0 )
        {
            M()->rollback();
            return array('code'=>1, 'msg'=>'找不到订单信息' );
        }
        if ( count($orders) != count($ids) )
        {
            M()->rollback();
            return array('code'=>1, 'msg'=>'存在无信息的订单' );
        }
        foreach ( $orders as $order ) {
            $produce_order_id = $order['produce_order_id'];
            $tmp = $this->decideOrderChanges( $act, $order );// 计算出订单更新和操作记录
            if ( $tmp['code'] != 200 ) {
                M()->rollback();
                return array('code'=>1, 'msg'=>"订单{$produce_order_id}回滚状态时，找不到上次状态" );
            }
            $save_order = $tmp ['info'] ['save_order'];// 更新订单
            $add_status = $tmp ['info'] ['add_status'];// 操作记录
            $commit[] = $result = $this->table($this->tablePrefix."produce_order")
                                ->where(array('produce_order_id' => $produce_order_id))->save($save_order);

            if ( $result && ($save_order['is_delete']==1||$save_order['is_delete']==2)) {
            $ret=$this->setMesByCmtOrder($produce_order_id,$order['category'],'cancel');//取消订单推送至mes系统
            $actual_order_num=0;
            $actual_order_num=$this->getTotalProduceOrder($order['order_info']);
            $where_schedule=array();
            $where_schedule['produce_group']=$order['produce_team'];
            $where_schedule['set_datetime']=strtotime(date('Y-m-d',$order['add_time']));
            $commit[] = $this->table($this->tablePrefix . 'produce_schedule_info')->where($where_schedule)->setDec('actual_order_num',$actual_order_num);//生产计划表实际下单数量增加
            }                   
            $commit[] = $this->table($this->tablePrefix.'produce_order_status')->add($add_status);
            if ( $remark )
            {
                $add_order_remark['operator'] = I('session.admin_name');
                $add_order_remark['time'] = time();
                $add_order_remark['produce_order_id'] = $produce_order_id;
                $add_order_remark['remark_info']=$remark;
                $commit[] = $this->table($this->tablePrefix.'produce_order_remark')->add($add_order_remark);
            }
        }
        //待审备货只有通过时候才生成工艺单
        $msg = '操作失败';
        if ($status==5) {
            // 批量通过操作;
            if(!is_array($ids)){
                $ids = array($ids);
            }
            if(is_array($ids) && $ids){
                //SKU或设计款号向PLM获取该SKU的二次外发工艺
                $goods_sns = M('produce_order')->where(array('produce_order_id'=>array('in',$ids)))->getField('goods_sn',true);
                // print_r($goods_sns);die;
                // 根据大货 sku 获取大货成本数据
                $sku_list=$goods_sns;
                $res_secondary_process_info = D('ProduceProject')->getCostBatchListBySku($sku_list); 
                foreach($ids as $produce_order_id){
                    $secondary_process_info = $resProduceOrderInfo= array();
                    $where=array();
                    $where=array(
                        'produce_order_id' =>$produce_order_id
                        );
                    $resProduceOrderInfo= $this->table($this->tablePrefix."produce_order")->where($where)->field('produce_order_id,goods_sn,supplier_id,category')->find();
                    // 参考sku获取规则
                    $refer_sku='';
                    // 根据订单SKU向款式生产信息查询【参考款号】字段是否为空，若为空，则参考SKU为空；
                    if ($resProduceOrderInfo['goods_sn']){ 
                        $where_sku=array();
                        $where_sku=array(
                            'goods_sn'=>$resProduceOrderInfo['goods_sn']
                            );
                        $resProduceStyleInfo= $this->table($this->tablePrefix."produce_style_info")->where($where_sku)->find();
                        // 若不为空，则根据【参考款号】在款式生产信息表查询【设计款号】，再根据【设计款号】查询出此设计款号对应的SKU，此SKU即为【参考SKU】。
                        if ($resProduceStyleInfo['refer_style']) {
                           $res_refer_style_info= M('produce_style_info')->where(array('design_style'=>$resProduceStyleInfo['refer_style'],'is_delete'=>0))->find();
                           // 若根据【参考款号】在款式生产信息表未能查询到【设计款号】，则根据【参考款号】在款式生产信息表查询【SKU】，若能查询到，则此SKU即为【参考SKU】；
                            if ($res_refer_style_info['design_style']) {
                               $res_refer_sku_info= M('produce_style_info')->where(array('design_style'=>$res_refer_style_info['design_style'],'is_delete'=>0))->find();
                               if ($res_refer_sku_info['goods_sn']) {
                                   $refer_sku=$res_refer_sku_info['goods_sn'];
                               }else{
                                   $refer_sku='';
                               }
                            }elseif(empty($res_refer_style_info['design_style'])){
                                $res_refer_sku_info= M('produce_style_info')->where(array('goods_sn'=>$resProduceStyleInfo['refer_style'],'is_delete'=>0))->find();
                                if ($res_refer_sku_info['goods_sn']) {
                                   $refer_sku=$res_refer_sku_info['goods_sn'];
                                }else{
                                   $refer_sku='';
                                }
                            }
                        }else{
                            $refer_sku='';
                        }
                        $commit[]= M('produce_order')->where(array('produce_order_id'=>$produce_order_id,'is_delete'=>0))->setField('refer_goods_sn',$refer_sku);//设置订单表中的refer_goods_sn字段
                    }
                    $supplier_id=$resProduceOrderInfo['supplier_id'];
                    //是否首单
                    $w = array(
                        'is_delete' => 0,
                        'produce_order_id' => $resProduceOrderInfo['produce_order_id'],
                    );
                    $res = M('produce_order')->where($w)->find();
                    $is_first_order =$res['is_first_order'];
                    $last_sql1=M()->getLastSql();
                    // 当订单为首单时，根据订单的SKU到生产款式信息查询此订单是否有参考SKU，若有参考SKU，则根据参考SKU变更该订单的加工厂。若无参考SKU，则无需变更订单的加工厂
                    if ($is_first_order == 1) {
                        $res_refer_sku_data= M('produce_style_info')->where(array('goods_sn'=>$resProduceOrderInfo['goods_sn'],'is_delete'=>0))->find();
                        if ($res_refer_sku_data['refer_goods_sn']) {
                           $supplier_info = $this->getSupplierBindBySku($res_refer_sku_data['refer_goods_sn']);
                            $final_supplier_id = $supplier_info['content']['info'][0]['supplierId'];//供应商id

                                $supplierIds = array($final_supplier_id);
                                //根据供应商id获取供应商信息
                                $supplierInfo = $this->getSupplierByIds($supplierIds);
                                $supplier_linkman = $supplierInfo['content'][$final_supplier_id]['title'];
                            $commit[]=$ret= M('produce_order')->where(array('produce_order_id'=>$produce_order_id,'is_delete'=>0))->setField(array('supplier_id'=>$final_supplier_id,'supplier_linkman'=>$supplier_linkman));
                                $supplier_id= $final_supplier_id;
                                $last_sql=M()->getLastSql();
                        }
                    }
                    $add_log_data=array(
                            'resProduceOrderInfo'=>$resProduceOrderInfo,
                            'is_first_order'=>$is_first_order,
                            'res_refer_sku_data'=>$res_refer_sku_data,
                            'last_sql1'=>$last_sql1,
                            'last_sql'=>$last_sql,
                            'final_supplier_id'=>$final_supplier_id,
                            'supplier_linkman'=>$supplier_linkman
                        );
                    $msg='待审备货:存储数据日志!';
                      $info_log = array(
                        'time' => time(),
                        'request_url' => 'ProduceProjectOrder',
                        'request_content' => $add_log_data,
                        'response_content' => array('msg'=>$msg,'error_info'=>''),
                      );
                    Log::info( '/ProduceProjectOrder/do_action', $info_log );//存日志
                    
                    // 供应商一级分类
                    $res_supplier_top_category = D('ProduceProjectOrder')->batchGetSupplierFirstCateGory(array($supplier_id));
                    // 待审备货列表操作审核时，若SKU的供应商一级分类不为【生产部】时，无需向设计款式开发请求此SKU是否有外发工艺。
                    if ($res_supplier_top_category[$supplier_id]=='生产部') {
                        $goods_sn=$resProduceOrderInfo['goods_sn'];
                        // $secondary_process_info  = D('ProduceProject')->getProduceFabricBySku($goods_sn);
                        $secondary_process_info  = $res_secondary_process_info['data'][$goods_sn];
                    }
                   
                    if ($secondary_process_info['secondary_list']) {
                        foreach ($secondary_process_info['secondary_list'] as $style) {
                            //查询plm中供应商为空或者为卓天商务的外发工艺
                            if($style['supplier_name'] && (strpos($style['supplier_name'],'卓天商务') === false)){
                                    $secondary_process_data=array();
                                    $sec_supplier_id=0;
                                    $secondary_process_data['goods_sn'] = $goods_sn;
                                    $secondary_process_data['add_time'] = time();
                                    $secondary_process_data['produce_order_id'] = $produce_order_id;
                                    $secondary_process_data['supplier_id']=$supplier_id;
                                    $res = M("produce_secondary_process")->order('id desc')->getField('id');
                                    $gyId=$res+1;
                                    $gyId_str=(string)$gyId;
                                    if(strlen($gyId_str) == 1){
                                        $gyId_str='000'.$gyId_str;
                                    }elseif(strlen($gyId_str) == 2){
                                        $gyId_str='00'.$gyId_str;
                                    }elseif(strlen($gyId_str) == 3){
                                        $gyId_str='0'.$gyId_str;
                                    }
                                    $secondary_process_data['produce_secondary_process_sn']='GY'.$gyId_str;
                                    $secondary_process_data['design_style'] = $secondary_process_info['info']['design_code'];//设计款号
                                    $secondary_process_data['process_type'] = $style['secondary_process_name'];//工艺类型
                                    $secondary_process_data['unit'] = '';//单位
                                    $secondary_process_data['process_factory'] = $style['supplier_name'];//工艺厂
                                    $sec_supplier_id = M('supplier_info')->where(array('title'=>$style['supplier_name'],'is_delete'=>array('in','0,1')))->getField('id');
                                    if ($sec_supplier_id) {
                                        $secondary_process_data['sec_supplier_id']=$sec_supplier_id;//工艺厂id
                                    }else{
                                        $secondary_process_data['sec_supplier_id']=0;//工艺厂id
                                        $secondary_process_data['process_factory'] = '';
                                    }
                                    $secondary_process_data['suggest_purchase_price'] = round($style['unit_price'],2);//建议采购价
                                    $secondary_process_data['status'] = 1;//创建工艺单默认状态为待审核
                                    $commit[]=$processId=$this->table($this->tablePrefix.'produce_secondary_process')->add($secondary_process_data);
                                    //增加操作记录
                                    $statusMark=1;
                                    if(!empty($processId)){
                                        $add_status_data['produce_process_id'] = $processId;//rs_produce_secondary_process的主键id
                                        $add_status_data['user_name'] = I('session.admin_name');
                                        $add_status_data['add_time'] = $secondary_process_data['add_time'];
                                        $add_status_data['status']=$statusMark;
                                        $commit[]=$this->table($this->tablePrefix.'produce_secondary_process_status')->add($add_status_data);
                                    }
                            }
                        }
                    }
                    if ($secondary_process_info['clothes_secondary_list']) {
                        foreach ($secondary_process_info['clothes_secondary_list'] as $style) {
                            //查询plm中供应商为空或者为卓天商务的外发工艺
                            if($style['supplier_name'] && (strpos($style['supplier_name'],'卓天商务') === false)){
                                    $secondary_process_data=array();
                                    $sec_supplier_id=0;
                                    $secondary_process_data['goods_sn'] = $goods_sn;
                                    $secondary_process_data['add_time'] = time();
                                    $secondary_process_data['produce_order_id'] = $produce_order_id;
                                    $secondary_process_data['supplier_id']=$supplier_id;
                                    $res = M("produce_secondary_process")->order('id desc')->getField('id');
                                    $gyId=$res+1;
                                    $gyId_str=(string)$gyId;
                                    if(strlen($gyId_str) == 1){
                                        $gyId_str='000'.$gyId_str;
                                    }elseif(strlen($gyId_str) == 2){
                                        $gyId_str='00'.$gyId_str;
                                    }elseif(strlen($gyId_str) == 3){
                                        $gyId_str='0'.$gyId_str;
                                    }
                                    $secondary_process_data['produce_secondary_process_sn']='GY'.$gyId_str;
                                    $secondary_process_data['design_style'] = $secondary_process_info['info']['design_code'];//设计款号
                                    $secondary_process_data['process_type'] = $style['secondary_process_name'];//工艺类型
                                    $secondary_process_data['unit'] = '';//单位
                                    $secondary_process_data['process_factory'] = $style['supplier_name'];//工艺厂
                                    $sec_supplier_id = M('supplier_info')->where(array('title'=>$style['supplier_name'],'is_delete'=>array('in','0,1')))->getField('id');
                                    if ($sec_supplier_id) {
                                        $secondary_process_data['sec_supplier_id']=$sec_supplier_id;//工艺厂id
                                    }else{
                                        $secondary_process_data['sec_supplier_id']=0;//工艺厂id
                                        $secondary_process_data['process_factory'] = '';
                                    }
                                    $secondary_process_data['suggest_purchase_price'] = round($style['unit_price'],2);//建议采购价
                                    $secondary_process_data['status'] = 1;//创建工艺单默认状态为待审核
                                    $commit[]=$processId=$this->table($this->tablePrefix.'produce_secondary_process')->add($secondary_process_data);
                                    //增加操作记录
                                    $statusMark=1;
                                    if(!empty($processId)){
                                        $add_status_data['produce_process_id'] = $processId;//rs_produce_secondary_process的主键id
                                        $add_status_data['user_name'] = I('session.admin_name');
                                        $add_status_data['add_time'] = $secondary_process_data['add_time'];
                                        $add_status_data['status']=$statusMark;
                                        $commit[]=$this->table($this->tablePrefix.'produce_secondary_process_status')->add($add_status_data);
                                    }
                            }
                        }
                    }
                }
            }
        }
        if(db_commit($commit)){
            return array('code'=>0,'content'=>$result,'msg'=>'操作成功');
        }else{
            return array('code'=>1,'content'=>$result,'msg'=>$msg);
        }
    }
    
    /**
     * 计算出订单更新数组和操作记录数组
     *
     * @author 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function decideOrderChanges( $act, $order )
    {
        $produce_order_id = $order['produce_order_id'];
        $add_status = array(
            'produce_order_id' => $produce_order_id,
            'user_name' => session( 'admin_name' ),
            'add_time' => time(),
        );
        $old_status = $order['status'];
        if ( $old_status == self::ORDER_STATUS_XDSH )
        // 当审核类型等于【下单审核】时，若操作【通过】，则数据进入订单跟进的【已下单】栏；若操作【拒绝】，则数据进入订单跟进的【已拒绝】栏。
        {
            if ( $act === 'pass' )// 通过
            {
                $save_order = array(
                    'status' => self::ORDER_STATUS_YXD,// 订单：已下单
                    'category' => 0,
                    'is_delete' => 0,
                );
                $add_status['status'] = self::OPERATE_STATUS_TGSH;// 操作记录：通过审核
            }
            elseif ( $act === 'refuse' )// 拒绝
            {
                $save_order = array(
                    'status' => self::ORDER_STATUS_YJJ,// 订单：已拒绝
                    'is_delete' => self::IS_DELETE_YJJ,// 订单：已拒绝
                );
                $add_status['status'] = self::OPERATE_STATUS_YJJ;// 操作记录：已拒绝
            }
        }
        elseif ( $old_status == self::ORDER_STATUS_ZFDD )
        // 当审核类型等于【作废订单】时，若操作【通过】，则数据进入订单跟进的【已拒绝】栏；若操作【拒绝】，则数据返回至原有状态，并生成一条审核拒绝的操作记录。
        {
            if ( $act === 'pass' )// 通过
            {
                $save_order = array(
                    'status' => self::ORDER_STATUS_YJJ,// 订单：已拒绝
                    'is_delete' => self::IS_DELETE_YJJ,// 订单：已拒绝
                );
                $add_status['status'] = self::OPERATE_STATUS_YJJ;// 操作记录：已拒绝
            }
            elseif ( $act === 'refuse' )// 拒绝
            {
                $res = $this->decideBackStatus( $act, $produce_order_id );
                if ( $res['code'] != 200 ) {
                    return array(
                        'code' => 404, 'info' => $res['info']
                    );
                }
                $back_status = $res['info'];
                $save_order = array(
                    'status' => $back_status,// 订单：返回原有状态
                );
                $add_status['status'] = self::OPERATE_STATUS_SHJJ;// 操作记录：审核拒绝
            }
        }
        elseif ( $old_status == self::ORDER_STATUS_WHXJ )
        // 当审核类型等于【无货下架】时，若操作【通过】，则数据进入订单跟进的【无货下架】栏；若操作【拒绝】，则数据返回至原有状态，并生成一条审核拒绝的操作记录。
        {
            if ( $act === 'pass' )// 通过
            {
                $save_order = array(
                    'is_delete' => self::IS_DELETE_WHXJ,// 订单：无货下架
                );
                $add_status['status'] = self::OPERATE_STATUS_WHXJ;// 操作记录：无货下架
            }
            elseif ( $act === 'refuse' )// 拒绝
            {
                $res = $this->decideBackStatus( $act, $produce_order_id );
                if ( $res['code'] != 200 ) {
                    return array(
                        'code' => 404, 'info' => $res['info']
                    );
                }
                $back_status = $res['info'];
                $save_order = array(
                    'status' => $back_status,// 订单：返回原有状态
                );
                $add_status['status'] = self::OPERATE_STATUS_SHJJ;//审核拒绝
            }
        }
        
        return array(
            'code' => 200,
            'info' => array(
                'save_order' => $save_order, 'add_status' => $add_status,
            ),
        );
    }
    
    /**
     * 判断订单应当返回到什么状态
     *
     * @author 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function decideBackStatus( $act, $produce_order_id )
    {
        $all_status = M('produce_order_status')->where(array('produce_order_id' => $produce_order_id))
                ->order('produce_order_status_id asc')->getField('status', true);
        $allow_status = $this->statusAllowRollback();
        $status_arr = array();
        foreach ( $all_status as $v ) {
            if ( in_array( $v, $allow_status ))
            {
                $status_arr[] = $this->fromOperateStatusToOrderStatus( $v );
            }
        }
        $last_status = end($status_arr);
        if ( ! $last_status )
        {
            return array( 'code' => 404, 'info' => $produce_order_id);
        }
        return array( 'code' => 200, 'info' => $last_status );
    }
    
    /**
     * 返回允许回滚的状态（有些状态只在操作记录表存在，是不允许回滚的）
     *
     * @author 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function statusAllowRollback()
    {
        return array(
            4,
            5,
            6,
            7,
            8,
            9,
            12,
            13,
            14,
            18,
            21,
            30,
            34,
            35,
            65,
        );
    }
    
    /**
     * 返回允许回滚的状态（有些状态只在操作记录表存在，是不允许回滚的）
     *
     * @author 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function fromOperateStatusToOrderStatus( $operate_status )
    {
        $table = array(
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            12 => 12,
            13 => 13,
            14 => 14,
            18 => 18,
            21 => 21,
            30 => 30,
            34 => 34,
            35 => 35,
            65 => 35,
        );
        
        return $table [$operate_status] ? $table [$operate_status] : 0;
    }
    
    /**
     * 对生产订单的批量操作（修改状态）
     * @access public
     * @param $ids 传入id的数组
     * @param $type 操作类型
     * @author 李健 2014-12-31 16:16:05
     * @return void
     * @modify 韦俞丞 2018-2-24 12:00:00 47398 订单跟进-备货订单状态变更需判断实价是否为空
     */
    public function batchProduceOrderStatus($ids, $type){
        $ids_count = count($ids);// 传入id个数
        $where = 'produce_order_id IN (' . implode( ',', $ids ) . ')';
        $return = array('msg' => '', 'status' => 1, 'link' => array());
        $return_add = array('msg' => '');
        $produce_order = $this->table($this->tablePrefix.'produce_order')->where($where)->field('produce_order_id, status, category, prepare_type,received_info,stored_num,goods_sn')->select();
        if (count($produce_order) != $ids_count) {
            $return['msg'] = '无此项目';
            return $return;
        }
        $status = 0;// 操作状态
        $success_num = 0;// 统计成功的订单数量
        $time = time();
        if ($type == 'fen_pei') {
            foreach($produce_order as $key => $value){
                if($value['status'] != 13){
                    $return['msg'] = '请确认所有项目是 \'已订料\' 状态';
                    return $return;
                }
            }
            $status = 6;// 修改后的状态
            $produce_order_data = array(// 下单表操作数据
                'status'=>$status,
                'handle_time'=>$time
                );
        } elseif ( in_array( $type, array('online', 'offline', 'fob', 'oem','odm'))) {
            foreach($ids as $key=>$value){
                $res = $this->updateDefaultOrderPrice($value);//默认实价
                if ( $res['status'] == 1 ) {
                    $return['msg'] = "订单{$value}从商品中心获取成本失败";
                    return $return;
                }
            }
            if('online' == $type){
                $category = 1;
                $status = 14;//分类状态位
            }elseif('offline' == $type){
                $category = 2;
                $status = 14;//分类状态位
            }elseif('fob' == $type){
                $category = 3;
                $status = 6;//分类状态位
            }elseif('oem' == $type){
                $category = 4;
                $status = 13;//分类状态位
            }elseif('odm' == $type){
                $category = 6;
                $status = 14;//分类状态位
            }
            $new_ids= array();
            foreach($produce_order as $k => $v){
                if($v['category'] == 0 && $v['status'] == 5){
                    $new_ids[] = $v['produce_order_id'];
                }
            }
            $ids = $new_ids;
            $produce_order_data = array(// 下单表操作数据
                'category'=>$category,
                'status'=>$status,
                'handle_time'=>$time
                );
            //消息推送给供应商
            $this->CategoryMq($ids);
        } elseif ('shouhuo' == $type) {// 批量收货
            $new_ids = array();
            foreach($produce_order as $k=>$v){
                if(($v['category'] == 1 || $v['category'] == 2 || $v['category'] == 6) && $v['status'] == 14 && $v['received_info'] != '0' && $v['received_info'] !=''){
                    $new_ids[] = $v['produce_order_id'];
                    $success_num++;
                }else{
                    $return_add['msg'] = $return_add['msg'].' '.$v['produce_order_id'];
                    $fail = true;
                }
            }
            $status = 12;//收货状态位
            $ids = $new_ids;
            $produce_order_data = array(//下单表操作数据
                'status'=>$status,
                'handle_time'=>$time
                );
        } elseif ($type == 'wancheng') {// 批量完成
            $fba_ids = $new_ids = array();
            foreach ($produce_order as $v) {
                if ($v['status'] == 12 && $v['stored_num'] != '') {
                    $new_ids[] = $v['produce_order_id'];
                    $success_num ++;
                    if ($v['prepare_type'] > 0) {// 备货类型不为0
                        $fba_ids[] = $v['produce_order_id'];
                    }
                } else {
                    $return_add['msg'] = $return_add['msg'] . ' ' . $v['produce_order_id'];
                    $fail = true;
                }
            }
            $status = 9;// 完成状态位
            $ids = $new_ids;
            $produce_order_data = array(// 下单表操作数据
                'status' => $status,
                'handle_time' => $time
            );
        } elseif ('rollback' == $type) {// 批量回滚
            $new_ids = array();
            $fob_id = array();//批量操作中的fob单
            foreach($produce_order as $k=>$v){
                if($v['status'] != 9 && $v['status'] != 12 && $v['status'] != 5 && $v['is_delete'] == 0){
                    $new_ids[] = $v['produce_order_id'];
                    if($v['category']==3){//获取批量操作中的fob单
                        $fob_id[] = $v['produce_order_id'];
                    }
                }
            }
            $status = 5;// 回滚状态位
            $ids = $new_ids;
            $produce_order_data = array(// 下单表操作数据
                'category'=>0,// 未分类
                'status'=>$status,
                'handle_time'=>$time
                );
        }
        if(empty($status)){
            $return['msg'] = '您没有选择任何操作';
            return $return;
        }
        if($ids){// 修改状态
            $where = 'produce_order_id IN(' . join(',', $ids) . ')';
            foreach($ids as $key => $value){    //操作记录
                if('rollback' == $type){
                    $data[$key+$ids_count]['produce_order_id'] = $value;   //id
                    $data[$key+$ids_count]['status'] = 15;  //状态回滚
                    $data[$key+$ids_count]['user_name'] = I('session.admin_name'); //操作用户
                    $data[$key+$ids_count]['add_time'] = $time; //操作时间
                }
                $data[$key]['produce_order_id'] = $value;   //id
                $data[$key]['status'] = $status;  //状态
                $data[$key]['user_name'] = I('session.admin_name'); //操作用户
                $data[$key]['add_time'] = $time; //操作时间
            }
            $commit_data = array();
            $this->startTrans();// 事务开启
            if ( in_array( $type, array('fob', 'oem','odm')) ) {
                foreach ($produce_order as $k_pro => $v_pro) {
                    $produce_mes = M('produce')->where(array('goods_sn'=>$v_pro['goods_sn'],'is_delete'=>0,'status'=>9))->find();
                    $save_data = array();
                    if($type=='fob' && $produce_mes){
                        $cost = '';
                        $cost_arr = D('ProduceProject')->getPredictCost(array("produce_id" =>$produce_mes['id'], 'type' => 2));
                        foreach ($cost_arr['process'] as  $v_process) {
                            $cost += $v_process['total'];
                        }
                        foreach ($cost_arr['fees'] as  $v_fees) {
                            $cost += $v_fees['total'];
                        }
                        foreach ($cost_arr['accessory_fabric'] as  $v_accessory) {
                            $cost += $v_accessory['total'];
                        }
                        foreach ($cost_arr['main_fabric'] as  $v_main) {
                            $cost += $v_main['total'];
                        }
                        if($cost){
                            $save_data['order_price'] = $cost;
                        }
                    }
                    if($save_data){
                        $commit_data[] = M('produce_order')->where(array('produce_order_id'=>$v_pro['produce_order_id'],'status'=>5,'is_delete'=>0))->data($save_data)->limit(1)->save();
                        if($save_data['back_time']){
                            $data[] = array('produce_order_id'=>$v_pro['produce_order_id'],'status'=>23,'user_name'=>I('session.admin_name'),'add_time'=>time());
                        }
                        if($save_data['order_price']){
                            $data[] = array('produce_order_id'=>$v_pro['produce_order_id'],'status'=>19,'user_name'=>I('session.admin_name'),'add_time'=>time());
                            
                        }
                        
                    }

                }
                
            }
            $commit_data[] = $this->table($this->tablePrefix.'produce_order')->where($where)->save($produce_order_data);// 批量分配
            if(!empty($fob_id)){
                $commit_data[] = $this->table($this->tablePrefix.'produce_order')->where('produce_order_id IN(' . join(',', $fob_id) . ')')->save(array('order_price'=>''));
            }
            $commit_data[] = $this->table($this->tablePrefix.'produce_order_status')->addAll($data);
            $res = db_commit($commit_data);// 事务提交
            if ($res && $fba_ids) {// 事务成功，而且批量操作类型是已完成，将FBA生产或美国生产订单对应的调仓管理数据update到调仓已打印
                foreach ($fba_ids as $produce_order_id) {
                    R("InventoryAdjust/updateFbaOrderByProduce",array($produce_order_id));
                }
            }

        }
        if($fail){
            $return['msg'] = $success_num . '件商品' . $return['msg'] . $return_add['msg'] . '失败';
        }else{
            $return['msg'] = '批量操作成功';
        }
        $return['status'] = 0;
        return $return;
    }

    /**
     * 订单跟进操作记入produce_order_status表
     * @param $ids
     * @return array
     * @author 周金剑
     */
    public function orderFollowUp($ids)
    {
        if(!is_array($ids))
        {
         $ids = array($ids);
        }
        $result = array(
            'msg' => '操作成功',
            'status' => '0'
        );
        $redis = redises();
        $redis=$redis->redis();
        $add_time = strtotime(date('Y-m-d',time()));
        $expire_time =strtotime(date('Y-m-d',strtotime('+1 day')));//明日凌晨时间戳
        foreach($ids as $id){
            //插入之前查重
            $res = $redis->get('produce_order_id'.$id.'_follow_time'.$add_time);
            if(!$res){
                $redis->set('produce_order_id'.$id.'_follow_time'.$add_time,1,$expire_time);
                $this->execute( "INSERT INTO ".$this->tablePrefix."produce_order_status (produce_order_id,status,user_name,add_time) VALUES ('".$id."', '60' , '".session('admin_name')."' , '".time()."')" );//记录操作状态
            }else{
                $result = false;
            }
        }
        return $result;
    }
    /**
     * 获取操作查询的信息
     * @param $produce_order_id 生产单id
     * @access public
     * @author 李健 2014-12-29 11:11:52
     * @modify 葛振 2017-04-05 16:49:36 生产订单跟进流程功能完善
     */
    public function getProduceOrderStatusInfo($produce_order_id){
        $produce_status = $this->getAll ( "select * from " . $this->tablePrefix . "produce_order_status where produce_order_id = '" . $produce_order_id . "' order by produce_order_status_id asc" );
        if ($produce_status) {
            foreach ( $produce_status as $k => $v ) {
                if($produce_status [$k] ['status'] == $this::ORDER_STATUS_FOLLOWUP ){
                    unset ($produce_status [$k]);
                    continue;
                }
                $produce_status [$k] ['add_time'] = date ( "Y-m-d H:i:s", $v ['add_time'] );
                $produce_status [$k] ['status'] = $this->getProduceOrderStatusName($v ['status']);
            }
        }
        $produce_status = array_merge(array(),$produce_status);
        return array ('produce_status' => $produce_status);
    }
    /**
     * 搜索下单跟进信息
     * @access public
     * @return array
     * @author 何洋 2014-12-30 16:54:45
     */
    public function getProduceOrderInfo($goods_sn){
        $data_arr['where']= "is_delete=0 and goods_sn='" . $goods_sn ."'";
        $data_arr['field']= "fabric_use,type";
        $data_arr['order']= "add_time desc";
        $data_arr['limit']= "1";
        return $this->getProduceOrder($data_arr);
    }

    /**
     * 判断是否需要印花
     * @param $arr
     * @return int
     * @author 陈东 2016-12-28 13:35:50
     */
    public function getIsPrinting($arr){
        $content = D('ProduceProject')->getCostBatchListBySku($arr['goods_sn']);
        if($content['code']){
            $is_printing=0;
        }else{
            foreach ($content['data'][$arr['goods_sn']]['secondary_list'] as $key => $value) {
                if(!$value['supplier_name'] || (strpos($value['supplier_name'],'卓天商务') !== false)){
                    $is_printing=1;
                    break;
                }
            }
        }
        return $is_printing;
    }
    /**
     * 进程管理信息下单到下单跟进
     * @access public
     * @return int
     * @author 何洋 2014-12-30 16:54:45
     * @modify 唐亮 2017-10-20 13:50:49 当标记为紧急时，更改预计回货时间
     * @modify 姚法强 2017-10-31 11:10:49  增加订单标识
     * @modify 李永旺 2018-02-08 15:26 若备货订单为首单时，则判断供应商一级分类是否为生产部，若为生产部则预计回货时间为下单日期+15天(不含当天);
     */
    public function addProduceOrder($arr){
        if($arr['prepare_type']==1){//如果是fba下单，获取goods表单价
            if(empty($arr['fba_count'])){
                return array(
                    'success' => 0,
                    'message' => 'FBA账户为空'
                );
            }
            $goods_info = R('Goods/getGoodsInfos',array(array($arr['goods_sn']),'cost,goods_id'));
            if($goods_info['success']==1){
                if(isset($goods_info['content'][$arr['goods_sn']]['cost'])){
                    $data['cost'] = $goods_info['content'][$arr['goods_sn']]['cost'];
                }
            }else{
                return array(
                    'success' => 0,
                    'message' => $goods_info['message']
                );
            }
        }
        $w = array(
            'goods_sn' => $arr['goods_sn'],
            'is_delete' => 0,
        );
        $res = M('produce_order')->where($w)->select();
        if ( $res ) {
            $is_first_order = 0;
        } else {
            $is_first_order = 1;
        }
        $add_time = time();//时间
        $data = array();
        $data['goods_sn']= $arr['goods_sn'];
        //查看是否需要印花
        $is_printing = $this->getIsPrinting($arr);
        //下单时获取加工厂id和生产跟单员
        $final_info = $this->getSupplierIdFollower($arr['goods_sn'],$arr['prepare_type']);
        $supplier_info = $this->getSupplierBindBySku($arr['goods_sn']);
        $final_supplier_id = $supplier_info['content']['info'][0]['supplierId'];//供应商id
        $data['goods_sn'] = $supplier_info['content']['info'][0]['sku'];    //sku
        $final_supplier_follower = $final_info['final_supplier_follower'];//生产跟单员
        $final_factory = $supplier_info['content']['info'][0]['supplierName'];//加工厂
        //当为普通备货的时候才判断
        if($arr['prepare_type']==0){
            //获取该sku备货的尺码
            if($arr['goods_attr']['detail']){
                foreach($arr['goods_attr']['detail'] as $each_k=>$each_v){
                    if($each_v['sizevalue']>0){
                        $sku_list[] = $each_v['sizename'];
                    }
                }
            }
            //判断是否紧急
            $is_urgent = $this->isUrgentByAttr($data['goods_sn'],$final_supplier_id,$sku_list);
        }else{
            $is_urgent  = false;
        }
        if ($is_urgent === true) {
            $is_urgent = 1;
        } else {
            $is_urgent = 0;
        }

        if($arr['prepare_type'] != '1'){
        //通过生产时效时间算出来的回货时间为预计入库时间 填写的是交货期
            if(!$is_urgent && $arr['old_storage_time'] && $arr['storage_time']){
                if($arr['storage_time']<$arr['old_storage_time']){
                    $is_urgent = 1;
                }
            }
        }
        if($arr['prepare_type']==0){
            //特殊备货 如果是生产部紧急标记致为0
             //获取供应商的一级分类
            if($is_urgent == 1){
                $supplier_info_arr = array(array('supplier_id'=>$final_supplier_id));
                $supplier_info_arr = D('Prepare')->getSupplierLinkman($supplier_info_arr);//根据id获取供应商
                $cate_name_arr = D('Purchase')->getSupplierCat($supplier_info_arr);
                if(isset($cate_name_arr[0]['first_category_name']) && $cate_name_arr[0]['first_category_name']){
                    //供应商的一级分类
                $first_category_name = $cate_name_arr[0]['first_category_name'];
                if($first_category_name == '生产部'){
                        $is_urgent = 0;
                    }
                }
            }
        }
        //获取生产组
        // $produce_team = R('produce/getProduceTeamBySku',array($arr['goods_sn']));
                 // 生产总时效 来自基础档案款式生产信息的生产总时效
        $goods_sn['goods_sn']=$arr['goods_sn'];
        $goods_sn['is_delete']=0;
        $produce_team = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
        if($produce_team['produce_group']==''){
            $produce_team = '';
        }else{
            $produce_team =$produce_team['produce_group'];
        }
        $supplier_info_currency = M('SupplierInfo')->where(array('id'=>$final_supplier_id))->find();
        $currency = $supplier_info_currency['currency'] ? $supplier_info_currency['currency'] : '';
        //要插入到produce_order表中的数组
        $data['is_first_order'] = $is_first_order;
        $data['is_two_process'] = $arr['is_two_process']?$arr['is_two_process']:0;
        $data['is_urgent'] = $is_urgent;
        $data['prepare_type']=$arr['prepare_type'];
        $data['is_printing']= $is_printing;
        $data['goods_thumb']= $arr['goods_attr']['goods_thumb'];
        $data['storage_time'] = $arr['old_storage_time'];
        //判断是否是首单，若订单为首单，则自动修改预计收货时间，计算规则为：下单日期+15天（不含当天）如果是特殊备货预计收货时间为输入时间
        if ( $is_first_order == 1 ) {
            if ($arr['prepare_type']==4) {
                $data['back_time']=$arr['storage_time'];
            }else{
                $supplier_info_arr2 = array(array('supplier_id'=>$final_supplier_id));
                $supplier_info_arr2 = D('Prepare')->getSupplierLinkman($supplier_info_arr2);//根据id获取供应商
                $cate_name_arr2 = D('Purchase')->getSupplierCat($supplier_info_arr2);
                if(isset($cate_name_arr2[0]['first_category_name']) && $cate_name_arr2[0]['first_category_name']){
                    //供应商的一级分类
                    $first_category_name2 = $cate_name_arr2[0]['first_category_name'];
                    if($first_category_name2 == '生产部'){
                          $data['back_time']=strtotime('+15 days');
                    }else{
                        $data['back_time']=$arr['storage_time']; 
                    }
                }else{
                    $data['back_time']=$arr['storage_time']; 
                }
            }
        }else{
           $data['back_time'] = $arr['storage_time'];
        }
        $data['supplier_linkman'] = $final_factory;
        $data['factory'] = $final_factory;
        $data['supplier_id']= $final_supplier_id;
        $data['produce_merchandiser']= empty($final_supplier_follower)?'':$final_supplier_follower;//生产跟单员
        $data['order_info']= $arr['goods_attr']['size'];
        $data['order_identification']= $arr['order_identification'];//订单标识
        $data['status']= 4;//代表进入待审核
        $data['add_time']= $add_time;
        $data['handle_time']= $add_time;
        $data['fabric_use']= $arr['last_fabric_use'][0]['fabric_use'];
        $data['produce_team']= $produce_team;
        $data['currency']= $currency;
        $data["total_order_num"]   = isset($arr["total"]) ? $arr["total"] : 0;
        foreach($data as $k => $v){
            if($v == null){
                unset($data[$k]);
            }
        }
        $commit_data = array();
        $this->startTrans();//事务开启
        $commit_data[] = $produce_order_id = $this->table($this->tablePrefix . 'produce_order')->data($data)->add();//插入数据
        if (!empty($produce_order_id)) {
            $actual_order_num=0;
            $actual_order_num=$this->getTotalProduceOrder($data['order_info']);
            $where_schedule=array();
            $where_schedule['produce_group']=$produce_team;
            $where_schedule['set_datetime']=strtotime(date('Y-m-d',$add_time));
            $commit_data[] = $this->table($this->tablePrefix . 'produce_schedule_info')->where($where_schedule)->setInc('actual_order_num',$actual_order_num);//生产计划表实际下单数量增加
        }
        $commit_data[] = $this->execute( "INSERT INTO ".$this->tablePrefix."produce_order_status (produce_order_id,status,user_name,add_time) VALUES ('".$produce_order_id."', '4' , '".session('admin_name')."' , '".$add_time."')" );//记录操作状态
        //在remark表中添加记录
        $remark_arr = array();
        $remark_arr['remark_info'] = $arr['order_remark'];
        $remark_arr['time'] = $add_time;
        $remark_arr['produce_order_id'] = $produce_order_id;
        $remark_arr['operator'] = session('admin_name');

        $commit_data[] = $this->table($this->tablePrefix . 'produce_order_remark')->data($remark_arr)->add();
        if($arr['prepare_type']==1){
            $msg = '';
            //向fba_order表插入数据
            $arr['produce_order_id'] = $produce_order_id;
            $add_fba_order = R("InventoryAdjust/addFbaOrder",array($arr));
            if($add_fba_order['success']==0){
                $msg .= $add_fba_order['message'];
                $commit_data[] = false;
            }else{
                $commit_data[] = true;
            }
            //向prepare_status表插入数据
            $prepare_status_add = array();
            $prepare_status_add['goods_id'] = $goods_info['content'][$arr['goods_sn']]['goods_id'];
            $prepare_status_add['status'] = 2;
            $prepare_status_add['user_name'] = session('admin_name');
            $prepare_status_add['content'] = $arr['goods_attr']['size'];
            $prepare_status_add['add_time'] = time();
            $prepare_status_add['goods_sn'] = $arr['goods_sn'];
            $prepare_status_add['type_status'] = 0;
            $res_prepare_status = $this->addPrepareStatus($prepare_status_add);
            if($res_prepare_status['success']==0){
                $msg .= $res_prepare_status['message'];
                $commit_data[] = false;
            }else{
                $commit_data[] = true;
            }
        }
        if(db_commit($commit_data)){ //事务提交
            //当标记为紧急时，更改预计回货时间
            if($is_urgent){
                $this->changeBackTimeByProduceOrderIds($produce_order_id,$is_urgent);
            }
            return array(
                'success' => 1,
                'content' => ''
            );
        }else{
            return array(
                'success' => 0,
                'errcode' => 1,
                'message' => empty($msg)?'下单数据提交失败':$msg
            );
        }
    }

    /**
     * 批量添加生产计划
     * @access public
     * @return int
     * @author 李永旺 2018-04-19 15:30:00
     */
    public function addProduceScheduleOrder($arr){
        if($arr['prepare_type']==1){//如果是fba下单，获取goods表单价
            if(empty($arr['fba_count'])){
                return array(
                    'success' => 0,
                    'message' => 'FBA账户为空'
                );
            }
            $goods_info = R('Goods/getGoodsInfos',array(array($arr['goods_sn']),'cost,goods_id'));
            if($goods_info['success']==1){
                if(isset($goods_info['content'][$arr['goods_sn']]['cost'])){
                    $data['cost'] = $goods_info['content'][$arr['goods_sn']]['cost'];
                }
            }else{
                return array(
                    'success' => 0,
                    'message' => $goods_info['message']
                );
            }
        }
        $w = array(
            'goods_sn' => $arr['goods_sn'],
            'is_delete' => 0,
        );
        $res = M('produce_order')->where($w)->select();
        if ( $res ) {
            $is_first_order = 0;
        } else {
            $is_first_order = 1;
        }
        $add_time = time();//时间
        $data = array();
        $data['goods_sn']= $arr['goods_sn'];
        //查看是否需要印花
        $is_printing = $this->getIsPrinting($arr);
        //下单时获取加工厂id和生产跟单员
        $final_info = $this->getSupplierIdFollower($arr['goods_sn'],$arr['prepare_type']);
        $supplier_info = $this->getSupplierBindBySku($arr['goods_sn']);
        $final_supplier_id = $supplier_info['content']['info'][0]['supplierId'];//供应商id
        $data['goods_sn'] = $supplier_info['content']['info'][0]['sku'];    //sku
        $final_supplier_follower = $final_info['final_supplier_follower'];//生产跟单员
        $final_factory = $supplier_info['content']['info'][0]['supplierName'];//加工厂
        //当为普通备货的时候才判断
        if($arr['prepare_type']==0){
            //获取该sku备货的尺码
            if($arr['goods_attr']['detail']){
                foreach($arr['goods_attr']['detail'] as $each_k=>$each_v){
                    if($each_v['sizevalue']>0){
                        $sku_list[] = $each_v['sizename'];
                    }
                }
            }
            //判断是否紧急
            $is_urgent = $this->isUrgentByAttr($data['goods_sn'],$final_supplier_id,$sku_list);
        }else{
            $is_urgent  = false;
        }
        if ($is_urgent === true) {
            $is_urgent = 1;
        } else {
            $is_urgent = 0;
        }

        if($arr['prepare_type'] != '1'){
        //通过生产时效时间算出来的回货时间为预计入库时间 填写的是交货期
            if(!$is_urgent && $arr['old_storage_time'] && $arr['storage_time']){
                if($arr['storage_time']<$arr['old_storage_time']){
                    $is_urgent = 1;
                }
            }
        }
        if($arr['prepare_type']==0){
            //特殊备货 如果是生产部紧急标记致为0
             //获取供应商的一级分类
            if($is_urgent == 1){
                $supplier_info_arr = array(array('supplier_id'=>$final_supplier_id));
                $supplier_info_arr = D('Prepare')->getSupplierLinkman($supplier_info_arr);//根据id获取供应商
                $cate_name_arr = D('Purchase')->getSupplierCat($supplier_info_arr);
                if(isset($cate_name_arr[0]['first_category_name']) && $cate_name_arr[0]['first_category_name']){
                    //供应商的一级分类
                $first_category_name = $cate_name_arr[0]['first_category_name'];
                if($first_category_name == '生产部'){
                        $is_urgent = 0;
                    }
                }
            }
        }
        //获取生产组
        // $produce_team = R('produce/getProduceTeamBySku',array($arr['goods_sn']));
                 // 生产总时效 来自基础档案款式生产信息的生产总时效
        $goods_sn['goods_sn']=$arr['goods_sn'];
        $goods_sn['is_delete']=0;
        $produce_team = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
        if($produce_team['produce_group']==''){
            $produce_team = '';
        }else{
            $produce_team =$produce_team['produce_group'];
        }
        $supplier_info_currency = M('SupplierInfo')->where(array('id'=>$final_supplier_id))->find();
        $currency = $supplier_info_currency['currency'] ? $supplier_info_currency['currency'] : '';
        //要插入到produce_order表中的数组
        $data['is_first_order'] = $is_first_order;
        $data['is_two_process'] = $arr['is_two_process']?$arr['is_two_process']:0;
        $data['is_urgent'] = $is_urgent;
        $data['prepare_type']=$arr['prepare_type'];
        $data['is_printing']= $is_printing;
        $data['goods_thumb']= $arr['goods_attr']['goods_thumb'];
        $data['storage_time'] = $arr['old_storage_time'];
        //判断是否是首单，若订单为首单，则自动修改预计收货时间，计算规则为：下单日期+15天（不含当天）如果是特殊备货预计收货时间为输入时间
        if ( $is_first_order == 1 ) {
            if ($arr['prepare_type']==4) {
                $data['back_time']=$arr['storage_time'];
            }else{
                $supplier_info_arr2 = array(array('supplier_id'=>$final_supplier_id));
                $supplier_info_arr2 = D('Prepare')->getSupplierLinkman($supplier_info_arr2);//根据id获取供应商
                $cate_name_arr2 = D('Purchase')->getSupplierCat($supplier_info_arr2);
                if(isset($cate_name_arr2[0]['first_category_name']) && $cate_name_arr2[0]['first_category_name']){
                    //供应商的一级分类
                    $first_category_name2 = $cate_name_arr2[0]['first_category_name'];
                    if($first_category_name2 == '生产部'){
                          $data['back_time']=strtotime('+15 days');
                    }else{
                        $data['back_time']=$arr['storage_time']; 
                    }
                }else{
                    $data['back_time']=$arr['storage_time']; 
                }
            }
        }else{
           $data['back_time'] = $arr['storage_time'];
        }
        $data['supplier_linkman'] = $final_factory;
        $data['factory'] = $final_factory;
        $data['supplier_id']= $final_supplier_id;
        $data['produce_merchandiser']= empty($final_supplier_follower)?'':$final_supplier_follower;//生产跟单员
        $data['order_info']= $arr['goods_attr']['size'];
        $data['order_identification']= $arr['order_identification'];//订单标识
        $data['status']= 4;//代表进入待审核
        $data['add_time']= $add_time;
        $data['handle_time']= $add_time;
        $data['fabric_use']= $arr['last_fabric_use'][0]['fabric_use'];
        $data['produce_team']= $produce_team;
        $data['currency']= $currency;
        $data["total_order_num"]   = isset($arr["total"]) ? $arr["total"] : 0;
        foreach($data as $k => $v){
            if($v == null){
                unset($data[$k]);
            }
        }
        $commit_data = array();
        $this->startTrans();//事务开启
        $commit_data[] = $produce_order_id = $this->table($this->tablePrefix . 'produce_order')->data($data)->add();//插入数据
        $commit_data[] = $this->execute( "INSERT INTO ".$this->tablePrefix."produce_order_status (produce_order_id,status,user_name,add_time) VALUES ('".$produce_order_id."', '4' , '".session('admin_name')."' , '".$add_time."')" );//记录操作状态
        //在remark表中添加记录
        $remark_arr = array();
        $remark_arr['remark_info'] = $arr['order_remark'];
        $remark_arr['time'] = $add_time;
        $remark_arr['produce_order_id'] = $produce_order_id;
        $remark_arr['operator'] = session('admin_name');

        $commit_data[] = $this->table($this->tablePrefix . 'produce_order_remark')->data($remark_arr)->add();
        if($arr['prepare_type']==1){
            $msg = '';
            //向fba_order表插入数据
            $arr['produce_order_id'] = $produce_order_id;
            $add_fba_order = R("InventoryAdjust/addFbaOrder",array($arr));
            if($add_fba_order['success']==0){
                $msg .= $add_fba_order['message'];
                $commit_data[] = false;
            }else{
                $commit_data[] = true;
            }
            //向prepare_status表插入数据
            $prepare_status_add = array();
            $prepare_status_add['goods_id'] = $goods_info['content'][$arr['goods_sn']]['goods_id'];
            $prepare_status_add['status'] = 2;
            $prepare_status_add['user_name'] = session('admin_name');
            $prepare_status_add['content'] = $arr['goods_attr']['size'];
            $prepare_status_add['add_time'] = time();
            $prepare_status_add['goods_sn'] = $arr['goods_sn'];
            $prepare_status_add['type_status'] = 0;
            $res_prepare_status = $this->addPrepareStatus($prepare_status_add);
            if($res_prepare_status['success']==0){
                $msg .= $res_prepare_status['message'];
                $commit_data[] = false;
            }else{
                $commit_data[] = true;
            }
        }
        if(db_commit($commit_data)){ //事务提交
            //当标记为紧急时，更改预计回货时间
            if($is_urgent){
                $this->changeBackTimeByProduceOrderIds($produce_order_id,$is_urgent);
            }
            return array(
                'success' => 1,
                'content' => ''
            );
        }else{
            return array(
                'success' => 0,
                'errcode' => 1,
                'message' => empty($msg)?'下单数据提交失败':$msg
            );
        }
    }
    /**
     * 判断是否为紧急单新规则
     * @access public
     * @param  string $goods_sn SKU
     * @param  int $final_supplier_id 供应商id
     * @param  array $size_list 规格尺码
     * @return bool
     * @author 唐亮 2016-09-08 19:50:49
     * @modify 靳明杰 2017-11-28 11:24:49  紧急需求,所有下单不标记紧急
     */
    public function isUrgentByAttr($goods_sn,$final_supplier_id,$sku_list=array()){
        if (!$goods_sn || !$final_supplier_id) {
            return false;
        }
        //获取供应商的一级分类
        $arr = array(array('supplier_id'=>$final_supplier_id));
        $arr = D('Prepare')->getSupplierLinkman($arr);//根据id获取供应商
        $cate_name_arr = D('Purchase')->getSupplierCat($arr);
        if(isset($cate_name_arr[0]['first_category_name']) && $cate_name_arr[0]['first_category_name']){
            //获取运营备货的数据（普通在途，普通待上架，b区库存，待采购，周销量，生产总失效）
            $filter = array(
                'type'=>0,
                'min_sale_num'=>10,
                'max_sale_num'=>'',
                'min_scale'=>'',
                'max_scale'=>'',
                'max_sale_day'=>'',
                'min_sale_day'=>'',
                'do_filter'=>0,
                'search_check'=>1,
                'is_jingjie'=>0,
                'supplier_linkman'=>'',
                'sku'=>$goods_sn,
                'buyer'=>'',
                'maintain_name'=>'',
                'search_by_site'=>'',
                'search_by_optimization_status'=>'',
                'search_by_is_on_sale'=>'',
                'search_by_is_real_stock_sync'=>'',
                'goods_level'=>'',
            );
            $list = D('Prepare')->getPrepareList($filter);
            if(!isset($list['arr'][0])){
                return false;
            }
            $list = $list['arr'][0];
            //供应商的一级分类
            $first_category_name = $cate_name_arr[0]['first_category_name'];
            foreach($sku_list as $each_k=>$each_v){
                $on_way = $unstored_info =$inventory_info_gz =$prepare_purchase =$sale_num_info =$produce_time=0;
                //普通在途
                $on_way = $list['on_way'][$each_v]?$list['on_way'][$each_v]:0;
                //普通待上架
                $unstored_info = $list['unstored_info'][$each_v]?$list['unstored_info'][$each_v]:0;
                //B区库存
                $inventory_info_gz = $list['inventory_info_gz'][$each_v]?$list['inventory_info_gz'][$each_v]:0;
                //待采购
                $prepare_purchase = $list['prepare_purchase'][$each_v]?$list['prepare_purchase'][$each_v]:0;
                //周销量
                $sale_num_info = $list['sale_num_info'][$each_v]?$list['sale_num_info'][$each_v]:0;
                //生产总时效
                $produce_time = $list['produce_time']?$list['produce_time']:0;
                //判断是否紧急
                if($first_category_name == '生产部' || $first_category_name == '外协'){
                    if(($on_way+$unstored_info+$inventory_info_gz-$prepare_purchase)/($sale_num_info/7)<=($produce_time-5)){
                        return true;
                    }
                }elseif($first_category_name == '阿里巴巴'){
                    if(($on_way+$unstored_info+$inventory_info_gz-$prepare_purchase)/($sale_num_info/7)<=5){
                        return true;
                    }
                }else{
                    if(($on_way+$unstored_info+$inventory_info_gz-$prepare_purchase)/($sale_num_info/7)<=4){
                        return true;
                    }
                }
            }
            return false;

        }else{
            return false;
        }
    }

    /**
     * 判断是否为紧急单
     * @access public
     * @param  string $goods_sn SKU
     * @return bool
     * @author 韦俞丞 2016-09-08 19:50:49
     * @modify 唐亮 2017-11-02 21:04:14 根据时间进行筛选
     */
    public function isUrgent($goods_sn) {
        if (!$goods_sn) {
            return false;
        }
        $date_time = date('Ymd',time());
        $sql = " SELECT purchase_total_num FROM " . $this->tablePrefix . "prepare_sku WHERE goods_sn = '" . $goods_sn . "' and date_time = ".$date_time." LIMIT 1 ";
        $res = $this->getOne($sql);
        if ($res && intval($res) > 0) {// 如果有待采购数量，就认为是紧急单
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断是否为首单
     * @access public
     * @param $goods_sn SKU
     * @param $where sql语句的条件
     * @author 姜笛 2015-1-9 11:05:27
     * @modify 杨尚儒 2016-7-18 14:36:52 去掉获取优化编号
     * @modify 姚法强 2017-10-31 14:36:52 获取维护人
     */
    public function isFirstOrder($goods_sn = '',$produce_id = 0, $produce_order_id = 0){
        $data = '';//首单
        $isFirstOrderStatus=0;
        $first_id = $this->table($this->tablePrefix."produce_order")->where('is_delete=0 and goods_sn="'.$goods_sn.'"')->getField('produce_order_id');//首单的自增id
        if($first_id == $produce_order_id){
            $data = '首单';
            $isFirstOrderStatus=1;
        }
        $date_time = date('Ymd',time());
        $prepare_sku=M('Prepare_sku')->where(array('goods_sn' => $goods_sn,'date_time'=>$date_time))->find();
        if($prepare_sku){
            return array('data'=>$data,'maintain_name'=>$prepare_sku['maintain_name'],'isFirstOrderStatus'=>$isFirstOrderStatus);
        }else{
            return array('data'=>$data,'maintain_name'=>'','isFirstOrderStatus'=>$isFirstOrderStatus);

        }
    }
    /**
     * 获取订单实价操作状态日志
     * @access public
     * @param $produce_order_id SKU
     * @author 李永旺 2017-12-21 11:36:07
     */
    public function getOrderPriceStatusInfo($produce_order_id){
        $data = '';
        $orderPriceStatusInfo=M('produce_order_price_status')->where(array('produce_order_id' => $produce_order_id))->select();

        if($orderPriceStatusInfo){
            return array('order_price_status'=>$orderPriceStatusInfo);
        }else{
            return array('order_price_status'=>$data);

        }
    }


    /**
     * 查看报价
     * @access public
     * @param $good_sn 商品sku
     * @param $$produce_order_id 生产单id
     * @author 游佳 2014-12-31 11:11:52
     */
    public function queryFinalPrice($good_sn,$produce_order_id){
        $content = array();
        if($good_sn){
            $sql = "select final_price from " . $this->tablePrefix . "produce where goods_sn='" . $good_sn ."' and is_delete=0 order by add_time desc limit 1";
            $content = $this->getRow ( $sql );
        }
        $content['produce_order_id'] = $produce_order_id;
        return $content;
    }

    /**
     * 编辑生产单信息
     * @access public
     * @param $id 生产单id
     * @param $val 修改的值
     * @param $field 字段名
     * @author 游佳 2014-12-31 11:11:52
     * @modify 陈东 2017-5-22 14:22:35 增加更新supplier_id和供应商字段
     */
    public function updateProduceProjectOrder($id,$val,$field){
        if(empty($id)){
            return array('success'=>0,'message'=>'id为空');
        }
        if($field=='fabric_purchaser' && $val == '无'){
            $val = '';
        }
        if($field=='factory_merchandiser'){
            $field = 'factory';
            $factory_info = D('SupplierInfo')->getSupplier(array('where'=>array('is_delete'=>0,'status'=>1)));
            $produce_order = D('ProduceOrder')->where(array('produce_order_id'=>$id))->find();
            $is_find = false;
            $flag=0;
            foreach($factory_info as $factory){
                if($factory['title']==$val){
                    if($factory['currency'] != $produce_order['currency']){
                        return array('success'=>0,'message'=>'前后供应商币种不一样,修改供应商失败,若要坚持更改请重新下单');
                    }
                    $data['produce_merchandiser'] = $factory['follower'];
                    if(!empty($factory['supplier_group_id'])){
                    $supplier_group_name= M("SupplierGroup")->where('id='.$factory['supplier_group_id'])->find();
                    $data['produce_team']=$supplier_group_name['group_name'];
                    }
                    $data['supplier_id'] = $factory['id'];
                    if($factory['id'] != $produce_order['supplier_id']){
                       $flag=1;
                    }
                    $data['supplier_linkman'] = $val;
                    $is_find = true;
                    break;
                }
            }
            if(!$is_find){
                return array('success'=>0,'message'=>'请填写正确的加工厂');
            }
        }
        $data[$field] = $val;
        $res = $this -> table($this->tablePrefix.'produce_order')->where(array('produce_order_id'=>$id))->save($data);
        if($res){  
            //如果供应商修改成功取消订单 推送到mes系统
            if ($flag==1 ) {
                $ret=$this->setMesByCmtOrder($produce_order['produce_order_id'],$produce_order['category'],'cancel');
                if ($ret['code']==0) {
                    $produce_order_info = $this -> table($this->tablePrefix.'produce_order')->where(array('produce_order_id'=>$id))->find();
                    $this->setMesByCmtOrder($produce_order_info['produce_order_id'],$produce_order_info['category'],'new');
                }
            }
            return array('success'=>1,'content'=>$val);
        }else{
            return array('success'=>0,'message'=>'保存失败');
        }
    }
    /**
     * 生产单打印数据获取SCM
     *
     * @param $produce_order_id 生产单id
     * @return array
     * @author 靳明杰 2018-1-12 11:11:52
     */
    public function printProduceByplm($produce_order_id){
        $num = 0;
        $size = array();
        $arr = array();
        $produce_order = M('produce_order')->where('produce_order_id = ' . $produce_order_id)->find();
        $order_info = explode('<br/>', $produce_order['order_info']);
        array_pop($order_info);
        foreach ($order_info as $key => $value) {
            $size[] = $arr = explode(':', $value);
            $num +=$arr[1];
        }
        $produce_order['total_num'] = $num;
        $res = D('produceProject')->getProcessBySku($produce_order['goods_sn']);
        if(!$res['code'] && $res['data'][$produce_order['goods_sn']]){
            $scm_data = $res['data'][$produce_order['goods_sn']];
            foreach ($scm_data['size_chart_info'] as $key => $value) {
                $flag = false;
                foreach ($value['clothing_size'] as $k => $v) {
                    if($v){
                        $flag = true;
                        break;
                    }
                }
                if(!$flag){
                    unset($scm_data['size_chart_info'][$key]);
                }
            }
            return array(
                'code'=>0,
                'scm_data'=>$scm_data,
                'produce_order'=>$produce_order,
                'size'=>$size
            );
        }else{
            return array(
                'code'=>1,
                'scm_data'=>array(),
                'produce_order'=>$produce_order,
                'size'=>$size
            );
        }
    }
    /**
     * 生产单打印
     *
     * @param $produce_order_id 生产单id
     * @return array
     * @author 游佳 2014-12-31 11:11:52
     * @modify 田靖 2017-06-16 13:50:34 修改图片
     */
    public function printProduce($produce_order_id)
    {
        $produce_order = M('produce_order')->where('produce_order_id = ' . $produce_order_id)->find();

        //页面供应商统一使用supplier_id获取
        $supplierIds = array($produce_order['supplier_id']);
        //根据供应商名称获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        $produce_order['factory'] = $supplierInfo[$produce_order['supplier_id']]['title'];

        $param = array(
            "skuList" => array(
                $produce_order['goods_sn'],
            ),
            "field" => "technics",
            "userCode" => "produce_order"
        );
        $res = R('Produce/getProduceProjectInfoBySku', $param);
        if ( $res['code'] == 200 && $res['info'][ $produce_order['goods_sn'] ]['technics'] ) {
            $path = $res['info'][ $produce_order['goods_sn'] ]['technics'];
            $mime_type = $this->getMimeType($path);
            $attr_type_arr = explode('<br/>', $produce_order['order_info']);
            array_pop($attr_type_arr);
            foreach ($attr_type_arr as $key => $value) {
                $attr_type_info = explode(':', $value);
                $attr_type_name []= $attr_type_info[0];
                $attr_type_num []= $attr_type_info[1];
            }
            // $_SERVER['SERVER_NAME']
            $info = array(
                'path' => $path,
                'mime_type' => $mime_type,
                'produce_order_info'=>array(
                    'produce_order_id'=>$produce_order['produce_order_id'],
                    'goods_sn'=>$produce_order['goods_sn'],
                    'host'=>C('LOCAL_HOST'),
                ),
                 'attr_type'=>array(
                    'attr_type_name'=>$attr_type_name,
                    'attr_type_num'=>$attr_type_num)
            );
            return array('code' => 201, 'info' => $info);
        }
        //判断广州单分类,取相应的分配和时间
        $where ="";
        if($produce_order["category"] == 3){
            $where .= "and status = 6";
        }elseif($produce_order["category"] == 4){
            $where .= "and status = 13";
        }else{
            $where .= "and status = 14";
        }
        $produce_order_status_add_time = $this->getOne(" select add_time from " . $this->tablePrefix . "produce_order_status where produce_order_id = '".$produce_order_id."'".$where." order by add_time desc limit 1");
        $goods_thumb = D('GoodsManage')->getImageUrl($produce_order['goods_thumb'],'',$produce_order['goods_sn']);
        $produce_order['goods_thumb'] = $goods_thumb;
        $produce_order['add_time']= date("Y-m-d H:i:s", $produce_order['add_time']);
        $produce_order['handle_time']= date("Y-m-d H:i:s", $produce_order_status_add_time);
        $firstorder = $this->isFirstOrder($produce_order['goods_sn'],$produce_order['produce_id'],$produce_order_id);// 是否是首单
        $produce_order['first_order'] = $firstorder['data'];
        $res = D('ProduceProject')->getProduceInfo($produce_order['goods_sn']);//查询编号、面料、工艺
        if($res['success']){
            $produce_info = $res['content'];
        }
        //获取抽检数量、质量简述、次品数量
        $qc_info=$this->table($this->tablePrefix.'produce_order_qc_report')->where(array('produce_order_id'=>$produce_order_id))->find();
        //大货属性解码
        $qc_info['large_size'] = json_decode($qc_info['large_size'],true);
        //获取下单的到货数量
        $received_info=$this->getTotalProduceOrder($produce_order['received_info'],true);
        $sampling_info_attr = array();
        $defective_num_attr = array();
        $sampling_num = explode('&lt;br/&gt;', $qc_info['sampling_num']);
        $defective_num = explode('&lt;br/&gt;', $qc_info['defective_num']);
        if($received_info){
            foreach($received_info as $received_key =>$received_val){
                $received_info_attr[$received_key]['attr']=$received_key;
                $received_info_attr[$received_key]['num']=$received_val;
           }
        }
        $sampling_info_attr = array();
        foreach ($sampling_num as $sam_key => $sam_value) {
            $arr_sampling = array();
            $arr_sampling = explode(':', $sam_value);
            if($arr_sampling){
                $sampling_info_attr[$arr_sampling[0]]['num'] = $arr_sampling[1];
            }

        }
        $defective_num_attr = array();
        foreach ($defective_num as $def_key => $def_value) {
            $arr_defective = array();
            $arr_defective = explode(':', $def_value);
            if($arr_defective[1]){
                $defective_num_attr[$arr_defective[0]]['num'] = $arr_defective[1];
            }

        }
        //获取面辅料信息
        $fabic_where = array(
                'produce_id'=>$res['content']['id'],
                'number'=>2
            );
        $fabric_info = D('ProduceProject')->getProduceFabric($fabic_where, true);
        $fabric_info_attr = '';//初始化
        foreach ($fabric_info as $fabric_key => $fabric_value) {
            $fabric_info_attr .= $fabric_value['huo_hao'].'——'.$fabric_value['color'].'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        //生产单下单的尺码与对应的数量
        $order_info_attr =array();
        $order_info = $this->getTotalProduceOrder($produce_order['order_info'],true);
        if($order_info){
            foreach($order_info as $k =>$v){
                $order_info_attr[$k]['attr']=$k;
                $order_info_attr[$k]['num']=$v;
           }
           $order_info_title = array_keys($order_info_attr);
        }
        //尺寸信息
        $order_info_title = array();
        $res = D('ProduceProject')->getProduceAttr($produce_info['id']);
        if($res['success']){
            $produce_attr = $res['content'];
        }
        $temp_title = $attr_num_arr = array();
        foreach($produce_attr as $k =>$v){
            $produce_attr[$k]['attr_value'] = explode(' ',$v['attr_value']);
            //拆分字符串
            $temp_arr = explode('；',$v['attr_value']);
            $cu_key = '';
            if(count($temp_arr)>1){// 是；XS:1
                $cu_key = $temp_arr[0];
                $middle_arr = explode(' ',$temp_arr[1]);
            }else{// XS:333
                $arr = D('ProduceProject')->querySizeAttrByCategoryId($v['attr_cat_id']);
                $cu_key = $arr[$v['attr_id']];// 获取项目尺寸相应分类的尺寸信息
                $middle_arr = explode(' ',$temp_arr[0]);
            }
            $temp_res_arr = array();
            foreach ($middle_arr as $value){
                $in_temp_arr = explode(':',$value);
                $temp_res_arr[$in_temp_arr[0]]=$in_temp_arr[1];
            }
                $produce_attr[$k]['attr_value'] = $temp_res_arr;
            if(!empty($cu_key)){
                $produce_attr[$k]['attr_name'] = $cu_key;
            }
            foreach ($temp_res_arr as $key=>$value){
                $temp_title[] = $key;
            }
        }
        $attr_cat_id = '';
        foreach($produce_attr as $v){
            $attr = $v['attr_name'];
            $num_arr = $v['attr_value'];
            $attr_num_arr[$attr] = $num_arr;
            if($v['attr_cat_id']&&$attr_cat_id===''){
                $success = 1;
                $attr_cat_id = $v['attr_cat_id'];
        }
        }
        if($success){//如果存在衣服属性
            $produce_num = D('ProduceProject')->querySizeAttrByCategoryId($attr_cat_id);
            foreach ($attr_num_arr as $attr_key => $attr_val) {
                foreach ($produce_num as $num_key => $num_val) {//如果相同则赋值keys
                    if($attr_key===$num_val){
                        $attr_num_arr[$attr_key]['keys'] = $num_key;
                    }
                }
            }
        }
        $num_val = 0;
        foreach ($attr_num_arr as $k_attr => $v_attr) {
            if(!$v_attr['keys']&&$v_attr['keys']!==0){
                $attr_num_arr[$k_attr]['keys'] = 'extra'.$num_val;
                $num_val++;
            }
        }
        $order_info_title = array_unique($temp_title);
        //对之前隐藏的属性进行过滤
        $filter = array(
            1 => array(4,5,6,7,8,9,10,11,12,13,14,15,16,21,22,23,25,26,27,28,29,30,31,36), //上衣
            2 => array(), //吊带裙全部过滤
            3 => array(1,2,9,10,11,12,13,14,15,16,17,18,19,20,22,23,24), //裤
            4 => array(), //背心全部过滤
            5 => array(6,15), //连衣裙
        );
        foreach ($produce_attr as $key => $val) {
            if(array_key_exists($val['attr_cat_id'], $filter)){
                if(empty($filter[$val['attr_cat_id']]) || in_array($val['attr_id'], $filter[$val['attr_cat_id']])){
                    unset($produce_attr[$key]);
                }
            }
        }
        $result_arr = array();
        $result_arr['order_info_title'] = $order_info_title;
        $result_arr['produce_attr'] = $produce_attr;
        $result_arr['received_info_attr'] = $received_info_attr;
        $result_arr['sampling_info_attr'] = $sampling_info_attr;
        $result_arr['defective_num_attr'] = $defective_num_attr;
        $result_arr['fabric_info_attr'] = $fabric_info_attr;
        $result_arr['quality_description'] = array();
        if ($qc_info['quality_description']) {
            //以下为了兼容数据，格式化json串为数组，如果不是数组，我们要加入数组中
            if ($quality_description = json_decode($qc_info['quality_description'], true)) {
                if (!is_array($quality_description)) {
                    $result_arr['quality_description'] = array(array(
                        'excerpt' => $quality_description,
                        'level_slight' => '',
                        'level_less_serious' => '',
                        'level_serious' => '',
                    ));
                } else {
                    $result_arr['quality_description'] = $quality_description;
                }
            } else {
                $quality_description = str_replace(':;', ":",  str_replace('<br />', ";",  $qc_info['quality_description']));
                $result_arr['quality_description'] = array(array(
                    'excerpt' => $quality_description,
                    'level_slight' => '',
                    'level_less_serious' => '',
                    'level_serious' => '',
                ));
            }
        }
        $result_arr['order_info_attr'] = $order_info_attr;
        $result_arr['produce_order'] = $produce_order;
        $result_arr['produce_info'] = $produce_info;
        $result_arr['attr_num_arr'] = $attr_num_arr;
        $result_arr['qc_info'] = $qc_info;
        // print_r($result_arr);die;
        return $result_arr;
    }

    /**
     * 根据附件地址，判断mime type
     *
     * @author 韦俞丞 2017-03-31 14:39:24
     */
    public function getMimeType($path)
    {
        $tmp = explode('.', $path);
        $suffix = end($tmp);
        $mime_type = '';
        switch ( $suffix ) {
            case 'jpg':

            case 'jpeg':

            case 'png':

            case 'bmp':
                $mime_type = 'image/jpg';
                break;
            default:
                $mime_type = 'application/pdf';
        }
        return $mime_type;
    }

    /**
     * 标记为紧急
     * @access public
     * @param array $ids_arr 需要标记紧急的id数组
     * @param int $urgent_type  标记的类型  1为紧急 ，2为特急，默认紧急
     * @author 游佳 2014-12-31 11:11:52
     * @modify 李永旺 2017-12-19 19:03:18 订单标记为紧急时，预计收货时间变更规则修改
     */
    public function markUrgent($ids_arr,$urgent_type=1){
        $new_arr = array();
        $sku_arr_first=array();
        $sku_arr_special=array();
        $sku_arr = array();
        foreach($ids_arr as $key => $value){
            $ids_arr[$key] = intval($value);
        }
        //查询符合条件的生产单及紧急标志
        if($ids_arr){
            $sql = 'SELECT produce_order_id,goods_sn,produce_id,prepare_type,is_urgent,status,is_delete,add_time FROM '.$this->tablePrefix.'produce_order WHERE produce_order_id IN('.join(',',$ids_arr).')';

            $result = $this->getAll($sql);
            $time = time();
            $is_urgent = false;
            //去除已完成和已经被标识为紧急的生产单
            foreach($result as $key => $value){
                if($value['status'] != '9' && ($value['is_delete'] == 0) && $value['is_urgent'] != $urgent_type){

                    // 是否是首单
                    $firstorder = $this->isFirstOrder($value['goods_sn'],$value['produce_id'],$value['produce_order_id']);

                    //判断订单是否超48小时
                    if ($firstorder['isFirstOrderStatus']==1) {
                        $is_urgent = true;
                        $sku_arr_first[] = $value['produce_order_id'];
                    }elseif($firstorder['isFirstOrderStatus']!=1 && ($value['prepare_type']==4)){
                        $is_urgent = true;
                        $sku_arr_special[] = $value['produce_order_id'];
                    }elseif(($firstorder['isFirstOrderStatus']!=1) && ($value['prepare_type']!=4) && (($value['add_time'] + 48*3600) < $time)){
                        $is_urgent = true;
                        $sku_arr[] = $value['produce_order_id'];
                    }else{
                        $new_arr[] = $value['produce_order_id'];
                    }
                }
            }
        }

        if($is_urgent){
            if (!empty($sku_arr_first)) {
               return array(
                'error'=>1,
                'message'=>'首单不可标记紧急！,首单订单编号: '.implode(',', $sku_arr_first),
                );
            }elseif (empty($sku_arr_first) && (!empty($sku_arr_special))) {
                return array(
                'error'=>1,
                'message'=>'特殊备货订单不可标记紧急！,特殊备货订单编号: '.implode(',', $sku_arr_special),
                );
            }elseif ( empty($sku_arr_special) && (!empty($sku_arr))) {
                return array(
                'error'=>1,
                'message'=>'下单48小时后不可标记紧急！,超时订单编号: '.implode(',', $sku_arr),
                );
            }

            $now=time();
            $day7ago = strtotime('-7 days');
            $data=array(
                'category'=>array('IN','3,4'),
                'add_time'=>array(
                        array('EGT',$day7ago),
                        array('ELT',$now),   
                    ),
            );
            // FOB单和CMT单总数
            $countTotal = M("produce_order")->where(array('category'=>array('IN','3,4')))->count();
            // FOB,CMT单中前7天内紧急订单数量
            $countUrgent = M("produce_order")->where($data)->count();
           if (!empty($countTotal)&& $countTotal!=0) {

                $countUrgent=(int)$countUrgent;
                $countTotal=(int)$countTotal;
                   if (($countUrgent/$countTotal)>0.01){
                      return array(
                        'error'=>1,
                        'message'=>'FOB,CMT单中前7天内紧急订单已占FOB,CMT单中总订单的10%，标记失败 ',
                        );
                    }
                
            }   
           
        }
        /**
         * 标记操作日志
         */
        if(count($new_arr)>0){
            $add_over_data = array(
                'status'    =>  $urgent_type>1?63:62, //状态 62紧急，63为特急
                'user_name' =>I('session.admin_name'),
                'add_time'  =>  time()
            );
            foreach($new_arr as $each_k=>$each_v){
                $add_over_data['produce_order_id'] = $each_v;
                $this->table($this->tablePrefix.'produce_order_status')->add($add_over_data);
            }
        }
        $ids = join(',',$new_arr);
        $ids = $ids ? $ids : 0;
        $data['is_urgent'] = $urgent_type;
        //备货紧急单与特急单时效规则修改
        $this->changeBackTimeByProduceOrderIds($ids,$urgent_type);
        //更新紧急状态
        $result = $this -> table($this->tablePrefix.'produce_order')->where('produce_order_id IN('.$ids.')')->save($data);
        if($result){
            $sql = '/*master*/SELECT produce_order_id,is_urgent FROM '.$this->tablePrefix.'produce_order WHERE produce_order_id IN('.$ids.') AND is_urgent = '.$urgent_type;
            $result = $this->getAll($sql);
            $content['info'] = $result;
            return $content;
         }else{
            $content['info'] = '';
            return $content;
        }
    }

    /**
     * 备货紧急单与特急单时效规则修改
     * @param string $ids  订单id组
     * @param int $urgent_type  编辑紧急类型
     * @return fixed
     * @author 唐亮 2017-8-23 13:53:52
     */
    public function changeBackTimeByProduceOrderIds($ids='',$urgent_type=1){
        $result = $this -> table($this->tablePrefix.'produce_order')->where('produce_order_id IN('.$ids.')')->select();
        if($result){
            foreach($result as $each_k=>$each_v){
                //备货类型不等于特殊备货
                if($each_v['prepare_type'] !=4){
                    //获取订单里面的供应商
                    $final_supplier_id = $each_v['supplier_id'];
                    if($final_supplier_id){
                        //获取供应商的一级分类
                        $arr = array(array('supplier_id'=>$final_supplier_id));
                        $arr = D('Prepare')->getSupplierLinkman($arr);//根据id获取供应商
                        $cate_name_arr = D('Purchase')->getSupplierCat($arr);
                        if(isset($cate_name_arr[0]['first_category_name']) && $cate_name_arr[0]['first_category_name']){
                            //供应商一级分类
                            $first_category_name = $cate_name_arr[0]['first_category_name'];
                            if($first_category_name == '生产部' || $first_category_name == '外协'){
                                if($each_v['storage_time']>=0 && $each_v['add_time']>0){
                                    //下单时间
                                    $add_time = strtotime(date('Y-m-d',$each_v['add_time']));//时间戳
                                    if($urgent_type ==1){
                                        //标记为紧急
                                        //生产总时效
                                        $storage_time = strtotime(date('Y-m-d',$each_v['storage_time']));//时间戳
                                        $use_day = ceil(($storage_time-$add_time+86400)/86400/2)-1;
                                        //最终需要修改的预计回货时间
                                        $back_time = $add_time+($use_day*86400);
                                        if ($first_category_name == '生产部') {
                                           $back_time = $add_time+(7*86400);
                                        }
                                    }elseif($urgent_type ==2){
                                        //标记为特急
                                        $back_time = $add_time+(4*86400);
                                    }
                                    //更新预计回货时间
                                    $res = M('produce_order')->where(array('produce_order_id'=>$each_v['produce_order_id']))->save(array('back_time'=>$back_time));
                                }
                            }
                        }
                    }
                }

            }
        }
    }

    /**
     * 批量生产单打印
     * @access public
     * @array $filter 批量打印生产单的id号及排序信息
     * @author 游佳 2014-12-31 11:11:52
     * @modify 田靖 2017-06-16 13:51:12 修改图片
     */
    public function printProduceBatch($filter){
        //sql where条件
        $where = '';
        //页面传来如果生产单号不为空
        if($filter['produce_order_ids']){
            $where .= " AND a.produce_order_id in ( " . $filter['produce_order_ids'] . " )";
        }
        $where .= ' and a.is_delete=0 and a.status not in (5,12,9) ';//打印显示
        $arr = array();
        $result = $this->getAll(" select DISTINCT a.produce_order_id,a.supplier_id,a.produce_id,a.goods_thumb,a.order_info,a.order_remark,a.goods_sn,a.category,a.cut_info,a.type,b.status,b.user_name,b.add_time,a.prepare_type from " . $this->tablePrefix ."produce_order a left join " .  $this->tablePrefix . "produce_order_status b on a.produce_order_id=b.produce_order_id where 1=1 ".$where . " order by a.". $filter['sort_by']." ".$filter ['sort_order'].", b.add_time asc ");
        //取出goods_sn
        $produce_order_id = 0;//初始化
        $sn = array();
        $supplierIds = array();//供应商
        foreach($result as $k=>$v){
            $supplierIds[] = $v['supplier_id'];
            if($produce_order_id != $v['produce_order_id']){
                $produce_order_id = $v['produce_order_id'];
                $sn[$k]=$v['goods_sn'];
            }
        }

        //调接口,从goods,goods_rw表查sku_supplier
        $res = R('Goods/getSupplier',array($sn));
        if($res['success'] === 0){ //接口调用失败时，直接将失败信息返回
            return $res;
        }
        $data_goods_n = $res['content'];

        if($result){
            //页面供应商统一使用supplier_id获取
            $supplierIds = unique($supplierIds);
            //根据供应商名称获取供应商信息
            $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
            $supplierInfo = $supplierInfo['content'];

            $produce_order_id = 0;//初始化
            $type_arr = array();//类型数组(美国/广州)
            $category_arr = array();//分类数组

            foreach($result as $k=>$v){

                $v['supplier_linkman'] = $supplierInfo[$v['supplier_id']]['title'];
                $type_arr[] = $v['type'];
                $category_arr[] = $v['category'];
                $goods_thumb = D('GoodsManage')->getImageUrl($v['goods_thumb'],'',$v['goods_sn']);
                $v['goods_thumb'] = $goods_thumb;

                if($produce_order_id != $v['produce_order_id']){//重复加载
                    $produce_order_id = $v['produce_order_id'];
                    if($v['category'] > 2 && $v['category'] != 6){//FOB与OEM
                        $arr['fb'][$produce_order_id]['produce_order_id'] = $v['produce_order_id'];
                        $arr['fb'][$produce_order_id]['factory'] = $v['supplier_linkman'];
                        $arr['fb'][$produce_order_id]['produce_id'] = $v['produce_id'];
                        $arr['fb'][$produce_order_id]['goods_thumb'] = $v['goods_thumb'];
                        $arr['fb'][$produce_order_id]['order_info'] = $v['order_info'];
                        $arr['fb'][$produce_order_id]['total'] = $this->getTotalProduceOrder($v['order_info']);
                        $arr['fb'][$produce_order_id]['cut_info'] = $v['cut_info'];
                        $arr['fb'][$produce_order_id]['prepare_type'] = $v['prepare_type'];
                        //状态
                        $arr['fb'][$produce_order_id]['data'][$k]['status'] = $this->getProduceOrderStatusName($v['status']);
                        $arr['fb'][$produce_order_id]['data'][$k]['user_name'] = $v['user_name'];
                        $arr['fb'][$produce_order_id]['data'][$k]['add_time'] = date('m-d H:i',$v['add_time']);

                    }else{
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['produce_order_id'] = $v['produce_order_id'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['produce_id'] = $v['produce_id'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['goods_sn'] = $v['goods_sn'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['goods_thumb'] = $v['goods_thumb'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['order_info'] = $v['order_info'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['total'] = $this->getTotalProduceOrder($v['order_info']);;
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['supplier_linkman'] = $v['supplier_linkman'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['order_remark'] = $v['order_remark'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['sku_supplier'] = $data_goods_n[$v['goods_sn']];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['prepare_type'] = $v['prepare_type'];
                        //状态
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['data'][$k]['status'] = $this->getProduceOrderStatusName($v['status']);
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['data'][$k]['user_name'] = $v['user_name'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['data'][$k]['add_time'] = date('m-d H:i',$v['add_time']);
                    }
                }else{
                    if($v['category'] > 2 && $v['category'] != 6){//FOB与OEM
                        $arr['fb'][$produce_order_id]['data'][$k]['status'] = $this->getProduceOrderStatusName($v['status']);
                        $arr['fb'][$produce_order_id]['data'][$k]['user_name'] = $v['user_name'];
                        $arr['fb'][$produce_order_id]['data'][$k]['add_time'] = date('m-d H:i',$v['add_time']);
                    }else{
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['data'][$k]['status'] = $this->getProduceOrderStatusName($v['status']);
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['data'][$k]['user_name'] = $v['user_name'];
                        $arr['line'][$v['supplier_linkman'].$produce_order_id]['data'][$k]['add_time'] = date('m-d H:i',$v['add_time']);
                    }
                }
            }
        }else{
            $arr['msg'] = "你选择的订单不能打印";
        }
        //线上线下单按照供应商字母排序,非美国单才排序
        $category_arr = array_unique($category_arr);
        if (in_array(0, $type_arr) && count($category_arr) == 1) {
            ksort($arr['line']);
        }
        //返回信息
        return array(
            'success' => 1,
            'content' => $arr
        );
    }

    /**
     * 采购单打印
     *
     * @access public
     * @author 李玉磊 2015-6-8 20:16:53
     * @modify 田靖 2017-06-20 16:08:57 修改供应商id获取
     */
    public function printPurchaseBatch($checkbox_dayin){
        $sql = "select a.produce_order_id,a.goods_sn,a.supplier_id,a.order_info,b.sku_supplier from ". $this->tablePrefix ."produce_order as a left join ". $this->tablePrefix ."goods as b on a.goods_sn = b.goods_sn where 1=1 and a.produce_order_id in(".$checkbox_dayin.")";

        $arr = $this->getAll($sql);
        //解析各尺寸和数量，计算总数
        foreach($arr as $key =>$value){
            $a = explode("<br/>",$value['order_info']);
            $total = 0;
            foreach($a as $val){
                $b = explode(":",$val);
                if($b[0]){
                    $arr[$key]['order_infos'][$b[0]] = $b[1];
                    $total += $b[1];
                }
            }
            $arr[$key]['total'] = $total;
        }
        $return = array();
        $supplierIds = array_column($arr,'supplier_id');

        //页面供应商统一使用supplier_id获取
        $supplierIds = unique($supplierIds);
        //根据供应商名称获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];

        foreach($arr as $key => $value){
            $value['supplier_linkman'] = $supplierInfo[$value['supplier_id']]['title'];
            $return[$value['supplier_id']]['supplier_linkman'] = $value['supplier_linkman'];
            $return[$value['supplier_id']]['supplier_id'] = $value['supplier_id'];
            $return[$value['supplier_id']]['goods'][$value['produce_order_id']]['goods_sn'] = $value['goods_sn'];
            $return[$value['supplier_id']]['goods'][$value['produce_order_id']]['sku_supplier'] = $value['sku_supplier'];
            $return[$value['supplier_id']]['goods'][$value['produce_order_id']]['order_infos'] = $value['order_infos'];
            $return[$value['supplier_id']]['goods'][$value['produce_order_id']]['total'] = $value['total'];
        }
        //同供应商不同sku的订单序号
        foreach($return as $k => $v) {
            $goods_d = 1;
            foreach($v['goods'] as $k_goods => $v_goods) {
                $return[$k]['goods'][$k_goods]['goods_d'] = $goods_d;
                $goods_d++;
            }
        }
        return $return;
    }


    /**
     * 生产单导出
     * @access public
     * @string $produce_order_id 生产单号集合
     * @author 游佳 2014-12-31 11:11:52
     * @modify 李永旺 2017-12-20 16:43:21 订单导出时,若备货类型为【特殊备货】时,表格内【订单类别】字段的值为特殊备货单
     */
    public function exportProduceOrder($produce_order_id){
        //将页面传来的生产单号字符串分割成数组
        if($produce_order_id){
            $pid = explode(',',trim($produce_order_id,','));
        }
        foreach($pid as $value){
           $value =  intval($value);
           if(empty($value)) exit('选中的数据ID异常');
           $pid[] = $value;
        }
        if($pid){
            $pid = join(',',$pid);
            //物品销量统计表和生产订单表2表查询导出表格需要的数据
            $sql = "SELECT a.produce_order_id,a.produce_merchandiser,a.goods_sn,a.supplier_id,a.order_info,a.order_price,a.fabric_purchaser,a.add_time,a.category,a.produce_id,a.storage_time, a.back_time,a.is_urgent,a.is_two_process,a.status,a.prepare_type,a.produce_team,a.factory_work_time FROM " . $this->tablePrefix . "produce_order AS a WHERE a.produce_order_id IN($pid)";
            $arr = $this->getAll($sql);
            $supplierIds = array_column($arr,'supplier_id');
            //页面供应商统一使用supplier_id获取
            $supplierIds = unique($supplierIds);
            //根据供应商名称获取供应商信息
            $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
            $supplierInfo = $supplierInfo['content'];

            //根据goods_sn获取 物料类型 和 针梭织作法的信息
            $goods_sn_arr = array_column($arr, "goods_sn");
            $produce_result =  R('Produce/getOrderProduceAndMaterialInfo', array($goods_sn_arr));
            $produce_m_info = array();
            if($produce_result["code"] == 0){
                $produce_m_info = $produce_result["info"];
            }
            // 获取货号 goods,goods_rw,goods_mmc  sku_spplier
            $skus = array();
            $skus_b = array();
            $data_goods_n = array();
            foreach ($arr as $po) {
                $skus[] = $po['goods_sn'];
            }

            //获取设计款号
            $res_style=D('ProduceProject')->getStyle($skus);
            $goods_sn_to_style = array();//sku跟设计款号对应关系,key:goods_sn,value:style
            foreach($res_style['content'] as$produce_info){
                $goods_sn_to_style[$produce_info['goods_sn']] = $produce_info['style'];
            }
            $fields = 'sku_supplier';//货号
            $sku_supplier_arr_res = R('Goods/getGoodsInfos', array('goods_sns' => $skus, 'fields' => $fields));
            $sku_supplier_arr = array();
            if($sku_supplier_arr_res["success"]){
                $sku_supplier_arr = $sku_supplier_arr_res["content"];
            }

            //补充操作时间
            $sql = "SELECT produce_order_id,add_time FROM ".$this->tablePrefix."produce_order_status WHERE produce_order_id IN ($pid) and status in(9,26)";
            $status_arr = $this->getAll ($sql);
            if(!empty($status_arr)){
            foreach($status_arr as $val){
                $status_res_arr[$val['produce_order_id']] = $val['add_time'];
            }
            }else{
                $status_res_arr=array();
            }
            $head_titles = array();
            $res_arr = array();
            $all_sku_goods_attrs = array();

            // 获取备货状态
            $sku_shein = array();
            $sku_romwe = array();
            $sku_mmc = array();
            $sale_info = array();
            $produce_order_id_sku_arr = array();
            foreach ($arr as $k => $v) {
                $arr[$k]['supplier_linkman'] = $supplierInfo[$v['supplier_id']]['title'];
                $arr[$k]['site_from']=R('Goods/getSiteOfGoods',array(
                    'goods_sn' => $skus[$k],
                ));
                $arr[$k]["material_card"] = isset($produce_m_info[$v["goods_sn"]]) ? $produce_m_info[$v["goods_sn"]]["material_card"] : array();
                //1 =>93,2=>romwe ,3=>mmc
                if($arr[$k]['site_from']==1){
                    $sku_shein[]['sku'] =$skus[$k];
                }elseif($arr[$k]['site_from']==2){
                    $sku_romwe[]['sku'] =$skus[$k];
                }elseif($arr[$k]['site_from']==3){
                    $sku_mmc[]['sku'] =$skus[$k];
                }
                $produce_order_id_sku_arr[$v['produce_order_id']] = $v['goods_sn'];
            }
            $two_process_info = D('ProduceProject')->getTwoProcess($produce_order_id_sku_arr);
            $uri = C('CURL_URI_GOODS_STOCK_STATUS');
            $sale_info[] = website_api(1, $uri, $sku_shein);
            $sale_info[] = website_api(2, $uri, $sku_romwe);
            $sale_info[] = website_api(3, $uri, $sku_mmc);
            foreach($arr as $key => $val){
                //当前耗时
                $order_add_time = strtotime(date('Y-m-d', $val['add_time']));
                if($status_res_arr[$val['produce_order_id']]!=''){//已完成的单
                    $arr[$key]['spend_time'] = ceil(($status_res_arr[$val['produce_order_id']] - $order_add_time) / 60 / 60 / 24);//已完成时间-下单时间
                }else{
                    $today=strtotime(date('Y-m-d'));
                    $arr[$key]['spend_time'] = ceil(($today - $order_add_time) / 60 / 60 / 24);//当天时间-下单时间
                }
                $arr[$key]['two_process'] = $two_process_info[$val['produce_order_id']];
                //获取优化编号和判断是不是首单
                $isFirstRes = $this->isFirstOrder($val["goods_sn"],$val["produce_id"],$val["produce_order_id"]);
                $order_category_str='备货单';
                if ($val["prepare_type"]==4) {
                   $order_category_str='特殊备货单';
                }
                $arr[$key]["produce_order_category"] = $isFirstRes["data"]=="首单" ? "首单":$order_category_str;//订单类型，除了首单其他的都为备货单
                //销售类别： 用于标识订单是预售还是备货。预售--优化编号首单S,Z类型，备货--优化编号首单B-Z,S类型。
                $beihuo = '';
                if($arr[$key]['site_from']==1){
                    if($sale_info[0]['success']){
                        $beihuo = $sale_info[0]['content'][$val['goods_sn']];
                    }
                }elseif($arr[$key]['site_from']==2){
                    if($sale_info[1]['success']){
                        $beihuo = $sale_info[1]['content'][$val['goods_sn']];
                    }
                }elseif($arr[$key]['site_from']==3){
                    if($sale_info[2]['success']){
                        $beihuo = $sale_info[2]['content'][$val['goods_sn']];
                    }
                }
                if($beihuo!=''){
                    if($beihuo==0||$beihuo==3){
                        $arr[$key]["sale_category"] ='';
                    }elseif($beihuo==1){
                        $arr[$key]["sale_category"] ='备货';
                    }elseif($beihuo==2){
                        $arr[$key]["sale_category"] ='预售';
                    }
                }else{
                    $arr[$key]["sale_category"] =$sale_info['msg'];
                }

                //获取分单类型
                $category = "";
                switch ($val["category"]){
                    case 1:$category = "线上";break;
                    case 2:$category = "线下";break;
                    case 3:$category = "FOB";break;
                    case 4:$category = "OEM";break;
                    case 6:$category = "ODM";break;
                    case 7:$category = "新CMT";break;
                    default:$category = "未知";break;
                }
                $arr[$key]["category"] = $category;//订单分类
                $status='';
                switch ($val["status"]){
                    case 4:$status = "下单审核";break;
                    case 5:$status = "已下单";break;
                    case 6:$status = "分配中";break;
                    case 7:$status = "裁剪中";break;
                    case 8:$status = "车缝中";break;
                    case 9:$status = "已完成";break;
                    case 12:$status = "已收货";break;
                    case 13:$status = "订料中";break;
                    case 14:$status = "已分单";break;
                    case 16:$status = "入仓";break;
                    case 21:$status = "后整中";break;
                    case 30:$status = "已查验";break;
                    case 34:$status = "已退货";break;
                    case 35:$status = "已发货";break;
                    case 36:$status = "账单生产待审核账单";break;
                    case 37:$status = "无货审核";break;
                    case 68:$status = "发料中";break;
                    default:$status = "未知";break;
                }
                $arr[$key]["status"] = $status;//订单状态
                $where_data='';
                $where_data.='produce_order_id='.$val["produce_order_id"].'';
                $where_data.=' and remark_info <> "最近次品原因：  " and remark_info <> "最近次品原因：  无"  ';
                $arr[$key]["remark"]=M('produce_order_remark')->where($where_data)->order('time desc')->find();
                //设计款号
                $arr[$key]['style']=$goods_sn_to_style[$val['goods_sn']];

                $where_produce_order_first_time='';
                $where_produce_order_first_time.='produce_order_id='.$val["produce_order_id"].'';
                $produce_order_first_time=M('produce_order_status')->where($where_produce_order_first_time)->order('add_time asc')->find();//订单编号第一次出现的时间

                $where_is_full_set_days='';
                $where_is_full_set_days.='produce_order_id='.$val["produce_order_id"].'';
                $where_is_full_set_days.=' and status = 61 ';

                $res_is_full_set_days=M('produce_order_status')->where($where_is_full_set_days)->order('add_time desc')->find();

                $arr[$key]["is_full_set_time"]=$res_is_full_set_days;
                if (!empty($res_is_full_set_days['add_time'])){
                   $is_full_set_twodays_between=($res_is_full_set_days['add_time']-$produce_order_first_time['add_time'])/86400;//齐套耗时 最后一次齐套时间-订单第一次出现时间 之间的天数
                    $arr[$key]["is_full_set_days"]=round($is_full_set_twodays_between); //齐套耗时 单位 天 
                    if ($arr[$key]["is_full_set_days"]<0) {
                       $arr[$key]["is_full_set_days"]='';
                    }
                }else{
                    $arr[$key]["is_full_set_days"]='';
                }
                
                



                //补充货号信息
                $arr[$key]["sku_supplier"] = $sku_supplier_arr[$val["goods_sn"]]["sku_supplier"]?$sku_supplier_arr[$val["goods_sn"]]["sku_supplier"]:" ";
                //要求入库日期
                if(empty($val['back_time'])){
                    $arr[$key]['need_ruku_time']="00000-00-00 00:00:00";
                }else{
                    $arr[$key]['need_ruku_time'] =  date('Y/m/d',$val['back_time']);
                }
                //已下单-已完成：  下单到已完成的时间。比如2016/4/1下单，完成时间为2016/4/9，则此处的时间为：9
                $wancheng_time = $status_res_arr[$val['produce_order_id']];
                if(!empty($wancheng_time)){
                    $wancheng_time = strtotime(date('Y-m-d 00:00:00',$wancheng_time));
                    $arr[$key]['xiadan_wancheng'] = !empty($wancheng_time) ? intval(($wancheng_time -strtotime(date('Y-m-d 00:00:00',$val['add_time'])))/86400+1) : "无";
                }else{
                    $arr[$key]['xiadan_wancheng'] = '';
                }
                //下单时间----格式化时间戳
                $arr[$key]['add_time'] =  date('Y/m/d H:i:s',$val['add_time']);
                //计算出总数
                $order_info = explode("<br/>",$val['order_info']);
                //拆分size字段
                $temp_size_arr = array();
                foreach($order_info as $ky => $vl){
                    if($vl!=''){
                        $order_info[$ky] = explode(":",$vl);
                    }
                    $size_key = $order_info[$ky][0]=="数量"?"无属性":$order_info[$ky][0];
                    if(!in_array($size_key, $head_titles)&&!empty($size_key)){
                        $head_titles[] = $size_key;
                    }
                    if(!empty($size_key)){
                        $temp_size_arr[$size_key] = $order_info[$ky][1];
                    }
                    //用于获取条形码的数组参数信息
                    $all_sku_goods_attrs[] =  array("goods_sn"=>$val["goods_sn"],"goods_attr"=>$order_info[$ky][0]=="数量"?"":$order_info[$ky][0]);
                    //总数
                    $arr[$key]['total'] += $order_info[$ky][1];
                }
                $arr[$key]["sizes"] = $temp_size_arr;
            }
            //获取条形码
            $bar_code_arr = R('Inventory/getBarcode',array($all_sku_goods_attrs));
            if($bar_code_arr["success"]){
                $bar_code_arr = $bar_code_arr["content"];//array('sku1_attr1'=>'barcode1',...)
            }else{
                $bar_code_arr = "";
            }
            //整理尺码和对应的条形码信息，如果这个单的某一个尺码没有值，默认为空
            $goods_attr_res = array();
            //数据格式举例：$goods_attr_res array[produce_order_id]=array("S"=>array("num"=>10,"bar_code"=>"S123123-2S"),"M"=>array("num"=>112,"bar_code"=>"S123123-3C"))
            foreach ($arr as $key => $val){
                $single_size = $val["sizes"];
                $temp_arr = array();
                foreach ($head_titles as $value){
                    if(array_key_exists($value, $single_size)){
                        $temp_arr[$value] = $single_size[$value];
                        $bar_code_key = $value=="无属性"?"":$value;
                        $temp_arr[$value."_barcode"] = $bar_code_arr[$val["goods_sn"]."_".$bar_code_key];
                    }
                }
                $arr[$key]["sizes"] = $temp_arr;
            }
            $return = array("data"=>$arr,"titles"=>$head_titles);
            return $return;
        }else{
            return null;
        }

    }

    /**
     * 分类操作
     * @access public
     * @author 戴鑫 2015-1-7 19:30:29
     * @modify 韦俞丞 2018-2-24 12:00:00 47398 订单跟进-备货订单状态变更需判断实价是否为空
     */
    public function updateProduceOrderCategory($produce_order_id = 0, $category = 0){
        $return = array('msg' => '',
                        'status' => '1',
                        'content' => array()
                  );
        $produce_order = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->find();
        if(empty($produce_order)){
            $return['msg'] = '无此项目';
            return $return;
        }
        if($produce_order['type']){
            $return['msg'] = '广州单才能分类';
            return $return;
        }
        //供应商统一使用supplier_id获取
        $supplierIds = array($produce_order['supplier_id']);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        $produce_order['factory'] = $supplierInfo[$produce_order['supplier_id']]['title'];
        $category_pdc = array(// 这些分单，要从商品中心获取成本
            self::ORDER_CATEGORY_ONLINE,
            self::ORDER_CATEGORY_OFFLINE,
            self::ORDER_CATEGORY_ODM,
        );
        $category_design = array(// 这些分单，要从设计款式开发获取成本
            self::ORDER_CATEGORY_FOB,
            self::ORDER_CATEGORY_CMT,
            self::ORDER_CATEGORY_NEW_CMT,
        );
        if ( in_array( $category, $category_pdc ) )// 线上单、线下单、ODM单，分单时分配默认实价，从商品中心取成本
        {
            //先判断该sku是否属于停止采购状态
            //$is_stop = D('Purchase')->isStopPurchase($produce_order['goods_sn']);
            //if($is_stop){
                //$return['msg'] = '该sku处于停止采购状态,无法分单';
                //return $return;
            //}
            $res = $this->updateDefaultOrderPrice($produce_order_id);
            if($res['status'] == 1){
                $return['msg'] = "订单{$produce_order_id} 商品中心获取成本失败";
                return $return;
            } 
        }
        elseif ( in_array( $category, $category_design ) )// FOB、CMT、新CMT，从设计款式开发获取成本cost，留到后面更新
        {
            //当分单为生产单的时候生成目标交期数据
            $this->addOrderPlanTime($produce_order_id);
            $order_price = 0;
            //获取订单数量
            $order_count = $produce_order['total_order_num'];
            //若订单类型等于FOB时，填写的实价不可大于【设计款式开发-上新-大货成本核算-核价中心报价】    加工费总金额+面料总金额
            if($category == 3){
                // 去plm获取大货bom数据的单件用量（kg）并存到数据库
                $single_amount = $this->storageProduceBomSingleAmount($produce_order['goods_sn']);
                if ($single_amount['code']) {
                    M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->save(['single_amount'=>$single_amount['data']]);
                }else{
                    $return['msg'] = "订单{$produce_order_id} PLM获取大货bom数据失败";
                    return $return;
                }
                
                $cost = 0;
                $res = $this->getOrderPrice($produce_order['goods_sn'], $category,$produce_order['prepare_type'],$order_count);
                $cost = $res['cost'];
                //若小于0则不显示
                $cost = ($cost < 0) ? '' : round($cost,2);
                if ( ! $cost   ) {// 
                    //去plm获取大货成本核算
                    $costBatch = D('ProduceProject')->getCostBatchListBySku($produce_order['goods_sn']);
                    if($costBatch['code'] || !$costBatch['data'][$produce_order['goods_sn']]['info']){
                        $return['msg'] = "订单{$produce_order_id} PLM获取成本失败";
                        return $return;
                    }
                    $prepare_type = $produce_order['prepare_type'];
                    $cost = $costBatch['data'][$produce_order['goods_sn']]['info']['material_price'] + $costBatch['data'][$produce_order['goods_sn']]['info']['process_price'];
                    if($cost > 0){
                        //判断备货类型和下单数量
                        if($prepare_type == 4 && $order_count > 2000 ){
                            $order_price = (float)($cost - $costBatch['data'][$produce_order['goods_sn']]['info']['process_price'] * 0.1);
                        }elseif($prepare_type == 4 && $order_count > 500 && $order_count<= 1000){
                            $order_price = (float)($cost - $costBatch['data'][$produce_order['goods_sn']]['info']['process_price'] * 0.11);
                        }elseif($prepare_type == 4 && $order_count > 1000 && $order_count<= 2000){
                            $order_price = (float)($cost - $costBatch['data'][$produce_order['goods_sn']]['info']['process_price'] * 0.13);
                        }elseif($prepare_type != 4 || $order_count <= 500 ){
                            $order_price = (float)($cost);
                        }else{
                            $order_price = (float)($cost);
                        }
                    }else{
                        $order_price = 0;
                    }
                }else{
                    $order_price = $res['order_price'];
                }
                $cost = round($cost,2);
                $order_price = round($order_price,2);
            }elseif($category == 4){
                // 去plm获取大货bom数据的单件用量（kg）并存到数据库
                $single_amount = $this->storageProduceBomSingleAmount($produce_order['goods_sn']);
                if ($single_amount['code']) {
                    M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->save(['single_amount'=>$single_amount['data']]);
                }else{
                    $return['msg'] = "订单{$produce_order_id} PLM获取大货bom数据失败";
                    return $return;
                }

                $cost = 0;
                $res = $this->getOrderPrice($produce_order['goods_sn'], $category,$produce_order['prepare_type'],$order_count);
                $cost = $res['cost'];
                //若小于0则不显示
                $cost = ($cost < 0) ? '' : round($cost,2);
                if ( ! $cost   ) {// 
                    //去plm获取大货成本核算
                    $costBatch = D('ProduceProject')->getCostBatchListBySku($produce_order['goods_sn']);
                    if($costBatch['code'] || !$costBatch['data'][$produce_order['goods_sn']]['info']){
                        $return['msg'] = "订单{$produce_order_id} PLM获取成本失败";
                        return $return;
                    }
                    $prepare_type = $produce_order['prepare_type'];
                    $order_price = 0;
                    //若订单类型等于CMT时，填写的实价不可大于【设计款式开发-上新-大货成本核算-加工费+外发工艺-SIS印花】；
                    $two_total = 0;
                    foreach ($costBatch['data'][$produce_order['goods_sn']]['secondary_list'] as $key => $value) {
                        //查询plm中供应商为空或者为卓天商务的外发工艺
                        if($value['supplier_name'] && (strpos($value['supplier_name'],'卓天商务') === false)){
                            $two_total += $value['unit_price'];
                        }
                    }
                    //成衣二次加工费
                    $cloths_two_total = 0;
                    foreach ($costBatch['data'][$produce_order['goods_sn']]['clothes_secondary_list'] as $key => $value) {
                        //查询plm中供应商为空或者为卓天商务的外发工艺
                        if($value['supplier_name'] && (strpos($value['supplier_name'],'卓天商务') === false)){
                            $cloths_two_total += $value['unit_price'];
                        }
                    }
                    $jiagongfei_fee = $costBatch['data'][$produce_order['goods_sn']]['info']['single_processing']?$costBatch['data'][$produce_order['goods_sn']]['info']['single_processing']:0;
                   $cost = $jiagongfei_fee + $two_total+$cloths_two_total;
                   if($cost > 0){
                        //判断备货类型和下单数量
                        if($prepare_type == 4 && $order_count > 2000 ){
                            $order_price = (float)($cost - $jiagongfei_fee * 0.07);
                        }elseif($prepare_type == 4 && $order_count > 500 && $order_count<= 2000){
                            $order_price = (float)($cost - $jiagongfei_fee * 0.05);
                        }else{
                            $order_price = $cost;
                        }
                    }else{
                        $order_price = 0;
                    }
                }else{
                    $order_price = $res['order_price'];
                }
                $cost = round($cost,2);
                $order_price = round($order_price,2);
            }elseif($category == 7){
                // 去plm获取大货bom数据的单件用量（kg）并存到数据库
                $single_amount = $this->storageProduceBomSingleAmount($produce_order['goods_sn']);
                if ($single_amount['code']) {
                    M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->save(['single_amount'=>$single_amount['data']]);
                }else{
                    $return['msg'] = "订单{$produce_order_id} PLM获取大货bom数据失败";
                    return $return;
                }

                $result = $this->oldNewCmtPrice($produce_order);
                if($result['cost'] != 0){
                    $cost = round($result['cost'],2);
                    $cost_cmt_price = round($result['cost_cmt_price'],2);
                }else{
                    //去plm获取大货成本核算
                    $costBatch = D('ProduceProject')->getCostBatchListBySku($produce_order['goods_sn']);
                    if($costBatch['code'] || !$costBatch['data'][$produce_order['goods_sn']]['info']){
                        $return['msg'] = "订单{$produce_order_id} PLM获取成本失败";
                        return $return;
                    }
                //新CMT：实价获取规则为：(加工费+辅料成本）*1.05+二次工艺费用 - SIS数码印花费用
                    $material_list = $costBatch['data'][$produce_order['goods_sn']]['material_list'];
                    $material_skus = array_column($material_list, 'material_sku');
                    $two_total = 0;
                    $fuliao_cost = 0;
                    $material_skus_need = array();
                    $jiagongfei_fee = $costBatch['data'][$produce_order['goods_sn']]['info']['single_processing']?$costBatch['data'][$produce_order['goods_sn']]['info']['single_processing']:0;
                    //根据物料sku去获取公司是否自购
                    $res = D('ProduceProject')->getMaterialInfoBySku($material_skus);
                    if(!$res['code']){
                        foreach ($res['data'] as $key => $value) {
                           if($value['purchase_type'] == 2){
                                //不是自购 需要计算辅料成本
                                $material_skus_need[] =$key;
                           }
                        }
                        if($material_skus_need){
                            foreach ($material_list as $key => $value) {
                                if(in_array($value['material_sku'], $material_skus_need)){
                                    //大货价元*单价用量*（损耗+1）
                                    $fuliao_cost += $value['unit_price'] * $value['single_amount'] * ($value['supplier_loss']/100 + 1);
                                }
                            }
                        }
                    }
                    foreach ($costBatch['data'][$produce_order['goods_sn']]['secondary_list'] as $key => $value) {
                        //查询plm中供应商为空或者为卓天商务的外发工艺
                        if($value['supplier_name'] && (strpos($value['supplier_name'],'卓天商务') === false)){
                            $two_total += $value['unit_price'];
                        }
                    }
                    //成衣二次加工费
                    $cloths_two_total = 0;
                    foreach ($costBatch['data'][$produce_order['goods_sn']]['clothes_secondary_list'] as $key => $value) {
                        //查询plm中供应商为空或者为卓天商务的外发工艺
                        if($value['supplier_name'] && (strpos($value['supplier_name'],'卓天商务') === false)){
                            $cloths_two_total += $value['unit_price'];
                        }
                    }
                    $cost = ($jiagongfei_fee+$fuliao_cost)*1.05+$two_total+ $cloths_two_total;
                    $cost_cmt_price=0;
                    //新CMT 折扣规则
                    if($cost != 0){
                        //判断备货类型和下单数量
                        // （加工费*X+辅料成本）*1.05+二次工艺费用-SIS印花
                        // X根据订单数量来决定，分为501-2000时X=0.95,大于2000时X=0.92 
                        //$cost = ($jiagongfei_fee+$fuliao_cost)*1.05+$two_total;
                        if($produce_order['prepare_type'] == 4 && $order_count > 2000 ){
                           $cost_cmt_price = (float)(($jiagongfei_fee*0.92+$fuliao_cost)*1.05 + $two_total+ $cloths_two_total);
                        }elseif($produce_order['prepare_type'] == 4 && $order_count > 500 && $order_count<= 2000){
                           $cost_cmt_price = (float)(($jiagongfei_fee*0.95+$fuliao_cost)*1.05+ $two_total+ $cloths_two_total);
                        }elseif($produce_order['prepare_type'] != 4 || $order_count <= 500 ){
                           $cost_cmt_price = ($jiagongfei_fee+$fuliao_cost)*1.05+$two_total+ $cloths_two_total;
                        }
                    }else{
                        $cost_cmt_price = 0;
                        }
                    if (!empty($cost_cmt_price)) {
                        // 四舍五入保留两位小数
                        $cost_cmt_price = round($cost_cmt_price,2);
                    } else {
                        $return['msg'] = "新CMT单{$produce_order_id} 获取成本失败";
                        return $return;
                    }
                    $cost_cmt_price = round($cost_cmt_price,2);
                    $cost = round($cost,2);
                }  
            }
        }
        else
        {
            $return['msg'] = '未知的分单类型';
            return $return;
        }
            //需求39740，分配到CMT订单时，根据该订单的SKU获取该SKU前一订单的【面料采购员】
            //获取订单的SKU获取该SKU前一订单的信息
            $last_order_arr = $this->table($this->tablePrefix.'produce_order')->where(array('goods_sn'=>$produce_order['goods_sn'],'is_delete'=>0))->where("produce_order_id!=$produce_order_id")->order('add_time desc')->field('fabric_purchaser')->find();
            if($category == 3){
                $arr_save = $cost ? array('category'=>$category,'status'=>6,'order_price'=>$order_price,'base_price'=>$cost) : array('category'=>$category,'status'=>6);
                $arr_save['handle_time'] = time();
                 // 生产总时效 来自基础档案款式生产信息的生产总时效
                $goods_sn['goods_sn']=$produce_order['goods_sn'];
                $goods_sn['is_delete']=0;
                $produce_time = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
                  if($produce_time['produce_time']==''){
                      $produce_time['produce_time']=0;
                  }

                if($produce_order['prepare_type']==4){
                    $arr_save['factory_work_time']=$produce_order['back_time'];//特殊备货  工厂交货期=预计收货
                }else{
                    $data_str=date("Y-m-d",$produce_order['add_time']);
                    $data_str=strtotime($data_str); //下单时间对应的零点
                    $arr_save['factory_work_time']=$data_str+(($produce_time['produce_time']-3)*86400);//非特殊备货 工厂交货期=生产总时效-3+下单时间对应的零点
                }
                //存储二次工艺
                if($produce_order['is_two_process'] == 1){
                    $two_process = D('ProduceProject')->getTwoProcess(array($produce_order['produce_order_id']=>$produce_order['goods_sn']));
                    if($two_process){
                        $two_process_info = '';
                        foreach ($two_process[$produce_order['produce_order_id']] as $key => $value) {
                            $two_process_info .= $value['specification'].'(金额:'.round($value['unit_price'],2).')<br>';
                        }
                        $arr_save['two_process_info'] = $two_process_info;
                    }
                }
                $this->table($this->tablePrefix . 'produce_order')
                    ->where(array(
                        'produce_order_id' => $produce_order_id,
                        'type'=>0,
                        'is_delete'=>0)
                    )->save($arr_save);
                $data['status'] = 6;// 状态

                    // FOB单传送借样衣数据,其他订单在updateProduceOrder方法中传送样衣数据
                    $w = array(
                        'produce_order_id'=>$produce_order_id,
                        'is_delete' => 0,
                    );
                    $res = M('produce_order')->where($w)->find();
                    $is_first_order = $res['is_first_order'];
                    $where_s = array(
                        'goods_sn' => $produce_order['goods_sn'],
                        'produce_order_id'=>array('NEQ',$produce_order_id),
                        'supplier_id' => $produce_order['supplier_id'],
                        'is_delete' => 0,
                        'add_time'=>array('GT',time()-86400*180),
                    );
                    $res2 = M('produce_order')->where($where_s)->select();
                    if ( $is_first_order == 1 || (empty($res2)) ) {
                        $commit_data[] = $this->addProduceOrderStatus($produce_order_id, $status);// 增加操作记录
                        // $this->updateProduceOrderInventorySampleDress($produce_order,$commit_data,1);
                        $this->getYangyiResult($produce_order,1,session('admin_name'));
                    }  
                    // Fob分配中推送数据到mes系统,其他订单在updateProduceOrder方法中推送数据到mes系统
                    $this->setMesByCmtOrder($produce_order_id,$category,'new');             
       
            }else if($category == 4){
                $this->table($this->tablePrefix . 'produce_order')
                    ->where(array(
                            'produce_order_id' => $produce_order_id,
                            'type' => 0,
                            'is_delete' => 0)
                    )->save(array(
                            'category' => $category,
                            'status' => 13,
                            'handle_time' => time(),
                            'fabric_purchaser' => $last_order_arr['fabric_purchaser'],
                            'order_price' => $order_price,
                            'base_price'=>$cost)
                    );
                $data['status'] = 13;// 状态
            }else if($category == 7){ //新CMT单分单
                // 生产总时效 来自基础档案款式生产信息的生产总时效

                $goods_sn['goods_sn']=$produce_order['goods_sn'];
                $goods_sn['is_delete']=0;
                $produce_time = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
                  if($produce_time['produce_time']==''){
                      $produce_time['produce_time']=0;
                  }
                $data_str=date("Y-m-d",$produce_order['add_time']);
                $data_str=strtotime($data_str); //下单时间对应的零点
                $factory_work_time=$data_str+(($produce_time['produce_time']-3)*86400);// 工厂交货期时间获取规则 生产总时效-2天+下单时间对应的零点
                // 当订单状态变更为新CMT时，实价获取规则为：加工费*1.05+加工厂采购的物料*1.05+二次工艺（N个二次工艺相加大货价元*用量；不含SIS印花）
                $this->table($this->tablePrefix . 'produce_order')
                    ->where(array(
                            'produce_order_id' => $produce_order_id,
                            'type' => 0,
                            'is_delete' => 0)
                    )->save(array(
                            'category' => $category,
                            'status' => 13,
                            'handle_time' => time(),
                            'fabric_purchaser' => $last_order_arr['fabric_purchaser'],
                            'order_price' => $cost_cmt_price,
                            'factory_work_time'=>$factory_work_time,
                            'base_price'=>$cost)
                    );
                $data['status'] = 13;// 状态
            }else if($category == 6){
                $this->table($this->tablePrefix . 'produce_order')->where(array('produce_order_id' => $produce_order_id, 'type'=>0, 'is_delete'=>0))->save(array('category'=>$category,'status'=>14,'handle_time'=>time()));
                $data['status'] = 14;// 状态
            }else{
                $this->table($this->tablePrefix . 'produce_order')->where(array('produce_order_id' => $produce_order_id, 'type'=>0, 'is_delete'=>0))->save(array('category'=>$category,'status'=>14,'handle_time'=>time()));
                $data['status'] = 14;// 状态
            }
            $data['produce_order_id'] = $produce_order_id;
            $data['user_name'] = I('session.admin_name');
            $data['add_time'] = time();
            $this->table($this->tablePrefix.'produce_order_status')->add($data);
            //添加分单时间
            $where_produce = array(
                "produce_order_id" => $produce_order_id,
            );
            M('produce_order')->where($where_produce)->save(array('divise_order_time' => time()));
            if($category==3 && $cost){
                M('produce_order_status')->data(array('produce_order_id'=>$produce_order_id,'status'=>19,'user_name'=>I('session.admin_name'),'add_time'=>time()))->add();
            }
            //分单时，从库存次品表获取该sku最新的一条次品原因，插入备注中
            $goods_sn = $produce_order['goods_sn'];
            $reasons = R('Inventory/getDefectiveReasonsBy', array($goods_sn));
            $remark = '最近次品原因：'.$reasons['add_name'].' '.$reasons['add_time'].' '.$reasons['reasons'];
            $this->addProduceOrderRemark($remark, $produce_order_id);
            $return['status'] = 0;
            $return['content'] = array('produce_order_id'=>$produce_order_id,'category'=>$category,'status'=>14,'admin_name'=>$data['user_name'],'user_add_time'=>date("m-d H:i",$data['add_time']));
            if($category == 1 || $category == 2 ){//如果是线上单，线下单，访问给我货 塞到MQ中-通知供应商
                $data = array(array('supplier_name'=>$produce_order['factory'],'msg'=>'您有新的采购订单'.$produce_order_id.'生成'));
                $url=C('GEIWOHUO_HOST').C('GWH_API_URL');
                //$ret = actionPost($url,array('supplier'=>json_encode($data)));
            }
            //分单成功后把cmt单的数据推送给面料进销存
            if($category ==4 || $category == 3 || $category == 7){
                $this->setRedisByCmtOrder($produce_order_id,$category);
                //推送 添加日期、订单号、sku、设计款号、款式颜色（暂为空）、色号（暂为空）、订单数量（总数）、加工类型（int）
            }

        return $return;
    }
    /**
     * 旧新cmt实价获取规则
     * @access public
     * @author 靳明杰 2018-2-2 19:30:29 
     */
    public function oldNewCmtPrice($produce_order){
        // 当订单状态变更为新CMT时，实价获取规则为：加工费*1.05+加工厂采购的物料*1.05+二次工艺（N个二次工艺相加大货价元*用量；不含SIS印花）
        $cost_cmt_price='';
        $sku=$produce_order['goods_sn'];
        $type=2;
        $result  = D('ProduceProject')->getProduceCostAndFabricBySku($sku,$type);

        $jiagong_fee_info=$result[$sku]['cost']['fees'];
        $jiagong_fee=0;
        foreach ($jiagong_fee_info as $key => $value) {
            if ($value['cost_name']=='加工费') {
                $jiagong_fee=$value['total'];
            }
        }

        $accessory_fabric_info=$result[$sku]['cost']['accessory_fabric'];
        $accessory_fabric_totals=0;
        foreach ($accessory_fabric_info as $k => $v) {
            $accessory_fabric_totals=$accessory_fabric_totals+$v['total'];
        }

        // 加工厂采购的物料的金额
        // 加工厂采购的物料：将设计款式开发-面料的物料ID，通过接口向PLM获取哪些物料ID是加工厂采购。根据PLM返回的值，计算加工厂采购物料的金额（计算公式为大货价元*单价用量*损耗*1.05）
        $jiagongchang_fabric_price_info=$result[$sku]['fabric_info'];
        $jiagongchang_fabric_price=0;
        $str_material_code='';
        foreach ($jiagongchang_fabric_price_info as $k_a1 => $v_a1) {
                if (!empty($v_a1['material_code'])) {
                   $str_material_code=$str_material_code.$v_a1['material_code'].',';
                }
        }
        // 找出加工厂的物料id
        $jiagong_arr=array();
        if (!empty($str_material_code)) {
            $data=array();
            $url=C('URL_REQUEST_PLM').'Api/material/getMaterialInfoBySku';
                $data=array('sku_list'=>$str_material_code);
            $res_plm_info=curl_get($url,$data);
            if (!empty($res_plm_info['info'])) {
                foreach ($res_plm_info['info'] as $key_a => $value_a) {
                    if ($value_a['purchase_type']==2) {
                       array_push($jiagong_arr,$key_a);
                    }
                }
            }
        }
        foreach ($jiagongchang_fabric_price_info as $k_a => $v_a) {
                if (in_array(trim($v_a['material_code']), $jiagong_arr)) {
                   $jiagongchang_fabric_price=$jiagongchang_fabric_price+$v_a['big_price']*$v_a['single_dosage']*($v_a['loss']*0.01+1);
                }
        }

        /* SIS数码印花费用取值逻辑：
        * 遍历fabric_info列表，找出supplier_id为「3393」的数据（线上 id）（101和112供应商 id为919）,
        * 单个sis数码印花价格= 大货价元*单件用量
        */    
        $sis_fabric_price_info=$result[$sku]['fabric_info'];
        $sis_fabric_price=0;
        foreach ($sis_fabric_price_info as $k2 => $v2) {
            if ($v2['supplier_id']=='3393') {
               $sis_fabric_price=$sis_fabric_price+$v2['big_price']*$v2['single_dosage'];
            }
        }
        //获取二次工艺信息
        $secondary_process_price_info=$result[$sku]['cost']['process'];
        $secondary_process_price=0;
        foreach ($secondary_process_price_info as $k3 => $v3) {
               $secondary_process_price=$secondary_process_price+$v3['total'];
        }
        $cost = $jiagong_fee +$jiagongchang_fabric_price+$secondary_process_price;
        if(($cost) != 0){
            //判断备货类型和下单数量
            // （加工费*X+辅料成本）*1.05+二次工艺费用-SIS印花
            // X根据订单数量来决定，分为501-2000时X=0.95,大于2000时X=0.92
            if($produce_order['prepare_type'] == 4 && $order_count > 2000 ){
               $cost_cmt_price = (float)(($jiagong_fee*0.92+$jiagongchang_fabric_price)*1.05 + $secondary_process_price-$sis_fabric_price);
            }elseif($produce_order['prepare_type'] == 4 && $order_count > 500 && $order_count<= 2000){
               $cost_cmt_price = (float)(($jiagong_fee*0.95+$jiagongchang_fabric_price)*1.05+ $secondary_process_price-$sis_fabric_price);
            }elseif($produce_order['prepare_type'] != 4 || $order_count <= 500 ){
               $cost_cmt_price = ($jiagong_fee+$jiagongchang_fabric_price)*1.05+$secondary_process_price-$sis_fabric_price;
            }
        }else{
            $cost = 0;
            $cost_cmt_price = '';
        }
        return array(
            'cost'=>$cost,
            'cost_cmt_price'=>$cost_cmt_price,
        );
    }
    /**
     * 分类操作顺序
     * @access public
     * @author 朱立洋 2018-2-2 19:30:29 wms发送数据
     */
    public function setRedisByCmtOrder($produce_order_id = 0,$category=4){
        if($produce_order_id){
            //获取订单的详情
            $produce_order = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->find();
            //获取订单下单的总数
            $order_count = $this->getTotalProduceOrder($produce_order['order_info']);
            //获取设计款号
            $res_style=D('ProduceProject')->getStyle(array($produce_order['goods_sn']));
            $goods_sn_to_style = array();//sku跟设计款号对应关系,key:goods_sn,value:style
            foreach($res_style['content'] as$produce_info){
                $goods_sn_to_style[$produce_info['goods_sn']] = $produce_info['style'];
            }
            //设计款号
            $style=$goods_sn_to_style[$produce_order['goods_sn']]?$goods_sn_to_style[$produce_order['goods_sn']]:'';
             // 判断是否为首单
            $isFirstOrderData = $this->isFirstOrder($produce_order['goods_sn'],$produce_order['produce_id'],$produce_order['produce_order_id']);

            //获取大货bom数据
            $formartInfo =  $this->formartSizeNum($produce_order['order_info']);

            $bom_list = $this->getProduceBomSendMrp($produce_order['goods_sn'],$category,$formartInfo);
            // print_r($bom_list);die;
            if ($bom_list['code']) {
                $post_bom_list = $bom_list['data'];  //获取成功则返回结果体
                $post_bom_info = $bom_list['info'];  //获取成功则返回结果体
            }else{
                $post_bom_list = array();   //未获取成功则返回空数组
                $post_bom_info['design_code'] = '';
                $post_bom_info['color_name'] = '';
                $post_bom_info['color_id'] = '';
                $post_bom_info['version_code'] = '';
            }

            $set_arr = array(
                'produce_order_id' => $produce_order_id,//订单号
                'goods_sku'     => $produce_order['goods_sn'],//sku
                'produce_group' => $produce_order["produce_team"],//生产组
                'order_identification' => $produce_order["order_identification"],//订单标识
                'prepare_type' => $produce_order["prepare_type"],//备货类型
                'is_first_order' =>$isFirstOrderData['isFirstOrderStatus'],//是否首单 0 非首单 1首单
                'order_num'=>$order_count,//订单数量
                'category'=>$category,//加工类型
                "order_info" => $produce_order["order_info"],//尺码数量
                "order_time" => $produce_order["add_time"],//下单时间
                //以下为新增返回字段
                "design_code" => $post_bom_info['design_code'],//设计款号
                "color_name" => $post_bom_info['color_name'],//款式颜色
                "color_id" => $post_bom_info['color_id'],//色号
                "version_code" => $post_bom_info['version_code'],//bom版本号
                "list" => $post_bom_list,
            );
            // return $set_arr;
            if ($set_arr){
              $msg='请求成功!';
              $info_log = array(
                'time' => time(),
                'request_url' => 'ProduceProjectOrder',
                'request_content' => $set_arr,
                'response_content' => array('msg'=>$msg,'error_info'=>''),
              );
              Log::info( '/ProduceProjectOrder/setRedisByCmtOrder', $info_log );//存日志
            }
            $strUrl = C("URL_SEND_PRODUCE_INFO")."/index.php/Home/MaterialCompleteApi/addVerificationOrder";
            $strKey = "supply_ProduceProjectOrder_setRedisByCmtOrder";
            $ret = D("SupplierGoodsApply")->curl_post($set_arr,$strUrl);
            $ret = json_decode($ret,true);
            if($ret["code"] != 0){    
                $set_arr["msg"] = $ret["msg"];
                redis_hash($strKey,$produce_order_id,$set_arr);
            }
            $data = redis_hash($strKey);
            if(!empty($data)){
                foreach($data as $val){
                    unset($val["msg"]);
                    $ret = D("SupplierGoodsApply")->curl_post($val,$strUrl);
                    $ret = json_decode($ret,true);
                    //成功删除数据
                    if($ret["code"] == 0){
                        redis_hash($strKey,$val["produce_order_id"],null);
                    }
                }
            }
        }

    }

    /**
     * 向mes系统发送订单数据 支持增量推送
     * @access public
     * @author 李永旺 2018-4-25 09:51:29 mes发送数据
     */
    public function setMesByCmtOrder($produce_order_id = 0,$category=4,$push_type='new',$fields=array()){
        if($produce_order_id){
            //获取订单的详情
            $produce_order = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->find();
            //获取订单下单的总数
            $order_count = $this->getTotalProduceOrder($produce_order['order_info']);
            //获取设计款号 
            $res_style=D('ProduceProject')->getStyleInfo(array($produce_order['goods_sn']));
            $goods_sn_to_style = array();//sku跟设计款号对应关系,key:goods_sn,value:style
            foreach($res_style['content'] as$produce_info){
                $goods_sn_to_style[$produce_info['goods_sn']] = $produce_info['design_style'];
            }
            //设计款号
            $style=$goods_sn_to_style[$produce_order['goods_sn']]?$goods_sn_to_style[$produce_order['goods_sn']]:'';
             // 判断是否为首单
            $isFirstOrderData = $this->isFirstOrder($produce_order['goods_sn'],$produce_order['produce_id'],$produce_order['produce_order_id']);
          

             $res_sku_bar='';
            //是否是FBA订单 Fba备货传送Fba条码
            if ($produce_order['prepare_type']==1) {
                if ($produce_order['order_info']) {
                    $order_info = str_replace('<br />','<br/>',$produce_order['order_info']);
                    $order_infos = explode("<br/>", $order_info);
                    if($order_infos){
                        foreach($order_infos as $ky => $vl){
                            if($vl!=''){
                               $new_arr = array();
                                $new_arr = explode(":",$vl);
                                 $res = D('InventoryManage')->fbaBarcodePrintBySkuattrnum($produce_order['goods_sn'].'_'.$new_arr['0'].'_1',$produce_order_id);//内部有R方法
                                 $res_sku_bar.=$new_arr['0'].':'.$res['content'][0]['barcode'].',';
                       
                            }
                        }
                    }
                }
            }else{ //非Fba备货传送官网条码
                if ($produce_order['order_info']) {
                    $order_info = str_replace('<br />','<br/>',$produce_order['order_info']);
                    $order_infos = explode("<br/>", $order_info);
                    if($order_infos){
                        foreach($order_infos as $ky => $vl){
                            if($vl!=''){
                               $new_arr = array();
                                $new_arr = explode(":",$vl);
                                 $res = D('InventoryManage')->printBarcodeBySkuattrnum($produce_order['goods_sn'].'_'.$new_arr['0'].'_1',$produce_order_id);//内部有R方法
                                 $res_sku_bar.=$new_arr['0'].':'.$res['content'][0]['barcode'].',';
                            }
                        }
                    }
                }
            }
           
            $goods_thumb = D('GoodsManage')->getImageUrl($produce_order['goods_thumb'],'',$produce_order['goods_sn']);
    
            if (!empty($fields)) {
                $set_arr=array();
                if ($push_type=='update') {
                    $set_arr = array(
                        'time'=>date('Y-m-d H:i:s',time()),
                        'push_type'=>$push_type,//new update cancel,
                        'info'=>$fields//增量推送字段
                    );
                }
            }else{
                $set_arr=array();
                $set_arr = array(
                        'time'=>date('Y-m-d H:i:s',time()),
                        'push_type'=>$push_type,//new update cancel,
                        'info'=>array(
                        'produce_order_id' => $produce_order_id,//订单号
                        "produce_id"=>$produce_order['produce_id'],//款式表id,
                        "design_code"=>empty($style) ? '' : $style,//设计款号
                        "prepare_type"=>$produce_order['prepare_type'],//备货类型
                        "order_identification"=>$produce_order['order_identification'],//订单标识
                        'goods_sn'=> $produce_order['goods_sn'],//sku
                        "sku_bar"=>empty($res_sku_bar) ? '' : rtrim($res_sku_bar,',') ,//sku条码
                        "goods_thumb"=>$goods_thumb,//图片地址
                        "cost"=>$produce_order['cost'],//成本
                        "supplier_linkman"=> $produce_order['supplier_linkman'],//供应商名称
                        "factory"=>$produce_order['factory'],//加工厂
                        "supplier_id"=>$produce_order['supplier_id'],//供应商id
                        "telephone"=>$produce_order['telephone'],//电话
                        "status"=>$produce_order['status'],//订单状态
                        "order_info"=>$produce_order['order_info'],//下单信息
                        "contract"=> $produce_order['contract'],//合同
                        "fabric_use"=> $produce_order['fabric_use'],//面料用量
                        "cut_info"=> $produce_order['cut_info'],//裁床数量
                        "order_remark"=>$produce_order['order_remark'],//广州的标题栏是备注 美国的标题栏是到货数量
                        "add_time"=>empty($produce_order['add_time']) ? '': date('Y-m-d H:i:s',$produce_order['add_time']),//添加时间
                        "handle_time"=>empty($produce_order['handle_time']) ? '': date('Y-m-d H:i:s',$produce_order['handle_time']),//操作时间
                        "is_delete"=> $produce_order['is_delete'],// 是否删除(0：未删除，1：已删除，2：无货下架)
                        "type"=> $produce_order['type'],//类型0广州普通备货
                        "is_urgent"=>$produce_order['is_urgent'],//是否紧急
                        "real_price"=> $produce_order['real_price'],//
                        "category"=> $category,//3FOB单4CMT单（以前的OEM单）6ODM单7新CMT
                        "issue_info"=> $produce_order['issue_info'],//广州发料用量
                        "received_info"=>$produce_order['received_info'],//广州的到货数量
                        "return_material"=>$produce_order['return_material'],//广州的退料
                        "order_price"=>$produce_order['order_price'],//订单实价
                        "stored_num"=> $produce_order['stored_num'],//入仓数量
                        "bill_id"=>$produce_order['bill_id'],//订单id
                        "storage_time"=>empty($produce_order['storage_time']) ? '': date('Y-m-d H:i:s',$produce_order['storage_time']),//要求入库时间
                        "fabric_purchaser"=>$produce_order['fabric_purchaser'],//面料采购员
                        "fabric_cost"=> $produce_order['fabric_cost'],//oem单面辅料信息
                        "is_printing"=> $produce_order['is_printing'],//是否有印花 1有0无
                        "back_time"=>empty($produce_order['back_time']) ? '': date('Y-m-d H:i:s',$produce_order['back_time']),//回货时间
                        "factory_work_time"=>empty($produce_order['factory_work_time']) ? '':  date('Y-m-d H:i:s',$produce_order['factory_work_time']),//工厂交货时间
                        "produce_merchandiser"=> $produce_order['produce_merchandiser'],//生产跟单员
                        "box_num"=>$produce_order['box_num'],//箱数
                        "is_two_process"=>$produce_order['is_two_process'],//是否含二次工艺
                        "two_process_info"=> $produce_order['two_process_info'],//二次工艺
                        "is_apply_color"=>$produce_order['is_apply_color'],//是否批色 0 未 1已批色
                        "is_first_order"=> $produce_order['is_first_order'],//是否首单 0不是 1是
                        "is_full_set"=>$produce_order['is_full_set'],//是否齐套
                        "produce_team"=>$produce_order['produce_team'],//生产组
                        "price_edition_num"=> $produce_order['price_edition_num'],//价格版本快照号
                        "currency"=> $produce_order['currency'],//币种
                        "base_price"=> $produce_order['base_price'],//基准价
                        "total_order_num"=> $produce_order['total_order_num'],//总下单量
                        "row_color"=>$produce_order['row_color'],//行颜色值(0:未标颜色,3:红色,２:橙色,1:黄色;默认:0)
                    )
                );
            }
        // print_r($set_arr);die;
            if ($set_arr){
              $msg='请求成功!';
              $info_log = array(
                'time' => time(),
                'request_url' => 'ProduceProjectOrder',
                'request_content' => $set_arr,
                'response_content' => array('msg'=>$msg,'error_info'=>''),
              );
              Log::info( '/ProduceProjectOrder/setMesByCmtOrder', $info_log );//存日志
            }
            // $strUrl = C("URL_SEND_PRODUCE_INFO")."/index.php/Home/MaterialCompleteApi/addVerificationOrder";
            $mes_host="http://58.213.139.100:8999";
            $mes_url=$mes_host."/order/data/pushProduceOrder";
            // $strKey = "supply_ProduceProjectOrder_setMesByCmtOrder";
            // $ret = D("SupplierGoodsApply")->curl_post($set_arr,$strUrl);
            // $ret = json_decode($ret,true);


            $res_data=curlPost($mes_url,$set_arr,true);
            return $res_data;
            // if($ret["code"] != 0){    
            //     $set_arr["msg"] = $ret["msg"];
            //     redis_hash($strKey,$produce_order_id,$set_arr);
            // }
            // $data = redis_hash($strKey);
            // if(!empty($data)){
            //     foreach($data as $val){
            //         unset($val["msg"]);
            //         $ret = D("SupplierGoodsApply")->curl_post($val,$strUrl);
            //         $ret = json_decode($ret,true);
            //         //成功删除数据
            //         if($ret["code"] == 0){
            //             redis_hash($strKey,$val["produce_order_id"],null);
            //         }
            //     }
            // }
        }

    }
      /**
      * 接口，订单跟进为亚马逊BI系统提供订单查询接口
      * @author 李永旺 2018-4-28 10:36:20
      */
    public function getProduceOrderData($param){
        $data=$param['data'];
        if(empty($data)){
            return array('success'=>0,'message'=>"参数为空!",'error_msg'=>"参数为空!");
        }
        if ($data['prepare_type']=='') {
                return array('success'=>0,'message'=>"查询失败2!",'error_msg'=>"备货类型必填!");
        }
        if (!empty($data['sku_list'])) {
            if (count($data['sku_list'])>500){
                return array('success'=>0,'message'=>"sku个数不能大于500!",'error_msg'=>"sku个数不能大于500!");
            }
        }
        if ($data){
          $msg='请求成功!';
          $info_log = array(
            'time' => time(),
            'request_url' => 'ProduceProjectOrder',
            'request_content' => $data,
            'response_content' => array('msg'=>$msg,'error_info'=>''),
          );
          Log::info( '/ProduceProjectOrder/getProduceOrderData', $info_log );//存日志
        }
        if($data){
            //如果传入订单编号 则走当前流程并返回数据
            if (!empty($data['produce_order_id'])) {
               // $produce_order = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = ".$data['produce_order_id'])->find();
                $where_sku='where 1=1';    
                // if ($data['prepare_sku']) {
                //    $where_sku .=' and a.prepare_sku='.$data['prepare_sku'];
                // }
                // if ($data['fba_account']) {
                //    $where_sku .=' and b.fba_account='.$data['fba_account'];
                // }
                $where_sku .=' and a.is_delete=0';
                // $where_sku .=" and a.goods_sn="."'".$sku."'";
                // if ($data['add_time']) {
                //    $where_sku .=' and a.add_time>'.$data['add_time'];
                // }
                $where_sku .=' and a.produce_order_id='.$data['produce_order_id'];
               $sql = "SELECT a.produce_order_id,a.goods_sn,a.prepare_type,a.order_info,a.status,a.cut_info,a.received_info,a.stored_num,b.type as fba_account FROM " . $this->tablePrefix . "produce_order AS a left join  " . $this->tablePrefix . "fba_order as b on a.produce_order_id = b.produce_order_id " . $where_sku;
                $res = $this->getAll($sql);
                // return $sql;
                $arr=array();
                $arr[$res[0]['goods_sn']]=$res[0];
               // return $arr;
                return array('success'=>1,'content'=>'请求成功!','data'=>$arr);
            }else{

                $arr=array();
                if (!empty($data['sku_list'])) {
                    $sku_list=explode(',', $data['sku_list']);
                    if (is_array($sku_list)) {
                        foreach ($sku_list as $key => $sku) {
                            $where_sku='where 1=1';
                            if ($data['prepare_type']) {
                               $where_sku .=' and a.prepare_type='.$data['prepare_type'];
                            }
                            if ($data['fba_account']) {
                               $where_sku .=' and b.fba_account='.$data['fba_account'];
                            }
                            $where_sku .=' and a.is_delete=0';
                            $where_sku .=" and a.goods_sn="."'".$sku."'";
                            if ($data['add_time']) {
                               $where_sku .=' and a.add_time>'.$data['add_time'];
                            }
                            
                            // $where_sku .=' and a.status in(5,14,6,68,13,7,8,21) ';
                            // $fields=array('produce_order_id');
                            // $produce_orders = $this->table($this->tablePrefix.'produce_order')->where($where_sku)->field($fields)->select();
                        $sql = "SELECT a.produce_order_id,a.goods_sn,a.prepare_type,a.order_info,a.status,a.cut_info,a.received_info,a.stored_num,b.type as fba_account FROM " . $this->tablePrefix . "produce_order AS a left join  " . $this->tablePrefix . "fba_order as b on a.produce_order_id = b.produce_order_id " . $where_sku . " ORDER BY a.produce_order_id";
                            $res = $this->getAll($sql);
                            
                            $arr[$sku]=$res;
                        }
                    }
                    // return $sql;
                    // return $arr;
                    // return array('success'=>1,'content'=>'请求成功!','data'=>$sql);
                    return array('success'=>1,'content'=>'请求成功!','data'=>$arr);
                }elseif(empty($data['sku_list'])){
                    $where_sku='where 1=1';
                    if ($data['prepare_type']) {
                               $where_sku .=' and a.prepare_type='.$data['prepare_type'];
                    }
                    if ($data['fba_account']) {
                       $where_sku .=' and b.fba_account='.$data['fba_account'];
                    }
                    $where_sku .=' and a.is_delete=0';
                    // $where_sku .=" and a.goods_sn="."'".$sku."'";
                    if ($data['add_time']) {
                       $where_sku .=' and a.add_time>'.$data['add_time'];
                    }
                    $sql = "SELECT a.produce_order_id,a.goods_sn,a.prepare_type,a.order_info,a.status,a.cut_info,a.received_info,a.stored_num,ifnull(b.type,'') as fba_account FROM " . $this->tablePrefix . "produce_order AS a left join  " . $this->tablePrefix . "fba_order as b on a.produce_order_id = b.produce_order_id " . $where_sku . " ORDER BY a.produce_order_id";
                    $res = $this->getAll($sql);
                    $arr=array();
                    foreach ($res as $key => $value){
                       $arr_data=array();
                       if (in_array($value['goods_sn'], $arr_data)) {
                           array_push($arr[$value['goods_sn']], $value);
                       }else{
                        array_push($arr_data,$value['goods_sn']);
                            $arr[$value['goods_sn']][]=$value;
                       }
                    }
                   return array('success'=>1,'content'=>'请求成功!','data'=>$arr);
                }
            }
        }else{
            return array('success'=>0,'content'=>'查询失败!');
        }

    }
          /**
      * 接口，订单跟进为亚马逊BI系统提供订单查询接口
      * @author 李永旺 2018-4-28 10:36:20
      */
    public function getProduceOrderDataReferSku($param){
        $data=$param['data'];
        if(empty($data)){
            return array('success'=>0,'message'=>"参数为空!",'error_msg'=>"参数为空!");
        }
        if ($data['produce_order_ids']=='') {
                return array('success'=>0,'message'=>"查询失败!",'error_msg'=>"订单编号必填!");
        }
        if ($data){
          $msg='请求成功!';
          $info_log = array(
            'time' => time(),
            'request_url' => 'ProduceProjectOrder',
            'request_content' => $data,
            'response_content' => array('msg'=>$msg,'error_info'=>''),
          );
          Log::info( '/ProduceProjectOrder/getProduceOrderDataReferSku', $info_log );//存日志
        }
        if($data){
            //如果传入订单编号 则走当前流程并返回数据
                $arr=array();
                if (!empty($data['produce_order_ids'])) {
                    $produce_order_ids=explode(',', $data['produce_order_ids']);
                    if (is_array($produce_order_ids)) {
                        foreach ($produce_order_ids as $key => $produce_order_id) {
                            $where='where 1=1';
                            $where .=' and a.is_delete=0';
                            $where .=" and a.produce_order_id="."'".$produce_order_id."'";
                            if ($data['add_time']) {
                               $where .=' and a.add_time>'.$data['add_time'];
                            }
                        $sql = "SELECT a.produce_order_id,a.goods_sn,a.refer_goods_sn FROM " . $this->tablePrefix . "produce_order as a " . $where . " ORDER BY a.produce_order_id";
                            $res = $this->getAll($sql);
                            
                            $arr[$produce_order_id]=$res;
                        }
                    }
                    return array('success'=>1,'content'=>'请求成功!','data'=>$arr);
                }
            
        }else{
            return array('success'=>0,'content'=>'查询失败!');
        }

    }
    /**
     * 分类操作顺序
     * @access public
     * @author 戴鑫 2015-1-7 19:30:29 对广州订单进行分类
     * @modify 陈东 2017-3-7 16:31:41 odm流程修改，增加已收货
     * @modify 靳明杰  2017-09-13 14:36:57 cmt fob增加送货状态35
     * @modify 李永旺  2017-12-27 10:43:00 新cmt
     */
    public function produceOrderStatusLine($key = 0){
        $arr['1'] = array(5,14,12,9);
        $arr['2'] = array(5,14,12,9);
        $arr['3'] = array(5,6,13,7,8,21,35,12,9);
        $arr['4'] = array(5,13,6,7,8,21,35,12,9);
        $arr['6'] = array(5,14,12,9);
        $arr['7'] = array(5,13,68,7,8,21,35,12,9);
        return $key ? $arr[$key] : $arr;
    }

    /**
     * 分类操作顺序
     * @access public
     * @author 戴鑫 2015-1-7 19:30:29 对广州订单进行分类
     * @modify 韦俞丞 2018-2-27 12:00:00 47136 待审核备货列表功能优化
     */
    public function getProduceOrderStatusName($key = 0){
        switch ($key){
            case 4:
                    $produce_status  = '已下单';
                    break;
            case 5:
                    $produce_status  = '通过审核';
                    break;
            case 6:
                    $produce_status  = '分配中';
                    break;
            case 7:
                    $produce_status  = '裁剪中';
                    break;
            case 8:
                    $produce_status  = '车缝中';
                    break;
            case 9:
                    $produce_status  = '已完成';
                    break;
            case 11:
                    $produce_status  = '重新审核';
                    break;
            case 12:
                    $produce_status  = '已收货';
                    break;
            case 13:
                    $produce_status  = '订料中';
                    break;
            case 14:
                    $produce_status  = '已分单';
                    break;
            case 15:
                    $produce_status  = '回滚';
                    break;
            case 16:
                    $produce_status  = '入仓&nbsp;&nbsp;';
                    break;
            case 17:
                    $produce_status  = '已拒绝';
                    break;
            case 18:
                    $produce_status  = '无货下架';
                    break;
            case 19:
                    $produce_status  = '修改实价';
                    break;
            case 20:
                    $produce_status  = '修改送货数量';
                    break;
            case 21:
                    $produce_status  = '后整中';
                    break;
            case 22:
                    $produce_status  = '已恢复';
                    break;
            case 23:
                $produce_status  = '预计回货';
                break;
            case 24:
                $produce_status  = '取消紧急标记';
                break;
            case 25:
                $produce_status  = '更新要求入库时间';
                break;
            case 26:
                $produce_status = 'PDA-已完成';
                break;
            case 27:
                $produce_status = '已批色';
                break;
            case 30:
                $produce_status = '已查验';
                break;
            case 34:
                $produce_status = '已退货 ';
                break;
            case 35:
                $produce_status = '已送货 ';
                break;
            case 60:
                $produce_status = '已跟进 ';
                break;
            case 61:
                $produce_status = '已齐套';
                break;
            case 62:
                $produce_status = '标记为紧急';
                break;
            case 63:
                $produce_status = '标记为特急';
                break;
            case 65:
                $produce_status = '已送货';
                break;
            case 66:
                $produce_status = '账单到待审核';
                break;
            case 67:
                $produce_status = '生成账单';
                break;
            case 68:
                $produce_status = '发料中';
                break;
            case self::OPERATE_STATUS_SHJJ:
                $produce_status = '审核拒绝';
                break;
        }
        return $produce_status;
    }

    /**
     * 返页面显示
     * @param
     * @return   array
     * @author  姜笛 2015-1-6 18:38:57
     * @modify 李永旺 2017-12-26 11:25:35 增加新CMT单状态
     */
    function produceOrderStatusList(){
        return array(
            '1'  => array(//线上单
                'pre' => array(//开始状态
                ),
                'next' => array(//转变状态
                    '5'  => '已完成'
                )
            ),
            '2' => array(//线下单
                'pre' => array(//开始状态
                ),
                'next' => array(//转变状态
                    '5' => '已完成',
                )
            ),
            '3'     => array(//FOB
                'pre' => array(//开始状态
                    '6' => '分配中',
                    '13' => '订料中',
                    '7'  => '裁剪中',
                    '8' => '车缝中',
                    '21' => '后整中'

                ),
                'next' => array(//转变状态
                    '14'  => '已分配',
                    '6' => '已订料',
                    '13'  => '已裁床',
                    '7' => '上车位'
                )
            ),
            '4'     => array(// CMT
                'pre' => array(//开始状态
                    '13'  => '订料中',
                    '6'  => '分配中',
                    '7'  => '裁剪中',
                    '8' => '车缝中',
                    '21' => '后整中'
                ),
                'next' => array(//转变状态
                    '14'  => '已订料',
                    '7'  => '已分配',
                    '13'  => '已裁床',
                    '6' => '上车位'
                )
            ),
            '6' => array(//ODM单
                'pre' => array(//开始状态
                ),
                'next' => array(//转变状态
                    '5' => '已完成',
                )
            ),
            '7'     => array(// 新CMT
                'pre' => array(//开始状态
                    '13'  => '订料中',
                    '68'  => '发料中',
                    '7'  => '裁剪中',
                    '8' => '车缝中',
                    '21' => '后整中'
                ),
                'next' => array(//转变状态
                    '14'  => '已订料',
                    '7'  => '已发料',
                    '13'  => '已裁床',
                    '6' => '上车位'
                )
            ),
        );
    }

     /**
     * 查看重量
     * @access public
     * @param $produce_order_id 生产单id
     * @param $where sql语句的条件
     * @author 姜笛 2015-1-8 17:24:08
     */
    public function getProduceWeight($goods_sn = '',$id = 0, $filed = 'weight'){
        $where = " ";
        if(!empty($produce_id)){
            $where .= "  id='{$id}' ";
        }else{
            $where .= "  goods_sn='{$goods_sn}' ";
        }
        return $this->table($this->tablePrefix . "inventory_weight")->where($where)->getField($filed) ;

    }
    /**
     * 计算某订单各种尺寸的数量总数或者各种尺码的数量信息
     * @return
     * @author 戴鑫 2015-1-8 17:24:08
     */
    public function getTotalProduceOrder($order_info = '', $type = false){
        $total = 0;
        $order_info = str_replace('<br />','<br/>',$order_info);
        $order_infos = explode("<br/>", $order_info);
        if($order_infos){
            foreach($order_infos as $ky => $vl){
                if($vl!=''){
                    $new_arr = array();
                    $new_arr = explode(":",$vl);
                    if(is_numeric($new_arr['1'])){
                        $total += $new_arr['1'];//总数
                    }
                    if($type==true){
                        $result[$new_arr['0']] = $new_arr['1'];//返回尺码信息
                    }
                }
            }
        }
        return $type ? $result : $total;
    }
    /**
     * 计算广州单的货期和返回最后14条状态信息
     * @param  $produce_order_ids 订单ID
     * @return array
     * @author 戴鑫 2015-1-8 17:24:08
     * @modify 杨尚儒 2015-2-4 14:07:35 增加统计oem的车缝时间
     * @modify 周金剑 2017-3-20 10:07:35 已跟进不返回记录
     */
    public function getProduceOrderDeliveryTime($produce_order_ids){
        $result = array();
        $where = " 1=1 ";
        if($produce_order_ids){
            $where .= " and produce_order_id in(".$produce_order_ids.") ";
        }
        $where .= "and status != 60";

        $data = $this->table($this->tablePrefix . "produce_order_status")->where($where)->order('produce_order_id desc,add_time desc')->field('produce_order_id,status,add_time,user_name')->select();
        if($data){
            $produce_order_id =  0;
            $status_xiadan_time = 0;//下单时间
            $status_wancheng_time = 0;//完成时间
            $status_fenpei_time = 0;//分配时间
            $status_shouhuo_time = 0;//收货时间
            $status_sewing_time = 0;//车缝时间（暂时oem使用）
            foreach($data as $k => $v){
                //初始化赋值
                if($produce_order_id != $v['produce_order_id']){
                    $produce_order_id = $v['produce_order_id'];
                    $status_xiadan_time = 0;//下单时间
                    $status_wancheng_time = 0;//完成时间
                    $status_fenpei_time = 0;//分配时间
                    $status_shouhuo_time = 0;//收货时间
                    $status_sewing_time = 0;//车缝时间（暂时oem使用）
                    $num = 13;
                }
                if(5 == $v['status']){
                    if(0 == $status_xiadan_time){
                        $status_xiadan_time = $v['add_time'];//下单时间
                    }
                    if($status_xiadan_time > $v['add_time']){//首次出现
                        $status_xiadan_time = $v['add_time'];//下单时间
                    }
                }
                if(9 == $v['status']){
                    if(0 == $status_wancheng_time){
                        $status_wancheng_time = $v['add_time'];//完成时间
                    }
                    if($status_wancheng_time < $v['add_time']){//最后出现
                        $status_wancheng_time = $v['add_time'];//完成时间
                    }
                }
                if(6 == $v['status']){
                    if(0 == $status_fenpei_time){
                        $status_fenpei_time = $v['add_time'];//分配时间
                    }
                    if($status_fenpei_time > $v['add_time']){//首次出现
                        $status_fenpei_time = $v['add_time'];//分配时间
                    }
                }
                // 车缝时间
                if(8 == $v['status']){
                    if(0 == $status_sewing_time){
                        $status_sewing_time = $v['add_time'];//车缝时间
                    }
                    if($status_sewing_time < $v['add_time']){//最后出现
                        $status_sewing_time = $v['add_time'];//车缝时间
                    }
                }
                if(12 == $v['status']){
                    if(0 == $status_shouhuo_time){
                        $status_shouhuo_time = $v['add_time'];//收货时间
                    }
                    if($status_shouhuo_time < $v['add_time']){//最后出现
                        $status_shouhuo_time = $v['add_time'];//收货时间
                    }
                }
                if($status_xiadan_time && $status_wancheng_time){//全程
                    $result[$produce_order_id]['delivery']['full'] = ceil(($status_wancheng_time-$status_xiadan_time)/86400);
                }
                if($status_shouhuo_time && $status_fenpei_time){//CMT
                        $result[$produce_order_id]['delivery']['cmt'] = ceil(($status_shouhuo_time-$status_fenpei_time)/86400);
                }
                //oem 车缝时间
                if($status_shouhuo_time && $status_sewing_time){//sewing
                        $result[$produce_order_id]['delivery']['sewing'] = ceil(($status_shouhuo_time-$status_sewing_time)/86400);
                }

                if($num >= 0){//最后14条状态信息
                    $result[$produce_order_id]['data'][$num] = $v['user_name']." ".date("Y-m-d H:i:s",$v['add_time'])." ".$this->getProduceOrderStatusName($v['status']);
                    $num--;
                }
            }
        }
        return is_numeric($produce_order_ids) ? $result[$produce_order_id] : $result ;
    }

    /**
     * 显示数量或者到货数量
     * @access public
     * @param  $produce_order_id 订单ID
     * @param  $filed 字段
     * @param  $goods_sn 1是进行数据分割处理0不处理原数据返回
     * @author 戴鑫 2015-1-8 16:08:16
     * @modify 陈东 2015-12-4 15:19:43 优化代码
     * @return array
     */
    public function getProduceOrderFieldValue($produce_order_id = 0,$filed = '',$type = 1 ){

        if(1 == $type){
            $fileds = 'produce_order_id,goods_sn,order_info,'.$filed;
        }else{
            $fileds = $filed;
        }
        $res = array();
        $data = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$produce_order_id)->getField($fileds);
        if(1 == $type){
            $data['0'] = $this->getTotalProduceOrder($data[$produce_order_id]['order_info'], true);
            $data['1'] = $this->getTotalProduceOrder($data[$produce_order_id][$filed], true);
            foreach($data['0'] as $k=>$v){
                $t = str_replace('数量', 'num', $k);
                $result[] = array('show'=>$t,'key'=>$k,'val'=>isset($data['1'][$k]) ? $data['1'][$k] : '');
            }
            $res["info"] = $result;
            $res["goods_sn"] = $data[$produce_order_id]["goods_sn"];
        }
        return 1 != $type ? $data : $res;
    }

    /**
     * 调用updateProduceOrderValueAction并开启事务控制
     * @param $produce_order_id
     * @param $field
     * @param $data
     * @param null $qc_status
     * @param bool $is_qc
     * @param int $box_number
     * @param bool $use_trans
     * @return array
     * @author 曹禺 2017-11-14 16:10:37
     */
    public function updateProduceOrderValue( $produce_order_id, $field, $data, $qc_status = null, $is_qc = false, $box_number = 0, $use_trans = true){
        $transModel = new TransModel();
        return $transModel->callFuncWithTransControl( $this->getModelName(),'updateProduceOrderValueAction',
            compact('produce_order_id', 'field', 'data', 'qc_status', 'is_qc', 'box_number','use_trans'));
    }



    /**
     * 根据具体数量更新相关数据
     * @param array $params ['produce_order_id', 'field', 'data', 'qc_status', 'is_qc', 'box_number']
     * $produce_order_id int 生产订单id
     * $field string 字段名
     * $data string 字符串形式的数据
     * $qc_status int qc大货扫描，直接修改的状态 9，默认为空
     * bool $is_qc  默认不是qc
     * int  $box_number 箱数
     * @return array
     * @author 戴鑫 2015-1-8 16:08:16
     * @modify 韦俞丞 2018-2-2 12:00:00 添加入仓数量时，FBA订单要请求接口添加FBA待上架
     */
    public function updateProduceOrderValueAction(array $params){

        $produce_order_id = $params['produce_order_id'];
        $field = $params['field'];
        $data = $params['data'];
        $qc_status = $params['qc_status'];
        $is_qc = $params['is_qc'];
        $box_number = $params['box_number'];

        $sql = "/*master*/select * from " . $this->tablePrefix . 'produce_order where produce_order_id = ' . $produce_order_id ;
        $res = $this->getAll($sql);
        if(!count($res)){
            return array( 'success' => 0, 'message' => '参数错误，请刷新重试', 'errcode' => 101 );
        }
        $produce_order_info = $res[0];
        if($field == 'received_info'&&$res[0]['category']==6){
            $box_number = 1;
        }
        if($field == 'stored_num'){ // 修改入仓数量，涉及到累加
            if($is_qc===false){//当不是从qc接口访问时，执行判断
                if($res[0]['category']==2||$res[0]['category']==6){//线下单 状态需要等于已完成9状态
                    if($res[0]['status']!=9){
                        return array( 'success' => 0, 'message' => '请确定订单处于已完成状态', 'errcode' => 102 );
                    }
                }else{
                    if($res[0]['status']!=12&&$res[0]['status']!=9){//其他单 需要等于已收货状态
                        return array( 'success' => 0, 'message' => '请确定订单处于已收货状态', 'errcode' => 103 );
                    }
                }
                if($res[0]['bill_id']!=0){//如果是已生成账单 修改入仓数量 需要判断权限
                    if(!R('Privilege/checkRoleBool',array('bills_rucang_num_changes'))){
                        return array( 'success' => 0, 'message' => '无权限，请联系部门负责人', 'errcode' => 104);
                    }
                }
            }
            //判断账单状态处于待提交与待审核
            if($res[0]['bill_id']!=0){
                $bill_status = M('bill')->where('bill_id = '.$res[0]['bill_id'])->field('bill_status,type')->select();
                if(($bill_status[0]['bill_status'] !=0 &&
                        $bill_status[0]['bill_status'] != 3) &&
                    $bill_status[0]['type']==1){//0待审核、3待提交状态 type=1 生产账单
                    return array( 'success' => 0, 'message' => '账单状态非待审核状态或待提交状态，请与财务联系', 'errcode' => 105 );
                }
            }
            //获取次品数量
            $res_qc = $this->table( $this->tablePrefix.'produce_order_qc_report' )
                ->where( 'produce_order_id = ' . $produce_order_id )
                ->field('defective_num')
                ->select();
            $stored_str = $res[0]['stored_num'];
            $stored_old_num = explode("<br/>", $stored_str);
            $stored_old_num_arr = array();
            foreach ($stored_old_num as $value){
                if(!empty($value)){
                    $cuu_arr = explode(":", $value);
                    if(is_numeric($cuu_arr[0])){
                        $stored_old_num_arr[$cuu_arr[0]."_num"] = $cuu_arr[1];
                    }else{
                        $stored_old_num_arr[$cuu_arr[0]] = $cuu_arr[1];
                    }
                }
            }
            //去除所有填上0的字段
            $arr = explode('&lt;br/&gt;', $data);
            $turnover_no = '';
            //过滤FOB、US不添加周装箱
            if (strpos($arr[0], 'turnover_no:') !== false) {
                $turnover_no = array_shift($arr); //弹出第一个数组是为了获取周转箱号
                $turnover_no = trim(strtr($turnover_no, array('turnover_no:'=>''))); //修复传过来的值为空的bug
                $turnover_no = R('Inventory/checkTurnoverNo', compact('turnover_no'));
                if (!$turnover_no) {
                    return array( 'success' => 0, 'message' => '未输入周转箱号，请核实', 'errcode' => 106 );
                }
            }

            $stored_new_num_arr = array();
            foreach($arr as $v){
                $new_arr = explode(":", $v);
                $size_type[] = $new_arr['0'];
                if(is_numeric($new_arr['1']) && $new_arr['1']){//去除所有非正整数的填写
                    $key = str_replace('_', " ", str_replace('num', "数量",$new_arr['0']));
                    if(is_numeric($key)){
                        $stored_new_num_arr[$key."_num"]=$new_arr['1'];
                    }else{
                        $stored_new_num_arr[$key]=$new_arr['1'];
                    }
                }
            }
            $all_size = array_merge($stored_old_num_arr,$stored_new_num_arr);
            $data = '';
            //获取收货数量
            $received_num_arr = array();
            $received_num = explode("<br/>", $res[0]['received_info']);
            foreach($received_num as $value){
                if(!empty($value)){
                    $received_arr = explode(":", $value);
                    if(is_numeric($received_arr[0])){
                        $received_num_arr[$received_arr[0]."_num"] = $received_arr[1];
                    }else{
                        $received_num_arr[$received_arr[0]] = $received_arr[1];
                    }
                }
            }
            //获取次品数量
            $defective_num_arr = array();
            $defective_num = explode('&lt;br/&gt;', $res_qc[0]['defective_num']);

            foreach($defective_num as $vs){
                if(!empty($vs)){
                    $defective_arr = explode(":", $vs);

                    if(is_numeric($defective_arr[0])){
                        $defective_num_arr[$defective_arr[0]."_num"] = $defective_arr[1];
                    }else{
                        $defective_num_arr[$defective_arr[0]] = $defective_arr[1];
                    }
                }
            }
            //去除无数量元素
            foreach ($size_type as $key => $value) {
                if(!$value){
                    unset($size_type[$key]);
                }
            }
            foreach ($all_size as $key=>$value){
                if (array_key_exists($key, $stored_new_num_arr)){
                    //累加
                    $curr_num = $stored_old_num_arr[$key]+$stored_new_num_arr[$key];
                    if($curr_num<0){
                        return array( 'success'=>0, 'message'=>'入仓数量不能小于0', 'errcode'=>107 );
                    }
                    //加上次品数量
                    if(($curr_num+$defective_num_arr[$key])>$received_num_arr[$key])
                    {
                        return array( 'success'=>0, 'message'=>'入仓数量和次品数量的和超出到货数量', 'errcode'=>108 );
                    }
                    if(strpos($key,"_num")){
                        $cuu_key = substr($key, 0,strlen($key)-4);
                        $data .= $cuu_key.":".$curr_num."<br/>"; //如果原来入过仓，进行累加
                    }else{
                        $data .= $key.":".$curr_num."<br/>"; //如果原来入过仓，进行累加
                    }
                }else{
                    if(strpos($key,"_num")){
                        $cuu_key = substr($key, 0,strlen($key)-4);
                        $data .= $cuu_key.":".$stored_old_num_arr[$key]."<br/>";  //没有新入仓，则保留原数据
                    }else{
                        $data .= $key.":".$stored_old_num_arr[$key]."<br/>";  //没有新入仓，则保留原数据
                    }
                }
            }
            //入仓数量重新排序
            $size_asc = explode("<br/>", $data);
            foreach ($size_asc as $key => $value) {
                if(!$value){
                    unset($size_asc[$key]);
                }else{
                    $size_type_info[] = explode(":", $value);
                }
            }
            if($size_type_info['0']['0'] != '数量'){
                $data = '';
                foreach ($size_type as $k => $v) {
                    foreach ($size_type_info as $key => $value) {
                        if($v == $value[0]){
                            $data .= $value[0].':'.$value[1].'<br/>';
                        }
                    }
                }
            }
        }else{
            //去除所有填上0的字段
            $arr = explode('&lt;br/&gt;', $data);
            $data = '';
            $sum_storeNum = 0;
            foreach($arr as $v){
                $new_arr = explode(":", $v);
                $new_arr[1] = $new_arr[1];
                if(is_numeric($new_arr['1']) && $new_arr['1']){//去除所有非正整数的填写
                    $key = str_replace('_', " ", str_replace('num', "数量",$new_arr['0']));
                    $data .= $key.":".$new_arr['1']."<br/>";
                    $sum_storeNum+=$new_arr['1'];
                }
            }
            $rule['category']=$produce_order_info['category'];
            $rule['order_info']=$produce_order_info['order_info'];
            $rule['sum_storeNum']=$sum_storeNum;
            $stored_rule_info =  $this->storedNumRuleCheck($rule);
            if(!$stored_rule_info['success']){
                return array( 'success' => 0, 'message' => $stored_rule_info['message'], 'errcode' => 109);
            }
        }

        $produce_order_data = array($field=>$data);
        if(empty($qc_status)){
            $produce_order_data['box_num'] = $box_number;
        }else{
            $produce_order_data['status'] = $qc_status;
        }
        $resp_flag = $this->table( $this->tablePrefix.'produce_order' )->
        where('produce_order_id='.$produce_order_id)->
        save($produce_order_data);
        if(!$resp_flag){
            return array( 'success' => 0, 'message' => '订单更新失败', 'errcode' => 110,
                'insert_arr' => $produce_order_data, 'db_err' =>$this->getDbError() );
        }

        if($field == 'received_info'){// 修改到货数量时，添加操作记录
            if($box_number==0){//当箱数不为空时，一定是FOB,CMT后整中状态
                $status = array(
                    'produce_order_id' => $produce_order_id,
                    'status' => 20,
                    'user_name' => session('admin_name'),
                    'add_time' => time()
                );
            }else{
                $data='箱数:'.$box_number.'<br/>'.$data;
                $status = array(
                    'produce_order_id' => $produce_order_id,
                    'status' => 20,//修改送货数量
                    'user_name' => session('admin_name'),
                    'add_time' => time()
                );
            }
            $resp_flag = $this->table( $this->tablePrefix . 'produce_order_status' )->add($status);
            if(!$resp_flag){
                return array( 'success' => 0, 'message' => '订单状态增加失败', 'errcode' => 111,
                    'insert_arr' => $status, 'db_err' =>$this->getDbError() );
            }
        }elseif($field == 'stored_num'){
            if(in_array($produce_order_info['prepare_type'],array(1,2))){
                $res_set_fba = R("InventoryAdjust/updateFbaOrderByProduce",array($produce_order_id,false));// 更新fba单  关闭事务
                if(!$res_set_fba['success']){
                    return array( 'success' => 0, 'message' => '更新订单失败：'.$res_set_fba['message'], 'errcode' => 112 ,
                        'para_arr' => array($produce_order_id,false), 'db_err' =>$this->getDbError());
                }
            }
            $add_data = array();
            $add_data['produce_order_id'] = $produce_order_id;
            $add_data['status'] = 16;
            $add_data['user_name'] = I('session.admin_name');
            $add_data['add_time'] = time();
            $commit_data[] = $this->table($this->tablePrefix.'produce_order_status')->add($add_data);
            if(!empty($qc_status)){ //入仓
                $add_over_data = array();
                $add_over_data['produce_order_id'] = $produce_order_id;
                $add_over_data['status'] = 9;
                $add_over_data['user_name'] = I('session.admin_name');
                $add_over_data['add_time'] = time();
                $resp_flag = $this->table($this->tablePrefix.'produce_order_status')->add($add_over_data);
                if(!$resp_flag){
                    return array( 'success' => 0, 'message' => '订单状态增加失败', 'errcode' => 113,
                        'insert_arr'=>$add_over_data, 'db_err' =>$this->getDbError());
                }
            }
            // 添加入仓数量时,在账单数据列表新增等同产品与数量信息
            $res = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$produce_order_id)->select();
            if(!$res){
                return array( 'success' => 0, 'message' => '添加入仓数量时,在账单数据列表新增等同产品与数量信息', 'errcode' => 114 ,
                    'db_err' =>$this->getDbError());
            }

            //供应商统一使用supplier_id获取
            $supplierIds = array($res[0]['supplier_id']);
            //根据供应商id获取供应商信息
            $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
            $supplierInfo = $supplierInfo['content'];
            $res[0]['factory'] = $supplierInfo[$res[0]['supplier_id']]['title'];
            $prepare_type = (int) $res[0]['prepare_type'];
            $produce_order = $res[0];

            if( in_array($res[0]['prepare_type'], array(0, 4, 5))) {// 普通备货、特殊备货、亚马逊备货，添加账单数据列表
                foreach($stored_new_num_arr as $k => $v){
                    if($v<0){
                        $add_time = time();
                        for ( $j = 0; $j > intval($v); $j-- ) {
                            if ( $k == '数量' ) {// 处理下单跟进那边SKU没有属性的情况
                                $goods_attr = '';
                            } else {
                                if(strpos($k,"_num")){
                                    $k = substr($k, 0,strlen($k)-4);
                                }
                                $goods_attr = 'Size:'.$k.'<br />';
                            }
                            $w = array(
                                'produce_order_id' => $produce_order_id,
                                'goods_sn' => $res[0]['goods_sn'],
                                'goods_attr' => $goods_attr,
                                'is_delete' => 0,
                                'new_turnover_no' => $turnover_no, //增加周转箱号字段
                                'warehouse' => array( 'in', '0,1' ), //条件限制默认值0和广州仓库类型值1
                            );
                            $temp_id = M('inventory_temp')->where($w)->order('id desc')->getField('id');
                            if ( !$temp_id ) {
                                return array( 'success' => 0, 'message' => '临时库存获取失败', 'errcode' => 115,
                                    'db_err' =>$this->getDbError() );
                            }
                            $resp_flag = M('inventory_temp')->where(array('id' => $temp_id))->save(array('is_delete' => 1));
                            if(!$resp_flag){
                                return array( 'success' => 0, 'message' => '删除临时库存失败', 'errcode' => 116,
                                    'delete_arr' => array('id' => $temp_id),'db_err' =>$this->getDbError() );
                            }

                            $add = array(
                                'inventory_temp_id' => $temp_id,
                                'status' => 3, // 3表示入仓删除
                                'add_time' => $add_time,
                                'admin_name' => session('admin_name'),
                            );
                            $resp_flag = M('inventory_temp_status')->add($add);// 删除的操作记录
                            if(!$resp_flag){
                                return array( 'success' => 0, 'message' => '增加删除的操作记录失败', 'errcode' => 117,
                                    'insert_arr' => $add, 'db_err' =>$this->getDbError() );
                            }
                        }
                    }else{
                        $data_arr = array();
                        for($i = 0; $i < intval($v); $i++){
                            $image_data['produce_order_id'] = $produce_order_id;
                            $image_data['goods_thumb'] = $res[0]['goods_thumb'];
                            $image_data['goods_sn'] = $res[0]['goods_sn'];
                            if($k == '数量'){// 处理下单跟进那边SKU没有属性的情况
                                $image_data['goods_attr'] = '';
                            }else{
                                if(strpos($k,"_num")){
                                    $k = substr($k, 0,strlen($k)-4);
                                }
                                $image_data['goods_attr'] = 'Size:'.$k.'<br />';
                            }
                            $image_data['supplier_linkman'] = $res[0]['factory'];// 供应商是加工厂信息,确保库存列表中生产商信息是准确的
                            $image_data['add_time'] = time();
                            $image_data['admin_name'] = I('session.admin_name');
                            $image_data['new_turnover_no'] = $turnover_no;//增加周转箱号字段
                            $image_data['warehouse'] = 1; //广州仓库类型值1
                            $image_data['type'] = 1; ////生成待上架数据的类型：大货（订单跟进入仓、PDA入仓）
                            $data_arr[$i] = $image_data;
                            //调用库存接口插入数据到账单数据列表中
                        }
                        $resp_flag = R('Inventory/insertBillStatistic',array($data_arr));
                        if(!$resp_flag){
                            return array(
                                'success' => 0, 'message' => '插入数据失败', 'errcode' => 118,
                                'insert_arr' => $data_arr,'db_err' =>$this->getDbError()
                            );
                        }
                    }
                }
            }
            elseif ( $prepare_type === 1 )// 46205 FBA订单手工录入数据需产生待上架数据
            {
                $params = array(
                    'produce_order' => $produce_order,// 订单信息一维数组，包含工厂名称
                    'new_stored_num' => $stored_new_num_arr,// 本次录入的入仓数量
                    'turnover' => $turnover_no,// 周转箱号
                );
                $tmp = $this->handleAmazon($params);// 处理amazon信息
                Log::info( '/ProduceProjectOrder/handleAmazon', array(
                        'request_url' => 'handleAmazon',
                        'request_content' => $params,
                        'response_content' => $tmp,
                ));
                if ( $tmp['code'] != 0 ) {
                    return array( 'success' => 0, 'message' => $tmp['msg'], 'errcode' => 119 );
                }
            }
        }

        return array( 'success'=>1, 'errcode'=>0, 'content'=>array(
            'produce_order_id'=>$produce_order_id,
            'filed'=>$field,
            'data'=>$data,
            'prepare_type'=>$res[0]['prepare_type'] )
        );
    }

    /**
     * FBA单，处理amazon逻辑
     * 验证周转箱，保存入仓数量，FBA订单，请求接口，添加FBA待上架数据
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function handleAmazon($params)
    {
        $produce_order = $params ['produce_order'];
        $new_stored_num = $params ['new_stored_num'];
        $turnover = $params ['turnover'];
        
        // 第一步 验证数据
        $res = $this->checkLogic( $produce_order, $new_stored_num, $turnover );
        if ( $res['code'] != 0 )
        {
            return $res;
        }
        $size_fnsku = $res ['size_fnsku'];
        
        // 第二步 拼装请求
        $request = $this->prepareLogic( $produce_order, $new_stored_num, $turnover, $size_fnsku );
        
        // 第三步 添加数据
        $res = $this->addLogic( $request );
        if ( $res['code'] != 0 )
        {
            return $res;
        }
        
        return array( 'code' => 0 );
    }
    
    /**
     * 验证周转箱、尺码
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function checkLogic( $produce_order, $new_stored_num, $turnover )
    {
        // 验证周转箱
        if ( ! $turnover ) {
            return array( 'code' => 0, 'msg' => '周转箱不能为空' );
        }
        // 第一步 验证周转箱是否可用，即不存在于亚马逊
        $res = $this->checkAmazonTurnover($turnover);
        if ( $res['code'] != 0 )
        {
            return $res;
        }
        // 第二步 验证新添加的尺码是否合法
        $res = $this->checkAmazonSizes( $produce_order, $new_stored_num );
        if ( $res['code'] != 0 )
        {
            return $res;
        }
        
        return array( 'code' => 0, 'size_fnsku' => $res['size_fnsku'] );
    }
    
    /**
     * 拼装请求
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function prepareLogic( $produce_order, $new_stored_num, $turnover, $size_fnsku )
    {
        $detail = array();
        foreach ( $new_stored_num as $size => $num ) {
            $size = ( $size === '数量' ) ? '' : $size;
            $fnsku = $size_fnsku [$size] ? $size_fnsku [$size] : '';
            $num = (int) $num;
            
            $detail [] = array(
                'turnover_no' => $turnover,
                'size' => $size,
                'fnsku' => $fnsku,
                'down_num' => $num,
            );
        }
        $shop_account = M('fba_order')->where(array(
            'produce_order_id' => $produce_order['produce_order_id'],
            'is_delete' => 0,
        ))->getField( 'type' );
        $request = array(
            'sku' => $produce_order ['goods_sn'],
            'add_time' => date( 'Y-m-d H:i:s', time() ),
            'add_uid' => session('admin_name'),
            'detail' => $detail,
            'shop_account' => $shop_account,
        );
        
        return $request;
    }
    
    /**
     * 添加数据
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function addLogic( $request )
    {
        return $this->addAmazonTostore($request);
    }
    
    /**
     * 验证周转箱
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function checkAmazonTurnover( $turnover )
    {
        if ( ! $turnover ) {
            return array( 'code' => 1, 'msg' => '周转箱号不能为空' );
        }
        $curl = \Common\Util\Curl::init();
        $params = array(
            'turnover_no' => $turnover,
        );
        $option = array(
            'url' => C('AMAZON_QC_TURNOVER_URL') . '/Api/MoveApi/checkTurnover',
            'httpheader' => array(
                'Content-Type:application/json',
                'Accept:application/json',
            ),
            'postfields' => json_encode($params),
        );
        $logDir = '/ppom_amazon_check';
        $res = $curl->option($option)->logdir($logDir)->run();
        $res = json_decode($res, true);
        if ( $res['code'] === '0' )
        {
            return array( 'code' => 0, 'msg' => "周转箱{$turnover}校验通过" );
        } else {
            return array( 'code' => 1, 'msg' => "周转箱{$turnover}校验失败" );
        }
    }
    
    /**
     * 检查尺码是否全部合法（合法是指在商城能获取到对应的sku、尺码）
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function checkAmazonSizes( $produce_order, $new_stored_num )
    {
        if ( ! $new_stored_num )
        {
            return array( 'code' => 1, 'msg' => '入仓数量为空' );
        }
        $fba_order = M('fba_order')->where(array(
            'produce_order_id' => $produce_order['produce_order_id'],
            'is_delete' => 0,
        ))->find();
        if ( ! $fba_order )
        {
            return array( 'code' => 1, 'msg' => '未找到fba订单信息' );
        }
        $params2 = array(
            'action' => 'getFbaInfo',
            'data' => array(
                array(
                    'goods_sn' => $produce_order ['goods_sn'],
                    'fba_account' => $fba_order ['type'],
                ),
            ),
        );
        $fba_infos = D('InventoryAdjust')->getGoodsSnFba( $params2 );// 包含这个sku的各个尺码
        if ( count($fba_infos) == 0 )
        {
            return array( 'code' => 1, 'msg' => '未能从商城获取fba信息' );
        }
        $fba_infos_key = array();
        foreach ( $fba_infos as $v )
        {
            $fba_infos_key [$v['goods_attr']] = $v;
        }
        $size_fnsku = array();
        foreach ( $fba_infos as $v )
        {
            $size_fnsku [$v['goods_attr']] = $v['goods_sn_fba'];
        }
        foreach ( $new_stored_num as $size => $num )
        {
            if ( $size === '数量' )
            {
                $size = '';
            }
            if ( ! $fba_infos_key [$size] )
            {
                return array( 'code' => 1, 'msg' => "尺码{$size} 未能从商城获取fba信息" );
            }
            if ( (int) $num <= 0 )
            {
                return array( 'code' => 1, 'msg' => "尺码{$size} 入仓数量必须大于0" );
            }
        }
        
        return array( 'code' => 0, 'size_fnsku' => $size_fnsku, );
    }
    
    /**
     * 添加数据
     *
     * @author 韦俞丞 2018-2-1 12:00:00
     */
    public function addAmazonTostore( $request )
    {
        if ( ! $request ) {
            return array( 'code' => 1, 'msg' => '请求参数不能为空' );
        }
        $curl = \Common\Util\Curl::init();
        $option = array(
            'url' => C('AMAZON_QC_TURNOVER_URL') . '/Api/MoveApi/moveEnter',
            'httpheader' => array(
                'Content-Type:application/json',
                'Accept:application/json',
            ),
            'postfields' => json_encode($request),
        );
        $logDir = '/ppom_amazon_move';
        $res = $curl->option($option)->logdir($logDir)->run();
        $res = json_decode($res, true);
        if ( $res['code'] === '0' )
        {
            return array( 'code' => 0, 'msg' => "接口请求成功" );
        } else {
            return array( 'code' => 1, 'msg' => "接口请求失败" );
        }
    }
    
    /**
     * 下单跟进的面料信息列表
     * @access public
     * @param mixed $produce_id
     * @param mixed $produce_order_id
     * @param mixed $goods_sn
     * @return string
     * @author 李健 2015-1-28 19:42:21
     * @modify 陈东 2017-3-14 10:04:30 增加生产信息获取
     * @modify 李永旺 2018-2-27 09:49:30 增加是否回货
     */
    public function produceOrderFabricList($data){
        if(!$data['goods_sn'] || !$data['produce_order_id']){
            return array(
                'success'=>0,
                'message'=>'sku或订单编号为空!',
                'errorcode'=>1
            );
        }
        $info = array();
        $size_array = array();
        $size_arr = array();
        $size_arr_new = array();
        $produce_order = M('produce_order')->where(array('produce_order_id'=>$data['produce_order_id']))->find();
        if($produce_order){
            $order_info = explode('<br/>', $produce_order['order_info']);
            array_pop($order_info);
            foreach ($order_info as $key => $value) {
                $size_array = explode(':', $value);
                $size_arr_new[$size_array[0]] = $size_array[1];
                if($size_array[0] !== '数量'){
                    $size_arr[] = $size_array[0];
                }
            }
        }
        $all_num = 0;
        foreach ($size_arr_new as $k=>$val){
            $all_num += $val;
        }
        $size_arr_new['合计'] = $all_num;
        // $info = S('getProduceFabricByPlm_'.$data['goods_sn']);
        // if(!$info){
        $info = D('ProduceProject')->getProduceFabricByPlm($data['goods_sn'],$size_arr);
        //     S('getProduceFabricByPlm_'.$data['goods_sn'],$info,3600);
        // }
        // 根据$produce_order_id和$produce_fabric_info_id来获取produce_order_fabric_info表中相关数据
        foreach($info as $k => $v){
            //获取order_info
            $order_info = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$data['produce_order_id'])->find();
            //获取下单数量
            $order_total = $this->getTotalProduceOrder($order_info['order_info']);
            $produce_fabric_info_id = $v['fabric_info_id'];
            $order_fabric_info = $this->table($this->tablePrefix . 'produce_order_fabric_info')->where("produce_order_id = ".$data['produce_order_id']." and produce_fabric_info_id = '$produce_fabric_info_id'")->find();

            $data_a = array(
            'produce_order_id' =>$data['produce_order_id'],
            'fabric_info_id' =>$produce_fabric_info_id,
            'is_delete' =>0,
            );
            if($order_fabric_info){
                $info[$k] = array_merge($v,$order_fabric_info);
                $info[$k]['remain_num'] = $info[$k]['purchase_actual_num'] - $info[$k]['cut_actual_num']; //减法操作再插入语句时计算 要去掉
                $info[$k]['purchase_actual_num'] = floatval($info[$k]['purchase_actual_num']);
                $info[$k]['purchase_amount'] = floatval($info[$k]['purchase_amount']);
                $info[$k]['cut_actual_num'] = floatval($info[$k]['cut_actual_num']);
                $info[$k]['cut_usehour'] = floatval($info[$k]['cut_usehour']);
                //操作记录
                $info[$k]['record'] = "{$info[$k]['operation']} ".date('Y-m-d H:i:s',$info[$k]['operation_time']);
            }else{
                $info[$k]['purchase_actual_num'] = '';
                $info[$k]['purchase_amount'] = '';
                $info[$k]['cut_actual_num'] = '';
                $info[$k]['remain_num'] = 0;
                $info[$k]['cut_usehour'] = '';
                $info[$k]['order_fabric_info_id'] = '';
                $info[$k]['huowei_name'] = '';
                $info[$k]['record'] = '';
            }
            $fabric_isback_info =  M('produce_fabric_isback_info')->where($data_a)->find();
            $info[$k]['is_back'] = 0;
            if (!empty($fabric_isback_info)) {
               $info[$k]['is_back'] =$fabric_isback_info['is_back'];
            }
            $info[$k]['order_info'] = $order_info;
            //统计总用量
            if($v['size']){
                $info[$k]['dosage_total'] = floatval($v['single_dosage']) * $size_arr_new[$v['size']];
            }else{
                $info[$k]['dosage_total'] = floatval($v['single_dosage']) * $order_total;
            }
            //裁床单价
            //获取cut_info
            $cut_info = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$data['produce_order_id'])->field('cut_info')->find();
            //获取下单数量
            $cut_total = $this->getTotalProduceOrder($cut_info['cut_info']);
            $info[$k]['cut_unitprice'] = ($cut_total ? round(44.5 * $info[$k]['cut_usehour'] / $cut_total,2) : 0);
        }
        if($data['is_print']===false){//如果不是配料单打印接口调用需要判断权限
            $role_config=array(
                    'fabric_purchase_check'=>array('purchase_actual_num','purchase_amount'),
                    'cut_check'=>array('cut_actual_num','cut_usehour'),
                    'add_huowei_name'=>array('huowei_num'),
                     );
            $arr=array();
            foreach($role_config as $role_key=>$role_val){
                if(!R('Privilege/checkRoleBool',array($role_key))){
                    $arr=array_merge($arr,$role_val);
                }
            }
            if(!empty($arr)){
                foreach($info as $i=>$v){
                    foreach($v as $attr=>$val){
                        $info[$i]['checklist'] = $arr;
                    }
                }
            }
        }
        if($info){
            return array(
                'success'=>1,
                'content'=>$info
            );
        }else{
            return array(
                'success'=>0,
                'message'=>'没有面料信息!',
                'errorcode'=>2
            );
        }
    }

     /**
     * 下单跟进的面料信息列表 给我货辅料待采列表调用
     * @access public
     * @param mixed $produce_id
     * @param mixed $produce_order_id
     * @param mixed $goods_sn
     * @return string
     * @author 李永旺 2018-3-1 15:06:21
     */
    public function produceOrderFabricListGeiwohuo($data){
        if(!$data['goods_sn']){
            return array(
                'success'=>0,
                'content'=>''
            );
        }
        // if(empty($data['produce_id'])){
        //     $data['produce_id'] = D('ProduceProject')->queryProdueId($data['goods_sn']);
        //     if($data['produce_id']['success']==0){
        //         return $data['produce_id'];
        //     }else{
        //         $data['produce_id'] = $data['produce_id']['content'];
        //     }
        // }
        $info = D('ProduceProject')->getProduceFabric("produce_id = ".$data['produce_id']);
        // 根据$produce_order_id和$produce_fabric_info_id来获取produce_order_fabric_info表中相关数据
        foreach($info as $k => $v){
            //获取order_info
            // $order_info = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$data['produce_order_id'])->find();
            //获取下单数量
            // $order_total = $this->getTotalProduceOrder($order_info['order_info']);
            $produce_fabric_info_id = $v['fabric_info_id'];
            $order_fabric_info = $this->table($this->tablePrefix . 'produce_order_fabric_info')->where("produce_order_id = ".$data['produce_order_id']." and produce_fabric_info_id = '$produce_fabric_info_id'")->find();

            $data_a = array(
            'produce_order_id' =>$data['produce_order_id'],
            'fabric_info_id' =>$produce_fabric_info_id,
            'is_delete' =>0,
            );
            $fabric_isback_info =  M('produce_fabric_isback_info')->where($data_a)->find();
            $info[$k]['is_back'] =0;
            if (!empty($fabric_isback_info)) {
               $info[$k]['is_back'] =$fabric_isback_info['is_back'];
            }
            // if($order_fabric_info){
            //     $info[$k] = array_merge($v,$order_fabric_info);
            //     $info[$k]['remain_num'] = $info[$k]['purchase_actual_num'] - $info[$k]['cut_actual_num']; //减法操作再插入语句时计算 要去掉
            //     $info[$k]['purchase_actual_num'] = floatval($info[$k]['purchase_actual_num']);
            //     $info[$k]['purchase_amount'] = floatval($info[$k]['purchase_amount']);
            //     $info[$k]['cut_actual_num'] = floatval($info[$k]['cut_actual_num']);
            //     $info[$k]['cut_usehour'] = floatval($info[$k]['cut_usehour']);
            //     //操作记录
            //     $info[$k]['record'] = "{$info[$k]['operation']} ".date('Y-m-d H:i:s',$info[$k]['operation_time']);
            // }else{
            //     $info[$k]['purchase_actual_num'] = '';
            //     $info[$k]['purchase_amount'] = '';
            //     $info[$k]['cut_actual_num'] = '';
            //     $info[$k]['remain_num'] = 0;
            //     $info[$k]['cut_usehour'] = '';
            //     $info[$k]['order_fabric_info_id'] = '';
            //     $info[$k]['huowei_name'] = '';
            //     $info[$k]['record'] = '';
            // }
            // $info[$k]['order_info'] = $order_info;
            //统计总用量
            // $info[$k]['dosage_total'] = floatval($v['single_dosage']) * $order_total;
            //裁床单价
            //获取cut_info
            // $cut_info = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$data['produce_order_id'])->field('cut_info')->find();
            //获取下单数量
            // $cut_total = $this->getTotalProduceOrder($cut_info['cut_info']);
            // $info[$k]['cut_unitprice'] = ($cut_total ? round(44.5 * $info[$k]['cut_usehour'] / $cut_total,2) : 0);
        }

        if($info){
            return array(
                'success'=>1,
                'content'=>$info
            );
        }else{
            return array(
                'success'=>0,
                'content'=>'',
            );
        }
    }

    /**
     * 下单跟进修改面料总用量
     * @access public
     * @param mixed $data
     * @author 李健 2015-1-24 16:28:58
     * @modify 葛振 2016-11-16 13:39:07  订单跟进功能新增
     */
    public function updateProduceOrderFabric($data){
        foreach($data as $key => $val){
            $update_data = array();
            $update_data['cut_usehour'] = $val['cut_usehour'];
            $update_data['purchase_actual_num'] = $val['purchase_actual_num'];
            $update_data['purchase_amount'] = $val['purchase_amount'];
            $update_data['cut_actual_num'] = $val['cut_actual_num'];
            $update_data['huowei_name'] = $val['huowei_num'];
            //加入操作人和时间
            $update_data['operation'] = session('admin_name');
            $update_data['operation_time'] = time();
            $this->table($this->tablePrefix . 'produce_order_fabric_info')->where('produce_fabric_info_id = ' . $val['fabric_info_id'] . ' AND produce_order_id=' . $val['produce_order_id'])->data($update_data)->save();
        }
    }

    /**
     * 下单进程面辅料信息新增
     * @access public
     * @param array $data
     * @author 薛升 2015-6-15 9:12:11
     * @modify 葛振 2016-11-16 13:39:20  订单跟进功能新增
     */
     public function addProduceOrderFabric($data){
         foreach($data as $key => $val){
             if($val['purchase_actual_num']!='' || $val['purchase_amount']!='' || $val['cut_actual_num']!='' || $val['cut_usehour']!=''|| $val['huowei_num']!=''){
                 $add_data = array();
                 $add_data['produce_order_id'] = $val['produce_order_id'];
                 $add_data['produce_fabric_info_id'] = $val['fabric_info_id'];
                 $add_data['cut_usehour'] = $val['cut_usehour'];
                 $add_data['purchase_actual_num'] = $val['purchase_actual_num'];
                 $add_data['purchase_amount'] = $val['purchase_amount'];
                 $add_data['cut_actual_num'] = $val['cut_actual_num'];
                 $add_data['huowei_name'] = $val['huowei_num'];
                 //加入操作人和时间
                 $add_data['operation'] = session('admin_name');
                 $add_data['operation_time'] = time();
                 $this->table($this->tablePrefix.'produce_order_fabric_info')->add($add_data);
             }
         }
     }
    /**
     * 根据订单id查看是否存在相关的面辅料信息
     * @access public
     * @param int $produce_order_id
     * @return boolean
     * @author 薛升 2015-6-16 10:46:24
     * @modify 韦俞丞 2015-11-27 13:46:02 判断参数是否为空
     */
    public function isHaveFabricInfo($produce_order_id){
        if(!$produce_order_id){
            return false;
        }
        if($this->table($this->tablePrefix."produce_order_fabric_info")->where('produce_order_id='.$produce_order_id)->find()){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 查询下单跟进管理的备注记录
     * @access public
     * @param  $produce_order_id 订单ID
     * @author 杨尚儒  2015-1-24 16:17:54
     * @modify 陈东 2015-12-7 11:39:34 优化代码
     */
    public function getProduceOrderRemark($produce_order_id){
        if(empty($produce_order_id)){
           return array(
                'success'=>0,
                'message'=>'produce_order_id is null',
                'errorcode'=>1
           );
        }
        $where = 'produce_order_id = '.$produce_order_id;
        $list = $this->table($this->tablePrefix . 'produce_order_remark')->where($where)->select();
        foreach ($list as $key=>$val){
            if(empty($list[$key]['remark_info']) || $list[$key]['remark_info'] == '最近次品原因：  ' || $list[$key]['remark_info'] == '最近次品原因：  无' ){
                unset($list[$key]);
                continue;
            }
            $list[$key]["time"] =  date('Y-m-d H:i:s',$val['time']);
        }
        return array(
            'success'=>1,
            'content'=>array_values($list)
        );
    }

    /**
     * ajax 插入备注信息
     * @access public
     * @param mixed $remark_info
     * @param mixed $produce_order_id
     * @param mixed $operator
     * @param mixed $time
     * @return array
     *
     * @author 杨尚儒 2015-1-23 14:06:21
     * @modify 周金剑 2017-04-11 15:27:01 geiwohuo数据插入
     */
    public function addProduceOrderRemark($remark_info,$produce_order_id,$operator = null,$time = null){
        if(empty($produce_order_id)){
           return array(
                'success'=>0,
                'message'=>'produce_order_id为空!',
                'errorcode'=>1
           );
        }
        $produce_order_remark = array();
        //geiwohuo传过来的数据
        if(empty($operator)){
            $operator =session('admin_name');
        }
        if(empty($time)){
            $time = time();
        }
        if($remark_info != ''){
            $produce_order_remark['produce_order_id']=$produce_order_id;
            $produce_order_remark['operator']=$operator;
            $produce_order_remark['time']=$time;
            $produce_order_remark['remark_info']=$remark_info;
            $row_res = $this->table($this->tablePrefix . 'produce_order_remark')->add($produce_order_remark);
        }else{
            return array(
                'success'=>0,
                'message'=>'备注信息为空!',
                'errorcode'=>2
            );
        }

        if($row_res){
            return array(
                'success'=>1,
                'content'=>$row_res
            );
        }else{
            return array(
                'success'=>0,
                'message'=>'插入失败',
                'errorcode'=>3
           );
        }
    }

     /**
     * 面料打印信息
     * @return
     * @access public
     * @author 丁浩宇 2015-3-3 16:45:17
     * @modify 李永旺 2018-03-106 21:28:18 修改物料单请求PLM紧为自购的打印
     */
    public function fabricPrintInfo($produce_order_id){
        if (!empty($produce_order_id)) {
            $pid = rtrim($produce_order_id,',');
        }else{
            return array("success"=>0,"message"=>"参数为空");
        }
        //现在逻辑为处理多个的sku
        $query_order_sql = "SELECT * FROM " . $this->tablePrefix . "produce_order WHERE produce_order_id in(".$pid.")";
        $produce_order_info =$this->getAll($query_order_sql);
        foreach ($produce_order_info as $key=>$p_value){
            //取优化编号
            $produce_info = D('ProduceProject')->getProduceCode($p_value['goods_sn'], $p_value['produce_id'], 'code,style');
            $produce_order_info[$key]['code'] = $produce_info['code'];
            
            //转换时间格式
            $produce_order_info[$key]['add_time'] = date("Y-m-d H:i:s",$p_value['add_time']);
            //转换图片地址

            $goods_thumb = D('GoodsManage')->getImageUrl($p_value['goods_thumb'],'',$p_value['goods_sn']);
            $produce_order_info[$key]['goods_thumb'] = $goods_thumb;
            //获取产品列表
            $size_arr = explode('<br/>', $p_value['order_info']);
            $size_arr_new = array();
            $size_arr_type = array();
            foreach ($size_arr as $value){
                $temp_size = explode(':',$value);
                if(!empty($temp_size[1])){
                    $size_arr_new[$temp_size[0]] = $temp_size[1];
                    if($temp_size[0] !== '数量'){
                        $size_arr_type[] = $temp_size[0];
                    }
                }
            }
            $all_num = 0;
            foreach ($size_arr_new as $k=>$val){
                $all_num += $val;
            }
            $size_arr_new['合计'] = $all_num;
           
            $produce_order_info[$key]['order_info'] = $size_arr_new;
            //拉链
            $produce_order_info[$key]['zipper_size'] = D('ProduceProject')->getZipperSizeByGoodsSn($p_value['goods_sn']);
            //获取源变更为plm
                $fabric_info_arr = D('ProduceProject')->getProduceFabricByPlm($p_value['goods_sn'],$size_arr_type);


                $str_material_code=array();
                foreach($fabric_info_arr as $index=>$one_fabric_info){
                    if (!empty($one_fabric_info['material_code'])) {
                           $str_material_code[] =$one_fabric_info['material_code'];
                    }
                    if(is_numeric($one_fabric_info['single_dosage'])){
                        if($one_fabric_info['size']){
                            $fabric_info_arr[$index]['total_dosage'] = $one_fabric_info['single_dosage']*$size_arr_new[$one_fabric_info['size']];
                        }else{
                            $fabric_info_arr[$index]['total_dosage'] = $one_fabric_info['single_dosage']*$all_num;
                        } 
                    }else{
                        $fabric_info_arr[$index]['total_dosage'] = '单件用量不是数字!';
                    }  
                }
                $zigou_arr=array();
                if (!empty($str_material_code)) {
                    $res_plm_info = D('ProduceProject')->getMaterialInfoBySku($str_material_code);
                    if (!empty($res_plm_info['data']) && !$res_plm_info['code']) {
                        foreach ($res_plm_info['data'] as $key_a => $value_a) {
                            if ($value_a['purchase_type']==1) {
                               array_push($zigou_arr,$key_a);
                            }
                        }
                    }
                }
                foreach($fabric_info_arr as $index2=>$one_fabric_info2){
                    if (in_array(trim($one_fabric_info2['material_code']), $zigou_arr)) {
                        $fabric_info_arr[$index2]['purchase_type'] =1;
                    }else{
                        $fabric_info_arr[$index2]['purchase_type'] ='';
                    }
                }

                $produce_order_info[$key]['suplis'] = $fabric_info_arr;
                $produce_order_info[$key]['style'] = $fabric_info_arr[0]['design_code'];
        }
        return $produce_order_info;//返回面料信息
    }



    /**
     * 获取生产次品信息
     * @param array $all_produce_order_id
     * @return array
     * @author 陈东 2017-4-12 15:35:12
     */
    public function getProduceDefective($all_produce_order_id = array()){
        if(empty($all_produce_order_id)){
            return array('tatol_defective_price'=>array(),'tatol_defective_num'=>array());
        }
        $tatol_defective_price = array();
        $tatol_defective_num = array();
        $defective_info_produce = R('Inventory/InventoryGetDefectiveQuery',array('type=2 and produce_order_id in ('.join(',',$all_produce_order_id).') and is_delete=0'));
        $defective_info = !empty($defective_info_produce['content'])?$defective_info_produce['content']:array();
        foreach($defective_info as $defective_info_value){
            if(!isset($tatol_defective_price[$defective_info_value['produce_order_id']])){
                $tatol_defective_price[$defective_info_value['produce_order_id']] = 0;
            }
            $tatol_defective_price[$defective_info_value['produce_order_id']] += $defective_info_value['cost'];
            $tatol_defective_num[$defective_info_value['produce_order_id']]++;
        }
        return array('tatol_defective_price'=>$tatol_defective_price,'tatol_defective_num'=>$tatol_defective_num);
    }

    /**
     * 查询商品成本作为实价
     * @access public
     * @param  $produce_order_id  生产订单号
     * @return bool
     * @author 韦俞丞 2015-6-9 16:09:51
     * @modify 韦俞丞 2018-2-24 12:00:00 47398 订单跟进-备货订单状态变更需判断实价是否为空
     */
    public function updateDefaultOrderPrice($produce_order_id){
        $sql = " SELECT produce_order_id,goods_sn,currency,supplier_id,order_info,order_price FROM " . $this->tablePrefix . "produce_order WHERE produce_order_id='" . $produce_order_id . "'";
        $order = $this->getRow($sql);

        //供应商统一使用supplier_id获取
        $supplierIds = array($order['supplier_id']);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        $order['supplier_linkman'] = $supplierInfo[$order['supplier_id']]['title'];
        $arr = array(array('supplier_id'=>$order['supplier_id']));
        $arr = D('Prepare')->getSupplierLinkman($arr);//根据id获取供应商
        $cate_name_arr = D('Purchase')->getSupplierCat($arr);
        // 获取一级分类名判断是否属于生产部
        if(!isset($cate_name_arr[0]['first_category_name']) || ($cate_name_arr[0]['first_category_name'] == '生产部')){
            // 非生产部，且order表没有实价，就查goods表取成本cost作为默认实价
            if(strpos($order['supplier_linkman'], '生产部') !== false){ 
                $res = R('Goods/getGoodsCost', array('goods_sn' => $order['goods_sn']));
                if($res['success']){
                    $cost = $res['content'];   
                    $edition_num = '';
                    $base_price = $cost;
                } else {
                    return array( 'status' => 1 );
                }
            } else {
                return array( 'status' => 1 );
            }
        }else{
            $rule_level = $this->getPriceGoodsCost($order);
            //根据SKU向商品中心获取成本价
            $size = D("PurchaseList")->getSizeDetail105($order["order_info"],false);
            $size_data = array();
            foreach($size as $key=>$num){
                $size_data[] = array(
                    "attribute" => $key,
                    "skc" => $order["goods_sn"],
                );
            }
            $cost_data = D("Purchase")->getGoodsCostByAttr($size_data);
            $currency  = $order["currency"] ? $order["currency"] : "CNY";
            $cost_data = array_unique(array_column($cost_data[$order['goods_sn']],$order['currency']));
            if(empty($cost_data) || count($cost_data) > 1){
                return array("status" => 1,"msg" => "商品中心获取单价失败");
            }
            $cost = $cost_data[0];

            if($cost && $cost!='0'){
                $base_price = $cost;
                if($rule_level['success']){
                    $cost = $rule_level['data'] * $cost;
                    $edition_num = $rule_level['edition_num'];
                } 
            }else{
                return array('status'=>1);
            }     
        }
        $data = array(
            'order_price' =>$cost,
            'price_edition_num' =>$edition_num ? $edition_num : "",
            'base_price' =>$base_price,
            );
       $res =  M('produce_order')->where(array('produce_order_id'=>$order['produce_order_id']))->save($data);
    }
    /*
     * 向生产订单状态表添加操作记录
     * @access private
     * @param $produce_order_id 生产订单号
     * @param $status 状态
     * @return boolean
     * @author 韦俞丞 2015-12-03 15:24:38
     */
    public function addProduceOrderStatus($produce_order_id = 0, $status){
        if(!$produce_order_id || !isset($status)){
            return false;
        }
        $data_arr = array(
            'produce_order_id' => $produce_order_id,
            'status' => $status,
            'user_name' => session('admin_name'),
            'add_time' => time()
        );
        return $this->table($this->tablePrefix.'produce_order_status')->add($data_arr);
    }

    /**
     * 对生产订单的批量操作（添加账单）
     * @access public
     * @param $ids 传入id的数组
     * @param boolean  $partner_supplier_bill 判断伙伴类型供应商，是否需要生成账单 true=是伙伴供应商，生成账单，false=是伙伴供应商，不生成账单
     * @return array
     * @author 韦俞丞 2015-12-04 10:22:19
     * @modify 靳明杰 2017-9-20 17:26:27 生成账单时通过供应商一级分类是否为【美国仓】
     */
    public function batchProduceOrderBill($ids,$partner_supplier_bill=true){
        $return = array('msg' => '', 'status' => 1, 'link'=>array());
        $ids_count = count($ids);// 传入id个数
        $produce_order = $this->table($this->tablePrefix.'produce_order')->where('produce_order_id IN(' . join(',', $ids) . ')')->field('produce_order_id,status,category,currency')->select();
        if(count($produce_order) != $ids_count){
            $return['msg'] = '勾选的生产订单，有些在数据库查不到！';
            return $return;
        }
        $suppliers = $this->getAll("/*master*/select produce_order_id,supplier_id,stored_num,is_delete,category,order_price,currency from ".$this->tablePrefix.'produce_order'." where 1=1 and ".'produce_order_id IN(' . join(',', $ids) . ')' ." and status=9 and bill_id =0 and supplier_id !=''");// 查询所有的供应商
        $supplierIds = array_column($suppliers,'supplier_id'); 
        //供应商统一使用supplier_id获取
        $supplierIds = unique($supplierIds);

        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        if($suppliers){
            $ids = array();
            $supplier_linkmans = array();
            $msg = '';
            $no_company_order='';//没有公司名称订单
            $no_category='';
            $no_vmi='';
            $no_stored_num = '';
            $no_order_price = '';
            foreach($suppliers as $supplier){
                $supplier['factory'] = $supplierInfo[$supplier['supplier_id']]['title'];
                if(empty($supplier['stored_num'])){
                    $no_stored_num .= $supplier['produce_order_id'].',';
                    continue;
                }else{
                    $stored_num_arr = array();
                    $stored_num_arr = explode('<br/>',$supplier['stored_num']);
                    $is_not_zero = false;
                    foreach($stored_num_arr as $stored_num_arr_val){
                        $stored_num_arr_val_temp = explode(':',$supplier['stored_num']);
                        if($stored_num_arr_val_temp[1]>0){
                            $is_not_zero = true;
                        }
                    }
                    if(!$is_not_zero){
                        $no_stored_num .= $supplier['produce_order_id'].',';
                        continue;
                    }
                }
                //
                if(empty($supplier['order_price'])){
                    $no_order_price .= $supplier['produce_order_id'].',';
                    continue;
                }
                if($supplier['factory']){
                    if($supplier['is_delete']==1){
                        $msg .= $supplier['produce_order_id'].',';
                    }else{
                        $arr = array(array('supplier_id'=>$supplier['supplier_id']));
                        $arr = D('Prepare')->getSupplierLinkman($arr);//根据id获取供应商
                        $cate_name_arr = D('Purchase')->getSupplierCat($arr);
                        // 获取一级分类名判断是否属于美国仓
                        if(!isset($cate_name_arr[0]['first_category_name']) || ($cate_name_arr[0]['first_category_name'] == '美国仓')){
                            $res = R('Bill/addBill', array('data_arr' => array('supplier_linkman'=>$supplier['factory'],'category'=>$supplier['category'],'currency'=>$supplier['currency']), 'type' => 7, 'use_trants'=>false));//美国C区西部生产账单
                        }else{
                            $categories = array($this::ORDER_CATEGORY_OFFLINE,$this::ORDER_CATEGORY_ODM,$this::ORDER_CATEGORY_FOB,$this::ORDER_CATEGORY_CMT);
                            //线上单生成账单以已分单时间为准，其他账单已送货日期为准
                            if($supplier['category']==1){//线上单
                                $status_where =  'status=14 and produce_order_id='.$supplier['produce_order_id'];//已分单
                            }else{//FOB,CMT,ODM,新CMT,线下单其他均以已送货时间为准
                                $status_where =  'status=65 and produce_order_id='.$supplier['produce_order_id'];//已送货
                            }
                            $purchase_time = M('produce_order_status')->where($status_where)->order('produce_order_status_id desc')->field('add_time')->find();
                            if($purchase_time){
                                $purchase_date = !empty($purchase_time['add_time']) ? strtotime(date('Y-m-d',$purchase_time['add_time'])) : 0;
                            }else{
                                //老数据,以收货时间
                                $status_where =  'status=12 and produce_order_id='.$supplier['produce_order_id'];//已送货
                                $purchase_time = M('produce_order_status')->where($status_where)->order('produce_order_status_id desc')->field('add_time')->find();
                                $purchase_date = !empty($purchase_time['add_time']) ? strtotime(date('Y-m-d',$purchase_time['add_time'])) : 0;
                            }
                            $res = R('Bill/addBill', array('data_arr' => array('supplier_linkman'=>$supplier['factory'],'category'=>$supplier['category'],'date'=>$purchase_date,'partner_supplier_bill'=>$partner_supplier_bill,'currency'=>$supplier['currency']), 'type' => 1, 'use_trants'=>false));//广州B区生产账单
                        }
                        if($res['success']==1){
                            $bill = $res['content'];
                            if($bill['bill_status'] == 3){// 新账单
                                $supplier_linkmans[$supplier['factory']] = $bill['bill_id'];
                            }else{
                                $return['msg'] = "加工厂".$supplier['factory']."的账单已审核或者已付款";
                                return $return;
                            }
                            $this->execute("update ".$this->tablePrefix.'produce_order'." set bill_id=".$supplier_linkmans[$supplier['factory']]." where supplier_id='".$supplier['supplier_id']."' and produce_order_id=".$supplier['produce_order_id']);
                            //生成账单同时产生操作记录
                            M('produce_order_status')->add(array(
                                'produce_order_id' => $supplier['produce_order_id'],
                                'status' => 67,
                                'user_name' => I('session.admin_name'),
                                'add_time' => time()
                            ));
                        }elseif($res['success']==2){
                            $no_company_order.= $supplier['produce_order_id'].',';
                        }elseif($res['success']==3){
                            $no_category.= $supplier['produce_order_id'].',';
                        }elseif($res['success']==4){
                            $no_vmi.= $supplier['produce_order_id'].',';
                        }elseif($res['success']==5){//大货QC，入仓PAD，不生成账单直接返回空
                             continue;
                        }else{
                            $return['msg'] = "添加账单失败: " . $res['message'];
                            return $return;
                        }
                    }

                }
            }
            $return['status'] = 0;
            $temp_msg='';
            $temp_company='';
            $temp_category='';
            $temp_vmi='';
            if($no_stored_num){
                $temp_msg = $no_stored_num.'订单编号无入仓数量或为0';
            }
            if($no_order_price){
                $temp_msg = $no_order_price.'订单编号无实价，生成账单失败';
            }
            if($msg){
                $temp_msg = $msg.'订单编号已被删除，请先在已删除中恢复订单编号';
            }
            if($no_company_order){
                $temp_company =$no_company_order.'未能获取到公司名称，请联系采购部门';
            }
            if($no_category){
                $temp_category =$no_category.'未能获取到供应商分类';
            }
            if($no_vmi){
                $temp_vmi =$no_vmi.'属于VMI账单，请联系采购部';
            }
            if($temp_company==''&&$temp_msg==''&&$temp_category==''&&$temp_vmi==''){
                $return['success']=1;
                $return['msg'] = '批量操作成功';
            }else{
                $return['success']=0;
                $return['msg'] = $temp_msg.$temp_company.$temp_category.$temp_vmi;
            }
        }else{
            $return['success']=0;
            $return['msg'] = "没有可生成账单的订单";
        }
        return $return;
    }

    /**
     * 对生产订单的批量操作（批量删除和恢复）
     * @access public
     * @param $ids 传入id的数组 $type传入的is_delete要修改的状态
     * @return array
     * @author 韦俞丞 2015-12-04 10:22:19
     * @modify 田靖 2016-08-30 16:45:53 批量删除时需要判断是否生成过账单
     */
    public function batchProduceOrderIsDelete($ids, $type){

        $ids_count = count($ids);// 传入id个数
        $return = array('msg' => '', 'status' => 1, 'link'=>array());
        $produce_order = $this->table($this->tablePrefix.'produce_order')->where('produce_order_id IN(' . join(',', $ids) . ')')->field('produce_order_id,status,category,bill_id')->select();
        if(count($produce_order) != $ids_count){
            $return['msg'] = '无此项目';
            return $return;
        }
        $msg = '';
        foreach ($produce_order as $key => $value) {
            if($value['bill_id'] ){
                $k = array_search($value['produce_order_id'], $ids);
                unset($ids[$k]);
                $msg .= $value['produce_order_id'].',';
            }
        }
        if(count($ids)!=0){
            $row = $this->isdeleteProduceOrder($ids,$type);
        }
        if($row['code'] ==0){
            if($msg){
                $return['msg'] = $msg.'订单编号已生成账单不可删除，请核实';
            }else{
                $return['msg'] = '批量操作成功';
            }
            $return['status'] = 0;
        }else{
            $return['msg'] = $row['msg'];
            $return['status'] = 1;
        }
        return $return;
    }

    /*
     * 根据sku，获取这个sku最近10个新帐单的实价
     * @access public
     * @param  $goods_sn
     * @return $array
     * @author 韦俞丞 2016-04-14 16:45:38
     * @modify 陈东 2017-5-30 09:13:32 供应商统一使用supplier_id获取
     */
    public function queryBilledRealprice($goods_sn) {
        if (!$goods_sn) {
            return array('success' => 0, 'message' => '参数 $goods_sn 为空');
        }
        $sql = " SELECT produce_order_id, category, supplier_id, order_price, a.bill_id, b.user_name, b.add_time FROM " . $this->tablePrefix . "produce_order  AS a LEFT JOIN " . $this->tablePrefix . "bill_status AS b on a.bill_id = b.bill_id WHERE a.goods_sn = '" . $goods_sn . "' AND category IN ( 3, 4, 7 ) AND a.status = 9 AND a.bill_id != 0 AND a.is_delete = 0 AND b.bill_status = 3 ORDER BY b.add_time DESC LIMIT 10 ";
        $res = $this->getAll($sql);

        $supplierIds = array_column($res,'supplier_id');
        //供应商统一使用supplier_id获取
        $supplierIds = unique($supplierIds);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];

        if ($res === false) {
            return array('success' => 0, 'message' => '执行查询时数据库报错');
        } elseif ($res === array()) {
            return array('success' => 0, 'message' => '未能从 ' . $goods_sn . ' 的已生成账单的订单中查到实价');
        }
        foreach ($res as $k => $order) {
            $res[$k]['factory'] = $supplierInfo[$order['supplier_id']]['title'];
            $res[$k]['add_time'] = date('Y-m-d H:i:s', $order['add_time']);
        }

        return array('success' => 1, 'content' => $res);
    }

    /**
     * 更新订单实价
     * @access public
     * @param  $data array('produce_order_id' => 123, 'order_price' => '123.45')
     * @return array
     * @author 韦俞丞 2016-04-18 18:14:50
     * @modify 李永旺 2017-12-21 11:00:00 添加数据到实价修改记录表
     */
    public function saveProduceOrderPrice($data) {
        $id = $data['produce_order_id'];// 生产订单id
        $price = floatval($data['order_price']);// 新实价
        $old_order_price='';
        if (is_numeric($data['old_order_price'])) {
           $old_order_price=$data['old_order_price'];//接收原实价
        }elseif(!is_numeric($data['old_order_price'])){
           $old_order_price=str_replace(array('$','￥'),'',$data['old_order_price']);//接收原实价,并去除币种符号
        }
        
        unset($data['old_order_price']);//删除接收到数组中的原实价,因为表中无此字段，会导致事务执行失败
        if (!$id || $price < 0) {
            return array('success' => 0, 'message' => '模型参数错误');
        }
        $cond = array(
            'field' => 'goods_sn, category,order_price',
            'table' => $this->tablePrefix . 'produce_order',
            'where' => array('produce_order_id' => $id),
            'limit' => 1
        );
        $res = $this->select($cond);
        $category=$res[0]['category'];
        if($res[0]['order_price']==$price){
            return array('success' => 1, 'content' => $price,'is_old'=>1);
        }

        //校验价格是否大于商品中心的采购成本
        if($res[0]['category']!=3&&$res[0]['category']!=4){
            try{
                $goods_cost = D('InventoryManage')->getCurlGoodsCost($res[0]['goods_sn']);
            }catch (\Exception $e){
                $content = array(
                    'content' => $e->getMessage() . PHP_EOL . '订单ID: ' . $data['produce_order_id'].'请求商品中心失败'
                );
                Log::err('/saveProduceOrderPrice',$content);
                return array('success' => 0, 'message' => '未获取到商品中心成本价格');
            }
            if($goods_cost['data'][0]['cost']<$price){
                return array('success' => 0, 'message' => '修改后的实价不可高于SKU成本');
            }
        }
        $goods_sn = $res[0]['goods_sn'];// 这个生产订单的goods_sn
        $category = $res[0]['category'];// 分单类型
        if (in_array($category, array(3, 4))) {// FOB 单或 OEM 单，检查实价是否超过限制
            $cost_role = R('Privilege/checkRoleBool',array('checkman_edit_cost'));//判断是否有核价师权限
            if(!$cost_role){
                $res = D('ProduceProject')->getProcessingfee($goods_sn, $category,1);// 获取核价中心报价
                $limit = $res['content'];
                if ($limit > 0 && $price > $limit) {
                    return array('success' => 0, 'message' => '大货价大于成本核价，请与核价师核实');
                }
            }
        }
        $this->startTrans();// 开启事务
        $commit_data = array();
        $commit_data[] = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$id)->save($data);
        $add_data = array(
            'produce_order_id' => $id,
            'status' => 19,
            'user_name' => I('session.admin_name'),
            'add_time' => time()
        );
        $commit_data[] = $this->table($this->tablePrefix.'produce_order_status')->add($add_data);
        $status_str=0;
        if ($old_order_price=='') {
            $status_str=1;
        }else{
            $status_str=19;
        }
        // 添加数据到实价修改记录表 $status_str值为0:默认状态,1:创建实价,19:修改实价;
        $add_data_order_price_status = array(
            'produce_order_id' => $id,
            'old_order_price' =>$old_order_price,
            'new_order_price'=>$data['order_price'],
            'status' => $status_str,
            'user_name' => I('session.admin_name'),
            'add_time' => time()
        );
        $commit_data[] = $this->table($this->tablePrefix.'produce_order_price_status')->add($add_data_order_price_status);
        $res = db_commit($commit_data);// 提交事务
        if ($res) {
            $update_fields=array();
            $update_fields=array(
                'produce_order_id' => $id,
                'order_price'=>$data['order_price'],
            );
            $this->setMesByCmtOrder($id,$category,'update',$update_fields);//修改实价时候推送订单信息到mes系统
            return array('success' => 1, 'content' => $res);
        } else {
            return array('success' => 0, 'message' => '事务执行失败');
        }
    }

    /**
     * 生产订单信息上传数据库
     * @access public
     * @param  $data 获取的excel数据
     * @return array
     * @author 田靖 2016-05-11 13:33:59
     */
    public function produceOrderUpload($data){
        $res = array(//初始化返回值
                'success'=>0,
                'message'=>'',
                'content'=>''
            );
        if(empty($data)){
            $res['message'] = '数据表为空，请核实';
            return $res;
        }
        $fail = '';//初始化
        $total = 0 ;
        foreach ($data as $key => $value) {
            $data_arr = array();
            if($key != 1){//如果不是第一行就进行计算
                if($value[1]){//如果produce_order_id存在
                    $data_arr['where'] = array('produce_order_id'=>$value[1]);
                    $data_arr['field'] = 'category';
                    $category = $this->getProduceOrder($data_arr);
                    if($category[0]['category'] == 4){//如果是oem单
                        $save = array(
                                'fabric_cost'=>$value[2],
                                'real_price'=>$value[3],
                            );
                    }else{
                        $save = array('real_price'=>$value[4]);
                    }
                    $return = $this -> table($this->tablePrefix.'produce_order')->where($data_arr['where'])->data($save)->save();
                    if($return === 0){
                        $fail .= $key.',';
                    }else{
                        $total ++;
                    }
                }else{
                    $fail .= $key.',';
                }
            }
        }
        //构建返回值
        if($total){
            $res['success'] = 1;
            $res['message'] = '共有'.$total.'条数据更新成功';
            if($fail){
                $fail = substr($fail,0,strlen($fail)-1);
                $res['message'] .= ',excel表中第'.$fail.'行数据更新失败';
            }
        }else{
            $res['message'] = '数据更新失败';
        }
        return $res;
    }

    /**
     * 导出物料
     * @access public
     * @param  string $str_ids 物料字符串
     * @author 葛振 2016-06-14 20:20:30
     * @modify 李永旺 2018-01-12 09:40:01 大货价后面增加一级品名
     */
    public function produceFabricExport($str_ids=''){
        if($str_ids){
            $fabric_arr=array();
            $first_arr=explode(';',$str_ids);
            $end_arr=array();
            $num=0;
            $fabric_name= array('1'=>'面料','2'=>'辅料','3'=>'里料','4'=>'成衣','5'=>'二次工艺-外发','6'=>'二次工艺-内部');
            foreach($first_arr as $k=>$v){
                $second_arr=explode(',',$v);
                $end_arr[$k]['produce_order_id']=$second_arr['1'];
                $end_arr[$k]['goods_sn']=$second_arr['0'];
            }
            foreach ($end_arr as $key => $value) {
                $data['produce_order_id'] = $value['produce_order_id'];//下单跟进ID
                $data['goods_sn'] = $value['goods_sn'];//sku
                $res=$this->produceOrderFabricList($data);
                foreach($res['content'] as $k1=>$v1){
                     $fabric_arr[$num]['produce_order_id']=$value['produce_order_id'];//订单编号
                     $fabric_arr[$num]['prepare_type']=$v1['order_info']['prepare_type'];// 备货类型
                     $fabric_arr[$num]['goods_sn']= $value['goods_sn'];//sku
                     $fabric_arr[$num]['supplier']= $v1['supplier'];//供应商
                     $fabric_arr[$num]['address']= $v1['address'];//地址
                     $fabric_arr[$num]['telephone']= $v1['telephone'];//联系电话
                     $fabric_arr[$num]['big_price']= $v1['big_price'];//大货价
                     $fabric_arr[$num]['name']= $fabric_name[$v1['name']];//一级品名
                     $fabric_arr[$num]['assembly']= $fabric_name[$v1['name']].'/'.$v1['huo_hao'].'/'.$v1['color_number'];//名称类型/货号/色号
                     $fabric_arr[$num]['color']= $v1['color'];//颜色
                     $fabric_arr[$num]['wide']= $v1['wide'];//幅宽
                     $fabric_arr[$num]['dosage_total']= $v1['dosage_total'];//总用量
                     $fabric_arr[$num]['crafts']= $v1['crafts'];//总用量
                     $num++;
                }
            }
            $filename = "物料单-" . date ( "Y-m-d-H-i-s" ) . ".xls";
            header ( "Content-type:application/vnd.ms-excel" );
            header ( "Content-Type: application/download" );
            header ( "Content-Disposition:filename=$filename" );
            echo "订单编号\t";
            echo "SKU\t";
            echo "供应商\t";
            echo "地址\t";
            echo "联系电话\t";
            echo "大货价\t";
            echo "一级品名\t";
            echo "名称类型/货号/色号\t";
            echo "工艺\t";
            echo "颜色\t";
            echo "幅宽\t";
            echo "总用量\t";
            echo "\n";
            foreach ( $fabric_arr as $ke => $ve ) {
                switch ( $ve['prepate_type'] ) {
                    case 3:
                        $prefix = "DZ";
                        break;
                    case 5:
                        $prefix = "Y";
                        break;
                    default:
                        $prefix = "";
                }
                echo $prefix . $ve['produce_order_id'] . "\t";
                echo $ve['goods_sn']. "\t";
                echo $ve['supplier'] . "\t";
                echo $ve['address'] . "\t";
                echo $ve['telephone'] . "\t";
                echo $ve['big_price'] . "\t";
                echo $ve['name'] . "\t";
                echo $ve['assembly'] . "\t";
                echo $ve['crafts'] . "\t";
                echo $ve['color'] . "\t";
                echo $ve['wide'] . "\t";
                echo $ve['dosage_total'] . "\t";
                echo "\n";
            }
            die;
        }
    }


    /**
     * 更新订单跟进中的入仓数量（累加的方式）
     * @access public
     * @param  int $produce_order_id 生产订单id
     * @param  array $data ['周转箱号1'=>['尺码1'=> 数量1,'尺码2'=> 数量2], ...]
     * @param  boolean $completed 默认不变成已完成状态
     * @param  boolean $partner_supplier_bill  市场大货QC、PDA入仓登记提交判断：如供应商类型为“伙伴型”，生成待上架数据但不生成账单；反之则允许上架待上架数据并生成账单。
     * @return array
     * @author 薛升 2016-6-13 18:49:46
     * @modify 陈东 2017-5-30 09:13:32 供应商统一使用supplier_id获取
     */
    public function updateProduceOrderStoredNum($produce_order_id, $data, $completed = false,$partner_supplier_bill=true){
        if (empty($produce_order_id)) {
            return array(
                'success' => 0,
                'message' => '参数非法',
                'errcode' => 1
            );
        }

        if (empty($data)) {
            return array(
                'success' => 0,
                'message' => '无更新的数据',
                'errcode' => 2
            );
        } else {
            //验证周转箱是否有效
            foreach ($data as $turnover_no => $v){
                $turnover_no_tmp = trim($turnover_no);
                if (!is_string($turnover_no_tmp) || empty($turnover_no_tmp)) {
                    return array(
                        'success' => 0,
                        'message' => '周转箱未填，请核实',
                        'errcode' => 6
                    );
                }
                unset($data[$turnover_no]);
                $data[$turnover_no_tmp] = $v;
            }
        }

        //先取得已有的入仓数量，到货数量
        $where = array('produce_order_id' => $produce_order_id);
        $res = M('produce_order')->where($where)->field('stored_num,bill_id,category,received_info,order_info,status,prepare_type')->find();
        $produce_order_info = $res;
        if (empty($res['received_info'])) {
            return array(
                'success' => 0,
                'message' => '没有到货数量',
                'errcode' => 6
            );
        }
        if($res['category']==2||$res['category']==6){//线下单 状态需要等于已完成9状态
            if($res['status']!=9&&$res['bill_id']==0){
                return array(
                    'success' => 0,
                    'message' => '请确定订单处于已完成状态',
                    'errcode' => 5
                );
            }

        }else{
            if($res['status']!=12&&$res['bill_id']==0){//其他单 需要等于已收货状态
                return array(
                    'success' => 0,
                    'message' => '请确定订单处于已收货状态',
                    'errcode' => 5
                );
            }
        }
        if($res['bill_id']!=0){//如果是已生成账单 修改入仓数量 需要判断权限
            if(! R('Privilege/checkRoleBool',array('bills_rucang_num_changes'))){
                return array(
                    'success' => 0,
                    'message' => '无权限，请联系部门负责人',
                    'errcode' => 6
                );
            }
        }
        //以下代码将'S:5<br/>M:5<br/>L:5<br/>' 转成 ['S'=>5,'M'=>5,'L'=>5]
        $stored_old_num = explode('<br/>', rtrim($res['stored_num'], '<br/>')); //入仓数量
        $received_num = explode('<br/>', rtrim($res['received_info'], '<br/>')); //到货数量

        //已经存在的入仓数量
        $stored_old_num_arr = array();
        foreach ($stored_old_num as $value){
            if(!empty($value)){
                $cuu_arr = explode(":", $value);
                $stored_old_num_arr[$cuu_arr[0]] = $cuu_arr[1];
            }
        }
        //新入仓数量，计算所有周转箱下的相同尺码的数量
        $stored_new_num_arr = array();
        foreach ($data as $item) {
            foreach ($item as $attr => $num) {
                if (array_key_exists($attr, $stored_new_num_arr)) {
                    $stored_new_num_arr[$attr] += $num;
                } else {
                    $stored_new_num_arr[$attr] = $num;
                }
            }
        }

        //计算所有的入仓数量
        $total_stored_num = array(); //总入仓数量
        //取出旧的入仓数量和新的入仓数量中的所有属性
        $all_attrs = array_unique(array_merge(array_keys($stored_old_num_arr), array_keys($stored_new_num_arr)));

        foreach ($all_attrs as $attr) {
            $old = isset($stored_old_num_arr[$attr]) ?  $stored_old_num_arr[$attr] : 0;
            $new = isset($stored_new_num_arr[$attr]) ?  $stored_new_num_arr[$attr] : 0;
            $sum = $old + $new;
            if($sum < 0){
                return array(
                    'success' => 0,
                    'message' => '入仓数量不能小于0',
                    'errcode' => 3
                );
            }
            //去除所有为0且不是数值的字段
            if (empty($sum)) { continue; }
            $total_stored_num[$attr] = $sum;
        }

        //到货数量
        $received_num_arr = array();
        foreach($received_num as $value){
            if(!empty($value)){
                $received_arr = explode(":", $value);
                $received_num_arr[$received_arr[0]] = $received_arr[1];
            }
        }

        //次品数量
        $res = M('produce_order_qc_report')->where($where)->field('defective_num')->find();
        $defective_num = explode('&lt;br/&gt;', $res['defective_num']);
        $defective_num_arr = array();
        foreach($defective_num as $vs){
            if(!empty($vs)){
                $defective_arr = explode(":", $vs);
                $defective_num_arr[$defective_arr[0]] = $defective_arr[1];
            }
        }

        //判断入仓数量和次品数量的和是否超出到货数量
        foreach ($all_attrs as $attr) {
            $sum = $total_stored_num[$attr] + $defective_num_arr[$attr];
            if ($sum > $received_num_arr[$attr]) {
                return array(
                    'success' => 0,
                    'message' => '入仓数量和次品数量的和超出到货数量',
                    'errcode' => 4
                );
            }
        }

        //生成字符格式的入仓数量 S:5<br/>M:5<br/>L:5<br/>
        $total_stored_num_data = '';
        foreach ($total_stored_num as $k=>$item) {
            $total_stored_num_data .= $k . ':' . $item . '<br/>';
        }

        //添加入仓数量时,在账单数据列表新增等同产品与数量信息
        $res = M("produce_order")->where('produce_order_id='.$produce_order_id)->field()->find();

        //供应商统一使用supplier_id获取
        $supplierIds = array($res['supplier_id']);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        $res['factory'] = $supplierInfo[$res['supplier_id']]['title'];

        $del_data = array(); //删除数据
        $add_data = array(); //增加数据
        if ( in_array($res['prepare_type'], array( 0, 4, 5 ))) { // 普通备货、特殊备货、亚马逊备货，添加账单数据列表
            foreach ($data as $turnover_no => $size) {
                    foreach ($size as $attr => $num) {
                        $num = intval($num);
                        if($num < 0) { //删除分支
                            for ($j = 0; $j > $num; $j--) {
                                $one_data = array();
                                $one_data['produce_order_id'] = $produce_order_id;
                                $one_data['goods_sn'] = $res['goods_sn'];
                                if($attr == '数量'){// 处理下单跟进那边SKU没有属性的情况
                                    $one_data['goods_attr'] = '';
                                }else{
                                    $one_data['goods_attr'] = 'Size:'.$attr.'<br />';
                                }
                                $one_data['is_delete']=array('neq',1);
                                $one_data['new_turnover_no'] = $turnover_no; //增加周转箱号字段
                                $one_data['warehouse'] = array('IN', '0,1'); //条件限制默认值0和广州仓库类型值1
                                $del_data[] = $one_data;

                            }
                        } elseif ($num > 0) { //增加分支
                            for($i = 0; $i < $num; $i++){
                                $one_data = array();
                                $one_data['produce_order_id'] = $produce_order_id;
                                $one_data['goods_thumb'] = $res['goods_thumb'];
                                $one_data['goods_sn'] = $res['goods_sn'];
                                if($attr == '数量'){// 处理下单跟进那边SKU没有属性的情况
                                    $one_data['goods_attr'] = '';
                                }else{
                                    $one_data['goods_attr'] = 'Size:'.$attr.'<br />';
                                }
                                $one_data['supplier_linkman'] = $res['factory'];// 供应商是加工厂信息,确保库存列表中生产商信息是准确的
                                $one_data['add_time'] = time();
                                $one_data['admin_name'] = I('session.admin_name');
                                $one_data['new_turnover_no'] = $turnover_no;//增加周转箱号字段
                                $one_data['warehouse'] = 1; //广州仓库类型值1
                                $one_data['type'] = 1; ////生成待上架数据的类型：大货（大货QC）
                                $add_data[] = $one_data;
                            }

                        }
                    }
            }
        }
        //保存数据
        $this->startTrans();// 事务开启
        $commit_data = array();

        //保存入仓数量，如果$completed为true, 则将生产订单置为已完成状态
        $save_data = array('stored_num' => $total_stored_num_data);
        if ($completed) { $save_data['status'] = 9; } //已完成状态
        $commit_data[] = M('produce_order')->where('produce_order_id='.$produce_order_id)->save($save_data);
        //保存操作记录
        $save_data = array();
        $save_data[] = array(
            'produce_order_id' => $produce_order_id,
            'status' => 16,
            'user_name' => I('session.admin_name'),
            'add_time' => time(),
        );
        if ($completed) { //增加已完成状态的操作记录
            $save_data[] = array(
                'produce_order_id' => $produce_order_id,
                'status' => 9, //已完成
                'user_name' => I('session.admin_name'),
                'add_time' => time(),
            );
        }
        $commit_data[] = M('produce_order_status')->addAll($save_data);
        if(in_array($produce_order_info['prepare_type'],array(1,2))){
            $res_set_fba = R("InventoryAdjust/updateFbaOrderByProduce",array($produce_order_id));// 更新fba单
            if($res_set_fba['success']){
                $commit_data[] = true;
            }else{
                $commit_data[] = false;
            }
        }
        //账单数量列表的更新
        //删除账单数据列表数据
        foreach ($del_data as $item) {
            $commit_data[]=R('Inventory/delOneInventoryTemp',array($item, array('id')));//调用库存接口插入数据到账单数据列表中
        }

        //增加账单数据列表数据
        if ($add_data) {
            $commit_data[] = R('Inventory/insertBillStatistic',array($add_data));
        }

        //提交事务
        if (db_commit($commit_data)) {
            $add_bill = $this->batchProduceOrderBill(array($produce_order_id));//当状态是已完成时，添加新账单
            if(!$add_bill['success']){
                return array(
                    'success' => 2,
                    'message' => '数据添加成功,'.$add_bill['msg'].',账单添加失败',
                    'errcode' => 8,
                    'content' => array(
                        'produce_order_id' => $produce_order_id,
                        'filed' => 'stored_num',
                        'data' => $data,
                        'prepare_type'=>$res['prepare_type']
                    )
                );
            }else{
                return array(
                    'success' => 1,
                    'message' => '',
                    'content' => array(
                        'produce_order_id' => $produce_order_id,
                        'filed' => 'stored_num',
                        'data' => $data,
                        'prepare_type'=>$res['prepare_type']
                    )
                );
            }

        } else {
            return array(
                'success' => 0,
                'message' => '数据保存失败',
                'errcode' => 5
            );
        }

    }

    /**
     * PDA入仓登记
     * @param int $produce_order_id 生产单号
     * @param array $data 入仓登记数据
     * @return array
     * @author 薛升 2016-6-27 13:30:23
     * @modify 韦俞丞 2016-09-01 15:13:45 区分入仓之后是否变为已完成，并且强制读主库
     */
    public function pdaStoredCheckin($produce_order_id, $data) {
        $res = $this->updateProduceOrderValue($produce_order_id, 'stored_num', $data);
        if (!$res['success']) {
            return array(
                'success' => 0,
                'message' => '入仓登记失败',
                'errcode' => 1,
            );
        }
        //验证入仓数量+次品数量是否等于到货数量
        $isequal = true; //默认相等，则不需要自动将生产单由已收货变成已完成状态
        $where = array('produce_order_id' => $produce_order_id);
        $sql = "/*master*/SELECT stored_num, received_info FROM " . $this->tablePrefix . "produce_order WHERE produce_order_id = " . $produce_order_id . " LIMIT 1 ";
        $res = $this->getRow($sql);
        $received_info = attr_num_convert($res['received_info']); //到货数量
        $stored_num = attr_num_convert($res['stored_num']); //入仓数量
        $res = M('produce_order_qc_report')->where($where)->field('defective_num')->find();
        $res['defective_num'] = html_entity_decode($res['defective_num']);// 将html实体解码
        $defective_num = attr_num_convert($res['defective_num']); //次品数量
        foreach ($received_info as $attr => $num) {
            $sum = $defective_num[$attr] + $stored_num[$attr];
            if ($sum != $num) { //循环判断到货的每个属性数量是否等于入仓和次品对应属性数量的总和
                $isequal = false;
                break;
            }
        }
        if ($isequal) {
            $status_info = $this->updateProduceOrder($produce_order_id,9,'',true); //9为已完成状态
            if($status_info['status'] === '1'){
                return array(
                    'success' => 0,
                    'message' => $status_info['msg'],
                    'errcode' => 2,
                );
            } else {
                return array(
                    'success' => 1,
                    'content' => 1,// 入仓登记成功，订单状态从已收货变为已完成
                );
            }
        } else {
            return array(
                'success' => 1,
                'content' => 2,// 入仓登记成功，不满足条件，所以订单状态不变
            );
        }
    }

    /**
     * 根据周转箱号，统计属于该箱号下的所有账单数据列表的数量(调用接口)
     * @access public
     * @param string $turnover_no 周转箱号
     * @return void
     * @author 薛升
     */
    public function getInventoryBillNumWithTurnoverNo($turnover_no = '')
    {
        $num = R('Inventory/getInventoryBillNumWithTurnoverNo', array($turnover_no));// 调用商品模块的接口
        return $num;
    }
    /**
     * 接口，提供给订单模块，用于获取生产信息
     * @access public
     * @param array
     * @return array
     * @author 陈东 2016-8-2 09:26:55
     * @modify 陈东 2017-5-30 09:13:32 供应商统一使用supplier_id获取
     */
    public function getProduceOrderToOrder($data){
        $check_arr = array('billno','goodsn','orderGoodsId');
        foreach($check_arr as $key=>$value){
            if(empty($data[$value])){
                return array('success'=>0,'message'=>'存在参数为空，请核对!');
            }
        }
        $return_arr = array(
            'orderGoodsId'=>$data['orderGoodsId'],  //商品id
            'billno'=>$data['billno'],  //订单编号
            'goodsn'=>$data['goodsn'],  //商品sku
            'produceOrderId'=>'',  //下单编号
            'purchaser'=>'', //采购员
            'supplier'=>'',  //供应商
            'quality'=>'',     //质检员
            'addTime'=>0
        );
        if(trim($data['inventoryId'])==''||$data['inventoryId']==0){
            return array('success'=>1,'content'=>$return_arr);
        }
        //根据库存id搜索库存信息
        $where_arr = array(
            'where'=>array(
                'inventory_id'=>$data['inventoryId'],
                'is_delete'=>0,
                'inventory_status'=>array('in','2,3,4')
            )
        );
        $inventory_info = R('InventoryOpenAPI/getInventoryByWhere',array($where_arr));
        //如果周转箱号为空，直接返回空信息
        if(empty($inventory_info)||empty($inventory_info[0]['turnover_no'])){
            return array('success'=>1,'content'=>$return_arr);
        }
        //根据周转箱号查询账单数据列表数据,取最新的一条
        $inventory_temp_info = R('InventoryOpenAPI/getInventoryTempByWhere',array(
            array(
                'where'=>array(
                    'new_turnover_no'=>$inventory_info[0]['turnover_no'],
                    'goods_sn' => $inventory_info[0]['goods_sn'],
                    'goods_attr' => $inventory_info[0]['goods_attr'],
                    'is_delete' => 1,
                    'add_time' => array('elt', strtotime($inventory_info[0]['add_time'])),
                ),
                'order'=>'add_time DESC',
                'limit'=>1
            )
        ));
        //没有关联到下单编号
        if(empty($inventory_temp_info)||empty($inventory_temp_info[0]['produce_order_id'])){
            $return_arr['quality'] = $inventory_temp_info[0]['admin_name'];
            return array('success'=>1,'content'=>$return_arr);
        }

        //根据下单编号获取生产数据
        $where = "produce_order_id=".$inventory_temp_info[0]['produce_order_id'];
        $query_arr = array('where'=>$where.' and is_delete=0',
                        'field'=>'category,fabric_purchaser,supplier_id,goods_sn,add_time',//新增查询下单时间
                        );
        $produce_order_info = $this->getProduceOrder($query_arr);

        //如果获取到的下单编号的sku跟库存的sku，直接返回
        if($produce_order_info[0]['goods_sn']!==$inventory_info[0]['goods_sn']){
            $return_arr['quality'] = $inventory_temp_info[0]['admin_name'];
            return array('success'=>1,'content'=>$return_arr);
        }
        $pass_type = array(1,2,6);
        if(in_array($produce_order_info[0]['category'],$pass_type)){
            $return_arr['quality'] = $inventory_temp_info[0]['admin_name'];
            $return_arr['produceOrderId'] = $inventory_temp_info[0]['produce_order_id'];
            $return_arr['supplier'] = $produce_order_info[0]['factory'];
            $return_arr['addTime'] = $produce_order_info[0]['add_time'];//返回新增下单时间（时间戳）
            return array('success'=>1,'content'=>$return_arr);
        }
        //获取质检报告信息
        $w = array(
            'produce_order_id' => $inventory_temp_info[0]['produce_order_id'],
        );
        $res = M('produce_order_qc_report')->where($w)->find();
        $report_id = $res['report_id'];
        $w = array(
            'report_id' => $report_id,
            'status' => 14,// 更新质检报告
        );
        $latest_update = M('produce_order_qc_report_status')->where($w)->order('id desc')->find();
        $checker = $latest_update['admin_name'];//获取质检人:操作记录为更新质检报告的最新的操作人

        $return_arr['purchaser'] = $produce_order_info[0]['fabric_purchaser'];
        $return_arr['supplier'] = $produce_order_info[0]['factory'];
        $return_arr['produceOrderId'] = $inventory_temp_info[0]['produce_order_id'];
        $return_arr['addTime'] = $produce_order_info[0]['add_time'];//返回新增下单时间（时间戳）
        $return_arr['quality'] = $checker;
        return array('success'=>1,'content'=>$return_arr);
    }
    /**
     * 接口，提供给订单模块，用于校验下单编号与sku是否一致
     * @access public
     * @param array $data example array array('produce_order_id'=>,'goods_sn'=>)
     * @return array
     * @author 陈东 2016-8-2 09:26:55
     */
    public function checkProduceOrderByIdAndSku($data){
        $where = "produce_order_id=".$data['produce_order_id']." and goods_sn='".$data['goods_sn']."'";
        $query_arr = array('where'=>$where);
        //获取生产订单信息
        $produce_order_info = $this->getProduceOrder($query_arr);
        if($produce_order_info){
            return array('success'=>1,'content'=>true);
        }else{
            return array('success'=>0,'message'=>'订单SKU与下单编号不符');
        }
    }
    /**
     * 预计回货时间或要求入库时间插入并生成操作记录
     * @access public
     * @param string $produce_order_id 订单编号
     * @param string $back_time 回货时间
     * @return array
     * @author 葛振
     * @modify 姚法强 2018-1-18 11:19:11 新增可以修改工厂交货时间
     */
    public function saveBackTime($produce_order_id,$back_time,$type){
        if(empty($produce_order_id)||empty($back_time)||empty($type)){
            return array(
                'success' => 0,
                'message' => '参数错误',
                'errcode' => 1,
            );
        }
        $res=array();
        $this->startTrans();
        if($type=='storage_time'){
            $data['storage_time']=strtotime($back_time);
        }elseif($type=='back_time'){
            $data['back_time']=strtotime($back_time);
        }elseif($type=='factory_work_time'){
            $data['factory_work_time']=strtotime($back_time);
        }else{
            return array(
                'success' => 0,
                'message' => '参数错误',
                'errcode' => 3,
            );
        }
        $res[]=M('produce_order')->where('produce_order_id='.$produce_order_id)->save($data);
        $data_remark['produce_order_id']=$produce_order_id;
        $data_remark['status'] = ($type=='storage_time')?25:23;
        $data_remark['user_name']=session('admin_name');
        $data_remark['add_time']=time();
        $res[]=M('produce_order_status')->data($data_remark)->add();
        if(db_commit($res)){
            return array(
                'success' => 1,
                'content' => '更新成功',
            );
        }else{
            return array(
                'success' => 0,
                'message' => '更新失败',
                'errcode' => 2,
            );
        }
    }


    /**
     * 获取produce_sale_other表数据
     * @param $data_arr
     * @return mixed
     * @author 陈东 2016-8-16 14:32:27
     */
    public function getProductSaleOther($data_arr){
        $where = isset($data_arr['where']) ? $data_arr['where'] : '';
        $field = isset($data_arr['field']) ? $data_arr['field'] : '';
        $order = isset($data_arr['order']) ? $data_arr['order'] : '';
        $limit = isset($data_arr['limit']) ? $data_arr['limit'] : '';
        return $this -> table($this->tablePrefix.'product_sale_other')->where($where)->order($order)->limit($limit)->field($field)->select();
    }

    /**
     * 向produce_sale_other表插数据
     * @param $add_data
     * @return array
     * @author 陈东 2016-8-16 14:32:27
     */
    public function addProductSaleOther($add_data){
        if(empty($add_data)){
            return array('success'=>0,'message'=>'参数为空');
        }
        $checked_arr = array(
                'goods_sn',
                'content_name',
                'time'
            );
        foreach ($checked_arr as $key => $value) {
           if(empty($add_data[$value])){
                return array('success'=>0,'message'=>$value.'为空');
           }
        }
        $res = M('product_sale_other')->add($add_data);
        if($res){
            return array('success'=>1,'content'=>'添加成功');
        }else{
            return array('success'=>0,'message'=>'添加失败');
        }
    }
    /**
     * 向prepare_status表插数据
     * @param $add_data
     * @return array
     * @author 陈东 2016-8-16 14:32:27
     */
    public function addPrepareStatus($add_data){
        if(empty($add_data)){
            return array('success'=>0,'message'=>'参数为空');
        }
        $checked_arr = array(
                'goods_id',
                'status',
                'user_name',
                'content',
                'add_time',
                'goods_sn',
                'type_status'
            );
        foreach ($checked_arr as $key => $value) {
           if(!isset($add_data[$value])){
                return array('success'=>0,'message'=>$value.'为空');
           }
        }
        $res = M('prepare_status')->add($add_data);
        if($res){
            return array('success'=>1,'content'=>'添加成功');
        }else{
            return array('success'=>0,'message'=>'添加失败');
        }
    }

    /**
     * 根据生产订单id，去inventory_defective次品表查次品数量
     * @access public
     * @param  array $id_arr 生产订单id数组
     * @return array
     * @author 韦俞丞 2016-09-23 09:20:11
     * @modify 葛振 2017-03-02 11:11:15 修复bug
     */
    public function getDefective($id_arr)
    {
        if (!$id_arr) {
            return array();
        }
        $where = array(
            'produce_order_id' => array('in', $id_arr),
            'category' => array('in', array(1, 2, 6)),// 线上单、线下单、ODM单
            'is_delete' => 0,
        );
        $id_arr = M('produce_order')->where($where)->getField('produce_order_id', true);
        $field = 'produce_order_id, goods_attr';
        $where = array(
            'produce_order_id' => array('in', $id_arr),
            'type' => 2,// 大货QC
        );
        $res = M('inventory_defective')->where($where)->field($field)->select();
        $arr = array();
        foreach($res as $v) {
            $order_id = $v['produce_order_id'];
            $attr = str_replace(array('Size:', '<br/>', '<br />'), '', $v['goods_attr']);
            if (isset($arr[$order_id][$attr])) {
                $arr[$order_id][$attr] += 1;
            } else {
                $arr[$order_id][$attr] = 1;
            }
        }
        return $arr;
    }
    /**
     * 获取箱数
     * @return array
     * @param $id int 订单ID
     * @author 葛振 2016-09-13 16:34:00
     */
    public function getProduceOrderBoxNumber($id){
        if(empty($id)){
            return array('success'=>0,'message'=>'参数为空');
        }
        $res=M('produce_order')->where(array('produce_order_id'=>$id))->getField('box_num');
        return array('success' => 1, 'content' =>$res);
    }
    /**
     * 打印箱数获取订单信息
     * @return array
     * @param $id int 订单ID
     * @author 葛振 2016-09-13 16:34:00
     * @modify 姚法强 2018-01-28 11:45:04 修改为可支持采购交接列表打印箱唛
     */
    public function printBoxNumberInfoById($id,$type=0){
        if(empty($id)){
            return array('success'=>0,'message'=>'参数为空');
        }
        if($type==1){
            $res= M('produce_order_rework_delivery_goods_info')->alias('a')->join('rs_produce_rework_deliver as b on a.deliver_id = b.id')->where(array('order_delivery_goods_id'=>$id))->find();
        }elseif($type==2){
            $res= M('produce_order_rework_delivery_goods_info')->alias('a')->join('rs_produce_deliver_detail as b on a.order_delivery_goods_id = b.box_id')->where(array('order_delivery_goods_id'=>$id))->find();
        }else{
            $res=M('produce_order')->where(array('produce_order_id'=>$id))->field('supplier_id,produce_order_id,box_num,goods_sn,is_urgent,order_info,received_info,prepare_type')->find();
        }
        if(empty($res)){
            return array('success'=>0,'message'=>'数据为空');
        }
        //获取最后一次送货时间
        if($type==2||$type==1){
            $produce_order_id=$res['produce_order_id'];
            $stored_time  = M('Produce_order_status')->WHERE('produce_order_id = '.$produce_order_id.' and status = 20')->order('add_time desc')->getField('add_time');
        }else{
            $stored_time  = M('Produce_order_status')->WHERE('produce_order_id = '.$id.' and status = 20')->order('add_time desc')->getField('add_time');
        }
        //页面供应商统一使用supplier_id获取
        if($type==2){
            $deliver=M('produce_deliver')->where(array('id'=>$res['deliver_id']))->find();
            $supplierIds = M('supplier_info')->where(array('id'=>$deliver['supplier_id']))->find();
            $res['factory'] = $supplierIds['title'];
        }else{
            $supplierIds = array($res['supplier_id']);
            //根据供应商名称获取供应商信息
//        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
            $supplierInfo = $this->getSupplierByIds($supplierIds);
            $res['factory'] = $supplierInfo['content'][$res['supplier_id']]['title'];
        }

        //判断是否是首单，如果不是首单就返单
        $is_first=$this->isFirstOrder($res['goods_sn'],'',$id);//返回值：首单或者空
        if(empty($is_first['data'])){
            $res['is_first']=0;//首单
            $res['is_back']=1;//返单
        }else{
            $res['is_first']=1;//首单
            $res['is_back']=0;//返单
        }
        //下单尺寸、数量
        if($type==1||$type==2){
            $size_info=explode('<br/>',$res['goods_attr']);
        }else{
            $size_info=explode('<br/>',$res['order_info']);
        }
        $size_info=array_filter($size_info);
        $size=array();
        $size_num_array=array();
        $size_sum=0;
        foreach($size_info as $key=>$value){
            $temp_size=explode(':',$value);
            $size[]=$temp_size[0];
            $size_num_array[]=$temp_size[1];
            $size_sum+=$temp_size[1];
        }
        $size[]="总数";
        $size_num_array[]=$size_sum;
        //送货数量produce_order_delivery_goods_info表数据
        $receive_detail_sum=0;
        $receive_detail =$this->getBoxSn($id,$type);
        if($type==1 && $receive_detail['error']==1){
            return array('success' => 0, 'content' =>'');
        }
        $b = '';
        $receive_detail_sum=array();
        $k_array=array();
        $receive_detail_info=array();
        foreach ($receive_detail['content'] as $k_d=>$v_d) {
            $a = explode('<br/>', $v_d['goods_attr']);
            $a=array_filter($a);
            foreach ($a as $k_a => $v_a) {
                    $temp_box_size = explode(':', $v_a);
                    $k_array[$v_d['order_delivery_goods_id']][$v_d['box_sn']][$temp_box_size[0]] = $temp_box_size[1];
                    if ($v_d['box_sn'] == $b) {
                        $receive_detail_sum[$v_d['order_delivery_goods_id']][$v_d['box_sn']] += $temp_box_size[1];
                    } else {
                        $b = $v_d['box_sn'];
                        $receive_detail_sum[$v_d['order_delivery_goods_id']][$v_d['box_sn']] = $temp_box_size[1];
                    }
                }
            }
        foreach ($size as $k_s=>$v_s){
            foreach($k_array as $k_k=>$v_v){
                foreach ($v_v as $k_3=>$v_3){
                    if(array_key_exists($v_s,$v_3)){
                        $receive_detail_info[$k_k][$k_3][$v_s]=$v_3[$v_s];
                    }elseif($v_s=="总数"){
                        $receive_detail_info[$k_k][$k_3]['sum']=$receive_detail_sum[$k_k][$k_3];
                    }else{
                        $receive_detail_info[$k_k][$k_3][$v_s]=0;
                    }
                }

            }
        }
        //送货数量
        $box_size_info=explode('<br/>',$res['received_info']);
        $box_size_info=array_filter($box_size_info);
        $pk_array=array();
        $box_sum=0;
        foreach($box_size_info as $k=>$v){
            $temp_box_size=explode(':',$v);
            $pk_array[$temp_box_size[0]]=$temp_box_size[1];
            $box_sum+=$temp_box_size[1];
        }
        $box_num_array=array();
        foreach($size as $key_size=>$val_size){
            if(array_key_exists($val_size,$pk_array)){
                $box_num_array[]=$pk_array[$val_size];
            }elseif($val_size=="总数"){
                $box_num_array[]=$box_sum;
            }else{
                $box_num_array[]='';
            }
        }
        //送货数量
        if(!empty($stored_time)){
            $res['stored_time']=date('m月d日',$stored_time);
        }
        $res['size']=$size;
        $res['date_time']=date('m月d日',$res['add_time']);
        $res['order_info']=$size_num_array;
        $res['received_info']=$box_num_array;
        $res['receive_detail_info']=array_filter($receive_detail_info);
        return array('success' => 1, 'content' =>$res);
    }
    /**
     * 更新箱数
     * @return array
     * @param $id int 订单ID
     * @author 葛振 2016-09-13 16:34:00
     */
    public function updateBoxNumber($id){
        if(empty($id)){
            return array('success'=>0,'message'=>'参数为空');
        }
        $redis=redises();
        $redis=$redis->redis();
        $box_redis_value=$redis->Get('produce_produceController_updateBoxNumber_'.$id);
        $num=0;
        if($box_redis_value===false){
            $res=M('produce_order')->where(array('produce_order_id'=>$id))->getField('box_num');
            $num=$res-1;
            $redis->Set('produce_produceController_updateBoxNumber_'.$id,$num);
        }else{
            $num=$redis->DECR('produce_produceController_updateBoxNumber_'.$id);//DECR 自减1
        }
            return array('success'=>1,'content'=>$num);
    }

    /**
     * 撤销订单紧急标记
     * @access public
     * @return array
     * @author 陈东 2016-10-31 11:04:45
     * @modify 李永旺 2017-12-19 11:00:53 修改取消紧急标记规则
     */
    public function cancelIsUrgent($produce_order_id){
        if(empty($produce_order_id)){
            return array('success'=>0,'message'=>'订单编号为空');
        }
        $this->startTrans();
        $commit_arr = array();
        $save_data=array();
        $save_data=array('is_urgent'=>0);



        $produce_order_info=M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->find();
        $supplier_id=$produce_order_info['supplier_id'];
        // 获取供应商一级分类名称
        $topCategoryName=D("ProduceProjectOrder")->getSupplierFirstCateGory($supplier_id);
        // 供应商一级分类是否为生产部,若为生产部预计收货时间:下单日期+生产总时效-2
        if ($topCategoryName=='生产部') {
              // 生产总时效 来自基础档案款式生产信息的生产总时效
                $goods_sn['goods_sn']=$produce_order_info['goods_sn'];
                $goods_sn['is_delete']=0;
                $produce_time = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
                  if($produce_time['produce_time']==''){
                      $produce_time['produce_time']=0;
                  }
            $back_time=$produce_order_info['add_time']+($produce_time['produce_time']-3)*86400;
            // M('produce_order')->where('produce_order_id='.$produce_order_id)->save(array('back_time'=>$back_time));
            if (!empty($back_time)) {
               $save_data=array('is_urgent'=>0,
                'back_time'=>$back_time);
            }
        }

        $commit_arr[] = M('produce_order')->where('produce_order_id='.$produce_order_id)->save($save_data);
        $status_arr = array();
        $status_arr['produce_order_id'] = $produce_order_id;
        $status_arr['status'] = 24;
        $status_arr['user_name'] = session('admin_name');
        $status_arr['add_time'] = time();
        $commit_arr[] = M('produce_order_status')->add($status_arr);
        if(db_commit($commit_arr)){
            return array('success'=>1,'content'=>'更新成功');
        }else{
            return array('success'=>0,'message'=>'更新失败');
        }
    }
    /**
     * 配料单打印
     * @access public
     * @param $produce_order_id int 订单ID
     * @param $produce_id int 产品ID
     * @param $goods_sn string sku
     * @return array
     * @author 葛振 2016-11-16 21:29:35
     * @modify 陈东 2017-5-25 11:00:53 页面供应商统一使用supplier_id获取
     */
    public function ingredientsListPrint($produce_order_id,$produce_id,$goods_sn){
        if(! R('Privilege/checkRoleBool',array('ingredients_list_print'))){
            return array('success'=>0,'message'=>'没有权限');
        }
        if($produce_order_id==''||$produce_id==''||$goods_sn==''){
            return array('success'=>0,'message'=>'参数为空');
        }
        $produce_order_id=rtrim($produce_order_id,',');
        $produce_order_info=M('produce_order')->where('produce_order_id in('.$produce_order_id.')')->select();
        $order_arr=array();

        $supplierIds = array_column($produce_order_info,'supplier_id');
        //页面供应商统一使用supplier_id获取
        $supplierIds = unique($supplierIds);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];

        foreach($produce_order_info as $order_id=>$order_info){
            $order_info['factory'] = $supplierInfo[$order_info['supplier_id']]['title'];
            $order_arr[$order_info['produce_order_id']]=$order_info;
        }

        $produce_order_id=explode(',',$produce_order_id);
        $produce_id=explode(',',rtrim($produce_id,','));
        $goods_sn=explode(',',rtrim($goods_sn,','));
        $arr=array();
        foreach($produce_order_id as $key=>$val){
            $arr[$val]['produce_order_id']=$val;
            $arr[$val]['produce_id']=$produce_id[$key];
            $arr[$val]['goods_sn']=$goods_sn[$key];
        }

        $info=array();
        foreach($arr as $k=>$v){
             $v['is_print']=true;
             $res=$this->produceOrderFabricList($v);
             foreach($res['content'] as $ke=>$va){
                 $info[$k]['list'][$ke]['huo_hao']=$va['huo_hao'];//货号
                 $info[$k]['list'][$ke]['color_number']=$va['color_number'];//色号
                 $info[$k]['list'][$ke]['color']=$va['color'];//颜色
                 $info[$k]['list'][$ke]['wide']=$va['wide'];//幅宽
                 $info[$k]['list'][$ke]['single_dosage']=$va['single_dosage'];//单件用量
                 $info[$k]['list'][$ke]['unit']=$va['unit'];//单位
                 $info[$k]['list'][$ke]['dosage_total']=$va['dosage_total'];//总用量
                 $info[$k]['list'][$ke]['huowei_num']=$va['huowei_name'];//货位号
             }
            $info[$k]['produce_order_id']=$k;//订单ID
            $info[$k]['time']=date('Y-m-d');//日期
            $info[$k]['fabric_purchaser']=$order_arr[$k]['fabric_purchaser'];//面料采购员
            $info[$k]['factory']=$order_arr[$k]['factory'];//加工厂
            $info[$k]['prepare_type']=$order_arr[$k]['prepare_type'];//加工厂
            $info[$k]['admin_name']=session('admin_name');//制单人
        }
       return array('success'=>1,'content'=>$info);
    }

    /**
     * 基础更新produce_order表
     * @author 陈东 2016-11-21 16:56:14
     */
    public function basicUpdateProduceOrder($id=array(),$data=array()){
        if(empty($id) || empty($data)||!is_array($id)||!is_array($data)){
            return false;
        }
        $res = M('produce_order')->where('produce_order_id in('.join(',',$id).')')->save($data);
        return $res;
    }


    /**
     * 根据id查询生产订单信息
     *
     * @param $id
     * @return array
     * @author Alex&陈超 2016-11-18 15:35
     * @modify 陈东 2017-5-30 09:13:32 供应商统一使用supplier_id获取
     */
    public function getMasterProduceOrderInfoById($id)
    {
        //根据下单编号，查询到货数量
        $produceOrderSql = "/*master*/ SELECT * FROM " . $this->tablePrefix . "produce_order WHERE produce_order_id = " . $id . " LIMIT 1";
        $produceOrderResult = $this->getRow($produceOrderSql);

        if($produceOrderResult){
            if($produceOrderResult['supplier_id'] == 0){
                $produceOrderResult['factory'] = '';
            }else{
                //供应商统一使用supplier_id获取
                $supplierIds = array($produceOrderResult['supplier_id']);
                //根据供应商id获取供应商信息
                $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
                $supplierInfo = $supplierInfo['content'];
                $produceOrderResult['factory'] = $supplierInfo[$produceOrderResult['supplier_id']]['title'];
            }

            return array(
                'success' => 1,
                'content' => $produceOrderResult,
            );
        } else {
            return array(
                'success' => 0,
                'errcode' => 1,
                'message' => '没有数据'
            );
        }
    }


    /**

     * 查询fba信息
     * @param  array $data_arr 搜索条件
     * @return array
     * @author 葛振 2016-12-26 17:05:42
     */
    public function getFbaOrderInfoByField($data_arr)
    {
        $where = isset($data_arr['where']) ? $data_arr['where'] : '';
        $field = isset($data_arr['field']) ? $data_arr['field'] : '';
        $order = isset($data_arr['order']) ? $data_arr['order'] : '';
        $limit = isset($data_arr['limit']) ? $data_arr['limit'] : '';
        return $this -> table($this->tablePrefix.'fba_order')->where($where)->order($order)->limit($limit)->field($field)->select();
    }

    /**
     * 添加生产单
     * @param $add_data
     * @param $status
     * @return array
     * @author 陈东 2016-12-28 14:45:26
     */
    public function basicAddProduceOrder($add_data,$status){
        if(empty($add_data)||empty($status)){
            return array('success'=>0,'message'=>'参数为空');
        }
        $produce_order_id = M('produce_order')->add($add_data);
        if($produce_order_id){
            $produce_order_status_add = array(
                'produce_order_id' => $produce_order_id,
                'status' => $status,
                'user_name' => empty($add_data['admin_name'])?session('admin_name'):$add_data['admin_name'],
                'add_time' => empty($add_data['add_time'])?time():$add_data['add_time']
            );
            M('produce_order_status')->add($produce_order_status_add);
        }
        if($produce_order_id){
            return array('success'=>1,'content'=>$produce_order_id);
        }else{
            return array('success'=>0,'message'=>'更新失败');
        }
    }

    /**
     * 批量下单
     *
     * @return array
     * @author Alex&陈超 2016-12-28 10:34
     * @modify朱立洋 2018-03-12 10:17:10 按价格对批量上传的单进行拆分
     */
    public function importProduceProjectOrder()
    {
        //直接读表格
        $data = read_excel($_FILES['file']['tmp_name']);
        $count = count($data);
        if (1000 < $count) {
            $result = array(
                'success' => false,
                'errorMsg' => '批量下单一次性最多可上传1000条数据。',
            );
            return $result;
        }
        //获取导入的尺码
        $title = current($data);
        $sizeList = array_slice($title, 6, null, true);
        //初始化检查数据结果
        $checkResult = array();
        $resultData = array();
        //循环检查数据并保存结果
        $skuList = array();
        foreach ($data as $key => $item) {
            if (1 == $key) {
                list($checkResult[], $resultData[]) = array(true, $item);
            } else {
                list($checkResult[], $resultData[]) = $this->_checkimportProduceProjectOrder($item, $sizeList);
                if (!in_array($item[self::_getBatchProduceOrderKey('sku')], $skuList)) {
                    $skuList[] = trim($item[self::_getBatchProduceOrderKey('sku')]);
                }
            }
        }
        //批量校验sku与属性
        $iaoResult = $this->getGoodsInfoBySku($skuList);
        if (!$iaoResult['success']) {
            $result = array(
                'success' => false,
                'errorMsg' => 'SKU校验失败，请重新上传。',
            );
            return $result;
        }
        //判断是否有sku与属性不匹配
        for ($i = 1; $i < $count; $i++) {
            $sizeCount = $sizeCountList = $sizeData = array();
            //获取excel数量内容
            $sizeCount = array_intersect_key($resultData[$i], $sizeList);
            //获取导入尺码数组
            $sizeCountList=array_intersect_key($sizeList, $sizeCount);
            //拼接尺寸数量信息
            $sizeData = array_combine($sizeCountList, $sizeCount);
            foreach ($sizeData as $sizeCountObj) {
                //校验只能为正整数
                if (!preg_match('/^[0-9]*$/', $sizeCountObj)) {
                    //校验数量
                    $checkResult[$i] = false;
                    $resultData[$i][self::_getBatchProduceOrderKey('remark')] .= '数量只能为正整数。';
                    break;
                }
            }
            if (!array_sum(array_intersect_key($resultData[$i], $sizeList))) {
                //校验数量
                $checkResult[$i] = false;
                $resultData[$i][self::_getBatchProduceOrderKey('remark')] .= '至少填写一个尺码的数量。';
            } elseif (!array_key_exists($resultData[$i][self::_getBatchProduceOrderKey('sku')], $iaoResult['data'])) {
                //sku不存在
                $checkResult[$i] = false;
                $resultData[$i][self::_getBatchProduceOrderKey('remark')] .= '未查到该SKU，请重新核实。';
            } else if (!$iaoResult['data'][$resultData[$i][self::_getBatchProduceOrderKey('sku')]]['beihuo_attr']['attr_value'] && array('无属性') != array_keys($sizeData)) {
                //无属性
                $checkResult[$i] = false;
                $resultData[$i][self::_getBatchProduceOrderKey('remark')] .= '该SKU无属性，请重新核实。';
            } else if ($iaoResult['data'][$resultData[$i][self::_getBatchProduceOrderKey('sku')]]['beihuo_attr']['attr_value']) {
                //对比属性
                if (array_diff(array_keys($sizeData), $iaoResult['data'][$resultData[$i][self::_getBatchProduceOrderKey('sku')]]['beihuo_attr']['attr_value'])) {
                    $checkResult[$i] = false;
                    $resultData[$i][self::_getBatchProduceOrderKey('remark')] .= 'SKU或属性选择错误，请重新核实。';
                }
            }
        }
        if (in_array(false, $checkResult)) {
            //初始化key
            $id = md5(session('admin_name') . time());
            //保存数据到redis(一小时内有效)
            $redis = redises();
            $redis = $redis->redis();
            $redis->set('Produce_ProduceProjectOrderModel_importProduceProjectOrder_'.$id, json_encode($resultData), 3600);

            $result = array(
                'success' => false,
                'errorMsg' => '文件上传失败，请修正后重新上传',
                'data' => array(
                    'id' => $id
                ),
            );
            return $result;
        }
        $prepareObj = array(
            'FBA' => 1,
            '普通备货' => 0,
            'US' => 2,
            '特殊备货' => 4,
            '亚马逊备货' => 5,
        );
        $order_identification=D('Prepare')->getOrderIdentification();
        $new_order=array();
        foreach ($order_identification as $k_o=>$v_o){
            $new_order[$v_o['basic_name']]=$v_o['id'];
        }
        //保存订单
        $addFlag = true;

        //循环data ，取预回货时间为空的SKU
        $sku_array = array();
        $sku_total = array();
        foreach($data as $sku_key=>$sku_val){
            if(1 ==$sku_key){
                continue;
            }
            $sku_array[]=$sku_val[3];
            $total_num  = 0;
            foreach($sku_val as $key => $val){
                if($key > 6 && $val){
                    $total_num += $val;
                }
            }
            if(isset($sku_total[$sku_val[3]])){
                $sku_total[$sku_val[3]] += $total_num; 
            }else{
                $sku_total[$sku_val[3]] = $total_num;
            }   
        }
        $sku_array = array_unique($sku_array);
        $supplier_id_arr = $this->getSupplierIdBySku($sku_array);
        $sku_supplierId_array = array_column($supplier_id_arr['content'],'supplierId','sku');
        //批量判断供应商ID是否是生产部 并且返回SKU的预回货时间
        $sku_back_time = R('SupplierInfo/BatchJudgeSupplierIdISProduce',array($sku_supplierId_array));
        //请求商品中心的价格数据
        $skc_list = array();
        foreach($data as $key => $val){
            if($key > 1){
                foreach($sizeList as $num => $v){
                    if($val[$num]){
                        $skc_list[] = array(
                                        "skc"       => $val[4],
                                        "attribute" => $v,
                                    );
                    }
                    
                }
            }
            
        }
        $cost_arr = D("Purchase")->getGoodsCostByAttr($skc_list);
        $new_data = array();
        foreach($data as $key => $val){
            if($key > 1){
                $current = "CNY";
                $supplier_id = $sku_supplierId_array[$val[4]] ? $sku_supplierId_array[$val[4]] : 0;
                if($supplier_id){
                    $current = M("supplier_info")->where(array("id" => $supplier_id))->getField("currency");
                }
                $cost_data = array_unique(array_column($cost_arr[$val[4]], $current));
                if(count($cost_data) == 1){
                    $new_val = array();
                    foreach($val as $num => $v){
                        if($num < 7){
                            $new_val[$num] = $v;
                        }
                        if($num>7&& $v>0){
                            $new_item = array();
                            $new_item[$num] = $v;
                            $new_item = $new_val+$new_item;
                            $new_data[] = $new_item;
                        }
                    }
                }else{
                    $new_data[] = $val;
                }
            }else{
                $new_data[1] = $val;
            }
            
        }
        foreach ($new_data as $key => $item) {
            if (1 != $key) {
                //获取标准sku，不采用用户输入的sku
                $goods_attr = D('ProduceProject')->getProduceProjectAttr($item[self::_getBatchProduceOrderKey('sku')],1);//获取产品尺码
                $goods_sn = $goods_attr['goods_sn'];
                //获取produce_order表信息
                $get_last_fabric_use = $this->getProduceOrderInfo($item[self::_getBatchProduceOrderKey('sku')]);//操作produceProjectOrder Model
                //数据插入produce_order表、
                $storage_time = '';
                if($sku_back_time[$item[self::_getBatchProduceOrderKey('sku')]]){
                    $storage_time = $sku_back_time[$item[self::_getBatchProduceOrderKey('sku')]];
                }

                if ($item[self::_getBatchProduceOrderKey('time')]) {
                    //日期读取自增1处理
                    $dateArr = explode('/', $item[self::_getBatchProduceOrderKey('time')]);
                    // $back_time = strtotime($dateArr[0].'/'.$dateArr[1].'/'.$dateArr[2]);
                    $back_time = (strtotime($dateArr[2].'/'.$dateArr[1].'/'.($dateArr[0]))-86400);
                }elseif($item[self::_getBatchProduceOrderKey('time')]=='') {
                    if($item[1]!='特殊备货'){
                        $res=$this->getProduceTime($item[self::_getBatchProduceOrderKey('sku')]);
                            $back_time = strtotime($res['content']);
                    }else{
                        $back_time='';
                    }
                }else{
                    //判断订单是否是特殊备货，如果是特殊备货默认为空
                    if($item[1]!='特殊备货'){
                        if($sku_back_time[$item[self::_getBatchProduceOrderKey('sku')]]){
                            $back_time = $sku_back_time[$item[self::_getBatchProduceOrderKey('sku')]];
                        }else{
                            $back_time = '';
                        }
                    }else{
                        $back_time = '';
                    }
                }

                //拼接尺码数组
                if ($iaoResult['data'][$item[self::_getBatchProduceOrderKey('sku')]]['beihuo_attr']['attr_value']) {
                    $skuSizeList = $iaoResult['data'][$item[self::_getBatchProduceOrderKey('sku')]]['beihuo_attr']['attr_value'];
                } else {
                    $skuSizeList = array('数量');
                }
                $detailObj = array();

                $sizeCount = $sizeCountList = $sizeData = array();
                //获取excel数量内容
                $sizeCount = array_intersect_key($item, $sizeList);
                //获取导入尺码数组
                $sizeCountList=array_intersect_key($sizeList, $sizeCount);
                //拼接尺寸数量信息
                $sizeData = array_combine($sizeCountList, $sizeCount);
                if (array_key_exists('无属性', $sizeData)) {
                    $sizeData['数量'] = $sizeData['无属性'];
                    unset($sizeData['无属性']);
                }
                foreach ($skuSizeList as $sizeObj) {
                    $detailObj[] = array(
                        'sizename' => $sizeObj,
                        'sizevalue' => $sizeData[$sizeObj] ? $sizeData[$sizeObj] : 0
                    );
                }

                $sizeObj = '';
                foreach ($detailObj as $detailItem) {
                    if ($detailItem['sizevalue']) {
                        $sizeObj .= $detailItem['sizename'] . ':' . $detailItem['sizevalue'] . '<br/>';
                    }
                }
                $data_arr[$key] = array(
                    'goods_sn'=>$goods_sn,
                    'goods_attr'=> array(
                        'detail' => $detailObj,
                        'size' => $sizeObj,
                        'goods_thumb' => $iaoResult['data'][$item[self::_getBatchProduceOrderKey('sku')]]['goods_thumb'],
                        'supplier_linkman' => $iaoResult['data'][$item[self::_getBatchProduceOrderKey('sku')]]['supplier_linkman'],
                    ),
                    'order_remark'=>'',
                    'old_storage_time'=> $storage_time,
                    'storage_time'=> $back_time,
                    'last_fabric_use'=>$get_last_fabric_use,
                    'prepare_type'=>$prepareObj[$item[self::_getBatchProduceOrderKey('type')]],
                    'order_identification'=>$new_order[$item[self::_getBatchProduceOrderKey('order_identification')]],
                    'paking_id'=>$item[self::_getBatchProduceOrderKey('packing_id')] ? $item[self::_getBatchProduceOrderKey('packing_id')] : '',
                    'fba_count'=>$item[self::_getBatchProduceOrderKey('fba_account')] ? $item[self::_getBatchProduceOrderKey('fba_account')] : '',
                    "total" => isset($sku_total[$item[3]]) ? $sku_total[$item[3]] : 0,
                );
               
            }
        }
        //去plm获取是否含有二次工艺
        $goods_sn_arr = array_column($data_arr, 'goods_sn');
        $costBatchList = D('ProduceProject')->getCostBatchListBySku($goods_sn_arr);
        $is_two_process_arr = array();
        if(!$costBatchList['code'] && $costBatchList['data']){
            foreach ($costBatchList['data'] as $key => $value) {
                if($value['secondary_list']){
                    $is_two_process_arr[] = $key;
                }
            }
        }
        foreach ($data_arr as $key => &$arr) {
            if(in_array($arr['goods_sn'], $is_two_process_arr)){
                $arr['is_two_process'] = 1;
            }
            //插入下单跟进表  操作produceProjectOrder Model
            $addResultObj = $this->addProduceOrder($arr);
            if (!$addResultObj['success']) {
                $addFlag = false;
                $resultData[$key-1][self::_getBatchProduceOrderKey('remark')] = '下单失败，请重新下单(注：其他订单下单成功)';
            }
        }
        if (!$addFlag) {
            //初始化key
            $id = md5(session('admin_name') . time());
            //保存数据到redis(一小时内有效)
            $redis = redises();
            $redis = $redis->redis();
            $redis->set('Produce_ProduceProjectOrderModel_importProduceProjectOrder_'.$id, json_encode($resultData), 3600);

            $result = array(
                'success' => false,
                'errorMsg' => '文件上传失败，请修正后重新上传。',
                'data' => array(
                    'id' => $id
                ),
            );
            return $result;
        } else {
            $result = array(
                'success' => true,
                'data' => array(
                    'count' => count($new_data) - 1,
                ),
            );
            return $result;
        }
    }

    /**
     * 获取导入错误数据
     *
     * @param $id
     * @return array
     * @author Alex&陈超 2016-12-27 17:14
     */
    public function getProduceProjectOrderError($id)
    {
        //获取数据
        $redis = redises();
        $redis = $redis->redis();
        $exportResult = $redis->get('Produce_ProduceProjectOrderModel_importProduceProjectOrder_'.$id);
        if ($exportResult) {
            $result = array(
                'success' => true,
                'data' => json_decode($exportResult, true),
            );
        } else {
            $result = array(
                'success' => false,
                'errorMsg' => '没有查到对应的文件',
            );
        }
        return $result;
    }

    /**
     * 验证订单跟进批量导入订单数据
     *
     * @param $item
     * @return array
     * @author Alex&陈超 2016-12-27 16:28
     * @modify 韦俞丞 2017-03-29 16:22:15 增加亚马逊备货
     * @modify 姚法强 2017-01-30 16:22:15 增加订单标识
     */
    private function _checkImportProduceProjectOrder($item, $sizeList)
    {

        if($item[6]==''){
             $res_backtime=$this->getProduceTime($item[self::_getBatchProduceOrderKey('sku')]);
             if (empty($res_backtime['content'])) {
                 $item[self::_getBatchProduceOrderKey('remark')] = '无预计回货时间，通过sku获取失败，请手动输入。';
                 return array(false,$item);
             }
        }

        if($item[6]!=''){
                $day=substr($item[6], 0,strpos($item[6],'/'));
                $month=substr($item[6],3,2);
                $year=substr(strrchr($item[6],'/'),1);
                // $time_str=$year.'/'.$month.'/'.($day-1);
                $time_str=$year.'/'.$month.'/'.$day;
                $time_str=date('Y/m/d',(strtotime($time_str)-86400));
                $item[6]=$time_str;
            if(!preg_match("/^([0-9]{4})\/([0-9]{2})\/([0-9]{2})$/",$item[6])){
                $item[self::_getBatchProduceOrderKey('remark')] = '预计回货时间格式不正确。';
                return array(false, $item);
            }else{
                if(strtotime($item[6])<strtotime(date('Y-m-d'))){
                    $item[self::_getBatchProduceOrderKey('remark')] = '预计回货时间小于当天时间。';
                    return array(false, $item);
                }
            }
        }
        //备货类型
        $allowType = array('FBA', 'US', '特殊备货', '普通备货', '亚马逊备货');
        if (!in_array($item[self::_getBatchProduceOrderKey('type')], $allowType)) {
            $item[self::_getBatchProduceOrderKey('remark')] = '备货类型为必填项，单选FBA/US/特殊备货/普通备货/亚马逊备货。';
            return array(false, $item);
        }
        //订单标识
        $order_identification=D('prepare')->getOrderIdentification();
        $orderType=array();
        foreach($order_identification as $k_a=>$v_a){
            $orderType[]=$v_a['basic_name'];
        }
        if (!in_array($item[self::_getBatchProduceOrderKey('order_identification')], $orderType)) {
            $item[self::_getBatchProduceOrderKey('remark')] = '订单标识为必填项，且值必须存在';
            return array(false, $item);
        }
        //尺码必填
        if (!$sizeList) {
            $item[self::_getBatchProduceOrderKey('remark')] = '尺码为必填项。';
            return array(false, $item);
        }

        //FBA备货FBA账户必填
        if ('FBA' == $item[self::_getBatchProduceOrderKey('type')]) {
            //获取FBA账号
            $fba_count = D('Site')->getFbaArr();
            if (!in_array($item[self::_getBatchProduceOrderKey('fba_account')], $fba_count)) {
                $item[self::_getBatchProduceOrderKey('remark')] = 'FBA账号匹配失败。';
                return array(false, $item);
            }
        }
        //sku必填
        if (!$item[self::_getBatchProduceOrderKey('sku')]) {
            $item[self::_getBatchProduceOrderKey('remark')] = 'SKU为必填项。';
            return array(false, $item);
        }
        return array(true, $item);
    }

    /**
     * 返回excel对应的key
     *
     * @param $name
     * @return bool|int|mixed|string
     * @author Alex&陈超 2017-01-18 17:54
     * @nodify 姚法强   2017-10-31 11-30 新增订单标识
     */
    private static function _getBatchProduceOrderKey($name) {
        $excelKey = array(
            'type',
            'order_identification',
            'fba_account',
            'sku',
            'packing_id',
            'time',
            'remark'
        );
        return array_search($name, $excelKey) + 1;
    }

    /**
     * 批量添加生产计划表
     *
     * @return array
     * @author 李永旺 2018-04-26 10:40
     */
    public function importProduceScheduleInfo()
    {
        //直接读表格
        $data = read_excel($_FILES['file']['tmp_name']);
        $count = count($data);
        if (500 < $count) {
            $result = array(
                'success' => false,
                'errorMsg' => '批量下单一次性最多可上传500条数据。',
            );
            return $result;
        }
        //获取导入的尺码
        $title = current($data);
        $addFlag = true;
        //初始化检查数据结果
        $checkResult = array();
        $resultData[] = array();
        foreach ($data as $key => $item) {

            if (1 == $key) {
                // list($checkResult[], $resultData[]) = array(true, $item);
            } else {
               
                // list($checkResult[], $resultData[]) = $this->_checkImportProduceScheduleInfo($item);
                 $dateArr = explode('/', $item[1]);
                $filter=array();
                $filter['set_datetime'] = (strtotime($dateArr[2].'/'.$dateArr[1].'/'.($dateArr[0]))-86400);
                // $filter['set_datetime'] = ($dateArr[2].'/'.$dateArr[1].'/'.$dateArr[0]);
                $filter['set_datetime'] = date('Y/m/d',$filter['set_datetime']);
                $produce_group_data=array();
                // $item1['errorMsg'] = '无预计回货时间，通过sku获取失败，请手动输入。';
                // return $item1;
                // $produce_group_data=array(
                //         array(
                //             "produce_group_name"=>$title[2],
                //             "pre_order_num"=>$item[2]
                //         ),
                //         array(
                //             "produce_group_name"=>$title[3],
                //             "pre_order_num"=>$item[3]
                //         ),
                //         array(
                //             "produce_group_name"=>$title[4],
                //             "pre_order_num"=>$item[4]
                //         ),
                //         array(
                //             "produce_group_name"=>$title[5],
                //             "pre_order_num"=>$item[5]
                //         ),
                //         array(
                //             "produce_group_name"=>$title[6],
                //             "pre_order_num"=>$item[6]
                //         )
                //     );

                foreach ($item as $k => $v) {
                    if ( ($k > 1) && ($k <= count($item))) {
                        $produce_group_data[]=array(
                            "produce_group_name"=>$title[$k],
                            "pre_order_num"=>$item[$k]
                        );
                    }
                }
                $filter['produce_group_data']=json_encode($produce_group_data);


                // $filter = array(
                //     'set_datetime'=>$set_datetime,
                //     'produce_group_data'=>array('produce_group'=>'',
                //     'pre_order_num'=> $item['pre_order_num'],
                //     'actual_order_num'=> $back_time,
                //     'admin_name'=> $admin_name,
                //     "add_time" => $add_time,)

                // );
                // $result = array(
                // 'success' => false,
                // 'errorMsg' => $filter['produce_group_data'],
                // );
                // return $result;die;
                // if (!empty($filter['produce_group_data'])) {
                  D('ProduceProject')->addProduceScheduleInfo($filter);
                // }
            }
        }


        // foreach ($data_arr as $key => &$arr) {
        //     if(in_array($arr['goods_sn'], $is_two_process_arr)){
        //         $arr['is_two_process'] = 1;
        //     }
        //     //插入下单跟进表  操作produceProjectOrder Model
        //     $addResultObj = $this->addProduceOrder($arr);
        //     if (!$addResultObj['success']) {
                // $addFlag = false;
        //         $resultData[$key-1][self::_getBatchProduceOrderKey('remark')] = '下单失败，请重新下单(注：其他订单下单成功)';
        //     }
        // }
        if (!$addFlag) {
            //初始化key
            $id = md5(session('admin_name') . time());
            //保存数据到redis(一小时内有效)
            $redis = redises();
            $redis = $redis->redis();
            $redis->set('Produce_ProduceProjectOrderModel_importProduceScheduleInfo_'.$id, json_encode($resultData), 3600);

            $result = array(
                'success' => false,
                'errorMsg' => '文件上传失败，请修正后重新上传。',
                'data' => array(
                    'id' => $id
                ),
            );
            return $result;
        } else {
            $result = array(
                'success' => true,
                'data' => array(
                    'count' => count($data) - 1,
                ),
            );
            return $result;
        }
    }

    /**
     * 获取导入错误数据
     *
     * @param $id
     * @return array
     * @author Alex&陈超 2016-12-27 17:14
     */
    public function getProduceScheduleInfoError($id)
    {
        //获取数据
        $redis = redises();
        $redis = $redis->redis();
        $exportResult = $redis->get('Produce_ProduceProjectOrderModel_importProduceProjectOrder_'.$id);
        if ($exportResult) {
            $result = array(
                'success' => true,
                'data' => json_decode($exportResult, true),
            );
        } else {
            $result = array(
                'success' => false,
                'errorMsg' => '没有查到对应的文件',
            );
        }
        return $result;
    }

    /**
     * 验证订单跟进批量导入订单数据
     *
     * @param $item
     * @return array
     * @author Alex&陈超 2016-12-27 16:28
     * @modify 韦俞丞 2017-03-29 16:22:15 增加亚马逊备货
     * @modify 姚法强 2017-01-30 16:22:15 增加订单标识
     */
    private function _checkImportProduceScheduleInfo($item)
    {

        if($item[6]==''){
             $res_backtime=$this->getProduceTime($item[self::_getBatchProduceOrderKey('sku')]);
             if (empty($res_backtime['content'])) {
                 $item[self::_getBatchProduceOrderKey('remark')] = '无预计回货时间，通过sku获取失败，请手动输入。';
                 return array(false,$item);
             }
        }

        if($item[6]!=''){
                $day=substr($item[6], 0,strpos($item[6],'/'));
                $month=substr($item[6],3,2);
                $year=substr(strrchr($item[6],'/'),1);
                // $time_str=$year.'/'.$month.'/'.($day-1);
                $time_str=$year.'/'.$month.'/'.$day;
                $time_str=date('Y/m/d',(strtotime($time_str)-86400));
                $item[6]=$time_str;
            if(!preg_match("/^([0-9]{4})\/([0-9]{2})\/([0-9]{2})$/",$item[6])){
                $item[self::_getBatchProduceOrderKey('remark')] = '预计回货时间格式不正确。';
                return array(false, $item);
            }else{
                if(strtotime($item[6])<strtotime(date('Y-m-d'))){
                    $item[self::_getBatchProduceOrderKey('remark')] = '预计回货时间小于当天时间。';
                    return array(false, $item);
                }
            }
        }
        //备货类型
        $allowType = array('FBA', 'US', '特殊备货', '普通备货', '亚马逊备货');
        if (!in_array($item[self::_getBatchProduceOrderKey('type')], $allowType)) {
            $item[self::_getBatchProduceOrderKey('remark')] = '备货类型为必填项，单选FBA/US/特殊备货/普通备货/亚马逊备货。';
            return array(false, $item);
        }
        //订单标识
        $order_identification=D('prepare')->getOrderIdentification();
        $orderType=array();
        foreach($order_identification as $k_a=>$v_a){
            $orderType[]=$v_a['basic_name'];
        }
        if (!in_array($item[self::_getBatchProduceOrderKey('order_identification')], $orderType)) {
            $item[self::_getBatchProduceOrderKey('remark')] = '订单标识为必填项，且值必须存在';
            return array(false, $item);
        }
        //尺码必填
        if (!$sizeList) {
            $item[self::_getBatchProduceOrderKey('remark')] = '尺码为必填项。';
            return array(false, $item);
        }

        //FBA备货FBA账户必填
        if ('FBA' == $item[self::_getBatchProduceOrderKey('type')]) {
            //获取FBA账号
            $fba_count = D('Site')->getFbaArr();
            if (!in_array($item[self::_getBatchProduceOrderKey('fba_account')], $fba_count)) {
                $item[self::_getBatchProduceOrderKey('remark')] = 'FBA账号匹配失败。';
                return array(false, $item);
            }
        }
        //sku必填
        if (!$item[self::_getBatchProduceOrderKey('sku')]) {
            $item[self::_getBatchProduceOrderKey('remark')] = 'SKU为必填项。';
            return array(false, $item);
        }
        return array(true, $item);
    }

    /**
     * 返回excel对应的key
     *
     * @param $name
     * @return bool|int|mixed|string
     * @author Alex&陈超 2017-01-18 17:54
     * @nodify 姚法强   2017-10-31 11-30 新增订单标识
     */
    private static function _getBatchProduceScheduleInfoKey($name) {
        $excelKey = array(
            'type',
            'order_identification',
            'fba_account',
            'sku',
            'packing_id',
            'time',
            'remark'
        );
        return array_search($name, $excelKey) + 1;
    }

    /**
     * 更改订单的状态【已查验】
     *
     * @param number $produce_order_id
     * @param string $status_key
     * @param string $type
     * @return string|boolean
     * @author 周阳阳 2017年2月20日 上午10:00:58
     * @modify 田靖 2017-08-22 10:56:41 添加已送货
     */
    public function saveProduceOrderStatus($produce_order_id = 0, $status_key = '', $type = '')
    {
        if (! empty($produce_order_id) && ! empty($status_key) && ! empty($type)) {

            $dir = ROOT_PATH . '/Logs/produceOrder';
            if (! is_dir($dir)) {
                @mkdir($dir);
            }
            $log = date('Y-m-d H:i:s', time()) . "【{$type}】\r\n" . json_encode(func_get_args()) . "\r\n";
            write_log_to_file($dir, $log);
            if ('inventory' != $type) {
                return '模块标识符不正确!';
            }
            $this->table($this->tablePrefix . 'produce_order');
            $where = array(
                'produce_order_id' => $produce_order_id,
                'is_delete' => 0
            );
            $order_status = array(
                'verified' => 30,//已查验
                'return' => 34,//已退货
                'received' => 12,//已收货
                'accomplished' => 9//已完成
            );
            $order_info = array();
            $order_info = $this->field('produce_order_id,status')->where($where)->find();
            if (! empty($order_info)) {
                if (array_key_exists($status_key, $order_status)) {
                     //key 更改后的状态，val 改之前的状态
                      $allow_status = array(
                            9=>array(12,30,34),
                            12=>array(21,34,14,35),
                            30=>array(12,34),
                            34=>array(12,30),
                      );
                    if (isset($allow_status[$order_status[$status_key]])) {
                        if (! in_array($order_info['status'], $allow_status[$order_status[$status_key]])) {
                            return '状态更改流程不对!';
                        }
                    } else {
                        return '订单状态不符合要求!';
                    }
                    $where['status'] = array(
                        'in',
                        '12,21,30,34,14,35'
                    );

                    $result = $this->table($this->tablePrefix . 'produce_order')->where($where)->setField('status', $order_status[$status_key]);
                    if (! empty($result)) {
                        // 日志
                        if ('accomplished' == $status_key) {
                            $order_status[$status_key] = 26;
                        }
                        $status_data = array(
                            'produce_order_id' => $produce_order_id,
                            'status' => $order_status[$status_key],
                            'user_name' => I('session.admin_name'),
                            'add_time' => time()
                        );
                        $this->table($this->tablePrefix . 'produce_order_status')->add($status_data);
                        return true;
                    } else {
                        return '订单状态更新失败!';
                    }
                } else {
                    return '订单类型不存在';
                }
            } else {
                return '订单不存在';
            }
        } else {
            return '参数错误';
        }
    }

    /**
     * 根据sku获取最早一次订单跟进回货日期数据
     *
     * @param $skuList
     * @return array
     * @author Alex&陈超 2017-03-04 上午10:59
     * @modify 周阳阳2017-03-27 11:16:30 添加尺码
     * @modify 唐亮 2017-04-14 17:21:30 添加只查询普通备货和特殊备货
     */
    public function getProduceOrderInfoBySku($params)
    {
        //初始化出参
        $result = array(
            'code' => 200,
            'info' => array()
        );
        //匹配需查询的订单状态
        $orderStatus = $this->_formatNeedOrderStatus($params['orderStatus']);
        //只查询普通备货和特殊备货
        $prepareType = array(0,4);
        //拼接查询参数
        $where = array(
            'goods_sn' => array('IN', $params['skuList']),
            'status' => array('IN', $orderStatus),
            'prepare_type' => array('IN', $prepareType),
            'is_delete' => 0
        );
        $field = array(
            'produce_order_id',
            'goods_sn',
            'status',
            'back_time',
            'order_info'
        );
        //TODO 排序不严谨，目前按照下单时间排序，应该以订单状态排序
        $sqlResult = M('ProduceOrder')->field($field)->where($where)->order('back_time desc')->select();
        //拼接数据
        foreach ($sqlResult as $produceOrderObj) {
            $result['info'][$produceOrderObj['goods_sn']][]= array(
                'status' => $this->getProduceOrderStatusName($produceOrderObj['status']),
                'backTime' => $produceOrderObj['back_time'] ? date('Y-m-d', $produceOrderObj['back_time']) : '',
                'orderInfo' => $this->_formatOrderInfo($produceOrderObj['order_info'])
            );
        }
        return $result;
    }

    /**
     * 格式化尺码信息
     *
     * @author 周阳阳 2017年3月27日 上午11:30:51
     */
    public function _formatOrderInfo($info = '')
    {
        if(!empty($info)){
            //
            $order_info = explode('<br/>',$info);
            $array = array();
            foreach ($order_info as $k=>$v){
                if(!empty($v)){
                    $_temp = explode(':',$v);
                    $array[array_shift($_temp)] = array_shift($_temp);
                }

            }
            return $array;
        }else{
            return array();
        }
    }

    /**
     * 匹配各状态值对应订单status
     *
     * @param $orderStatus
     * @return array
     * @author Alex&陈超 2017-03-06 下午1:17
     */
    private function _formatNeedOrderStatus($orderStatus)
    {
        switch ($orderStatus) {
            case 'inspection':
                $status = array(5,14,6,13,7,8,21,12,31,32,33,34);
                break;
            default :
                $status = array(5,14,6,13,7,8,21,12,31,32,33,34,9);
                break;
        }
        return $status;
    }
    /**
     * 新增生产时间延期申请
     *
     * @param $data
     * @return arr
     * @author 田靖
     * @modify 陈东 2017-5-25 11:00:53 页面供应商统一使用supplier_id获取
     */
    public function produceDelaySubmit($data = array())
    {
        $produce_order = M('produce_order')->where(array('produce_order_id' => $data['produce_order_id']))->find();

        $supplierIds = array($produce_order['supplier_id']);
        //供应商统一使用supplier_id获取
        $supplierIds = unique($supplierIds);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];
        $produce_order['factory'] = empty($supplierInfo[$produce_order['supplier_id']]['title'])?
                                    '':$supplierInfo[$produce_order['supplier_id']]['title'];

        $produce_order_man = M('produce_order_status')->where(array('produce_order_id' => $data['produce_order_id'], 'status' => 5))->getField('user_name');
        if ($produce_order['storage_time'] > strtotime($data['back_time'])) {
            return array('success' => 0, 'message' => '申请延期时间未大于生产总时效');
        }
        if ($produce_order && $produce_order_man) {
            $this->startTrans();
            $commit = array();
            $save_data = array(
                'produce_order_id' => $produce_order['produce_order_id'],
                'factory' => $produce_order['factory'],
                'goods_sn' => $produce_order['goods_sn'],
                'order_man' => $produce_order_man,
                'apply_man' => session('admin_name'),
                'storage_time' => $produce_order['storage_time'],
                'back_time' => strtotime($data['back_time']),
                'status' => 0,
                'reason' => $data['reason'],
                'add_time' => time(),
            );
            $commit[] = $res = M('produce_delay')->add($save_data);
            $commit[] = M('produce_delay_status')->add(array('delay_id' => $res, 'status' => 0, 'add_time' => time(), 'user_name' => session('admin_name')));
            if (db_commit($commit)) {
                return array('success' => 1);
            }
        }
        return array('success' => 0, 'message' => '添加失败');
    }

    /**
     * 获取生产延迟申请
     *
     * @param $data
     * @return arr
     * @author 田靖
     * @modify 葛振 2017-03-27 11:47:53 生产订单跟进流程功能完善
     */
    public function getProduceDelayList($filter=array())
    {
        $where = '';
        $filter = page_and_size($filter);
        if($filter['status']!==''){
            $where .= " and status = ".$filter['status'];
        }
        if($filter['produce_order_id']){
            $where .= " and produce_order_id = ".$filter['produce_order_id'];
        }
        if($filter['goods_sn']){
            $where .= " and goods_sn = '".$filter['goods_sn']."' ";
        }
        if($filter['factory']){
            $where .= " and factory like '".$filter['factory']."%' ";
        }
        if($filter['order_man']){
            $where .= " and order_man = '".$filter['order_man']."'";
        }
        if($filter['apply_man']){
            $where .= " and apply_man = '".$filter['apply_man']."'";
        }
        if($filter['apply_start_time']){
            $where .= " and add_time >= ".strtotime($filter['apply_start_time']);
        }
        if($filter['apply_end_time']){
            $where .= " and add_time <= ".strtotime($filter['apply_end_time']);
        }
        if($filter['handle_start_time']){
            $where .= " and handle_time >= ".strtotime($filter['handle_start_time']);
        }
        if($filter['handle_end_time']){
            $where .= " and handle_time <= ".strtotime($filter['handle_end_time']);
        }
        $sql = ' SELECT COUNT(*) FROM ' . $this->tablePrefix . 'produce_delay where 1=1 ' . $where;
        $record_count = $this->getOne($sql);// 搜出来的个数
        $page_count = $record_count > 0 ? ceil( $record_count / $filter['page_size'] ) : 1;// 分页页数
        $sql = ' SELECT * FROM ' . $this->tablePrefix . "produce_delay where 1=1 " .$where.
            " order by id desc  LIMIT " . ($filter['page']-1) * $filter['page_size'] . "," . $filter['page_size'];
        $produce_delay = $this->getAll($sql);
        foreach ($produce_delay as $key => $value) {
            $produce_delay[$key]['do'] = $value['apply_man'].date(' Y-m-d H:i:s ',$value['add_time']).'延期申请';
            if($value['status'] != 0){
                $produce_deal_name = M('produce_delay_status')->where(array('delay_id'=>$value['id'],'status'=>$value['status']))->getField('user_name');
                $produce_delay[$key]['do'] .= '<br/>'.$produce_deal_name.date(' Y-m-d H:i:s ',$value['handle_time']);
                $produce_delay[$key]['do'] .= $value['status'] == 1?'已通过':'已拒绝';
            }
            $produce_delay[$key]['storage_time'] = $produce_delay[$key]['storage_time'] ? date('Y-m-d H:i:s',$value['storage_time']) :'';
            $produce_delay[$key]['back_time'] = $produce_delay[$key]['back_time'] ? date('Y-m-d H:i:s',$value['back_time']):'';
        }
        return array ('arr' => $produce_delay, 'filter' => $filter, 'page_count' => $page_count, 'record_count' => $record_count );
    }

    /**
     * 处理生产时间延期申请
     *
     * @param $data
     * @return arr
     * @author 田靖
     */
    public function produceDealDelay($data=array())
    {
        $this->startTrans();
        $commit = array();
        $find = M('produce_delay')->where(array('status'=>0,'id'=>$data['id']))->lock(true)->find();
        if($find){
            $commit[] = M('produce_delay')->where(array('id'=>$data['id']))->save(array('status'=>$data['type'],'handle_time'=>time()));
            $commit[] = M('produce_delay_status')->add(array('status'=>$data['type'],'delay_id'=>$data['id'],'add_time'=>time(),'user_name'=>session('admin_name')));
            $content = '已拒绝';
            if($data['type'] == 1){
                $commit[] = M('produce_order')->where(array('produce_order_id'=>$find['produce_order_id']))->setField('back_time',$find['back_time']);
                $content = '已通过';
                $commit[] = M('produce_order_status')->add(array('produce_order_id'=>$find['produce_order_id'],'status'=>23,'user_name'=>session('admin_name'),'add_time'=>time()));
            }
            if(db_commit($commit)){
                return array('success'=>1,'content' => session('admin_name').date(' Y-m-d H:i:s ',time()).$content);
            }else{
                return array('success'=>0,'message'=>'提交失败');
            }

        }else{
            $this->rollback();
            return array('success'=>0,'message'=>'未找到该申请');
        }
    }

    /**
     * 批色操作
     *
     * @param number $produce_order_id
     * @author 周阳阳 2017年3月16日 上午9:35:41
     * @modify 李永旺 2017-11-27 16:30:53 增加状态 14,6,7,8,21
     */
    public function applyColor($produce_order_id = 0)
    {
        $result = '批色失败!';
        if(!empty($produce_order_id)){
            //查询条件
            $where = array(
                'produce_order_id' =>$produce_order_id,
                'is_delete'=>0,
                'is_apply_color'=>0,
                'status'=>array('in','13,14,6,7,8,21'),
            );
            $_model =  M('produce_order');
            //订单数据
            $produce_order = $_model->where($where)->getField('produce_order_id');
            if(!empty($produce_order)){
                //保存数据
                if($_model->where($where)->setField('is_apply_color',1)){
                    //日志
                    $this->addProduceOrderStatus($produce_order_id,27);
                    return true;
                }else{
                    $result = '批色操作失败!';
                }
            }else{
                return '未查到符合条件数据!';
            }

        }else{

            return $result;
        }
    }
    /**
     * 批色操作(批量)
     *
     * @param number $produce_order_id
     * @author 周阳阳 2017年3月16日 上午9:35:41
     * @modify 李永旺 2017-11-27 16:30:53 增加状态 14,6,7,8,21
     */
    public function applyColorMore($produce_order_id = array())
    {
        $result = '批色失败!';
        if(!is_array($produce_order_id)){
            return '请求的参数类型有误';
        }
        if(!empty($produce_order_id)){
            //查询条件
            $where = array(
                'produce_order_id' =>array('in',$produce_order_id),
                'is_delete'=>0,
                'is_apply_color'=>0,
                'status'=>array('in','13,14,6,7,8,21'),
            );
            $_model =  M('produce_order');
            //订单数据
            $produce_order = $_model->where($where)->getField('produce_order_id',true);
            if(!empty($produce_order)){
                //保存数据
                if($_model->where($where)->setField('is_apply_color',1)){
                    //日志
                    $this->addProduceOrderStatus($produce_order_id,27);
                    return true;
                }else{
                    $result = '批色操作失败!';
                }
            }else{
                return '未查到符合条件数据!';
            }

        }else{

            return $result;
        }
    }
    /**
     * 获取生产时间延期
     *
     * @param $data
     * @return arr
     * @author 田靖
     */
    public function getProduceDelay($produce_order_id = '')
    {
        $result = '';
        if($produce_order_id){
            $res = M('produce_delay')->where(array('produce_order_id'=>$produce_order_id))->order('id desc')->find();
            if($res){
                if($res['status']){
                    if($res['status'] == 2){
                        $result = '已拒绝延期申请';
                    }
                }else{
                    $result = '已提交延期申请';
                }
            }
        }
        return $result;
    }

    /**
     * 获取订单信息数组自定义字段
     * @param $order_id_arr array 订单ID数组
     * @param $field  string 需要返回的字段 例如：'category,add_time'
     * @return array  array（'produce_order_id'=>field）
     * @author 葛振 2017-03-20 14:03:36
     */
    public function getFieldArrayByOrderId($order_id_arr,$field ='')
    {
        if(empty($order_id_arr)){
            return array('success'=>0,'message'=>'');
        }
        $where['produce_order_id']=array('in',$order_id_arr);
        if(empty($field)){
            return array('success'=>0,'message'=>'');
        }else{
            $field = 'produce_order_id,'.$field;
        }
        $res = M('produce_order')->where($where)->getField($field,true);
        return array('success'=>1,'content'=>$res);
    }
    /**
     * 操作已齐套
     * @param $order_id  string 订单ID
     * @return array  array（'produce_order_id'=>field）
     * @author 葛振 2017-03-20 14:03:36
     */
    public function fullSetHandle($order_id)
    {
        if(empty($order_id)){
            return array('success'=>0,'message'=>'参数为空');
        }
        $where['produce_order_id']=$order_id;
        $data['is_full_set']=1;
        $this->startTrans();
        $res[] = M('produce_order')->where($where)->save($data);
        $res[]=$this->addProduceOrderStatus($order_id,61);
        if(db_commit($res)){
            return array('success'=>1,'content'=>$res);
        }else{
            return array('success'=>0,'message'=>'数据更新失败');
        }
    }
    /**
     * 操作已齐套
     * @param $order_id  string 订单ID
     * @return array  array（'produce_order_id'=>field）
     * @author 葛振 2017-03-20 14:03:36
     */
    public function getRemarkInfo($order_id)
    {
        $where['produce_order_id'] = array('in',$order_id);
        $list = M('produce_order_remark')->where($where)->select();
        $return_arr = array();
        foreach($list as $key=>$val){
            if(array_key_exists($val['produce_order_id'],$return_arr)){
                if($val['time']>$return_arr[$val['produce_order_id']]['time']){
                    $return_arr[$val['produce_order_id']]['time'] = $val['time'];
                    $return_arr[$val['produce_order_id']]['remark_info'] =$val['remark_info'];
                }
            }else{
                $return_arr[$val['produce_order_id']]=$val;
            }
        }
        $arr = array();
        foreach($return_arr as $k=>$v){
            if(empty($val['remark_info'])||$v['remark_info'] == '最近次品原因：  ' || $v['remark_info'] == '最近次品原因：  无' ){
                $arr[$k] = '';
                continue;
            }else{
                $arr[$k] = mb_substr($v['remark_info'],0,5,"utf-8");
            }
        }
        return $arr;
    }

    /**
     * 生产订单颜色值定时任务
     *
     * @author 李永旺 2017年11月21日 下午14:50:00
     */
    public function setProduceOrderRowColor()
    {
            //无超时验证
            set_time_limit(0);
            // ignore_user_abort();//关闭浏览器仍然执行
            //查询条件
           $data_arr = array(
                'where' => array(
                    'status'=>array(
                        'in','5,14,6,7,8,13,21'
                    ),
                    'is_delete' => 0
                ),
                'field' => '*',
            );
           
                    $where = isset($data_arr['where']) ? $data_arr['where'] : '';
                    $field = isset($data_arr['field']) ? $data_arr['field'] : '';
                    $order = isset($data_arr['order']) ? $data_arr['order'] : '';
                    $limit = isset($data_arr['limit']) ? $data_arr['limit'] : '';
                    $produceOrders = $this->table($this->tablePrefix . 'produce_order')->where($where)->order($order)->limit($limit)->field($field)->select();

                    $result = $produceOrders;
                    //未送货状态，已送货之前的状态
                    $status_items_delivering=array('5','14','6','13','7','8','21');

                    // 广州订单分类 1:线上单 2:线下单 3:FOB单 4:CMT单（以前的OEM单） 6:ODM单
                    $category=array('3','4');
                    $instance = M("produce_order"); 
                    $instanceProduce = M("produce"); 
                    $k=0;
                    foreach ($result as $key => $value) {
                        $this->startTrans();
                        $commit = array();
                                    $k++;
                                     // 判断是否为首单
                                     $isFirstOrderData = D('ProduceProjectOrder')->isFirstOrder($value['goods_sn'],$value['produce_id'],$value['produce_order_id']);
                                    // 若订单为【首单】且未送货之前已超过预计收货时间，则将此订单整行信息的底色变更为红色
                                    $init="produce_order_id={$value['produce_order_id']}";
                                    // die;
                                    $data['row_color'] = 0;
                                    $flag=0;
                                     $commit[]=$instance->where($init)->save($data);

                                    if(isset($isFirstOrderData['isFirstOrderStatus']) && $isFirstOrderData['isFirstOrderStatus']==1){

                                        if(in_array($value['status'], $status_items_delivering) && ($value['back_time'] < time())){
                                                $wheres="produce_order_id={$value['produce_order_id']}";
                                                $commit[]=$instance->where($wheres)->setField('row_color',3);
                                                $flag=1;
                                        }
                                       
                                    }
                                    // 若订单【不为首单】在未送货之前已超过预计收货时间，则将此订单整行信息的底色变更为橙色
                                    if(isset($isFirstOrderData['isFirstOrderStatus']) && $isFirstOrderData['isFirstOrderStatus']==0){
                                        if(in_array($value['status'], $status_items_delivering) && ($value['back_time'] < time())){
                                                $wheres="produce_order_id={$value['produce_order_id']}";
                                            $commit[]=$instance->where($wheres)->setField('row_color',2);
                                            $flag=1;
                                        }
                                    }
                                    // FOB单、CMT单设定环节时效，将超出时效的订单底色变更为黄色 （当前耗时大于等于环节时效时，视为环节超期。）
                                    if(in_array($value['category'], $category) && $flag!=1 ){
                                            // 判断是否是FOB单,备货类型等于 特殊备货，不参与此规则
                                            if ($value['category']==3 && $value['prepare_type']!=4) {
                                                        $order_add_time = strtotime(date('Y-m-d', $value['add_time']));
                                                        if ($value['status']!=9){//当状态不是已完成时，用当前时间-下单时间
                                                            $today=strtotime(date('Y-m-d'));
                                                            $spend_time= ceil(($today - $order_add_time) / 60 / 60 / 24);//耗时

                                                        }else{//已完成时间-下单时间
                                                            $over_order_time=M('produce_order_status')->where('produce_order_id = '.$value['produce_order_id'].' and status in(9,26)')->getField('add_time');
                                                            $order_over_time = strtotime(date('Y-m-d', $over_order_time));
                                                            $spend_time = ceil(($order_over_time - $order_add_time) / 60 / 60 / 24);//耗时

                                                        }

                                                         // 生产总时效 来自基础档案款式生产信息的生产总时效
                                                            $goods_sn['goods_sn']=$value['goods_sn'];
                                                            $goods_sn['is_delete']=0;
                                                            $produce_time = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
                                                              if($produce_time['produce_time']==''){
                                                                  $produce_time['produce_time']=0;
                                                              }

                                                        if ($value['status']==6 && $spend_time >= 1) {//判断状态是否为'分配中'
                                                            $where_a= "produce_order_id={$value['produce_order_id']}";
                                                            $commit[]=$instance-> where($where_a)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==13 && $spend_time >= 3) {//判断状态是否为'订料中'
                                                             $where_a= "produce_order_id={$value['produce_order_id']}";
                                                             $commit[]=$instance-> where($where_a)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==7 && $spend_time >= 4.5) {//判断状态是否为'裁剪中'
                                                             $where_a= "produce_order_id={$value['produce_order_id']}";
                                                             $commit[]=$instance-> where($where_a)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==8 && ($spend_time >= ($produce_time['produce_time']-3))){ //判断状态是否为'车缝中',车缝中 环节时效:生产总时效-7.5天+4.5天；
                                                            $where_a= "produce_order_id={$value['produce_order_id']}";
                                                            $commit[]=$instance-> where($where_a)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==21 && (($value['back_time']-86400)<=time())) {//判断状态是否为'后整中'
                                                            $where_a= "produce_order_id={$value['produce_order_id']}";
                                                            $commit[]=$instance-> where($where_a)->setField('row_color',1);
                                                        }
                                            }
                                            // 判断是否是CMT单
                                            if ($value['category']==4 && $value['prepare_type']!=4) {

                                                        $order_add_time = strtotime(date('Y-m-d', $value['add_time']));
                                                        if ($value['status']!=9){//当状态不是已完成时，用当前时间-下单时间
                                                            $today=strtotime(date('Y-m-d'));
                                                            $spend_time= ceil(($today - $order_add_time) / 60 / 60 / 24);//耗时
   
                                                        }else{//已完成时间-下单时间
                                                            $over_order_time=M('produce_order_status')->where('produce_order_id = '.$value['produce_order_id'].' and status in(9,26)')->getField('add_time');
                                                            $order_over_time = strtotime(date('Y-m-d', $over_order_time));
                                                            $spend_time = ceil(($order_over_time - $order_add_time) / 60 / 60 / 24);//耗时

                                                        }


                                                        // 生产总时效 来自基础档案款式生产信息的生产总时效
                                                        $goods_sn['goods_sn']=$value['goods_sn'];
                                                        $goods_sn['is_delete']=0;
                                                        $produce_time = M("produce_style_info")->where($goods_sn)->order(array('add_time'=>'desc'))->find(); 
                                                          if($produce_time['produce_time']==''){
                                                              $produce_time['produce_time']=0;
                                                          }

                                                        if ($value['status']==6 && $spend_time >= 4) {//判断状态是否为'分配中'
                                                            $where_b= "produce_order_id={$value['produce_order_id']}";
                                                            $commit[]=$instance-> where($where_b)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==13 && $spend_time >= 3) {//判断状态是否为'订料中'
                                                             $where_b= "produce_order_id={$value['produce_order_id']}";
                                                             $commit[]=$instance-> where($where_b)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==7 && $spend_time >= 5.5) {//判断状态是否为'裁剪中'
                                                             $where_b= "produce_order_id={$value['produce_order_id']}";
                                                             $commit[]=$instance-> where($where_b)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==8 && ($spend_time >= ($produce_time['produce_time']-3))) { //判断状态是否为'车缝中',车缝中 环节时效:生产总时效-7.5天+4.5天；
                                                            $where_b= "produce_order_id={$value['produce_order_id']}";
                                                            $commit[]=$instance-> where($where_b)->setField('row_color',1);
                                                        }
                                                        if ($value['status']==21 && (($value['back_time']-86400)<=time())) {//判断状态是否为'后整中'
                                                            $where_b= "produce_order_id={$value['produce_order_id']}";
                                                            $commit[]=$instance-> where($where_b)->setField('row_color',1);
                                                        }
                                            }

                                    }

                                    if ($k%5000==0) {
                                        sleep(0.5);
                                    }
                                    if(!empty($commit)){
                                            db_commit($commit);
                                            echo 'produce_order_id:'.$value['produce_order_id'].'处理成功!success<br>';
                                        }else{
                                           echo 'produce_order_id:'.$value['produce_order_id'].'处理失败!failed<br>';
                                    }
                    }//end foreach
                    echo '订单跟进颜色设置完成！';


    }

    /**
     * 重置生产总时效
     *
     * @author 周阳阳 2017年4月4日 上午10:34:47
     * @modify 李永旺 2018-3-22 15:57:53  修改 生产总时效 来自基础档案款式生产信息的生产总时效
     */
    public function resetStorageTime()
    {
        //无超时验证
        set_time_limit(0);
        //查询条件
        $map = array(
            'where' => array(
                'category' => array(
                    'in',
                    '3,4'
                ),
                'storage_time' => array(
                    'ELT',
                    0
                ),
                'status'=>array(
                    'in','6,7,8,13,21'
                ),
                'is_delete' => 0
            ),
            'field' => 'produce_order_id,storage_time,add_time,goods_sn'
        );
        $result = $this->getProduceOrder($map);
        if(empty($result)){
            return ;
        }
        $goods_sn = array();
        $goods_sn = array_column($result, 'goods_sn');//取出SKU
        $where = array(
            'goods_sn' => array(
                'in',
                $goods_sn
            ),
            'produce_time'=>array('GT',0),
            'is_delete' => 0
        );
        //获取生产失效
        $_temp = M('produce_style_info')->field('produce_time,goods_sn')
                ->where($where)
                ->select();
        if (empty($result)) {
            return;
        }
        $produce_time = array();
        foreach ($_temp as $v) {
            $produce_time[$v['goods_sn']] = $v['produce_time'];
        }
        $itime = 0;
        $_model = M('produce_order');
        //批量更新
        foreach ($result as $v) {
            if($itime<1000){
                ++$itime;
            }else{
                usleep(100000);//100ms 延迟
                $itime=0;
            }
           /*计算失效  计算方式为下单日期+生产总时效*/
            if (!empty($produce_time[$v['goods_sn']]) && !empty($v['add_time'])) {
                $day = ((int)($produce_time[$v['goods_sn']] - 1) > 0)?(int)($produce_time[$v['goods_sn']] - 1):0;
                $storage_time = strtotime('+' .$day. 'days', $v['add_time']);
                $_model->where(array('produce_order_id'=>$v['produce_order_id']))->setField('storage_time', $storage_time);
            }else{
                continue ;
            }

        }
    }

    /**
     * 消息推送 分单 给供应商 批量
     * @param $ids array 数组
     * @return array
     * @author 葛振 2017-04-13 17:22:35
     * @modify 陈东 2017-5-25 11:00:53 供应商统一使用supplier_id获取
     */
    public function CategoryMq($ids){
        $where['produce_order_id'] = array('in',$ids);
        $supplier_info =M('Produce_order')->where($where)->getField('produce_order_id,supplier_id',true);

        //供应商统一使用supplier_id获取
        $supplierIds = array_values($supplier_info);
        $supplierIds = unique($supplierIds);
        //根据供应商id获取供应商信息
        $supplierInfo = R('SupplierInfo/getSupplierByIds', array($supplierIds));
        $supplierInfo = $supplierInfo['content'];

        $arr = array();
        foreach($supplier_info as $key=>$val){
            $val = $supplierInfo[$val]['title'];
            $arr[$val][] = $key;
        }
        $return = array();
        $n = 0;
        foreach($arr as $k=>$v){
            if(count($v)>1){
                $return[$n]['supplier_name'] = $k;
                $return[$n]['msg'] = '您有'.count($v).'订单生成';
            }else{
                $return[$n]['supplier_name'] = $k;
                $return[$n]['msg'] = '您有新的采购订单'.$v[0].'生成';
            }
            $n++;
        }
        $url=C('GEIWOHUO_HOST').C('GWH_API_URL');
        actionPost($url,array('supplier'=>json_encode($return)));
    }

    /**
     * 到货数量 规则校验
     * @access public
     * @return array
     * @param $data array 订单数据
     * @author 葛振 2017-05-22 16:23:58
     */
    public function storedNumRuleCheck($data){
        $category = $data['category'];//订单类型
        $sum_storeNum = $data['sum_storeNum'];//送货数量
        $order_num = $this->getTotalProduceOrder($data['order_info']);//下单数量
        $msg = '';
        $stored_order_subtract = $sum_storeNum -$order_num;
        if($category==3){//fob
            $first_if = 1.3*$order_num;
            if(($sum_storeNum>$first_if)||($stored_order_subtract)>100){
                $msg = '送货数量需小于下单数量的130%且超出件数需小于100';
            }
        }elseif(in_array($category,array(1,2,6))){//线上，线下，ODM
            if($order_num>50){
                $first_if = 1.2*$order_num;
                if(($sum_storeNum>$first_if)||($stored_order_subtract>50)){
                    $msg = '送货数量需小于下单数量的120%且超出件数需小于50';
                }
            }else{
                if($stored_order_subtract>5){
                    $msg = '送货数量不可比下单数量多5件以上';
                }
            }
        }
        if($msg){
            return array('success'=>0,'message'=>$msg);
        }else{
            return array('success'=>1,'content'=>'');
        }
    }


    /**
     * 获取订单实价
     * @param $goods_sn
     * @param $category
     * @param $prepare_type
     * @param $order_count
     * @return float|string
     *
     * @author 周金剑 2017-06-01 15:00:00
     * @modify 靳明杰 2017-10-27 11:00:00  车缝改为加工费
     */
    public function getOrderPrice($goods_sn, $category,$prepare_type, $order_count)
    {
        $order_price = '';
        //检车sku在设计款式开发是否存在
        $produce_info = M('produce')->where(array('goods_sn'=>$goods_sn,'is_delete'=>$this::IS_NOT_DELETE,'status'=>9))->order('id desc')->find();
        if(empty($produce_info)){
            return $order_price;
        }
        $produce_id = $produce_info['id'];
        $cost_arr = D('ProduceProject')->getPredictCost(array("produce_id" =>$produce_id, 'type' => 2));
        //获取核算中心报价
        $total_cost = $main_fabric_total = $accessory_fabric_total=$process_total=$fees_total=$unit_price= '';
        if(!empty($cost_arr['main_fabric'])){
            foreach($cost_arr['main_fabric'] as $each_v){
                $main_fabric_total = (float)$main_fabric_total + (float)$each_v['total'];
                $main_fabric_total = round($main_fabric_total,2);
            }
        }
        if(!empty($cost_arr['accessory_fabric'])){
            foreach($cost_arr['accessory_fabric'] as $each_v){
                $accessory_fabric_total = (float)$accessory_fabric_total + (float)$each_v['total'];
                $accessory_fabric_total = round($accessory_fabric_total,2);
            }
        }
        //外发工艺成本
        if(!empty($cost_arr['process'])){
            foreach($cost_arr['process'] as $each_v){
                $process_total = (float)$process_total + (float)$each_v['total'];
                $process_total = round($process_total,2);
            }
        }
        if(!empty($cost_arr['fees'])){
            foreach($cost_arr['fees'] as $each_v){
                //加工费总额
                $fees_total = (float)$fees_total + (float)$each_v['total'];
                $fees_total  = round($fees_total,2);
                //获取加工费价格
                if($each_v['cost_type'] == 4 && $each_v['cost_name']=='加工费'){
                    $unit_price = (float)$unit_price + (float)$each_v['total'];
                    $unit_price  = round($unit_price,2);
                }
            }
        }
        $total_cost =  (float)$main_fabric_total+(float)$accessory_fabric_total+(float)$process_total+(float)$fees_total;
        $total_cost = round($total_cost,2);//获取核算中心报价

        if(empty($total_cost)){
            return $order_price;
        }
        $cost = 0;
        if(!empty($total_cost) && $category == 4){//CMT
            //SIS印花价格
            $sis_price = $this->getSisCost($produce_id);
            if(($unit_price + $process_total) != 0){
                $cost = (float)($unit_price + $process_total - $sis_price);
                //判断备货类型和下单数量
                if($prepare_type == 4 && $order_count > 2000 ){
                    $order_price = (float)($unit_price * 0.92 + $process_total - $sis_price);
                }elseif($prepare_type == 4 && $order_count > 500 && $order_count<= 2000){
                    $order_price = (float)($unit_price * 0.95 + $process_total - $sis_price);
                }elseif($prepare_type != 4 || $order_count <= 500 ){
                    $order_price = (float)($unit_price + $process_total - $sis_price);
                }
            }else{
                $order_price = '';
            }
        }
        if(!empty($total_cost) && $category == 3){//FOB
            $cost = $total_cost;
           if($prepare_type != 4 || $order_count <= 500 ){
               $order_price = $total_cost;
           }elseif($prepare_type == 4 && $order_count > 500 && $order_count<= 1000){
               $order_price = (float)($total_cost - $unit_price * 0.1);
           }elseif($prepare_type == 4 && $order_count > 1000 && $order_count<= 2000){
               $order_price = (float)($total_cost - $unit_price * 0.11);
           }elseif($prepare_type == 4 && $order_count > 2000){
               $order_price = (float)($total_cost - $unit_price * 0.13);
           }
        }
        return array(
            'cost'=>$cost,
            'order_price'=>$order_price,
        );
    }

    /**
     * 获取SIS印花价格
     * @param $produce_id
     * @return float|string
     *
     * @author 周金剑 2017-06-01 14:00:00
     */
    public function getSisCost($produce_id)
    {
        $sis_price = '';
        $sis_cost = D('ProduceProject')->getProduceFabric("produce_id = $produce_id", true);
        if(!empty($sis_cost)){
            foreach($sis_cost as $each_v){
                //获取sis印花价格
                if($each_v['supplier'] == 'SIS印花'){
                    $sis_price += (float)($each_v['big_price'] * $each_v['single_dosage']);
                }
            }
        }
        $sis_price  = round($sis_price,2);

        return $sis_price;
    }
    /**
     * 向接口发送请求，获取商品信息
     * @param string $goods_sn  sku
     * @param bool $return_beihuo_attr  是否获取备货数据
     * @return array
     * @author  唐亮 2017-06-09 11:20:00
     */
    public function getGoodsInfoByGoodsSn($goods_sn = '',$return_beihuo_attr = false){
        //根据SKU获取对应商品的详细信息，访问商城接口
        $site = D('GoodsManage')->getSiteOfGoods($goods_sn);
        $goods_info = $param_goods =  array();//初始化
        if($site){
            $param_goods['action'] = 'getGoodsInfo';//接口方法
            $param_goods['return_beihuo_attr'] = $return_beihuo_attr;//接口参数
            $param_goods['goods_sn'] = array('goods_sn'=>$goods_sn);//接口参数
            $goods_info = website_api($site,C('CURL_URI_UPDATE_GOODS_WEIGHT_INFO'),$param_goods);//向接口发送请求，获取商品信息
        }
        return $goods_info;
    }

    /**
     * 下单时获取加工厂id和生产跟单员
     * @param string $goods_sn  SKU
     * @return array
     * @author  唐亮 2017-06-26 14:16:55
     * @modify  李永旺 2018-05-16 11:36:00
     */
    public function getSupplierIdFollower($goods_sn='',$prepare_type=0){
        //根据SKU获取对应商品的详细信息，访问商城接口
        // $goods_info = $this->getGoodsInfoByGoodsSn($goods_sn);//向接口发送请求，获取商品信息
        $goods_info = D('ProduceProjectOrder')->getSupplierBindBySku($goods_sn);
        //初始化供应商id和生产跟单员
        $final_supplier_id = 0;
        $final_supplier_follower = $final_factory = '';
        if($goods_info['success'] == 0){
            return array('final_supplier_id'=>$final_supplier_id,'final_supplier_follower'=>$final_supplier_follower,'final_factory'=>$final_factory);
        }
        //获取sku的对应供应商ID
        // $supplierId = $goods_info['content'][$goods_sn]['supplier_id'];
        $supplierId=$goods_info['content']['info'][0]['supplierId'];//供应商id
        if($supplierId){
            //查询供应商详细信息
            $sqlResult = M('SupplierInfo')->getById($supplierId);
            if(!empty($sqlResult) && $sqlResult['is_delete']<2){
                $final_supplier_id = $sqlResult['id'];
                $final_supplier_follower = $sqlResult['follower'];
                $final_factory = $sqlResult['title'];
            }
        }
        return array('final_supplier_id'=>$final_supplier_id,'final_supplier_follower'=>$final_supplier_follower,'final_factory'=>$final_factory);
    }


    /**
     * 订单获取物料信息以及物料卡信息
     * @param $goods_sn
     * @return array
     *
     * @author  周金剑 2017-06-07 15:00:00
     */
    public function getOrderProduceAndMaterialInfo($goods_sn)
    {
        $result = array();
        if(empty($goods_sn)){
            $result = array(
                'code' => 1,
                'msg' => '参数错误',
                'info' => ''
            );
            return $result;
        }
        if(!is_array($goods_sn)){
            $goods_sn = array($goods_sn);
        }
        $info =  $styles = $tmp = array();
        //根据sku获取produce信息
        // $produce_info_tmp = D('ProduceProject')->getProduceInfoBySku($goods_sn,'id,style,goods_sn,fabric_type,needle_method');
        $produce_info_tmp = D('ProduceProject')->getProduceStyleInfoBySku($goods_sn,'id,design_style,goods_sn');
        $produce_info = formart_arr_index($produce_info_tmp, array('goods_sn'));
        //获取设计款号
        foreach ($goods_sn as $v) {
            $res=array();
            $res=D('ProduceProject')->getStyleBySku($v);
            if ($res['design_style']) {
                $styles[] =$res['design_style'];
                $tmp[$v] = $res['design_style'];
            }
        }
        foreach($produce_info as $key => &$val){
                $val['style'] = $tmp[$key];
        }
        //获取物料的物料卡信息
        $material_card_info_tmp = D('MaterialCard')->getMaterialCardInfoByStyle($styles,'style, bin_num,status');
        $material_card_info = formart_arr_index($material_card_info_tmp, array('style'));
        $info = $this->formatData($produce_info,$material_card_info);
        $result = array(
            'code' => 0,
            'msg' => '',
            'info' => $info
        );

        return $result;

    }

    /**
     * 拼装数据
     * @param $produce_info
     * @param $material_card_info
     * @return array
     *
     * @author  周金剑 2017-06-07 16:00:00
     */
    public function formatData($produce_info,$material_card_info)
    {
        $needle_method = array(
        1=>'针织',
        2=>'梭织',
        3=>'针织拼梭织'
        );
        $fabric_type = array(
            '1' => '市场物料',
            '2' => '工厂物料',
        );
        $material_card_status = array(
            1 => '在库',
            6 => '领用',
            7 => '出库',
            8 => '在库'
        );
        $result = array();
        foreach($produce_info as $key => $value){
            //物料类型和针梭织类型
            $result[$key] = array(
                'fabric_type' => !empty($fabric_type[$value['fabric_type']]) ? $fabric_type[$value['fabric_type']] : '' ,
                'needle_method' =>!empty($needle_method[$value['needle_method']]) ? $needle_method[$value['needle_method']] : '',
            );
            $material_card_tmp = $material_card_info[$value['style']];
            //物料卡信息
            $material_card = array(
                'style' => $material_card_tmp['style'],
                'bin_num' => $material_card_tmp['bin_num'],
                'status' => !empty($material_card_status[$material_card_tmp['status']]) ? $material_card_status[$material_card_tmp['status']] : ''
            );
            $result[$key]['material_card'] = $material_card;
        }
        return $result;
    }

    /**
     * 获取要求入库时间
     * @access public
     * @return array
     * @param $goods_sn string
     * @author 葛振 2017-05-09 15:36:05
     */
    public function getProduceTime($goods_sn='')
    {
        //去供应商绑定中心获取供应商ID
        $info = $this->getSupplierIdBySku(array($goods_sn));
        // print_r($info);exit;
        if($info['success']){
            $supplierId = $info['content'][0]['supplierId'];
        }else{
            return array('success'=>0,'message'=>$info['message']);
        }
        $res = R('Prepare/getProduceTimeAPI',array($goods_sn,$supplierId));
        if($res['success']){
            return array('success'=>1,'content'=>$res['content']);
        }else{
            return array('success'=>0,'message'=>$res['message']);
        }
    }

    /**
     * 获取生产计划
     * @access public
     * @return array
     * @param $goods_sn string
     * @author 李永旺 2018-04-23 15:36:05
     */
    public function getProduceSchedule($goods_sn='',$count_order_num=0)
    {
      // 获取生产组 来自基础档案款式生产信息的生产组
        $where_goods_sn['goods_sn']=$goods_sn;
        $where_goods_sn['is_delete']=0;
        $produce_group_info = M("produce_style_info")->where($where_goods_sn)->order(array('add_time'=>'desc'))->find(); 
        if($produce_group_info['produce_group']){
            $where=array();
            $where['set_datetime']=strtotime(date('Y-m-d',time()));
            $where['produce_group']=$produce_group_info['produce_group'];
             $produce_schedule_info=M("produce_schedule_info")->where($where)->order(array('add_time'=>'desc'))->find();
        }
        if ($produce_schedule_info) {
           $percentage=(($produce_schedule_info['actual_order_num']+$count_order_num-$produce_schedule_info['pre_order_num'])/($produce_schedule_info['pre_order_num']))*100;//数据库中计算出来的百分比
            $myfile = fopen("Public/setProduceSchedulePercentage.txt", "r") or die("Unable to open file!");
            $percentage_read=fread($myfile,filesize("Public/setProduceSchedulePercentage.txt"));//设定的生产计划百分比
            fclose($myfile);
        }

        if ($percentage > $percentage_read) {
              return array('success'=>0,'message'=>'当日下单量已超过预计下单量的比值，是否继续下单','data'=>array('pre_order_num'=>$produce_schedule_info['pre_order_num'],'actual_order_num'=>$produce_schedule_info['actual_order_num']));
        }else{
              return array('success'=>1,'content'=>'添加成功！','data'=>array('pre_order_num'=>$produce_schedule_info['pre_order_num'],'actual_order_num'=>$produce_schedule_info['actual_order_num']));
        }
        // if($res['success']){
        //     return array('success'=>1,'content'=>$res['content']);
        // }else{
        //     return array('success'=>0,'message'=>$res['message']);
        // }
    }
        /**
     * 获取生产计划 运营备货用
     * @access public
     * @return array
     * @param $goods_sn string
     * @author 李永旺 2018-04-23 15:36:05
     */
    public function getProduceScheduleInfo($goods_sn='',$count_order_num=0)
    {
      // 获取生产组 来自基础档案款式生产信息的生产组
        $where_goods_sn['goods_sn']=$goods_sn;
        $where_goods_sn['is_delete']=0;
        $produce_group_info = M("produce_style_info")->where($where_goods_sn)->order(array('add_time'=>'desc'))->find(); 
        if($produce_group_info['produce_group']){
            $where=array();
            $where['set_datetime']=strtotime(date('Y-m-d',time()));
            $where['produce_group']=$produce_group_info['produce_group'];
             $produce_schedule_info=M("produce_schedule_info")->where($where)->order(array('add_time'=>'desc'))->find();
        }
        if ($produce_schedule_info) {
           $percentage=(($produce_schedule_info['actual_order_num']+$count_order_num-$produce_schedule_info['pre_order_num'])/($produce_schedule_info['pre_order_num']))*100;//数据库中计算出来的百分比
            $myfile = fopen("Public/setProduceSchedulePercentage.txt", "r") or die("Unable to open file!");
            $percentage_read=fread($myfile,filesize("Public/setProduceSchedulePercentage.txt"));//设定的生产计划百分比
            fclose($myfile);
        }
        $data=array(
            'pre_order_num'=>isset($produce_schedule_info['pre_order_num'])?$produce_schedule_info['pre_order_num']:0,//预计下单数量
            'actual_order_num'=>$produce_schedule_info['actual_order_num'],//实际下单数量
            'percentage_read'=>$percentage_read,//设定参数百分比
            'spare_order_num'=>($produce_schedule_info['pre_order_num']*(1+($percentage_read/100))-$produce_schedule_info['actual_order_num']),//剩余下单数量
            );
        if (!empty($data)) {
            return array('success'=>1,'content'=>'添加成功！','data'=>$data);
              
        }else{
           return array('success'=>0,'message'=>'暂无数据！','data'=>$data);   
        }

        return $data;

    }
    /**
     * 根据SKU获取供应商ID
     * @access public
     * @return array
     * @param $goods_sn array
     * @author 葛振 2017-05-09 15:36:05
     * @modify 陈东 2017-8-5 10:00:58 站点更换
     */
    public function getSupplierIdBySku($goods_sn= array()){
        $uri = C('SUPPLIER_ID_FOR_SKU');
        $param = array(
            'sku' => $goods_sn
        );
        $res =  website_api(6, $uri, $param);
        if($res['success']){
            return array('success'=>1,'content'=>$res['content']['info']);
        }else{
            return array('success'=>0,'message'=>'未获取到供应商');
        }
    }

    /**
     * 下单跟进中判断是否更新或者添加质检报告
     * @access public
     * @return array
     * @param $qc_data array
     * @author 葛振 2017-05-09 15:36:05
     * @modify 葛振 2017-07-31 17:40:50 生产大货QC流程变更
     */
    public function produceReportAddOrUpdate($qc_data){
        $res_report = M('produce_order_qc_report')->WHERE('produce_order_id = '. $qc_data['produce_order_id'] .' and type = '.$qc_data['type'])->getField('report_id');
        if(empty($res_report)){
            $commit[] = $report_id = $this->table($this->tablePrefix.'produce_order_qc_report')->add($qc_data);
        }else{
            $data['status'] = 0;
            $commit[] = M('produce_order_qc_report')->WHERE(' report_id = '.$res_report)->save($data);
            $report_id = $res_report;
        }
        $add_status = array(
            'report_id' => $report_id,
            'status' => 1,
            'admin_name' => I('session.admin_name'),
            'add_time' => time(),
        );
        $commit[] = D('InventoryQcReport')->addReportStatus(array($add_status));
        return $commit;
    }

    /**
     * 下单跟进修改生产组
     * @param $param 参数
     * @return array
     *
     * @author 葛振 2017-07-27 15:32:24
     */
    public function updateProduceTeam($param){

        $where['produce_order_id'] = $param['id'];
        $data['produce_team'] = trim($param['produce_team']);

        $res = M('produce_order')->where($where)->save($data);
        if($res){
            return array('success'=>1,'content'=>'操作成功');
        }else{
            return array('success'=>1,'content'=>'操作失败');
        }
    }
     /**
     * 根据sku+供应商+下单数量获取商品价格规则表中折扣率和版本号
     * @access public
     * @param  $produce_order_id  生产订单号
     * @return array
     * @author 靳明杰 2017-8-28 10:33:51
     * @motify 唐亮 2017-12-25 增加软删除标识
     */
    public function getPriceGoodsCost($order){
        if(!$order['supplier_id']){
            return array(
                'success'     =>0,
                'data'        =>'',
                'edition_num' =>''
                );
        }
        $where_rule_goods = array(
            'goods_sn'      =>$order['goods_sn'],
            'supplier_id'   =>$order['supplier_id'],
            'is_used'       =>1
            );
        $res = M('price_goods')->where($where_rule_goods)->find();
        if(!$res){
          //查询供应商默认价格规则列表
            $res = M('price_supplier')->where(array('supplier_id'=>$order['supplier_id'],'is_used'=>1))->find();
            if(!$res){
               return array(
                'success'     =>0,
                'data'        =>'',
                'edition_num' =>''
                ); 
            }
        }
        $order_info = $order["total_order_num"];
        if($order_info == 0){
            $arr = explode('<br/>',$order['order_info']);
            array_pop($arr);
            $order_info = 0;
            foreach ($arr as $key => $value) {
                $arr1 = explode(':',$value);
                $order_info += $arr1[1];
            }
        }
        $where_rule_level = array(
            'price_rule_id' =>$res['price_rule_id'],
            'is_delete'=>0,
            'min_num <= '.$order_info.' AND max_num >= '.$order_info,
            );
        $rule_level = M('price_rule_level')->where($where_rule_level)->find();
       
        if($rule_level){
             $price_rule_edition = M('price_rule_edition')->where(array('price_rule_id' =>$res['price_rule_id'],'price_rule_level_id'=>$rule_level['id']))->order('id desc')->find();
            $edition_num = 'R'.str_pad($res['price_rule_id'], 3, "0", STR_PAD_LEFT).'.'.$price_rule_edition['edition_num'];
            return array(
                'success'      => 1,
                'data'         =>(rtrim($rule_level['discount'],'%'))/100,
                'edition_num' =>$edition_num,
                );
        }
        return array(
                'success'     =>0,
                'data'        =>'',
                'edition_num' =>''
                );
    }
    /**
     * 根据SKU获取供应商sku绑定关系
     * @access public
     * @return array
     * @param $goods_sn array
     * @author 靳明杰 2017-09-08 15:56:05
     */
    public function getSupplierBindBySku($goods_sn){
        $uri = '/branchGetSuppierId';
         if(is_array($goods_sn)){
            $param = array(
                'sku' => $goods_sn
            );
         }else{
            $param = array(
                'sku' => array($goods_sn)
            );
         }
        $res =  website_api(6, $uri, $param);
        if($res['success'] && isset($res['content']['info'][0]['supplierId']) && $res['content']['info'][0]['supplierId'] > 0){
            return array('success'=>1,'content'=>$res['content']);
        }else{
            return array('success'=>0,'message'=>'未获取到供应商');
        }
    }
    /**
     * 根据SKU获取商品尺码
     * @access public
     * @return array
     * @param $goods_sn array
     * @author 靳明杰 2017-09-08 15:56:05
     */
    public function getGoodsSize($sku){
       $uri = C('CURL_URI_GET_PRODUCT_SIZE_ATTRIBUTE');
       if(is_array($sku)){
            $params = array(
                'sku_arr'=>$sku,
                 "language" => "en",
                );
       }else{
        $params = array(
                'sku_arr'=>array($sku),
                 "language" => "en",
                );
       }
        $res = pdcApiRequest($uri,$params);//向商品中心请求接口
        if(isset($res['data'][0]['sku']) && $res['data'][0]['sku'] != ''){
            return array('success'=>1,'content'=>$res['data']);
        }else{
            return array('success'=>0,'message'=>'未获取到商品尺码');
        }
    }
    /**
     * 根据SKU获取商品中心批量获取商品信息
     * @access public
     * @return array
     * @param $goods_sn array
     * @author 靳明杰 2017-09-08 15:56:05
     * @modify 唐亮 2017-10-17 14:20:00 修改批量下单数据获取源
     */
    public function getGoodsInfoBySku($skuList){
        //获取商品信息
        $goods_size = $this->getGoodsAllInfoSize($skuList);
        if($goods_size['success'] == 0){
            return array(
                'success' => false,
                'errorMsg' => '未获取到商品中心尺码',
            );
        }
        $arr = array();
        foreach ($skuList as $val) {
            foreach ($goods_size['content'] as $value) {
                if($value['product_info']['sku'] == $val){
                    $arr[$val]['goods_thumb'] = isset($value['product_image']['image_url'])?$value['product_image']['image_url']:'http://img.ltwebstatic.com/images/pi/201709/e3/15060714244665366417.jpg';
                    $arr[$val]['goods_sn'] = $val;
                    $arr[$val]['beihuo_attr']['goods_sn'] = $val;
                    $arr[$val]['beihuo_attr']['attr_value'] = array_column($value['product_size_attribute'],'name');
                    //获取商品供应商绑定的信息
                    $supplier_linkman = $this->getSupplierBindBySku($val);
                    $arr[$val]['supplier_linkman'] = isset($supplier_linkman['content']['info'][0]['supplierName']) ? $supplier_linkman['content']['info'][0]['supplierName'] : '';
                }
                continue;
            }
        }
        if($arr){
            return array('success'=>1,'data'=>$arr);
        }
    }
    /**
     * 根据SKU获取商品尺码
     * @access public
     * @return array
     * @param $sku array
     * @author 唐亮 2017-09-08 15:56:05
     */
    public function getGoodsAllInfoSize($sku){
        $uri = C('CURL_URI_GET_PRODUCT_ALL_INFO');
        if(is_array($sku)){
            $params = array(
                'sku_arr'=>$sku,
            );
        }else{
            $params = array(
                'sku_arr'=>array($sku),
            );
        }
        $res = pdcApiRequest($uri,$params);//向商品中心请求接口
        if(isset($res['data'][0]['product_info']['sku']) && $res['data'][0]['product_info']['sku'] != ''){
            return array('success'=>1,'content'=>$res['data']);
        }else{
            return array('success'=>0,'message'=>'未获取到商品信息');
        }
    }
    /**
     * 核价异议表
     * 
     * @author 靳明杰 2017年10月21日 下午16:33:39
     */
    public function getPriceDissentList($filter){
        $filter = page_and_size($filter);
        $where = array();
        $is_diff = false;
        $where_produce = array();
        $new_produce_order_ids = array();
        if($filter['factory']){        //搜索供应商名字
            //根据供应商名称获取供应商信息
            $supplier_res = R('supplierInfo/getSupplierInfoBySupplierName',array($filter ['factory'],'id',false));
            if($supplier_res['success']){
                $supplier_id = array_column($supplier_res['content'],'id');
                $where['supplier_id'] = array('in',implode(',',$supplier_id));
            }else{
                return array ('arr' => array(), 'filter' => $filter, 'page_count' => 1, 'record_count' => 0 );
            }
        }
        //获取相应的订单跟单和生产组
         if($filter['produce_merchandiser']){           //搜索生产跟单员
            $where_produce['produce_merchandiser'] = $filter['produce_merchandiser'];
        }
        //如果是生产跟单员,只能看到自己的单
        $admin_role = I("session.admin_role");
        if($admin_role == 54){
            $where_produce['produce_merchandiser'] = session('admin_name');
        }
        if($filter['produce_team']){           //搜索生产组
            $where_produce['produce_team'] = $filter['produce_team'];
        }
        if($where_produce['produce_team'] || $where_produce['produce_merchandiser']){
            $where_produce['is_delete'] = 0 ;
           $produce_order_ids = M('produce_order')->where($where_produce)->Field('produce_order_id')->select();
           if($produce_order_ids){
                foreach ($produce_order_ids as $key => $value) {
                    $new_produce_order_ids[] = $value['produce_order_id'];
                } 
           }
        }
        if($filter['goods_sn']){           //搜索sku
            $where['goods_sn'] = $filter['goods_sn'];
        }
        if($filter['is_diff']){           //搜索s是否报价差异单
            $is_diff = true;
            $where['is_diff'] = $filter['is_diff'];
        }
        if($filter['produce_order_id']){           //搜索訂單编号
            $where['produce_order_id'] = $filter['produce_order_id'];
        }else{
            if($new_produce_order_ids){
                $where['produce_order_id'] = array('in',$new_produce_order_ids);
            }
        }
        if($filter['id']){           //搜索核价异议单号
            $where['id'] = $filter['id'];
        }
        if($filter['status'] && $filter['status'] != 0){           //搜索状态
            $where['status'] = $filter['status'];
        }
        if($filter['start_date']){
            $where['add_time'][] = array('gt',strtotime($filter['start_date']));
        }
        if($filter['end_date']){
            $where['add_time'][] = array('lt',strtotime($filter['end_date']));
        }
        if($filter['is_deal'] && $filter['is_deal'] !=2){
            $where['is_deal'] = $filter['is_deal'];
        }elseif($filter['is_deal'] === '0'){
             $where['is_deal'] = $filter['is_deal'];
        }
        
        // print_r($where);die;
        if(!$where){
            return array('arr' => array(),'log_data'=>array(),'remark_data'=>array(),'assessment_log'=>array(), 'filter' => array(), 'page_count' => 0, 'record_count' => 0 );
        }
        $record_count = M()->table($this->tablePrefix.'price_dissent')->where($where)->count();
        $start_page = ($filter['page'] - 1) * $filter['page_size'];
        $list = M()->table($this->tablePrefix.'price_dissent')->where($where)->limit($start_page,$filter['page_size'])->order('id DESC')->select();          //全部列表
        $supplier_ids = array_column($list,'supplier_id');
        $ids  = array_column($list, "id");
        $goods_sns  = array_column($list, "goods_sn");
        $produce_list = M('produce')->where(array('goods_sn'=>array('in',$goods_sns),'is_delete'=>0))->getField('goods_sn,id',true);
        $produce_order_id_arr  = array_column($list, "produce_order_id");
        $produce_order_id_arr = M('produce_order')->where(array('produce_order_id'=>array('in',$produce_order_id_arr)))->Field('produce_order_id,produce_merchandiser,produce_team,category')->select();
        foreach ($produce_order_id_arr as $key => $value) {
            $produce_team[$value['produce_order_id']] = $value['produce_team'];
            $category[$value['produce_order_id']] = $value['category'];
            $produce_merchandiser[$value['produce_order_id']] = $value['produce_merchandiser'];
        }
        $res_log = $this->getLogData($ids);
        $res_remark = $this->getRemarkData($ids);
        $supplier_arr = D('PriceRule')->getSupplierNamesByIds($supplier_ids);
        $max = 0;
        $type = array(
                '1'=>'加工费升降',
                '2'=>'面辅料费用升降',
                '3'=>'二次工艺费用升降',
                '4'=>'换档口',
                '5'=>'其他',
            );   
        $order_category = array(
                '3'=>'FOB',
                '4'=>'CMT'
            ); 
        $order_status = array(
                '6'=>'分配中',
                '13'=>'订料中',
                '7'=>'裁剪中',
                '8'=>'车缝中',
                '21'=>'后整中',
            );
        $assessment = array(
            0=>'未评价',
            1=>'满  意',
            2=>'不满意',
        );
        $is_deal = array(
            0=>'处  理',
            1=>'已处理',
        );
        //统计发起次数   
        $apply_count_sql = 'SELECT produce_order_id,type, count(1) AS counts FROM '.$this->tablePrefix.'price_dissent GROUP BY produce_order_id,type';
        $apply_count = $this->getAll($apply_count_sql);
        foreach ($list as $k => $v) {
            foreach ($apply_count as $key => $value) {
                if(($v['produce_order_id'] == $value['produce_order_id']) && ($v['type'] == $value['type'])){
                    $list[$k]['apply_count'] = $value['counts'];
                }
            }
            //添加供应商名称
            $list[$k]['supplier_linkman'] = $supplier_arr[$v['supplier_id']];//供应商
            $list[$k]['status_name'] = $this->price_dissent_status[$v['status']];//状态
            $list[$k]['photo'] = json_decode(htmlspecialchars_decode($v['photo']),true);
            $list[$k]['info'] = objectToArray(json_decode(htmlspecialchars_decode($v['info'])));
            $list[$k]['produce_merchandiser'] = $produce_merchandiser[$v['produce_order_id']];
            $list[$k]['produce_team'] = $produce_team[$v['produce_order_id']];
            $list[$k]['category'] = $category[$v['produce_order_id']];
            $list[$k]['produce_id'] = $produce_list[$v['goods_sn']];
            foreach ($list[$k]['photo'] as $key => $value) {
                $list[$k]['photo'][$key] = C('GEIWOHUO_HOST').$value;
            }
            if($v['type'] != '5'){
                $list[$k]['type_name'] = $type[$v['type']];
            }
            $list[$k]['apply_time'] = $order_category[$v['order_category']].'-'.$order_status[$v['order_status']];
            $list[$k]['id_num'] = str_pad($v['id'], 3, "0", STR_PAD_LEFT);
        }
        if($is_diff){
            //报价差异单
            //获取评价
            $assessment_log = $this->getPriceDissentAssessment($ids);
            // print_r($assessment_log);die;
            foreach ($list as $key => $value) {
                  $list[$key]['diff_price'] = abs($value['new_price_total'] - $value['estimate_price']);
                  $list[$key]['factory_assessment'] = $assessment[$value['is_pleased']];
                  $list[$key]['deal_name'] = $is_deal[$value['is_deal']];
              }  
        }
        // print_r($list);die;
        $page_count = $record_count > 0 ? ceil( $record_count / $filter['page_size'] ) : 1;// 分页页数
        return array ('arr' => $list,'log_data'=>$res_log,'remark_data'=>$res_remark,'assessment_log'=>$assessment_log, 'filter' => $filter, 'page_count' => $page_count, 'record_count' => $record_count );
    }
     /**
     * 获取核价异议供应商评价
     *
     * @author 靳明杰 2017-10-1 15:09
     */
     public function getPriceDissentAssessment($ids){
        $ids  = implode(",", $ids);
        $where_log = array(
                "price_dissent_id" => array("in",$ids),
            );

        $data_log  = M("price_dissent_assessment")->where($where_log)->select();
        $res_log   = array();
        foreach($data_log as $val){
            $strContent  = $val["assessment"].'<br>        '.date("Y-m-d H:i:s",$val["add_time"]);
            $res_log[$val["price_dissent_id"]][] = "<p>".$strContent."</p>";
        }
        return $res_log;
     }
    /**
     * 获取核价异议操作记录
     *
     * @author 靳明杰 2017-10-1 15:09
     */
     public function getLogData($ids){
        $ids  = implode(",", $ids);
        $where_log = array(
                "price_dissent_id" => array("in",$ids),
            );

        $data_log  = M("price_dissent_status")->where($where_log)->select();
        $res_log   = array();
        foreach($data_log as $val){
            $strContent  = date("Y-m-d H:i:s",$val["add_time"])." ".$this->price_dissent_status_apply[$val["status"]];
            $res_log[$val["price_dissent_id"]][] = "<p>".$strContent."</p>";
        }
        return $res_log;
     }
     /**
     * 获取核价异议备注
     *
     * @author 靳明杰 2017-10-1 15:09
     */
     public function getRemarkData($ids){
        $ids  = implode(",", $ids);
        $where = array(
                "price_dissent_id" => array("in",$ids),
            );

        $data_log  = M("price_dissent_remark")->where($where)->select();
        $res_log   = array();
        foreach($data_log as $val){
            $strContent  = $val['operator']." ".date("Y-m-d H:i:s",$val["time"])." ".$val["remark_info"];
            $res_log[$val["price_dissent_id"]][] = "<p>".$strContent."</p>";
        }
        return $res_log;
     }
     /**
     * ajax获取上传图片
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function getPriceDissentPhoto($id){
        $arr = array();
        $price_dissent_info = M('price_dissent')->where(array('id'=>$id))->find();
        if($price_dissent_info){
            $photo = json_decode(htmlspecialchars_decode($price_dissent_info['photo']),true);
            foreach ($photo as $key => $value) {
                $arr[$key] = C('GEIWOHUO_HOST').$value;
            }
        }
        if($arr){
            return array('code'=>0,'data'=>$arr);
        }else{
            return array('code'=>1,'message'=>'未获取到图片信息');
        }
        
    }
    /**
     * ajax获取异议详情
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function getPriceDissentInfo($id){
        $arr = array();
        $new_price = array();
        $type = '';
        $type_name = '';
        $price_dissent_info = M('price_dissent')->where(array('id'=>$id))->find();
        if($price_dissent_info){
            $arr = objectToArray(json_decode(htmlspecialchars_decode($price_dissent_info['info'])));
            $new_price = json_decode(htmlspecialchars_decode($price_dissent_info['new_price']));  
            $new_price_total = $price_dissent_info['new_price_total'] ? $price_dissent_info['new_price_total'] : '';
            $estimate_price = $price_dissent_info['estimate_price'] ? $price_dissent_info['estimate_price'] : '';
             // print_r($arr);die;
            if(!$new_price){
                foreach ($arr as $k => $v) {
                    if(is_array($v)){
                        $new_price[$k] = '';
                        $arr[$k]['cut_info'] = $v['cut_info']?htmlspecialchars_decode($v['cut_info']):'';
                        $arr[$k]['name'] = $v['name'] ? $v['name'] : '';
                        $arr[$k]['material_code'] = $v['material_code'] ? $v['material_code'] : '';
                        $arr[$k]['composition'] = $v['composition'] ? $v['composition'] : '';
                        $arr[$k]['wide'] = $v['wide'] ? $v['wide'] : '';
                        $arr[$k]['crafts'] = $v['crafts'] ? $v['crafts'] : '';
                        $arr[$k]['bulk_shear_price'] = $v['bulk_shear_price'] ? $v['bulk_shear_price'] : '';
                        $arr[$k]['space_diff'] = $v['space_diff'] ? $v['space_diff'] : '';
                        $arr[$k]['big_price'] = $v['big_price'] ? $v['big_price'] : '';
                        $arr[$k]['grams'] = $v['grams'] ? $v['grams'] : '';
                        $arr[$k]['net_dosage'] = $v['net_dosage'] ? $v['net_dosage'] : '';
                        $arr[$k]['loss'] = $v['loss'] ? $v['loss'] : '';
                        $arr[$k]['single_dosage'] = $v['single_dosage'] ? $v['single_dosage'] : '';
                        $arr[$k]['unit'] = $v['unit'] ? $v['unit'] : '';
                        $arr[$k]['address'] = $v['address'] ? $v['address'] : '';
                        $arr[$k]['yangyi_price'] = $v['yangyi_price'] ? $v['yangyi_price'] : '';
                        $arr[$k]['mul_type'] = $v['mul_type'] ? $v['mul_type'] : '';
                        $arr[$k]['goods_name'] = $v['goods_name'] ? $v['goods_name'] : '';
                        $arr[$k]['number'] = $v['number'] ? $v['number'] : '';
                        $arr[$k]['one_price'] = $v['one_price'] ? $v['one_price'] : '';
                        $arr[$k]['input_unit'] = $v['input_unit'] ? $v['input_unit'] : '';
                        $arr[$k]['telephone'] = $v['telephone'] ? $v['telephone'] : '';
                    }else{
                        $new_price[] = '';
                    }
                }
            }else{
            foreach ($arr as $k => $v) {
                if(is_array($v)){
                    $arr[$k]['cut_info'] = $v['cut_info']?htmlspecialchars_decode($v['cut_info']):'';
                    $arr[$k]['name'] = $v['name'] ? $v['name'] : '';
                    $arr[$k]['material_code'] = $v['material_code'] ? $v['material_code'] : '';
                    $arr[$k]['composition'] = $v['composition'] ? $v['composition'] : '';
                    $arr[$k]['wide'] = $v['wide'] ? $v['wide'] : '';
                    $arr[$k]['crafts'] = $v['crafts'] ? $v['crafts'] : '';
                    $arr[$k]['bulk_shear_price'] = $v['bulk_shear_price'] ? $v['bulk_shear_price'] : '';
                    $arr[$k]['space_diff'] = $v['space_diff'] ? $v['space_diff'] : '';
                    $arr[$k]['big_price'] = $v['big_price'] ? $v['big_price'] : '';
                    $arr[$k]['grams'] = $v['grams'] ? $v['grams'] : '';
                    $arr[$k]['net_dosage'] = $v['net_dosage'] ? $v['net_dosage'] : '';
                    $arr[$k]['loss'] = $v['loss'] ? $v['loss'] : '';
                    $arr[$k]['single_dosage'] = $v['single_dosage'] ? $v['single_dosage'] : '';
                    $arr[$k]['unit'] = $v['unit'] ? $v['unit'] : '';
                    $arr[$k]['address'] = $v['address'] ? $v['address'] : '';
                    $arr[$k]['yangyi_price'] = $v['yangyi_price'] ? $v['yangyi_price'] : '';
                    $arr[$k]['mul_type'] = $v['mul_type'] ? $v['mul_type'] : '';
                    $arr[$k]['goods_name'] = $v['goods_name'] ? $v['goods_name'] : '';
                    $arr[$k]['number'] = $v['number'] ? $v['number'] : '';
                    $arr[$k]['one_price'] = $v['one_price'] ? $v['one_price'] : '';
                    $arr[$k]['input_unit'] = $v['input_unit'] ? $v['input_unit'] : '';
                    $arr[$k]['telephone'] = $v['telephone'] ? $v['telephone'] : '';
                }
            }
        }
            $type = $price_dissent_info['type'];
            $type_name = $price_dissent_info['type_name'];
        }
        if($type == 1){
                $arr['old_price'] = $arr['old_price'] ? $arr['old_price'] : '';
                $arr['factory_price'] = $arr['factory_price'] ? $arr['factory_price'] : '';
        }
        if($arr){
            return array('code'=>0,'info'=>$arr,'new_price'=>$new_price,'new_price_total'=>$new_price_total,'type'=>$type,'type_name'=>$type_name,'estimate_price'=>$estimate_price);
        }else{
            return array('code'=>1,'message'=>'未获取到异议详情信息');
        }
        
    }
    /**
     * ajax添加备注
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function addPriceDissentRemark($params){
        if($params){
            $content = $params['content'];
            $price_dissent_id = $params['id'];
            $this->startTrans();// 事务开启
            $data = array(
                'price_dissent_id' =>$price_dissent_id,
                'operator' =>session('admin_name'),
                'time' =>time(),
                'remark_info' =>$content,
                'type' =>0,
            );
            $commit = M('price_dissent_remark')->add($data);
            if(db_commit($commit)){
                    $remark_data = session('admin_name').' '.date('Y-m-d-H-i-s').' '.$content;
                return array('code'=>0,'message'=>'添加成功','data'=>$remark_data);
            }else{
                return array('code'=>1,'message'=>'添加失败');
            }
        }else{
            return array('code'=>1,'message'=>'未获取到参数');
        } 
    }
    /**
     * ajax查看备注
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function getPriceDissentRemark($params){
        $id = $params['id'];
        $remark_data = $this->getRemarkData(array($id));
        $str = '';
        if($remark_data){
            foreach ($remark_data[$id] as $k=>$value) {
                $str .= $value;
            }
        }else{
            $str = '暂无备注信息';
        }
        return array(
            'code'=>0,
            'mes'=>$str
        );
    }
    /**
     * ajax处理差异单
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function adddPriceDissentDeal($params){
        $id = $params['id'];
        $content = $params['content'];
        if(strlen(trim($content)) > 150 || strlen(trim($content)) < 0){
            return array(
                'code'=>1,
                'mes'=>'处理结果为必填项，可录入150个以内的字符'
            );
        }
        $res = M('price_dissent')->where(array('id'=>$id))->find();
        if(!$res || $res['is_deal']!=0){
            return array(
                'code'=>1,
                'mes'=>'该差异单已处理,请重新选择'
            );
        }
        $ret = M('price_dissent')->where(array('id'=>$id))->save(array('deal_result'=>$content,'is_deal'=>1));
        if($ret){
            return array(
                'code'=>0,
                'mes'=>'处理成功'
            );
        }else{
            return array(
                'code'=>1,
                'mes'=>'处理失败'
            );
        } 
    }
    /**
     * ajax添加预估价
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function addPriceDissentEstimatePrice($params){
        if($params){
            $params['new_price_total'] = $params['estimate_price'];
            $data = array();
            $id = $params['price_dissent_id'];
            $new_price_total = '';
            $price_dissent = M('price_dissent')->where(array('id'=>$id))->find();
            if(!$price_dissent){
                return array(
                        'code'=>1,
                        'message'=>'核价信息不存在'
                    );
            }
        if(!$params['new_price_total'] || $params['new_price_total'] > 9999 || $params['new_price_total'] < 0.01 || !is_numeric($params['new_price_total'])){
                return array(
                    'code'=>1,
                    'message'=>'预估报价只能录入【0.01至9999内的正数】'
                );
            }
            $new_price_total = round($params['new_price_total'],2);
            $this->startTrans();// 事务开启
            $data = array(
                'status'=>4,
                'estimate_price'=>$new_price_total,
            );
            $data_status = array(
                'price_dissent_id'  =>$id,
                'status'            =>4,
                'admin_name'        =>session('admin_name'),
                'add_time'          =>time(),
            );
            $commit[] = M('price_dissent')->where(array('id'=>$id))->save($data);
            $commit[] = M('price_dissent_status')->add($data_status);
            if(db_commit($commit)){
                return array('code'=>0,'message'=>'已通过');
            }else{
                return array('code'=>1,'message'=>'通过失败');
            }
        }else{
            return array('code'=>1,'message'=>'未获取到参数');
        } 
    }
    /**
     * ajax添加卓天新报价
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function addPriceDissentNewprice($params){
        if($params){
            $data = array();
            $id = $params['price_dissent_id'];
            $num = 0;
            $new_price_total = 0;
            $price_dissent = M('price_dissent')->where(array('id'=>$id))->find();
            if(!$price_dissent){
                return array(
                        'code'=>1,
                        'message'=>'核价信息不存在'
                    );
            }
        if(!$params['new_price_total'] || $params['new_price_total'] > 9999 || $params['new_price_total'] < 0.01 || !is_numeric($params['new_price_total'])){
                return array(
                    'code'=>1,
                    'message'=>'卓天新报价只能录入【0.01至9999内的正数】'
                );
            }
            $new_price_total = round($params['new_price_total'],2);
            $this->startTrans();// 事务开启
            if($new_price_total != 0){
                $produce_order = M('produce_order')->where(array('produce_order_id'=>$price_dissent['produce_order_id']))->find();
                if($produce_order){
                    $order_price = $new_price_total ? $new_price_total : $produce_order['order_price'];
                }
                $commit[] = M('produce_order')->where(array('produce_order_id'=>$price_dissent['produce_order_id']))->save(array('order_price'=>$order_price));
                $data_produce_status = array(
                    'produce_order_id'=>$price_dissent['produce_order_id'],
                    'status'=>19,       //更改实价
                    'user_name'=>session('admin_name'),
                    'add_time'=>time()
                );
                $data_price_status = array(
                    'produce_order_id'=>$price_dissent['produce_order_id'],
                    'new_order_price'=>$order_price,
                    'old_order_price'=>$produce_order['order_price'] ? $produce_order['order_price'] : 0,
                    'status'=>19,       //更改实价
                    'user_name'=>session('admin_name'),
                    'add_time'=>time()
                );
                $commit[] = M('produce_order_status')->add($data_produce_status);
                $commit[] = M('produce_order_price_status')->add($data_price_status);
            }
            if(abs($new_price_total-$price_dissent['estimate_price']) >= 0.5){
                  $data = array(
                    'status'=>2,
                    'new_price_total'=>$new_price_total,
                    'is_diff'=>1
                );  
            }else{
                $data = array(
                    'status'=>2,
                    'new_price_total'=>$new_price_total,
                );
            }
            $data_status = array(
                'price_dissent_id'  =>$id,
                'status'            =>2,
                'admin_name'        =>session('admin_name'),
                'add_time'          =>time(),
            );
            $commit[] = M('price_dissent')->where(array('id'=>$id))->save($data);
            $commit[] = M('price_dissent_status')->add($data_status);
            if(db_commit($commit)){
                return array('code'=>0,'message'=>'已通过');
            }else{
                return array('code'=>1,'message'=>'通过失败');
            }
        }else{
            return array('code'=>1,'message'=>'未获取到参数');
        } 
    }
    /**
     * 拒绝核价异议申请
     *
     * @author 靳明杰  2017-10-20 14:33
     */
    public function refusePriceDissent($params){
        if($params){
            $content = $params['content'];
            $price_dissent_id = $params['id'];
            $this->startTrans();// 事务开启
            $data_save = array(
                'status'=>3
            );
            $data_remark = array(
                'price_dissent_id' =>$price_dissent_id,
                'operator' =>session('admin_name'),
                'time' =>time(),
                'remark_info' =>$content,
                'type' =>0,
            );
            $data_status = array(
                'price_dissent_id' =>$price_dissent_id,
                'status'    =>3,
                'admin_name' =>session('admin_name'),
                'add_time' =>time(),
            );
            $commit[] = M('price_dissent')->where(array('id'=>$price_dissent_id))->save($data_save);
            $commit[] = M('price_dissent_remark')->add($data_remark);
            $commit[] = M('price_dissent_status')->add($data_status);
            if(db_commit($commit)){
                return array('code'=>0,'message'=>'已拒绝');
            }else{
                return array('code'=>1,'message'=>'拒绝失败');
            }
        }else{
            return array('code'=>1,'message'=>'未获取到参数');
        } 
    }



    /**
     * 补料信息存数据库
     * @access public
     * @return array
     * @param $goods_sn array
     * @author 姚法强 2017-09-08 15:56:05
     */
    public function updateProduceFabricInfo($produce_order_info,$produce_fabric_info){
        $supplement=M('price_supplement');
        $produce_order_id['produce_order_id']=$produce_order_info['produce_order_id'];
        $produce=M('Produce_order')->where($produce_order_id)->field('produce_team,status,fabric_purchaser,category')->find();
        if(empty($produce)){
            return array(
                'error' => 200,
                'Msg' => '未获取到面料采购员',
            );
        }elseif(empty($produce_order_info)||empty($produce_fabric_info)){
            return array(
                'error' => 0,
                'Msg' => '数据传输失败',
            );
        }else{
            $order_category=$produce['category'];
            $order_status=$produce['status'];
            // if($produce_order_info['status_name']=='分配中'){
            //     $order_status=6;
            // }elseif($produce_order_info['status_name']=='订料中'){
            //     $order_status=13;
            // }elseif($produce_order_info['status_name']=='裁剪中'){
            //     $order_status=7;
            // }elseif($produce_order_info['status_name']=='车缝中'){
            //     $order_status=8;
            // }elseif($produce_order_info['status_name']=='后整中'){
            //     $order_status=21;
            // }
            $data=array(
                'produce_order_id'=>$produce_order_info['produce_order_id'],
                'goods_sn'=>$produce_order_info['goods_sn'],
                'supplier_id'=>$produce_order_info['supplier_id'],
                'order_category'=>$order_category,
                'order_status'=>$order_status,
                'photo'=>$produce_order_info['photo']?json_encode($produce_order_info['photo']):'',
                'factory'=>$produce_order_info['factory'],
                'info'=>$produce_fabric_info?json_encode($produce_fabric_info):'',
                'fabric_purchaser'=>$produce['fabric_purchaser'],
                'produce_team'=>$produce['produce_team'],
                'status'=>0,
                'add_time'=>time(),
            );
            $res=$supplement->add($data);
            if($res){
                $data_status=array(
                    'price_supplement_id'=>$res,
                    'status'=>0,
                    'admin_name'=>$produce_order_info['admin_name'],
                    'add_time'=>time(),
                );
                $res_status=M('Price_supplement_status')->add($data_status);
                if($res_status){
                    return array(
                        "success" => 0,
                        "Msg" => "发起补料申请成功",
                    );
                }else{
                    $info = array(
                        'time' => time(),
                        'request_url' => 'ProduceOrderApi',
                        'request_content' => $data_status,
                        'response_content' =>M()->getLastSql(),
                    );
                    //记录日志
                    Log::info( '/ProduceOrderApi/updateProduceFabricInfo/fail', $info );
                    return array(
                        "error" => 200,
                        "Msg" => "发起补料申请失败",
                    );
                }

            }else{
                $info = array(
                        'time' => time(),
                        'request_url' => 'ProduceOrderApi',
                        'request_content' => $data,
                        'response_content' =>M()->getLastSql(),
                    );
                    //记录日志
                    Log::info( '/ProduceOrderApi/updateProduceFabricInfo/fail', $info );
                return array(
                    "error" => 200,
                    "Msg" => "发起补料申请失败",
                );
            }


        }


    }

    /**
     * 数据库读取补料信息
     * @access public
     * @return array
     * @param $goods_sn array
     * @author 姚法强 2017-09-08 15:56:05
     */
    public function getpricesupplementinfo($filter){
        $where =array();
        if($filter['goods_sn']){
            $where['goods_sn']=$filter['goods_sn'];
        }
        if($filter['start_date']){
            $where['add_time']=array('egt',strtotime($filter['start_date']));
        }
        if($filter['end_date']){
            $where['add_time']=array('elt',strtotime($filter['end_date']));
        }
        if($filter['produce_order_id']){
            $where['produce_order_id']=$filter['produce_order_id'];
        }
        if($filter['produce_team']){
            $where['produce_team']=$filter['produce_team'];
        }
        if($filter['fabric_purchaser']){
            $where['fabric_purchaser']=$filter['fabric_purchaser'];
        }
        if($filter['factory']){
            $where['factory']=$filter['factory'];
        }
        //备货类型
        if($filter['print']){//如果是打印就不进行类型筛选
        }else{
            if($filter['prepare_type']==''){
                $where['status'] =0;
            }elseif($filter['prepare_type']==4){
            }else{
                $where['status'] =  $filter ['prepare_type'] ;
            }
        }
        $supplement=M('price_supplement');
        $info=$supplement->where($where)->limit(($filter ['page']-1)*$filter ['page_size'],$filter ['page_size'])->select();
        $record_count  =$supplement->where($where)->count();
        unset($where);
        $where='';
        foreach($info as $k=>$v){
            $where.=$v['id'].',';
        }
        $where=substr($where,0,-1);
        $map['price_supplement_id']  = array('in',$where);
        $info_remark=M('Price_supplement_remark')->where($map)->order('id desc')->select();
        $info_status=M('Price_supplement_status')->where($map)->select();
        $info_arr=array();
        foreach($info as $k=>$v){
            foreach($info_remark as $key=>$val){
                if($v['id']==$val['price_supplement_id']){
                    $v['remark'][]=$val;
                }
            }
            foreach($info_status as $key_s=>$val_s){
                if($v['id']==$val_s['price_supplement_id']){
                    $v['caozuo'][]=$val_s;
                }
            }
            $info_arr[]=$v;
        }
        $info_arr['record_count']=$record_count;
        $info_arr['page_count']=ceil($record_count/$filter ['page_size']);
        if($info){
            return array(
                "success" => 0,
                "content" => $info_arr,
            );
        }else{
            return array(
                "error" => 200,
                "Msg" => "获取补料信息失败",
            );
        }
    }


    /**
     * 生产补料管理
     * @access public
     * @param $filter 页面传过来的数组信息
     * @author 姚法强   2017-10-22 11:32:31
     * @return array
     */
    public function editpricesupplementinfo($filter){
        $filter = page_and_size ( $filter );
        $produce_order_arr=$this->getpricesupplementinfo($filter);//获取所有符合条件记录信息
        $produce_order_arr=$produce_order_arr['content'];
        $page_count=$produce_order_arr['page_count'];//分页数
        $record_count=$produce_order_arr['record_count'];//总记录数
        unset($produce_order_arr['page_count'],$produce_order_arr['record_count']);//删除无用参数
        $new_arr = array();
        //从当前供应商所有的单的中筛选出满足条件的订单
        foreach ($produce_order_arr as $key=>$value){
            $produce_order_arr[$key]['receive_goods_time'] = 0;
            $produce_order_arr[$key]['finish_produce_time'] = $value['add_time'];
            $produce_order_arr[$key]['remark_info_last'] = $value['remark']['0']['remark'] ? mb_substr($value['remark']['0']['remark'],0,5,'utf-8') : '';
            $value['photo']=json_decode($value['photo'],true);
            $value['info']=json_decode($value['info'],true);

            if($value['status']==0){
                $value['status_info']='待审核';
            }elseif($value['status']==1){
                $value['status_info']='已拒绝';
            }elseif($value['status']==2){
                $value['status_info']='已通过';
            }elseif($value['status']==3){
                $value['status_info']='已完成';
            }
            if($value['order_category']==3){
                $value['order_category']='FOB单';
            }elseif($value['order_category']==4){
                $value['order_category']='CMT单';
            }
            if($value['order_status']==6){
                $value['order_status']='分配中';
            }elseif($value['order_status']==13){
                $value['order_status']='订料中';
            }elseif($value['order_status']==7){
                $value['order_status']='裁剪中';
            }elseif($value['order_status']==8){
                $value['order_status']='车缝中';
            }elseif($value['order_status']==7){
                $value['order_status']='后整中';
            }
            foreach($value['remark'] as $k_v=>$v_v){
                $value['remark_info'][]=$v_v['last_update_time'].$v_v['admin_name'].$v_v['remark'];
            }
            foreach($value['caozuo'] as $k_c=>$v_c){
                if($v_c['status']==0){
                    $s_info='发起补料申请';
                }elseif($v_c['status']==1){
                    $s_info='已拒绝';
                }elseif($v_c['status']==2){
                    $s_info='已通过';
                }elseif($v_c['status']==3){
                    $s_info='已完成';
                }
                $value['caozuo_info'][]=$v_c['last_update_time'].'&nbsp'.$v_c['admin_name'].'&nbsp'.$s_info.'<br/>';
            }
            $fabric_info=array();
            foreach($value['info'] as $k_i=>$v_i){
                if($k_i=='name_levelthree'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['name_levelthree']=$v_ivalue;
                    }
                }
                if($k_i=='supplier'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['supplier']=$v_ivalue;
                    }
                }
                if($k_i=='address'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['address']=$v_ivalue;
                    }
                }
                if($k_i=='telephone'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['telephone']=$v_ivalue;
                    }
                }
                if($k_i=='huo_hao'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['huo_hao']=$v_ivalue;
                    }
                }
                if($k_i=='color_number'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['color_number']=$v_ivalue;
                    }
                }
                if($k_i=='color'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['color']=$v_ivalue;
                    }
                }
                if($k_i=='single_dosage'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['single_dosage']=$v_ivalue;
                    }
                }
                if($k_i=='unit'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['unit']=$v_ivalue;
                    }
                }
                if($k_i=='fabric_n'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['fabric_n']=$v_ivalue;
                    }
                }
                if($k_i=='fabric_type'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['fabric_type']=$v_ivalue;
                    }
                }
            }
                $new_arr[]=$value;
        }
        return array ('arr' => $new_arr, 'filter' => $filter, 'page_count' => $page_count, 'record_count' => $record_count );
    }


    /**
     * 打印生产补料管理
     * @return array
     * @author 姚法强 2017-11-6 14:08:00
     */
    public function priceSupplementInfoPrint($ids){
        $where['id']  = array('in',$ids);
        $produce_order_arr=M('Price_supplement')->where($where)->select();
        $new_arr = array();
        //从当前供应商所有的单的中筛选出满足条件的订单
        foreach ($produce_order_arr as $key=>$value){
            $value['add_time']=date('Y-m-d',$value['add_time']);
            $value['photo']=json_decode($value['photo'],true);
            $value['info']=json_decode($value['info'],true);
            if($value['status']==0){
                $value['status_info']='待审核';
            }elseif($value['status']==1){
                $value['status_info']='已拒绝';
            }elseif($value['status']==2){
                $value['status_info']='已通过';
            }elseif($value['status']==3){
                $value['status_info']='已完成';
            }
            if($value['order_category']==3){
                $value['order_category']='FOB单';
            }elseif($value['order_category']==4){
                $value['order_category']='CMT单';
            }
            if($value['order_status']==6){
                $value['order_status']='分配中';
            }elseif($value['order_status']==13){
                $value['order_status']='订料中';
            }elseif($value['order_status']==7){
                $value['order_status']='裁剪中';
            }elseif($value['order_status']==8){
                $value['order_status']='车缝中';
            }elseif($value['order_status']==7){
                $value['order_status']='后整中';
            }
            foreach($value['remark'] as $k_v=>$v_v){
                $value['remark_info'][]=$v_v['last_update_time'].$v_v['admin_name'].$v_v['remark'];
            }
            $fabric_info=array();
            foreach($value['info'] as $k_i=>$v_i){
                if($k_i=='name_levelthree'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['name_levelthree']=$v_ivalue;
                    }
                }
                if($k_i=='supplier'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['supplier']=$v_ivalue;
                    }
                }
                if($k_i=='address'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['address']=$v_ivalue;
                    }
                }
                if($k_i=='telephone'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['telephone']=$v_ivalue;
                    }
                }
                if($k_i=='huo_hao'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['huo_hao']=$v_ivalue;
                    }
                }
                if($k_i=='color_number'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['color_number']=$v_ivalue;
                    }
                }
                if($k_i=='color'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['color']=$v_ivalue;
                    }
                }
                if($k_i=='single_dosage'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['single_dosage']=$v_ivalue;
                    }
                }
                if($k_i=='unit'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['unit']=$v_ivalue;
                    }
                }
                if($k_i=='fabric_n'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['fabric_n']=$v_ivalue;
                    }
                }
                if($k_i=='material_code'){      //新增获取需补数量kg
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $single_amount_kg = D('ProduceProjectOrder')->getProduceFabricByPlm_1($value['goods_sn'],$v_ivalue);
                        $value['fabric_info'][$k_ikey]['fabric_n_kg']=$single_amount_kg;
                    }
                }
                if($k_i=='fabric_type'){
                    foreach($v_i as $k_ikey=> $v_ivalue){
                        $value['fabric_info'][$k_ikey]['fabric_type']=$v_ivalue;
                    }
                }
            }
                $new_arr[] = $value;

        }
        return array ('arr' => $new_arr);
    }

    /**
     * 操作补料申请表
     * @return array
     * @author 姚法强 2017-12-00 16:34:00
     */
    public function changeSupplementStatus($id,$status,$pay_type,$jujue){
        $supplement=M('Price_supplement');
        $supplement_status=M('Price_supplement_status');
        $supplement_remark=M('Price_supplement_remark');
        $save['status'] = $status;
        if($pay_type){
            $save['pay_user'] = $pay_type;
        }
        $res=$supplement->where("id=$id")->save($save);
        if($res){
            $data=array(
                'price_supplement_id'=>$id,
                'status'=>$status,
                'admin_name'=>session('admin_name'),
                'add_time'=>time()
            );
            $res_status=$supplement_status->add($data);
            if($res_status && $jujue){
                $data_remark=array(
                    'price_supplement_id'=>$id,
                    'admin_name'=>session('admin_name'),
                    'add_time'=>time(),
                    'remark'=>$jujue
                );
                $res_remark=$supplement_remark->add($data_remark);
                if($res_remark){
                    return array(
                        "success" => 0,
                        "Msg" => "操作成功",
                    );
                }else{
                    return array(
                        "error" => 200,
                        "Msg" => "操作失败",
                    );
                }
            }
            if($res_status){
                return array(
                    "success" => 0,
                    "Msg" => "操作成功",
                );
            }else{
                return array(
                    "error" => 200,
                    "Msg" => "操作失败",
                );
            }
        }else{
            return array(
                "error" => 200,
                "Msg" => "操作失败",
            );
        }

    }
    /**
     * ajax获取上传图片
     *
     * @author 姚法强  2017-10-23 14:33
     */
    public function getPriceSupplementPhoto($id){
        $arr = array();
        $price_dissent_arr_res = $this->getpriceSupplementInfo();//参数传加工厂的名字
        $price_dissent_arr = $price_dissent_arr_res['content'];
        if($price_dissent_arr){
            foreach ($price_dissent_arr as $key => $value) {
                if($id == $value['id']){
                    $arr = json_decode($value['photo']);
                }
            }
        }
        if($arr){
            return array('code'=>0,'data'=>$arr,'path'=>'https://appgeiwohuo:HGD4324fa93@www.geiwohuo.com/');
        }else{
            return array('code'=>1,'Msg'=>'未获取到图片信息','path'=>'https://appgeiwohuo:HGD4324fa93@www.geiwohuo.com/');
        }

    }

    /**
     * 新增备注
     *
     * @author 姚法强  2017-10-23 14:33
     */
    public function add_remark($id,$remark){
        $supplement_remark=M('Price_supplement_remark');
        $data_remark=array(
            'price_supplement_id'=>$id,
            'admin_name'=>session('admin_name'),
            'add_time'=>time(),
            'remark'=>$remark
        );
        $res_remark=$supplement_remark->add($data_remark);
        if($res_remark){
            return array(
                "success" => 0,
                "Msg" => "备注新增成功",
            );
        }else{
            return array(
                "error" => 200,
                "Msg" => "操作失败",
            );
        }
    }


    /**
     * ajax显示备注
     *
     * @author 姚法强  2017-10-23 14:33
     */
    public function showSupplementRemark($id){
        $supplement_remark=M('Price_supplement_remark');

           $where['price_supplement_id']=$id;

        $res_remark=$supplement_remark->where($where)->select();
        $remark=array();
        if($res_remark){
            foreach($res_remark as $k=>$v){
                $remark[]=date('Y-m-d H-i-s',$v['add_time']).$v['admin_name'].$v['remark'];
            }
            return array(
                "success" => 0,
                "content" => $remark,
            );
        }else{
            return array(
                "error" => 200,
                "Msg" => "获取备注失败",
            );
        }
    }


    /**
     * 添加快递单号
     *
     * @author 靳明杰  2017-10-26 14:33
     */
    public function batchProduceOrderExpress($ids,$express_num){
        $res_arr = array();
        if (!empty($ids) && $express_num) {
            //判断这个快递单有没有
            $express = M("express")->where(array("express_num" => $express_num))->find();
            $produce_order = M('produce_order')->where(array('produce_order_id'=>array('in',$ids)))->select();
            $new_arr = array();
            foreach ($produce_order as $k => $v) {
                $new_arr[$v['produce_order_id']] = $v;
            }
            $this->startTrans();// 事务开启
            if (empty($express)) {
                $arrInfo = array(
                    "express_num" => $express_num,
                    "add_time" => time(),
                );
                M("express")->add($arrInfo);
            }
            $data = array();
            foreach ($ids as $key => $value) {
                $info = M('express_order')->where(array('purchase_no'=>$value))->find();
                if($info){
                    $commit[] = M('express_order')->where(array('purchase_no'=>$value))->save(array('express_num'=>$express_num));
                }else{
                    $data[] = array(
                        "express_num" => $express_num,
                        "type" => 2,
                        "goods_order_info" => $new_arr[$value]['order_info'],
                        "purchase_no" => $value,
                        "goods_sn" => $new_arr[$value]['goods_sn'],
                        "add_time" => time(),
                    );
                }
            }
            if($data){
                $commit[] = M('express_order')->addAll($data);
            }
            if(db_commit($commit)){
                $res = array(
                    "status" => 0,
                    "msg" => "添加成功",
                );
            }else{
                $res = array(
                    "status" => 1,
                    "msg" => "添加失败",
                );
            }
        }else{
            $res = array(
                "status" => 1,
                "msg" => "数据不能为空",
            );
        }
        return $res;
    }
    /**
     * 根据供应商ID获取供应商一级分类
     * @param int $final_supplier_id 供应商ID
     * @return string   返回的一级分类名
     * @author 唐亮 2017-10-27 10:25:00
     */
    public function getSupplierFirstCateGory($final_supplier_id = 0){
        //获取供应商的一级分类
        $arr = array(array('supplier_id'=>$final_supplier_id));
        $arr = D('Prepare')->getSupplierLinkman($arr);//根据id获取供应商
        $cate_name_arr = D('Purchase')->getSupplierCat($arr);
        if(isset($cate_name_arr[0]['first_category_name']) && $cate_name_arr[0]['first_category_name']){
            return $cate_name_arr[0]['first_category_name'];
        }else{
            return '';
        }
    }

    /**
     * 请求仓储接口接口样衣状态
     * @return bool
     * @author 靳明杰 2018-04-10 17:51:21
     * @modfiy 李永旺 2018-05-08 17:51:21 修改传送参考sku
     *
     */
    public function getYangyiResult($produce_order,$reason=1,$operator){
        $operator = $operator ? $operator : session('admin_name');
        $person = $produce_order['produce_merchandiser'].'-'.$produce_order['factory'];
        if($reason == 3){
            $person = $produce_order['factory'];
        }
        // 是否首单 若是首单，且有参考sku的情况下传参考sku，若是首单，且无参考sku的情况下传sku，若是非首单还是传sku
        $w = array(
            'produce_order_id'=>$produce_order['produce_order_id'],
            'is_delete' => 0,
        );
        $res = M('produce_order')->where($w)->find();         
        $is_first_order = $res['is_first_order'];
        if ($is_first_order == 1 && (!empty($produce_order['refer_goods_sn']))) {
            $sku = $produce_order['refer_goods_sn'];
        }else{
            $sku = $produce_order['goods_sn'];
        }

        $data = array(
            'token_name'=>'sheinside105',
            'token_key'=>'mywayec105',
            'data'=>array(
                'system'=>'supply_produce',
                'operator'=>$operator,
                'borrow_info'=>array(
                    array(
                        'goods_sn'=>$sku,
                        'borrow_people'=>$person,
                        'borrow_reason'=>$reason,
                    ),
                ),

            ),
        );
        $url = 'http://wms-inspect.dotfashion.cn:8021/index_new.php/Home/InventoryBorrowApi/borrowSampleDress';
        $res = curlPost($url,$data,true);
        $info = array(
                'time' => time(),
                'request_url' => 'InventoryBorrowApi/borrow',
                'request_content' => $data,
                'response_content' => array($res),
            );
            //记录日志
            Log::info( '/produceOrder/getYangyiResult', $info );
        if(!$res['code']){
            $status = 1;//成功
        }else{
            $status = 2;//失敗
        }
        $commit = M('produce_order')->where(array('produce_order_id'=>$produce_order['produce_order_id']))->save(array('yangyi_result'=>$status));
    }
    /**
     * 修改订单样衣状态
     * @param $produce_order
     * @param $commit_data
     * @return bool
     * @author 曹禺 2017-11-2 17:51:21
     * @modify 曹禺 2017-11-3 09:58:17 添加样衣非空验证
     *
     */
    private function updateProduceOrderInventorySampleDress($produce_order, &$commit_data,$borrow_reason=0){

        $where = array(
            'goods_sn' => $produce_order['goods_sn'],
            'status' => 1
        );
      
        if (!empty($borrow_reason)) {
            $where_a = array(
            'produce_order_id' => $produce_order['produce_order_id'],
            'is_delete' => 0
            );
            $res=$this->table($this->tablePrefix.'produce_order')->where($where_a)->field('supplier_id')->find();
            $supplier_id=$res['supplier_id'];
            //页面供应商统一使用supplier_id获取
            $supplierIds = array($supplier_id);
            //根据供应商名称获取供应商信息
            $supplierInfo=$this->getSupplierByIds($supplierIds);  
            $supplierInfo = $supplierInfo['content'];
            $supplier_linkman = $supplierInfo[$supplier_id]['title'];
           $save_data = array(
            'status' => 2,
            'borrow_reason' => $borrow_reason,
            'borrow_status' => 1,
            'borrow_people' => $supplier_linkman
            );
        }else{
            $save_data = array(
            'status' => 2,
            'borrow_reason' => 3,
            'borrow_status' => 1,
            'borrow_people' => '生产品控部'
            );
        }
        

        $inventory_sample_dress = $this->table($this->tablePrefix.'inventory_sample_dress')->where($where)->field('id')->find();
        if(!$inventory_sample_dress){
            return false;
        }

        $commit_data[] = $this->table($this->tablePrefix.'inventory_sample_dress')->where($where)->save($save_data);
        $commit_data[] = $this->table($this->tablePrefix.'inventory_sample_dress_status')->add(array(
            'operate_time' => time(),
            'status' => 5,
            'operater' => session('admin_name'),
            'sample_id' => $inventory_sample_dress['id']
        ));

        return true;

    }
    /**
     * 更新二次工艺跟进项目的属性
     * @access public
     * @author 李永旺 2017-12-04 11:29:29
     * @return int
     */
    public function updateProduceProcess($id_arr = array(), $condtion = array(),$admin_name=''){

        if ($condtion['status']==2) {
           $res=$this->table($this->tablePrefix . 'produce_secondary_process')->where(array('id' => array('in', $id_arr),'status' =>'1'))->field('id')->select();
        }elseif($condtion['status']==3) {
           $res=$this->table($this->tablePrefix . 'produce_secondary_process')->where(array('id' => array('in', $id_arr),'status' =>'2'))->field('id')->select();
        }
        // 查找状态为待审核的数据        

        $id_arr_check='';
        foreach ($res as $key => $value) {
            $id_arr_check.=$value['id'].',';
        }
        if (!empty($id_arr_check)) {
            $id_arr_check=rtrim($id_arr_check, ',');
        }

        // if($condtion['goods_sn'] && $condtion['goods_sn'] !='' && $condtion['goods_sn'] !='无'){//检测SKU信息
        //     $good_info_curl = R('Goods/getGoodsInfoByApi',array($condtion['goods_sn']));
        //     if($good_info_curl['success']){
        //         $good_info=$good_info_curl['content'];
        //         $condtion['supplier_linkman'] = $good_info['supplier_linkman'];
        //         $condtion['cost'] = $good_info['cost'];
        //         $condtion['goods_thumb'] = $good_info['goods_thumb'];
        //         $condtion['goods_sn'] = $good_info['goods_sn'];//前台填写的SKU替换为与本身SKU，保持数据库product表SKU大小写一致
        //     }else{
        //         make_json_error('无此SKU的相关信息！');
        //     }
        // }
        $row =$this->table($this->tablePrefix . 'produce_secondary_process')->where(array('id' => array('in', $id_arr_check)))->save($condtion);//插入数据
        if (!empty($row)) {
            foreach ($res as $key => $value) {
                $this->startTrans();
                $commit = array();
                if (!empty($admin_name)) {
                    $data_status=array(
                        'produce_process_id'=>$value['id'],
                        'user_name'=>'给我货:'.$admin_name,
                        'add_time'=>time(),
                        'status'=>$condtion['status']
                    );
                    $commit[]=$this->table($this->tablePrefix . 'produce_secondary_process_status')->add($data_status);//插入数据
                }else{
                    $data_status=array(
                        'produce_process_id'=>$value['id'],
                        'user_name'=>session('admin_name'),
                        'add_time'=>time(),
                        'status'=>$condtion['status']
                    );
                    $commit[]=$this->table($this->tablePrefix . 'produce_secondary_process_status')->add($data_status);//插入数据
                }
                if (!empty($commit)) {
                    db_commit($commit);
                }
            }
        }
        return $row;
    }

    /**
     * 编辑二次工艺跟进工艺厂信息
     * @access public
     * @param $id 工艺订单id
     * @param $val 修改的值
     * @param $field 字段名
     * @author 李永旺 2017-12-14 11:11:52
     */
    public function updateProcessFactoryOrder($id,$val,$field){
        if(empty($id)){
            return array('success'=>0,'message'=>'id为空');
        }
        if($field=='fabric_purchaser' && $val == '无'){
            $val = '';
        }
        if($field=='sec_supplier_id'){
            $field = 'sec_supplier_id';
            $factory_info = D('SupplierInfo')->getSupplier(array('where'=>array('is_delete'=>0,'status'=>1)));
            $is_find = false;
            foreach($factory_info as $factory){
                if($factory['title']==$val){
                     $is_find = true;
                    break;
                }
            }
            if(!$is_find){
                return array('success'=>0,'message'=>'请填写正确的加工厂');
            }
        }
        // 通过供应商名称获取供应商id
        $supplier_id = M('supplier_info')->where(array('title'=>$val,'is_delete'=>array('in','0,1')))->getField('id');
        $data[$field]=$supplier_id;
        $data['process_factory']=$val;
          $res =  M('produce_secondary_process')->where(array('id'=>$id))->save($data);
        if($res){
            return array('success'=>1,'content'=>$val);
        }else{
            return array('success'=>0,'message'=>'保存失败');
        }
    }


    /**
     * 根据具体数量更新相关数据
     * @param array $params ['produce_order_id', 'field', 'data', 'qc_status', 'is_qc', 'box_number']
     * $produce_order_id int 生产订单id
     * $field string 字段名
     * $data string 字符串形式的数据
     * $qc_status int qc大货扫描，直接修改的状态 9，默认为空
     * bool $is_qc  默认不是qc
     * int  $box_number 箱数
     * @return array
     * @author 戴鑫 2015-1-8 16:08:16
     * @modify 曹禺 2017-11-14 13:13:43 去除事务
     */
    public function _updateProduceOrderValue(array $params){

//        extract($params);

        $produce_order_id = $params['produce_order_id'];
        $field = $params['field'];
        $data = $params['data'];
        $qc_status = $params['qc_status'];
        $is_qc = $params['is_qc'];
        $box_number = $params['box_number'];

        $sql = "/*master*/select * from " . $this->tablePrefix . 'produce_order where produce_order_id = ' . $produce_order_id ;
        $res = $this->getAll($sql);
            if(!count($res)){
            return array( 'success' => 0, 'message' => '参数错误，请刷新重试', 'errcode' => 101 );
        }
        $produce_order_info = $res[0];
        if($field == 'stored_num'){ // 修改入仓数量，涉及到累加
            if($is_qc===false){//当不是从qc接口访问时，执行判断
                if($res[0]['category']==2||$res[0]['category']==6){//线下单 状态需要等于已完成9状态
                    if($res[0]['status']!=9){
                        return array( 'success' => 0, 'message' => '请确定订单处于已完成状态', 'errcode' => 102 );
                    }
                }else{
                    if($res[0]['status']!=12&&$res[0]['status']!=9){//其他单 需要等于已收货状态
                        return array( 'success' => 0, 'message' => '请确定订单处于已收货状态', 'errcode' => 103 );
                    }
                }
                if($res[0]['bill_id']!=0){//如果是已生成账单 修改入仓数量 需要判断权限
                        if(!R('Privilege/checkRoleBool',array('bills_rucang_num_changes'))){
                        return array( 'success' => 0, 'message' => '无权限，请联系部门负责人', 'errcode' => 104);
                    }
                }
            }
            //判断账单状态处于待提交与待审核
            if($res[0]['bill_id']!=0){
                $bill_status = M('bill')->where('bill_id = '.$res[0]['bill_id'])->field('bill_status,type')->select();
                if(($bill_status[0]['bill_status'] !=0 &&
                        $bill_status[0]['bill_status'] != 3) &&
                    $bill_status[0]['type']==1){//0待审核、3待提交状态 type=1 生产账单
                    return array( 'success' => 0, 'message' => '账单状态非待审核状态或待提交状态，请与财务联系', 'errcode' => 105 );
                }
            }
            //获取次品数量
            $res_qc = $this->table( $this->tablePrefix.'produce_order_qc_report' )
                ->where( 'produce_order_id = ' . $produce_order_id )
                ->field('defective_num')
                ->select();
            $stored_str = $res[0]['stored_num'];
            $stored_old_num = explode("<br/>", $stored_str);
            $stored_old_num_arr = array();
            foreach ($stored_old_num as $value){
                if(!empty($value)){
                    $cuu_arr = explode(":", $value);
                    if(is_numeric($cuu_arr[0])){
                        $stored_old_num_arr[$cuu_arr[0]."_num"] = $cuu_arr[1];
                    }else{
                        $stored_old_num_arr[$cuu_arr[0]] = $cuu_arr[1];
                    }
                }
            }
            //去除所有填上0的字段
            $arr = explode('&lt;br/&gt;', $data);
            $turnover_no = '';
            //过滤FOB、US不添加周装箱
            if (strpos($arr[0], 'turnover_no:') !== false) {
                $turnover_no = array_shift($arr); //弹出第一个数组是为了获取周转箱号
                $turnover_no = trim(strtr($turnover_no, array('turnover_no:'=>''))); //修复传过来的值为空的bug
                $turnover_no = R('Inventory/checkTurnoverNo', compact('turnover_no'));
                if (!$turnover_no) {
                    return array( 'success' => 0, 'message' => '未输入周转箱号，请核实', 'errcode' => 106 );
                }
            }
            $stored_new_num_arr = array();
            foreach($arr as $v){
                $new_arr = explode(":", $v);
                $size_type[] = $new_arr['0'];
                if(is_numeric($new_arr['1']) && $new_arr['1']){//去除所有非正整数的填写
                    $key = str_replace('_', " ", str_replace('num', "数量",$new_arr['0']));
                    if(is_numeric($key)){
                        $stored_new_num_arr[$key."_num"]=$new_arr['1'];
                    }else{
                        $stored_new_num_arr[$key]=$new_arr['1'];
                    }
                }
            }
            $all_size = array_merge($stored_old_num_arr,$stored_new_num_arr);
            $data = '';
            //获取收货数量
            $received_num_arr = array();
            $received_num = explode("<br/>", $res[0]['received_info']);
            foreach($received_num as $value){
                    if(!empty($value)){
                    $received_arr = explode(":", $value);
                    if(is_numeric($received_arr[0])){
                        $received_num_arr[$received_arr[0]."_num"] = $received_arr[1];
                    }else{
                        $received_num_arr[$received_arr[0]] = $received_arr[1];
                    }
                }
            }
            //获取次品数量
            $defective_num_arr = array();
            $defective_num = explode('&lt;br/&gt;', $res_qc[0]['defective_num']);

            foreach($defective_num as $vs){
                if(!empty($vs)){
                    $defective_arr = explode(":", $vs);

                    if(is_numeric($defective_arr[0])){
                        $defective_num_arr[$defective_arr[0]."_num"] = $defective_arr[1];
                    }else{
                        $defective_num_arr[$defective_arr[0]] = $defective_arr[1];
                    }
                }
            }
            foreach ($size_type as $key => $value) {
                if(!$value){
                    unset($size_type[$key]);
                }
            }
            foreach ($all_size as $key=>$value){
                if (array_key_exists($key, $stored_new_num_arr)){
                    //累加
                    $curr_num = $stored_old_num_arr[$key]+$stored_new_num_arr[$key];
                    if($curr_num<0){
                        return array( 'success'=>0, 'message'=>'入仓数量不能小于0', 'errcode'=>107 );
                    }
                    //加上次品数量
                    if(($curr_num+$defective_num_arr[$key])>$received_num_arr[$key])
                    {
                        return array( 'success'=>0, 'message'=>'入仓数量和次品数量的和超出到货数量', 'errcode'=>108 );
                    }
                    if(strpos($key,"_num")){
                        $cuu_key = substr($key, 0,strlen($key)-4);
                        $data .= $cuu_key.":".$curr_num."<br/>"; //如果原来入过仓，进行累加
                    }else{
                        $data .= $key.":".$curr_num."<br/>"; //如果原来入过仓，进行累加
                    }
                }else{
                    if(strpos($key,"_num")){
                        $cuu_key = substr($key, 0,strlen($key)-4);
                        $data .= $cuu_key.":".$stored_old_num_arr[$key]."<br/>";  //没有新入仓，则保留原数据
                    }else{
                        $data .= $key.":".$stored_old_num_arr[$key]."<br/>";  //没有新入仓，则保留原数据
                    }
                }
            }
            $size_asc = explode("<br/>", $data);

            foreach ($size_asc as $key => $value) {
                if(!$value){
                    unset($size_asc[$key]);
                }else{
                    $size_type_info[] = explode(":", $value);
                }
            }
            if($size_type_info['0']['0'] != '数量'){
                $data = '';
                foreach ($size_type as $k => $v) {
                    foreach ($size_type_info as $key => $value) {
                        if($v == $value[0]){
                            $data .= $value[0].':'.$value[1].'<br/>';
                        }
                    }
                }
            }
        }else{
            //去除所有填上0的字段
            $arr = explode('&lt;br/&gt;', $data);
            $data = '';
            $sum_storeNum = 0;
            foreach($arr as $v){
                $new_arr = explode(":", $v);
                $new_arr[1] = $new_arr[1];
                if(is_numeric($new_arr['1']) && $new_arr['1']){//去除所有非正整数的填写
                    $key = str_replace('_', " ", str_replace('num', "数量",$new_arr['0']));
                    $data .= $key.":".$new_arr['1']."<br/>";
                    $sum_storeNum+=$new_arr['1'];
                }
            }
            $rule['category']=$produce_order_info['category'];
            $rule['order_info']=$produce_order_info['order_info'];
            $rule['sum_storeNum']=$sum_storeNum;
            $stored_rule_info =  $this->storedNumRuleCheck($rule);
            if(!$stored_rule_info['success']){
                return array( 'success' => 0, 'message' => $stored_rule_info['message'], 'errcode' => 109);
            }
        }

        $produce_order_data = array($field=>$data);
        if(empty($qc_status)){
            $produce_order_data['box_num'] = $box_number;
        }else{
            $produce_order_data['status'] = $qc_status;
        }
        $resp_flag = $this->table( $this->tablePrefix.'produce_order' )->
        where('produce_order_id='.$produce_order_id)->
        save($produce_order_data);

        if(!$resp_flag){
            return array( 'success' => 0, 'message' => '订单更新失败', 'errcode' => 110,
                'insert_arr' => $produce_order_data, 'db_err' =>$this->getDbError() );
        }

        if($field == 'received_info'){// 修改到货数量时，添加操作记录
            if($box_number==0){//当箱数不为空时，一定是FOB,CMT后整中状态
                $status = array(
                    'produce_order_id' => $produce_order_id,
                    'status' => 20,
                    'user_name' => session('admin_name'),
                    'add_time' => time()
                );
            }else{
                $data='箱数:'.$box_number.'<br/>'.$data;
                $status = array(
                    'produce_order_id' => $produce_order_id,
                    'status' => 20,//修改送货数量
                    'user_name' => session('admin_name'),
                    'add_time' => time()
                );
            }
            $resp_flag = $this->table( $this->tablePrefix . 'produce_order_status' )->add($status);
            if(!$resp_flag){
                return array( 'success' => 0, 'message' => '订单状态增加失败', 'errcode' => 111,
                    'insert_arr' => $status, 'db_err' =>$this->getDbError() );
            }
        }elseif($field == 'stored_num'){
            if(in_array($produce_order_info['prepare_type'],array(1,2))){
                $res_set_fba = R("InventoryAdjust/updateFbaOrderByProduce",array($produce_order_id,false));// 更新fba单  关闭事务
                if(!$res_set_fba['success']){
                    return array( 'success' => 0, 'message' => '更新fba单失败', 'errcode' => 112 ,
                        'para_arr' => array($produce_order_id,false), 'db_err' =>$this->getDbError());
                }
            }
            $add_data = array();
            $add_data['produce_order_id'] = $produce_order_id;
            $add_data['status'] = 16;
            $add_data['user_name'] = I('session.admin_name');
            $add_data['add_time'] = time();
            $commit_data[] = $this->table($this->tablePrefix.'produce_order_status')->add($add_data);
            if(!empty($qc_status)){ //入仓
                $add_over_data = array();
                $add_over_data['produce_order_id'] = $produce_order_id;
                $add_over_data['status'] = 9;
                $add_over_data['user_name'] = I('session.admin_name');
                $add_over_data['add_time'] = time();
                $resp_flag = $this->table($this->tablePrefix.'produce_order_status')->add($add_over_data);
                if(!$resp_flag){
                    return array( 'success' => 0, 'message' => '订单状态增加失败', 'errcode' => 113,
                        'insert_arr'=>$add_over_data, 'db_err' =>$this->getDbError());
                }
            }
            // 添加入仓数量时,在账单数据列表新增等同产品与数量信息
            $res = $this->table($this->tablePrefix."produce_order")->where('produce_order_id='.$produce_order_id)
                ->find();
            if(empty($res)){
                return array( 'success' => 0, 'message' => '添加入仓数量时,在账单数据列表新增等同产品与数量信息', 'errcode' => 114 ,
                    'db_err' =>$this->getDbError());
            }

            $goodsInfo = D('InventoryHuoweiMove')->getGoodsInfo($res['goods_sn']);
            foreach($stored_new_num_arr as $k => $v){
                if($v > 0) {
                    $tmpInfo = $goodsInfo;
                    $tmpInfo['goods_attr'] = D('InventoryHuoweiMove')->getRealSize($k);
                    //4.生产类型待上架 5.采购类型待上架
                    $huoweiMoveRes = D('InventoryHuoweiMove')->insertHuoweiMoveData(
                        $tmpInfo, $v, 4, $produce_order_id, $turnover_no);
                    if (!$huoweiMoveRes['success']) {
                        $errorReturn['message'] = $huoweiMoveRes['message'];
                        return array( 'success' => 0, 'message' => $errorReturn, 'errcode' => 114 ,
                            'db_err' =>$this->getDbError());
                    }
                }
            }

        }

        return array( 'success'=>1, 'errcode'=>0, 'content'=>array(
            'produce_order_id'=>$produce_order_id,
            'filed'=>$field,
            'data'=>$data,
            'prepare_type'=>$res['prepare_type'] )
        );
    }
    /**
     * 根据供应商ID批量获取供应商一级分类
     * @param array() $final_supplier_ids 供应商ID
     * @return array()   返回的一级分类名数组
     * @author 唐亮 2017-12-15 10:25:00
     */
    public function batchGetSupplierFirstCateGory($final_supplier_ids = array()){
        //初始化返回数组
        $return_res = array();
        //获取供应商的一级分类
        if(!$final_supplier_ids){
            return array();
        }
        if(!is_array($final_supplier_ids)){
            $final_supplier_ids = array($final_supplier_ids);
        }
        //去除重复的值
        $final_supplier_ids = array_unique($final_supplier_ids);
        //组装成需要传入的参数
        foreach($final_supplier_ids as $each_k=>$each_v){
            $arr[] = array(
                'supplier_id'=>$each_v
            );
        }
        $arr = D('Prepare')->getSupplierLinkman($arr);//根据id获取供应商
        //获取一级分类
        $cate_name_arr = D('Purchase')->getSupplierCat($arr);
        //组装返回数组   供应商id对应一级分类名
        if($cate_name_arr){
            foreach($cate_name_arr as $each_k=>$each_v){
                $return_res[$each_v['supplier_id']] = isset($each_v['first_category_name'])?$each_v['first_category_name']:'';
            }
        }
        return $return_res;
    }
    /**
     * 根据produce_order_id获取送货数量
     *
     * @access public
     * @param $id
     * @author 靳明杰 2017-12-18 15:23:23
     */
    public function getReceiveInfo($id){
        $received_info = array();
        $box_sn = array();
        $type = 0;
        $list = M('produce_order_delivery_goods_info')->where(array('produce_order_id'=>$id,'is_delete'=>0))->select();
        //获取produce_order中送货数量
        $info = M('produce_order')->where(array('produce_order_id'=>$id))->find();
        if(!$info){
            array(
                'code'=>1,
                'message'=>'未获取到订单数据'
            );
        }
        $data_order = $this->getTotalProduceOrder($info['order_info'], true);
        $data_received = $this->getTotalProduceOrder($info['received_info'], true);
        if(!$list){
            //去拿produce_order中送货数量
            foreach($data_order as $k=>$v){
                $key = $k;
                if($k == '数量'){
                    $key = 'num';
                }
                $received[$key]= isset($data_received[$k]) ? $data_received[$k] : '';
            }
            $received_info[] = $received;
            $box_sn[] = '';
            if(!$data_received){
                $type = 1;
            }
        }else{
            foreach ($list as $key => $value) {
                $goods_info = $this->getTotalProduceOrder($value['goods_attr'], true);
                foreach($data_order as $k=>$v){
                    $size = $k;
                    if($k == '数量'){
                        $size = 'num';
                    }
                    $received_info[$key][$size] = isset($goods_info[$k]) ? $goods_info[$k] : '';
                }
                $box_sn[] = $value['box_sn']; 
            }
        }
        return array(
            'code'=>0,
            'type'=>$type,
            'received_info'=>$received_info,
            'box_sn'=>$box_sn
        );
    }
    /**
     * 根据produce_order_id获取下单尺码
     *
     * @access public
     * @param $id
     * @author 靳明杰 2017-12-18 15:23:23
     */
    public function getReceiveSize($id){
        $info = M('produce_order')->where(array('produce_order_id'=>$id))->find();
        if(!$info){
            array(
                'code'=>1,
                'message'=>'未获取到订单数据'
            );
        }
        $data_order = $this->getTotalProduceOrder($info['order_info'], true);
        $new = array();
        foreach($data_order as $k=>$v){
            if($k == '数量'){
                $new['num'] = $v;
            }else{
                $new[$k] = $v;
            }
        }
        return array(
            'code'=>0,
            'data'=>$new
        );
    }
    /**
     * 修改送货数量
     *
     * @access public
     * @param $id
     * @author 靳明杰 2017-12-18 15:23:23
     */
    public function saveReceivedInfo($data){
        $id = $data['id'];
        $num = 0;
        $total = array();
        $received_str ='';
        $box_sn = $data['box_sn'];
        unset($data['id']);
        unset($data['box_sn']);
        shuffle($data);
        $info = M('produce_order')->where(array('produce_order_id'=>$id))->find();
        $data_order = $this->getTotalProduceOrder($info['order_info'], true);
        foreach ($data as $key => $value) {
            $flag = true;
            foreach ($value as $k => $v) {
                if($v){
                    $flag = false;
                }
            }
            if($flag){
                return array(
                    'code'=>1,
                    'message'=>'送货数量不能全部为空'
                );
            }
            foreach ($value as $k => $v) {
                if($v){
                    if($v > 99999 || $v < 1 || !is_numeric($v)){
                        return array(
                            'code'=>1,
                            'message'=>'送货数量只能录入1-99999内的正整数'
                        );
                    }
                $num +=$v;
                $total[$k] +=$v;
                } 
            }
        }
        //验证箱号不能重复
        foreach ($box_sn as $key => $value) {
            if($value){
                $number = 0;
                foreach ($box_sn as $k => $v) {
                    if($value == $v){
                        $number += 1;
                    }
                }
                if($number > 1){
                    return array(
                        'code'=>1,
                        'message'=>'箱号不能重复'
                    );
                }
            }
        }
        //验证送货数量
        $new = array(
            'category'=>$info['category'],
            'order_info'=>$info['order_info'],
            'sum_storeNum'=>$num,
        );
        $res = $this->storedNumRuleCheck($new);
        if(!$res['success']){
            return array(
                'code'=>1,
                'message'=>$res['message']
            );
        }
        //存储下单数量
        foreach ($box_sn as $key => $value) {
            $str = '';
            foreach ($data[$key] as $k => $v) {
                if($v){
                    if($k == 'num'){
                        $k = '数量';
                    }
                    $str .= $k.':'.$v.'<br/>';
                }
            }
            $data_add[] = array(
                'produce_order_id'=>$id,
                'box_sn'=>$value,
                'goods_attr'=>$str,
                'user_name'=>session('admin_name'),
                'add_time'=>time(),
            );
        }

        foreach ($total as $key => $value) {
            if($value){
                if($key == 'num'){
                    $key = '数量';
                }
               $received_str .= $key.':'.$value.'<br/>'; 
            } 
        }
        // print_r($data_add);
        // print_r($received_str);die;
         $this->startTrans();// 事务开启
        $commit[] = M('produce_order_delivery_goods_info')->where(array('produce_order_id'=>$id,'is_delete'=>0))->save(array('is_delete'=>1));
        $commit[] = M('produce_order_delivery_goods_info')->addAll($data_add);
        $commit[] = M('produce_order')->where(array('produce_order_id'=>$id))->save(array('received_info'=>$received_str,'box_num'=>count($data_add)));
        $add_status = array(
            'produce_order_id'=>$id,
            'status'          =>20,
            'user_name'       =>session('admin_name'),
            'add_time'        =>time()
        );
        $commit[] = M('produce_order_status')->add($add_status);
        if(db_commit($commit)){
            return array(
                'code'=>0,
                'message'=>'提交成功',
                'data'=>'箱数:'.count($data_add).'<br/>'.$received_str,
            );
        }else{
            return array(
                'code'=>1,
                'message'=>'提交失败'
            );
        }
    }


    /**
     * 检查单号是否重复
     *
     * @author 姚法强 2017-12-18 15:31:21
     */
    public function checkBoxSn($produce_order_id, $box_num)
    {
        $res = M('Produce_order_delivery_goods_info')->where(array('produce_order_id' => $produce_order_id, 'box_sn' => $box_num))->find();
        if ($res) {
            return array(
                "error" => 1,
                "msg" => "箱单号已存在",
            );
        } else {
            return array(
                "error" => 0,
                "msg" => "箱单号可用",
            );
        }
    }


    /**
     * 存储箱号
     *
     * @author 姚法强 2017-12-18 15:31:21
     */
    public function writeBoxSn($data)
    {
        $add = array();
        $save = array();
        $i=0;
        $res_delete =M('Produce_order_delivery_goods_info')->where(array('produce_order_id'=>$data['produce_order_id']))->select();
        $ids=array_column($res_delete,'box_sn');
        $box_arr=array();
        $del_box_arr=array();
        if($data['type']==2){
            foreach ($data['id'] as $k_1 => $v_1) {
                $id_arr[]=$v_1;
            }
            foreach($res_delete as $k_de=>$v_de){
                if(!in_array($v_de['order_delivery_goods_id'],$id_arr)){
                    $del_box_arr[]=$v_de['order_delivery_goods_id'];
                }
            }
            $where_delete['order_delivery_goods_id']=array('in',$del_box_arr);
            $res_del =M('Produce_order_delivery_goods_info')->where($where_delete)->delete();
            foreach($data['goods_attr'] as $k_3=>$v_3){
                if( $data['id'][$k_3]){
                    $save['goods_attr'] = htmlspecialchars_decode($v_3);
                    $save['user_name'] = $data['user_name'];
                    $save['add_time'] = time();
                    $save['box_sn'] = $data['box_sn'][$k_3];
                    $map['order_delivery_goods_id'] = $data['id'][$k_3];
                    $res = M('Produce_order_delivery_goods_info')->where($map)->save($save);
                }else{
                    if(!in_array($data['box_sn'][$k_3],$ids)){
                        $add['goods_attr'] = htmlspecialchars_decode($v_3);
                        $add['user_name'] = $data['user_name'];
                        $add['add_time'] = time();
                        $add['box_sn'] = $data['box_sn'][$k_3];
                        $add['is_delete'] = 0;
                        $add['produce_order_id'] = $data['produce_order_id'];
                        $res = M('Produce_order_delivery_goods_info')->add($add);
                    }else{
                        return array(
                            "error" => 1,
                            "msg" => "操作失败，".$data['box_sn'][$k_3]."箱号重复",
                        );
                    }

                }

            }
        }else{
            foreach ($data['old_goods_attr'] as $k_1 => $v_1) {
                $box_arr[]=$k_1;
            }
            foreach($res_delete as $k_de=>$v_de){
                if(!in_array($v_de['order_delivery_goods_id'],$box_arr)){
                    $del_box_arr[]=$v_de['order_delivery_goods_id'];
                }
            }
            $where_delete['order_delivery_goods_id']=array('in',$del_box_arr);
            $res_del =M('Produce_order_delivery_goods_info')->where($where_delete)->delete();
            foreach ($data['new_goods_attr'] as $k => $v) {
                $map = array();
                $add['goods_attr'] = htmlspecialchars_decode($v);
                $add['user_name'] = $data['user_name'];
                $add['add_time'] = time();
                $add['is_delete'] = 0;
                $map['produce_order_id'] = $data['produce_order_id'];
                $map['box_sn'] = $k;
                $res = M('Produce_order_delivery_goods_info')->add(array_merge($map, $add));
            }
            foreach ($data['old_goods_attr'] as $k => $v) {
                foreach ($v as$k_1=>$v_1){
                    $save['goods_attr'] = htmlspecialchars_decode($v_1);
                    $save['user_name'] = $data['user_name'];
                    $save['add_time'] = time();
                    $save['is_delete'] = 0;
                    $save['box_sn'] = $k_1;
                    $map['produce_order_id'] = $data['produce_order_id'];
                    $map['order_delivery_goods_id'] = $k;
                }
                $res = M('Produce_order_delivery_goods_info')->where($map)->save($save);
            }
        }

        $where['produce_order_id'] = $data['produce_order_id'];
        $save_order['box_num'] = $data['box_num'];
        $save_order['received_info'] = htmlspecialchars_decode($data['new_receive_final']);
        $res_produce = M('Produce_order')->where($where)->save($save_order);
        $add_status['produce_order_id']=$data['produce_order_id'];
        $add_status['status']=20;
        $add_status['add_time']=time();
        $add_status['user_name']=$data['user_name'];
        $res_produce_status=M('Produce_order_status')->add($add_status);

        if ($res || $res_del ||$res_produce) {
            return array(
                "error" => 0,
                "msg" => "操作成功",
            );
        } else {
            return array(
                "error" => 1,
                "msg" => "操作失败",
            );
        }
    }


    /**
     * 获取箱号
     *
     * @author 姚法强 2017-12-18 15:31:21
     * @modify 姚法强 2018-1-28 14:31:21
     */
    public function getBoxSn($data,$type=0)
    {
        if($type==0){
            $res= M('Produce_order_delivery_goods_info')->where(array('produce_order_id'=>$data,'is_delete'=>0))->select();
        }else{
            $res= M('Produce_order_rework_delivery_goods_info')->where(array('order_delivery_goods_id'=>$data))->select();
        }
        if ($res) {
            return array(
                "error" => 0,
                "content" => $res,
            );
        } else {
            return array(
                "error" => 1,
                "msg" => "查询失败",
            );
        }
    }

    /**
     * 给面辅料erp提供设置CMT已齐套的接口
     * @param $data
     * @return array
     * @author 唐亮 2018-1-2  15:30
     */
    public function setCompleteByFabric($data){
        //判断传递过来的数据是否异常
        $code = 0;
        if(!array($data) || count($data)<1 ){
            $code = 1;
        }
        //判断数组里面各元素是否有值
        foreach($data as $each_k=>$each_v){
            if(!$each_v['produce_order_id'] || !$each_v['complete_time']){
                $code = 1;
            }
        }
        if($code == 1){
            $info = array(
                'time' => time(),
                'request_url' => 'ProduceOrderApi',
                'request_content' => $data,
                'response_content' => array('msg'=>'请求的参数异常'),
            );
            //记录日志
            Log::info( '/ProduceOrderApi/setCompleteByFabric/fail', $info );
            return array('code'=>$code,'msg'=>'请求的参数异常','info'=>(object)array());
        }
        //处理数据
        $this->startTrans();// 事务开启
        $commit = array();
        $commit[] = true;
        foreach($data as $each_k=>$each_v){
            $order_info = array();
            $order_info = M('produce_order')->where(array('produce_order_id'=>$each_v['produce_order_id']))->field('produce_order_id,is_full_set')->find();
            if($order_info['is_full_set'] == 0){
                //把订单改为已齐套
                $commit[] = M('produce_order')->where(array('produce_order_id'=>$each_v['produce_order_id']))->save(array('is_full_set'=>1));
                //添加操作记录
                $commit[] = M('produce_order_status')->add(
                    array(
                        'produce_order_id'=>$each_v['produce_order_id'],
                        'status'=>61,//已齐套
                        'user_name'=>'面辅料ERP',//已齐套
                        'add_time'=>$each_v['complete_time'],//已齐套
                    )
                );
            }
        }
        //提交事务
        if(!db_commit($commit)){
            $info = array(
                'time' => time(),
                'request_url' => 'ProduceOrderApi',
                'request_content' => $data,
                'response_content' => array('msg'=>M()->getDbError()),
            );
            //记录日志
            Log::info( '/ProduceOrderApi/setCompleteByFabric/fail', $info );
            return array('code'=>1,'msg'=>'事务执行失败','info'=>(object)array());
        }else{
            $info = array(
                'time' => time(),
                'request_url' => 'ProduceOrderApi',
                'request_content' => $data,
                'response_content' => array_column($data,'produce_order_id'),
            );
            //记录日志
            Log::info( '/ProduceOrderApi/setCompleteByFabric/success', $info );
            return array('code'=>0,'msg'=>'','info'=>(object)array());
        }
    }

    /**
     * 根据订单id获取备货类型的口
     * @param $data
     * @return array
     * @author 靳明杰 2018-1-2  15:30
     */
    public function getPrepareByOrderId($data){
        if(!array($data) || count($data)<1 ){
            $info = array(
                'time' => time(),
                'request_url' => 'ProduceOrderApi/getPrepareByOrderId',
                'request_content' => $data,
                'response_content' => array('msg'=>'请求的参数异常'),
            );
            //记录日志
            Log::info( '/ProduceOrderApi/getPrepareByOrderId/fail', $info );
            return array('code'=>1,'msg'=>'请求的参数异常','info'=>array());
        }
        $list = M('produce_order')->where(array('produce_order_id'=>array('in',$data)))->field('produce_order_id,prepare_type,supplier_id,status')->select();
        $supplier_ids = array_column($list,'supplier_id');
        $supplier_arr = M('supplier_info')->where(array('id'=>array('in',$supplier_ids)))->getField('id,title');
        $new = array();
        foreach ($list as $key => &$value) {
            $value['factory'] = $supplier_arr[$value['supplier_id']];
            $new[$value['produce_order_id']] = $value;
        }
         $info = array(
            'time' => time(),
            'request_url' => 'ProduceOrderApi/getPrepareByOrderId',
            'request_content' => implode(',', $data),
            'response_content' => array($new),
        );
        //记录日志
        Log::info( '/ProduceOrderApi/getPrepareByOrderId/success', $info );
        return array('code'=>0,'msg'=>'','info'=>$new);
    }
    /**
     * 获取订单进度管理
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    public function getProduceOrderDate($data)
    {
        $status_date = array(
            '6'=>'fenpei_date',//分配中
            '13'=>'dingliao_date',//订料中
            '7'=>'caijian_date',//裁剪中
            '8'=>'chefeng_date',//车缝中
            '21'=>'houzheng_date',//后整中
            '12'=>'receive_date',//已收货
        );
        $produce_order_id = $data['produce_order_id'];
        $info = M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->find();
        if($info['category'] ==4){
           $status_date = array(
                '13'=>'dingliao_date',//订料中
                '6'=>'fenpei_date',//分配中
                '7'=>'caijian_date',//裁剪中
                '8'=>'chefeng_date',//车缝中
                '21'=>'houzheng_date',//后整中
                '12'=>'receive_date',//已收货
            ); 
        }elseif($info['category'] ==7){
            $status_date = array(
                '13'=>'dingliao_date',//订料中
                '68'=>'fenpei_date',//分配中
                '7'=>'caijian_date',//裁剪中
                '8'=>'chefeng_date',//车缝中
                '21'=>'houzheng_date',//后整中
                '12'=>'receive_date',//已收货
            ); 
        }
        $today=strtotime(date('Y-m-d'));
        $order_add_time = strtotime(date('Y-m-d', $info['add_time']));
        $spend_time = ceil(($today - $order_add_time) / 60 / 60 / 24);//耗时
        $status_arr = array_keys($status_date);
        $res = M('produce_order_plan_time')->where(array('produce_order_id'=>$produce_order_id))->order('id desc')->find();
        if(!$res){
            return array(
                'code'=>1,
                'msg'=>'未获取到目标交期',
                'info'=>array(),
            );
        }
        $day = 0;   //计划耗时
        $all_date = 0;   //计划耗时
        $conf = array();//各进度修改限制
        foreach ($status_arr as $key => $value) {
            $day += $res[$status_date[$value]];
            if($info['status'] == $value){
                break;
            }
            $conf[$status_date[$value]] = 1;    //当前进度之前的计划天数均不可更改
        }
        //总计划天数
        foreach ($status_arr as $key => $value) {
            $all_date += $res[$status_date[$value]];
        }
        if($day <= $spend_time){
            $conf[$status_date[$info['status']]] = 1;//当前进度已超时不可更改
        }else{
             $conf[$status_date[$info['status']]] = 0;
        }
        return array(
            'code'=>0,
            'info'=>$res,
            'conf'=>$conf,
            'produce_order'=>$info
        );
        
    }
    /**
     * 获取当前进度天数
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    function getNowPlanDate($produce_order){
        $status_date = array(
            '6'=>'fenpei_date',//分配中
            '13'=>'dingliao_date',//订料中
            '7'=>'caijian_date',//裁剪中
            '8'=>'chefeng_date',//车缝中
            '21'=>'houzheng_date',//后整中
            '12'=>'receive_date',//已收货
        );
        if($produce_order['category'] ==4){
           $status_date = array(
                '13'=>'dingliao_date',//订料中
                '6'=>'fenpei_date',//分配中
                '7'=>'caijian_date',//裁剪中
                '8'=>'chefeng_date',//车缝中
                '21'=>'houzheng_date',//后整中
                '12'=>'receive_date',//已收货
            ); 
        }elseif($info['category'] ==7){
            $status_date = array(
                '13'=>'dingliao_date',//订料中
                '68'=>'fenpei_date',//分配中
                '7'=>'caijian_date',//裁剪中
                '8'=>'chefeng_date',//车缝中
                '21'=>'houzheng_date',//后整中
                '12'=>'receive_date',//已收货
            ); 
        }
        $day = 0;   //计划耗时
        $all_date = 0;   //总计划耗时
        $conf = array();//各进度修改限制
        $status_arr = array_keys($status_date);
        $res = M('produce_order_plan_time')->where(array('produce_order_id'=>$produce_order['produce_order_id']))->order('id desc')->find();
        if(!$res){
            return array(
                'day'=>0,
                'all_date'=>0,
            );
        }
        foreach ($status_arr as $key => $value) {
            $day += $res[$status_date[$value]];
            if($produce_order['status'] == $value){
                break;
            }
            $conf[$status_date[$value]] = 1;    //当前进度之前的计划天数均不可更改
        }
        foreach ($status_arr as $key => $value) {
            $all_date += $res[$status_date[$value]];
        }
        return array(
            'day'=>$day,
            'all_date'=>$all_date,
        );
    }
    /**
     * 修改订单进度管理
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    public function saveProduceOrderDate($data)
    {
        // print_r($data);die;
        $id = $data['id'];
        $produce_order_id = $data['produce_order_id'];
        unset($data['id']);
        unset($data['produce_order_id']);//^[0-9]+([.][0-9]{1}){0,1}$
        $num = 0;
        foreach ($data as $key => $value) {
            if(!preg_match('/^[0-9]+(.[0-9]{0,1})?$/', $value)){
                return array(
                    'code'=>1,
                    'msg'=>'天数必须为整数且最多保留一位小数'
                );
            }
            $num +=$value;
        }
        if($num > 15){
            return array(
                    'code'=>1,
                    'msg'=>'各进度的天数总和不可大于15'
                );
        }
        $info = M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->find();
        $res = M('produce_order_plan_time')->where(array('id'=>$id,'produce_order_id'=>$produce_order_id))->save($data);
        $plan_date = $this->getNowPlanDate($info);
        $today=strtotime(date('Y-m-d'));
        $order_add_time = strtotime(date('Y-m-d', $info['add_time']));
        $spend_time = ceil(($today - $order_add_time) / 60 / 60 / 24);//耗时
        if(!$plan_date['all_date']){
            $plan_overdue = '无';
        }else{
            $plan_overdue = $plan_date['all_date']-15-($plan_date['day']-$spend_time);
            $plan_overdue = $plan_overdue>0?$plan_overdue:0;
        }
       
        if($res){
            return array(
                    'code'=>0,
                    'msg'=>'更新成功',
                    'info'=>array(
                        'plan_overdue'=>$plan_overdue
                    ),
                );
        }else{
            return array(
                    'code'=>1,
                    'msg'=>'更新失败'
                );
        }
    }
    /**
     * 生成目标交期数据
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    public function addOrderPlanTime($produce_order_id){
        $data = array(
            'produce_order_id'=>$produce_order_id,
            'fenpei_date'=>'0.5',
            'dingliao_date'=>'4',
            'caijian_date'=>'2',
            'chefeng_date'=>'6',
            'houzheng_date'=>'2',
            'receive_date'=>'0.5',
            'add_time'=>time(),
        );
        M('produce_order_plan_time')->add($data);
    }
    /**
     * 生产存货核算数据存储(MQ)
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    public function addProduceCheck($data){
        //只取生产入库所需数据
        if($data['biz_big_class'] == 0 && $data['biz_sub_class'] == 3){
            $produce_order = M('produce_order')->where(array('produce_order_id'=>$data['buz_id']))->find();
            $data_add = array(
                'produce_order_id'=>$data['buz_id'],
                'goods_sn'=>$data['goods_sn'],
                'size'=>$data['size'],
                'num'=>$data['num'],
                'contacts_no'=>$data['id'],
                'outin_date'=>$data['add_time'],
                'bill_id'=>$produce_order['bill_id'],
                'add_time'=>time(),
            );
        }else{
            return array(
                'code'=>0,
                'msg'=>'消费成功',
            );
        }
        $res = M('produce_check')->add($data_add);
        if($res){
            return array(
                'code'=>0,
                'msg'=>'消费添加数据成功',
            );
        }else{
            return array(
                'code'=>1,
                'msg'=>'数据库插入失败,sql:'.M()->getLastSql(),
            );
        }
    }
     /**
     * 生产账单存储账单id
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
     public function saveBillIdToProduceCheck($bill_id){
        $list = M('produce_order')->where(array('bill_id'=>$bill_id))->select();
        if($list){
            $produce_order_ids = array_column($list, 'produce_order_id');
            $produce_check = M('produce_check')->where(array('produce_order_id'=>array('in',$produce_order_ids),'bill_id'=>0))->select();
            $produce_check_id = array_column($produce_check, 'id');
            $res = M('produce_check')->where(array('id'=>array('in',$produce_check_id)))->save(array('bill_id'=>$bill_id));
            $info = array(
                'time' => time(),
                'request_url' => 'addBill/saveBillIdToProduceCheck',
                'request_content' =>array($bill_id),
                'response_content' => array('受影响id'=>$produce_check_id),
            );
            
        }else{
            $info = array(
                'time' => time(),
                'request_url' => 'addBill/saveBillIdToProduceCheck',
                'request_content' =>array($bill_id),
                'response_content' => array(),
            );
        }
        //记录日志
            Log::info( '/addBill/saveBillIdToProduceCheck', $info );
     }
     /**
     * 获取全检中心次品信息
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
     public function getDefectiveList($param){
        $res = curl_qc('wms/qc/outside/query_defective_list',$param);
        if($res['code'] == 0 && $res['info']){
            return array(
                'code'=>0,
                'data'=>$res['info']
            );
        }else{
            return array(
                'code'=>1,
                'data'=>array()
            );
        }
     }
     /**
     * 获取全检中心质检报告
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
     public function getSamplingInspect($produce_order_id){
        $res = curl_qc('wms/qc/outside/query_sampling_inspect',array('purchase_code'=>$produce_order_id));
        if($res['code'] == 0 && $res['info']){
            return array(
                'code'=>0,
                'data'=>$res['info']['data']
            );
        }else{
            return array(
                'code'=>1,
                'msg'=>$res['msg'],
                'data'=>array()
            );
        }
     }
    /**
     * 订单状态变更接口()
     *
     * @author 靳明杰 2018-1-2  15:30
     */
    public function updatePurchaseStatus($data){
        //处理数据
        if(!$data['data']){
            return array(
                'code'=>1,
                'msg' =>'数据不存在'
            );
        }
        //校验数据
        foreach ($data['data'] as $key => $value) {
            if(!in_array($value['status'], array(1,2))){
                return array(
                    'code'=>1,
                    'msg' =>'订单状态不存在'
                );
            }
            if(!$value['purchase_code']){
                return array(
                    'code'=>1,
                    'msg' =>'订单编号不存在'
                );
            }
        }
        $status = array('','30','34');
        $this->startTrans();// 事务开启
        $commit = array();
        foreach ($data['data'] as $key => $value) {
            $info = M('produce_order')->where(array('produce_order_id'=>$value['purchase_code']))->find();
            $flag = false;
            if($info['status'] == 12 && $value['status'] == 1){
                //校验订单是否是已收货
                $flag = true;
                
            }else if($info['status'] == 30 && $value['status'] == 2){
                $flag = true;
            }
            //校验订单是否是已插眼
            if($flag){
                $commit[] = $res = M('produce_order')->where(array('produce_order_id'=>$value['purchase_code']))->save(array('status'=>$status[$value['status']]));
                $add_data = array(
                    'produce_order_id'=>$value['purchase_code'],
                    'status'=>$status[$value['status']],
                    'user_name'=>$value['oper_name'],
                    'add_time'=>$value['oper_time'],

                );
                if($res){
                    $commit[] = M('produce_order_status')->add($add_data);
                }
            }
            
        }
        if(db_commit($commit)){
             return array(
                    'code'=>0,
                    'msg' =>'更改成功'
                );
        }else{
            return array(
                    'code'=>1,
                    'msg' =>'更改失败'
                );
        }
    }
    
    /**
     * 新增收货单信息
     *
     * @param $produce_order_id
     *
     * @return array|bool
     */
    public function addReceiptInfo($produce_order_id)
    {
        $map = "produce_order_id = {$produce_order_id}";
        if(!$order_info = M('produce_order')->where($map)->find()) {
            return false;
        }
        // 下单总数
        $order_total = $this->getTotal($order_info['order_info']);
        // 单价
        $order_price = (float)$order_info['order_price'];
        // 总价
        $total_amount = $order_price * $order_total;
        // 收货总数
        $received_total = $this->getTotal($order_info['received_info']);
        // 最新分单时间
        $order_status_map = array(
            'produce_order_id' => $produce_order_id,
            'status'           => 14, // 已分单
        );
        $order_status_info = M('produce_order_status')->where($order_status_map)
            ->field('max(add_time) as add_time,produce_order_id as order_id')
            ->group('produce_order_id')
            ->find();
        $add_time = !empty($order_status_info['add_time']) ? date('Y-m-d H:i:s', $order_status_info['add_time']) : date('Y-m-d H:i:s', false);
        // 获取供应商信息
        $supplier_map = array(
            'id' => array('in', $order_info['supplier_id']),
        );
        $supplier_info = M('supplier_info')->where($supplier_map)
            ->field('title as supplier_name,follower as merchandiser_name')
            ->find();
        if(!$supplier_info) {
            return false;
        }
        $supplier_name = (string)$supplier_info['supplier_name'];
        $merchandiser_name = (string)$supplier_info['merchandiser_name'];
        $admin_name = session('admin_name');
        if (!$receipt_detail = $this->getReceiptDetail($order_info)) {
            return false;
        }
        $array = array(
            // 收货明细
            'detail_list'           => $receipt_detail,
            // 跟单员
            'merchandiser_name'     => $merchandiser_name,
            // 原始单据类型(默认ODM)
            'original_type'         => 'ODM',
            // 下单编号
            'purchase_code'         => (string)$order_info['produce_order_id'],
            // 下单时间 最新分单时间
            'purchase_time'         => (string)$add_time,
            // 下单总金额 单价*下单总数量
            'purchase_total_amount' => $total_amount,
            // 下单总数量
            'purchase_total_count'  => $order_total,
            // 收货人
            'receipt_name'          => (string)$admin_name,
            // 收货时间
            'receipt_time'          => date('Y-m-d H:i:s'),
            // 收货总数
            'receipt_total_count'   => (int)$received_total,
            // 收货总箱数
            'receipt_total_ctn'     => (int)$order_info['box_num'],
            // sku 数量
            'sku_count'             => 1,
            // 单据类型 默认为 3:ODM
            'sub_type'              => '3',
            // 供应商编号
            'supplier_code'         => (string)$order_info['supplier_id'],
            // 供应商名称
            'supplier_name'         => $supplier_name,
            // 仓库编号 默认 1
            'warehouse_code'        => '1',
        );
        // 增加请求日志
        redis_hash("sendReceiptDetails", date("H:i:s"), $array);
        // 向 MQ 中发送消息
        $json_receipt_detail = json_encode($array);
        $mq_key = C('SUPPLIER_WMS_QC_RECEIPT');
        vendor('Mq.vendor.autoload');
        $mq = MQ::instance(
            C('PACKAGE_RELATED.MQ_HOST'),
            C('PACKAGE_RELATED.MQ_PORT'),
            C('PACKAGE_RELATED.MQ_NAME'),
            C('PACKAGE_RELATED.MQ_PWD'),
            $mq_key,
            true,
            true
        );
        $mq->connection();
        $mq->send($json_receipt_detail);
        
        return true;
    }
    
    /**
     * 订单内商品总数计算
     *
     * @param $str
     *
     * @return int
     */
    private function getTotal($str)
    {
        $total = 0;
        $info = array_filter(explode('<br/>', $str));
        if (!$info) return $total;
        foreach ($info as $k => $v) {
            $v = explode(':', $v);
            if (!empty($v[1])) {
                $total += intval($v[1]);
            }
        }
        
        return $total;
    }
    
    /**
     * 收货单明细
     *
     * @param $order_info
     *
     * @return array
     */
    private function getReceiptDetail($order_info)
    {
        $ret = array();
        $order_array = $this->explodeSizeString($order_info['order_info']);
        $received_array = $this->explodeSizeString($order_info['received_info']);
        if (!$order_array) return $ret;
        // 快递编号
        $express_map = array(
            'purchase_no' => $order_info['produce_order_id'],
        );
        $express_info = M('express_order')->where($express_map)
            ->field('express_num,purchase_no')
            ->select();
        if ($express_info) {
            $express = array();
            foreach ($express_info as $k => $v) {
                $express[] = $v['express_num'];
            }
            $express_info = $express;
        }
        // 根据SKU获取商品详细数据
        try {
            $goods_info = $this->getGoodsAllInfoSize($order_info['goods_sn']);
        } catch (\Exception $e) {
            $goods_info = array();
        }
        $sku_supplier = !empty($goods_info['content'][0]['product_supply']['sku_supplier']) ? $goods_info['content'][0]['product_supply']['sku_supplier'] : '';
        // 整合数据
        foreach ($order_array as $k => $v) {
            $ret[$k] = array(
                // 尺码
                'size'               => (string)($k != '数量') ? $k : '',
                // 下单数量
                'count'              => (int)$v,
                // 收货数量
                'delivery_count'     => 0,
                // 快递单号
                'express_no'         => (array)$express_info,
                // 商品编码
                'goods_sn'           => (string)$order_info['goods_sn'],
                // 单件金额
                'single_unit_price'  => (float)$order_info['order_price'],
                // 供应商货号
                'supplier_goods_num' => (string)$sku_supplier,
            );
            if ($received_array) {
                foreach ($received_array as $kk => $vv) {
                    $ret[$kk]['delivery_count'] = (int)$vv;
                }
            }
        }
    
        return array_values($ret);
    }
    
    /**
     * 尺寸与数量关系处理
     *
     * @param $str
     *
     * @return array
     */
    private function explodeSizeString($str)
    {
        $ret = array();
        $array = array_filter(explode('<br/>', $str));
        if (!$array) return $ret;
        foreach ($array as $k => $v) {
            $v = explode(':', $v);
            // 尺码对应数量
            $ret[$v[0]] = $v[1];
        }
        
        return $ret;
    }
    /**
     * 获取大货成本核算
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    public function getProduceCostInfo($produce_order_id)
    {
        $data = array();
        if(!$produce_order_id){
            return array(
                'code'=>1,

                'msg' =>'请求的参数异常'
            );
        }
        $produce_order = M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->find();
        $res = D('produceProject')->getCostBatchListBySku($produce_order['goods_sn']);
        if(!$res['code']){
            //拼装数据
            $info = $res['data'];
            $material_all_price = 0;
            $processing_all_price = 0;
            $secondary_all_price = 0;
            $clothes_secondary_all_price = 0;
            $other_all_price = 0;
            $goods_thumb = D('GoodsManage')->getImageUrl($produce_order['goods_thumb'],'',$produce_order['goods_sn']);
            foreach ($info[$produce_order['goods_sn']]['material_list'] as $key => $value) {
               $material_all_price += $value['price'];
            }
            foreach ($info[$produce_order['goods_sn']]['processing_list'] as $key => $value) {
               $processing_all_price += $value['unit_price'];
            }
            foreach ($info[$produce_order['goods_sn']]['secondary_list'] as $key => $value) {
               $secondary_all_price += $value['price'];
            }
            foreach ($info[$produce_order['goods_sn']]['clothes_secondary_list'] as $key => $value) {
               $clothes_secondary_all_price += $value['price'];
            }
            foreach ($info[$produce_order['goods_sn']]['other_list'] as $key => $value) {
               $other_all_price += $value['price'];
            }
            $material_code_arr = array_column($info[$produce_order['goods_sn']]['material_list'], 'material_sku');
            $material_code_arr = array_unique($material_code_arr);
            //根据物料sku去获取物料基础档案
            $result = D('produceProject')->getMaterialInfoBySku($material_code_arr);
            if(!$result['code']){
                $wuliao_info = $result['data'];
            }
            $data = array(
                'base_info'=>array(
                    'goods_sn'=>$produce_order['goods_sn'],
                    'design_code'=>$info[$produce_order['goods_sn']]['info']['design_code'],
                    'color_name'=>$info[$produce_order['goods_sn']]['info']['color_name'],
                    'category_name'=>$info[$produce_order['goods_sn']]['info']['category_name'],
                    'category'=>$produce_order['category'],
                    'goods_thumb'=>$goods_thumb,
                    'price'=>round($material_all_price+$processing_all_price+$secondary_all_price+$clothes_secondary_all_price+$other_all_price,2),
                ),
                'price_info'=>array(
                    'material_all_price'=>round($material_all_price,2),
                    'processing_all_price'=>round($processing_all_price,2),
                    'secondary_all_price'=>round($secondary_all_price,2),
                    'clothes_secondary_all_price'=>round($clothes_secondary_all_price,2),
                    'other_all_price'=>round($other_all_price,2),
                    'profit'=>round(($material_all_price+$processing_all_price)*10/100,2),
                ),
                'material_info'=>$info[$produce_order['goods_sn']]['material_list'],
                'processing_list'=>$info[$produce_order['goods_sn']]['processing_list'],
                'secondary_list'=>$info[$produce_order['goods_sn']]['secondary_list'],
                'clothes_secondary_list'=>$info[$produce_order['goods_sn']]['clothes_secondary_list'],
                'other_list'=>$info[$produce_order['goods_sn']]['other_list'],
            );
            foreach ($data['material_info'] as $key => $value) {
                foreach ($wuliao_info as $k => $v) {
                    if($value['material_sku'] == $k){
                        $data['material_info'][$key]['wide'] = $v['width']?$v['width']:'';
                        break;
                    }
                }
            }
            return array(
                'code'=>0,
                'info'=>$data,
            );
        }else{
           return array(
                'code'=>1,
                'msg'=>$res['msg'],
            ); 
        }
    }
    /**
     * 打印订单
     *
     * @author 靳明杰 2018-04-12 13:00:03
     */
    public function printProduceOrder($produce_order_id)
    {
        
        $data = array();
        if(!$produce_order_id){
            return array(
                'code'=>1,
                'msg' =>'请求的参数异常'
            );
        }
        $produce_order = M('produce_order')->where(array('produce_order_id'=>$produce_order_id))->find();
        if(!$produce_order){
            return array(
                'code'=>1,
                'msg'=>'请求数据为空'
            );
        }
        $goods_thumb = D('GoodsManage')->getImageUrl($produce_order['goods_thumb'],'',$produce_order['goods_sn']);
        $order_info = $this->getTotalProduceOrder($produce_order['order_info']);// 拆分size字段
        $cut_info = $this->getTotalProduceOrder($produce_order['cut_info']);// 拆分size字段
        $info = M('produce_secondary_process')->where(array('produce_order_id'=>$produce_order_id,'is_delete'=>0))->select();
        $secondary_process_status = array('','待审核','已审核','已发起','已完成');
        $secondary_process = '';
        foreach ($info as $key => $value) {
           $secondary_process .= $value['process_type'].'('.$secondary_process_status[$value['status']].')<br>';
        }
        $Produce_order_status = M('Produce_order_status')->where(array('produce_order_id'=>$produce_order_id))->order('produce_order_status_id desc')->find();
        $produce_order_status = $this->getProduceOrderStatusName($Produce_order_status['status']);
        $data = array(
            'produce_order_id'=>$this->prepare_type_prefix[$produce_order['prepare_type']].$produce_order['produce_order_id'],
            'factory'=>$produce_order['factory'],
            'goods_thumb'=>$goods_thumb,
            'order_info'=>'总数:'.$order_info.'<br/>'.$produce_order['order_info'],
            'cut_info'=>'总数:'.$cut_info.'<br/>'.$produce_order['cut_info'],
            'secondary_process'=>$secondary_process,
            'back_time'=>date("Y-m-d"),
            'Produce_order_status'=>$Produce_order_status['user_name'].' '.date('Y-m-d H:i:s',$Produce_order_status['add_time']).' '.$produce_order_status,
            'record'=>''
        );
        return array(
            'code'=>0,
            'info'=>$data
        );
    }
    /**
     * 根据订单id获取订单数据
     * @param $data
     * @return array
     * @author 靳明杰 2018-1-2  15:30
     */
    public function getProduceInfoById($data){
        if(!array($data) || count($data)<1 ){
            $info = array(
                'time' => time(),
                'request_url' => 'ProduceOrderApi/getProduceInfoById',
                'request_content' => $data,
                'response_content' => array('msg'=>'请求的参数异常'),
            );
            //记录日志
            Log::info( '/ProduceOrderApi/getProduceInfoById/fail', $info );
            return array('code'=>1,'msg'=>'请求的参数异常','info'=>array());
        }
        if(count($data) > 200){
             return array('code'=>1,'msg'=>'一次最多请求200条数据','info'=>array());
        }
        $produce_order_ids = array_column($data, 'buz_id');
        $produce_order_ids = array_unique($produce_order_ids);
        $list = M('produce_order')->where(array('produce_order_id'=>array('in',$produce_order_ids)))->field('produce_order_id,currency,order_price')->select();
        $new = array();
        foreach ($list as $key => $value) {
            $new[$value['produce_order_id']] = $value;
        }
         $info = array(
            'time' => time(),
            'request_url' => 'ProduceOrderApi/getProduceInfoById',
            'request_content' => implode(',', $data),
            'response_content' => array($new),
        );
        //记录日志
        Log::info( '/ProduceOrderApi/getProduceInfoById/success', $info );
        return array('code'=>0,'msg'=>'','info'=>$new);
    }

    /**
     * 根据大货sku获取大货bom数据进行存储【单件用量（kg）】
     *
     * @param $sku   string
     * @author 蓝勇强 2018-05-11 15:59:23 
     */
    public function storageProduceBomSingleAmount($sku) 
    {
        $data_list = $data = array();
        if(!$sku){
            return array(
                'code'=> 0,
                'msg' => '参数为空'
            );
        }
        $res = D('ProduceProject')->getMaterialListBySku(array($sku));      //plm获取大货bom信息
        if(!$res['code'] && $res['data']){
            $data_list['version_code'] = $res['data'][$sku]['info']['version_code'];//BOM版本号
            unset($res['data'][$sku]['info']);
            foreach ($res['data'][$sku] as $k => $v) {
                foreach ($v as $key => $value) {
                    if(!$value['original_material_sku']){
                        //未配码
                        $data[] =  array(
                            'material_sku' => $value['material_sku'],
                            'size' => '',
                            'single_amount_kg' => $value['single_amount_kg'] ? round($value['single_amount_kg'],2) : '',
                        );
                    }else{
                        //配过码
                        $data[] =  array(
                            'material_sku' => $value['material_sku'],
                            'size' => $k,
                            'single_amount_kg' => $value['single_amount_kg'] ? round($value['single_amount_kg'],2) : '',
                        );
                    } 
                }
            }
            $data_list['info'] = $data;
        }
        if ($data_list) {
            return array(
                'code' => 1,
                'data' => json_encode($data_list),
            );
        }else{
            return array(
                'code' => 0,
                'msg' => '未获取到大货boom数据',
            );
        }
    }
    /**
     * 根据大货sku获取大货bom数据,返回推送BOM数据
     * @param $sku   string
     * @author 蓝勇强 2018-05-11 15:59:23 
     * 损耗 = 供应商损耗|单件用量 = (1+供应商损耗/100)*净用量|总用量 = 单件用量 * 总下单量
     */
    public function getProduceBomSendMrp($sku,$category,$formartInfo)
    {
        $data_list = $data = array();
        $ret = D('ProduceProject')->getCostBatchListBySku(array($sku));      //plm获取大货成本信息
        if(!$ret['code'] && $ret['data']){
            foreach ($ret['data'][$sku]['material_list'] as $key => $value) {
                $material_list[$value['material_sku']] = $value;
            }
        }
        // $formartInfo = array('XS','XXS');
        // $res = D('ProduceProject')->getMaterialListBySkuSize([$sku=>array_keys($formartInfo)]);      //plm获取大货bom信息
        $json_list = '{"code":"0","msg":"接口请求成功","info":{"tee150727130":{"info":{"id":"48549","sku":"tee150727130","year":"2018","design_code":"M02XM180514002","color_id":"808","version_code":"1","brand_id":"1001","band_id":"4048","category_id":"170010202","designer_id":"徐明","size_type_id":"clothes_letter","color_name":"白花灰","band_name":"0508","brand_name":"shein","category_name":"短袖T恤","size_type_list":["XXS","XS","S","M","L","XL","XXL","XXXL"]},"XXS":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}],"XS":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}],"S":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}],"M":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","material_status":"","supplier_tel":"15918760365","process_remark":"","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":28.89}],"L":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}],"XL":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}],"XXL":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}],"XXXL":[{"id":"201615","material_items_name":"成衣","use_area_name":"抽绳","material_sku":"MSQXT0001SB黑色","material_title":"MES测试勿删A","material_color_id":"黑色","material_color_name":"黑-小黑","composition_name":"T:%","unit_name":"条","supplier_name":"3A织带","supplier_code":"001","supplier_color_code":"黑色A","secondary_process_name":[],"process_remark":"","single_amount":"1.20","material_status":"","supplier_tel":"15918760365","supplier_address":"轻纺城负一层四街UG1391档","purchase_type":"","purchase_type_name":"是","valid_width":"","width":"150","weight":"30","single_amount_kg":26.67},{"id":"201618","material_items_name":"辅料","use_area_name":"帽子","material_sku":"FNYXV0001SB咖啡色","material_title":"MES测试勿删C","material_color_id":"咖啡色","material_color_name":"黑-小黑","composition_name":"V:%","unit_name":"条","supplier_name":"007纽扣","supplier_code":"007纽扣1","supplier_color_code":"007纽扣3","secondary_process_name":[],"process_remark":"","single_amount":"1.40","material_status":"","supplier_tel":"15918760366","supplier_address":"轻纺城负一层二街UG1158档","purchase_type":"","purchase_type_name":"否","valid_width":"","width":"170","weight":"14","single_amount_kg":58.82},{"develop_batch_extend_id":"201616","material_items_name":"包装辅料","use_area_name":"领-下摆","original_material_sku":"MSNZV0001SB咖啡色","single_amount":"1.30","material_sku":"","material_title":"","material_color_id":"","material_color_name":"","unit_name":"","supplier_name":"","supplier_code":"","supplier_color_code":"","material_status":"","supplier_tel":"","process_remark":"","supplier_address":"","purchase_type":"","purchase_type_name":"","valid_width":"","width":"","weight":"","single_amount_kg":""}]}},"error":{}}';
        $json_data = json_decode($json_list,true);
        $res['code'] = 0;
        $res['data'] = $json_data['info'];
        $res_1 = $this->uniqueBomSku($res['data'],$formartInfo,$category,$material_list);
        $info_data = $res_1[$sku]['info'];
        foreach ($res_1[$sku]['material_list'] as $index => $info) {
            $size_need_total = array_map(function ($v) {
                return $v['need_num'];
            }, $info['size_num']);
            $size_num_total = array_sum($size_need_total);     //该物料sku总需用量
            $data[$index] = array(
                'material_sku' => $info['material_sku'],
                'supplier' => $info['supplier_name'],
                'material_name' => $info['material_title'],
                'material_color' => $info['material_color_name'],
                'unit' => $info['unit_name'],
                'size_num' => $info['size_num'],
                'two_process' => $info['two_process'],
                'wastage' => $info['supplier_loss'],
                'need_num' => round($size_num_total * (1+$info['supplier_loss']/100), 2),
            );
        }
        if ($data) {
            return array(
                'code' => 1,
                'data' => $data,
                'info' => $info_data,
            );
        }else{
            return array(
                'code' => 0,
                'msg' => '单件用量（kg）获取为空',
            );
        }
    }
    /**
     * 为财务系统提供BOM查询接口
     * @author 蓝勇强 2018-05-15 16:38:23 
     * 出参: 转向PLM的BOM查询接口+供应链后台存储的各物料SKU的单件用量（kg）
     */
    public function setFinanceBomInquire($produce_order_id){
        //获取订单的详情
        $produce_order = $this->table($this->tablePrefix.'produce_order')->where("produce_order_id = $produce_order_id")->field('goods_sn,single_amount')->find();
        $sku = $produce_order['goods_sn'];
        $single_amount = json_decode($produce_order['single_amount'],true);    //单件用量kg
        $res = D('ProduceProject')->getMaterialListBySku(array($sku));
        if(!$res['code'] && $res['data']){
            $res['data']['single_amount_kg'] = $single_amount;//添加各物料SKU的单件用量（kg）
            return array(
                'code' => 1,
                'msg' => "接口请求成功",
                'result' => $res['data'],
            );
        }else{
            return array(
                'code' => 0,
                'msg' => "订单{$produce_order_id}".$res['msg'],
                'result' => array(),
            );
        }    
    }
    /**
     * 大货bom数据去重处理
     * @author 转自CD 2018-05-16 16:38:23
     * @modify 蓝勇强 新增损耗，新cmt单过滤
     */
    public function uniqueBomSku($bom, $formartInfo,$category,$material_list)
    {
        $return = [];
        foreach ($bom as $goods_sku => $skuInfo) {
            foreach ($skuInfo as $index => $value) {
                if ($index == 'info') {//数组中包含的是大货sku的信息
                    $return[$goods_sku]['info'] = $value;
                } else {//index是尺码，数组是这个尺码对应的物料信息
                    foreach ($value as $materialInfo) {
                        //取对应物料sku的供应商损耗
                        foreach ($material_list as $sku => $cost) {
                            $supplier_loss = 0;
                            if($sku == $materialInfo['material_sku']){
                                $price_loss = $cost['supplier_loss'] ? $cost['supplier_loss'] : 0;
                                $supplier_loss = $price_loss;   //损耗
                                break;
                            }
                        }
                        //新cmt单过滤非自购
                        if ($category == 7 && $materialInfo['purchase_type_name'] == '否') {
                            continue;
                        }
//                        if (in_array($materialInfo['material_sku'], ['16FLXH00157009', '16FLXH00159014', '16FLGF04159014'])) {
//                            //透明条，不进行采购，工厂自己采
//                            continue;
//                        }

                        //由于供应链后台boom数据中的二次工艺数据都是备注，而且业务方也没有整理，所以这边只能通过
                        //汉字判断是否含数码印花，其他的二次工艺全部过滤掉，公司现在只做数码印花的二次工艺
                        if (in_array('数码印花', $materialInfo['secondary_process_name'])) {
                            $two_process = '数码印花';
                        } else {
                            $two_process = '';
                        }

                        /**
                         * 业务基础信息维护不对，开后门放开限制
                         */
//                        if(empty($materialInfo['material_sku']) || empty($materialInfo['supplier_name'])){
//                            unset($return[$goods_sku]);
//                            break 2;
//                        }

                        /**
                         * 物料sku为空的过滤掉，可能会导致系统数据以及业务流程混乱
                         */
                        //@TODO 等业务方吧基础数据维护好，这边的代码要去掉
                        if (empty($materialInfo['material_sku'])) {
                            //没有物料sku的过滤掉
                            continue;
                        }
                        if (!array_key_exists($materialInfo['material_sku'] . '_' . $two_process, $return[$goods_sku]['material_list'])) {
                            $return[$goods_sku]['material_list'][$materialInfo['material_sku'] . '_' . $two_process] = $materialInfo;
                        }
                        $return[$goods_sku]['material_list'][$materialInfo['material_sku'] . '_' . $two_process]['two_process'] = $two_process;
                        $return[$goods_sku]['material_list'][$materialInfo['material_sku'] . '_' . $two_process]['size_num'][$index]['size'] = $index;
                        $return[$goods_sku]['material_list'][$materialInfo['material_sku'] . '_' . $two_process]['size_num'][$index]['single_cost'] += $materialInfo['single_amount'];
                        $return[$goods_sku]['material_list'][$materialInfo['material_sku'] . '_' . $two_process]['size_num'][$index]['order_num'] = $formartInfo[$index];
                        //对应物料sku的供应商损耗
                        $return[$goods_sku]['material_list'][$materialInfo['material_sku'] . '_' . $two_process]['supplier_loss'] = $supplier_loss;
                    }
                }
            }
        }

        //统计每个物料对应尺码的总订单数量s
        foreach ($return as $goods_sku => &$skuInfo) {
            foreach ($skuInfo['material_list'] as $index => &$value) {
                foreach ($value['size_num'] as $size => &$sizeInfo) {
                    $sizeInfo['need_num'] = round($sizeInfo['single_cost'] * $sizeInfo['order_num'], 2);
                }
            }
        }

        //注销掉，防止引用错误
        unset($skuInfo);
        unset($value);
        unset($sizeInfo);

        return $return;
    }

    /**
     * 对尺码数量进行分解成数组
     *
     * @param String $size_num 'S:1,L:2'
     * @return array
     * [
     *      S:1,
     *      L:2,
     * ]
     * @author CD 2017-11-16 13:28:20
     */
    public function formartSizeNum($size_num)
    {
        $temp = explode('<br/>', rtrim($size_num,"<br/>"));

        $return = [];
        foreach ($temp as $index => $value) {
            $return[reset(explode(':', $value))] = end(explode(':', $value));
        }

        return $return;
    }
    /**
     * 根据大货 sku 获取面辅料数据
     * 为ProduceProjectOrder的priceSupplementInfoPrint方法
     * 提供需补数量
     * @author 蓝勇强 2018-05-17 14:19:23 
     */
    public function getProduceFabricByPlm_1($sku,$material_sku)
    {
        $data = array();
        if(!$sku || !$material_sku){
            return array(
                'code'=>1,
                'msg' =>'参数为空'
            );
        }
        $res = D('ProduceProject')->getMaterialListBySku(array($sku));        //plm获取大货bom信息
        if(!$res['code'] && $res['data']){
            unset($res['data'][$sku]['info']);
            foreach ($res['data'][$sku] as $k => $v) {
                foreach ($v as $key => $value) {
                    if ($value['material_sku'] == $material_sku) {
                        $single_amount_kg = $value['single_amount_kg']?$value['single_amount_kg']:'';
                        return $single_amount_kg;
                    }
                }
            }
        }
    }

    






}
