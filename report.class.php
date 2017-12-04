<?php
/**
 +----------------------------------------------------------
 * 报表模组
 * @author	: pengj
 * @date	: 2012/2/27
 +----------------------------------------------------------
 */
class report extends Index_Public
{
	/**
     * @功能：初始化JSON数据
     * @作者：罗杰
     * @日期：2011 07 12
     *
     * @return Json数据
     */
	private function _get_json_obj(){
		include_once ("lib/json/json.php");
		return  new Services_JSON();
	}
	/**
     +----------------------------------------------------------
     * 根据fromdate、todate 获取通话记录分表(通话记录分表规则, 按月分表，但最近两个月的数据会合并在一张表中)
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function getTableList($dbname)
	{
		$fromdate = $_REQUEST['fromdate'];
		$todate = $_REQUEST['todate'];

		if (empty($fromdate)) return array($dbname .'.ss_cdr_cdr_merge'); //起始时间为空, 返回默认表名

		$last_month = date('Y-m-01', strtotime('last month')); //获取上个月第1天的日期
		if ($fromdate>=$last_month) return array($dbname . '.ss_cdr_cdr_merge');  //起始时间大于上月第1天日期, 返回默认表名

		global $db;

		$array_table = $this->getTables('ss_cdr_cdr_info', $dbname); //获取真实存在的通话记录分表

		$table_list = array();
		if ($todate>=$last_month) $table_list[] = $dbname . '.ss_cdr_cdr_merge';

		$s_ym = intval(date("ym", strtotime($fromdate))); //开始年月
		$e_ym = intval(date("ym", strtotime($last_month) - 86400));

		$ym = $e_ym;
		while ($ym >= $s_ym) {
			$table = 'ss_cdr_cdr_info_' . strval($ym);

			if (in_array($table, $array_table)) {
				$table_list[] = $dbname . '.' . $table;
			}

			$ym --;
			if ($ym % 100 == 0) $ym = $ym - 100 + 12; //获取上年最后一月
		}

		return $table_list;
	}

	/**
	* @Purpose		: 获取数据库里指定前缀所有真实存在的分表
	* @Method Name	: getTables()
	* @Parameter	:
	*				$prefix : string (表前缀)
	*				$database : 数据库
	* @Return		: array
	*/
	function getTables($prefix, $database = ASTERISKCDRDB_DB_NAME)
	{
		global $db;
		$db->Execute("use " . $database);
		$rs = $db->Execute("show tables from $database");

		$table_list = array();

		if ( $rs ){
			while( $o = $rs->FetchRow() )
			{
				if ( strstr( $o["Tables_in_" . $database], $prefix) && 0 == strpos($o["Tables_in_" . $database], $prefix)){
					$table_name = $o["Tables_in_" . $database];
					$table_list[] = $table_name;
				}// end if
			}// end while
		}// end if
		$db->Execute( "use " . CFG_DB_NAME );
		return $table_list;
	}

	
	/**
     +----------------------------------------------------------
     * 根据fromdate、todate 获取通话记录分表(通话记录分表规则, 按月分表，但最近两个月的数据会合并在一张表中)
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function getCdrTableList($dbname)
	{
		$fromdate = $_REQUEST['fromdate'];
		$todate = $_REQUEST['todate'];

		//if (empty($fromdate)) return array($dbname .'.ss_cdr_cdr_merge'); //起始时间为空, 返回默认表名
		if (empty($fromdate)) return array('ss_cdr_cdr_merge'); //起始时间为空, 返回默认表名

		//$last_month = date('Y-m-01', strtotime('last month')); //获取上个月第1天的日期
		//update by yeyp 2013-01-21 strtotime方法是存在固有bug的，在2012-12-31那天使用该方法，获取前一个月的时间是2012-12-1
		$last_month = date('Y-m-d', mktime(0,0,0,date('m')-1,1,date('Y')));//获取上个月第一天的日期
		//if ($fromdate>=$last_month) return array($dbname . '.ss_cdr_cdr_merge');  //起始时间大于上月第1天日期, 返回默认表名
		if ($fromdate>=$last_month) return array('ss_cdr_cdr_merge');  //起始时间大于上月第1天日期, 返回默认表名

		global $db;

		$array_table = $this->getTables('ss_cdr_cdr_info', $dbname); //获取真实存在的通话记录分表

		$table_list = array();
		if ($todate>=$last_month) $table_list[] = 'ss_cdr_cdr_merge';

		$s_ym = intval(date("ym", strtotime($fromdate))); //开始年月
		//$e_ym = intval(date("ym", strtotime($last_month) - 86400));
		if (empty($todate) || $todate >= $last_month) {
			$e_ym = intval(date("ym", strtotime($last_month) - 86400));
		} else {
			$e_ym = intval(date("ym", strtotime($todate)));
		}

		$ym = $e_ym;
		while ($ym >= $s_ym) {
			$table = 'ss_cdr_cdr_info_' . strval($ym);

			if (in_array($table, $array_table)) {
				$table_list[] = $table;
			}

			$ym --;
			if ($ym % 100 == 0) $ym = $ym - 100 + 12; //获取上年最后一月
		}
		if (count($table_list) == 0) {
			//$table_list = array($dbname . '.ss_cdr_cdr_merge');
			$table_list = array('ss_cdr_cdr_merge');
		}

		return $table_list;
	}

	/**
     +----------------------------------------------------------
     * 通话记录
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function showCdrList() {
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'cdr_view', $arr_local_priv, '您没有查看通话记录的权限！' );
		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 0); //只显示当天数据
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		if (!isset($_REQUEST['s_hour'])) $_REQUEST['s_hour'] = '00';
		if (!isset($_REQUEST['e_hour'])) $_REQUEST['e_hour'] = '23';
		if (!isset($_REQUEST['s_min'])) $_REQUEST['s_min'] = '00';
		if (!isset($_REQUEST['e_min'])) $_REQUEST['e_min'] = '59';
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$timestr=strtotime($_REQUEST['todate'])-strtotime($_REQUEST['fromdate']);
		if($timestr>93*24*60*60){
			goBack(c('查询的日期不能超过3个月.'));
		}
		$table_list = $this->getTableList(ASTERISKCDRDB_DB_NAME);

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		//根据”部门条件“组合条件
		$extenSelect = array();
		if (!empty($depart_id)) {
			//获取所有的子部门
			$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
			$list_depart .= "$depart_id";	//加上所选部门
			$condition .= " and `group_id` in ($list_depart)";

			//获取所选部门的座席列表
			$rs	= $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
			while(!$rs->EOF) {
				$extenSelect[] = $rs->fields;
				$rs->MoveNext();
			}

			$this->Tmpl['extenSelect'] = $extenSelect;
		}

		$list = array();

		if ('' != $do) {
			//非admin管理员, 获取其所能管理的部门及座席
			$arr_exten = array();
			$list_exten = "";
			$arr_deptid = array();
			$list_deptid = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);

				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}

			//根据起止时间组合查询条件
			$condition = " 1 ";
			if (!empty($fromdate)) {
				if (empty($s_hour)) $s_hour = '00';
				if (empty($s_min)) $s_min = '00';
				$fromdate .= ' ' . $s_hour . ':' . $s_min;

				$condition .= " and start_stamp>='$fromdate'";
			}

			if (!empty($todate)) {
				if (empty($e_hour)) $e_hour = '59';
				if (empty($e_min)) $e_min = '59';
				$todate .= ' ' . $e_hour . ':' . $e_min . ':59';

				$condition .= " and start_stamp<='$todate'";
			}

			//接听状态
			if ($_REQUEST['newdo'] == 'sys') {
				if ('0' === $is_answered) $condition .= " and bill_sec=0";
				else if ('1' == $is_answered) $condition .= " and bill_sec>0";
			} else {
				if ('0' === $is_answered) $condition .= " and agent_sec=0";
				else if ('1' == $is_answered) $condition .= " and agent_sec>0";
			}

			//呼叫类型
			if ('' != $call_type) $condition .= " and call_type='$call_type'";

			//模糊/模糊查询 主/被 叫
			if ('' == $sel_match) { //
				if ('1' == $call_type) {
					if (!empty($caller_number)) $condition .= " and caller_number = '$caller_number'";
					if (!empty($callee_number)) $condition .= " and callee_number = '$callee_number'";
				} else if ('2' ==  $call_type) {
					if (!empty($caller_number)) $condition .= " and caller_number = '$caller_number'";
					if (!empty($callee_number)) $condition .= " and callee_number = '$callee_number'";
				} else if ('3' ==  $call_type) {
					if (!empty($caller_number)) $condition .= " and agent_number = '$caller_number'";
					if (!empty($callee_number)) $condition .= " and callee_number = '$callee_number'";
				} else {
					if (!empty($caller_number)) $condition .= " and (('3' !=  call_type and caller_number='$caller_number') or ('3' =  call_type and agent_number='$caller_number'))";
					if (!empty($callee_number)) $condition .= " and callee_number='$callee_number'";
				}
			}
			else {
				if ('1' == $call_type) {
					if (!empty($caller_number)) $condition .= " and caller_number like '%$caller_number%'";
					if (!empty($callee_number)) $condition .= " and callee_number like '%$callee_number%'";
				} else if ('2' ==  $call_type) {
					if (!empty($caller_number)) $condition .= " and caller_number like '%$caller_number%'";
					if (!empty($callee_number)) $condition .= " and callee_number like '%$callee_number%'";
				} else if ('3' ==  $call_type) {
					if (!empty($caller_number)) $condition .= " and agent_number like '%$caller_number%'";
					if (!empty($callee_number)) $condition .= " and callee_number like '%$callee_number%'";
				} else {
					if (!empty($caller_number)) $condition .= " and (('3' !=  call_type and caller_number like '%$caller_number%') or ('3' =  call_type and agent_number like '%$caller_number%'))";
					if (!empty($callee_number)) $condition .= " and callee_number like '%$callee_number%'";
				}
			}

			// 按小时查询
			if (isset($_REQUEST['ss_hour']) && !empty($_REQUEST['ss_hour'])) {
				$condition .= " AND SUBSTR(start_stamp, 12, 2)={$_REQUEST['ss_hour']} ";
			}

			//挂断原因
			if ('' != $hangup_code) $condition .= " and hangup_code='$hangup_code'";

			//根据”部门条件“组合条件
			$extenSelect = array();
			if (!empty($depart_id) && !empty($list_depart)) {
				if (!empty($list_exten)) {
					$condition .= " and (`group_id` in ($list_depart) or (call_type = '3' and (agent_number in ($list_exten) or callee_number in ($list_exten))))";
				} else {
					$condition .= " and `group_id` in ($list_depart)";
				}
			}

			//非管理员, 根据所能管理的部门或座席组合限定条件,增加ivr也可查询
			if (1 != $_SESSION['userinfo']['power']) {
				// 如果坐席拥有“查看全部通话记录”的权限，则可以查看该坐席所在部门的所有通话记录
				if(in_array('see_all_call_record',$arr_local_priv)){
					$sql_me_deptid = "SELECT dept_id FROM org_user WHERE extension='".$_SESSION['userinfo']['extension']."' "; 
					$re_me_deptid = $db -> GetOne($sql_me_deptid);	// 我所在部门的 部门id
					$parent_deptid = $this -> getParentDepartId($re_me_deptid);		// 我在部门的第一级部门 id
					$department_ids = $this -> getChildDepartId($parent_deptid);	// 该部门所能管辖的所有子部门
					$department_ids = trim($department_ids,',').','.$parent_deptid;	// 将最上级部门也加上
					$condition .= " and (`group_id` in ($department_ids) or agent_number = '".$_SESSION['userinfo']['extension']."')";	//可能管理的部门不是自己所在部门
				}elseif (count($arr_deptid) == 1 && 0 == $arr_deptid[0]){
					$condition .= " and ( agent_number in ($list_exten, 'ivr') or callee_number = '".$_SESSION['userinfo']['extension']."') ";
				}else {
					$condition .= " and (`group_id` in ($list_deptid) or agent_number = '".$_SESSION['userinfo']['extension']."')";	//可能管理的部门不是自己所在部门
				}
			}

			//座席工号查找
			if (!empty($extension)) {
				$condition	.= " and (agent_number='$extension' or (call_type = '3' and callee_number = '$extension'))";
			}
			//质检状态
			if ('' != $is_qualified) 
			{
				$condition .= " and quality_status='$is_qualified'";
				if ('1' != $is_qualified)
				{
					if ($_REQUEST['newdo'] == 'sys') {
						$condition .= " and bill_sec>0";
					} else {
						$condition .= " and agent_sec>0";
					}
				}
			}

			//组合查询sql
			if($table_list == NULL ) {
				$table_list[] = 'ss_cdr_cdr_info';
			}
			$sql_count = "";
			$sql = "";
			if (empty($table_list)) {
				$sql_count = "select count(distinct id) from ss_cdr_cdr_merge where 1=0";
				$sql = "select distinct * from ss_cdr_cdr_merge where 1=0";
			} else if (count($table_list) == 1) {
				$table = $table_list[0];

				$sql_count = "select count(distinct id) from $table where " . $condition;
				$sql = "select distinct * from $table where " . $condition . " order by start_stamp desc";
			} else {
				foreach ($table_list as $table) {
					if ($sql_count != "") $sql_count .= " union all ";
					$sql_count .= "select count(distinct id) as n from $table where " . $condition;

					if ($sql != "") $sql .= " union all ";
					$sql .= "(select distinct * from $table where " . $condition . ")";
				}
				$sql .= " order by start_stamp desc ";
				$sql_count = "select sum(n) from ($sql_count) as t";
				//$sql = "select * from ($sql) as t order by id desc";
			}
			$record_nums = $db->GetOne($sql_count);

			//导出通话记录
			if ('export_xls' == $do) {
				$this->exportCdrXls($sql);
				exit();
			}
			//导出通话录音
			else if ('export_record' == $do) {
				$this->exportCdrRecord($sql, $record_nums);
				exit;
			}

			
			$this->Tmpl['record_nums'] = $record_nums;

			$pg = loadClass('tool','page',$this);
			$pg->setPageVar('p');
			$pg->setNumPerPage( 20 );

			$currentPage = $_REQUEST['p'];
			unset($_REQUEST['p']);
			unset($_REQUEST['action']);
			unset($_REQUEST['module']);
			unset($_REQUEST['cfg_traffic_header']);

			$pg->setVar($_REQUEST);
			$pg->setVar(array("module"=>"report","action"=>"cdrList"));
			$pg->set($record_nums,$currentPage);
			$this->Tmpl['show_pages'] = $pg->output(1);
			if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
				echo $db->ErrorMsg();
				exit();
			}
 			//var_dump($sql);
			global $cache_department;  //加载部门缓存
			//加密处理
			$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );
			$this->Tmpl['flag_hidden'] = $flag_hidden;

			while (!$rs->EOF) {
				if (1 == $flag_hidden && !empty($rs->fields['caller_number']) && strlen($rs->fields['caller_number'])>6){
					$rs->fields['caller_number'] = transferPhone($rs->fields['caller_number']);
				}
				if (1 == $flag_hidden && !empty($rs->fields['callee_number']) && strlen($rs->fields['callee_number'])>6){
					$rs->fields['callee_number'] = transferPhone($rs->fields['callee_number']);
				}
				if (1 == $flag_hidden && !empty($rs->fields['transfer_number']) && strlen($rs->fields['transfer_number'])>6){
					$rs->fields['transfer_number'] = transferPhone($rs->fields['transfer_number']);//转接号码的隐藏
				}

				//因底层无法实现给datetime字段填充默认值NULL, 因此由应用层将时间格式为"0000-00-00 00:00:00"的数据整为NULL
				foreach ($rs->fields as $key => $value) {
					if ('0000-00-00 00:00:00' == $rs->fields[$key]) $rs->fields[$key] = null;
				}

				$rs->fields['user_name'] = '';
				$rs->fields['dept_name'] = '';
				if (!empty($rs->fields['agent_number'])) {
					$u = $this->getUserByExten($rs->fields['agent_number']);
					$rs->fields['user_name'] = $u['user_name'] . ' (' . $rs->fields['agent_number'] . ')';
					$rs->fields['dept_name'] = $cache_department[$rs->fields['group_id']]['dept_name'];
				}

				if(1==$rs->fields['call_type']){//"呼入"
					$sql="select id,name from crm_customerinfo where phone1='{$rs->fields['caller_number']}' or phone2='{$rs->fields['caller_number']}' or phone3='{$rs->fields['caller_number']}' ";
				}elseif(2==$rs->fields['call_type']){//呼出
					$sql="select id,name from crm_customerinfo where phone1='{$rs->fields['callee_number']}' or phone2='{$rs->fields['callee_number']}' or phone3='{$rs->fields['callee_number']}' ";
				}
				
				$customerinfos=$db->GetRow($sql);
// 				var_dump($customerinfos);
                if($customerinfos){
                	$rs->fields['customer_id']=$customerinfos['id'];
                	$rs->fields['customer_name']=$customerinfos['name'];
                }
			
				$rs->fields['filename'] = $this->getRecordFile($rs->fields['call_id']);
				//print_r(pathinfo($rs->fields['filename']));
				if (!empty($rs->fields['filename'])) {	//三方通话录音2
					$rs->fields['threecall_filename'] = $this->getRecordMeetmeFile($rs->fields['call_id']);
					//$file_pathinfo = pathinfo($rs->fields['filename']);
					//$file_record_name = $file_pathinfo['dirname'] . '/meetme-' . $file_pathinfo['basename'];
					//if (!file_exists($file_record_name)) {
					//	$file_record_name = $file_pathinfo['dirname'] . '/meetme-' . $file_pathinfo['basename'];
					//}
					//$rs->fields['threecall_filename'] = is_file($file_record_name) ? $file_record_name : '';
					//$rs->fields['threecall_filename'] = $file_record_name;
				}
				$list[] = $rs->fields;
				$rs->MoveNext();
			} // end while (!$rs->EOF)

		} // end if ('' != $do)
		$this->Tmpl['list'] = $list;
		$qualityFlag = $this->isAuth('record_quality', $arr_local_priv, '' );
		$this->Tmpl['qualityFlag'] = $qualityFlag;//质检权限
		$this->display();
	}

	/**
	 * 
	 * 质检员业务统计报表
	 * 
	 */
	function showBusinessStatistics(){ 
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'BusinessStatistics_sel', $arr_local_priv, '您没有查看质检员业务统计报表的权限！' );
	
		
		//获得部门列表
		if (1 != $_SESSION['userinfo']['power']) {
			$manager_dept = $this->getManageDept($_SESSION['userinfo_detail']['user_id']);
			$sql = "SELECT * FROM org_department where dept_id = '".$_SESSION['userinfo_detail']['dept_id']."'";
			if (!empty($manager_dept)) {
				$sql .= " or find_in_set(dept_id, '".implode(',', $manager_dept)."') > 0";
			}
		} else {
			$sql = "SELECT * FROM org_department";
		}
		$dept = $db->GetAll( $sql );
		
		$depart_id=$_REQUEST['depart_id'];
		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;
		
		
		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;
		
		$depart_id=$_REQUEST['depart_id'];
		
		/* 处理查询 */
		$where = " WHERE record_number<>'' ";
		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 0); //只显示当天数据
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		
		if (isset($_REQUEST['fromdate']) && !empty($_REQUEST['fromdate'])) { // 通话时间起
			$where .= " AND create_time>='".$_REQUEST['fromdate']." 00:00:00' ";
		}
		if (isset($_REQUEST['todate']) && !empty($_REQUEST['todate'])) { // 通话时间止
			$where .= " AND create_time<='".$_REQUEST['todate']." 23:59:59' ";
		}
		
		$timestr=strtotime($_REQUEST['todate'])-strtotime($_REQUEST['fromdate']);
		if($timestr>93*24*60*60){
			goBack(c('查询的日期不能超过3个月.'));
		}
		//$where .= $extenWhere;
		//根据”部门条件“组合条件
		/*筛选出表里面的质检员  查询的结果 */
		
		
		$extenSelect = array();
		if (!empty($depart_id)) {
			$exten_arr = array();
			//获取所有的子部门
			$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
			$list_depart .= "$depart_id";	//加上所选部门
		
			//获取所选部门的座席列表
			$sql = "SELECT extension, user_name FROM org_user WHERE find_in_set(dept_id, '$list_depart') > 0 and extension != '' and extension is not null";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				$where .= " and (find_in_set(dept_id, '".implode(',', $arr_deptid)."') > 0 or extension = '".$_SESSION['userinfo']['extension']."')";
			}
			$rs	= $db->Execute($sql);
			while(!$rs->EOF) {
				$extenSelect[] = $rs->fields['extension'];
				$exten_arr[] = $rs->fields;
				$rs->MoveNext();
			}
			$list_exten = numberToString4Sql(implode(',', $extenSelect));
			$this->Tmpl['extenSelect'] = $exten_arr;
		}
		$list=array();
		$arrDeptId = $db->GetRow("select dept_id,dept_name from org_department where dept_name='".c('质控组')."'");
		if($_REQUEST['do']!=''){
			
			if (!empty($list_exten)) {
				$where .= " and create_user in ($list_exten) ";
			}
			
			$sql_q="select extension,dept_id from org_user where dept_id='".$arrDeptId['dept_id']."'";
			$extension=$db->GetAll($sql_q);
			//
			$tmpl_arr=array();
			foreach ($extension as $k=>$v){
				$dept_id_s=$v['dept_id'];
			   $tmpl_arr[]=$v['extension'];
		   }
		   $str_extension=implode(',', $tmpl_arr);
		   $str_extension=trim($str_extension,',');
			if($str_extension){
				$where .=" and create_user in (".$str_extension.")";
			}
			
			if(!empty($_REQUEST['extension'])){
				$where .= " and create_user = '{$_REQUEST['extension']}' ";
			}
			if(!empty($_REQUEST['result'])){
				if($_REQUEST['result']==1){
					$result=1;
				}elseif($_REQUEST['result']==2){
					$result=0;
				}
				$where .= " and quality_passed = '{$result}' ";
			}
			
			$this->Tmpl['result'] = $_REQUEST['result'];
			//查询客服质检方案
			$sql = "select * from stdout_quality_plan where is_enable=1 and is_deleted=0 and is_build=1 and is_build_again=0 and is_kehu=1";
			$kehuPlan = $db->GetRow($sql);
			$qpid=$kehuPlan['id'];
			
			if (empty($qpid) && !empty($_REQUEST['do'])) {
				goBack(c('查询失败：系统未配置客服质检方案, 或请检查质检方案是否已启用及生成.'));
			}
			$qualityTable = 'stdout_quality_record_' . $qpid; //设置质检方案表名
			
			if($kehuPlan['standard_score']<>'' || $kehuPlan['standard_err_fatal']<>'' || $kehuPlan['standard_err_common']<>''){//判断质检是否通过的条件
				//$standardFlag=1;
				$pass_tmp="sum(";
				if($kehuPlan['standard_score']<>'')
				{
					$kehuPlan['standard_score'] = trim($kehuPlan['standard_score'],',');
					$pass_std[]=" IF(quality_score>".$kehuPlan['standard_score'].",1,0)";
				}
				if($kehuPlan['standard_err_fatal']<>'')
				{
					$kehuPlan['standard_err_fatal'] = trim($kehuPlan['standard_err_fatal'],',');
					$pass_std[]=" IF(fatal_errors<".$kehuPlan['standard_err_fatal'].",1,0)";
				}
				if($kehuPlan['standard_err_common']<>'')
				{
					$kehuPlan['standard_err_common'] = trim($kehuPlan['standard_err_common'],',');
					$pass_std[]=" IF(common_errors<".$kehuPlan['standard_err_common'].",1,0)";
				}
				$pass_tmp.=implode(' AND ',$pass_std)." ) as cnt_passed";
				$where_pass=' ,'.$pass_tmp;
			}else{
				$where_pass="";
			}
			
			if ('search' == $_REQUEST['do']) {
			
				//
				//获取总记录数(用于分页)
				//
				$sql = "select count(distinct create_user) as n from {$qualityTable} {$where} ";
				$record_nums = $db->GetOne($sql);
				$pg = loadClass('tool','page',$this);
				$pg->setPageVar('p');
				$pg->setNumPerPage( 20 );
			
				$currentPage = $_REQUEST['p'];
				unset($_REQUEST['p']);
				unset($_REQUEST['action']);
				unset($_REQUEST['module']);
				unset($_REQUEST['btn_search']);
				unset($_REQUEST['PHPSESSID']);
			
				$pg->setVar($_REQUEST);
				$pg->setVar(array("module"=>"report", "action"=>"businessStatistics"));
				$pg->set($record_nums,$currentPage);
				$this->Tmpl['record_nums'] = $record_nums;
				$this->Tmpl['show_pages'] = $pg->output(1);
			}
			
			//计算每个通话记录的平均值后，再用来计算每个座席的平均值
			// 		$sql ="select dept_id,extension, count(0) as cnt,avg(quality_score) as quality_score ,avg(ded_score) as ded_score,sum(fatal_errors) as fatal_errors,sum(common_errors) as common_errors {$where_pass}
			// 		from (select dept_id,extension, avg(quality_score) as quality_score, avg(ded_score) as ded_score,avg(fatal_errors) as fatal_errors, avg(common_errors) as common_errors from {$qualityTable} {$where} group by record_number) as t
			// 		group by t.extension ";
			
			$sql ="select dept_id,create_user, extensions, count(0) as cnt,avg(quality_score) as quality_score ,avg(ded_score) as ded_score,sum(fatal_errors) as fatal_errors,sum(common_errors) as common_errors {$where_pass}
			from (select dept_id,create_user,extension as extensions, avg(quality_score) as quality_score, avg(ded_score) as ded_score,avg(fatal_errors) as fatal_errors, avg(common_errors) as common_errors from {$qualityTable} {$where} group by record_number) as t
			group by t.create_user ";
			
			if ('search' == $_REQUEST['do']||'' == $_REQUEST['do']) {
			    if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
					echo $sql . "<br/><br/>";
					echo $db->ErrorMsg();
					$db->Close();
					exit();
			  	}
			}else {
				if (!$rs = $db->Execute($sql)) {
					echo $sql . "<br/><br/>";
					echo $db->ErrorMsg();
					$db->Close();
					exit();
				}
			}
			
			if ('export' == $_REQUEST['do']) {
			// 获取当前用户权限
				$export_dir1 = '/data/justcall/html/export' . "/"; // (/data/justcall/html/export)
				if (! is_dir ( $export_dir1 )) {
					mkdir ( $export_dir1, 0777 );
				}
				$export_dir = $export_dir1 . date ( "Ym" );
				if (! is_dir ( $export_dir )) {
					mkdir ( $export_dir, 0777 );
				}
			
			$dir_name = "bus-quality-" . date ( "Ymd" ); // 文件名
			$sub_dir = $export_dir . "/" . $dir_name;
			if (! is_dir ( $sub_dir )) {
				mkdir ( $sub_dir, 0777 );
			}
			
			$xls_columns = array (
				'id' => '部门',
				'caller_number' => '质检员',
				'callee_number' => '质检总数',
				'user_name' => '通过数',
				'customer_name' => '未通过数',
				'dept_name' => '通过率',
				'transfer_number' => '质检通话总时长',
				'trans_by' => '质检平均通话时长',
			);
			$lines = array ();
			$line = "";
			$filename = "Bus-" . date ( "YmdHis" ) . ".csv";
			$fp = fopen ( $filename, "w" );
			$line = "导出时间 ：," . date ( 'Y-m-d H:i:s' );
			fputs ( $fp, $line . "\r\n" );
			$line = implode ( ',', $xls_columns );
			fputs ( $fp, $line . "\r\n" );
			} // end if ('export' == $_REQUEST['do'])
					
			$arr_total = array(
				'cnt'			=> 0,
				'cnt_passed'	=> 0,
				'cnt_notpass'	=> 0,
				'accuracy'	=> 0,
				'agent_sec'		=> 0,//通话时长
				'avg_agent_sec'	=> 0,//质检平均通话时长
			);
			//print_r($sql);
			global $cache_department;
			while (!$rs->EOF) {
			$rs->fields['agent_sec']=0;

			$sql="select record_number,task_create_time from {$qualityTable} {$where} and  create_user = {$rs->fields['create_user']} group by record_number ";
	      // echo $sql;

			$res= $db->GetAll($sql);
			foreach ($res as $k=>$v){
			    $record_number=$v['record_number'];
					//通话时长
				$searchMonth = date("ym", strtotime($v['task_create_time']));
								
				/*-------------------------add by lirq 20160616----------------------------*/
				$currentM =  date("ym", time());
				$preM = date("ym",strtotime("-1 month"));
				if($searchMonth == $currentM || $searchMonth == $preM){
				   $searchTable = "ss_cdr_cdr_merge";
				}else{
				   $searchTable = "ss_cdr_cdr_info_".$searchMonth;
				}
				$sqlCdr = "select agent_sec from {$searchTable} where id=".$record_number;
               
				$reCdr = $db->GetOne($sqlCdr);
				if($reCdr){
					$rs->fields['agent_sec'] += $reCdr;
				}
			}
									
				$rs->fields['dept_id'] = $cache_department[$dept_id_s]['dept_name'];
				$exten_tmp=$this->getNameByExten($rs->fields['create_user']);
				$rs->fields['extension'] = $exten_tmp? $exten_tmp. '(' . $rs->fields['create_user'] . ')':$rs->fields['extension'];//被质检的座席
				$rs->fields['cnt_notpass']=$rs->fields['cnt']-$rs->fields['cnt_passed'];
			    $rs->fields['avg_agent_sec']=round(($rs->fields['agent_sec']/$rs->fields['cnt']), 0);
				
				$rs->fields['agent_sec'] = $this->getTime($rs->fields['agent_sec']);
				$rs->fields['avg_agent_sec'] = $this->getTime($rs->fields['avg_agent_sec']);
			
								//计算每条记录的通过率
			    $accuracy = '0';
				if ($rs->fields['cnt']>0) $accuracy = round(($rs->fields['cnt_passed']/$rs->fields['cnt'])*100, 1) . '%';
				$rs->fields['accuracy'] = $accuracy;
	
				//合计
				$arr_total['cnt'] += $rs->fields['cnt'];
				$arr_total['cnt_passed'] += $rs->fields['cnt_passed'];
				$arr_total['cnt_notpass'] += $rs->fields['cnt_notpass'];
					
				$arr_total['agent_sec'] += $rs->fields['agent_sec'];
				
			
							
				if ('export' == $_REQUEST['do']) {
				    fputs ( $fp, iconv("UTF-8", "GB2312//IGNORE",$rs->fields['dept_id']) . "," );
					fputs ( $fp, iconv("UTF-8", "GB2312//IGNORE",$rs->fields['extension']) . "," );
					fputs ( $fp, $rs->fields['cnt'] . "," );
					fputs ( $fp, $rs->fields['cnt_passed'] . "," );
				    fputs ( $fp, $rs->fields['cnt_notpass'] . "," );
					fputs ( $fp, $rs->fields['accuracy'] . "," );
					//fputs ( $fp, $rs->fields['agent_sec'] . "," );
					//fputs ( $fp, $rs->fields['avg_agent_sec'] . "," );
					fputs ( $fp, iconv("UTF-8", "GB2312//IGNORE",$rs->fields['agent_sec']) . "," );
					fputs ( $fp, iconv("UTF-8", "GB2312//IGNORE",$rs->fields['avg_agent_sec']) . "," );
					fputs ( $fp, "\r\n" );
			
			}
		
						$list[] = $rs->fields;
						$rs->MoveNext();
		}
								//
										//处理合计数组
										//
		if (count($list) > 1) {
			$i = count($list);
				
			//计算合计数组的准确率
			$accuracy = '0';
			if ($arr_total['cnt']>0) $accuracy = round(($arr_total['cnt_passed']/$arr_total['cnt'])*100, 1) . '%';
				$arr_total['accuracy'] = $accuracy;
					
				$avg_agent_sec = '0';
				if ($arr_total['cnt']>0) $avg_agent_sec = round(($arr_total['agent_sec']/$arr_total['cnt']), 0);
						//$arr_total['avg_agent_sec'] = $avg_agent_sec;
						$arr_total['avg_agent_sec'] = $this->getTime($avg_agent_sec);
						$arr_total['agent_sec'] = $this->getTime($arr_total['agent_sec']);	
						$this->Tmpl['arr_total'] = $arr_total;
			}
			
	
			if ('export' == $_REQUEST['do'] ) {
				if(count($list)>1){
					fputs ( $fp,  "," );
					fputs ( $fp, "合计," );
					fputs ( $fp, $arr_total['cnt'] . "," );
					fputs ( $fp, $arr_total['cnt_passed'] . "," );
					fputs ( $fp, $arr_total['cnt_notpass'] . "," );
					fputs ( $fp, $arr_total['accuracy'] . "," );
					//fputs ( $fp, $arr_total['agent_sec'] . "," );
					//fputs ( $fp, $arr_total['avg_agent_sec'] . "," );
					fputs ( $fp, iconv("UTF-8", "GB2312//IGNORE",$arr_total['agent_sec']) . "," );
					fputs ( $fp, iconv("UTF-8", "GB2312//IGNORE",$arr_total['avg_agent_sec']) . "," );
					fputs ( $fp, "\r\n" );
				}
				fclose ( $fp );
				ob_end_clean();
				header ( "Pragma: public" );
				header ( "Expires: 0" );
				header ( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
				header ( "Content-Type: application/force-download" );
				header ( "Content-Type: application/octet-stream" );
				header ( "Content-Type: application/download" );
				header ( "Content-Disposition: attachment;filename=" . $filename . "" );
				header ( "Content-Transfer-Encoding: binary " );
	
				readfile ( $filename );
				exit ();
			}
		}
		
		
		$this->Tmpl['list'] = $list;
		$this->display();
	}

	//把时长改为0时0分0秒这种格式
	function getTime($sec){
		$h = intval( $sec / 3600 );
		$m = ($sec % 3600);
		$ym = intval( $m / 60 );
		$sec = $m % 60;
		return   $h.c("时").$ym.c("分").$sec .c("秒");
	}

	/**
     +----------------------------------------------------------
     * 通话记录
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function showCdrDetail()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'cdr_view', $arr_local_priv, '您没有查看通话记录的权限！' );

		//加密处理
		$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );

		$request = varFilter($_REQUEST);
		$id = intval($request['id']);
		$start_stamp = $request['start_stamp'];
		$table = (date("ym", strtotime($start_stamp)) == date("ym")) ? 'cdr_info' : 'cdr_info_'.date("ym", strtotime($start_stamp));
		$sql = "select * from ss_cdr_{$table} where id={$id} limit 1";
		$row = $db->GetRow($sql);
		if (!$row) {
			goBack(c('通话记录不存在.'));
		}

		//
		//因底层无法实现给datetime字段填充默认值NULL, 因此由应用层将时间格式为"0000-00-00 00:00:00"的数据整为NULL
		//
		foreach ($row as $key => $value)
		{
			if ('0000-00-00 00:00:00' == $row[$key]) $row[$key] = null;
			if($flag_hidden == 1)
			{
				if($key == 'caller_number' && !empty($row[$key]) && strlen($row[$key])>6)//本机座席不隐藏
				{
					$row[$key] = transferPhone($row[$key]);
				}
				if($key == 'callee_number' && !empty($row[$key]) && strlen($row[$key])>6 )//本机座席不隐藏
				{
					$row[$key] = transferPhone($row[$key]);
				}
				if($key == 'threecall' && !empty($row[$key]) && strlen($row[$key])>6)//第三方通话号码
				{
					$row[$key] = transferPhone($row[$key]);
				}
				if($key == 'transfer_number' && !empty($row[$key]) && strlen($row[$key])>6)//转接号码
				{
					$row[$key] = transferPhone($row[$key]);
				}
				if($key == 'shift_number' && !empty($row[$key]) && strlen($row[$key])>6)//转移号码
				{
					$row[$key] = transferPhone($row[$key]);
				}
			}
		}

		$columns = array(
			'id'				=> 'id',
			'caller_number'		=> '主叫号码',
			'callee_number'		=> '被叫号码',
			'user_name'			=> '接听座席',
			'transfer_number'	=> '转接号码',
			'trans_by'			=> '转入座席',
			'call_id'			=> '对话id',
			'start_stamp'		=> '呼叫时间',
			'end_stamp'			=> '呼叫结束时间',
			'ring_stamp'		=> '振铃时间',
			'ivr_answer_stamp'	=> 'ivr应答时间',
			'ivr_end_stamp'		=> 'ivr通话结束时间',
			'agent_answer_stamp' => '座席应答时间',
			'agent_end_stamp'	=> '座席通话结束时间',
			'duration'			=> '呼叫时长',
			'bill_sec'			=> '呼叫计费时长',
			'ivr_sec'			=> 'ivr通话时长',
			'agent_sec'			=> '座席通话时长',
			'wait_agent_sec'	=> '排队等待时长',
			//'wait_agent_ans_sec' => '排队座席应答时长',
			//'wait_ring_sec'		=> '振铃时长',
			'wait_agent_ans_sec' => '振铃时长', //底层要求将"排队座席应答时长"显示为"振铃时长", 使客户较容易理解
			'hangup_code'		=> '挂断原因',
			'call_type'			=> '呼叫类型',
			'dept_name'			=> '部门',
			'province'			=> '省份',
			'area'				=> '城市/地区',
			//'dst_context'		=> 'dst_context',
			//'channel'			=> '通道名称',
			//'dst_channel'		=> 'dst_channel',
			'queue'				=> '转人工服务队列',
			'use_ivr'			=> 'ivr导航',
			'ivr_key'			=> 'ivr按键',
			'use_manual'		=> '转人工服务',
			'shift_number'		=> '转移号码',
			'threecall'			=> '三方通话号码',
			//'trans_ivr_times'	=> '转ivr次数',
			//'trans_inner_times'	=> '内部转移次数',
			//'trans_out_times'	=> '转出次数',
			'agent_hold_times'	=> '保持次数',
			//'internal_help_times' => '内部求助次数',
			//'filename'			=> '录音'
		);

		foreach ($columns as $key => $value)
		{
			$columns[$key] = c($columns[$key]);
		}

		$this->Tmpl['columns'] = $columns;

		global $cache_department;

		$row['user_name'] = '';
		$row['dept_name'] = '';
		if (!empty($row['agent_number'])) {
			$u = $this->getUserByExten($row['agent_number']);
			$row['user_name'] = $u['user_name'] . '(' . $row['agent_number'] . ')';
			$row['dept_name'] = $cache_department[$row['group_id']]['dept_name'];
		}

		if (!empty($row['trans_by'])) {
			$u = $this->getUserByExten($row['trans_by']);
			$row['trans_by'] = $u['user_name'] . '(' . $row['trans_by'] . ')';
		}

		if (1 == $row['hangup_code']) $row['hangup_code'] = c('主叫挂断');
		else if (2 == $row['hangup_code']) $row['hangup_code'] = c('被叫挂断');
		else if (3 == $row['hangup_code']) $row['hangup_code'] = c('系统挂断');
		else if (4 == $row['hangup_code']) $row['hangup_code'] = c('转接挂断');
		else $row['hangup_code'] = c('未知');

		if (1 == $row['call_type']) $row['call_type'] = c('呼入');
		else if (2 == $row['call_type']) $row['call_type'] = c('呼出');
		else if (3 == $row['call_type']) $row['call_type'] = c('分机互打');
		else $row['call_type'] = c('未知');

		$row['use_ivr'] = 1 == $row['use_ivr'] ? c('转入') : c('未转入');
		$row['use_manual'] = 1 == $row['use_manual'] ? c('转人工服务') : c('-');

		$filename = $this->getRecordFile($row['call_id']);
		$row['filename'] = c("<a href='index.php?module=callLog&action=recordingPlay&filename=$filename&exten=".$row['agent_number']."&calltime=".$row['start_stamp']."' target='_blank' class='blue'>播放</a>");

		$this->Tmpl['row'] = $row;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 导出通话记录
     * @author	: pengj
     * @date	: 2012/3/2
     +----------------------------------------------------------
     */
	function exportCdrXls($sql)
	{
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);

		//导出
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=cdr_info.xls ");
		header("Content-Transfer-Encoding: binary ");

		xlsBOF();

		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		$xls_columns = array(
			'id'				=> 'id',
			'caller_number'		=> '主叫号码',
			'callee_number'		=> '被叫号码',
			'user_name'			=> '接听座席',
			'customer_name'		=> '客户名称',
			'dept_name'			=> '部门',
			'transfer_number'	=> '转接号码',
			'trans_by'			=> '转入座席',
			'call_id'			=> '对话id',
			'start_stamp'		=> '呼叫时间',
			'end_stamp'			=> '呼叫结束时间',
			'ring_stamp'		=> '振铃时间',
			'ivr_answer_stamp'	=> 'ivr应答时间',
			'ivr_end_stamp'		=> 'ivr通话结束时间',
			'agent_answer_stamp' => '座席应答时间',
			'agent_end_stamp'	=> '座席通话结束时间',
			'duration'			=> '呼叫时长',
			'bill_sec'			=> '呼叫计费时长',
			'ivr_sec'			=> 'ivr通话时长',
			'agent_sec'			=> '座席通话时长',
			'wait_agent_sec'	=> '排队等待时长',
			//'wait_agent_ans_sec' => '排队座席应答时长',
			//'wait_ring_sec'		=> '振铃时长',
			'wait_agent_ans_sec' => '振铃时长', //底层要求将"排队座席应答时长"显示为"振铃时长", 使客户较容易理解
			'hangup_code'		=> '挂断原因',
			'call_type'			=> '呼叫类型',
			'province'			=> '省份',
			'area'				=> '城市/地区',
			//'dst_context'		=> 'dst_context',
			//'channel'			=> '通道名称',
			//'dst_channel'		=> 'dst_channel',
			'queue'				=> '转人工服务队列',
			'use_ivr'			=> 'ivr导航',
			'ivr_key'			=> 'ivr按键',
			'use_manual'		=> '转人工服务',
			'shift_number'		=> '转移号码',
			'threecall'			=> '三方通话号码',
			//'trans_ivr_times'	=> '转ivr次数',
			//'trans_inner_times'	=> '内部转移次数',
			//'trans_out_times'	=> '转出次数',
			'agent_hold_times'	=> '保持次数',
			//'internal_help_times' => '内部求助次数',
			'wrap_up_sec'			=> '话后处理时间 ',
			'monitor_filename'		=> '导出文件名'
		);

		//导出excel, 写表头
		$cols = 0;
		foreach ($xls_columns as $value) xlsWriteLabel(1, $cols++, $value);

		global $db;
		$rs = $db->Execute($sql);
		$rows = 2;

		//加密处理
		$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );
		$this->Tmpl['flag_hidden'] = $flag_hidden;

		global $cache_department;
		while (!$rs->EOF) {
			if (1 == $flag_hidden && !empty($rs->fields['caller_number']) && strlen($rs->fields['caller_number'])>6){
				$rs->fields['caller_number'] = transferPhone($rs->fields['caller_number']);
			}
			if (1 == $flag_hidden && !empty($rs->fields['callee_number']) && strlen($rs->fields['callee_number'])>6){
				$rs->fields['callee_number'] = transferPhone($rs->fields['callee_number']);
			}
			if (1 == $flag_hidden && !empty($rs->fields['threecall']) && strlen($rs->fields['threecall'])>6){
				$rs->fields['threecall'] = transferPhone($rs->fields['threecall']);//第三方通话号码的隐藏
			}
			if (1 == $flag_hidden && !empty($rs->fields['transfer_number']) && strlen($rs->fields['transfer_number'])>6){
				$rs->fields['transfer_number'] = transferPhone($rs->fields['transfer_number']);//转接号码
			}
			if (1 == $flag_hidden && !empty($rs->fields['shift_number']) && strlen($rs->fields['shift_number'])>6){
				$rs->fields['shift_number'] = transferPhone($rs->fields['shift_number']);//转移号码
			}
			//
			//因底层无法实现给datetime字段填充默认值NULL, 因此由应用层将时间格式为"0000-00-00 00:00:00"的数据整为NULL
			//
			foreach ($rs->fields as $key => $value)
			{
				if ('0000-00-00 00:00:00' == $rs->fields[$key]) $rs->fields[$key] = null;
			}

			$cols = 0;

			$rs->fields['user_name'] = '';
			$rs->fields['dept_name'] = '';
			if (!empty($rs->fields['agent_number'])) {
				$u = $this->getUserByExten($rs->fields['agent_number']);
				$rs->fields['user_name'] = iconv("UTF-8", "GB2312//IGNORE", $u['user_name']) . '(' . $rs->fields['agent_number'] . ')';
				$rs->fields['dept_name'] = iconv("UTF-8", "GB2312//IGNORE", $cache_department[$rs->fields['group_id']]['dept_name']);
			}
			if (!empty($rs->fields['customer_name'])) {
				$rs->fields['customer_name'] = iconv("UTF-8", "GB2312//IGNORE", $rs->fields['customer_name']);
			}

			$rs->fields['province'] =  iconv("UTF-8", "GB2312//IGNORE", $rs->fields['province']);
			$rs->fields['area'] =  iconv("UTF-8", "GB2312//IGNORE", $rs->fields['area']);

			if (!empty($rs->fields['trans_by'])) {
				$u = $this->getUserByExten($rs->fields['trans_by']);
				//$rs->fields['trans_by'] = $u['user_name'] . '(' . $rs->fields['trans_by'] . ')';
				$rs->fields['trans_by'] = iconv("UTF-8", "GB2312//IGNORE", $u['user_name']) . '(' . $rs->fields['trans_by'] . ')';
			}

			if (1 == $rs->fields['hangup_code']) $rs->fields['hangup_code'] = '主叫挂断';
			else if (2 == $rs->fields['hangup_code']) $rs->fields['hangup_code'] = '被叫挂断';
			else if (3 == $rs->fields['hangup_code']) $rs->fields['hangup_code'] = '系统挂断';
			else if (4 == $rs->fields['hangup_code']) $rs->fields['hangup_code'] = '转接挂断';
			else $rs->fields['hangup_code'] = '未知';

			if (1 == $rs->fields['call_type']) $rs->fields['call_type'] = '呼入';
			else if (2 == $rs->fields['call_type']) $rs->fields['call_type'] = '呼出';
			else if (3 == $rs->fields['call_type']) $rs->fields['call_type'] = '分机互打';
			else $rs->fields['call_type'] = '未知';

			$rs->fields['use_ivr'] = 1 == $rs->fields['use_ivr'] ? '转入' : '未转入';
			$rs->fields['use_manual'] = 1 == $rs->fields['use_manual'] ? '转人工服务' : '-';

			foreach ($xls_columns as $key => $value)
			{
				if (strstr($key, '_times') || strstr($key, '_sec')) {
					xlsWriteNumber($rows, $cols++, $rs->fields[$key]);
				}
				else {
					if (!empty($rs->fields[$key]) && $key == 'monitor_filename')
						xlsWriteLabel($rows, $cols++, $rs->fields[$key].'.mp3');
					else
						xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
				}
			}

			$rows ++;
			$rs->MoveNext();
		}

		xlsEOF();
		exit;
	}

	/**
     +----------------------------------------------------------
     * 导出通话录音
     * @author	: pengj
     * @date	: 2012/3/2
     +----------------------------------------------------------
     */
	function exportCdrRecord($sql, $record_nums)
	{
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		//加密处理
		$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );

		if ($record_nums > 3000) {
			goBack(c('导出通话录音的记录条数不允许超过 3000 条.'));
		}

		$this->Cortrol['tmplCacheFile'] = "./tmpl/index/report/cdrExport.tpl.php";
		$this->Tmpl['record_nums'] = $record_nums;

		global $cache_department;  //加载部门缓存

		//获取部门名称
		if (!empty($_REQUEST['depart_id'])) {
			$this->Tmpl['dept_name'] = iconv('UTF8', 'GB2312', $cache_department[$_REQUEST['depart_id']]['dept_name']);
		}

		global $db;
		$rs = $db->Execute($sql);

		$list = array();
		$filelist = array();
		$meetfile_list = array();
		while (!$rs->EOF)
		{
			//因底层无法实现给datetime字段填充默认值NULL, 因此由应用层将时间格式为"0000-00-00 00:00:00"的数据整为NULL
			foreach ($rs->fields as $key => $value)
			{
				if ('0000-00-00 00:00:00' == $rs->fields[$key]) $rs->fields[$key] = null;
				//号码加密的处理
				if($flag_hidden == 1)
				{
					if($key == 'caller_number' && !empty($rs->fields[$key]) && strlen($rs->fields[$key])>6)//本机座席不隐藏
					{
						$rs->fields[$key] = transferPhone($rs->fields[$key]);
					}
					if($key == 'callee_number' && !empty($rs->fields[$key]) && strlen($rs->fields[$key])>6 )//本机座席不隐藏
					{
						$rs->fields[$key] = transferPhone($rs->fields[$key]);
					}
				}
			}

			$rs->fields['user_name'] = '';
			$rs->fields['dept_name'] = '';
			if (!empty($rs->fields['agent_number'])) {
				$u = $this->getUserByExten($rs->fields['agent_number']);
				$rs->fields['user_name'] = iconv('UTF8', 'GB2312', $u['user_name']) . ' (' . $rs->fields['agent_number'] . ')';
				$rs->fields['dept_name'] = $cache_department[$rs->fields['group_id']]['dept_name'];
			}

			$filename = $this->getRecordFile($rs->fields['call_id']);	//本体录音文件
			$meetme_filename = $this->getRecordMeetmeFile($rs->fields['call_id']);		//会议室录音文件
			if (!empty($filename)) {
				$rs->fields['filename'] = $rs->fields['call_id'] . substr($filename, -4);
				$filelist[$rs->fields['monitor_filename']] = $filename;
			}
			if (!empty($meetme_filename)) {
				$rs->fields['threecall_filename'] = $rs->fields['call_id'] . substr($meetme_filename, -4);
				$meetfile_list[$rs->fields['monitor_filename']] = $meetme_filename;
			}

			$list[] = $rs->fields;
			$rs->MoveNext();
		}
		$count = count($list);
		for ($i = 0; $i < $count; $i++)
		{
			if (empty($list[$i]['monitor_filename'])) continue;
			$list[$i]['filename'] = $list[$i]['monitor_filename'].substr($list[$i]['filename'], -4);
			if (!empty($list[$i]['threecall_filename'])) {
				$list[$i]['threecall_filename'] = 'meetme-'.$list[$i]['monitor_filename'].substr($list[$i]['threecall_filename'], -4);
			}
		}
		$this->Tmpl['list'] = $list;


		// 网页内容输出到变量中
		ob_end_clean();
		ob_start();
		$this->display();
		$htmlContent = ob_get_contents();
		ob_end_clean();

		// 准备临时目录
		umask(011);
		$dirname = PBX_LIB_PATH.'backups/cdrexport';
		if (file_exists($dirname)) {
			if (is_dir($dirname)) {
				$time = date('U') - filemtime($dirname);
				if ( $time > 60 ) {
					system("rm -rf $dirname");
				} else {
					die("someone is making cdr export at the same time, please try later");
				}
			} else if (is_file($dirname)) {
				unlink($dirname);
			}
		} // end of file_exists()
		mkdir($dirname, 0777);
		mkdir($dirname . "/files", 0777);

		// 网页内容写入文件
		$fh = fopen(PBX_LIB_PATH."backups/cdrexport/index.html", "w");
		fwrite($fh, $htmlContent);
		fclose($fh);

		// 拷贝声音文件
		foreach ($filelist as $key => $val) {
			system("cp $val " . $dirname . "/files/" . $key . substr($val, -4));
			if (!empty($meetfile_list[$key])) {
				system("cp ".$meetfile_list[$key]." " . $dirname . "/files/meetme-" . $key . substr($meetfile_list[$key], -4));
			}
		}

		//拷贝图片与js
		$sys_pic_cmd1 = "cd ".PBX_LIB_PATH."backups/cdrexport/ && cp ".HTML_PATH."userweb/tmpl/index/report/jquery.min.js .";
		system($sys_pic_cmd1);
		$sys_pic_cmd2 = "cd ".PBX_LIB_PATH."backups/cdrexport/ && mkdir images && cp ".HTML_PATH."userweb/tmpl/index/report/images/* images/";
		system($sys_pic_cmd2);

		//打包
		system("cd ".PBX_LIB_PATH."backups && tar zcf recording.tgz cdrexport/ && sleep 1");
		$_GET['recording'] = PBX_LIB_PATH.'backups/recording.tgz';
		$this->showFileRead();
		system ( "rm -f " . $_GET['recording'] ); //delete export file 'recording     .tgz'
		system ( "rm -rf ".PBX_LIB_PATH."backups/cdrexport" ); //delete html file & wav files
	}

	/**
     +----------------------------------------------------------
     * 座席考勤报表
     * @author	: pengj
     * @date	: 2012/2/27
     +----------------------------------------------------------
     */
	function showAgentCheck()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'agent_check', $arr_local_priv, '您没有查看座席考勤的权限！' );

		//非admin管理员, 获取其所能管理的所有工号列表
		if (AGENT_ATTEND_REPORTING_AUTH == 'enable'){
			$arr_exten = array();
			$list_exten = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);

				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}
		}

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time() - 86400 * 1);
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		$dep = array();
		foreach($dept as $k=>$val){
			$dep[$k] = $val['dept_name'];
		}
		//转为JSON数据
		$json_obj = $this->_get_json_obj();
		$this->Tmpl['dep'] = $json_obj->encode($dep);

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);

		$this->Tmpl['deptSelect'] = $deptOptions;

		//获取考勤示忙状态(用于表头)
		$header = $this->getAgentBusyList();
		$this->Tmpl['header'] = $header;		//设置条件查询记录

		$list = array();
		if ('' != $do) {
			$condition = " 1 ";
			if (!empty($fromdate)) {
				$condition .= " and total_date>='$fromdate'";
			}

			if (!empty($todate)) {
				$todate .= " 23:59:59";
				$condition .= " and total_date<='$todate'";
			}

			//部门查找
			$extenSelect = array();
			if (!empty($depart_id)) {
				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";	//加上所选部门

				$condition .= " and `group_id` in ($list_depart)";

				//获取所选部门的座席列表
				$rs	= $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while(!$rs->EOF) {
					$extenSelect[] = $rs->fields;
					$rs->MoveNext();
				}

				$this->Tmpl['extenSelect'] = $extenSelect;
			} // end if (!empty($depart_id))

			if (AGENT_ATTEND_REPORTING_AUTH == 'enable'){
				if (1 != $_SESSION['userinfo']['power']) {
					if (count($arr_deptid) == 1 && 0 == $arr_deptid[0]) {
						$condition .= " and login_id in ($list_exten)";
					}
					else {
						$condition .= " and `group_id` in ($list_deptid)";
					}
				}
			}

			//座席工号查找
			if (!empty($extension)) {
				$condition	.= " and login_id='$extension' ";
			}

			if ('search' == $do) {
				//获取总记录数
				//$sql = "select login_id, count(0) from agent_attend_total where " . $condition . " group by login_id";
				//$sql = "select count(0) from ($sql) as t";
				$sql = "select count(distinct login_id) from crm_agent_attend_total where " . $condition;
				$record_nums = $db->GetOne($sql);

				$pg = loadClass('tool','page',$this);
				$pg->setPageVar('p');
				$pg->setNumPerPage( 20 );

				$currentPage = $_REQUEST['p'];
				unset($_REQUEST['p']);
				unset($_REQUEST['action']);
				unset($_REQUEST['module']);
				unset($_REQUEST['cfg_traffic_header']);
				$pg->setVar($_REQUEST);
				$pg->setVar(array("module"=>"report","action"=>"agentCheck"));
				$pg->set($record_nums,$currentPage);
				$this->Tmpl['show_pages'] = $pg->output(1);
			}
			else {
				//导出
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=agent_attend_total.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);

				$xls_columns = array(
					'工号',
					'姓名',
					'部门',
					'登录次数',
					'注销次数'
				);
				foreach ($header as $v){
					$xls_columns[] = iconv('UTF-8', 'GB2312//IGNORE', $v['name']) . '次数';
					$xls_columns[] = iconv('UTF-8', 'GB2312//IGNORE', $v['name']) . '时长';
				}
				$xls_columns[] = '工作时长';
				$xls_columns[] = '工作态时长';
				$xls_columns[] = '工时利用率';
				//$xls_columns[] = '话后处理次数';
				$xls_columns[] = '话后时长';
				//$xls_columns[] = '话后处理平均时长';

				//导出excel, 写表头
				$cols = 0;
				foreach ($xls_columns as $value) xlsWriteLabel(1, $cols++, $value);

			}

			$field_list = "sum(sign_in_times) as sign_in_times, sum(sign_out_times) as sign_out_times";

			foreach ($header as $val)
			{
				$field_list .= ", sum(say_busy_times_".$val['id'].") as say_busy_times_".$val['id'];
				$field_list .= ", sum(say_busy_duration_".$val['id'].") as say_busy_duration_".$val['id'];
			}
			$field_list .= ", sum(online_duration) as online_duration, sum(working_duration) as working_duration";

			$sql = "select login_id, name, `group_id`, `wrap_up_duration`, ".$field_list."
					from crm_agent_attend_total where ".$condition." group by login_id asc";

			if ('search' == $do) {
				//查询,分页,设置查询字段列表
				if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
					echo $db->ErrorMsg();
				}
			}
			else {
				$rs = $db->Execute($sql);
			}

			$rows = 2;
			global $cache_department;
			while (!$rs->EOF) {
				$cols = 0;
				$rs->fields['dept_name'] = $cache_department[$rs->fields['group_id']]['dept_name'];

				$u = $this->getUserByExten($rs->fields['login_id']);
				@$rs->fields['man_hour_util_rate'] = round($rs->fields['working_duration'] / $rs->fields['online_duration'], 4);
				
				@$rs->fields['wrap_up_duration_time']	= $rs->fields['wrap_up_duration'];
				@$rs->fields['wrap_up_duration']		= $this->SecondsToTime($rs->fields['wrap_up_duration']);	//话后时长
				@$rs->fields['online_duration_time']		= $rs->fields['online_duration'];
				@$rs->fields['online_duration']			= $this->SecondsToTime($rs->fields['online_duration']);		//工作时长
				@$rs->fields['working_duration_time']	= $rs->fields['working_duration'];
				@$rs->fields['working_duration']		= $this->SecondsToTime($rs->fields['working_duration']);	//工作态时长

				foreach ($header as $val){
					@$rs->fields['say_busy_duration_'.$val['id']] = $this->SecondsToTime($rs->fields['say_busy_duration_'.$val['id']]);	//工作态时长
				}

				if ('export' == $do) {
					xlsWriteLabel($rows, $cols++, $rs->fields['login_id']);
					xlsWriteLabel($rows, $cols++, iconv('UTF-8', 'GB2312//IGNORE', $rs->fields['name']));
					xlsWriteLabel($rows, $cols++, iconv('UTF-8', 'GB2312//IGNORE', $rs->fields['dept_name']));
					xlsWriteLabel($rows, $cols++, $rs->fields['sign_in_times']);
					xlsWriteLabel($rows, $cols++, $rs->fields['sign_out_times']);
					foreach ($header as $v){
						xlsWriteLabel($rows, $cols++, $rs->fields['say_busy_times_' . $v['id']]);
						xlsWriteLabel($rows, $cols++, $rs->fields['say_busy_duration_' . $v['id']]);
					}
					xlsWriteLabel($rows, $cols++, $rs->fields['online_duration']);
					xlsWriteLabel($rows, $cols++, $rs->fields['working_duration']);
					$rs->fields['man_hour_util_rate'] = ($rs->fields['man_hour_util_rate'] * 100) . '%';
					xlsWriteLabel($rows, $cols++, $rs->fields['man_hour_util_rate']);
					xlsWriteLabel($rows, $cols++, $rs->fields['wrap_up_duration']);
				}


				if ('search' == $do) $list[] = $rs->fields;

				$rows ++;
				$rs->MoveNext();
			}

			if ('export' == $do) {
				xlsEOF();
				exit;
			}

			if ('search' == $do) $list = count($list) < 1 ? array() : $list;
		}

		$this->Tmpl['list'] = $list;
		$json_list = array();
		$json_list[0]['name'] = c('签入次数');
		$json_list[1]['name'] = c('签出次数');
		$json_list[2]['name'] = c('工作时长');
		$json_list[3]['name'] = c('工作态时长');
		foreach($list as $k=>$val){
			$json_list[0]['data'][$k] = floatval($val['sign_in_times']);
			$json_list[1]['data'][$k] = floatval($val['sign_out_times']);
			$json_list[2]['data'][$k] = floatval($val['online_duration_time'])/720;
			$json_list[3]['data'][$k] = floatval($val['working_duration_time'])/720;
		}

		$json_obj = $this->_get_json_obj();
		$this->Tmpl['json_list'] = $json_obj->encode($json_list);
		//echo $this->Tmpl['json_list'];exit;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 显示考勤详情
     * @author	: pengj
     * @date	: 2012/2/27
     +----------------------------------------------------------
     */
	function showAgentCheckDetail()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'agent_check', $arr_local_priv, '您没有查看座席考勤的权限！' );

		$extension = $_REQUEST['extension'];
		$busy_id = $_REQUEST['id'];
		$type = $_REQUEST['type'];
		$fromdate = $_REQUEST['fromdate'];
		$todate = $_REQUEST['todate'];


		//获取考勤状态
		if ('dnd' == $type) {
			$row_status = $this->getAgentBusy($busy_id);
			$this->Tmpl['row_status'] = $row_status;
		}


		//设置要获取的字段
		$field_list = "login_id, name, device, `group_id`";
		if ('login' == $type) {
			$field_list .= ", sign_in_time, sign_out_time, online_duration, ip";
		}
		else {
			$field_list .= ", say_busy_start_time, say_busy_stop_time, say_busy_duration, say_busy_into_work";
		}

		$condition = "login_id='$extension' and who_insert != 2 ";
		if (!empty($fromdate)) {
			$condition .= "and create_time>='$fromdate' ";
		}
		if (!empty($todate)) {
			$todate .= " 23:59:59";
			$condition .= "and create_time<='$todate' ";
		}

		if ('dnd' == $type) $condition .= "and say_busy_uuid='$busy_id' ";
		else $condition .= "and say_busy_uuid=0 and sign_in_time is not null ";

		//获取总记录数
		$sql = "select count(0) from crm_agent_attendence_record where " . $condition;
		$record_nums = $db->GetOne($sql);

		$pg = loadClass('tool','page',$this);
		$pg->setPageVar('p');
		$pg->setNumPerPage( 20 );

		$currentPage = $_REQUEST['p'];
		unset($_REQUEST['p']);
		unset($_REQUEST['action']);
		unset($_REQUEST['module']);

        $_REQUEST['module'] = 'report';
        $_REQUEST['action'] = 'agentCheckDetail';
		$pg->setVar($_REQUEST);
		$pg->set($record_nums,$currentPage);
		$this->Tmpl['show_pages'] = $pg->output(1);

		//
		//查询,分页,设置查询字段列表
		//
		$sql = str_replace( "count(0)", $field_list, $sql );
		$sql .= " order by id asc";

		if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
			echo $db->ErrorMsg();
		}

		global $cache_department;
		while (!$rs->EOF) {
			$list[] = $rs->fields;
			$rs->MoveNext();
		}
		$list = count($list) < 1 ? array() : $list;
		$this->Tmpl['list'] = $list;

		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 导出考勤记录
     * @author	: pengj
     * @date	: 2012/2/27
     +----------------------------------------------------------
     */
	function showAgentCheckExport()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'agent_check', $arr_local_priv, '您没有查看座席考勤的权限！' );

		//非admin管理员, 获取其所能管理的所有工号列表
		if (AGENT_ATTEND_REPORTING_AUTH == 'enable'){
			$arr_exten = array();
			$list_exten = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);

				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}
		}

		varFilter($_REQUEST);
		extract($_REQUEST);

		$condition = "1";
		if (!empty($fromdate)) {
			$condition .= " and create_time>='$fromdate'";
		}
		if (!empty($todate)) {
			$todate .= " 23:59:59";
			$condition .= " and create_time<='$todate'";
		}

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		if (!empty($depart_id) && empty($extension)) {
			//获取所有的子部门
			$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
			$list_depart .= "$depart_id";	//加上所选部门

			$condition .= " and `group_id` in ($list_depart)";
		}

		//座席工号查找
		if (!empty($extension)) {
			$condition .= " and login_id='$extension' ";
		}

		$condition .= " and who_insert != 2 ";

		if (AGENT_ATTEND_REPORTING_AUTH == 'enable'){
			if (1 != $_SESSION['userinfo']['power']) {
				if (count($arr_deptid) == 1 && 0 == $arr_deptid[0]) {
					$condition .= " and login_id in ($list_exten)";
				}
				else {
					$condition .= " and `group_id` in ($list_deptid)";
				}
			}
		}

		if ('login' == $type) {
			$condition .= " and say_busy_uuid=0 and sign_in_time is not null";
		}
		else if ($type != '') {
			$condition .= " and say_busy_uuid='$type'";
		}

		include_once('include/cache/agent_busy.inc.php'); //加载考勤状态配置缓存

		//导出
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=agent_working.xls ");
		header("Content-Transfer-Encoding: binary ");

		xlsBOF();

		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		//导出全部类型
		if ('' == $type) {
			$xls_columns = array(
				'座席',
				'设备',
				'部门',
				'签入时间',
				'签出时间',
				'工作时长',
				'登录IP',
				'示忙开始时间',
				'示忙结束时间',
				'示忙类型',
				'示忙时长'
			);
		}
		//导出登录
		else if ('login' == $type) {
			$xls_columns = array(
				'座席',
				'设备',
				'部门',
				'签入时间',
				'签出时间',
				'工作时长',
				'登录IP',
			);
		}
		//导出示忙
		else {
			$busy_name = iconv("UTF-8", "GB2312//IGNORE", $cache_agent_busy[$type]['name']);
			$xls_columns = array(
				'座席',
				'设备',
				'部门',
				$busy_name . '开始时间',
				$busy_name . '结束时间',
				$busy_name . '时长'
			);
		}

		//导出excel, 写表头
		$cols = 0;
		foreach ($xls_columns as $value)
		{
			xlsWriteLabel(1, $cols++, $value);
		}

		//导出excel, 写记录
		$sql = "select * from crm_agent_attendence_record where " . $condition . " order by login_id asc, id asc";
		$rs = $db->Execute($sql);

		$arr_exist = array(); //此数组用于导excel重复座席只写一条

		global $cache_department;

		$rows = 2;
		while (!$rs->EOF) {
			$cols = 0;

			$user_name = '';
			//$device = '';
			$dept_name = '';
			$device = $rs->fields['device'];
			if (!in_array($rs->fields['login_id'], $arr_exist)) {
				$user_name = $rs->fields['name'] . '('. $rs->fields['login_id'] .')';
				$user_name = iconv( "UTF-8", "GB2312//IGNORE", $user_name);


				$dept_name = $cache_department[$rs->fields['group_id']]['dept_name'];
				$dept_name = iconv( "UTF-8", "GB2312//IGNORE", $dept_name);

				$arr_exist[] = $rs->fields['login_id'];
			}

			$ip = $rs->fields['ip'];

			xlsWriteLabel($rows, $cols++, $user_name);
			xlsWriteLabel($rows, $cols++, $device);
			xlsWriteLabel($rows, $cols++, $dept_name);

			$sign_in_time = '';  //签入时间
			$sign_out_time = '';  //签出时间
			$online_duration = '';  //工作时长
			if (!empty($rs->fields['sign_in_time'])) $sign_in_time = $rs->fields['sign_in_time'];
			if (!empty($rs->fields['sign_out_time'])) $sign_out_time = $rs->fields['sign_out_time'];
			if ($rs->fields['online_duration'] > 0)	$online_duration = gmstrftime('%H:%M:%S', $rs->fields['online_duration']);

			$say_busy_start_time = ''; //示忙开始时间
			$say_busy_stop_time = ''; //示忙结束时间
			$say_busy_duration = ''; //示忙时长
			$say_busy_type = ''; //示忙类型
			if (!empty($rs->fields['say_busy_start_time'])) $say_busy_start_time = $rs->fields['say_busy_start_time'];
			if (!empty($rs->fields['say_busy_stop_time'])) $say_busy_stop_time = $rs->fields['say_busy_stop_time'];
			if ($rs->fields['say_busy_duration'] > 0)	$say_busy_duration = gmstrftime('%H:%M:%S', $rs->fields['say_busy_duration']);
			if ($rs->fields['say_busy_uuid'] > 0) {
				$say_busy_type = $cache_agent_busy[$rs->fields['say_busy_uuid']]['name'];
				$say_busy_type = iconv( "UTF-8", "GB2312//IGNORE", $say_busy_type);
			}

			//导出全部类型
			if ('' == $type) {
				xlsWriteLabel($rows, $cols++, $sign_in_time);
				xlsWriteLabel($rows, $cols++, $sign_out_time);
				xlsWriteLabel($rows, $cols++, $online_duration);
				xlsWriteLabel($rows, $cols++, $ip);
				xlsWriteLabel($rows, $cols++, $say_busy_start_time);
				xlsWriteLabel($rows, $cols++, $say_busy_stop_time);
				xlsWriteLabel($rows, $cols++, $say_busy_type);
				xlsWriteLabel($rows, $cols++, $say_busy_duration);
			}
			//导出登录
			else if ('login' == $type) {
				xlsWriteLabel($rows, $cols++, $sign_in_time);
				xlsWriteLabel($rows, $cols++, $sign_out_time);
				xlsWriteLabel($rows, $cols++, $online_duration);
				xlsWriteLabel($rows, $cols++, $ip);
			}
			//导出示忙
			else {
				xlsWriteLabel($rows, $cols++, $say_busy_start_time);
				xlsWriteLabel($rows, $cols++, $say_busy_stop_time);
				xlsWriteLabel($rows, $cols++, $say_busy_duration);
			}
			$rows ++;
			$rs->MoveNext();
		}

		xlsEOF();
		exit;

	}

	/**
     +----------------------------------------------------------
     * 班组考勤报表
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function showGroupCheck()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'group_check', $arr_local_priv, '您没有查看班组考勤报表的权限！' );

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time() - 86400 * 1);
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		$list = array();

		if ('' != $do) {
			//非admin管理员, 获取其所能管理的部门列表
			if (AGENT_ATTEND_REPORTING_AUTH == 'enable'){
				$arr_deptid = array();
				$list_deptid = "";
				if (1 != $_SESSION['userinfo']['power']) {
					$arr_deptid = $this->getManageDept();
					if (count($arr_deptid) == 0) $arr_deptid[] = 0;
					$list_deptid = implode(',', $arr_deptid);
				}
			}

			$condition = " 1 ";
			if (!empty($fromdate)) $condition .= " and total_date>='$fromdate'";

			if (!empty($todate)) {
				$todate .= ' 23:59:59';
				$condition .= " and total_date<='$todate'";
			}


			//
			//获取所选部门的子级(一级)部门, 如果无子部门, 则显示所选部门
			//

			//加载部门缓存
			global $cache_department;
			$arr_depart = array();

			if (empty($depart_id)) $depart_id = 0;
			else $depart_id = intval($depart_id);

			//admin超级管理员
			if (1 == $_SESSION['userinfo']['power'] || AGENT_ATTEND_REPORTING_AUTH != 'enable') {
				foreach ($cache_department as $val)
				{
					if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
				}

				//所选部门没有子部门, 只显示所选(条件中的)部门
				if ($depart_id != 0 && count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
			}
			//非admin管理员
			else {
				if (0 == $depart_id) {
					$arr_depart = $this->getManageDirectDept();
				}
				else if (in_array($depart_id, $arr_deptid)) {
					foreach ($cache_department as $val)
					{
						if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
					}

					//所选部门没有子部门, 只显示所选(条件中的)部门
					if (count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
				}
			}

			if (!empty($depart_id)) $arr_depart[] = $depart_id; //此行代码用于除显示某部门的子部门的统计数据外，还需显示某部门"本身"的数据

			//合计数组
			$arr_total = array(
					'sign_in_times'		=> 0,
					'sign_out_times'	=> 0,
					'online_duration'	=> 0,
					'say_busy_duration'	=> 0,
					'say_busy_times'	=> 0,
					'working_duration'	=> 0,
					'man_hour_util_rate' => 0
				);

			$list = array();
			$i = 1;

			$arr = array(); //此数组用于避免最底层的部门重复显示

			//根据子级部门统计
			foreach ($arr_depart as $value)
			{
				if (in_array($value, $arr)) continue;
				$arr[] = $value;

				$list_depart = $this->getNodeChild($dept, $value, 'dept');
				$list_depart .= $value;	//加上所选部门

				$sql = "select SUM(wrap_up_duration) AS wrap_up_duration, sum(sign_in_times) as sign_in_times, ";
				$sql .= "sum(sign_out_times) as sign_out_times, ";
				$sql .= "sum(online_duration) as online_duration, ";
				$sql .= "sum(say_busy_duration) as say_busy_duration, ";
				$sql .= "sum(say_busy_times) as say_busy_times, ";
				$sql .= "sum(working_duration) as working_duration ";

				if ($depart_id != $value) {
					$sql .= "from crm_group_attend_total where " . $condition . " and `group_id` in ($list_depart) ";
				}
				else {
					$sql .= "from crm_group_attend_total where " . $condition . " and `group_id`='$value' ";
				}

				$row = $db->GetRow($sql);

				if ($row) {
					$row['dept_id'] = $value;
					$row['dept_name'] = $cache_department[$value]['dept_name'];

					@$row['man_hour_util_rate'] = round(($row['online_duration'] - $row['say_busy_duration']) / $row['online_duration'], 4); //工时利用率

					//合计数组(累加)
					$arr_total['sign_in_times'] += $row['sign_in_times'];
					$arr_total['sign_out_times'] += $row['sign_out_times'];
					$arr_total['online_duration'] += $row['online_duration'];
					$arr_total['say_busy_duration'] += $row['say_busy_duration'];
					$arr_total['say_busy_times'] += $row['say_busy_times'];
					$arr_total['working_duration'] += $row['working_duration'];
					$arr_total['wrap_up_duration'] += $row['wrap_up_duration'];


					//时间转换
					@$row['working_duration_time']	= $row['working_duration'];
					@$row['working_duration']		= $this->SecondsToTime($row['working_duration']);	//工作态时长
					@$row['online_duration_time']	= $row['online_duration'];
					@$row['online_duration']		= $this->SecondsToTime($row['online_duration']);	//工作时长
					@$row['say_busy_duration_time']	= $row['say_busy_duration'];
					@$row['say_busy_duration']		= $this->SecondsToTime($row['say_busy_duration']);	//示忙时长
					@$row['wrap_up_duration_time']	= $row['wrap_up_duration'];
					@$row['wrap_up_duration']		= $this->SecondsToTime($row['wrap_up_duration']);	//话后时长

					//合计数组(取平均值)
					//if ($i > 2) $i = 2;
					//$arr_total['man_hour_util_rate'] = ($arr_total['man_hour_util_rate'] + $row['man_hour_util_rate']) / $i; //工时利用率

					$i ++;
					$list[] = $row;
				}
			} // end foreach ($arr_depart as $value)

			$list = count($list) < 1 ? array() : $list;

			@$arr_total['man_hour_util_rate'] = round(($arr_total['online_duration'] - $arr_total['say_busy_duration']) / $arr_total['online_duration'], 4); //工时利用率


			//时间转换
			@$arr_total['working_duration_time']	= $arr_total['working_duration'];
			@$arr_total['working_duration']			= $this->SecondsToTime($arr_total['working_duration']);		//工作态时长
			@$arr_total['online_duration_time']		= $arr_total['online_duration'];
			@$arr_total['online_duration']			= $this->SecondsToTime($arr_total['online_duration']);		//工作时长
			@$arr_total['say_busy_duration_time']	= $arr_total['say_busy_duration'];
			@$arr_total['say_busy_duration']		= $this->SecondsToTime($arr_total['say_busy_duration']);	//示忙时长
			@$arr_total['wrap_up_duration_time']	= $arr_total['wrap_up_duration'];
			@$arr_total['wrap_up_duration']			= $this->SecondsToTime($arr_total['wrap_up_duration']);		//话后时长

			//导出
			if ('export' == $do) {
				//导出表头
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=group_check.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);

				$xls_columns = array(
					'部门',
					'登录次数',
					'注销次数',
					'工作时长',
					'工作态时长',
					'示忙次数',
					'示忙时长',
					'工时利用率',
					//'话后处理次数',
					'话后时长',
					//'话后处理均长'
				);

				//导出excel, 写表头
				$cols = 0;
				foreach ($xls_columns as $value) xlsWriteLabel(1, $cols++, $value);

				$rows = 2;
				foreach ($list as $val)
				{
					$cols = 0;

					$dept_name = iconv("UTF-8", "GB2312//IGNORE", $val['dept_name']);
					xlsWriteLabel($rows, $cols++, $dept_name);

					xlsWriteLabel($rows, $cols++, $val['sign_in_times']);
					xlsWriteLabel($rows, $cols++, $val['sign_out_times']);
					xlsWriteLabel($rows, $cols++, $val['online_duration']);
					xlsWriteLabel($rows, $cols++, $val['working_duration']);
					xlsWriteLabel($rows, $cols++, $val['say_busy_times']);
					xlsWriteLabel($rows, $cols++, $val['say_busy_duration']);
					xlsWriteLabel($rows, $cols++, $val['man_hour_util_rate']);
					//xlsWriteNumber($rows, $cols++, $val['wrap_up_times']);
					xlsWriteLabel($rows, $cols++, $val['wrap_up_duration']);
					//xlsWriteNumber($rows, $cols++, round($val['wrap_up_duration'] / $val['wrap_up_times'], 2));

					$rows ++;
				} // end foreach ($list as $val)

				//导出合计数组
				if ($rows>3) {
					$cols = 0;
					xlsWriteLabel($rows, $cols++, '合计：');

					xlsWriteLabel($rows, $cols++, $arr_total['sign_in_times']);
					xlsWriteLabel($rows, $cols++, $arr_total['sign_out_times']);
					xlsWriteLabel($rows, $cols++, $arr_total['online_duration']);
					xlsWriteLabel($rows, $cols++, $arr_total['working_duration']);
					xlsWriteLabel($rows, $cols++, $arr_total['say_busy_times']);
					xlsWriteLabel($rows, $cols++, $arr_total['say_busy_duration']);
					xlsWriteLabel($rows, $cols++, $arr_total['man_hour_util_rate']);
					xlsWriteLabel($rows, $cols++, $arr_total['wrap_up_duration']);
				} // end if ($rows>4)

				xlsEOF();
				exit;
			} // end if ('export' == $do)

			$this->Tmpl['arr_total'] = $arr_total;

		} // end if ('' != $do)

		$this->Tmpl['list'] = $list;

		$dep = array();



		$json_list = array();
		//$json_list[0]['name'] = c('签入次数');
		//$json_list[1]['name'] = c('签出次数');
		$json_list[0]['name'] = c('工作时长');
		$json_list[1]['name'] = c('工作态时长');
		//$json_list[3]['name'] = c('示忙次数');
		$json_list[2]['name'] = c('示忙时长');
		foreach($list as $k=>$res){
			$dep[$k] = $res['dept_name'];
			//$json_list[0]['data'][$k] = floatval($res['sign_in_times']);
			//$json_list[1]['data'][$k] = floatval($res['sign_out_times']);
			$json_list[0]['data'][$k] = floatval(floatval(number_format($res['online_duration_time']/3600,2)));
			$json_list[1]['data'][$k] = floatval(floatval(number_format($res['working_duration_time']/3600,2)));
			$json_list[2]['data'][$k] = floatval(floatval(number_format($res['say_busy_duration_time']/3600,2)));
		}

		//转为JSON数据
		$json_obj = $this->_get_json_obj();

		$this->Tmpl['dep'] = $json_obj->encode($dep);

		$this->Tmpl['json_list'] = $json_obj->encode($json_list);
		//echo $this->Tmpl['json_list'];exit;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 座席话务量报表(座席呼入呼出统计)
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function showAgentTrafficTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'agent_traffic_total', $arr_local_priv, '您没有查看座席话务量报表的权限！' );

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time());
		if (!isset($_REQUEST['s_hour'])) $_REQUEST['s_hour'] = '00';
		if (!isset($_REQUEST['e_hour'])) $_REQUEST['e_hour'] = '23';
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		$list = array();

		//if (!isset($_REQUEST['inbound'])) {
		if ($change_header != '1') {
			$inbound = unserialize($_COOKIE['cfg_traffic_header']['inbound']);

			if (!is_array($inbound)) {
				$inbound = array(
					'inbound_times',				// '呼入次数',
					'inbound_conv_times',			// '通话次数',
					'inbound_circuit_busy',			// '摘机振铃未接听',
					'inbound_conv_rate',			// '通话率',
					'inbound_conv_duration',		// '通话时长',
					'inbound_max_conv_duration',	// '最长通话时长',
					'inbound_call_loss_times',		// '呼损数',
					'inbound_call_loss_rate',		// '呼损率',
					'inbound_avg_conv_duration'		// '通话均长',
				);
			}

			$_REQUEST['inbound'] = $inbound;
		}

		//if (!isset($_REQUEST['outbound'])) {
		if ($change_header != '1') {

			$outbound = unserialize($_COOKIE['cfg_traffic_header']['outbound']);

			if (!is_array($outbound)) {
				$outbound = array(
					'outbound_conv_times',			// '通话次数',
					'outbound_conv_duration'		// '通话时长',
				);
			}

			$_REQUEST['outbound'] = $outbound;
		}

		if (empty($_REQUEST['inbound'])) $_REQUEST['inbound'] = array();
		if (empty($_REQUEST['outbound'])) $_REQUEST['outbound'] = array();

		setcookie("cfg_traffic_header[inbound]", serialize($_REQUEST['inbound']), time() + 86400 * 1); //cookie默认有效时间为1天
		setcookie("cfg_traffic_header[outbound]", serialize($_REQUEST['outbound']), time() + 86400 * 1);
		if ('' != $do) {
			//按统计步长
			if('all' == $step || empty($step)){
				$_REQUEST['step'] = 'all';
			} else if ('day' == $step){
				$_REQUEST['step'] = 'day';
				$kk = " SUBSTRING(total_date, 1, 10) ";
				$kk_record = " from_unixtime(create_time, '%Y-%m-%d') ";
			} else if ('hour' == $step) {
				$kk = " SUBSTRING(total_date, 12, 2) ";
				$kk_record = " from_unixtime(create_time, '%H') ";
			} else if ('week' == $step) {
				$kk = " WEEK(total_date,1) ";
				$kk_record = " WEEK(from_unixtime(create_time, '%Y-%m-%d'),1) ";
			} else if ('month' == $step) {
				$kk = " SUBSTRING(total_date, 1, 7) ";
				$kk_record = " from_unixtime(create_time, '%Y-%m') ";
			}

			//
			//非admin管理员, 获取其所能管理的部门及座席
			//
			$arr_exten = array();
			$list_exten = "";
			$arr_deptid = array();
			$list_deptid = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);

				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}

			//
			//根据起止时间组合查询条件
			//
			$condition = " 1 ";
			$condition_quoanswer = " 1 ";
			$condition_circuit_busy = " 1 ";
			$condition_record = " 1 ";
			if (!empty($fromdate)) {
				if (empty($s_hour)||$s_hour=='00') $s_hour = '00';
				$fromdate .= ' ' . $s_hour . ':00:00';
				$condition .= " and total_date>='$fromdate'";
				$condition_circuit_busy	.= " and time>='$fromdate'";
				$condition_quoanswer	.= " and time>='$fromdate'";
				$fdate = strtotime($fromdate);
				$condition_record .=" and create_time>='$fdate' "; //求录单次数
			}

			if (!empty($todate)) {
				if (empty($e_hour)) $e_hour = '59';
				$todate .= ' ' . $e_hour . ':59:59';
				$condition .= " and total_date<='$todate'";
				$condition_circuit_busy	.= " and time<='$todate'";
				$condition_quoanswer	.= " and time<='$todate'";
				$tdate = strtotime($todate);
				$condition_record	.= " and create_time<='$tdate' "; //求录单次数
			}

			//
			//根据”部门条件“组合条件
			//
			$extenSelect = array();
			if (!empty($depart_id)) {
				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";	//加上所选部门

				$condition .= " and `group_id` in ($list_depart)";
				$condition_quoanswer	.= " and group_id in ($list_depart)";

				//获取所选部门的座席列表
				$rs	= $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while(!$rs->EOF) {
					$extenSelect[] = $rs->fields;
					$rs->MoveNext();
				}

				$this->Tmpl['extenSelect'] = $extenSelect;
			} // end if (!empty($depart_id))

			//
			//非管理员, 根据所能管理的部门或座席组合限定条件
			//
			if (1 != $_SESSION['userinfo']['power']) {
				if (count($arr_deptid) == 1 && 0 == $arr_deptid[0]){
					$condition				.= " and login_id in ($list_exten)";
					$condition_circuit_busy	.= " and agent in ($list_exten)";
					$condition_quoanswer	.= " and agent in ($list_exten)";
				} else {
					if(empty($depart_id)){
						$condition .= " and `group_id` in ($list_deptid)";
					}
					$condition_quoanswer	.= " and group_id in ($list_deptid)";
				}

			}

			//座席工号查找
			if (!empty($extension)){
				$condition				.= " and login_id='$extension' ";
				$condition_circuit_busy	.= " and agent='$extension' ";
				$condition_quoanswer	.= " and agent='$extension' ";
			}
			//表头(字段)配置
			$cfg_column = array(
					'inbound'	=> array(
							'inbound_times'				=> '呼入次数',
							'inbound_conv_times'		=> '通话次数',
							'inbound_circuit_busy'		=> '摘机未接听',
							'inbound_conv_rate'			=> '通话率',
							'inbound_conv_duration'		=> '通话时长',
							'inbound_avg_conv_duration' => '通话均长',
							'inbound_max_conv_duration' => '最长通话时长',
							'inbound_min_conv_duration' => '最短通话时长',
							'inbound_call_loss_times'	=> '呼损数',
							'inbound_call_loss_rate'	=> '呼损率',
							'inbound_call_internal_times' => '分机互打数',
							'inbound_hold_times'		=> '保持次数',
							'inbound_three_call_times'	=> '三方通话次数',
							'inbound_wait_ans_times'	=> '等待应答数',
							'inbound_wait_ans_duration' => '等待应答时长',
							'inbound_avg_wait_ans_duration' => '等待应答均长',
							//'inbound_wrap_up_times'		=> '呼入话后处理次数',
							//'inbound_wrap_up_duration'	=> '话后处理总时长',
							'inbound_wrap_up_duration_avg'	=> '话后均长',
					),
					'outbound'	=> array(
							'outbound_times'			=> '呼出次数',
							'outbound_conv_times'		=> '通话次数',
							'outbound_conv_rate'		=> '通话率',
							'outbound_conv_duration'	=> '通话时长',
							'outbound_avg_conv_duration' => '通话均长',
							'outbound_max_conv_duration' => '最长通话时长',
							'outbound_min_conv_duration' => '最短通话时长',
							'outbound_call_loss_times'	=> '呼损数',
							'outbound_call_loss_rate'	=> '呼损率',
							'outbound_call_internal_times' => '分机互打数',
							//'outbound_hold_times'		=> '保持次数', //暂且屏蔽掉 Edit by code at 2012-3-19
							'outbound_three_call_times'	=> '三方通话次数',
							'outbound_wait_ans_times'	=> '等待应答数',
							'outbound_wait_ans_duration' => '等待应答时长',
							'outbound_avg_wait_ans_duration' => '等待应答均长',
							//'outbound_wrap_up_times'	=> '呼出话后处理次数',
							//'outbound_wrap_up_duration'	=> '呼出话后处理总时长',
							'outbound_wrap_up_duration_avg'	=> '话后均长'
					),
				);
			$this->Tmpl['cfg_column'] = $cfg_column;

			//可排序字段
			$cfg_order_header = array(
					'login_id',
					'inbound_conv_times',
					'outbound_conv_times',
				);
			$this->Tmpl['cfg_order_header'] = $cfg_order_header;
			
			//获取摘机未接听
			$sql_circuit_busy = "select count(agent) as num,agent from ss_cdr_circuit_busy where " . $condition_circuit_busy . " GROUP BY agent ";
			if (!$rs_circuit_busy = $db->Execute($sql_circuit_busy)){
				echo $db->ErrorMsg();
			}
			while (!$rs_circuit_busy->EOF) {
				$circuit_busy_list[$rs_circuit_busy->fields['agent']] = $rs_circuit_busy->fields['num'];
				$rs_circuit_busy->MoveNext();
			}
			//获取摘机未接听结束

			//获取振铃未接听
			$sql_cdr_qnoanswer = "select count(agent) as num,agent from ss_cdr_qnoanswer where " . $condition_quoanswer . " GROUP BY agent ";
			if (!$rs_cdr_qnoanswer = $db->Execute($sql_cdr_qnoanswer)){
				echo $db->ErrorMsg();
			}
			while (!$rs_cdr_qnoanswer->EOF) {
				$cdr_qnoanswer_list[$rs_cdr_qnoanswer->fields['agent']] = $rs_cdr_qnoanswer->fields['num'];
				$rs_cdr_qnoanswer->MoveNext();
			}
			//获取振铃未接听结束

			if ('export' != $do) {
				//
				//获取总记录数(用于分页)
				//
				if($kk){
					$sql = "select count(0) from crm_agent_traffic_total where " . $condition . " group by $kk,login_id";
				}else{
					$sql = "select count(0) from crm_agent_traffic_total where " . $condition . " group by login_id";
				}
				$sql = "select count(0) from ($sql) as n";
				$record_nums = $db->GetOne($sql);

				$pg = loadClass('tool','page',$this);
				$pg->setPageVar('p');
				$pg->setNumPerPage( 20 );

				$currentPage = $_REQUEST['p'];
				unset($_REQUEST['p']);
				unset($_REQUEST['btn_search']);
				unset($_REQUEST['PHPSESSID']);

				$request = $_REQUEST;
				unset($request['inbound']);
				unset($request['outbound']);
				unset($request['cfg_traffic_header']);

				$pg->setVar($request);
				$pg->setVar(array("module"=>"report","action"=>"agentTrafficTotal"));
				$pg->set($record_nums,$currentPage);
				$this->Tmpl['show_pages'] = $pg->output(1);
			}
			else if (1) {
				//导出表头
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=agent_traffic_total.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);

				if ($_REQUEST['step'] == 'day') {
					$step_name = '日期';
				} else if ($_REQUEST['step'] == 'hour') {
					$step_name = '小时';
				} else if ($_REQUEST['step'] == 'week') {
					$step_name = '周';
				} else if ($_REQUEST['step'] == 'month') {
					$step_name = '月份';
				}

				if($step_name){
					$xls_columns = array(
						'step_name' => $step_name,
						'agent_name' => '座席',
						'dept_name'	 => '部门',
						'record_times' => '录单次数',
						'record_rate' => '录单比率',
					);
				}else{
					$xls_columns = array(
						'agent_name' => '座席',
						'dept_name'	 => '部门',
						'record_times' => '录单次数',
						'record_rate' => '录单比率',
					);
				}

				//导出excel, 写表头
				$cols = 2;
				if (count($_REQUEST['inbound']) > 0) {
					xlsWriteLabel(1, $cols, '呼入');
					$cols += count($_REQUEST['inbound']);
				}

				if (count($_REQUEST['outbound']) > 0) {
					xlsWriteLabel(1, $cols, '呼出');
				}

				$cols = 0;
				foreach ($xls_columns as $value) xlsWriteLabel(2, $cols++, $value);

				foreach ($cfg_column['inbound'] as $key => $value) {
					if (!in_array($key, $_REQUEST['inbound'])) continue;

					xlsWriteLabel(2, $cols++, $value);
				}

				foreach ($cfg_column['outbound'] as $key => $value) {
					if (!in_array($key, $_REQUEST['outbound'])) continue;
					xlsWriteLabel(2, $cols++, $value);
				}
			}

			if($kk_record){
				$records = $this->recordCount($condition_record,$kk_record);//录单次数
			}else{
				$records = $this->recordCount($condition_record);//录单次数
			}
			//
			//查询座席呼入呼出表
			//
			if($kk){
				$sql = "select $kk as total_date,login_id, name, group_id, ";
			}else{
				$sql = "select login_id, name, group_id, ";
			}
			$sql .= "sum(inbound_conv_duration) as inbound_conv_duration, ";
			$sql .= "sum(inbound_conv_times) as inbound_conv_times, ";
			$sql .= "sum(inbound_hold_times) as inbound_hold_times, ";
			$sql .= "sum(inbound_three_call_times) as inbound_three_call_times, ";
			$sql .= "sum(inbound_times) as inbound_times, ";
			$sql .= "sum(inbound_trans_inner_times) as inbound_trans_inner_times, ";
			$sql .= "sum(inbound_call_internal_times) as inbound_call_internal_times, ";
			$sql .= "sum(inbound_trans_ivr_times) as inbound_trans_ivr_times, ";
			$sql .= "max(inbound_max_conv_duration) as inbound_max_conv_duration, ";
			$sql .= "min(case when inbound_min_conv_duration>0 then inbound_min_conv_duration end) as inbound_min_conv_duration, ";
			$sql .= "sum(inbound_non_ans_times) as inbound_non_ans_times, ";
			$sql .= "sum(inbound_wait_ans_times) as inbound_wait_ans_times, ";
			$sql .= "sum(inbound_wait_ans_duration) as inbound_wait_ans_duration, ";

			$sql .= "sum(outbound_conv_duration) as outbound_conv_duration, ";
			$sql .= "sum(outbound_conv_times) as outbound_conv_times, ";
			$sql .= "sum(outbound_hold_times) as outbound_hold_times, ";
			$sql .= "sum(outbound_three_call_times) as outbound_three_call_times, ";
			$sql .= "sum(outbound_times) as outbound_times, ";
			$sql .= "sum(outbound_trans_inner_times) as outbound_trans_inner_times, ";
			$sql .= "sum(outbound_call_internal_times) as outbound_call_internal_times, ";
			$sql .= "sum(outbound_trans_ivr_times) as outbound_trans_ivr_times, ";
			$sql .= "max(outbound_max_conv_duration) as outbound_max_conv_duration, ";
			$sql .= "min(case when outbound_min_conv_duration>0 then outbound_min_conv_duration end) as outbound_min_conv_duration, ";
			$sql .= "sum(outbound_non_ans_times) as outbound_non_ans_times, ";
			$sql .= "sum(outbound_wait_ans_times) as outbound_wait_ans_times, ";
			$sql .= "sum(outbound_wait_ans_duration) as outbound_wait_ans_duration, ";

			$sql .= 'SUM(inbound_wrap_up_times) AS inbound_wrap_up_times, ';
			$sql .= 'SUM(inbound_wrap_up_duration) AS inbound_wrap_up_duration, ';
			$sql .= 'SUM(outbound_wrap_up_times) AS outbound_wrap_up_times, ';
			$sql .= 'SUM(outbound_wrap_up_duration) AS outbound_wrap_up_duration ';

			if($kk){
				$sql .= "from crm_agent_traffic_total where ".$condition." group by login_id,$kk";
			}else{
				$sql .= "from crm_agent_traffic_total where ".$condition." group by login_id";
			}

			if ($_REQUEST['orderby']) $sql .= " order by " . $_REQUEST['orderby'] . " " . $_REQUEST['s'];
			else $sql .= " order by login_id desc";
			if ('export' != $do) {
				if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) echo $db->ErrorMsg();
			}
			else {
				if (!$rs = $db->Execute($sql)) echo $db->ErrorMsg();
			}
			global $cache_department;  //加载部门缓存

			//初始化当前页合计数组(累加)
			$arr_total = array(
					'inbound_conv_duration'			=> 0,
					'inbound_conv_times'			=> 0,
					'inbound_circuit_busy'			=> 0,
					'inbound_hold_times'			=> 0,
					'inbound_three_call_times'		=> 0,
					'inbound_times'					=> 0,
					'inbound_trans_inner_times'		=> 0,
					'inbound_call_internal_times'	=> 0,
					'inbound_trans_ivr_times'		=> 0,
					'inbound_non_ans_times'			=> 0,
					'inbound_wait_ans_times'		=> 0,
					'inbound_wait_ans_duration'		=> 0,
					'outbound_conv_duration'		=> 0,
					'outbound_conv_times'			=> 0,
					'outbound_hold_times'			=> 0,
					'outbound_three_call_times'		=> 0,
					'outbound_times'				=> 0,
					'outbound_trans_inner_times'	=> 0,
					'outbound_call_internal_times'	=> 0,
					'outbound_trans_ivr_times'		=> 0,
					'outbound_non_ans_times'		=> 0,
					'outbound_wait_ans_times'		=> 0,
					'outbound_wait_ans_duration'	=> 0,
					'inbound_wrap_up_times'			=> 0,
					'inbound_wrap_up_duration'		=> 0,
					'inbound_wrap_up_duration_avg'	=> 0,
					'outbound_wrap_up_times'		=> 0,
					'outbound_wrap_up_duration'		=> 0,
					'outbound_wrap_up_duration_avg'	=> 0,
					'record_times' 					=> 0, //录单次数
				);

			//其它合计数组(通过数据表公式计算得出)
			$arr_total_other = array(
					'inbound_conv_rate'				=> 0, //通话率
					'inbound_avg_conv_duration'		=> 0, //通话均长
					'inbound_call_loss_times'		=> 0, //呼损数
					'inbound_call_loss_rate'		=> 0, //呼损率
					'inbound_avg_wait_ans_duration'	=> 0, //等待应答均长
					'inbound_max_conv_duration'		=> 0, //最长通话时长
					'inbound_min_conv_duration'		=> 0, //最短通话时长
					'outbound_conv_rate'			=> 0, //通话率
					'outbound_avg_conv_duration'	=> 0, //通话均长
					'outbound_call_loss_times'		=> 0, //呼损数
					'outbound_call_loss_rate'		=> 0, //呼损率
					'outbound_avg_wait_ans_duration' => 0, //等待应答均长
					'outbound_max_conv_duration'	=> 0, //最长通话时长
					'outbound_min_conv_duration'	=> 0, //最短通话时长
					//'record_rate'					=> 0, //录单比率
				);

			$i = 1;
			$rows = 3;
			$arrExten = $db->GetAll("select extension,user_name from org_user");
			$arrDept = $db->GetAll("select dept_id,dept_name from org_department");
			while (!$rs->EOF) {

				//$u = $this->getUserByExten($rs->fields['login_id']);
				foreach($arrExten as $k => $v){
					if($rs->fields['login_id'] == $v['extension']){
						$rs->fields['name'] = $v['user_name'];
					}
				}
				

				//振铃未接听
				if($cdr_qnoanswer_list[$rs->fields['login_id']] == ''){
					@$rs->fields['inbound_conv_qnoanswer'] = '0';
				} else {
					@$rs->fields['inbound_conv_qnoanswer'] = $cdr_qnoanswer_list[$rs->fields['login_id']];
				}
				
				//摘机振铃未接听
				if($circuit_busy_list[$rs->fields['login_id']] == ''){
					@$rs->fields['inbound_circuit_busy'] = '0';
				} else {
					@$rs->fields['inbound_circuit_busy'] = $circuit_busy_list[$rs->fields['login_id']];
				}

				@$rs->fields['inbound_conv_rate'] = round($rs->fields['inbound_conv_times'] / $rs->fields['inbound_times'], 4); //通话率
				@$rs->fields['inbound_avg_conv_duration'] = round($rs->fields['inbound_conv_duration'] / $rs->fields['inbound_conv_times'], 2); //平均通话时长
				$rs->fields['inbound_call_loss_times'] = $rs->fields['inbound_times'] - $rs->fields['inbound_conv_times']; //呼损数
				@$rs->fields['inbound_call_loss_rate'] = round($rs->fields['inbound_call_loss_times'] / $rs->fields['inbound_times'], 4); //呼损率
				@$rs->fields['inbound_avg_wait_ans_duration'] = round($rs->fields['inbound_wait_ans_duration'] / $rs->fields['inbound_wait_ans_times'], 2); //等待应答均长

				@$rs->fields['outbound_conv_rate'] = round($rs->fields['outbound_conv_times'] / $rs->fields['outbound_times'], 4); //通话率
				@$rs->fields['outbound_avg_conv_duration'] = round($rs->fields['outbound_conv_duration'] / $rs->fields['outbound_conv_times'], 2); //平均通话时长
				$rs->fields['outbound_call_loss_times'] = $rs->fields['outbound_times'] - $rs->fields['outbound_conv_times']; //呼损数
				@$rs->fields['outbound_call_loss_rate'] = round($rs->fields['outbound_call_loss_times'] / $rs->fields['outbound_times'], 4); //呼损率
				@$rs->fields['outbound_avg_wait_ans_duration'] = round($rs->fields['outbound_wait_ans_duration'] / $rs->fields['outbound_wait_ans_times'], 2); //等待应答均长
				@$rs->fields['inbound_wrap_up_duration_avg'] = round($rs->fields['inbound_wrap_up_duration'] / $rs->fields['inbound_wrap_up_times'], 2); //呼入话后处理平均时长
				@$rs->fields['outbound_wrap_up_duration_avg'] = round($rs->fields['outbound_wrap_up_duration'] / $rs->fields['outbound_wrap_up_times'], 2); //呼出话后处理平均时长

				//获取部门名称
				$groupSql = "select dept_id from org_user where extension = ".$rs->fields['login_id'];
				$group_id = $db->GetOne($groupSql);
				
				foreach($arrDept as $k => $v){
					if($group_id == $v['dept_id']){
						$rs->fields['dept_name'] = $v['dept_name'];
					}
				}
				//$rs->fields['dept_name'] = $arrDept[$group_id]['dept_name'];
				//$rs->fields['dept_name'] = $cache_department[$rs->fields['group_id']]['dept_name'];
				//$rs->fields['dept_name'] = $cache_department[$group_id]['dept_name'];

				if($kk_record){
					@$rs->fields['record_times'] = $records[$rs->fields['login_id']][$rs->fields['total_date']] ? $records[$rs->fields['login_id']][$rs->fields['total_date']] : 0; //录单次数
				}else{
					@$rs->fields['record_times'] = $records[$rs->fields['login_id']] ? $records[$rs->fields['login_id']] : 0; //录单次数
				}

				@$rs->fields['record_rate'] = round($rs->fields['record_times']/($rs->fields['inbound_conv_times']+$rs->fields['outbound_conv_times']),4);//录单比率（录单次数除以通话次数（呼入+呼出））


				//合计数组累加
				foreach ($arr_total as $key => $value) $arr_total[$key] += $rs->fields[$key];
				$arr_total_other['inbound_call_loss_times'] += $rs->fields['inbound_call_loss_times']; //呼损数
				$arr_total_other['outbound_call_loss_times'] += $rs->fields['outbound_call_loss_times']; //呼损数

				//合计数组(取最大值)
				$arr_total_other['inbound_max_conv_duration'] = $arr_total_other['inbound_max_conv_duration'] < $rs->fields['inbound_max_conv_duration'] ? $rs->fields['inbound_max_conv_duration'] : $arr_total_other['inbound_max_conv_duration'];
				$arr_total_other['outbound_max_conv_duration'] = $arr_total_other['outbound_max_conv_duration'] < $rs->fields['outbound_max_conv_duration'] ? $rs->fields['outbound_max_conv_duration'] : $arr_total_other['outbound_max_conv_duration'];

				//合计数组(取最小值)
				if ($rs->fields['inbound_min_conv_duration']>0) {
					if (0 === $arr_total_other['inbound_min_conv_duration'])
						$arr_total_other['inbound_min_conv_duration'] = $rs->fields['inbound_min_conv_duration'];
					else
						$arr_total_other['inbound_min_conv_duration'] = $arr_total_other['inbound_min_conv_duration'] > $rs->fields['inbound_min_conv_duration'] ? $rs->fields['inbound_min_conv_duration'] : $arr_total_other['inbound_min_conv_duration'];
				}

				if ($rs->fields['outbound_min_conv_duration'] > 0) {
					if (0 === $arr_total_other['outbound_min_conv_duration'])
						$arr_total_other['outbound_min_conv_duration'] = $rs->fields['outbound_min_conv_duration'];
					else
						$arr_total_other['outbound_min_conv_duration'] = $arr_total_other['outbound_min_conv_duration'] > $rs->fields['outbound_min_conv_duration'] ? $rs->fields['outbound_min_conv_duration'] : $arr_total_other['outbound_min_conv_duration'];
				}
				
				//时间转换
				@$rs->fields['inbound_conv_duration']	= $this->SecondsToTime($rs->fields['inbound_conv_duration']); //通话时长
				@$rs->fields['inbound_max_conv_duration'] = $this->SecondsToTime($rs->fields['inbound_max_conv_duration']); //最长通话时长
				@$rs->fields['inbound_wait_ans_duration'] = $this->SecondsToTime($rs->fields['inbound_wait_ans_duration']); //等待应答时长
				
				@$rs->fields['outbound_conv_duration'] = $this->SecondsToTime($rs->fields['outbound_conv_duration']); //通话时长
				@$rs->fields['outbound_max_conv_duration'] = $this->SecondsToTime($rs->fields['outbound_max_conv_duration']); //最长通话时长
				@$rs->fields['outbound_wait_ans_duration'] = $this->SecondsToTime($rs->fields['outbound_wait_ans_duration']); //等待应答时长


				//导出
				if ('export' == $do) {
					$cols = 0;

					$user_name = $rs->fields['name'] . '(' . $rs->fields['login_id'] . ')';
					$user_name = iconv("UTF-8", "GB2312//IGNORE", $user_name);
					$dept_name = iconv("UTF-8", "GB2312//IGNORE", $rs->fields['dept_name']);

					if($step_name){
						xlsWriteLabel($rows, $cols++, $rs->fields['total_date']);
					}

					xlsWriteLabel($rows, $cols++, $user_name);
					xlsWriteLabel($rows, $cols++, $dept_name);
					xlsWriteLabel($rows, $cols++, $rs->fields['record_times']);
					xlsWriteLabel($rows, $cols++, $rs->fields['record_rate']);

					foreach ($cfg_column['inbound'] as $key => $value) {
						if (!in_array($key, $_REQUEST['inbound'])) continue;

						xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
					}

					foreach ($cfg_column['outbound'] as $key => $value) {
						if (!in_array($key, $_REQUEST['outbound'])) continue;

						xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
					}
				} // end if ('export' == $do)
				else {
					$list[] = $rs->fields;
				}

				$i ++;
				$rows ++;
				$rs->MoveNext();
			} // end while (!$rs->EOF)

			$list = count($list) < 1 ? array() : $list;
			//var_dump($arrData);
			$arr_total = array_merge($arr_total, $arr_total_other);


			@$arr_total['inbound_conv_rate'] = round($arr_total['inbound_conv_times'] / $arr_total['inbound_times'], 4); //通话率
			@$arr_total['inbound_avg_conv_duration'] = round($arr_total['inbound_conv_duration'] / $arr_total['inbound_conv_times'], 2); //平均通话时长
			@$arr_total['inbound_call_loss_rate'] = round($arr_total['inbound_call_loss_times'] / $arr_total['inbound_times'], 4); //呼损率
			@$arr_total['inbound_avg_wait_ans_duration'] = round($arr_total['inbound_wait_ans_duration'] / $arr_total['inbound_wait_ans_times'], 2); //等待应答均长

			@$arr_total['outbound_conv_rate'] = round($arr_total['outbound_conv_times'] / $arr_total['outbound_times'], 4); //通话率
			@$arr_total['outbound_avg_conv_duration'] = round($arr_total['outbound_conv_duration'] / $arr_total['outbound_conv_times'], 2); //平均通话时长
			@$arr_total['outbound_call_loss_rate'] = round($arr_total['outbound_call_loss_times'] / $arr_total['outbound_times'], 4); //呼损率
			@$arr_total['outbound_avg_wait_ans_duration'] = round($arr_total['outbound_wait_ans_duration'] / $arr_total['outbound_wait_ans_times'], 2); //等待应答均长

			//导出合计数组
            @$arr_total['inbound_wrap_up_duration_avg'] = round($arr_total['inbound_wrap_up_duration'] / $arr_total['inbound_wrap_up_times'], 2);
            @$arr_total['outbound_wrap_up_duration_avg'] = round($arr_total['outbound_wrap_up_duration'] / $arr_total['outbound_wrap_up_times'], 2);

			//时间转换
			@$arr_total['inbound_conv_duration'] = $this->SecondsToTime($arr_total['inbound_conv_duration']); //通话时长
			@$arr_total['inbound_max_conv_duration'] = $this->SecondsToTime($arr_total['inbound_max_conv_duration']); //最长通话时长
			@$arr_total['inbound_wait_ans_duration'] = $this->SecondsToTime($arr_total['inbound_wait_ans_duration']); //等待应答时长
			
			@$arr_total['outbound_conv_duration'] = $this->SecondsToTime($arr_total['outbound_conv_duration']); ;//通话时长
			@$arr_total['outbound_max_conv_duration'] = $this->SecondsToTime($arr_total['outbound_max_conv_duration']); //最长通话时长
			@$arr_total['outbound_wait_ans_duration'] = $this->SecondsToTime($arr_total['outbound_wait_ans_duration']); //等待应答时长
			@$arr_total['record_rate'] = round($arr_total['record_times']/($arr_total['inbound_conv_times']+$arr_total['outbound_conv_times']),4);//录单比率

			if ($rows>4 && 'export' == $do) {
				if($step_name){
					$c1 = 2;
					$c2 = 3;
				}else{
					$c1 = 1;
					$c2 = 2;
				}
				xlsWriteLabel($rows, $c1, '合计：');
				$cols = $c2;

				xlsWriteLabel($rows, $cols++, $arr_total['record_times']);
				xlsWriteLabel($rows, $cols++, $arr_total['record_rate']);
				foreach ($cfg_column['inbound'] as $key => $value) {
					if (!in_array($key, $_REQUEST['inbound'])) continue;

					xlsWriteLabel($rows, $cols++, $arr_total[$key]);
				}

				foreach ($cfg_column['outbound'] as $key => $value) {
					if (!in_array($key, $_REQUEST['outbound'])) continue;

					xlsWriteLabel($rows, $cols++, $arr_total[$key]);
				}
			} // end if ($rows>4 && 'export' == $do) 导出

			if ('export' == $do) {
				xlsEOF();
				exit;
			}

			$this->Tmpl['arr_total'] = $arr_total;
		}
		$this->Tmpl['list'] = $list;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 班组话务量报表(班组呼入呼出)
     * @author	: pengj
     * @date	: 2012/2/29
     +----------------------------------------------------------
     */
	function showGroupTrafficTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'group_traffic_total', $arr_local_priv, '您没有查看班组话务量报表的权限！' );

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time());
		if (!isset($_REQUEST['s_hour'])) $_REQUEST['s_hour'] = '00';
		if (!isset($_REQUEST['e_hour'])) $_REQUEST['e_hour'] = '23';
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';
		if ("" == $_REQUEST['depart_type']) {
			$_REQUEST['depart_type'] = 'now';
		}
		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		if (isset($_REQUEST['inbound']) || isset($_REQUEST['outbound']))
		{
			$_SESSION['column_select'] = array_merge($_REQUEST['inbound'], $_REQUEST['outbound']);
			$_SESSION['column_select_count'] = count($_REQUEST['inbound']);
		}
		
		// if ('del' == $depart_type) {
			// $depart_id = $depart_id_del;
		// }

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );
		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;
		
		//已删除部门选择 start
		$sql = "SELECT * FROM org_department_del";
		$arrDeptDel = $db->GetAll( $sql );
		$deptOptionsDel = $this->getCateOption( $arrDeptDel, 'dept', $depart_id_del);
		$this->Tmpl['deptSelectDel'] = $deptOptionsDel;
		//end
	
		$list = array();

		//按统计步长
		$step = $_REQUEST['step'];
		if('all'==$step || empty($step)){
			$_REQUEST['step'] = 'all';
		} else if ('day' == $step) {
			$kk = " SUBSTRING(total_date, 1, 10) ";
			$kk_record = " from_unixtime(create_time, '%Y-%m-%d') ";
		} else if ('hour' == $step) {
			$kk = " SUBSTRING(total_date, 12, 2) ";
			$kk_record = " from_unixtime(create_time, '%H') ";
		} else if ('week' == $step) {
			$kk = " WEEK(total_date,1) ";
			$kk_record = " WEEK(from_unixtime(create_time, '%Y-%m-%d'),1) ";
		} else if ('month' == $step) {
			$kk = " SUBSTRING(total_date, 1, 7) ";
			$kk_record = " from_unixtime(create_time, '%Y-%m') ";
		}

		if ('' != $do)
		{
			//非admin管理员, 获取其所能管理的部门列表
			$arr_deptid = array();
			$list_deptid = "";
			if (1 != $_SESSION['userinfo']['power']) {
				
				if ('del' == $depart_type) {
					$arr_deptid = $this->getManageDeptDel();
					//$arr_deptid = $this->getManageDept();
				} else {
					$arr_deptid = $this->getManageDept();
				}
				
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);
			}
			//echo "<pre>";
			//print_r($arrDeptDel);
			//print_r($arr_deptid);
			$condition = " 1 ";
			$condition_record = "1";
			if (!empty($fromdate)) {
				if (empty($s_hour)) $s_hour = '00';
				$fromdate .= ' ' . $s_hour . ':00:00';

				$condition .= " and total_date>='$fromdate'";
				$fdate = strtotime($fromdate);
				$condition_record .=" and create_time>='$fdate' "; //求录单次数
			}
			if (!empty($todate)) {
				if (empty($e_hour)) $e_hour = '59';
				$todate .= ' ' . $e_hour . ':59:59';
				$condition .= " and total_date<='$todate'";
				$tdate = strtotime($todate);
				$condition_record	.= " and create_time<='$tdate' "; //求录单次数
			}

			//获取所选部门的子级(一级)部门, 如果无子部门, 则显示所选部门
			//加载部门缓存
			if ('del' == $depart_type) {	//已删除部门
				foreach ($arrDeptDel as $v) {
					$arrDeptDelSearch[$v['dept_id']] =$v;
				}
				$arr_depart = array();
				if (empty($depart_id_del)){
					$depart_id_del = 0;
				} else {
					$depart_id_del = intval($depart_id_del);
				}
				//admin超级管理员
				if (1 == $_SESSION['userinfo']['power']) {
					foreach ($arrDeptDelSearch as $val) {
						if ($val['dept_parent'] == $depart_id_del) $arr_depart[] = $val['dept_id'];
					}
					//所选部门没有子部门, 只显示所选(条件中的)部门
					if ($depart_id_del != 0 && count($arr_depart) == 0) $arr_depart[] = $arrDeptDelSearch[$depart_id_del]['dept_id'];
					//print_r($depart_id_del);
				} else {	//非admin管理员
					if (0 == $depart_id_del) {
						$arr_depart = $this->getManageDirectDeptDel();
					} else if (in_array($depart_id_del, $arr_deptid)) {
						foreach ($arrDeptDelSearch as $val) {
							if ($val['dept_parent'] == $depart_id_del) $arr_depart[] = $val['dept_id'];
						}
						//所选部门没有子部门, 只显示所选(条件中的)部门
						if (count($arr_depart) == 0) $arr_depart[] = $arrDeptDelSearch[$depart_id_del]['dept_id'];
					}
				}
				if (!empty($depart_id_del)) $arr_depart[] = $depart_id_del; 
			} else {	//现有部门
				global $cache_department;
				$arr_depart = array();
				if (empty($depart_id)){
					$depart_id = 0;
				} else {
					$depart_id = intval($depart_id);
				}
				//admin超级管理员
				if (1 == $_SESSION['userinfo']['power']) {
					foreach ($cache_department as $val) {
						if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
					}
					//所选部门没有子部门, 只显示所选(条件中的)部门
					if ($depart_id != 0 && count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
					//print_r($depart_id);
				} else {	//非admin管理员
					if (0 == $depart_id) {
						$arr_depart = $this->getManageDirectDept();
					} else if (in_array($depart_id, $arr_deptid)) {
						foreach ($cache_department as $val) {
							if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
						}
						//所选部门没有子部门, 只显示所选(条件中的)部门
						if (count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
					}
				}
				if (!empty($depart_id)) $arr_depart[] = $depart_id; 
			}
			
			// $arr_depart = array();

			// if (empty($depart_id))
				// $depart_id = 0;
			// else
				// $depart_id = intval($depart_id);

			// //admin超级管理员
			// if (1 == $_SESSION['userinfo']['power']) {
				// foreach ($cache_department as $val)
				// {
					// if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
				// }

				// //所选部门没有子部门, 只显示所选(条件中的)部门
				// if ($depart_id != 0 && count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
				// print_r($depart_id);
			// }
			// //非admin管理员
			// else
			// {
				// if (0 == $depart_id)
				// {
					// $arr_depart = $this->getManageDirectDept();
				// }
				// else if (in_array($depart_id, $arr_deptid))
				// {
					// foreach ($cache_department as $val)
					// {
						// if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
					// }

					// //所选部门没有子部门, 只显示所选(条件中的)部门
					// if (count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
				// }
			// }

			// if (!empty($depart_id)) $arr_depart[] = $depart_id; //此行代码用于除显示某部门的子部门的统计数据外，还需显示某部门"本身"的数据

			//初始化当前页合计数组(累加)
			$arr_total = array(
					'inbound_conv_duration'			=> 0,
					'inbound_conv_times'			=> 0,
					'inbound_hold_times'			=> 0,
					'inbound_three_call_times'		=> 0,
					'inbound_times'					=> 0,
					'inbound_trans_inner_times'		=> 0,
					'inbound_call_internal_times'	=> 0,
					'inbound_trans_ivr_times'		=> 0,
					'inbound_non_ans_times'			=> 0,
					'inbound_wait_ans_times'		=> 0,
					'inbound_wait_ans_duration'		=> 0,
					'outbound_conv_duration'		=> 0,
					'outbound_conv_times'			=> 0,
					'outbound_hold_times'			=> 0,
					'outbound_three_call_times'		=> 0,
					'outbound_times'				=> 0,
					'outbound_trans_inner_times'	=> 0,
					'outbound_call_internal_times'	=> 0,
					'outbound_trans_ivr_times'		=> 0,
					'outbound_non_ans_times'		=> 0,
					'outbound_wait_ans_times'		=> 0,
					'outbound_wait_ans_duration'	=> 0,
					'inbound_wrap_up_times'			=> 0,
					'inbound_wrap_up_duration'		=> 0,
					'inbound_wrap_up_duration_avg'	=> 0,
					'outbound_wrap_up_times'		=> 0,
					'outbound_wrap_up_duration'		=> 0,
					'outbound_wrap_up_duration_avg'	=> 0,
					'record_times'					=> 0,
				);

			//其它合计数组(通过数据表公式计算得出)
			$arr_total_other = array(
					'inbound_conv_rate'				=> 0, //通话率
					'inbound_avg_conv_duration'		=> 0, //通话均长
					'inbound_call_loss_times'		=> 0, //呼损数
					'inbound_call_loss_rate'		=> 0, //呼损率
					'inbound_avg_wait_ans_duration'	=> 0, //等待应答均长
					'outbound_conv_rate'			=> 0, //通话率
					'outbound_avg_conv_duration'	=> 0, //通话均长
					'outbound_call_loss_times'		=> 0, //呼损数
					'outbound_call_loss_rate'		=> 0, //呼损率
					'outbound_avg_wait_ans_duration' => 0, //等待应答均长
				);

			$list = array();
			$i = 1;
			$arr = array(); //此数组用于避免最底层的部门重复显示
	/*		if('all'==$step || empty($step)){*/
				//根据子级部门统计
				foreach ($arr_depart as $value)
				{
					if (in_array($value, $arr)) continue;
					$arr[] = $value;

					$list_depart = $this->getNodeChild($dept, $value, 'dept');
					$list_depart .= $value; //加上所选部门
					if('all' == $step || empty($step)){
						$sql = "select sum(inbound_conv_duration) as inbound_conv_duration, ";
					}else{
						$sql = "select $kk as total_date,group_id,sum(inbound_conv_duration) as inbound_conv_duration, ";
					}

					$sql .= "sum(inbound_conv_times) as inbound_conv_times, ";
					$sql .= "sum(inbound_hold_times) as inbound_hold_times, ";
					$sql .= "sum(inbound_three_call_times) as inbound_three_call_times, ";
					$sql .= "sum(inbound_times) as inbound_times, ";
					$sql .= "sum(inbound_trans_inner_times) as inbound_trans_inner_times, ";
					$sql .= "sum(inbound_call_internal_times) as inbound_call_internal_times, ";
					$sql .= "sum(inbound_trans_ivr_times) as inbound_trans_ivr_times, ";
					$sql .= "max(inbound_max_conv_duration) as inbound_max_conv_duration, ";
					$sql .= "min(case when inbound_min_conv_duration>0 then inbound_min_conv_duration end) as inbound_min_conv_duration, ";
					$sql .= "sum(inbound_non_ans_times) as inbound_non_ans_times, ";
					$sql .= "sum(inbound_wait_ans_times) as inbound_wait_ans_times, ";
					$sql .= "sum(inbound_wait_ans_duration) as inbound_wait_ans_duration, ";

					$sql .= "sum(outbound_conv_duration) as outbound_conv_duration, ";
					$sql .= "sum(outbound_conv_times) as outbound_conv_times, ";
					$sql .= "sum(outbound_hold_times) as outbound_hold_times, ";
					$sql .= "sum(outbound_three_call_times) as outbound_three_call_times, ";
					$sql .= "sum(outbound_times) as outbound_times, ";
					$sql .= "sum(outbound_trans_inner_times) as outbound_trans_inner_times, ";
					$sql .= "sum(outbound_call_internal_times) as outbound_call_internal_times, ";
					$sql .= "sum(outbound_trans_ivr_times) as outbound_trans_ivr_times, ";
					$sql .= "max(outbound_max_conv_duration) as outbound_max_conv_duration, ";
					$sql .= "min(case when outbound_min_conv_duration>0 then outbound_min_conv_duration end) as outbound_min_conv_duration, ";
					$sql .= "sum(outbound_non_ans_times) as outbound_non_ans_times, ";
					$sql .= "sum(outbound_wait_ans_times) as outbound_wait_ans_times, ";
					$sql .= "sum(outbound_wait_ans_duration) as outbound_wait_ans_duration, ";

					$sql .= 'SUM(inbound_wrap_up_times) AS inbound_wrap_up_times, ';
					$sql .= 'SUM(inbound_wrap_up_duration) AS inbound_wrap_up_duration, ';
					$sql .= 'SUM(outbound_wrap_up_times) AS outbound_wrap_up_times, ';
					$sql .= 'SUM(outbound_wrap_up_duration) AS outbound_wrap_up_duration ';

					if('all' == $step || empty($step)){
						if ($depart_id != $value){
							$sql .= "from crm_group_traffic_total where " . $condition . " and `group_id` in ($list_depart) ";

							//根据部门获取坐席
							$sql_extension = "select extension from org_user where dept_id in ({$list_depart})";
						} else{
							$sql .= "from crm_group_traffic_total where " . $condition . " and `group_id`='$value' ";

							//根据部门获取坐席
							$sql_extension = "select extension from org_user where dept_id = '$value'";
						}
						$extens = $db->GetAll($sql_extension);
						$arrExtens = array();
						foreach($extens as $v){
							if(!empty($v['extension'])){
								$arrExtens[] = $v['extension'];
							}
						}
						$strExtens = implode(',',$arrExtens);
						$row = $db->GetRow($sql);
					}else{
						if ($depart_id != $value){
							$sql .= "from crm_group_traffic_total where " . $condition . " and `group_id` in ($list_depart) group by $kk";
							//根据部门获取坐席
							$sql_extension = "select extension from org_user where dept_id in ({$list_depart})";
						}else{
							$sql .= "from crm_group_traffic_total where " . $condition . " and `group_id`='$value' group by $kk";
							//根据部门获取坐席
							$sql_extension = "select extension from org_user where dept_id = '$value'";
						}
						$extens = $db->GetAll($sql_extension);
						$arrExtens = array();
						foreach($extens as $v){
							if(!empty($v['extension'])){
								$arrExtens[] = $v['extension'];
							}
						}
						$strExtens = implode(',',$arrExtens);
						$row = $db->GetAll($sql);
					}
					if('all' == $step || empty($step)){
						if ($row) {
							$row['dept_id'] = $value;
							if ('del' == $depart_type) {
								$row['dept_name'] = $arrDeptDelSearch[$value]['dept_name'];
							} else {
								$row['dept_name'] = $cache_department[$value]['dept_name'];
							}


							@$row['inbound_conv_rate'] = round($row['inbound_conv_times'] / $row['inbound_times'], 4); //通话率
							@$row['inbound_avg_conv_duration'] = round($row['inbound_conv_duration'] / $row['inbound_conv_times'], 2); //平均通话时长
							@$row['inbound_call_loss_times'] = $row['inbound_times'] - $row['inbound_conv_times']; //呼损数
							@$row['inbound_call_loss_rate'] = round($row['inbound_call_loss_times'] / $row['inbound_times'], 4); //呼损率
							@$row['inbound_avg_wait_ans_duration'] = round($row['inbound_wait_ans_duration'] / $row['inbound_wait_ans_times'], 2); //等待应答均长

							@$row['outbound_conv_rate'] = round($row['outbound_conv_times'] / $row['outbound_times'], 4); //通话率
							@$row['outbound_avg_conv_duration'] = round($row['outbound_conv_duration'] / $row['outbound_conv_times'], 2); //平均通话时长
							@$row['outbound_call_loss_times'] = $row['outbound_times'] - $row['outbound_conv_times']; //呼损数
							@$row['outbound_call_loss_rate'] = round($row['outbound_call_loss_times'] / $row['outbound_times'], 4); //呼损率
							@$row['outbound_avg_wait_ans_duration'] = round($row['outbound_wait_ans_duration'] / $row['outbound_wait_ans_times'], 2); //等待应答均长
							@$row['inbound_wrap_up_duration_avg'] = round($row['inbound_wrap_up_duration'] / $row['inbound_wrap_up_times'], 2); //呼入话后处理均长
							@$row['outbound_wrap_up_duration_avg'] = round($row['outbound_wrap_up_duration'] / $row['outbound_wrap_up_times'], 2); //呼出话后入理均长
							@$row['record_times'] = $this->groupRecordCount($strExtens,$condition_record);
							@$row['record_rate'] = round($row['record_times']/($row['inbound_conv_times']+$row['outbound_conv_times']),4);

							//合计数组累加
							foreach ($arr_total as $key => $value)
							{
								$rq = empty($row[$key]) ? 0 : $row[$key];
								$arr_total[$key] += $rq;
							}
							//print_r($arr_total);
							$arr_total_other['inbound_call_loss_times'] += $row['inbound_call_loss_times']; //呼损数
							$arr_total_other['outbound_call_loss_times'] += $row['outbound_call_loss_times']; //呼损数

							//合计数组(取最大值)
							$arr_total_other['inbound_max_conv_duration'] = $arr_total_other['inbound_max_conv_duration'] < $row['inbound_max_conv_duration'] ? $row['inbound_max_conv_duration'] : $arr_total_other['inbound_max_conv_duration'];

							$arr_total_other['outbound_max_conv_duration'] = $arr_total_other['outbound_max_conv_duration'] < $row['outbound_max_conv_duration'] ? $row['outbound_max_conv_duration'] : $arr_total_other['outbound_max_conv_duration'];

							//合计数组(取最小值)
							if ($row['inbound_min_conv_duration']>0) {
								if (0 == $arr_total_other['inbound_min_conv_duration'])
									$arr_total_other['inbound_min_conv_duration'] = $row['inbound_min_conv_duration'];
								else
									$arr_total_other['inbound_min_conv_duration'] = $arr_total_other['inbound_min_conv_duration'] > $row['inbound_min_conv_duration'] ? $row['inbound_min_conv_duration'] : $arr_total_other['inbound_min_conv_duration'];
							}

							if ($row['outbound_min_conv_duration'] > 0) {
								if (0 == $arr_total_other['outbound_min_conv_duration'])
									$arr_total_other['outbound_min_conv_duration'] = $row['outbound_min_conv_duration'];
								else
									$arr_total_other['outbound_min_conv_duration'] = $arr_total_other['outbound_min_conv_duration'] > $row['outbound_min_conv_duration'] ? $row['outbound_min_conv_duration'] : $arr_total_other['outbound_min_conv_duration'];
							}

							//时间转换
							@$row['inbound_conv_duration'] = $this->SecondsToTime($row['inbound_conv_duration']);
							@$row['inbound_max_conv_duration'] = $this->SecondsToTime($row['inbound_max_conv_duration']);

							@$row['outbound_conv_duration'] = $this->SecondsToTime($row['outbound_conv_duration']);
							@$row['outbound_max_conv_duration'] = $this->SecondsToTime($row['outbound_max_conv_duration']);

							$list[] = $row;
							$i ++;
						}
					}else{ //按统计步长
						$dept_id = $value;
						if ($row) {
							foreach($row as $val){
								/*$arr_step_extens = array();
								$rs_extens = $db->Execute("select extension from org_user where dept_id = '$val[group_id]'");
								while(!$rs_extens->EOF) {
									$arr_step_extens[] = $rs_extens->fields['extension'];
									$rs_extens->MoveNext();
								}
								$step_extens = implode(',',$arr_step_extens);*/
								/*$arr_step_extens = array();
								$str_dept = $this->getNodeChild($dept, $dept_id, 'dept');
								$str_dept .= $dept_id; //加上所选部门
								$str_extens_sql = "select extension from org_user where dept_id in ({$str_dept})";
								$rs_extens = $db->Execute($str_extens_sql);
								while(!$rs_extens->EOF) {
									if(!empty($rs_extens->fields['extension'])){
										$arr_step_extens[] = $rs_extens->fields['extension'];
									}
									$rs_extens->MoveNext();
								}
								$step_extens = implode(',',$arr_step_extens);*/
								//echo $step_extens.'--'.$str_dept.'<br/>';
								$arrRecord = $this->groupRecordCount($strExtens,$condition_record,$kk_record);
							/*	if ('del' == $depart_type) {
									$val['dept_name'] = $arrDeptDelSearch[$value]['dept_name'];
								} else {
									$val['dept_name'] = $cache_department[$value]['dept_name'];
								}*/

								$val['dept_id'] = $dept_id;
								$val['dept_name'] = $cache_department[$dept_id]['dept_name'];
								$val['record_times'] = $arrRecord[$val['total_date']] ? $arrRecord[$val['total_date']] : 0;//录单次数
								$val['record_rate'] = round($val['record_times']/($val['inbound_conv_times']+$val['outbound_conv_times']),4);//录单比率

								@$val['inbound_conv_rate'] = round($val['inbound_conv_times'] / $val['inbound_times'], 4); //通话率
								@$val['inbound_avg_conv_duration'] = round($val['inbound_conv_duration'] / $val['inbound_conv_times'], 2); //平均通话时长
								@$val['inbound_call_loss_times'] = $val['inbound_times'] - $val['inbound_conv_times']; //呼损数
								@$val['inbound_call_loss_rate'] = round($val['inbound_call_loss_times'] / $val['inbound_times'], 4); //呼损率
								@$val['inbound_avg_wait_ans_duration'] = round($val['inbound_wait_ans_duration'] / $val['inbound_wait_ans_times'], 2); //等待应答均长

								@$val['outbound_conv_rate'] = round($val['outbound_conv_times'] / $val['outbound_times'], 4); //通话率
								@$val['outbound_avg_conv_duration'] = round($val['outbound_conv_duration'] / $val['outbound_conv_times'], 2); //平均通话时长
								@$val['outbound_call_loss_times'] = $val['outbound_times'] - $val['outbound_conv_times']; //呼损数
								@$val['outbound_call_loss_rate'] = round($val['outbound_call_loss_times'] / $val['outbound_times'], 4); //呼损率
								@$val['outbound_avg_wait_ans_duration'] = round($val['outbound_wait_ans_duration'] / $val['outbound_wait_ans_times'], 2); //等待应答均长
								@$val['inbound_wrap_up_duration_avg'] = round($val['inbound_wrap_up_duration'] / $val['inbound_wrap_up_times'], 2); //呼入话后处理均长
								@$val['outbound_wrap_up_duration_avg'] = round($val['outbound_wrap_up_duration'] / $val['outbound_wrap_up_times'], 2); //呼出话后入理均长

								//合计数组累加
								foreach ($arr_total as $key => $value)
								{
									$rq = empty($val[$key]) ? 0 : $val[$key];
									$arr_total[$key] += $rq;
								}
								//print_r($arr_total);
								$arr_total_other['inbound_call_loss_times'] += $val['inbound_call_loss_times']; //呼损数
								$arr_total_other['outbound_call_loss_times'] += $val['outbound_call_loss_times']; //呼损数

								//合计数组(取最大值)
								$arr_total_other['inbound_max_conv_duration'] = $arr_total_other['inbound_max_conv_duration'] < $val['inbound_max_conv_duration'] ? $val['inbound_max_conv_duration'] : $arr_total_other['inbound_max_conv_duration'];

								$arr_total_other['outbound_max_conv_duration'] = $arr_total_other['outbound_max_conv_duration'] < $val['outbound_max_conv_duration'] ? $val['outbound_max_conv_duration'] : $arr_total_other['outbound_max_conv_duration'];

								//合计数组(取最小值)
								if ($val['inbound_min_conv_duration']>0) {
									if (0 == $arr_total_other['inbound_min_conv_duration'])
										$arr_total_other['inbound_min_conv_duration'] = $val['inbound_min_conv_duration'];
									else
										$arr_total_other['inbound_min_conv_duration'] = $arr_total_other['inbound_min_conv_duration'] > $val['inbound_min_conv_duration'] ? $val['inbound_min_conv_duration'] : $arr_total_other['inbound_min_conv_duration'];
								}

								if ($val['outbound_min_conv_duration'] > 0) {
									if (0 == $arr_total_other['outbound_min_conv_duration'])
										$arr_total_other['outbound_min_conv_duration'] = $val['outbound_min_conv_duration'];
									else
										$arr_total_other['outbound_min_conv_duration'] = $arr_total_other['outbound_min_conv_duration'] > $val['outbound_min_conv_duration'] ? $val['outbound_min_conv_duration'] : $arr_total_other['outbound_min_conv_duration'];
								}

								//时间转换
								@$val['inbound_conv_duration'] = $this->SecondsToTime($val['inbound_conv_duration']);
								@$val['inbound_max_conv_duration'] = $this->SecondsToTime($val['inbound_max_conv_duration']);

								@$val['outbound_conv_duration'] = $this->SecondsToTime($val['outbound_conv_duration']);
								@$val['outbound_max_conv_duration'] = $this->SecondsToTime($val['outbound_max_conv_duration']);

								$list[] = $val;
								$i ++;
							}
						}
					}

				} // end foreach ($arr_depart as $value)
			$list = count($list) < 1 ? array() : $list;
			$arr_total = array_merge($arr_total, $arr_total_other);
			//echo "<pre>";
			//print_r($tempArray);
			@$arr_total['inbound_conv_rate'] = round($arr_total['inbound_conv_times'] / $arr_total['inbound_times'], 4); //通话率
			@$arr_total['inbound_avg_conv_duration'] = round($arr_total['inbound_conv_duration'] / $arr_total['inbound_conv_times'], 2); //平均通话时长
			@$arr_total['inbound_call_loss_rate'] = round($arr_total['inbound_call_loss_times'] / $arr_total['inbound_times'], 4); //呼损率
			@$arr_total['inbound_avg_wait_ans_duration'] = round($arr_total['inbound_wait_ans_duration'] / $arr_total['inbound_wait_ans_times'], 2); //等待应答均长

			@$arr_total['outbound_conv_rate'] = round($arr_total['outbound_conv_times'] / $arr_total['outbound_times'], 4); //通话率
			@$arr_total['outbound_avg_conv_duration'] = round($arr_total['outbound_conv_duration'] / $arr_total['outbound_conv_times'], 2); //平均通话时长
			@$arr_total['outbound_call_loss_rate'] = round($arr_total['outbound_call_loss_times'] / $arr_total['outbound_times'], 4); //呼损率
			@$arr_total['outbound_avg_wait_ans_duration'] = round($arr_total['outbound_wait_ans_duration'] / $arr_total['outbound_wait_ans_times'], 2); //等待应答均长

            // 话后时间
            $arr_total['inbound_wrap_up_duration_avg'] = round($arr_total['inbound_wrap_up_duration'] / $arr_total['inbound_wrap_up_times'], 2);
            $arr_total['outbound_wrap_up_duration_avg'] = round($arr_total['outbound_wrap_up_duration'] / $arr_total['outbound_wrap_up_times'], 2);

			//时间转换
			@$arr_total['inbound_conv_duration'] = $this->SecondsToTime($arr_total['inbound_conv_duration']);
			@$arr_total['inbound_max_conv_duration'] = $this->SecondsToTime($arr_total['inbound_max_conv_duration']);

			@$arr_total['outbound_conv_duration'] = $this->SecondsToTime($arr_total['outbound_conv_duration']);
			@$arr_total['outbound_max_conv_duration'] = $this->SecondsToTime($arr_total['outbound_max_conv_duration']);

			$arr_total['record_rate'] = round($arr_total['record_times']/($arr_total['inbound_conv_times']+$arr_total['outbound_conv_times']),4);

			//表头(字段)配置
			$cfg_column = array(
					'inbound'	=> array(
							'inbound_times'				=> '呼入次数',
							'inbound_conv_times'		=> '通话次数',
							'inbound_conv_rate'			=> '通话率',
							'inbound_conv_duration'		=> '通话时长',
							'inbound_avg_conv_duration' => '通话均长',
							'inbound_max_conv_duration' => '最长通话时长',
							'inbound_min_conv_duration' => '最短通话时长',
							'inbound_call_loss_times'	=> '呼损数',
							//'inbound_call_loss_rate'	=> '呼损率',
							//'inbound_call_internal_times' => '分机互打数',
							//'inbound_hold_times'		=> '保持次数',
							//'inbound_three_call_times'	=> '三方通话次数',
							//'inbound_wait_ans_times'	=> '等待应答数',
							//'inbound_wait_ans_duration' => '等待应答时长',
							'inbound_avg_wait_ans_duration' => '等待应答均长',
							//'inbound_wrap_up_times'		=> '呼入话后处理次数',
							//'inbound_wrap_up_duration'	=> '话后处理总时长',
							'inbound_wrap_up_duration_avg'	=> '话后均长',
					),
					'outbound'	=> array(
							'outbound_times'			=> '呼出次数',
							'outbound_conv_times'		=> '通话次数',
							'outbound_conv_rate'		=> '通话率',
							'outbound_conv_duration'	=> '通话时长',
							'outbound_avg_conv_duration' => '通话均长',
							'outbound_max_conv_duration' => '最长通话时长',
							'outbound_min_conv_duration' => '最短通话时长',
							'outbound_call_loss_times'	=> '呼损数',
							//'outbound_call_loss_rate'	=> '呼损率',
							//'outbound_call_internal_times' => '分机互打数',
							//'outbound_hold_times'		=> '保持次数', //暂且屏蔽掉 Edit by code at 2012-3-19
							//'outbound_three_call_times'	=> '三方通话次数',
							//'outbound_wait_ans_times'	=> '等待应答数',
							//'outbound_wait_ans_duration' => '等待应答时长',
							//'outbound_avg_wait_ans_duration' => '等待应答均长'
							//'outbound_wrap_up_times'	=> '呼出话后处理次数',
							//'outbound_wrap_up_duration'	=> '呼出话后处理总时长',
							'outbound_wrap_up_duration_avg'	=> '话后均长'
					),
				);
			$this->Tmpl['cfg_column'] = $cfg_column;

			// 默认选中
			$columnArray = array(
					'inbound'	=> array(
							'inbound_times'				=> '呼入次数',
							'inbound_conv_times'		=> '通话次数',
							'inbound_conv_rate'			=> '通话率',
							'inbound_conv_duration'		=> '通话时长',
							'inbound_avg_conv_duration' => '通话均长',
							//'inbound_max_conv_duration' => '最长通话时长',
							//'inbound_min_conv_duration' => '最短通话时长',
							//'inbound_call_loss_times'	=> '呼损数',
							//'inbound_call_loss_rate'	=> '呼损率',
							//'inbound_call_internal_times' => '分机互打数',
							//'inbound_hold_times'		=> '保持次数',
							//'inbound_three_call_times'	=> '三方通话次数',
							//'inbound_wait_ans_times'	=> '等待应答数',
							//'inbound_wait_ans_duration' => '等待应答时长',
							'inbound_avg_wait_ans_duration' => '等待应答均长',
					),
					'outbound'	=> array(
							'outbound_times'			=> '呼出次数',
							'outbound_conv_times'		=> '通话次数',
							'outbound_conv_rate'		=> '通话率',
							'outbound_conv_duration'	=> '通话时长',
							'outbound_avg_conv_duration' => '通话均长',
							//'outbound_max_conv_duration' => '最长通话时长',
							//'outbound_min_conv_duration' => '最短通话时长',
							//'outbound_call_loss_times'	=> '呼损数',
							//'outbound_call_loss_rate'	=> '呼损率',
							//'outbound_call_internal_times' => '分机互打数',
							//'outbound_hold_times'		=> '保持次数', //暂且屏蔽掉 Edit by code at 2012-3-19
							//'outbound_three_call_times'	=> '三方通话次数',
							//'outbound_wait_ans_times'	=> '等待应答数',
							//'outbound_wait_ans_duration' => '等待应答时长',
							//'outbound_avg_wait_ans_duration' => '等待应答均长'
					),
				);

			//导出
			if ('export' == $do) {

				$tempArray = array();
				$columnCount = 0;
				if (empty($_SESSION['column_select']))
				{
					$tempArray = array_merge($columnArray['inbound'], $columnArray['outbound']);
					$columnCount = count($columnArray['inbound']);
				}
				else
				{
					$tempArray = array_flip($_SESSION['column_select']);
					$columnCount = $_SESSION['column_select_count'];
				}
//print_r($list);exit;
				//导出表头
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=group_traffic_total.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);
				if ($_REQUEST['step'] == 'day') {
					$step_name = '日期';
				} else if ($_REQUEST['step'] == 'hour') {
					$step_name = '小时';
				} else if ($_REQUEST['step'] == 'week') {
					$step_name = '周';
				} else if ($_REQUEST['step'] == 'month') {
					$step_name = '月份';
				}
				$xls_columns = array(
					'inbound_times'					=> '呼入次数',
					'inbound_conv_times'			=> '通话次数',
					'inbound_conv_rate'				=> '通话率',
					'inbound_conv_duration'			=> '通话时长',
					'inbound_avg_conv_duration' 	=> '通话均长',
					'inbound_max_conv_duration' 	=> '最长通话时长',
					'inbound_min_conv_duration'		=> '最短通话时长',
					'inbound_call_loss_times'		=> '呼损数',
					'inbound_call_loss_rate'		=> '呼损率',
					'inbound_call_internal_times' 	=> '分机互打数',
					'inbound_hold_times'			=> '保持次数',
					'inbound_three_call_times'		=> '三方通话次数',
					'inbound_wait_ans_times'		=> '等待应答数',
					'inbound_wait_ans_duration' 	=> '等待应答时长',
					'inbound_avg_wait_ans_duration' => '等待应答均长',
					//'inbound_wrap_up_times'			=> '呼入话后处理次数',
					//'inbound_wrap_up_duration'		=> '话后处理总时长',
					'inbound_wrap_up_duration_avg'	=> '话后均长',

					'outbound_times'				=> '呼出次数',
					'outbound_conv_times'			=> '通话次数',
					'outbound_conv_rate'			=> '通话率',
					'outbound_conv_duration'		=> '通话时长',
					'outbound_avg_conv_duration' 	=> '通话均长',
					'outbound_max_conv_duration' 	=> '最长通话时长',
					'outbound_min_conv_duration' 	=> '最短通话时长',
					'outbound_call_loss_times'		=> '呼损数',
					'outbound_call_loss_rate'		=> '呼损率',
					'outbound_call_internal_times' 	=> '分机互打数',
					'outbound_hold_times'			=> '保持次数', //暂且屏蔽掉 Edit by code at 2012-3-19
					'outbound_three_call_times'		=> '三方通话次数',
					'outbound_wait_ans_times'		=> '等待应答数',
					'outbound_wait_ans_duration' 	=> '等待应答时长',
					'outbound_avg_wait_ans_duration'=> '等待应答均长',
					//'outbound_wrap_up_times'		=> '呼出话后处理次数',
					//'outbound_wrap_up_duration'		=> '呼出话后处理总时长',
					'outbound_wrap_up_duration_avg'	=> '话后均长'
				);
				ksort($xls_columns);
				//导出excel, 写表头
				xlsWriteLabel(1, 4, '呼入');
				xlsWriteLabel(1, $columnCount + 4, '呼出');

				if($step_name){
					$cols = 4;
					xlsWriteLabel(2, 0, $step_name);
					xlsWriteLabel(2, 1, '部门');
					xlsWriteLabel(2, 2, '录单次数');
					xlsWriteLabel(2, 3, '录单比率');
				}else{
					$cols = 3;
					xlsWriteLabel(2, 0, '部门');
					xlsWriteLabel(2, 1, '录单次数');
					xlsWriteLabel(2, 2, '录单比率');
				}

				foreach ($tempArray as $key=>$value)
				{
					if (!isset($tempArray[$key])) continue;
					xlsWriteLabel( 2, $cols++, $xls_columns[$key]);
				}
				//ksort($tempArray);
				$rows = 3;
				ksort($val);
				
				foreach ($list as $val)
				{
					$cols = 0;
					if($step_name) xlsWriteLabel($rows, $cols++, $val['total_date']);

					$dept_name = iconv("UTF-8", "GB2312//IGNORE", $val['dept_name']);
					xlsWriteLabel($rows, $cols++, $dept_name);
					xlsWriteLabel($rows, $cols++, $val['record_times']);
					xlsWriteLabel($rows, $cols++, $val['record_rate']);

					foreach ($tempArray as $key=>$v)
					{
						xlsWriteLabel($rows, $cols++, $val[$key]);
					}

					$cols ++;
					$rows ++;

				} // end foreach ($list as $val)

				//导出合计数组
                if ($rows>4) {
					$step_name ? $cols = 1 : $cols = 0;
					xlsWriteLabel($rows, $cols++, '合计：');
					ksort($arr_total);
					xlsWriteLabel($rows, $cols++, $arr_total['record_times']);
					xlsWriteLabel($rows, $cols++, $arr_total['record_rate']);
					foreach ($tempArray as $key=>$v)
					{
						xlsWriteLabel($rows, $cols++, $arr_total[$key]);
					}
				} // end if ($rows>4)

				xlsEOF();
			} // end if ('export' == $do)

			$this->Tmpl['arr_total'] = $arr_total;

            // 选择显示数据列
            if (!empty($inbound) || !empty($outbound))
            {
            	$columnArray['inbound'] = array_flip($inbound);
				$columnArray['outbound'] = array_flip($outbound);
            }

		} // end if ('' != $do)

        $this->Tmpl['select_column'] = $columnArray;
		$this->Tmpl['list'] = $list;

		$dep = array();
		$json_list = array();
		$json_list[0]['name'] = c('呼入次数');
		$json_list[1]['name'] = c('呼入通话次数');
		$json_list[2]['name'] = c('呼损次数');
		$json_list[3]['name'] = c('呼出次数');
		$json_list[4]['name'] = c('呼出通话次数');
		foreach($list as $k=>$value){
			$dep[$k] = $value['dept_name'];
			$json_list[0]['data'][$k] = floatval($value['inbound_times']);
			$json_list[1]['data'][$k] = floatval($value['inbound_conv_times']);
			$json_list[2]['data'][$k] = floatval($value['inbound_call_loss_times']);
			$json_list[3]['data'][$k] = floatval($value['outbound_times']);
			$json_list[4]['data'][$k] = floatval($value['outbound_conv_times']);
		}
		//转为JSON数据
		$json_obj = $this->_get_json_obj();
		$this->Tmpl['dep'] = $json_obj->encode($dep);

		$this->Tmpl['json_list'] = $json_obj->encode($json_list);

		$this->display();
	}
	
	/**
	* @Purpose		: 获取用户所直接主管的已删除部门
	* @Method Name	: getManageDirectDept()
	* @Parameter	:
	*					1)$user_id:用户id,
	* @Return		: array
	*/
	function getManageDirectDeptDel($user_id='')
	{
		if ('' == $user_id) {
			$user = $this->getLocalUser();
			$user_id = $user['user_id'];
			$extension = $user['extension'];
		}
		// else {
			// $extension = $this->getUserExten($user_id);
		// }

		if ('' == $extension) {
			$extension = $_SESSION['userinfo']['extension'];
			$arr_exten = array($extension);
			return $arr_exten;
		}


		//global $cache_user;
		//global $cache_department;

		$arr_dept = array();
		$arr_exten = array();

		// if ($cache_user && $cache_department) {

			// //先根据$user_id获取主管的部门编号
			// foreach ($cache_department as $val)
			// {
				// $arr = explode(',', $val['manager']); //部门主管
				// $arr = array_merge($arr, explode(',', $val['leader1'])); //上级主管领导
				// $arr = array_merge($arr, explode(',', $val['leader2'])); //上级分管领导
				// if (in_array($user_id, $arr)) {
					// $arr_dept[] = $val['dept_id'];
				// }
			// }
		// }
		// else {
			global $db;
			$sql = "SELECT * FROM org_department_del ORDER BY dept_id ASC";
			$rs = $db->GetArray($sql);
			if (empty($rs)) $rs = array();
			//先根据$user_id获取主管的部门编号
			foreach ($rs as $val)
			{
				$arr = explode(',', $val['manager']); //部门主管
				$arr = array_merge($arr, explode(',', $val['leader1'])); //上级主管领导
				$arr = array_merge($arr, explode(',', $val['leader2'])); //上级分管领导
				if (in_array($user_id, $arr)) {
					$arr_dept[] = $val['dept_id'];
				}
			}
		//}
		return $arr_dept;
	}
	
	/**
     +----------------------------------------------------------
     * 已删除部门
     * @author	: 
     * @date	: 2016-05-26
     +----------------------------------------------------------
     */
	function getManageDeptDel($user_id='')
	{
		if ('' == $user_id) {
			$user = $this->getLocalUser();
			$user_id = $user['user_id'];
			$extension = $user['extension'];
		}
		// else {
			// $extension = $this->getUserExten($user_id);
		// }

		if ('' == $extension) {
			$extension = $_SESSION['userinfo']['extension'];
			$arr_exten = array($extension);
			return $arr_exten;
		}


		// global $cache_user;
		// global $cache_department;

		$arr_dept = array();
		$arr_exten = array();

		// if ($cache_user && $cache_department) {

			// //先根据$user_id获取主管的部门编号
			// foreach ($cache_department as $val)
			// {
				// $arr = explode(',', $val['manager']); //部门主管
				// $arr = array_merge($arr, explode(',', $val['leader1'])); //上级主管领导
				// $arr = array_merge($arr, explode(',', $val['leader2'])); //上级分管领导
				// if (in_array($user_id, $arr)) {
					// $arr_dept[] = $val['dept_id'];
				// }
			// }

			// if (count($arr_dept)>0) {

				// //获取所有部门的所有子部门编号(包含自己)
				// $list_deptid = '';;
				// foreach ($arr_dept as $dept_id)
				// {
					// $list_deptid .= $dept_id . ",";
					// $list_deptid .= $this->getNodeChild($cache_department, $dept_id, 'dept');
				// }


				// //去掉重复的部门编号
				// $arr = explode(',', $list_deptid);
				// $arr_dept = array();
				// foreach ($arr as $dept_id)
				// {
					// if (!empty($dept_id) && !in_array($dept_id, $arr_dept)) {
						// $arr_dept[] = $dept_id;
					// }
				// }
			// } // end if (count($arr_dept)>0)

		// }
		// else {
			global $db;
			$sql = "SELECT * FROM org_department_del ORDER BY dept_id ASC";
			$rs = $db->GetArray($sql);
			if (empty($rs)) $rs = array();
			//先根据$user_id获取主管的部门编号
			foreach ($rs as $val)
			{
				$arr = explode(',', $val['manager']); //部门主管
				$arr = array_merge($arr, explode(',', $val['leader1'])); //上级主管领导
				$arr = array_merge($arr, explode(',', $val['leader2'])); //上级分管领导
				if (in_array($user_id, $arr)) {
					$arr_dept[] = $val['dept_id'];
				}
			}

			if (count($arr_dept)>0) {

				//获取所有部门的所有子部门编号(包含自己)
				$list_deptid = '';
				foreach ($arr_dept as $dept_id)
				{
					$list_deptid .= $dept_id . ",";
					$list_deptid .= $this->getNodeChild($rs, $dept_id, 'dept');
				}


				//去掉重复的部门编号
				$arr = explode(',', $list_deptid);
				$arr_dept = array();
				foreach ($arr as $dept_id)
				{
					if (!empty($dept_id) && !in_array($dept_id, $arr_dept)) {
						$arr_dept[] = $dept_id;
					}
				}
			} // end if (count($arr_dept[])>0)
		// }
		return $arr_dept;
	}
	

	/**
     +----------------------------------------------------------
     * 系统话务量报表(呼入/呼出统计)
     * @author	: pengj
     * @date	: 2012/3/1
     +----------------------------------------------------------
     */
	function showSysTrafficTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'sys_traffic_total', $arr_local_priv, '您没有查看系统话务量报表的权限！' );

		if (isset($_REQUEST['inbound']) || isset($_REQUEST['outbound']))
		{
			$_SESSION['column_select_sys'] = array_merge($_REQUEST['inbound'], $_REQUEST['outbound']);
			$_SESSION['column_select_sys_count'] = count($_REQUEST['inbound']);
		}

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time());
		if (!isset($_REQUEST['s_hour'])) $_REQUEST['s_hour'] = '00';
		if (!isset($_REQUEST['e_hour'])) $_REQUEST['e_hour'] = '23';
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		$list = array();

        // 选项菜单
		$cfg_column = array(
			'inbound'	=> array(
					'inbound_times' 					=> '呼入次数',
					'inbound_conv_times' 				=> '通话次数',
					'inbound_conv_rate' 				=> '通话率',
					'inbound_call_loss_times' 			=> '呼损数',
					'inbound_conv_duration' 			=> '通话时长',
					'inbound_avg_conv_duration' 		=> '平均通话时长',
					'inbound_max_conv_duration' 		=> '最长通话时长',
		            'inbound_min_conv_duration' 		=> '最短通话时长',
		            'inbound_avg_wait_ans_duration' 	=> '等待应答均长',
					'inbound_ivr_occupation_times'		=> 'IVR占用数',
		            'inbound_ivr_connected_times'		=> 'IVR接通数',
					'inbound_avg_ivr_conv_duration' 	=> 'IVR通话均长',
					'inbound_manual_occupation_times' 	=> '人工占用数',
		            'inbound_manual_connected_times' 	=> '人工接通数',
		            'inbound_avg_manual_conv_duration' 	=> '人工通话均长',
					'inbound_queue_waiting_times' 		=> '排队等待数',
					'inbound_avg_queue_waiting_duration'=> '排队等待均长',
					'inbound_wrap_up_duration_avg'		=> '话后均长'
		            //'inbound_hold_times' => 1,
		            //'inbound_three_call_times' => 0,
		            //'inbound_trans_inner_times' => 0,
		            //'call_internal_times' => 178,
		            //'inbound_trans_ivr_times' => 0,
		            //'inbound_non_ans_times' => 227,
		            //'inbound_wait_ans_times' => 394,
		            //'inbound_wait_ans_duration' => 4594,
		            //'inbound_ivr_conv_duration' => 4030,
		            //'inbound_manual_conv_duration' => 35524,
		            //'inbound_queue_waiting_duration' => 956,
		            //'inbound_call_loss_rate' => 0.1653,
					//'inbound_wrap_up_times'				=> '呼入话后处理次数',
					//'inbound_wrap_up_duration'			=> '话后处理总时长',
			),
			'outbound'	=> array(
					'outbound_times' 					=> '呼出次数',
		            'outbound_conv_times' 				=> '通话次数',
					'outbound_conv_rate' 				=> '通话率',
					'outbound_call_loss_times' 			=> '呼损数',
					'outbound_conv_duration' 			=> '通话时长',
					'outbound_avg_conv_duration' 		=> '平均通话时长',
					'outbound_max_conv_duration' 		=> '最长通话时长',
		            'outbound_min_conv_duration' 		=> '最短通话时长',
					'outbound_wrap_up_duration_avg'		=> '话后均长'
		           // 'outbound_hold_times' => 0,
		            //'outbound_three_call_times' => 0,
		            //'outbound_trans_inner_times' => 0,
		            //'outbound_trans_ivr_times' => 0,
		            //'outbound_non_ans_times' => 318,
		            //'outbound_wait_ans_times' => 787,
		            //'outbound_wait_ans_duration' => 12517,
		            //'outbound_ivr_occupation_times' => 0,
		            //'outbound_ivr_connected_times' => 0,
		            //'outbound_ivr_conv_duration' => 0,
		            //'outbound_manual_occupation_times' => 824,
		            //'outbound_manual_connected_times' => 506,
		            //'outbound_manual_conv_duration' => 71528,
		            //'outbound_queue_waiting_times' => 0,
		            //'outbound_queue_waiting_duration' => 0,
		            //'outbound_call_loss_rate' => 0.6285,
		            //'outbound_avg_wait_ans_duration' => 15.9,
		            //'outbound_avg_ivr_conv_duration' => 0,
		            //'outbound_avg_manual_conv_duration' => 141.36,
		            //'outbound_avg_queue_waiting_duration' => 0
		           // 'outbound_wrap_up_times'	=> '呼出话后处理次数',
					//'outbound_wrap_up_duration'	=> '呼出话后处理总时长',
			)
		);
		$this->Tmpl['cfg_column'] = $cfg_column;

		// 默认选中选项菜单
		$optionArray = array(
			'inbound'	=> array(
					'inbound_conv_duration' 			=> '通话时长',
		            'inbound_conv_times' 				=> '通话次数',
		            //'inbound_hold_times' => 1,
		            //'inbound_three_call_times' => 0,
		            'inbound_times' 					=> '呼入次数',
		            //'inbound_trans_inner_times' => 0,
		            //'call_internal_times' => 178,
		            //'inbound_trans_ivr_times' => 0,
		            'inbound_max_conv_duration' 		=> '最长通话时长',
		            'inbound_min_conv_duration' 		=> '最短通话时长',
		            //'inbound_non_ans_times' => 227,
		            //'inbound_wait_ans_times' => 394,
		            //'inbound_wait_ans_duration' => 4594,
		            'inbound_ivr_occupation_times'		=> 'IVR占用数',
		            'inbound_ivr_connected_times'		=> 'IVR接通数',
		            //'inbound_ivr_conv_duration' => 4030,
		            'inbound_manual_occupation_times' 	=> '人工占用数',
		            'inbound_manual_connected_times' 	=> '人工接通数',
		            //'inbound_manual_conv_duration' => 35524,
		            'inbound_queue_waiting_times' 		=> '排队等待数',
		            //'inbound_queue_waiting_duration' => 956,
					'inbound_conv_rate' 				=> '通话率',
		            //'inbound_avg_conv_duration' 		=> '平均通话时长',
		            //'inbound_call_loss_times' 			=> '呼损数',
		            //'inbound_call_loss_rate' => 0.1653,
		            //'inbound_avg_wait_ans_duration' 	=> '等待应答均长',
		            //'inbound_avg_ivr_conv_duration' 	=> 'IVR通话均长',
		            //'inbound_avg_manual_conv_duration' 	=> '人工通话均长',
		            //'inbound_avg_queue_waiting_duration'=> '排队等待均长',
			),
			'outbound'	=> array(
		            'outbound_conv_duration' 			=> '通话时长',
		            'outbound_conv_times' 				=> '通话次数',
		           // 'outbound_hold_times' => 0,
		            //'outbound_three_call_times' => 0,
		            'outbound_times' 					=> '呼出次数',
		            //'outbound_trans_inner_times' => 0,
		            //'outbound_trans_ivr_times' => 0,
		            'outbound_max_conv_duration' 		=> '最长通话时长',
		            'outbound_min_conv_duration' 		=> '最短通话时长',
		            //'outbound_non_ans_times' => 318,
		            //'outbound_wait_ans_times' => 787,
		            //'outbound_wait_ans_duration' => 12517,
		            //'outbound_ivr_occupation_times' => 0,
		            //'outbound_ivr_connected_times' => 0,
		            //'outbound_ivr_conv_duration' => 0,
		            //'outbound_manual_occupation_times' => 824,
		            //'outbound_manual_connected_times' => 506,
		            //'outbound_manual_conv_duration' => 71528,
		            //'outbound_queue_waiting_times' => 0,
		            //'outbound_queue_waiting_duration' => 0,
		            'outbound_conv_rate' 				=> '通话率',
		            'outbound_avg_conv_duration' 		=> '平均通话时长',
		            'outbound_call_loss_times' 			=> '呼损数',
		            //'outbound_call_loss_rate' => 0.6285,
		            //'outbound_avg_wait_ans_duration' => 15.9,
		            //'outbound_avg_ivr_conv_duration' => 0,
		            //'outbound_avg_manual_conv_duration' => 141.36,
		            //'outbound_avg_queue_waiting_duration' => 0
			)
		);

		if ('' != $do) {

			$condition = " 1 ";
			if (!empty($fromdate))
			{
				if (empty($s_hour)) $s_hour = '00';
				$fromdate .= ' ' . $s_hour;
				$condition .= " and total_date>='$fromdate'";
			}

			if (!empty($todate))
			 {
				if (empty($e_hour)) $e_hour = '59';
				$todate .= ' ' . $e_hour . ':59:59';
				$condition .= " and total_date<='$todate'";
			}

            // 选项菜单
			if (!empty($inbound) || !empty($outbound))
			{
				$optionArray = array();
				$optionArray['inbound'] = array_flip($inbound);
				$optionArray['outbound'] = array_flip($outbound);
			}

			//按统计步长
			if ('day' == $step || empty($step))
				$key = "SUBSTRING(total_date, 1, 10)";
			else if ('hour' == $step)
				$key = "SUBSTRING(total_date, 12, 2)";
			else if ('month' == $step)
				$key = "SUBSTRING(total_date, 1, 7)";
			else if ('week' == $step)
				$key = "WEEK(total_date, 1)";

			if ('search' == $do) {
				$sql = "select $key as total_date, count(0) from crm_sys_traffic_total where " . $condition . " group by $key";
				$sql = "select count(0) from ($sql) as n";
				$record_nums = $db->GetOne($sql);

				$pg = loadClass('tool','page',$this);
				$pg->setPageVar('p');
				$pg->setNumPerPage(30);

				$currentPage = $_REQUEST['p'];
				unset($_REQUEST['p']);
				unset($_REQUEST['action']);
				unset($_REQUEST['module']);

				$pg->setVar($_REQUEST);
				$pg->setVar(array("module"=>"report","action"=>"sysTrafficTotal"));
				$pg->set($record_nums,$currentPage);
				$this->Tmpl['show_pages'] = $pg->output(1);
			}

			$sql = "select $key as total_date,date_format(total_date,'%Y') AS year, ";
			$sql .= "sum(inbound_conv_duration) as inbound_conv_duration, ";
			$sql .= "sum(inbound_conv_times) as inbound_conv_times, ";
			$sql .= "sum(inbound_hold_times) as inbound_hold_times, ";
			$sql .= "sum(inbound_three_call_times) as inbound_three_call_times, ";
			$sql .= "sum(inbound_times) as inbound_times, ";
			$sql .= "sum(inbound_trans_inner_times) as inbound_trans_inner_times, ";
			$sql .= "sum(call_internal_times) as call_internal_times, ";
			$sql .= "sum(inbound_trans_ivr_times) as inbound_trans_ivr_times, ";
			$sql .= "max(inbound_max_conv_duration) as inbound_max_conv_duration, ";
			$sql .= "min(case when inbound_min_conv_duration>0 then inbound_min_conv_duration end) as inbound_min_conv_duration, ";
			$sql .= "sum(inbound_non_ans_times) as inbound_non_ans_times, ";
			$sql .= "sum(inbound_wait_ans_times) as inbound_wait_ans_times, ";
			$sql .= "sum(inbound_wait_ans_duration) as inbound_wait_ans_duration, ";
			$sql .= "sum(inbound_ivr_occupation_times) as inbound_ivr_occupation_times, ";
			$sql .= "sum(inbound_ivr_connected_times) as inbound_ivr_connected_times, ";
			$sql .= "sum(inbound_ivr_conv_duration) as inbound_ivr_conv_duration, ";
			$sql .= "sum(inbound_manual_occupation_times) as inbound_manual_occupation_times, ";
			$sql .= "sum(inbound_manual_connected_times) as inbound_manual_connected_times, ";
			$sql .= "sum(inbound_manual_conv_duration) as inbound_manual_conv_duration, ";
			$sql .= "sum(inbound_queue_waiting_times) as inbound_queue_waiting_times, ";
			$sql .= "sum(inbound_queue_waiting_duration) as inbound_queue_waiting_duration, ";

			$sql .= "sum(outbound_conv_duration) as outbound_conv_duration, ";
			$sql .= "sum(outbound_conv_times) as outbound_conv_times, ";
			$sql .= "sum(outbound_hold_times) as outbound_hold_times, ";
			$sql .= "sum(outbound_three_call_times) as outbound_three_call_times, ";
			$sql .= "sum(outbound_times) as outbound_times, ";
			$sql .= "sum(outbound_trans_inner_times) as outbound_trans_inner_times, ";
			$sql .= "sum(outbound_trans_ivr_times) as outbound_trans_ivr_times, ";
			$sql .= "max(outbound_max_conv_duration) as outbound_max_conv_duration, ";
			$sql .= "min(case when outbound_min_conv_duration>0 then outbound_min_conv_duration end) as outbound_min_conv_duration, ";
			$sql .= "sum(outbound_non_ans_times) as outbound_non_ans_times, ";
			$sql .= "sum(outbound_wait_ans_times) as outbound_wait_ans_times, ";
			$sql .= "sum(outbound_wait_ans_duration) as outbound_wait_ans_duration, ";
			$sql .= "sum(outbound_ivr_occupation_times) as outbound_ivr_occupation_times, ";
			$sql .= "sum(outbound_ivr_connected_times) as outbound_ivr_connected_times, ";
			$sql .= "sum(outbound_ivr_conv_duration) as outbound_ivr_conv_duration, ";
			$sql .= "sum(outbound_manual_occupation_times) as outbound_manual_occupation_times, ";
			$sql .= "sum(outbound_manual_connected_times) as outbound_manual_connected_times, ";
			$sql .= "sum(outbound_manual_conv_duration) as outbound_manual_conv_duration, ";
			$sql .= "sum(outbound_queue_waiting_times) as outbound_queue_waiting_times, ";
			$sql .= "sum(outbound_queue_waiting_duration) as outbound_queue_waiting_duration, ";

			$sql .= 'SUM(inbound_wrap_up_times) AS inbound_wrap_up_times, ';
			$sql .= 'SUM(inbound_wrap_up_duration) AS inbound_wrap_up_duration, ';
			$sql .= 'SUM(outbound_wrap_up_times) AS outbound_wrap_up_times, ';
			$sql .= 'SUM(outbound_wrap_up_duration) AS outbound_wrap_up_duration ';

			$sql .= "from crm_sys_traffic_total where " . $condition . " group by $key order by total_date asc";

			if ('export' != $do)
			{
				if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset()))
				echo $db->ErrorMsg();
			}
			else
			{
				if (!$rs = $db->Execute($sql))
				echo $db->ErrorMsg();
			}
			if ('export' == $do)
			{
				$tempArray = array();
				$columnCount = 0;
				
				if (!isset($_SESSION['column_select_sys']) || empty($_SESSION['column_select_sys'])) {
					$tempArray = array_merge($cfg_column['inbound'], $cfg_column['outbound']);
					$columnCount = count($cfg_column);
				} else {
					$tempArray = array_flip($_SESSION['column_select_sys']);
					$columnCount = $_SESSION['column_select_sys_count'];
				}

				//导出表头
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=sys_traffic_total.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);

				$xls_columns = array(
					'inbound_conv_duration' 			=> '通话时长',
		            'inbound_conv_times' 				=> '通话次数',
		            'inbound_times' 					=> '呼入次数',
		            'inbound_max_conv_duration' 		=> '最长通话时长',
		            'inbound_min_conv_duration' 		=> '最短通话时长',
		            'inbound_ivr_occupation_times'		=> 'IVR占用数',
		            'inbound_ivr_connected_times'		=> 'IVR接通数',
		            'inbound_manual_occupation_times' 	=> '人工占用数',
		            'inbound_manual_connected_times' 	=> '人工接通数',
		            'inbound_queue_waiting_times' 		=> '排队等待数',
					'inbound_conv_rate' 				=> '通话率',
		            'inbound_avg_conv_duration' 		=> '平均通话时长',
		            'inbound_call_loss_times' 			=> '呼损数',
		            'inbound_avg_wait_ans_duration' 	=> '等待应答均长',
		            'inbound_avg_ivr_conv_duration' 	=> 'IVR通话均长',
		            'inbound_avg_manual_conv_duration' 	=> '人工通话均长',
		            'inbound_avg_queue_waiting_duration'=> '排队等待均长',
					//'inbound_wrap_up_times'				=> '呼入话后处理次数',
					//'inbound_wrap_up_duration'			=> '话后处理总时长',
					'inbound_wrap_up_duration_avg'		=> '话后均长',
					'outbound_conv_duration' 			=> '通话时长',
		            'outbound_conv_times' 				=> '通话次数',
		            'outbound_times' 					=> '呼出次数',
		            'outbound_max_conv_duration' 		=> '最长通话时长',
		            'outbound_min_conv_duration' 		=> '最短通话时长',
		            'outbound_conv_rate' 				=> '通话率',
		            'outbound_avg_conv_duration' 		=> '平均通话时长',
		            'outbound_call_loss_times' 			=> '呼损数',
					//'outbound_wrap_up_times'			=> '呼出话后处理次数',
					//'outbound_wrap_up_duration'			=> '呼出话后处理总时长',
					'outbound_wrap_up_duration_avg'		=> '话后均长'
				);

				//导出excel, 写表头
				xlsWriteLabel(1, 1, '呼入');
				xlsWriteLabel(1, $columnCount + 1, '呼出');

				if ('hour' == $_REQUEST['step'])
				{
					xlsWriteLabel(2, 0, '时间');
				}
				else
				{
					xlsWriteLabel(2, 0, '日期');
				}
				$cols = 1;
				ksort($xls_columns);
				foreach ($tempArray as $key=>$value)
				{
					if (!isset($tempArray[$key])) continue;
					xlsWriteLabel(2, $cols++, $xls_columns[$key]);
				}
			} // end if ('export' == $do)


			//初始化当前页合计数组(累加)
			$arr_total = array(
					'inbound_conv_duration'			=> 0,
					'inbound_conv_times'			=> 0,
					'inbound_hold_times'			=> 0,
					'inbound_three_call_times'		=> 0,
					'inbound_times'					=> 0,
					'inbound_trans_inner_times'		=> 0,
					'call_internal_times'			=> 0,
					'inbound_trans_ivr_times'		=> 0,
					'inbound_non_ans_times'			=> 0,
					'inbound_wait_ans_times'		=> 0,
					'inbound_wait_ans_duration'		=> 0,
					'inbound_ivr_occupation_times'	=> 0,
					'inbound_ivr_connected_times'	=> 0,
					'inbound_ivr_conv_duration'		=> 0,
					'inbound_manual_occupation_times' => 0,
					'inbound_manual_connected_times' => 0,
					'inbound_manual_conv_duration'	=> 0,
					'inbound_queue_waiting_times'	=> 0,
					'inbound_queue_waiting_duration' => 0,
					'inbound_wrap_up_times'			=> 0,
					'inbound_wrap_up_duration'		=> 0,
					'inbound_wrap_up_duration_avg'	=> 0,
					'outbound_wrap_up_times'		=> 0,
					'outbound_wrap_up_duration'		=> 0,
					'outbound_wrap_up_duration_avg'	=> 0,
					'outbound_conv_duration'		=> 0,
					'outbound_conv_times'			=> 0,
					'outbound_hold_times'			=> 0,
					'outbound_three_call_times'		=> 0,
					'outbound_times'				=> 0,
					'outbound_trans_inner_times'	=> 0,
					'outbound_trans_ivr_times'		=> 0,
					'outbound_non_ans_times'		=> 0,
					'outbound_wait_ans_times'		=> 0,
					'outbound_wait_ans_duration'	=> 0,
					'outbound_ivr_occupation_times'	=> 0,
					'outbound_ivr_connected_times'	=> 0,
					'outbound_ivr_conv_duration'	=> 0,
					'outbound_manual_occupation_times' => 0,
					'outbound_manual_connected_times' => 0,
					'outbound_manual_conv_duration'	=> 0,
					'outbound_queue_waiting_times'	=> 0,
					'outbound_queue_waiting_duration' => 0
				);

			//其它合计数组(通过数据表公式计算得出)
			$arr_total_other = array(
					'inbound_conv_rate'				=> 0, //通话率
					'inbound_avg_conv_duration'		=> 0, //通话均长
					'inbound_call_loss_times'		=> 0, //呼损数
					'inbound_call_loss_rate'		=> 0, //呼损率
					'inbound_avg_wait_ans_duration' => 0, //等待应答均长
					'inbound_max_conv_duration'		=> 0, //最长通话时长
					'inbound_min_conv_duration'		=> 0, //最短通话时长
					'inbound_avg_ivr_conv_duration' => 0, //IVR通话均长
					'inbound_avg_manual_conv_duration' => 0, //人工通话均长
					'inbound_avg_queue_waiting_duration' => 0, //排除等待均长
					'outbound_conv_rate'			=> 0, //通话率
					'outbound_avg_conv_duration'	=> 0, //通话均长
					'outbound_call_loss_times'		=> 0, //呼损数
					'outbound_call_loss_rate'		=> 0, //呼损率
					'outbound_avg_wait_ans_duration' => 0, //等待应答均长
					'outbound_max_conv_duration'	=> 0, //最长通话时长
					'outbound_min_conv_duration'	=> 0, //最短通话时长
					'outbound_avg_ivr_conv_duration' => 0, //IVR通话均长
					'outbound_avg_manual_conv_duration' => 0, //人工通话均长
					'outbound_avg_queue_waiting_duration' => 0 //排除等待均长
				);

			$i = 1;
			$rows = 3;
			while (!$rs->EOF)
			{
				//echo $rs->fields['total_date'];
				$cols = 1;

				@$rs->fields['inbound_conv_rate'] = round($rs->fields['inbound_conv_times'] / $rs->fields['inbound_times'], 4); //通话率
				@$rs->fields['inbound_avg_conv_duration'] = round($rs->fields['inbound_conv_duration'] / $rs->fields['inbound_conv_times'], 2); //平均通话时长
				@$rs->fields['inbound_call_loss_times'] = $rs->fields['inbound_times'] - $rs->fields['inbound_conv_times']; //呼损数
				@$rs->fields['inbound_call_loss_rate'] = round($rs->fields['inbound_call_loss_times'] / $rs->fields['inbound_times'], 4); //呼损率
				@$rs->fields['inbound_avg_wait_ans_duration'] = round($rs->fields['inbound_wait_ans_duration'] / $rs->fields['inbound_wait_ans_times'], 2); //等待应答均长
				@$rs->fields['inbound_avg_ivr_conv_duration'] = round($rs->fields['inbound_ivr_conv_duration'] / $rs->fields['inbound_ivr_connected_times'], 2); //ivr话均长
				@$rs->fields['inbound_avg_manual_conv_duration'] = round($rs->fields['inbound_manual_conv_duration'] / $rs->fields['inbound_manual_connected_times'], 2); //人工通话均长
				@$rs->fields['inbound_avg_queue_waiting_duration'] = round($rs->fields['inbound_queue_waiting_duration'] / $rs->fields['inbound_queue_waiting_times'], 2); //排队等待均长

				@$rs->fields['outbound_conv_rate'] = round($rs->fields['outbound_conv_times'] / $rs->fields['outbound_times'], 4); //通话率
				@$rs->fields['outbound_avg_conv_duration'] = round($rs->fields['outbound_conv_duration'] / $rs->fields['outbound_conv_times'], 2); //平均通话时长
				@$rs->fields['outbound_call_loss_times'] = $rs->fields['outbound_times'] - $rs->fields['outbound_conv_times']; //呼损数
				@$rs->fields['outbound_call_loss_rate'] = round($rs->fields['outbound_call_loss_times'] / $rs->fields['outbound_conv_times'], 4); //呼损率
				@$rs->fields['outbound_avg_wait_ans_duration'] =round( $rs->fields['outbound_wait_ans_duration'] / $rs->fields['outbound_wait_ans_times'], 2); //等待应答均长
				@$rs->fields['outbound_avg_ivr_conv_duration'] = round($rs->fields['outbound_ivr_conv_duration'] / $rs->fields['outbound_ivr_connected_times'], 2); //ivr通话均长
				@$rs->fields['outbound_avg_manual_conv_duration'] = round($rs->fields['outbound_manual_conv_duration'] / $rs->fields['outbound_manual_connected_times'], 2); //人工通话均长
				@$rs->fields['outbound_avg_queue_waiting_duration'] = round($rs->fields['outbound_queue_waiting_duration'] / $rs->fields['outbound_queue_waiting_times'], 2); //排队等待均长
				@$rs->fields['inbound_wrap_up_duration_avg'] = round($rs->fields['inbound_wrap_up_duration'] / $rs->fields['inbound_wrap_up_times'], 2); //呼入话后处理均长
				@$rs->fields['outbound_wrap_up_duration_avg'] = round($rs->fields['outbound_wrap_up_duration'] / $rs->fields['outbound_wrap_up_times'], 2); //呼出话后处理均长
				// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
				if($step == 'week'){
					$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['total_date']);
					$rs->fields['total_date'] = $week_time['start'].c('至').$week_time['end'];
				}

				//合计数组累加
				foreach ($arr_total as $key => $value) $arr_total[$key] += $rs->fields[$key];
				$arr_total_other['inbound_call_loss_times'] += $rs->fields['inbound_call_loss_times']; //呼损数
				$arr_total_other['outbound_call_loss_times'] += $rs->fields['outbound_call_loss_times']; //呼损数

				//合计数组(取平均值)
				if ($i > 2) $i = 2;

				$arr_total_other['inbound_conv_rate'] = ($arr_total_other['inbound_conv_rate'] + $rs->fields['inbound_conv_rate']) / $i; //通话率
				$arr_total_other['inbound_avg_conv_duration'] = ($arr_total_other['inbound_avg_conv_duration'] + $rs->fields['inbound_avg_conv_duration']) / $i; //通话均长
				$arr_total_other['inbound_call_loss_rate'] = ($arr_total_other['inbound_call_loss_rate'] + $rs->fields['inbound_call_loss_rate']) / $i; //呼损率
				$arr_total_other['inbound_avg_wait_ans_duration'] = ($arr_total_other['inbound_avg_wait_ans_duration'] + $rs->fields['inbound_avg_wait_ans_duration']) / $i; //等待应答均长
				$arr_total_other['inbound_avg_ivr_conv_duration'] = ($arr_total_other['inbound_avg_ivr_conv_duration'] + $rs->fields['inbound_avg_ivr_conv_duration']) / $i; //IVR通话均长
				$arr_total_other['inbound_avg_manual_conv_duration'] = ($arr_total_other['inbound_avg_manual_conv_duration'] + $rs->fields['inbound_avg_manual_conv_duration']) / $i; //人工通话均长
				$arr_total_other['inbound_avg_queue_waiting_duration'] = ($arr_total_other['inbound_avg_queue_waiting_duration'] + $rs->fields['inbound_avg_queue_waiting_duration']) / $i; //排除等待均长

				$arr_total_other['outbound_conv_rate'] = ($arr_total_other['outbound_conv_rate'] + $rs->fields['outbound_conv_rate']) / $i; //通话率
				$arr_total_other['outbound_avg_conv_duration'] = ($arr_total_other['outbound_avg_conv_duration'] + $rs->fields['outbound_avg_conv_duration']) / $i; //通话均长
				$arr_total_other['outbound_call_loss_rate'] = ($arr_total_other['outbound_call_loss_rate'] + $rs->fields['outbound_call_loss_rate']) / $i; //呼损率
				$arr_total_other['outbound_avg_wait_ans_duration'] = ($arr_total_other['outbound_avg_wait_ans_duration'] + $rs->fields['outbound_avg_wait_ans_duration']) / $i; //等待应答均长
				$arr_total_other['outbound_avg_ivr_conv_duration'] = ($arr_total_other['outbound_avg_ivr_conv_duration'] + $rs->fields['outbound_avg_ivr_conv_duration']) / $i; //IVR通话均长
				$arr_total_other['outbound_avg_manual_conv_duration'] = ($arr_total_other['outbound_avg_manual_conv_duration'] + $rs->fields['outbound_avg_manual_conv_duration']) / $i; //人工通话均长
				$arr_total_other['outbound_avg_queue_waiting_duration'] = ($arr_total_other['outbound_avg_queue_waiting_duration'] + $rs->fields['outbound_avg_queue_waiting_duration']) / $i; //排除等待均长


				//合计数组(取最大值)
				$arr_total_other['inbound_max_conv_duration'] = $arr_total_other['inbound_max_conv_duration'] < $rs->fields['inbound_max_conv_duration'] ? $rs->fields['inbound_max_conv_duration'] : $arr_total_other['inbound_max_conv_duration'];
				$arr_total_other['outbound_max_conv_duration'] = $arr_total_other['outbound_max_conv_duration'] < $rs->fields['outbound_max_conv_duration'] ? $rs->fields['outbound_max_conv_duration'] : $arr_total_other['outbound_max_conv_duration'];


				//合计数组(取最小值)
				if ($rs->fields['inbound_min_conv_duration']>0) {
					if (0 === $arr_total_other['inbound_min_conv_duration'])
						$arr_total_other['inbound_min_conv_duration'] = $rs->fields['inbound_min_conv_duration'];
					else
						$arr_total_other['inbound_min_conv_duration'] = $arr_total_other['inbound_min_conv_duration'] > $rs->fields['inbound_min_conv_duration'] ? $rs->fields['inbound_min_conv_duration'] : $arr_total_other['inbound_min_conv_duration'];
				}

				if ($rs->fields['outbound_min_conv_duration'] > 0) {
					if (0 === $arr_total_other['outbound_min_conv_duration'])
						$arr_total_other['outbound_min_conv_duration'] = $rs->fields['outbound_min_conv_duration'];
					else
						$arr_total_other['outbound_min_conv_duration'] = $arr_total_other['outbound_min_conv_duration'] > $rs->fields['outbound_min_conv_duration'] ? $rs->fields['outbound_min_conv_duration'] : $arr_total_other['outbound_min_conv_duration'];
				}


				//时间转换
				@$rs->fields['inbound_conv_duration'] = $this->SecondsToTime($rs->fields['inbound_conv_duration']); //通话时长
				@$rs->fields['inbound_max_conv_duration'] = $this->SecondsToTime($rs->fields['inbound_max_conv_duration']); //最长通话时长

				@$rs->fields['outbound_conv_duration'] = $this->SecondsToTime($rs->fields['outbound_conv_duration']); //通话时长
				@$rs->fields['outbound_max_conv_duration'] = $this->SecondsToTime($rs->fields['outbound_max_conv_duration']); //最长通话时长


				//导出
				if ('export' == $do)
				{
					if ($step != '') $total_date = $rs->fields['total_date'];
					else $total_date = $fromdate . " 至 " . $todate;
					ksort($rs->fields);
					xlsWriteLabel($rows, 0, $rs->fields['total_date']);
					foreach ($tempArray as $key=>$v)
					{
						xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
					}
				} // end if ('export' == $do)
				else
				{
					$list[] = $rs->fields;
				}

				$i++;
				$rows ++;
				$rs->MoveNext();
			} // end while(!$rs->EOF)
			$list = count($list) < 1 ? array() : $list;
			$arr_total = array_merge($arr_total, $arr_total_other);

			@$arr_total['inbound_conv_rate'] = round($arr_total['inbound_conv_times'] / $arr_total['inbound_times'], 4); //通话率
			@$arr_total['inbound_avg_conv_duration'] = round($arr_total['inbound_conv_duration'] / $arr_total['inbound_conv_times'], 2); //平均通话时长
			@$arr_total['inbound_call_loss_rate'] = round($arr_total['inbound_call_loss_times'] / $arr_total['inbound_times'], 4); //呼损率
			@$arr_total['inbound_avg_wait_ans_duration'] = round($arr_total['inbound_wait_ans_duration'] / $arr_total['inbound_wait_ans_times'], 2); //等待应答均长

			@$arr_total['outbound_conv_rate'] = round($arr_total['outbound_conv_times'] / $arr_total['outbound_times'], 4); //通话率
			@$arr_total['outbound_avg_conv_duration'] = round($arr_total['outbound_conv_duration'] / $arr_total['outbound_conv_times'], 2); //平均通话时长
			@$arr_total['outbound_call_loss_rate'] = round($arr_total['outbound_call_loss_times'] / $arr_total['outbound_times'], 4); //呼损率
			@$arr_total['outbound_avg_wait_ans_duration'] = round($arr_total['outbound_wait_ans_duration'] / $arr_total['outbound_wait_ans_times'], 2); //等待应答均长

			@$arr_total['inbound_avg_ivr_conv_duration'] = round($arr_total['inbound_ivr_conv_duration'] / $arr_total['inbound_ivr_connected_times'], 2); //ivr话均长
			@$arr_total['inbound_avg_manual_conv_duration'] = round($arr_total['inbound_manual_conv_duration'] / $arr_total['inbound_manual_connected_times'], 2); //人工通话均长
			@$arr_total['inbound_avg_queue_waiting_duration'] = round($arr_total['inbound_queue_waiting_duration'] / $arr_total['inbound_queue_waiting_times'], 2); //排队等待均长

			@$arr_total['outbound_avg_ivr_conv_duration'] = round($arr_total['outbound_ivr_conv_duration'] / $arr_total['outbound_ivr_connected_times'], 2); //ivr通话均长
			@$arr_total['outbound_avg_manual_conv_duration'] = round($arr_total['outbound_manual_conv_duration'] / $arr_total['outbound_manual_connected_times'], 2); //人工通话均长
			@$arr_total['outbound_avg_queue_waiting_duration'] = round($arr_total['outbound_queue_waiting_duration'] / $arr_total['outbound_queue_waiting_times'], 2); //排队等待均长

			//时间转换
			@$arr_total['inbound_conv_duration'] = $this->SecondsToTime($arr_total['inbound_conv_duration']); //通话时长
			@$arr_total['inbound_max_conv_duration'] = $this->SecondsToTime($arr_total['inbound_max_conv_duration']); //最长通话时长

			@$arr_total['outbound_conv_duration'] = $this->SecondsToTime($arr_total['outbound_conv_duration']); //通话时长
			@$arr_total['outbound_max_conv_duration'] = $this->SecondsToTime($arr_total['outbound_max_conv_duration']); //最长通话时长


			//导出合计数组
            $arr_total['inbound_wrap_up_duration_avg'] = round($arr_total['inbound_wrap_up_duration'] / $arr_total['inbound_wrap_up_times'], 2);
            $arr_total['outbound_wrap_up_duration_avg'] = round($arr_total['outbound_wrap_up_duration'] / $arr_total['outbound_wrap_up_times'], 2);
            //print_r($arr_total);exit;
			if ('export' == $do) {
				$cols = 0;
				xlsWriteLabel($rows, $cols++, '合计：');
				ksort($arr_total);
				foreach ($tempArray as $key=>$v) {
					xlsWriteLabel($rows, $cols++, $arr_total[$key]);
				}
			} // end if (count($list)>1 && 'export' == $do) 导出
			if ('export' == $do) {
				xlsEOF();
				exit();
			}

			$this->Tmpl['arr_total'] = $arr_total;
		}
		//构造应用一维数组
		//$month = array('00','01','02','03','04','05','06','07','08','09','10','11');
		$month =  array();
		$json_obj = $this->_get_json_obj();
		$this->Tmpl['select_column'] = $optionArray;
		$this->Tmpl['list'] = $list;
		$sys_list = array();
		$sys_list[0]['name'] = c('呼入');
		$sys_list[1]['name'] = c('通话次数');
		$sys_list[2]['name'] = c('IVR占用');
		$sys_list[3]['name'] = c('IVR应答');
		$sys_list[4]['name'] = c('座席占用');
		$sys_list[5]['name'] = c('座席应答');
		$sys_list[6]['name'] = c('呼出');
		$sys_list[7]['name'] = c('呼出成功');

		//默认按天查询
		if(!$_REQUEST['step']){
			$_REQUEST['step'] = 'day';
		}

		foreach ($list as $k=>$val)
		{
			//如果按照天查询，则截取五位以后的,如果为按小时查询，则不截取
			if('day' == $_REQUEST['step'])
			{
				$month[$k] = substr($val['total_date'], 5);
			}
			elseif('hour' == $_REQUEST['step'])
			{
				$month[$k] = $val['total_date'];
			}
			$sys_list[0]['data'][$k] = floatval($val['inbound_times']);
			$sys_list[1]['data'][$k] = floatval($val['inbound_conv_times']);
			$sys_list[2]['data'][$k] = floatval($val['inbound_ivr_occupation_times']);
			$sys_list[3]['data'][$k] = floatval($val['inbound_ivr_connected_times']);
			$sys_list[4]['data'][$k] = floatval($val['inbound_manual_occupation_times']);
			$sys_list[5]['data'][$k] = floatval($val['inbound_manual_connected_times']);
			$sys_list[6]['data'][$k] = floatval($val['outbound_times']);
			$sys_list[7]['data'][$k] = floatval($val['outbound_conv_times']);
		}

		$sys_list[1]['visible'] = false;
		$sys_list[2]['visible'] = false;
		$sys_list[3]['visible'] = false;
		$sys_list[4]['visible'] = false;
		$sys_list[7]['visible'] = false;

		//如果总记录数大于30条，则不显示X轴坐标的标记
		if(16 < $record_nums)
		{
			for($i = 0;$i<$record_nums;$i++) $month[$i] = '';
		}

		$this->Tmpl['month'] = $json_obj->encode($month);
		$this->Tmpl['sys_list'] = $json_obj->encode($sys_list);
		$this->display();
	}


	/**
     +----------------------------------------------------------
     * 排队等待时长
     * @author	: pengj
     * @date	: 2012/2/27
     +----------------------------------------------------------
     */
	function showQueueWaiting()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'queue_waiting_total', $arr_local_priv, '您没有查看排队等待时长报表的权限！' );

		//默认统计时间为30天
		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		if (!isset($_REQUEST['s_hour'])) $_REQUEST['s_hour'] = '00';
		if (!isset($_REQUEST['e_hour'])) $_REQUEST['e_hour'] = '23';
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获取列队
		$list_queue = $this->getQueueList();
		$this->Tmpl['list_queue'] = $list_queue;

		$list = array();

		if ('search' == $do) {

			$condition = " 1 ";
			if (!empty($fromdate)) {
				if (empty($s_hour)) $s_hour = '00';
				$fromdate .= ' ' . $s_hour;

				$condition .= " and total_date>='$fromdate'";
			}

			if (!empty($todate)) {
				if (empty($e_hour)) $e_hour = '59';
				$todate .= ' ' . $e_hour . ':59:59';

				$condition .= " and total_date<='$todate'";
			}

			if (!empty($queue)) $condition .= " and queue_id='$queue'";

			$condition .= " and is_day_stat = '0' ";

			//统计步长: 全部
			if ('' == $step) {
				$sql = "select queue_id, ";
				$sql .= "sum(wait_5s_counts) as wait_5s_counts, ";
				$sql .= "sum(wait_6_10s_counts) as wait_6_10s_counts, ";
				$sql .= "sum(wait_11_15s_counts) as wait_11_15s_counts, ";
				$sql .= "sum(wait_16_20s_counts) as wait_16_20s_counts, ";
				$sql .= "sum(wait_21_25s_counts) as wait_21_25s_counts, ";
				$sql .= "sum(wait_26_30s_counts) as wait_26_30s_counts, ";
				$sql .= "sum(wait_31_50s_counts) as wait_31_50s_counts, ";
				$sql .= "sum(wait_50s_counts) as wait_50s_counts ";

				$sql .= "from crm_inc_call_queue_traffic_total where " . $condition . " group by queue_id order by queue_id asc";
				$rs = $db->Execute($sql);
			}
			//按统计步长
			else {
				//if ('day' == $step) $key = "SUBSTRING(total_date, 1, 10)";
				//else if ('hour' == $step) $key = "SUBSTRING(total_date, 1, 13)";
				if ('day' == $step) $key = "date_format(total_date, '%Y-%m-%d')";
				else if ('hour' == $step) $key = "date_format(total_date, '%H')";

				$sql = "select $key as total_date, queue_id, count(0) from crm_inc_call_queue_traffic_total where " . $condition . " group by $key, queue_id";
				$sql = "select count(0) from ($sql) as n";
				$record_nums = $db->GetOne($sql);

				$pg = loadClass('tool','page',$this);
				$pg->setPageVar('p');
				$pg->setNumPerPage(30);

				$currentPage = $_REQUEST['p'];
				unset($_REQUEST['p']);
				unset($_REQUEST['action']);
				unset($_REQUEST['module']);

				$pg->setVar($_REQUEST);
				$pg->setVar(array("module"=>"report","action"=>"queueWaiting"));
				$pg->set($record_nums,$currentPage);
				$this->Tmpl['show_pages'] = $pg->output(1);

				$sql = "select $key as total_date, queue_id, ";
				$sql .= "sum(wait_5s_counts) as wait_5s_counts, ";
				$sql .= "sum(wait_6_10s_counts) as wait_6_10s_counts, ";
				$sql .= "sum(wait_11_15s_counts) as wait_11_15s_counts, ";
				$sql .= "sum(wait_16_20s_counts) as wait_16_20s_counts, ";
				$sql .= "sum(wait_21_25s_counts) as wait_21_25s_counts, ";
				$sql .= "sum(wait_26_30s_counts) as wait_26_30s_counts, ";
				$sql .= "sum(wait_31_50s_counts) as wait_31_50s_counts, ";
				$sql .= "sum(wait_50s_counts) as wait_50s_counts ";

				$sql .= "from crm_inc_call_queue_traffic_total where " . $condition . " group by $key, queue_id order by total_date asc, queue_id asc";

				if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
					echo $db->ErrorMsg();
				}
			}

			//记录(多)"行"数据合计
			$arr_total = array(
					'wait_5s_counts'		=> 0,
					'wait_6_10s_counts'		=> 0,
					'wait_11_15s_counts'	=> 0,
					'wait_16_20s_counts'	=> 0,
					'wait_21_25s_counts'	=> 0,
					'wait_26_30s_counts'	=> 0,
					'wait_31_50s_counts'	=> 0,
					'wait_50s_counts'		=> 0,
					'total'					=> 0
				);

			while (!$rs->EOF) {

				//记录"列"合计

				$rs->fields['total'] = $rs->fields['wait_5s_counts'] + $rs->fields['wait_6_10s_counts'] + $rs->fields['wait_11_15s_counts'] + $rs->fields['wait_16_20s_counts'] + $rs->fields['wait_21_25s_counts'] + $rs->fields['wait_26_30s_counts'] + $rs->fields['wait_31_50s_counts'] + $rs->fields['wait_50s_counts'];

				$arr_total['wait_5s_counts'] += $rs->fields['wait_5s_counts'];
				$arr_total['wait_6_10s_counts'] += $rs->fields['wait_6_10s_counts'];
				$arr_total['wait_11_15s_counts'] += $rs->fields['wait_11_15s_counts'];
				$arr_total['wait_16_20s_counts'] += $rs->fields['wait_16_20s_counts'];
				$arr_total['wait_21_25s_counts'] += $rs->fields['wait_21_25s_counts'];
				$arr_total['wait_26_30s_counts'] += $rs->fields['wait_26_30s_counts'];
				$arr_total['wait_31_50s_counts'] += $rs->fields['wait_31_50s_counts'];
				$arr_total['wait_50s_counts'] += $rs->fields['wait_50s_counts'];
				$arr_total['total'] += $rs->fields['total'];

				$list[] = $rs->fields;
				$rs->MoveNext();
			}
			$list = count($list) < 1 ? array() : $list;

			$this->Tmpl['arr_total'] = $arr_total;
		}
		$this->Tmpl['list'] = $list;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 排队等待时长报表导出
     * @author	: pengj
     * @date	: 2012/2/28
     +----------------------------------------------------------
     */
	function showQueueWaitingExport()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'queue_waiting_total', $arr_local_priv, '您没有查看排队等待时长报表的权限！' );

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		$condition = " 1 ";
		if (!empty($fromdate)) {
			if (empty($s_hour)) $s_hour = '00';
			$fromdate .= ' ' . $s_hour;

			$condition .= " and total_date>='$fromdate'";
		}

		if (!empty($todate)) {
			if (empty($e_hour)) $e_hour = '59';
			$todate .= ' ' . $e_hour . ':59:59';

			$condition .= " and total_date<='$todate'";
		}

		if (!empty($queue)) $condition .= " and queue_id='$queue'";

		//获取列队
		$list_queue = $this->getQueueList();
		$this->Tmpl['list_queue'] = $list_queue;

		//导出
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=queue_waiting.xls ");
		header("Content-Transfer-Encoding: binary ");

		xlsBOF();

		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		$xls_columns = array(
			'队列',
			'0 - 5s',
			'6 - 10s',
			'11 - 15s',
			'16 - 20s',
			'21 - 25s',
			'26 - 30s',
			'31 - 50s',
			'>50s',
			'合计'
		);

		//导出excel, 写表头
		$cols = 0;
		if ($step != '') xlsWriteLabel(1, $cols++, '时间段');

		foreach ($xls_columns as $value) xlsWriteLabel(1, $cols++, $value);

		$condition .= " and is_day_stat = '0' ";

		//统计步长: 全部
		if ('' == $step) {
			$sql = "select queue_id, ";
			$sql .= "sum(wait_5s_counts) as wait_5s_counts, ";
			$sql .= "sum(wait_6_10s_counts) as wait_6_10s_counts, ";
			$sql .= "sum(wait_11_15s_counts) as wait_11_15s_counts, ";
			$sql .= "sum(wait_16_20s_counts) as wait_16_20s_counts, ";
			$sql .= "sum(wait_21_25s_counts) as wait_21_25s_counts, ";
			$sql .= "sum(wait_26_30s_counts) as wait_26_30s_counts, ";
			$sql .= "sum(wait_31_50s_counts) as wait_31_50s_counts, ";
			$sql .= "sum(wait_50s_counts) as wait_50s_counts ";

			$sql .= "from crm_inc_call_queue_traffic_total where " . $condition . " group by queue_id order by queue_id asc";
			$rs = $db->Execute($sql);
		}
		//按统计步长
		else {
			//if ('day' == $step) $key = "SUBSTRING(total_date, 1, 10)";
			//else if ('hour' == $step) $key = "SUBSTRING(total_date, 1, 13)";
			if ('day' == $step) $key = "date_format(total_date, '%Y-%m-%d')";
			else if ('hour' == $step) $key = "date_format(total_date, '%H')";

			$sql = "select $key as total_date, queue_id, ";
			$sql .= "sum(wait_5s_counts) as wait_5s_counts, ";
			$sql .= "sum(wait_6_10s_counts) as wait_6_10s_counts, ";
			$sql .= "sum(wait_11_15s_counts) as wait_11_15s_counts, ";
			$sql .= "sum(wait_16_20s_counts) as wait_16_20s_counts, ";
			$sql .= "sum(wait_21_25s_counts) as wait_21_25s_counts, ";
			$sql .= "sum(wait_26_30s_counts) as wait_26_30s_counts, ";
			$sql .= "sum(wait_31_50s_counts) as wait_31_50s_counts, ";
			$sql .= "sum(wait_50s_counts) as wait_50s_counts ";

			$sql .= "from crm_inc_call_queue_traffic_total where " . $condition . " group by $key, queue_id order by total_date asc, queue_id asc";

			$rs = $db->Execute($sql);
		}

		//记录(多)"行"数据合计
		$arr_total = array(
				'wait_5s_counts'		=> 0,
				'wait_6_10s_counts'		=> 0,
				'wait_11_15s_counts'	=> 0,
				'wait_16_20s_counts'	=> 0,
				'wait_21_25s_counts'	=> 0,
				'wait_26_30s_counts'	=> 0,
				'wait_31_50s_counts'	=> 0,
				'wait_50s_counts'		=> 0,
				'total'					=> 0
			);

		$rows = 2;
		while (!$rs->EOF) {
			$cols = 0;

			//记录"列"合计
			$rs->fields['total'] = $rs->fields['wait_5s_counts'] + $rs->fields['wait_6_10s_counts'] + $rs->fields['wait_11_15s_counts'] + $rs->fields['wait_16_20s_counts'] + $rs->fields['wait_21_25s_counts'] + $rs->fields['wait_26_30s_counts'] + $rs->fields['wait_31_50s_counts'] + $rs->fields['wait_50s_counts'];

			$arr_total['wait_5s_counts'] += $rs->fields['wait_5s_counts'];
			$arr_total['wait_6_10s_counts'] += $rs->fields['wait_6_10s_counts'];
			$arr_total['wait_11_15s_counts'] += $rs->fields['wait_11_15s_counts'];
			$arr_total['wait_16_20s_counts'] += $rs->fields['wait_16_20s_counts'];
			$arr_total['wait_21_25s_counts'] += $rs->fields['wait_21_25s_counts'];
			$arr_total['wait_26_30s_counts'] += $rs->fields['wait_26_30s_counts'];
			$arr_total['wait_31_50s_counts'] += $rs->fields['wait_31_50s_counts'];
			$arr_total['wait_50s_counts'] += $rs->fields['wait_50s_counts'];
			$arr_total['total'] += $rs->fields['total'];

			if ($step != '') xlsWriteLabel($rows, $cols++, $rs->fields['total_date']);

			$queue_name = $list_queue[$rs->fields['queue_id']]['desc'] . ' (' . $rs->fields['queue_id'] . ')';
			$queue_name = iconv("UTF-8", "GB2312//IGNORE", $queue_name);
			xlsWriteLabel($rows, $cols++, $queue_name);

			xlsWriteNumber($rows, $cols++, $rs->fields['wait_5s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_6_10s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_11_15s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_16_20s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_21_25s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_26_30s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_31_50s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['wait_50s_counts']);
			xlsWriteNumber($rows, $cols++, $rs->fields['total']);

			$rows ++;
			$rs->MoveNext();
		}

		//在excel导出的记录最末追加一行写入合计
		if ($rows > 3) {
			$cols = 0;
			if ($step != '') $cols = 1;
			xlsWriteLabel($rows, $cols++, iconv("UTF-8", "GB2312//IGNORE", c('合计')));
			xlsWriteNumber($rows, $cols++, $arr_total['wait_5s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_6_10s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_11_15s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_16_20s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_21_25s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_26_30s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_31_50s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['wait_50s_counts']);
			xlsWriteNumber($rows, $cols++, $arr_total['total']);
		}

		xlsEOF();
		exit;
	}

	/**
     +----------------------------------------------------------
     * 队列统计报表
     * @author	: pengj
     * @date	: 2012/3/3
     +----------------------------------------------------------
     */
	function showQueueTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'queue_total', $arr_local_priv, '您没有查看队列统计报表的权限！' );

		//默认统计时间为30天
		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		if (!isset($_REQUEST['s_hour'])) $_REQUEST['s_hour'] = '00';
		if (!isset($_REQUEST['e_hour'])) $_REQUEST['e_hour'] = '23';
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获取列队
		$list_queue = $this->getQueueList();
		$this->Tmpl['list_queue'] = $list_queue;

		$list = array();

		if ('' != $do) {

			$condition = " 1 ";
			if (!empty($fromdate)) {
				if (empty($s_hour)) $s_hour = '00';
				$fromdate .= ' ' . $s_hour;

				$condition .= " and total_date>='$fromdate'";
			}

			if (!empty($todate)) {
				if (empty($e_hour)) $e_hour = '59';
				$todate .= ' ' . $e_hour . ':59:59';

				$condition .= " and total_date<='$todate'";
			}

			if (!empty($queue)) $condition .= " and queue_id='$queue'";

			$condition .= " and is_day_stat = '0' ";

			//统计步长: 全部
			if ('' == $step) {
				$sql = "select queue_id, ";
				$sql .= "sum(conv_duration) as conv_duration, ";
				$sql .= "sum(conv_times) as conv_times, ";
				$sql .= "sum(hold_times) as hold_times, ";
				$sql .= "sum(three_call_times) as three_call_times, ";
				$sql .= "sum(trans_inner_times) as trans_inner_times, ";
				//$sql .= "sum(trans_ivr_times) as trans_ivr_times, ";
				$sql .= "sum(internal_help_times) as internal_help_times, ";
				$sql .= "max(max_conv_duration) as max_conv_duration, ";
				$sql .= "min(case when min_conv_duration>0 then min_conv_duration end) as min_conv_duration, ";
				$sql .= "sum(occupation_times) as occupation_times, ";
				$sql .= "sum(wait_ans_sec) as wait_ans_sec, ";
				$sql .= "sum(non_ans_times) as non_ans_times, ";
				$sql .= "sum(wait_ans_times) as wait_ans_times ";

				$sql .= "from crm_inc_call_queue_traffic_total where " . $condition . " group by queue_id order by queue_id asc";
				$rs = $db->Execute($sql);
			}
			//按统计步长
			else {
				//if ('day' == $step) $key = "SUBSTRING(total_date, 1, 10)";
				//else if ('hour' == $step) $key = "SUBSTRING(total_date, 1, 13)";
				if ('day' == $step) $key = "date_format(total_date, '%Y-%m-%d')";
				else if ('hour' == $step) $key = "date_format(total_date, '%H')";
				else if ('month' == $step) $key = "date_format(total_date, '%Y-%m')";
				else if ('week' == $step) $key =  " WEEK(total_date,1) ";

				$sql = "select $key as total_date, queue_id, count(0) from crm_inc_call_queue_traffic_total where " . $condition . " group by $key, queue_id";
				$sql = "select count(0) from ($sql) as n";
				$record_nums = $db->GetOne($sql);
                $pagesnum = isset($_REQUEST['pagenum']) ? $_REQUEST['pagenum'] : '';
                $pagesnum = filterPageNum($pagesnum);//过滤页码
                $this->Tmpl['pagenum'] = $pagesnum;
        
				$pg = loadClass('tool','page',$this);
				$pg->setPageVar('p');
				$pg->setNumPerPage($pagesnum);

				$currentPage = $_REQUEST['p'];
				unset($_REQUEST['p']);
				unset($_REQUEST['action']);
				unset($_REQUEST['module']);

				$pg->setVar($_REQUEST);
				$pg->setVar(array("module"=>"report","action"=>"queueTotal"));
				$pg->set($record_nums,$currentPage);
				$this->Tmpl['show_pages'] = $pg->output(1);

				$sql = "select $key as total_date,date_format(total_date, '%Y') AS year, queue_id, ";
				$sql .= "sum(conv_duration) as conv_duration, ";
				$sql .= "sum(conv_times) as conv_times, ";
				$sql .= "sum(hold_times) as hold_times, ";
				$sql .= "sum(three_call_times) as three_call_times, ";
				$sql .= "sum(trans_inner_times) as trans_inner_times, ";
				//$sql .= "sum(trans_ivr_times) as trans_ivr_times, ";
				$sql .= "sum(internal_help_times) as internal_help_times, ";
				$sql .= "max(max_conv_duration) as max_conv_duration, ";
				$sql .= "min(case when min_conv_duration>0 then min_conv_duration end) as min_conv_duration, ";
				$sql .= "sum(occupation_times) as occupation_times, ";
				$sql .= "sum(wait_ans_sec) as wait_ans_sec, ";
				$sql .= "sum(non_ans_times) as non_ans_times, ";
				$sql .= "sum(wait_ans_times) as wait_ans_times ";

				$sql .= "from crm_inc_call_queue_traffic_total where " . $condition . " group by $key, queue_id order by total_date asc, queue_id asc";

				if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
					echo $db->ErrorMsg();
				}
			}
		
			if ('export' == $do) {
				$content_arr = array(
					'step'			=>  $step,
					'fromdate'		=>  $fromdate,
					'todate'		=>  $todate,
					'list_queue'    =>  $list_queue
				);
				$this->exportXlsQueueTotal($db,$sql,$content_arr);
			}

			//初始化合计数组
			$arr_total = array(
					'conv_duration'			=> 0,
					'conv_avg_duration'		=> 0,
					'conv_times'			=> 0,
					'hold_times'			=> 0,
					'three_call_times'		=> 0,
					'trans_inner_times'		=> 0,
					//'trans_ivr_times'		=> 0,
					'internal_help_times'	=> 0,
					'max_conv_duration'		=> 0,
					'min_conv_duration'		=> 0,
					'call_loss_times'		=> 0,
					'call_loss_rate'		=> 0,
					'occupation_times'		=> 0,
					'conv_rate'				=> 0,
					'wait_ans_sec'			=> 0,
					'wait_ans_avg'			=> 0,
					'non_ans_times'			=> 0,
					'wait_ans_times'		=> 0
				);

			$i = 1;
			$rows = 2;
			while (!$rs->EOF) {
				$cols = 0;

				//根据公式计算相应值
				@$rs->fields['conv_avg_duration'] = $rs->fields['conv_duration'] / $rs->fields['conv_times']; //通话均长
				@$rs->fields['conv_rate'] = $rs->fields['conv_times'] / $rs->fields['occupation_times']; //通话率
				$rs->fields['call_loss_times'] = $rs->fields['occupation_times'] - $rs->fields['conv_times']; //呼损数
				@$rs->fields['call_loss_rate'] = $rs->fields['call_loss_times'] / $rs->fields['occupation_times']; //呼损率
				@$rs->fields['wait_ans_avg'] = $rs->fields['wait_ans_sec'] / $rs->fields['wait_ans_times']; //等待应答均长

				$rs->fields['conv_avg_duration'] = round($rs->fields['conv_avg_duration'], 2);
				$rs->fields['conv_rate'] = round($rs->fields['conv_rate'], 4);
				$rs->fields['call_loss_rate'] = round($rs->fields['call_loss_rate'], 4);
				$rs->fields['wait_ans_avg'] = round($rs->fields['wait_ans_avg'], 2);
				// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
				if($step == 'week'){
					$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['total_date']);
					$rs->fields['total_date'] = $week_time['start'].c('至').$week_time['end'];
				}

				//合计数组(累加)
				$arr_total['conv_duration'] += $rs->fields['conv_duration'];
				$arr_total['conv_times'] += $rs->fields['conv_times'];
				$arr_total['hold_times'] += $rs->fields['hold_times'];
				$arr_total['three_call_times'] += $rs->fields['three_call_times'];
				$arr_total['trans_inner_times'] += $rs->fields['trans_inner_times'];
				//$arr_total['trans_ivr_times'] += $rs->fields['trans_ivr_times'];
				$arr_total['internal_help_times'] += $rs->fields['internal_help_times'];
				$arr_total['call_loss_times'] += $rs->fields['call_loss_times'];
				$arr_total['occupation_times'] += $rs->fields['occupation_times'];
				$arr_total['wait_ans_sec'] += $rs->fields['wait_ans_sec'];
				$arr_total['non_ans_times'] += $rs->fields['non_ans_times'];
				$arr_total['wait_ans_times'] += $rs->fields['wait_ans_times'];

				//合计数组(取平均值)
//				if ($i > 2) $i = 2;
//				$arr_total['conv_avg_duration'] = ($arr_total['conv_avg_duration'] + $rs->fields['conv_avg_duration']) / $i; //通话均长
//				$arr_total['conv_rate'] = ($arr_total['conv_rate'] + $rs->fields['conv_rate']) / $i; //通话率
//				$arr_total['wait_ans_avg'] = ($arr_total['wait_ans_avg'] + $rs->fields['wait_ans_avg']) / $i; //等待应答均长
//				$arr_total['call_loss_rate'] = ($arr_total['call_loss_rate'] + $rs->fields['call_loss_rate']) / $i; //呼损率

				//合计数组(取最大值)
				$arr_total['max_conv_duration'] = $arr_total['max_conv_duration'] < $rs->fields['max_conv_duration'] ? $rs->fields['max_conv_duration'] : $arr_total['max_conv_duration'];

				//合计数组(取最小值)
				if ($rs->fields['min_conv_duration'] > 0) {
					if (0 === $arr_total['min_conv_duration'])
						$arr_total['min_conv_duration'] = $rs->fields['min_conv_duration'];
					else
						$arr_total['min_conv_duration'] = $arr_total['min_conv_duration'] > $rs->fields['min_conv_duration'] ? $rs->fields['min_conv_duration'] : $arr_total['min_conv_duration'];
				}

				//时间转换
				@$rs->fields['conv_duration'] = $this->SecondsToTime($rs->fields['conv_duration']); ;			//通话时长
				@$rs->fields['max_conv_duration'] = $this->SecondsToTime($rs->fields['max_conv_duration']);	//最长通话时长

				$list[] = $rs->fields;
				$i ++;
				$rows ++;
				$rs->MoveNext();
			}
			$list = count($list) < 1 ? array() : $list;

			@$arr_total['conv_avg_duration'] = round($arr_total['conv_duration'] / $arr_total['conv_times'], 2); //通话均长
			@$arr_total['conv_rate'] = round($arr_total['conv_times'] / $arr_total['occupation_times'], 4); //通话率
			@$arr_total['call_loss_rate'] = round($arr_total['call_loss_times'] / $arr_total['occupation_times'], 4); //呼损率
			@$arr_total['wait_ans_avg'] = round($arr_total['wait_ans_sec'] / $arr_total['wait_ans_times'], 2); //等待应答均长

			//时间转换
			@$arr_total['conv_duration'] = $this->SecondsToTime($arr_total['conv_duration']); ;		//通话时长
			@$arr_total['max_conv_duration'] = $this->SecondsToTime($arr_total['max_conv_duration']);	//最长通话时长

			$this->Tmpl['arr_total'] = $arr_total;
		}


		$this->Tmpl['list'] = $list;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 导出队列统计报表为xls
     * @author	: zhuangl
     * @date	: 2014/10/13
     +----------------------------------------------------------
     */
	 function exportXlsQueueTotal($db,$sql,$content_arr){
		global $db;
		//if (!$db) { $db = $this->loadDB();}
		$rs = $db->Execute($sql);

		if (!$rs) {
			goback(c('导出失败,请先查询后重试'),"");
		}

		//导出表头
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=queue_total.xls ");
		header("Content-Transfer-Encoding: binary ");

		xlsBOF();

		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		$xls_columns = array(
			'total_date'			=> '时间段',
			'queue_name'			=> '队列',
			'occupation_times'		=> '占用数',
			'conv_times'			=> '通话次数',
			'conv_rate'				=> '通话率',
			'conv_duration'			=> '通话时长',
			'conv_avg_duration'		=> '通话均长',
			'hold_times'			=> '保持次数',
			'three_call_times'		=> '三方通话数',
			//'trans_inner_times'		=> '内部转移数',
			//'trans_ivr_times'		=> '转ivr次数',
			//'internal_help_times'	=> '内部求助数',
			'max_conv_duration'		=> '最长通话时长',
			'min_conv_duration'		=> '最短通话时长',
			'call_loss_times'		=> '呼损数',
			'call_loss_rate'		=> '呼损率',
			'wait_ans_sec'			=> '等待应答时长',
			//'wait_ans_times'		=> '等待应答数',
			'wait_ans_avg'			=> '等待应答均长',
			//'non_ans_times'			=> '无应答数'
		);

		$cols = 0;
		foreach ($xls_columns as $value) 
		{ 
			xlsWriteLabel(1, $cols++, $value);
		}
		

		$i = 1;
		$rows = 2;
		while (!$rs->EOF) {
			$cols = 0;

			//根据公式计算相应值
			@$rs->fields['conv_avg_duration'] = $rs->fields['conv_duration'] / $rs->fields['conv_times']; //通话均长
			@$rs->fields['conv_rate'] = $rs->fields['conv_times'] / $rs->fields['occupation_times']; //通话率
			$rs->fields['call_loss_times'] = $rs->fields['occupation_times'] - $rs->fields['conv_times']; //呼损数
			@$rs->fields['call_loss_rate'] = $rs->fields['call_loss_times'] / $rs->fields['occupation_times']; //呼损率
			@$rs->fields['wait_ans_avg'] = $rs->fields['wait_ans_sec'] / $rs->fields['wait_ans_times']; //等待应答均长

			$rs->fields['conv_avg_duration'] = round($rs->fields['conv_avg_duration'], 2);
			$rs->fields['conv_rate'] = round($rs->fields['conv_rate'], 4);
			$rs->fields['call_loss_rate'] = round($rs->fields['call_loss_rate'], 4);
			$rs->fields['wait_ans_avg'] = round($rs->fields['wait_ans_avg'], 2);
			// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
			if($content_arr['step'] == 'week'){
				$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['total_date']);
				$rs->fields['total_date'] = $week_time['start'].'至'.$week_time['end'];
			}

			//合计数组(累加)
			$arr_total['conv_duration'] += $rs->fields['conv_duration'];
			$arr_total['conv_times'] += $rs->fields['conv_times'];
			$arr_total['hold_times'] += $rs->fields['hold_times'];
			$arr_total['three_call_times'] += $rs->fields['three_call_times'];
			$arr_total['trans_inner_times'] += $rs->fields['trans_inner_times'];
			//$arr_total['trans_ivr_times'] += $rs->fields['trans_ivr_times'];
			$arr_total['internal_help_times'] += $rs->fields['internal_help_times'];
			$arr_total['call_loss_times'] += $rs->fields['call_loss_times'];
			$arr_total['occupation_times'] += $rs->fields['occupation_times'];
			$arr_total['wait_ans_sec'] += $rs->fields['wait_ans_sec'];
			$arr_total['non_ans_times'] += $rs->fields['non_ans_times'];
			$arr_total['wait_ans_times'] += $rs->fields['wait_ans_times'];


			//合计数组(取最大值)
			$arr_total['max_conv_duration'] = $arr_total['max_conv_duration'] < $rs->fields['max_conv_duration'] ? $rs->fields['max_conv_duration'] : $arr_total['max_conv_duration'];

			//合计数组(取最小值)
			if ($rs->fields['min_conv_duration'] > 0) {
				if (0 === $arr_total['min_conv_duration'])
					$arr_total['min_conv_duration'] = $rs->fields['min_conv_duration'];
				else
					$arr_total['min_conv_duration'] = $arr_total['min_conv_duration'] > $rs->fields['min_conv_duration'] ? $rs->fields['min_conv_duration'] : $arr_total['min_conv_duration'];
			}

			//时间转换
			@$rs->fields['conv_duration'] = $this->SecondsToTime($rs->fields['conv_duration']); ;			//通话时长
			@$rs->fields['max_conv_duration'] = $this->SecondsToTime($rs->fields['max_conv_duration']);	//最长通话时长

			if ($content_arr['step'] != '') 
			{
				$rs->fields['total_date'] = $rs->fields['total_date'];
			} else {
				$rs->fields['total_date'] = $content_arr['fromdate'] . ' 至 ' . $content_arr['todate'];
			}

			$queue_name = $content_arr['list_queue'][$rs->fields['queue_id']]['desc'] . ' (' . $rs->fields['queue_id'] . ')';
			$queue_name = iconv("UTF-8", "GB2312//IGNORE", $queue_name);
			$rs->fields['queue_name'] = $queue_name;

			foreach ($xls_columns as $key => $value)
			{
				if (strstr($key, '_times') || strstr($key, '_sec') || strstr($key, '_duration')) {
				xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
				} else {
					xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
				}
			}
			$list[] = $rs->fields;
			$i ++;
			$rows ++;
			$rs->MoveNext();
		}

		@$arr_total['conv_avg_duration'] = round($arr_total['conv_duration'] / $arr_total['conv_times'], 2); //通话均长
		@$arr_total['conv_rate'] = round($arr_total['conv_times'] / $arr_total['occupation_times'], 4); //通话率
		@$arr_total['call_loss_rate'] = round($arr_total['call_loss_times'] / $arr_total['occupation_times'], 4); //呼损率
		@$arr_total['wait_ans_avg'] = round($arr_total['wait_ans_sec'] / $arr_total['wait_ans_times'], 2); //等待应答均长

		//时间转换
		@$arr_total['conv_duration'] = $this->SecondsToTime($arr_total['conv_duration']); ;		//通话时长
		@$arr_total['max_conv_duration'] = $this->SecondsToTime($arr_total['max_conv_duration']);	//最长通话时长

		//在excel导出的记录最末追加一行写入合计
		xlsWriteLabel ($rows, 0, '合计');
		$cols = 0;
		foreach ($xls_columns as $key => $value)
		{
			if (strstr($key, '_times') || strstr($key, '_sec') || strstr($key, '_duration') || strstr($key, '_rate')) {
				xlsWriteLabel($rows, $cols++, $arr_total[$key]);
			} else {
				xlsWriteLabel($rows, $cols++, $arr_total[$key]);
			}
		}

		xlsEOF();
		exit;
	 }

	/**
     +----------------------------------------------------------
     * 质检日志查看
     * @author	: pengj
     * @date	: 2012/3/5
     +----------------------------------------------------------
     */
	function showFeedbackList()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'feedback_list', $arr_local_priv, '您没有查看质检日志的权限！' );

		//加密处理
		$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );
		$this->Tmpl['flag_hidden'] = $flag_hidden;

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		// 质检结果
		$quality_result = array(
			0=>array('quality_score'=>5,'quality_name'=>c('非常满意')),
			1=>array('quality_score'=>3,'quality_name'=>c('满意')),
			2=>array('quality_score'=>2,'quality_name'=>c('一般')),
			3=>array('quality_score'=>0,'quality_name'=>c('不满意')),
		);
		$this->Tmpl['quality_result'] = $quality_result;
		
		// 呼叫类型
		$callType = array(0=>array('call_type'=> 4,'call_type_name' => c('全部')),1=>array('call_type'=> 1,'call_type_name' => c('呼入')),2=>array('call_type'=> 2,'call_type_name' => c('呼出')));
		$this->Tmpl['callType'] = $callType;
		
		$list = array();

		if ('' != $do) {
			//
			//非admin管理员, 获取其所能管理的部门及座席
			//
			$arr_exten = array();
			$list_exten = "";
			$arr_deptid = array();
			$list_deptid = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);

				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}

			//
			//根据起止时间组合查询条件
			//
			$condition = " 1 ";
			if (!empty($fromdate)) {
				$condition .= " and calltime>='$fromdate'";
			}

			if (!empty($todate)) {
				$todate .= ' 23:59:59';
				$condition .= " and calltime<='$todate'";
			}

			//
			//根据分数范围查询
			//
			if ($s_score != '') {
				$condition .= " and score>='$s_score'";
			}

			if ($e_score != '') {
				$condition .= " and score<='$e_score'";
			}
			
			// 按呼叫类型查找
			if(!empty($type) && $type !=4){
				$condition .= " AND type=$type";
			}
		
		
			// 按按质检结果查找
			if(!empty($inspection_result) || $inspection_result == '0'){
				$condition .= " AND score='$inspection_result'";
			}
			

			//按部门查找
			if (!empty($depart_id)) {

				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";	//加上所选部门

				//获取所选部门的座席列表
				$extenSelect = array();
				$rs	= $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while(!$rs->EOF){
					$extenSelect[] = $rs->fields;
					if ($rs->fields['extension']) {
						$extensionList[] = $rs->fields['extension'];
					}
					$rs->MoveNext();
				}

				if (empty($extension)) {
					if(count($extensionList)>0){
						$extension_strs	= implode(",", $extensionList);
						$condition	.= " and agent in ($extension_strs)";
					} else {
						$condition	.= " and agent in (0)";
					}
				}

				$this->Tmpl['extenSelect']	= $extenSelect;
			}

			//
			//非管理员, 根据所能管理的部门或座席组合限定条件
			//
			if (1 != $_SESSION['userinfo']['power']) $condition .= " and agent in ($list_exten)";

			//座席工号查找
			if (!empty($extension)) $condition	.= " and agent='$extension' ";

			$sql_count = "select count(0) from ss_cdr_feedback where " . $condition; //统计数量
			$record_nums = $db->GetOne($sql_count);

			$sql = "select * from ss_cdr_feedback where " . $condition . " order by calltime desc";

			if ('export' == $do) {
				$this->feedbackListExport($sql);
				exit();
			}

			$this->Tmpl['record_nums'] = $record_nums;

			$pg = loadClass('tool','page',$this);
			$pg->setPageVar('p');
			$pg->setNumPerPage( 20 );

			$currentPage = $_REQUEST['p'];
			unset($_REQUEST['p']);
			unset($_REQUEST['action']);
			unset($_REQUEST['module']);
			unset($_REQUEST['cfg_traffic_header']);

			$pg->setVar($_REQUEST);
			$pg->setVar(array("module"=>"report","action"=>"feedbackList"));
			$pg->set($record_nums,$currentPage);
			$this->Tmpl['show_pages'] = $pg->output(1);
			//echo $sql;
			if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
				echo $db->ErrorMsg();
				exit();
			}

			while (!$rs->EOF) {

				if (!empty($rs->fields['agent'])) {
					$u = $this->getUserByExten($rs->fields['agent']);
					$rs->fields['user_name'] = $u['user_name'];
					$rs->fields['dept_name'] = $u['dept_name'];
				}
				
				// 用户满意度，5分：非常满意；3分：满意；2分：一般；0分：不满意
				if(is_numeric($rs->fields['score'])){
					switch($rs->fields['score']){
						case 5	:
							$rs->fields['re_quality'] = c('非常满意');
							break;			
						case 3	:
							$rs->fields['re_quality'] = c('满意');
							break;						
						case 2	:
							$rs->fields['re_quality'] = c('一般');
							break;							
						case 0	:
							$rs->fields['re_quality'] = c('不满意');
							break;						
					}
				}
				
				//质检报表号码隐藏
				if (1 == $flag_hidden && !empty($rs->fields['callerid']) && strlen($rs->fields['callerid'])>6){
					$rs->fields['callerid'] = transferPhone($rs->fields['callerid']);
				}

				$rs->fields['filename'] = $this->getRecordFile($rs->fields['uniqueid']);

				$list[] = $rs->fields;
				$rs->MoveNext();
			} // end while (!$rs->EOF)

		} // if ('' != $do)


		$this->Tmpl['list'] = $list;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 导出质检日志
     * @author	: pengj
     * @date	: 2012/3/5
     +----------------------------------------------------------
     */
	function feedbackListExport($sql)
	{
		//导出
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=feedback_list.xls ");
		header("Content-Transfer-Encoding: binary ");

		xlsBOF();

		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		$xls_columns = array(
			'calltime'		=> '质检时间',
			'callerid'		=> '外线',
			'user_name'		=> '座席',
			'dept_name'		=> '部门',
			're_quality'	=> '质检结果',
			'score'			=> '评分',
			'remarks'		=> '备注'
		);

		//导出excel, 写表头
		$cols = 0;
		foreach ($xls_columns as $value) xlsWriteLabel(1, $cols++, $value);

		global $db;
		$rs = $db->Execute($sql);

		$rows = 2;
		while (!$rs->EOF) {

			$cols = 0;

			if (!empty($rs->fields['agent'])) {
				$u = $this->getUserByExten($rs->fields['agent']);
				$user_name = iconv('UTF-8', 'GB2312//IGNORE', $u['user_name']) . '(' . $rs->fields['agent'] . ')';
				$rs->fields['user_name'] = $user_name;
				$rs->fields['dept_name'] = iconv('UTF-8', 'GB2312//IGNORE', $u['dept_name']);
			}

			// 用户满意度，5分：非常满意；3分：满意；2分：一般；0分：不满意
			if(is_numeric($rs->fields['score'])){
				switch($rs->fields['score']){
					case 5	:
						$rs->fields['re_quality'] = '非常满意';
						break;			
					case 3	:
						$rs->fields['re_quality'] = '满意';
						break;						
					case 2	:
						$rs->fields['re_quality'] = '一般';
						break;							
					case 0	:
						$rs->fields['re_quality'] = '不满意';
						break;						
				}
			}
			
			foreach ($xls_columns as $key => $value)
			{
				//if (strstr($key, 'score')) {
				//	xlsWriteNumber($rows, $cols++, $rs->fields[$key]);
				//}
				//else {
				xlsWriteLabel($rows, $cols++, $rs->fields[$key]);
				//}
			}

			$rows ++;
			$rs->MoveNext();
		} // end while (!$rs->EOF)

		xlsEOF();
		exit;
	}


	/**
     +----------------------------------------------------------
     * 质检统计
     * @author	: pengj
	 * @modify	: modifid by daicr 2017/11/14
     * @date	: 2012/3/5
     +----------------------------------------------------------
     */
	function showFeedbackTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'feedback_total', $arr_local_priv, '您没有查看质检统计报表的权限！' );

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';
		
		# 统计维度
		if ('by_seats' == $_REQUEST['statistical_dimension'] || empty($_REQUEST['statistical_dimension'])) {
			$_REQUEST['statistical_dimension'] = 'by_seats';
		} else {
			$_REQUEST['statistical_dimension'] = 'by_department';
		}	
		
		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);
		
		// 限制查询日期不能超过 3 个月
		$timestr=strtotime($_REQUEST['todate'])-strtotime($_REQUEST['fromdate']);
		if($timestr>93*24*60*60){
			goBack(c('查询的日期不能超过3个月.'));
		}
		
		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;
		
		// 呼叫类型
		$callType = array(0=>array('call_type'=> 4,'call_type_name' => c('全部')),1=>array('call_type'=> 1,'call_type_name' => c('呼入')),2=>array('call_type'=> 2,'call_type_name' => c('呼出')));
		$this->Tmpl['callType'] = $callType;
		
		$list = array();

		if ('' != $do) {
			//
			//非admin管理员, 获取其所能管理的部门及座席
			//
			$arr_exten = array();
			$list_exten = "";
			$arr_deptid = array();
			$list_deptid = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				if (count($arr_deptid) == 0) $arr_deptid[] = 0;
				$list_deptid = implode(',', $arr_deptid);

				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}

			//
			//根据起止时间组合查询条件
			//
			$condition = " 1 ";
			if (!empty($fromdate)) {
				$condition .= " and calltime>='$fromdate'";
			}

			if (!empty($todate)) {
				$todate .= ' 23:59:59';
				$condition .= " and calltime<='$todate'";
			}
				
			// 按呼叫类型查找
			if (!empty($call_type) && $call_type !=4) {
				$condition .= " and type=$call_type";
			}
			
			//按部门查找
			if (!empty($depart_id)) {

				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";	//加上所选部门

				//获取所选部门的座席列表
				$extenSelect = array();
				$rs	= $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while(!$rs->EOF){
					$extenSelect[] = $rs->fields;
					if ($rs->fields['extension']) {
						$extensionList[] = $rs->fields['extension'];
					}
					$rs->MoveNext();
				}

				if (empty($extension) && $statistical_dimension == 'by_seats') {
					if(count($extensionList)>0){
						$extension_strs	= implode(",", $extensionList);
						$condition	.= " and agent in ($extension_strs)";
					} else {
						$condition	.= " and agent in (0)";
					}
				}

				$this->Tmpl['extenSelect']	= $extenSelect;
			}

			//
			//非管理员, 根据所能管理的部门或座席组合限定条件
			//
			if($statistical_dimension == 'by_seats'){
				if (1 != $_SESSION['userinfo']['power']) $condition .= " and agent in ($list_exten)";
			}
			
			//座席工号查找
			if (!empty($extension)) $condition	.= " and agent='$extension' ";
			
			//初始化合计数组
			$arr_total = array(
					'number_of_calls'			=> 0,  //通话次数
					'transfer_times'			=> 0,  //转接次数
					'num'						=> 0,  //质检数量
					'v_num'						=> 0,  //有效质检数量
					'quality_proportion'		=> 0,  //质检比例
					'valid_quality_proportion'	=> 0,  //有效质检比例
					'very_satisfied'			=> 0,  //非常满意数量
					'satisfied'					=> 0,  //满意数量
					'commonly'					=> 0,  //一般
					'unsatisfied'				=> 0,  //不满意
					'score'						=> 0,  //质检总评分
					'avg_score'					=> 0,  //质检平均评分
					'v_score'					=> 0,  //有效质检评分
					'avg_v_score'				=> 0   //有效平均评分
				);

			# 统计步长
			if ('all' == $step || empty($step)) {
				$_REQUEST['step'] = 'all';
			} else if ('day' == $step) {
				$_REQUEST['step'] = 'day';
				$time = " SUBSTRING(calltime, 1, 10) ";
			} else if ('hour' == $step) {
				$time = " SUBSTRING(calltime, 12, 2) ";
			} else if ('week' == $step) {
				$time = " WEEK(calltime,1) ";
			} else if ('month' == $step) {
				$time = " SUBSTRING(calltime, 1, 7) ";
			}
			
			//设置有效分数
			if ($s_score != '') {
				$score_list = "score>=$s_score";
			}

			if ($e_score != '') {
				$score_list .= " and score<='$e_score'";
			}

			if (empty($score_list)) {
				$score_list = "score<>''";
			}
			
			/************************* 按坐席查询 start *********************************/
			if($statistical_dimension == 'by_seats'){

				if($step == 'all' || empty($step)){
					$sql = "select agent, count(0) as num, sum(score) as score, sum(if($score_list,1,0)) as v_num, sum(if($score_list,score,0)) as v_score,	sum(IF(score = 5,1,0)) AS very_satisfied,sum(IF(score = 3,1,0)) AS satisfied,sum(IF(score = 2,1,0)) AS commonly,sum(IF(score = 0,1,0)) AS unsatisfied from ss_cdr_feedback where " . $condition . " group by agent order by agent asc";
				}else{
					$sql = "select $time AS time,date_format(`calltime`,'%Y') AS `year`, agent, count(0) as num, sum(score) as score, sum(if($score_list,1,0)) as v_num, sum(if($score_list,score,0)) as v_score,sum(IF(score = 5,1,0)) AS very_satisfied,sum(IF(score = 3,1,0)) AS satisfied,sum(IF(score = 2,1,0)) AS commonly,sum(IF(score = 0,1,0)) AS unsatisfied from ss_cdr_feedback where " . $condition . " group by agent order by time DESC, agent asc";
				}
			
				if ('search' == $_REQUEST['do']) {
					//获取总记录数(用于分页)
					$sql_page_num = "SELECT COUNT(*) FROM ( " .$sql ." ) AS t1";
					$record_nums = $db->GetOne($sql_page_num);
					$this->Tmpl['record_nums'] = $record_nums;

					$pg = loadClass('tool','page',$this);
					$pg->setPageVar('p');
					$pg->setNumPerPage( 20 );

					$currentPage = $_REQUEST['p'];
					unset($_REQUEST['p']);
					unset($_REQUEST['action']);
					unset($_REQUEST['module']);
					unset($_REQUEST['cfg_traffic_header']);

					$pg->setVar($_REQUEST);
					$pg->setVar(array("module"=>"report","action"=>"feedbackTotal"));
					$pg->set($record_nums,$currentPage);
					$this->Tmpl['show_pages'] = $pg->output(1);
				}
				
				if ('search' == $do) {
					//echo $sql;
					if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
						echo $db->ErrorMsg();
						exit();
					}
				}
				else {
					$rs = $db->Execute($sql);
				}

				$arrExten = $db->GetAll("select dept_id,extension,user_name from org_user");
				$arrDept = $db->GetAll("select dept_id,dept_name from org_department");
				
				while (!$rs->EOF) {

					if (!empty($rs->fields['agent'])) {
						foreach($arrExten as $k => $v){
							if($rs->fields['agent'] == $v['extension']){
								$rs->fields['user_name'] = $v['user_name'];
								$deptId = $v['dept_id'];
							}
						}
						foreach($arrDept as $k => $v){
							if($deptId == $v['dept_id']){
								$rs->fields['dept_name'] = $v['dept_name'];
							}
							
						}
					}

					@$rs->fields['avg_score'] = round($rs->fields['score'] / $rs->fields['num'], 2); //质检平均评分
					@$rs->fields['avg_v_score'] = round($rs->fields['v_score'] / $rs->fields['v_num'], 2); //有效平均评分
					// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
					if($step == 'week'){
						$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['time']);
						$rs->fields['time'] = $week_time['start'].c('至').$week_time['end'];
					}		

					//合计数组(累加)
					$arr_total['num'] += $rs->fields['num'];
					$arr_total['score'] += $rs->fields['score'];
					$arr_total['v_num'] += $rs->fields['v_num'];
					$arr_total['v_score'] += $rs->fields['v_score'];
					$arr_total['very_satisfied'] += $rs->fields['very_satisfied'];
					$arr_total['satisfied'] += $rs->fields['satisfied'];
					$arr_total['commonly'] += $rs->fields['commonly'];
					$arr_total['unsatisfied'] += $rs->fields['unsatisfied'];
					$list[] = $rs->fields;

					$i ++;
					$rs->MoveNext();
				} // end while (!$rs->EOF)

				@$arr_total['avg_score'] = round($arr_total['score'] / $arr_total['num'], 2); //质检平均评分
				@$arr_total['avg_v_score'] = round($arr_total['v_score'] / $arr_total['v_num'], 2); //有效平均评分
				
				
				/******************** 添加'通话次数'、'转接号码次数' 和'有效质检比例' start *****************/
				// 查询时间范围内的通话记录表
				$table_list = $this->getCdrTableList(ASTERISKCDRDB_DB_NAME);
				
				if(empty($table_list)){
					$table_list[] = 'ss_cdr_cdr_info';
				}
				
				// 按坐席
				$agent_extension = '';		// 坐席分机号
				foreach($list as $key => $value){
					if(!empty($value['agent'])){
						$agent_extension .= $value['agent'] . ',';
					}
				}
				$agent_extension = trim($agent_extension,',');
				
				// 组合查询 “通话次数” 的 sql
				$sql_num_calls = "SELECT agent_number,SUM(number_of_calls) AS number_of_calls,SUM(transfer_times) AS transfer_times FROM (";
				foreach($table_list as $table){
					$sql_num_calls .= " SELECT agent_number,COUNT(0) AS number_of_calls,SUM(IF(transfer_number <> '',1,0)) AS transfer_times FROM `$table` WHERE agent_number IN($agent_extension) AND `start_stamp` BETWEEN '$fromdate' AND '$todate' ";  
					if(!empty($call_type) && $call_type !=4){
						$sql_num_calls .= " AND `call_type` = $call_type AND agent_sec > 0 ";
					}
					$sql_num_calls .= " GROUP BY agent_number UNION ALL ";
				}
				$sql_num_calls =  rtrim($sql_num_calls,'UNION ALL');
				$sql_num_calls .= ") AS t1 GROUP BY agent_number";
				
				//echo $sql_num_calls;
				$re_num_calls = $db -> GetALl($sql_num_calls);
				
				// 将分机号相同的数组合并到一起
				foreach($list as $key => $value){
					foreach($re_num_calls as $k => $v){
						if($value['agent'] == $v['agent_number']){
							$list_all[] = $value+$v;
						}
					}
				}
				$list = $list_all;
				
				foreach($list as $key => $value){
					// 质检比例
					$list[$key]['quality_proportion'] = number_format($value['num']/($value['number_of_calls'] - $value['transfer_times']),4)  * 100 . '%';
					// 有效质检比例
					$list[$key]['valid_quality_proportion'] = number_format($value['v_num']/$value['num'],4)  * 100 . '%';
					// 通话记录（合计）
					$arr_total['number_of_calls'] += $value['number_of_calls'];
					// 通话转接次数（合计）
					$arr_total['transfer_times'] += $value['transfer_times'];
				}
				
				// 有效质检比例（合计）
				
				/******************** 添加'通话次数'、'转接号码次数'和'质检比例' end *****************/
				// 质检比例
				$arr_total['quality_proportion'] = number_format($arr_total['num']/($arr_total['number_of_calls'] - $arr_total['transfer_times']),4)  * 100 . '%';
				// 有效质检比例
				$arr_total['valid_quality_proportion'] = number_format($arr_total['v_num']/$arr_total['num'],4)  * 100 . '%';
				
			} # end by seats
			/************************* 按坐席查询 end *********************************/

			/************************* 按部门查询 start *******************************/
			if($statistical_dimension == 'by_department'){
					
				$arr_depart = array();
				if (empty($depart_id)){
					$depart_id = 0;
				} else {
					$depart_id = intval($depart_id);
				}
				
				/******************* 若按部门查询，根据搜索条件中的 dept_id 来查处它所对应的下一级部门的 id. start ************************/
				//admin超级管理员
				global $cache_department;
				if (1 == $_SESSION['userinfo']['power']) {
					foreach ($cache_department as $val) {
						if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
					}
					//所选部门没有子部门, 只显示所选(条件中的)部门
					if ($depart_id != 0 && count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
					//print_r($depart_id);
				} else {	//非admin管理员
					if (0 == $depart_id) {
						$arr_depart = $this->getManageDirectDept();
					} else if (in_array($depart_id, $arr_deptid)) {
						foreach ($cache_department as $val) {
							if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
						}
						//所选部门没有子部门, 只显示所选(条件中的)部门
						if (count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
					}
				}
				if (!empty($depart_id)) $arr_depart[] = $depart_id;
				
				$arr_depart = array_unique($arr_depart);	// 对部门id 去重
				
				/******************* 若按部门查询，根据搜索条件中的 dept_id 来查处它所对应的下一级部门的 id. end ************************/
					
				foreach($arr_depart as $key => $value){
					if (in_array($value, $arr)) continue;
						$arr[] = $value;
					//var_dump($value);echo"&emsp;&emsp;&emsp;&emsp;&emsp;";
					$list_depart = $this->getNodeChild($dept, $value, 'dept');
					$list_depart .= $value; //加上所选部门
					$list_depart = $depart_id == $value ? $value : $list_depart;
						
					//根据部门获取坐席
					$sql_extension = "select extension from org_user where dept_id in ({$list_depart})";
					$extens = $db->GetAll($sql_extension);
						$arrExtens = array();
						foreach($extens as $v){
							if(!empty($v['extension'])){
								$arrExtens[] = $v['extension'];
							}
						}
					$strExtens = implode(',',$arrExtens);
					$strExtens = trim($strExtens,',');
					
					//计算每个通话记录的平均值后，再用来计算每个座席的平均值
					if(!empty($strExtens)){
						if($step == 'all' || empty($step)){
							$sql = "select agent, count(0) as num, sum(score) as score, sum(if($score_list,1,0)) as v_num, sum(if($score_list,score,0)) as v_score,	sum(IF(score = 5,1,0)) AS very_satisfied,sum(IF(score = 3,1,0)) AS satisfied,sum(IF(score = 2,1,0)) AS commonly,sum(IF(score = 0,1,0)) AS unsatisfied from ss_cdr_feedback where " . $condition . " AND agent IN($strExtens) ";
						}else{
							$sql = "select $time AS time,date_format('calltime','%Y') AS year,calltime, agent, count(0) as num, sum(score) as score, sum(if($score_list,1,0)) as v_num, sum(if($score_list,score,0)) as v_score,sum(IF(score = 5,1,0)) AS very_satisfied,sum(IF(score = 3,1,0)) AS satisfied,sum(IF(score = 2,1,0)) AS commonly,sum(IF(score = 0,1,0)) AS unsatisfied from ss_cdr_feedback where " . $condition . " AND agent IN($strExtens) ";
						}
					}

					if ('search' == $_REQUEST['do']) {
						//获取总记录数(用于分页)
						$sql_page_num = "SELECT COUNT(*) FROM ( " .$sql ." ) AS t1";
						$record_nums = $db->GetOne($sql_page_num);
						$this->Tmpl['record_nums'] = $record_nums;

						$pg = loadClass('tool','page',$this);
						$pg->setPageVar('p');
						$pg->setNumPerPage( 20 );

						$currentPage = $_REQUEST['p'];
						unset($_REQUEST['p']);
						unset($_REQUEST['action']);
						unset($_REQUEST['module']);
						unset($_REQUEST['cfg_traffic_header']);

						$pg->setVar($_REQUEST);
						$pg->setVar(array("module"=>"report","action"=>"feedbackTotal"));
						$pg->set($record_nums,$currentPage);
						$this->Tmpl['show_pages'] = $pg->output(1);
					}
				
					$rs = $db->Execute($sql);

					while (!$rs->EOF) {
						@$rs->fields['avg_score'] = round($rs->fields['score'] / $rs->fields['num'], 2); //质检平均评分
						@$rs->fields['avg_v_score'] = round($rs->fields['v_score'] / $rs->fields['v_num'], 2); //有效平均评分
						@$rs->fields['deparment'] = $value; // 本次查询的部门，作为后面合并数组的关联关系
						@$rs->fields['dept_name'] = $cache_department[$rs->fields['deparment']]['dept_name'];
						// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
						if($step == 'week'){
							$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['time']);
							$rs->fields['time'] = $week_time['start'].c('至').$week_time['end'];
						}		
						// 有效质检比例
						//@$rs->fields['valid_quality_proportion'] = number_format($rs->fields['v_num']/($rs->fields['number_of_calls'] - $rs->fields['transfer_times']),4)  * 100 . '%';
					
						//合计数组(累加)
						$arr_total['num'] += $rs->fields['num'];
						$arr_total['score'] += $rs->fields['score'];
						$arr_total['v_num'] += $rs->fields['v_num'];
						$arr_total['v_score'] += $rs->fields['v_score'];
						$arr_total['very_satisfied'] += $rs->fields['very_satisfied'];
						$arr_total['satisfied'] += $rs->fields['satisfied'];
						$arr_total['commonly'] += $rs->fields['commonly'];
						$arr_total['unsatisfied'] += $rs->fields['unsatisfied'];
						$list[] = $rs->fields;

						$rs->MoveNext();
					} // end while (!$rs->EOF)

					@$arr_total['avg_score'] = round($arr_total['score'] / $arr_total['num'], 2); //质检平均评分
					@$arr_total['avg_v_score'] = round($arr_total['v_score'] / $arr_total['v_num'], 2); //有效平均评分
					
					
					/******************** 添加'通话次数'、'转接号码次数' 和'有效质检比例' start *****************/
					// 查询时间范围内的通话记录表  
					
					$table_list = $this->getCdrTableList(ASTERISKCDRDB_DB_NAME);
					
					if(empty($table_list)){
						$table_list[] = 'ss_cdr_cdr_info';
					}
								
					// 组合查询 “通话次数” 的 sql
					$sql_num_calls = "SELECT agent_number,SUM(number_of_calls) AS number_of_calls,SUM(transfer_times) AS transfer_times FROM (";
					foreach($table_list as $table){
						$sql_num_calls .= " SELECT agent_number,COUNT(0) AS number_of_calls,SUM(IF(transfer_number <> '',1,0)) AS transfer_times FROM `$table` WHERE agent_number IN($strExtens) AND `start_stamp` BETWEEN '$fromdate' AND '$todate' ";  
						if(!empty($call_type) && $call_type !=4){
							$sql_num_calls .= " AND `call_type` = $call_type AND agent_sec > 0 ";
						}
						$sql_num_calls .= " GROUP BY agent_number UNION ALL ";
					}
					$sql_num_calls =  rtrim($sql_num_calls,'UNION ALL');
					$sql_num_calls .= ") AS t1 ";
					//echo $sql_num_calls;
					$re_num_calls = $db -> GetALl($sql_num_calls);
					foreach($re_num_calls as $k => $v){
						$re_num_calls[$k]['deparment'] = $value;
					}
						
					// 将分机号相同的数组合并到一起
					foreach($list as $val){
						foreach($re_num_calls as $v){
							if($val['deparment'] == $v['deparment']){
								 $list_all[] = $val+$v;
							}
						}
					}
						
					$list = $list_all;
					foreach($list as $key => $val){
						// 该部门质检数不能为空，且不能重复加入合计里面
						if(!empty($val['num']) && $val['deparment'] == $value){
							// 通话记录（合计）
							$arr_total['number_of_calls'] += $val['number_of_calls'];
							// 通话转接次数（合计）
							$arr_total['transfer_times'] += $val['transfer_times'];
						}

					}
			
					// 有效质检比例（合计）
					/******************** 添加'通话次数'、'转接号码次数'和'质检比例' end *****************/
					// 质检比例
					$arr_total['quality_proportion'] = number_format($arr_total['num']/($arr_total['number_of_calls'] - $arr_total['transfer_times']),4)  * 100 . '%';
					// 有效质检比例
					$arr_total['valid_quality_proportion'] = number_format($arr_total['v_num']/$arr_total['num'],4)  * 100 . '%';
					
					// 如果按步长查询，则最后做一个时间从现在到以前的排序
					if($step == 'month' || $step == 'day' || $step == 'hour'){
						$list = $this -> arraySort($list,'time');
					}elseif($step == 'week' ){
						$list = $this -> arraySort($list,'calltime');
					}
					
				}	# end foreach $arr_depart
				
				// 如果质检数为 0 ，则不显示该条记录
				foreach($list as $key => $val){
					if($val['num'] == 0){
						unset($list[$key]);
						continue;
					}
					// 质检比例
					$list[$key]['quality_proportion'] = number_format($val['num']/($val['number_of_calls'] - $val['transfer_times']),4)  * 100 . '%';
					// 有效质检比例
					$list[$key]['valid_quality_proportion'] = number_format($val['v_num']/$val['num'],4)  * 100 . '%';
				}
				
				$offset = ($current_page-1) * 20;
				$list = array_slice($list,$offset,20);
						
			} # end by deparment
			/************************* 按部门查询 end *********************************/
	
			// 导出
			if ('export' == $do) {
				//导出表头
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=feedback_total.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);

				$xls_cols1 = array(
					'user_name'					=> '座席',
					'dept_name'					=> '部门',
					'number_of_calls'			=> '通话次数',
					'transfer_times'			=> '转接号码次数',
					'num'						=> '质检数量',
					'v_num'						=> '有效质检数量',
					'quality_proportion'		=> '质检比例',
					'valid_quality_proportion'	=> '有效质检比例',
					'very_satisfied'			=> '非常满意',
					'satisfied'					=> '满意',
					'commonly'					=> '一般',
					'unsatisfied'				=> '不满意',
					'score'						=> '质检总评分',
					'avg_score'					=> '质检平均评分',
					'v_score'					=> '有效质检评分',
					'avg_v_score'				=> '有效平均评分'
				);
				
				// 如果是通过部门的话，不显示坐席
				if($statistical_dimension == 'by_department'){
					array_shift($xls_cols1);
				}
				
				if ($_REQUEST['step'] == 'day') {
					$step_name = '日期';
				} else if ($_REQUEST['step'] == 'hour') {
					$step_name = '小时';
				} else if ($_REQUEST['step'] == 'week') {
					$step_name = '周';
				} else if ($_REQUEST['step'] == 'month') {
					$step_name = '月份';
				}
				
				if($step_name){
					$xls_cols2 = array(
						'time' => $step_name,
					);
					$xls_columns = $xls_cols2 + $xls_cols1;
				}else{
					$xls_columns = $xls_cols1;
				}
			
				$cols = 0;
				foreach ($xls_columns as $value) xlsWriteLabel(1, $cols++, $value);
				
				//第三行, 输出详细数据
				$rows = 2;
				foreach ($list as $fields)
				{
					$cols = 0;
					
					//按cfg_column的配置项导出
					foreach ($xls_columns as $key => $val)
					{
						$str = iconv("UTF-8", "GBK//IGNORE", $fields[$key]);
						xlsWriteLabel($rows, $cols++, $str);
					} //end foreach ($cfg_column as $key => $val)

					$rows ++;
				}
				
				//输出合计数组
				if (count($list)>1) {
					if($statistical_dimension == 'by_department'){	
						xlsWriteLabel($rows, 0, '合计：');
						$cols = 1;
					}else{
						xlsWriteLabel($rows, 1, '合计：');
						$cols = 1;
					}
					//按cfg_column的配置项导出
					foreach ($xls_columns as $key => $val)
					{
						if ('dept_name' == $key || 'agent' == $key ) continue;
						$str = iconv("UTF-8", "GBK//IGNORE", $arr_total[$key]);
						xlsWriteLabel($rows, $cols++, $str);
					} //end foreach ($cfg_column as $key => $val)
				} // end if (count($list)>1)

				xlsEOF();
				exit;

			}  # end $do=export
	
		} // if ('' != $do)

		$this->Tmpl['arr_total'] = $arr_total;
		$this->Tmpl['list'] = $list;
		$this->display();
	}


	/**
     +----------------------------------------------------------
     * 电话监听报表
     * @author	: pengj
     * @date	: 2012/3/6
     +----------------------------------------------------------
     */
	function showMonitorList()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'monitor_list', $arr_local_priv, '您没有查看电话监听报表的权限！' );

		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d');
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll( $sql );

		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		$list = array();

		if ('' != $do) {
			//
			//非admin管理员, 获取其所能管理的座席
			//
			$arr_exten = array();
			$list_exten = "";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_exten = $this->getManageUserExten();
				if (count($arr_exten) == 0) $arr_exten[] = 0;
				$list_exten = numberToString4Sql(implode(',', $arr_exten));
			}

			//
			//根据起止时间组合查询条件
			//
			$condition = " 1 ";
			if (!empty($fromdate)) {
				$condition .= " and time_start>='$fromdate'";
			}

			if (!empty($todate)) {
				$todate .= ' 23:59:59';
				$condition .= " and time_start<='$todate'";
			}

			//
			//按部门查找(被监听者)
			//
			if (!empty($depart_id)) {

				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";	//加上所选部门

				//获取所选部门的座席列表
				$extenSelect = array();
				$rs	= $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while(!$rs->EOF){
					$extenSelect[] = $rs->fields;
					if ($rs->fields['extension']) {
						$extensionList[] = $rs->fields['extension'];
					}
					$rs->MoveNext();
				}

				if (empty($extension)) {
					if(count($extensionList)>0){
						$extension_strs	= implode(",", $extensionList);
						$condition	.= " and dst in ($extension_strs)";
					} else {
						$condition	.= " and dst in (0)";
					}
				}

				$this->Tmpl['extenSelect']	= $extenSelect;
			}

			//座席工号查找(被监听者)
			if (!empty($extension)) $condition	.= " and dst='$extension' ";

			//
			//监听者查询
			//
			if ($agent != '') $condition .= " and agent='$agent'";

			//
			//非管理员, 根据所能管理的部门或座席组合限定条件(限定监听者)
			//
			if (1 != $_SESSION['userinfo']['power']) $condition .= " and agent in ($list_exten)";

			$sql_count = "select count(0) from ss_cdr_monitor where " . $condition; //统计数量
			$sql = "select * from ss_cdr_monitor where " . $condition . " order by id desc";

			$record_nums = $db->GetOne($sql_count);

			$this->Tmpl['record_nums'] = $record_nums;

			$pg = loadClass('tool','page',$this);
			$pg->setPageVar('p');
			$pg->setNumPerPage( 20 );

			$currentPage = $_REQUEST['p'];
			unset($_REQUEST['p']);
			unset($_REQUEST['action']);
			unset($_REQUEST['module']);
			unset($_REQUEST['cfg_traffic_header']);

			$pg->setVar($_REQUEST);
			$pg->setVar(array("module"=>"report","action"=>"monitorList"));
			$pg->set($record_nums,$currentPage);
			$this->Tmpl['show_pages'] = $pg->output(1);


			if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
				echo $db->ErrorMsg();
				exit();
			}

			//加密处理
			$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );
			$this->Tmpl['flag_hidden'] = $flag_hidden;

			while (!$rs->EOF) {

				//号码隐藏
				if (1 == $flag_hidden && !empty($rs->fields['outnum'])) {
					$rs->fields['outnum'] = transferPhone($rs->fields['outnum']);
				}

				if (!empty($rs->fields['agent'])) {
					$u = $this->getUserByExten($rs->fields['agent']);
					$rs->fields['agent_name'] = $u['user_name'];
				}

				if (!empty($rs->fields['dst'])) {
					$u = $this->getUserByExten($rs->fields['dst']);
					$rs->fields['dst_name'] = $u['user_name'];
					$rs->fields['dept_name'] = $u['dept_name'];
				}

				$rs->fields['filename'] = $this->getRecordFile($rs->fields['call_id']);


				$list[] = $rs->fields;
				$rs->MoveNext();
			} // end while (!$rs->EOF)

		} // if ('' != $do)


		$this->Tmpl['list'] = $list;
		$this->display();
	}


    /**
     *播放
     */
	function showRecordingPlay()
	{
		$this->Tmpl['exten'] = $_GET['exten'];
		$this->Tmpl['calltime'] = $_GET['calltime'];
		$this->Tmpl['filename'] = $_GET['filename'];
		$this->display();
	}

     /**
      *读取文件
      */
    function showFileRead()
	{
        if (isset($_GET['recording'])) {
          $path = $_GET['recording'];

          // See if the file exists
          if (!is_file($path)) { die("<b>404 File not found!</b>"); }

          // Gather relevent info about file
          $size = filesize($path);
          $name = basename($path);
          $extension = strtolower(substr(strrchr($name,"."),1));

          // This will set the Content-Type to the appropriate setting for the file
          $ctype ='';
          switch( $extension ) {
            case "mp3": $ctype="audio/mpeg"; break;
            case "wav": $ctype="audio/x-wav"; break;
            case "Wav": $ctype="audio/x-wav"; break;
            case "WAV": $ctype="audio/x-wav"; break;
            case "gsm": $ctype="audio/x-gsm"; break;
			case "tgz":	$ctype="application/x-tar"; break;

            // not downloadable
            case "php":
            case "htm":
            case "html":
            case "txt": die("<b>Cannot be used for ". $file_extension ." files!</b>"); break;

            default: die("<b>Cannot use file: ". $path ."!</b>"); break ;
          }

          // need to check if file is mislabeled or a liar.
          $fp=fopen($path, "rb");
          if ($size && $ctype && $fp) {
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: public");
            header("Content-Description: wav file");
            header("Content-Type: " . $ctype);
            header("Content-Disposition: attachment; filename=" . $name);
            header("Content-Transfer-Encoding: binary");
            header("Content-length: " . $size);
            fpassthru($fp);
          }//end if
        }//end if

    }//end function showfileRead


		/**
	 *功能：查询语音留言
	 *日期：2012-3-14
	 **/
	function showVoiceList()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'voiceList_view', $arr_local_priv, '您没有查看语音留言的权限！' );
		$pdxdb = $this->loadPdxDB();

		//加密处理
		$flag_hidden = $this->isAuth( 'phonenumber_hid', $arr_local_priv, '' );

		/* 超级管理员可以查看所有语音留言
		        部门主管可以查看自己及下级用户的语音留言
		        普通用户只能查看自己的语音留言 */
		if(1 != $_SESSION['userinfo']['power']){
			//$db = $this->loadDB();
			$arr_exten = $this->getManageUserExten();
			foreach ($arr_exten as &$value)
			{
				$value = "'$value'";
			}
			$extensionlist = implode(',', $arr_exten);
			//$db->Close();
		}

		$word = varFilter($_REQUEST['word']);
		$exten = varFilter($_REQUEST['exten']);
		$flag = varFilter($_REQUEST['flag']);
		$condition = " where 1=1 ";
		if ($word != '') $condition .= " and callerid like '%$word%'";
		if ($exten != '') $condition .= " and mailbox='$exten'";
		if ($flag != '') $condition .= " and flag='$flag'";
		if (1 != $_SESSION['userinfo']['power']) {
			$condition .= " and mailbox in ($extensionlist)";
		}

		$sql = "select count(*) from ss_cdr_voicemail " . $condition;
		$record_nums = $pdxdb->GetOne($sql);

		$pg = loadClass('tool','page',$this);
		$pg->setPageVar('p');
		$pg->setNumPerPage( 15 );
		$currentPage = $_REQUEST['p'];

		unset($_REQUEST['p']);
		unset($_REQUEST['btn_search']);
		unset($_REQUEST['action']);
		unset($_REQUEST['module']);
		$this->Tmpl['REQUEST'] = $_REQUEST;

		$pg->setVar($_REQUEST);
		$pg->setVar(array("module"=>"report","action"=>"voiceList"));
		$pg->set($record_nums,$currentPage);

		$this->Tmpl['show_pages'] = $pg->output(1);

		$sql = "select * from ss_cdr_voicemail " . $condition;
		$sql .= ' ORDER BY calldate DESC';
		if (!$rs = $pdxdb->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
			echo $pdxdb->ErrorMsg();
		}
		while(!$rs->EOF)
		{
			//加密处理
			if (1 == $flag_hidden && !empty($rs->fields['callerid'])) {
				$rs->fields['callerid'] = transferPhone($rs->fields['callerid']);
			}

			$rs->fields['duration']			= intval($rs->fields['duration']) == 0?"00:00":sprintf("%02d",intval($rs->fields['duration']/60)).":".sprintf("%02d",intval($rs->fields['duration']%60));
			$rs->fields['dir'] = PBX_SPOOL_PATH."voicemail/default/".$rs->fields[mailbox]."/INBOX/";
			$list[] = $rs->fields;
			$rs->MoveNext();
		}

		$this->Tmpl['list'] = $list;
		$this->Tmpl['dir'] = $dir;

		$pdxdb->Close();
		$this->display();
	}

	/**
	 *功能：秒 转成 时:分:秒
	 *返回：时 分 秒
	 *日期：2012-12-10
	 **/
	function SecondsToTime($Seconds)
	{
		if($Seconds > 0){
			$hour = intval($Seconds/3600);			//求出小时
			//如果小时小于10，则在前面加0
			if($hour < 10){
				$hour = '0' . $hour;
			}
			$hourRemainder = $Seconds % 3600 ;		//求出小时的余数
			$minute = intval($hourRemainder/60);	//求出分钟
			//如果分钟小于10，则在前面加0
			if($minute < 10){
				$minute = '0' . $minute;
			}
			$minuteRemainder = $hourRemainder % 60; //求出分钟的余数
			//如果秒小于10，则在前面加0
			if($minuteRemainder < 10){
				$minuteRemainder = '0' . $minuteRemainder;
			}
			$time = $hour . ':' . $minute . ':' . $minuteRemainder;	//组合 时 分秒
			return $time ;
		}else{
			return '00:00:00';
		}
	}
        
        /**
     +----------------------------------------------------------
     * 系统运营指标报表
     * @author	: liuxb
     * @date	: 2013/10/16
     +----------------------------------------------------------
     */
	function showSystemTrafficTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'system_traffic_total', $arr_local_priv, '您没有查看系统运营指标报表的权限！' );

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		
		//按统计步长
		if ('day' == $step || empty($step)){
			$_REQUEST['step'] = 'day';
			$key = " SUBSTRING(total_date, 1, 10) ";
		} else if ('hour' == $step) {
			$key = " SUBSTRING(total_date, 12, 2) ";
		} else if ('week' == $step) {
			$key = " WEEK(total_date,1) ";
		} else if ('month' == $step) {
			$key = " SUBSTRING(total_date, 1, 7) ";
		}

		//选择时间
		if (!isset($_REQUEST['fromdate'])){
			$fromdate = date('Y-m-d', time() - 86400 * 7);
			$_REQUEST['fromdate'] = $fromdate ;
		}
		if (!isset($_REQUEST['todate'])){
			$todate = date('Y-m-d', time());
			$_REQUEST['todate'] = $todate ;
		}
		if (!isset($_REQUEST['s_hour'])){ 
			$s_hour = '00';
			$_REQUEST['s_hour'] = $s_hour ;
		}
		if (!isset($_REQUEST['e_hour'])){ 
			$e_hour = '23';
			$_REQUEST['e_hour'] = $e_hour ;
		}

		//时间检索
		$condition = "";
		if (!empty($fromdate)){
			if (empty($s_hour)) $s_hour = '00';
			$fromdate .= ' ' . $s_hour;
			$condition .= " and total_date >= '$fromdate'";
		}

		if (!empty($todate)){
			if (empty($e_hour)) $e_hour = '59';
			$todate .= ' ' . $e_hour . ':59:59';
			$condition .= " and total_date <= '$todate'";
		}

		$sql  = " SELECT $key as total_date2,SUM(queue_occupation_times) as inbound_manual_occupation_times ";	//呼入次数
		$sql .= " ,SUM(queue_connected_times) as inbound_manual_connected_times ";								//通话次数
		$sql .= " ,SUM(inbound_20s_conv_times) as inbound_20s_conv_times ";										//20秒接通数
		//$sql .= " ,SUM(inbound_non_ans_times) as inbound_non_ans_times ";										//呼损数
		$sql .= " ,SUM(queue_conv_duration) as inbound_manual_conv_duration ";									//平均通话时长
		$sql .= " ,SUM(inbound_queue_waiting_duration) as inbound_wait_ans_duration ";							//平均等待均长
		$sql .= " ,SUM(inbound_queue_waiting_times) as inbound_wait_ans_times ";								//等待数
		$sql .= " ,SUM(call_limit_dur) as call_limit_dur FROM crm_sys_traffic_total WHERE 1  ";					//满线时长
		$sql .= " $condition GROUP BY total_date2 ORDER BY total_date2 ASC ";	
		
		//列表总数
		$sql_record = "SELECT count(*) FROM ( $sql ) as t ";
		$record_nums = $db->GetOne($sql_record);

		//分页
		$pg = loadClass('tool','page',$this);
		$pg->setPageVar('p');
		$pg->setNumPerPage( 20 );
		$currentPage = $_REQUEST['p'];
		unset($_REQUEST['p']);
		unset($_REQUEST['action']);
		unset($_REQUEST['module']);
        $_REQUEST['module'] = 'report';
        $_REQUEST['action'] = 'systemTrafficTotal';
		$pg->setVar($_REQUEST);
		$pg->set($record_nums,$currentPage);
		$this->Tmpl['show_pages'] = $pg->output(1);
		
		if ('export' == $_REQUEST['do']) {
			$rs = $db->Execute($sql);
		} else {
			//查询数据
			if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
				echo $db->ErrorMsg();
				exit;
			}
		}
			
		//遍历数据
		while (!$rs->EOF) {
			
			//通话时间
			$rs->fields['total_date']  = $rs->fields['total_date2'] ;

			//接通率
			$rs->fields['inbound_manual_occupation_times_rate'] 
					= round(($rs->fields['inbound_manual_connected_times'] / $rs->fields['inbound_manual_occupation_times']) * 100, 2).'%';
			//20秒接通率
			$rs->fields['inbound_20s_conv_times_rate'] 
					= round(($rs->fields['inbound_20s_conv_times'] / $rs->fields['inbound_manual_occupation_times']) * 100, 2).'%';
			//呼损数 = 呼入数 -  接听数
			$rs->fields['inbound_non_ans_times']
					= $rs->fields['inbound_manual_occupation_times'] - $rs->fields['inbound_manual_connected_times'];
			//呼损率
			$rs->fields['inbound_non_ans_times_rate'] 
					= round(($rs->fields['inbound_non_ans_times'] / $rs->fields['inbound_manual_occupation_times']) * 100, 2).'%';
			//平均通话时长
			$rs->fields['inbound_avg_manual_conv_duration'] 
					= round(($rs->fields['inbound_manual_conv_duration'] / $rs->fields['inbound_manual_connected_times']), 2);
			//平均等待均长
			$rs->fields['inbound_avg_wait_ans_duration'] 
					= round(($rs->fields['inbound_wait_ans_duration'] / $rs->fields['inbound_wait_ans_times']), 2);

			//合计
			$arr_total['inbound_manual_occupation_times'] += $rs->fields['inbound_manual_occupation_times'];
			$arr_total['inbound_manual_connected_times'] += $rs->fields['inbound_manual_connected_times'];
			$arr_total['inbound_20s_conv_times'] += $rs->fields['inbound_20s_conv_times'];
			$arr_total['inbound_non_ans_times'] += $rs->fields['inbound_non_ans_times'];
			$arr_total['inbound_manual_conv_duration'] += $rs->fields['inbound_manual_conv_duration'];
			$arr_total['inbound_wait_ans_duration'] += $rs->fields['inbound_wait_ans_duration'];
			$arr_total['inbound_wait_ans_times'] += $rs->fields['inbound_wait_ans_times'];
			$arr_total['call_limit_dur'] += $rs->fields['call_limit_dur'];

			//时间转换
			$rs->fields['call_limit_dur'] = $this->SecondsToTime($rs->fields['call_limit_dur']);
			$rs->fields['inbound_avg_wait_ans_duration'] = $this->SecondsToTime($rs->fields['inbound_avg_wait_ans_duration']);
			$rs->fields['inbound_avg_manual_conv_duration'] = $this->SecondsToTime($rs->fields['inbound_avg_manual_conv_duration']);

			$list[] = $rs->fields;
			$rs->MoveNext();
		}

		//统计计算
		//接通率
		$arr_total['inbound_manual_occupation_times_rate'] 
				= round(($arr_total['inbound_manual_connected_times'] / $arr_total['inbound_manual_occupation_times']) * 100, 2).'%';
		//20秒接通率
		$arr_total['inbound_20s_conv_times_rate'] 
				= round(($arr_total['inbound_20s_conv_times'] / $arr_total['inbound_manual_occupation_times']) * 100, 2).'%';
		//呼损率
		$arr_total['inbound_non_ans_times_rate'] 
				= round(($arr_total['inbound_non_ans_times'] / $arr_total['inbound_manual_occupation_times']) * 100, 2).'%';
		//平均通话时长
		$arr_total['inbound_avg_manual_conv_duration'] 
				= round(($arr_total['inbound_manual_conv_duration'] / $arr_total['inbound_manual_connected_times']), 2);
		//平均等待均长
		$arr_total['inbound_wait_ans_duration'] 
				= round(($arr_total['inbound_wait_ans_duration'] / $arr_total['inbound_wait_ans_times']), 2);
		//时间转换
		$arr_total['call_limit_dur'] = $this->SecondsToTime($arr_total['call_limit_dur']);
		$arr_total['inbound_avg_wait_ans_duration'] = $this->SecondsToTime($arr_total['inbound_wait_ans_duration']);
		$arr_total['inbound_avg_manual_conv_duration'] = $this->SecondsToTime($arr_total['inbound_avg_manual_conv_duration']);

		//导出excel
		if ('export' == $_REQUEST['do']) {

			if($_REQUEST['step'] == 'day'){ 
				$total_date_name = '日期';
			} else if($_REQUEST['step'] == 'hour'){ 
				$total_date_name = '小时';
			} else if($_REQUEST['step'] == 'week'){
				$total_date_name = '周';
			} else if($_REQUEST['step'] == 'month'){
				$total_date_name = '月份';
			}
			
			$cfg_column = array(
				'total_date'							=> $total_date_name,
				'inbound_manual_occupation_times'		=> '呼入次数',
				'inbound_manual_connected_times'		=> '通话次数',
				'inbound_manual_occupation_times_rate'	=> '接通率',
				'inbound_20s_conv_times'				=> '20秒接通',
				'inbound_20s_conv_times_rate'			=> '20秒接通率',
				'inbound_non_ans_times'					=> '呼损数',
				'inbound_non_ans_times_rate'			=> '呼损率',
				'inbound_avg_manual_conv_duration'		=> '平均通话时长',
				'inbound_avg_wait_ans_duration'			=> '平均等待均长',
				'call_limit_dur'						=> '满线时长'
			);

			//导出表头
			ob_end_clean();
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment;filename=system_quality_total.xls ");
			header("Content-Transfer-Encoding: binary ");
			xlsBOF();

			//第一行, 输出“导出时间”
			$export_time = date('Y-m-d H:i:s');
			xlsWriteLabel(0, 0, '导出时间:');
			xlsWriteLabel(0, 1, $export_time);

			//第二行, 输出标准的列、详细的“订单类型2”及“任务子状态”
			//输出标准表头
			$cols = 0;
			foreach ($cfg_column as $val){
				xlsWriteLabel(1, $cols++, $val);
			}

			//第三行, 输出详细数据
			$rows = 2;
			foreach ($list as $fields){
				$cols = 0;
				foreach ($cfg_column as $key=>$val){
					xlsWriteLabel($rows, $cols++, $fields[$key]);
				}
				$rows ++;
			}
			
			//统计数据
			$cols = 0;
			foreach ($cfg_column as $key=>$val){
				xlsWriteLabel($rows, $cols++, $arr_total[$key]);
			}
			xlsWriteLabel($rows, 0 , '总计：');

			xlsEOF();
			exit;
		}//导出结束
		

		$this->Tmpl['list'] = $list;
		$this->Tmpl['arr_total'] = $arr_total;
		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 通话记录质检明细报表
     +----------------------------------------------------------
     * @author	: pengj
     * @date	: 2012/8/27
	 * @parm	: $_REQUEST
	 * @return  : none
     +----------------------------------------------------------
     */	
	function showCdrQuality()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'cdr_quality_sel', $arr_local_priv, '您没有权限执行此操作' );

		$_REQUEST['fromdate'] = empty($_REQUEST['fromdate']) ? date('Y-m-d', strtotime('-30 day')) : trim($_REQUEST['fromdate']);
		$_REQUEST['todate'] = empty($_REQUEST['todate']) ? date('Y-m-d') : trim($_REQUEST['todate']);

		$_REQUEST['page_size'] = intval($_REQUEST['page_size']) <= 0 || intval($_REQUEST['page_size']) > 200 ? 20 : intval($_REQUEST['page_size']);
		$_REQUEST = varFilter($_REQUEST);
		
		// 获取质检员列表
		$tmp_str = c('质检员');
		$sql_inspector = "SELECT u.`user_name`,u.`extension` FROM `org_user` u LEFT JOIN `org_user_priv` p ON u.`user_priv` = p.`priv_id` WHERE p.`priv_name`='$tmp_str'";
		$re_inspector = $db -> GetAll($sql_inspector);
		$this -> Tmpl['inspector'] = $re_inspector;
		
		//获得部门列表
		if (1 != $_SESSION['userinfo']['power']) {
			$manager_dept = $this->getManageDept($_SESSION['userinfo_detail']['user_id']);
			$sql = "SELECT * FROM org_department where dept_id = '".$_SESSION['userinfo_detail']['dept_id']."'";
			if (!empty($manager_dept)) {
				$sql .= " or find_in_set(dept_id, '".implode(',', $manager_dept)."') > 0";
			}
		} else {
			$sql = "SELECT * FROM org_department";
		}
		$dept = $db->GetAll( $sql );
		
		$depart_id=$_REQUEST['depart_id'];
		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		// 呼叫类型
		$callType = array(0=>array('call_type'=> 4,'call_type_name' => c('全部')),1=>array('call_type'=> 1,'call_type_name' => c('呼入')),2=>array('call_type'=> 2,'call_type_name' => c('呼出')));
		$this->Tmpl['callType'] = $callType;
		
		/* 处理查询 */
		$where = " WHERE create_time>='{$_REQUEST['fromdate']} 00:00:00' AND create_time<='{$_REQUEST['todate']} 23:59:59' ";

		//根据”部门条件“组合条件
		$extenSelect = array();
		if (!empty($depart_id)) {
			$exten_arr = array();
			//获取所有的子部门
			$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
			$list_depart .= "$depart_id";	//加上所选部门

			//获取所选部门的座席列表
			$sql = "SELECT extension, user_name FROM org_user WHERE find_in_set(dept_id, '$list_depart') > 0 and extension != '' and extension is not null";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				$sql .= " and (find_in_set(dept_id, '".implode(',', $arr_deptid)."') > 0 or extension = '".$_SESSION['userinfo']['extension']."')";
			}
			$rs	= $db->Execute($sql);
			while(!$rs->EOF) {
				$extenSelect[] = $rs->fields['extension'];
				$exten_arr[] = $rs->fields;
				$rs->MoveNext();
			}
			$list_exten = numberToString4Sql(implode(',', $extenSelect));
			$this->Tmpl['extenSelect'] = $exten_arr;
		} elseif (1 != $_SESSION['userinfo']['power']) {
			$arr_exten = array();
			$list_exten = "";
			$arr_deptid = array();
			$list_depart = "";
			$arr_deptid = $this->getManageDept();
			if (count($arr_deptid) == 0) $arr_deptid[] = 0;
			$list_depart = implode(',', $arr_deptid);

			$arr_exten = $this->getManageUserExten();
			if (count($arr_exten) == 0) $arr_exten[] = 0;
			$list_exten = numberToString4Sql(implode(',', $arr_exten));
		} else {
			//void
		}

		//座席工号查找
		if (!empty($_REQUEST['extension'])) {
			$where	.= " and extension='".$_REQUEST['extension']."' ";
		}else if (!empty($list_exten)) {
			$where .= " and extension in ($list_exten) ";
		} 

		$where .= " AND record_number<>'' "; //通话记录质检

			//查询客服质检方案
			$sql = "select * from stdout_quality_plan where is_enable=1 and is_deleted=0 and is_build=1 and is_build_again=0 and is_kehu=1";
			$kehuPlan = $db->GetRow($sql);
			$qpid=$kehuPlan['id'];
			if (empty($qpid) && !empty($_REQUEST['do'])) {
				goBack(c('查询失败：系统未配置客服质检方案, 或请检查质检方案是否已启用及生成.'));
			}
			if($kehuPlan['standard_score']<>'' || $kehuPlan['standard_err_fatal']<>'' || $kehuPlan['standard_err_common']<>''){
				$standardFlag=1;
			}else
			{
				$standardFlag=0;
			}

		$qualityTable = 'stdout_quality_record_' . $qpid; //设置质检方案表名

		if (isset($_REQUEST['quality_passed']) && $_REQUEST['quality_passed'] != -1 && $standardFlag='1') {	// 质检状态条件
			$where_pass="AND ("; 
			if($_REQUEST['quality_passed']=='1'){
				if($kehuPlan['standard_score']<>'')
				{
					$pass_std[]=" IF(quality_score>".$kehuPlan['standard_score'].",1,0)";
				}
				if($kehuPlan['standard_err_fatal']<>'')
				{
					$pass_std[]=" IF(fatal_errors<".$kehuPlan['standard_err_fatal'].",1,0)";
				}
				if($kehuPlan['standard_err_common']<>'')
				{
					$pass_std[]=" IF(common_errors<".$kehuPlan['standard_err_common'].",1,0)";
				}
			$where_pass.=implode(' AND ',$pass_std)." ) ";
			}else
			{
				if($kehuPlan['standard_score']<>'')
				{
					$pass_std[]=" IF(quality_score>".$kehuPlan['standard_score'].",0,1)";
				}
				if($kehuPlan['standard_err_fatal']<>'')
				{
					$pass_std[]=" IF(fatal_errors<".$kehuPlan['standard_err_fatal'].",0,1)";
				}
				if($kehuPlan['standard_err_common']<>'')
				{
					$pass_std[]=" IF(common_errors<".$kehuPlan['standard_err_common'].",0,1)";
				}
			$where_pass.=implode(' OR ',$pass_std)." ) ";
			}
			$where .= $where_pass;
		}

		if (isset($_REQUEST['quality_user']) && !empty($_REQUEST['quality_user'])) { // 质检用户条件
			$where .= " AND create_user='{$_REQUEST['quality_user']}' ";
		}
		if (isset($_REQUEST['fromdate_cdr']) && !empty($_REQUEST['fromdate_cdr'])) { // 通话时间起
			$where .= " AND task_create_time>='{$_REQUEST['fromdate_cdr']}' ";
		}
		if (isset($_REQUEST['todate_cdr']) && !empty($_REQUEST['todate_cdr'])) { // 通话时间止
			$where .= " AND task_create_time<='{$_REQUEST['todate_cdr']}' ";
		}
		
		// 搜索条件为呼叫类型
		if(!empty($_REQUEST['call_type']) && $_REQUEST['call_type']!=4){
			$where .= " AND call_type='{$_REQUEST['call_type']}' ";
		}

		//$_SESSION['task_where'] = $where;
        
		if ('search' == $_REQUEST['do']) { //查询

			$sumSql = "SELECT COUNT(0) AS c FROM {$qualityTable} {$where}";
			//echo $sumSql;
			$count = 0;
			$c = $db->GetAll($sumSql);
			foreach ($c as $val) {
				$count += $val['c'];
			}
			
			// 分页处理
			$pg = loadClass('tool', 'page', $this);
			$pg->setPageVar('p');


			//设置需查询的字段
			$fields = " remark,record_number, task_create_time, extension, task_create_time, dept_id, quality_passed, quality_score, fatal_errors, common_errors, ded_score, create_user, create_time,f_1,f_2,f_3,f_4,f_5,f_7,quality_phone_number ";

			$param = $_REQUEST;
			unset($param['p']);
			$this->Tmpl['param'] = http_build_query($param);
			$pg->setVar($param);
			$pg->setNumPerPage($_REQUEST['page_size']);
			$pg->set($count);
			if (!isset($_REQUEST['p']) || empty($_REQUEST['p'])) {
				$p = 1;
			}
			$serialId = (intval($p) -1) * $pg->getNumPerPage();
			$this->Tmpl['ser_id'] = $serialId;
			$this->Tmpl['show_pages'] = $pg->output(1);
			$sumSql = str_replace('COUNT(0) AS c', $fields, $sumSql);
			$sumSql .= " ORDER BY create_time DESC";
			$result = $db->SelectLimit($sumSql, $pg->getNumPerPage(), $pg->getOffset());
			$list = array();
			if ($result) {
				while (!$result->EOF) {
					//通话时长
					$searchMonth = date("ym", strtotime($result->fields['task_create_time']));

					/*-------------------------add by lirq 20160616----------------------------*/
					$currentM =  date("ym", time());
					$preM = date("ym",strtotime("-1 month"));
					if($searchMonth == $currentM || $searchMonth == $preM){
						$searchTable = "ss_cdr_cdr_merge";
					}else{
						$searchTable = "ss_cdr_cdr_info_".$searchMonth;
					}
					$sqlCdr = "select agent_sec from {$searchTable} where id=".$result->fields['record_number'];
					
					/*-------------------------end--------------------------*/
					//$sqlCdr = "select agent_sec from ss_cdr_cdr_info_".$searchMonth." where id=".$result->fields['record_number'];

					$reCdr = $db->GetOne($sqlCdr);
					$result->fields['agent_sec'] = $reCdr;
					
					if($standardFlag){
						if($result->fields['quality_score']>$kehuPlan['standard_score'] && $result->fields['fatal_errors']<$kehuPlan['standard_err_fatal'] && $result->fields['common_errors']<$kehuPlan['standard_err_common']){
							$result->fields['quality_passed']=c('通过');
						}else{
							$result->fields['quality_passed']=c('未通过');
						}
					}else{
						$result->fields['quality_passed']=c('-');
					}
					$list[] = $result->fields;
					$result->MoveNext();
				}
			}
			
			$this->Tmpl['list'] = $list;
			$p = $_REQUEST['p'];
			if (empty($p)) {
				$p = 1;
			}
		} else if ('export' == $_REQUEST['do']) { //导出
			$sql = "SELECT * FROM {$qualityTable} {$where}";
			$this->exportOrderQuality($sql, $qpid,$standardFlag);
			exit;
		}

		$qualityFlag = $this->isAuth( 'see_inspector', $arr_local_priv, '' );
		$this->Tmpl['qualityFlag'] = $qualityFlag;// 查看质检的权限

		$this->display();
	}

	/**
     +----------------------------------------------------------
     * 通话记录质检明细导出
     +----------------------------------------------------------
     * @author	: pengj
     * @date	: 2012/8/27
	 * @parm	: string $sql 查询sql语句
	 *			: int $qpid 质检方案id
	 *			: int $standardFlag 质检标准
	 * @return  : 
     +----------------------------------------------------------
     */	
	private function exportOrderQuality($sql, $qpid,$standardFlag) {
		global $db;
		if (empty($db)) $db = $this->loadDB();
		//print_r($sql);exit;
		$rs = $db->Execute($sql);
		if (!$rs) {
			echo $sql;
			echo "<br/><br/>";
			echo $db->ErrorMsg();
		}
		global $cache_department;
		if($standardFlag){
			//查询客服质检方案
			$sqll = "select * from stdout_quality_plan where is_enable=1 and is_deleted=0 and is_build=1 and is_build_again=0 and is_kehu=1";
			$kehuPlan = $db->GetRow($sqll);
		}
		$list = array();
		while (!$rs->EOF) {
			
			//通话时长
			$searchMonth = date("ym", strtotime($rs->fields['task_create_time']));
			$currentM =  date("ym", time());
			$preM = date("ym",strtotime("-1 month"));
			if($searchMonth == $currentM || $searchMonth == $preM){
				$searchTable = "ss_cdr_cdr_merge";
			}else{
				$searchTable = "ss_cdr_cdr_info_".$searchMonth;
			}
			$sqlCdr = "select agent_sec from {$searchTable} where id=".$rs->fields['record_number'];
			//$searchMonth = date("ym", strtotime($rs->fields['task_create_time']));
			//$sqlCdr = "select agent_sec from ss_cdr_cdr_info_".$searchMonth." where id=".$rs->fields['record_number'];
			$reCdr = $db->GetOne($sqlCdr);
			$rs->fields['agent_sec'] = $reCdr;
			
			foreach ($rs->fields as $key => $value)
			{
				if ('0000-00-00 00:00:00' == $rs->fields[$key]) $rs->fields[$key] = null;
			}
			$fields = $rs->fields;
			$exten_tmp=$this->getNameByExten($fields['extension']);
			$fields['extension'] = $exten_tmp? $exten_tmp. '(' . $fields['extension'] . ')':$fields['extension'];//被质检的座席

			$fields['dept_id'] = $cache_department[$fields['dept_id']]['dept_name'];//所属部门
			
			$creater_tmp=$this->getNameByExten($fields['create_user']);
			$fields['create_user'] = $creater_tmp? $creater_tmp. '(' . $fields['create_user'] . ')':$fields['create_user'];//质检员
			
			if($standardFlag){
				if($fields['quality_score'] > $kehuPlan['standard_score'] && $fields['fatal_errors'] < $kehuPlan['standard_err_fatal'] && $fields['common_errors'] < $kehuPlan['standard_err_common'])
				{
					$fields['quality_passed']=c('通过');
				}else{
					$fields['quality_passed']=c('未通过');
				}
			}else
			{
				$fields['quality_passed']=c('-');
			}

			$list[] = $fields;
			$rs->MoveNext();
		}


		$cfg_column = array(
			'task_create_time'	=> array(
				'name'			=> c('通话时间')
			),
			'agent_sec'	=> array(
				'name'  => c('通话时长')
			),			
			'quality_phone_number'	=> array(
				'name'  => c('电话号码')
			),
			'extension'		=> array(
				'name'  => c('所属座席')
			),
			'dept_id'	=> array(
				'name'  => c('所属部门')
			),
			'quality_passed'	=> array(
				'name'  => c('质检结果')
			),
			'f_1'	=> array(
				'name'  => c('服务态度评分')
			),
			'f_2'	=> array(
				'name'  => c('服务用语评分')
			),
			'f_3'	=> array(
				'name'  => c('业务能力评分')
			),
			'f_4'	=> array(
				'name'  => c('沟通技巧评分')
			),
			'f_7'	=> array(
				'name'  => c('录单信息评分')
			),
			'quality_score'	=> array(
				'name'  => c('总评分')
			),
			'ded_score'	=> array(
				'name'  => c('总扣分')
			),
			'fatal_errors'	=> array(
				'name'  => c('致命差错')
			),
			'common_errors'	=> array(
				'name'  => c('普通差错')
			),
			'create_user'	=> array(
				'name'  => c('质检员')
			),
			'create_time'	=> array(
				'name'  => c('质检时间'),
				
			),
			'remark'	=> array(
				'name'  => c('质检备注'),
				
			)
		);

		//导出表头
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");//exit;
		header("Content-Disposition: attachment;filename=order_quality_total.xls");
		header("Content-Transfer-Encoding: binary");
		
		xlsBOF();

		//第一行, 输出“导出时间”
		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		//第二行, 输出标准的列、详细的“订单类型2”及“任务子状态”
		//输出标准表头
		$cols = 0;
		foreach ($cfg_column as $val) xlsWriteLabel(1, $cols++, iconv("UTF-8", "GBK", $val['name']));

		//第三行, 输出详细数据
		$rows = 2;
		foreach ($list as $fields){
			$cols = 0;

			//按cfg_column的配置项导出
			foreach ($cfg_column as $key => $val){
				if ('number' == $val['type']) {
					xlsWriteNumber($rows, $cols++, $fields[$key]);
				} else {
					$str = iconv("UTF-8", "GBK//IGNORE", $fields[$key]);
					xlsWriteLabel($rows, $cols++, $str);
				}
			} //end foreach ($cfg_column as $key => $val)

			$rows ++;
		}

		xlsEOF();
		exit;
	}
	/**
     +----------------------------------------------------------
     * 通话记录质检统计报表
     +----------------------------------------------------------
     * @author	: pengj
     * @date	: 2012/8/27
	 * @parm	: $_REQUEST
	 * @return  : none
     +----------------------------------------------------------
     */	
	function showCdrQualityTotal()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu( $_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv ); # 获取导航菜单
		$this->isAuth( 'cdr_quality_total_sel', $arr_local_priv, '您没有权限执行此操作.' );

		$_REQUEST['fromdate'] = empty($_REQUEST['fromdate']) ? date('Y-m-d', strtotime('-7 day')) : trim($_REQUEST['fromdate']);
		$_REQUEST['todate'] = empty($_REQUEST['todate']) ? date('Y-m-d') : trim($_REQUEST['todate']);
		$_REQUEST['page_size'] = intval($_REQUEST['page_size']) <= 0 || intval($_REQUEST['page_size']) > 200 ? 20 : intval($_REQUEST['page_size']);
		$_REQUEST['do'] = empty($_REQUEST['do']) ? $_REQUEST['do']= 'search' : $_REQUEST['do'];
		$_REQUEST['p'] = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
		$current_page = $_REQUEST['p'];
		
		# 统计维度
		if ('by_seats' == $_REQUEST['statistical_dimension'] || empty($_REQUEST['statistical_dimension'])) {
			$_REQUEST['statistical_dimension'] = 'by_seats';
		} else {
			$_REQUEST['statistical_dimension'] = 'by_department';
		}		
		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);
		
		// 限制查询日期不能超过 3 个月
		$timestr=strtotime($_REQUEST['todate'])-strtotime($_REQUEST['fromdate']);
		if($timestr>93*24*60*60){
			goBack(c('查询的日期不能超过3个月.'));
		}
		
		// 报表显示的表头			
		$cfg_column = array(	
			'dept_id'		=> array(
				'name'  => c('部门'),
				'type'	=> 'label',
				'width' => 100
			),
			'extension'		=> array(
				'name'  => c('座席'),
				'type'	=> 'label',
				'width' => 100
			),
			'time_range'	=> array(
				'name'  => c('查询周期'),
				'type'	=> 'label',
				'width' => 120
			),			
			'cnt'			=> array(
				'name'  => c('质检数'),
				'type'	=> 'number',
				'width' => 90
			),
			'number_of_calls'	=> array(
				'name'  => c('通话次数'),
				'type'	=> 'number',
				'width' => 120
			),			
			'quality_proportion'	=> array(
				'name'  => c('质检比例'),
				'type'	=> 'label',
				'width' => 120
			),
			'cnt_passed'	=> array(
				'name'  => c('通过数'),
				'type'	=> 'number',
				'width' => 90
			),
			'cnt_notpass'	=> array(
				'name'  => c('不通过数'),
				'type'	=> 'number',
				'width' => 90
			),
			'accuracy'		=> array(
				'name'  => c('通过率'),
				'type'	=> 'label',
				'width' => 80
			),
			'f_1'	=> array(
				'name'  => c('服务态度平均评分'),
				'type'	=> 'number',
				'width' => 80
			),
			'f_2'	=> array(
				'name'  => c('服务用语平均评分'),
				'type'	=> 'number',
				'width' => 80
			),
			'f_3'	=> array(
				'name'  => c('业务能力平均评分'),
				'type'	=> 'number',
				'width' => 80
			),
			'f_4'	=> array(
				'name'  => c('沟通技巧平均评分'),
				'type'	=> 'number',
				'width' => 80
			),
			'f_7'	=> array(
				'name'  => c('录单信息平均评分'),
				'type'	=> 'number',
				'width' => 80
			),
			'quality_score'	=> array(
				'name'  => c('平均评分'),
				'type'	=> 'number',
				'width' => 80
			),
			'ded_score'			=> array(
				'name'  => c('平均扣分'),
				'type'	=> 'number',
				'width' => 80
			),
			'fatal_errors'	=> array(
				'name'  => c('致命差错数'),
				'type'	=> 'number',
				'width' => 80
			),
			'common_errors'	=> array(
				'name'  => c('普通差错数'),
				'type'	=> 'number',
				'width' => 80
			),
		);
		$this->Tmpl['cfg_column'] = $cfg_column;
		
		
		/************ 获得部门列表 start **************/
		if (1 != $_SESSION['userinfo']['power']) {
			$manager_dept = $this->getManageDept($_SESSION['userinfo_detail']['user_id']);
			$sql = "SELECT * FROM org_department where dept_id = '".$_SESSION['userinfo_detail']['dept_id']."'";
			if (!empty($manager_dept)) {
				$sql .= " or find_in_set(dept_id, '".implode(',', $manager_dept)."') > 0";
			}
		} else {
			$sql = "SELECT * FROM org_department";
		}
		$dept = $db->GetAll( $sql );
		
		$depart_id=$_REQUEST['depart_id'];
		//提供部门选择end
		$deptOptions = $this->getCateOption( $dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;
		/************ 获得部门列表 end **************/
		
		// 呼叫类型
		$callType = array(0=>array('call_type'=> 4,'call_type_name' => c('全部')),1=>array('call_type'=> 1,'call_type_name' => c('呼入')),2=>array('call_type'=> 2,'call_type_name' => c('呼出')));
		$this->Tmpl['callType'] = $callType;
		
		/************* 查询客服质检方案 和  组合通过的条件 start *******************/
		$sql = "select * from stdout_quality_plan where is_enable=1 and is_deleted=0 and is_build=1 and is_build_again=0 and is_kehu=1";
		$kehuPlan = $db->GetRow($sql);
		$qpid=$kehuPlan['id'];
		if (empty($qpid) && !empty($_REQUEST['do'])) {
			goBack(c('查询失败：系统未配置客服质检方案, 或请检查质检方案是否已启用及生成.'));
		}
		if($kehuPlan['standard_score']<>'' || $kehuPlan['standard_err_fatal']<>'' || $kehuPlan['standard_err_common']<>''){//判断质检是否通过的条件
			//$standardFlag=1;
				$pass_tmp="sum("; 
			if($kehuPlan['standard_score']<>'')
			{
                $kehuPlan['standard_score'] = trim($kehuPlan['standard_score'],',');
				$pass_std[]=" IF(quality_score>".$kehuPlan['standard_score'].",1,0)";
			}
			if($kehuPlan['standard_err_fatal']<>'')
			{
                $kehuPlan['standard_err_fatal'] = trim($kehuPlan['standard_err_fatal'],',');
				$pass_std[]=" IF(fatal_errors<".$kehuPlan['standard_err_fatal'].",1,0)";
			}
			if($kehuPlan['standard_err_common']<>'')
			{
                $kehuPlan['standard_err_common'] = trim($kehuPlan['standard_err_common'],',');
				$pass_std[]=" IF(common_errors<".$kehuPlan['standard_err_common'].",1,0)";
			}
			$pass_tmp.=implode(' AND ',$pass_std)." ) as cnt_passed";
			$where_pass=' ,'.$pass_tmp;
		}else{
			//$standardFlag=0;
			$where_pass="";
		}
		/************* 查询客服质检方案 和  组合通过的条件 end *******************/
		
		
		
		/************* 组合查询条件 start *******************/
		$where = " WHERE record_number<>'' ";
		
		if (isset($_REQUEST['fromdate']) && !empty($_REQUEST['fromdate'])) { // 质检时间(起)条件
			$where .= " AND create_time>='{$_REQUEST['fromdate']} 00:00:00' ";
		}

		if (isset($_REQUEST['todate']) && !empty($_REQUEST['todate'])) { // 质检时间(止)条件
			$where .= " AND create_time<='{$_REQUEST['todate']} 23:59:59' ";
		}
		if (isset($_REQUEST['fromdate_cdr']) && !empty($_REQUEST['fromdate_cdr'])) { // 通话时间起
			$where .= " AND task_create_time>='{$_REQUEST['fromdate_cdr']}' ";
		}
		if (isset($_REQUEST['todate_cdr']) && !empty($_REQUEST['todate_cdr'])) { // 通话时间止
			$where .= " AND task_create_time<='{$_REQUEST['todate_cdr']}' ";
		}
		
		if(!empty($_REQUEST['call_type']) && $_REQUEST['call_type'] != 4){
			$where .= " AND call_type = {$_REQUEST['call_type']} ";
		}
		
		//根据”部门条件“组合条件
		$extenSelect = array();
		if (!empty($depart_id)) {
			$exten_arr = array();
			//获取所有的子部门
			$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
			$list_depart .= "$depart_id";	//加上所选部门

			//获取所选部门的座席列表
			$sql = "SELECT extension, user_name FROM org_user WHERE find_in_set(dept_id, '$list_depart') > 0 and extension != '' and extension is not null";
			if (1 != $_SESSION['userinfo']['power']) {
				$arr_deptid = $this->getManageDept();
				$sql .= " and (find_in_set(dept_id, '".implode(',', $arr_deptid)."') > 0 or extension = '".$_SESSION['userinfo']['extension']."')";
			}
			$rs	= $db->Execute($sql);
			while(!$rs->EOF) {
				$extenSelect[] = $rs->fields['extension'];
				$exten_arr[] = $rs->fields;
				$rs->MoveNext();
			}
			$list_exten = numberToString4Sql(implode(',', $extenSelect));

			$this->Tmpl['extenSelect'] = $exten_arr;
		} elseif (1 != $_SESSION['userinfo']['power']) {
			$arr_exten = array();
			$list_exten = "";
			$arr_deptid = array();
			$list_depart = "";
			$arr_deptid = $this->getManageDept();
			if (count($arr_deptid) == 0) $arr_deptid[] = 0;
			$list_depart = implode(',', $arr_deptid);

			$arr_exten = $this->getManageUserExten();
			if (count($arr_exten) == 0) $arr_exten[] = 0;
			$list_exten = numberToString4Sql(implode(',', $arr_exten));
		} else {
			//void
		}
		
		if($statistical_dimension == 'by_seats'){
			//座席工号查找
			if (!empty($_REQUEST['extension'])) {
				$where	.= " and extension='".$_REQUEST['extension']."' ";
			}else if (!empty($list_exten)) {
				$where .= " and extension in ($list_exten) ";
			}
		}
		
		/************* 组合查询条件 end *******************/

		
		$list = array();	// 数据结果

		global $cache_department;
		if (!empty($_REQUEST['do'])) {

			$qualityTable = 'stdout_quality_record_' . $qpid; //设置质检方案表名
			
			$arr_depart = array();
			if (empty($depart_id)){
				$depart_id = 0;
			} else {
				$depart_id = intval($depart_id);
			}
			
			/******************* 若按部门查询，根据搜索条件中的 dept_id 来查处它所对应的下一级部门的 id. start ************************/
			//admin超级管理员
			if (1 == $_SESSION['userinfo']['power']) {
				foreach ($cache_department as $val) {
					if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
				}
				//所选部门没有子部门, 只显示所选(条件中的)部门
				if ($depart_id != 0 && count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
				//print_r($depart_id);
			} else {	//非admin管理员
				if (0 == $depart_id) {
					$arr_depart = $this->getManageDirectDept();
				} else if (in_array($depart_id, $arr_deptid)) {
					foreach ($cache_department as $val) {
						if ($val['dept_parent'] == $depart_id) $arr_depart[] = $val['dept_id'];
					}
					//所选部门没有子部门, 只显示所选(条件中的)部门
					if (count($arr_depart) == 0) $arr_depart[] = $cache_department[$depart_id]['dept_id'];
				}
			}
			if (!empty($depart_id)) $arr_depart[] = $depart_id;
			
			$arr_depart = array_unique($arr_depart);	// 对部门id 去重
			/******************* 若按部门查询，根据搜索条件中的 dept_id 来查处它所对应的下一级部门的 id. end ************************/

			# 统计步长
			if ('all' == $step || empty($step)) {
				$_REQUEST['step'] = 'all';
			} else if ('day' == $step) {
				$_REQUEST['step'] = 'day';
				$time = " SUBSTRING(create_time, 1, 10) ";
			} else if ('hour' == $step) {
				$time = " SUBSTRING(create_time, 12, 2) ";
			} else if ('week' == $step) {
				$time = " WEEK(create_time,1) ";
			} else if ('month' == $step) {
				$time = " SUBSTRING(create_time, 1, 7) ";
			}
			
				// 按坐席查询
				if($statistical_dimension == 'by_seats'){

					//计算每个通话记录的平均值后，再用来计算每个座席的平均值
					if($step == 'all' || empty($step)){
						$sql ="select dept_id,extension, count(0) as cnt,avg(quality_score) as quality_score ,avg(ded_score) as ded_score,sum(fatal_errors) as fatal_errors,sum(common_errors) as common_errors {$where_pass},avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7
							from 
							(select dept_id,extension, avg(quality_score) as quality_score, avg(ded_score) as ded_score,avg(fatal_errors) as fatal_errors, avg(common_errors) as common_errors,avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7 from {$qualityTable} {$where} group by record_number) as t 
							GROUP BY t.extension ASC ";
					}else{
						$sql ="select time, `year`,create_time,dept_id,extension, count(0) as cnt,avg(quality_score) as quality_score ,avg(ded_score) as ded_score,sum(fatal_errors) as fatal_errors,sum(common_errors) as common_errors {$where_pass},avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7
							from 
							(select $time AS time, date_format(create_time,'%Y') AS year,create_time,dept_id,extension, avg(quality_score) as quality_score, avg(ded_score) as ded_score,avg(fatal_errors) as fatal_errors, avg(common_errors) as common_errors,avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7 from {$qualityTable} {$where} group by record_number) as t
							GROUP BY t.time DESC ,t.extension ASC ";
					}
					
					if ('search' == $_REQUEST['do']) {
						//获取总记录数(用于分页)
						$sql_page_num = "SELECT COUNT(*) FROM ( " .$sql ." ) AS t1";
						$record_nums = $db->GetOne($sql_page_num);
						$pg = loadClass('tool','page',$this);
						$pg->setPageVar('p');
						$pg->setNumPerPage( $page_size );

						$currentPage = $_REQUEST['p'];
						unset($_REQUEST['p']);
						unset($_REQUEST['action']);
						unset($_REQUEST['module']);
						unset($_REQUEST['btn_search']);
						unset($_REQUEST['PHPSESSID']);

						$pg->setVar($_REQUEST);
						$pg->setVar(array("module"=>"report", "action"=>"cdrQualityTotal"));
						$pg->set($record_nums,$currentPage);
						$this->Tmpl['record_nums'] = $record_nums;
						$this->Tmpl['show_pages'] = $pg->output(1);
					}
					
					if ('search' == $_REQUEST['do']) {
						if (!$rs = $db->SelectLimit($sql, $pg->getNumPerPage(), $pg->getOffset())) {
							echo $sql . "<br/><br/>";
							echo $db->ErrorMsg();
							$db->Close();
							exit();
						}
					}
					else {
						if (!$rs = $db->Execute($sql)) {
							echo $sql . "<br/><br/>";
							echo $db->ErrorMsg();
							$db->Close();
							exit();
						}
					}

					$arr_total = array(
						'cnt'			=> 0,
						'cnt_passed'	=> 0,
						'cnt_notpass'	=> 0,
						'quality_score'	=> 0,
						'ded_score'		=> 0,
						'fatal_errors'	=> 0,
						'common_errors'	=> 0,
					);

					while (!$rs->EOF) {
						# 部门
						$rs->fields['dept_id'] = $cache_department[$rs->fields['dept_id']]['dept_name'];
						$rs->fields['cnt_notpass']=$rs->fields['cnt']-$rs->fields['cnt_passed'];
						
						//时间范围(周期)
						$rs->fields['time_range'] = substr($_REQUEST['fromdate'], 5) . c(' 至 ') . substr($_REQUEST['todate'], 5);

						$rs->fields['quality_score'] = round($rs->fields['quality_score'], 2);
						$rs->fields['ded_score'] = round($rs->fields['ded_score'], 2);
						$rs->fields['fatal_errors'] = round($rs->fields['fatal_errors'], 2);
						$rs->fields['common_errors'] = round($rs->fields['common_errors'], 2);
						$rs->fields['f_1'] = round($rs->fields['f_1'], 2);
						$rs->fields['f_2'] = round($rs->fields['f_2'], 2);
						$rs->fields['f_3'] = round($rs->fields['f_3'], 2);
						$rs->fields['f_4'] = round($rs->fields['f_4'], 2);
						$rs->fields['f_5'] = round($rs->fields['f_5'], 2);
						$rs->fields['f_7'] = round($rs->fields['f_7'], 2);
						// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
						if($step == 'week'){
							$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['time']);
							$rs->fields['time'] = $week_time['start'].c('至').$week_time['end'];
						}		

						//计算每条记录的通过率
						$accuracy = '0';
						if ($rs->fields['cnt']>0) $accuracy = round(($rs->fields['cnt_passed']/$rs->fields['cnt'])*100, 1) . '%';
						$rs->fields['accuracy'] = $accuracy;

						//合计
						$arr_total['cnt'] += $rs->fields['cnt'];
						$arr_total['cnt_passed'] += $rs->fields['cnt_passed'];
						$arr_total['cnt_notpass'] += $rs->fields['cnt_notpass'];
						$arr_total['quality_score'] += $rs->fields['quality_score'];
						$arr_total['ded_score'] += $rs->fields['ded_score'];
						$arr_total['fatal_errors'] += $rs->fields['fatal_errors'];
						$arr_total['common_errors'] += $rs->fields['common_errors'];
						$arr_total['f_1'] += $rs->fields['f_1'];
						$arr_total['f_2'] += $rs->fields['f_2'];
						$arr_total['f_3'] += $rs->fields['f_3'];
						$arr_total['f_4'] += $rs->fields['f_4'];
						$arr_total['f_5'] += $rs->fields['f_5'];
						$arr_total['f_7'] += $rs->fields['f_7'];

						$list[] = $rs->fields;
						$rs->MoveNext();
					}
					
					/******************** 添加'通话次数'和'质检比例' start *****************/
					// 查询时间范围内的通话记录表
					$table_list = $this->getCdrTableList(ASTERISKCDRDB_DB_NAME);
					
					if(empty($table_list)){
						$table_list[] = 'ss_cdr_cdr_info';
					}
					
					// 按坐席
					$agent_extension = '';		// 坐席分机号
					foreach($list as $key => $value){
						$agent_extension .= $value['extension'] . ',';
					}
					$agent_extension = rtrim($agent_extension,',');
					
					$condition = " ";
					if (isset($_REQUEST['fromdate']) && !empty($_REQUEST['fromdate'])) { 
						$condition .= " AND start_stamp>='{$_REQUEST['fromdate']} 00:00:00' ";
					}
					if (isset($_REQUEST['todate']) && !empty($_REQUEST['todate'])) {
						$condition .= " AND start_stamp<='{$_REQUEST['todate']} 23:59:59' ";
					}
		
					// 如果选择呼入/呼出，则只查询呼入/呼出的通话次数，
					if(!empty($_REQUEST['call_type']) && $_REQUEST['call_type'] != 4){
						$condition .= " AND call_type = {$_REQUEST['call_type']} AND agent_sec > 0 ";
					}
					
					// 组合查询 “通话次数” 的 sql
					$sql_num_calls = "SELECT agent_number,SUM(number_of_calls) AS number_of_calls FROM (";
					foreach($table_list as $table){
						$sql_num_calls .= " SELECT agent_number,COUNT(0) AS number_of_calls FROM `$table` WHERE agent_number IN($agent_extension) $condition GROUP BY agent_number UNION ALL";  
					}
					$sql_num_calls =  rtrim($sql_num_calls,'UNION ALL');
					$sql_num_calls .= ") AS t1 GROUP BY agent_number";
					//echo $sql_num_calls;
					$re_num_calls = $db -> GetALl($sql_num_calls);
					
					// 将分机号相同的数组合并到一起
					foreach($list as $key => $value){
						foreach($re_num_calls as $k => $v){
							if($value['extension'] == $v['agent_number']){
								 $list_all[] = $value+$v;
							}
						}
					}
					$list = $list_all;
					
					// 质检比例
					foreach($list as $key => $value){
						$list[$key]['quality_proportion'] = number_format($value['cnt']/$value['number_of_calls'],4)  * 100 . '%';
						$exten_tmp=$this->getNameByExten($list[$key]['extension']);
						$list[$key]['extension'] = $exten_tmp? $exten_tmp. '(' . $list[$key]['extension'] . ')':$list[$key]['extension'];//被质检的座席		
					}
					/******************** 添加'通话次数'和'质检比例' end *****************/
				
					//处理合计数组
					if (count($list) > 1) {
						$i = count($list);
						$arr_total['quality_score'] = round($arr_total['quality_score'] / $i, 2);
						$arr_total['ded_score'] = round($arr_total['ded_score'] / $i, 2);

						//计算合计数组的准确率
						$accuracy = '0';
						if ($arr_total['cnt']>0) $accuracy = round(($arr_total['cnt_passed']*100/$arr_total['cnt']), 1) . '%';
						$arr_total['accuracy'] = $accuracy;
						
						// 通话次数
						foreach($list as $key => $value){
							$arr_total['number_of_calls'] += $value['number_of_calls'];
						}
						// 质检比例
						$arr_total['quality_proportion'] =  number_format($arr_total['cnt']/$arr_total['number_of_calls'],4)  * 100 . '%';
					}
				}
				
				/*********************** add by daicr 2017/11/10 按部门和统计维度筛选 start ***********************/
				if($statistical_dimension == 'by_department'){ 	 
					$record_nums = 0;
					$arr = array(); //此数组用于避免最底层的部门重复显示
					
					// 合计数组
					$arr_total = array(
						'cnt'			=> 0,
						'cnt_passed'	=> 0,
						'cnt_notpass'	=> 0,
						'quality_score'	=> 0,
						'ded_score'		=> 0,
						'fatal_errors'	=> 0,
						'common_errors'	=> 0,
					);
					
					foreach($arr_depart as $key => $value){
						// $list = array();
						if (in_array($value, $arr)) continue;
							$arr[] = $value;
						//var_dump($value);echo"&emsp;&emsp;&emsp;&emsp;&emsp;";
						$list_depart = $this->getNodeChild($dept, $value, 'dept');
						$list_depart .= $value; //加上所选部门
						$list_depart = $depart_id == $value ? $value : $list_depart;
						
						//根据部门获取坐席
						$sql_extension = "select extension from org_user where dept_id in ({$list_depart})";
						$extens = $db->GetAll($sql_extension);
							$arrExtens = array();
							foreach($extens as $v){
								if(!empty($v['extension'])){
									$arrExtens[] = $v['extension'];
								}
							}
						$strExtens = implode(',',$arrExtens);
						$strExtens = empty($strExtens) ? "''": $strExtens;
						//计算每个通话记录的平均值后，再用来计算每个座席的平均值
						if($step == 'all' || empty($step)){
							
							$sql ="select dept_id,extension, count(0) as cnt,avg(quality_score) as quality_score ,avg(ded_score) as ded_score,sum(fatal_errors) as fatal_errors,sum(common_errors) as common_errors {$where_pass},avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7
								from 
								(select dept_id,extension, avg(quality_score) as quality_score, avg(ded_score) as ded_score,avg(fatal_errors) as fatal_errors, avg(common_errors) as common_errors,avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7 from {$qualityTable} {$where} AND extension IN($strExtens) group by record_number) as t ";
						}else{
							$sql ="select time, year,create_time,dept_id,extension, count(0) as cnt,avg(quality_score) as quality_score ,avg(ded_score) as ded_score,sum(fatal_errors) as fatal_errors,sum(common_errors) as common_errors {$where_pass},avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7
								from 
								(select $time AS time, date_format(create_time,'%Y') AS year,create_time,dept_id,extension, avg(quality_score) as quality_score, avg(ded_score) as ded_score,avg(fatal_errors) as fatal_errors, avg(common_errors) as common_errors,avg(f_1) as f_1,avg(f_2) as f_2,avg(f_3) as f_3,avg(f_4) as f_4,avg(f_5) as f_5,avg(f_7) as f_7 from {$qualityTable} {$where} group by record_number) as t
								GROUP BY t.time DESC ";
						}
						
						
						if ('search' == $_REQUEST['do']) {
							//获取总记录数(用于分页)
							$sql_page_num = "SELECT COUNT(*) FROM ( " .$sql ." ) AS t1";
							//echo $sql_page_num;echo "</br></br>";
							$record_nums += $db->GetOne($sql_page_num);
							//var_dump($record_nums);
							$pg = loadClass('tool','page',$this);
							$pg->setPageVar('p');
							$pg->setNumPerPage( $page_size );

							$currentPage = $_REQUEST['p'];
							unset($_REQUEST['p']);
							unset($_REQUEST['action']);
							unset($_REQUEST['module']);
							unset($_REQUEST['btn_search']);
							unset($_REQUEST['PHPSESSID']);

							$pg->setVar($_REQUEST);
							$pg->setVar(array("module"=>"report", "action"=>"cdrQualityTotal"));
							$pg->set($record_nums,$currentPage);
							$this->Tmpl['record_nums'] = $record_nums;
							$this->Tmpl['show_pages'] = $pg->output(1);
						}
						
						if (!$rs = $db->Execute($sql)) {
							echo $sql . "<br/><br/>";
							echo $db->ErrorMsg();
							$db->Close();
							exit();
						}

						while (!$rs->EOF) {
							# 部门
							$rs->fields['deparment'] = $value;		// 本次查询的部门，作为后面合并数组的关联关系
							$rs->fields['dept_id'] = $cache_department[$rs->fields['deparment']]['dept_name'];
							$rs->fields['cnt_notpass']=$rs->fields['cnt']-$rs->fields['cnt_passed'];
							
							//时间范围(周期)
							$rs->fields['time_range'] = substr($_REQUEST['fromdate'], 5) . c(' 至 ') . substr($_REQUEST['todate'], 5);

							$rs->fields['quality_score'] = round($rs->fields['quality_score'], 2);
							$rs->fields['ded_score'] = round($rs->fields['ded_score'], 2);
							$rs->fields['fatal_errors'] = round($rs->fields['fatal_errors'], 2);
							$rs->fields['common_errors'] = round($rs->fields['common_errors'], 2);
							$rs->fields['f_1'] = round($rs->fields['f_1'], 2);
							$rs->fields['f_2'] = round($rs->fields['f_2'], 2);
							$rs->fields['f_3'] = round($rs->fields['f_3'], 2);
							$rs->fields['f_4'] = round($rs->fields['f_4'], 2);
							$rs->fields['f_5'] = round($rs->fields['f_5'], 2);
							$rs->fields['f_7'] = round($rs->fields['f_7'], 2);
							// 如果按周统计，该“周”在本年中的显示格式为XX月XX日至XX月XX日
							if($step == 'week'){
								$week_time = $this -> getWeekStartAndEnd($rs->fields['year'],$rs->fields['time']);
								$rs->fields['time'] = $week_time['start'].c('至').$week_time['end'];
							}	
							
							//计算每条记录的通过率
							$accuracy = '0';
							if ($rs->fields['cnt']>0) $accuracy = round(($rs->fields['cnt_passed']/$rs->fields['cnt'])*100,1) . '%';
							$rs->fields['accuracy'] = $accuracy;

							//合计
							$arr_total['cnt'] += intval($rs->fields['cnt']);
							$arr_total['cnt_passed'] += $rs->fields['cnt_passed'];
							$arr_total['cnt_notpass'] += $rs->fields['cnt_notpass'];
							$arr_total['quality_score'] += $rs->fields['quality_score'];
							$arr_total['ded_score'] += $rs->fields['ded_score'];
							$arr_total['fatal_errors'] += $rs->fields['fatal_errors'];
							$arr_total['common_errors'] += $rs->fields['common_errors'];
							$arr_total['f_1'] += $rs->fields['f_1'];
							$arr_total['f_2'] += $rs->fields['f_2'];
							$arr_total['f_3'] += $rs->fields['f_3'];
							$arr_total['f_4'] += $rs->fields['f_4'];
							$arr_total['f_5'] += $rs->fields['f_5'];
							$arr_total['f_7'] += $rs->fields['f_7'];

							$list[] = $rs->fields;
							$rs->MoveNext();
						}
						
						/******************** 添加'通话次数'和'质检比例' start *****************/
						// 查询时间范围内的通话记录表
						$table_list = $this->getCdrTableList(ASTERISKCDRDB_DB_NAME);
						
						if(empty($table_list)){
							$table_list[] = 'ss_cdr_cdr_info';
						}
						
						$condition = " ";
						if (isset($_REQUEST['fromdate']) && !empty($_REQUEST['fromdate'])) { 
							$condition .= " AND start_stamp>='{$_REQUEST['fromdate']} 00:00:00' ";
						}
						if (isset($_REQUEST['todate']) && !empty($_REQUEST['todate'])) {
							$condition .= " AND start_stamp<='{$_REQUEST['todate']} 23:59:59' ";
						}
			
						// 如果选择呼入/呼出，则只查询呼入/呼出的通话次数，
						if(!empty($_REQUEST['call_type']) && $_REQUEST['call_type'] != 4){
							$condition .= " AND call_type = {$_REQUEST['call_type']} AND agent_sec > 0 ";
						}
					
						// 组合查询 “通话次数” 的 sql
						$sql_num_calls = "SELECT agent_number,SUM(number_of_calls) AS number_of_calls FROM (";
						foreach($table_list as $table){
							$sql_num_calls .= " SELECT agent_number,COUNT(0) AS number_of_calls FROM `$table` WHERE agent_number IN($strExtens) $condition GROUP BY agent_number UNION ALL";  
						}
						$sql_num_calls =  rtrim($sql_num_calls,'UNION ALL');
						$sql_num_calls .= ") AS t1 ";

						$re_num_calls = $db -> GetALl($sql_num_calls);
						foreach($re_num_calls as $k => $v){
							$re_num_calls[$k]['deparment'] = $value;
						}
						
						// 将分机号相同的数组合并到一起
						foreach($list as $val){
							foreach($re_num_calls as $v){
								if($val['deparment'] == $v['deparment']){
									 $list_all[] = $val+$v;
								}
							}
						}
						//var_dump($list_all); echo "</br></br>";
						$list = $list_all;
						
						// 质检比例
						foreach($list as $key => $val){
							$list[$key]['quality_proportion'] = number_format($val['cnt']/$val['number_of_calls'],4)  * 100 . '%';
							$exten_tmp=$this->getNameByExten($list[$key]['extension']);
							$list[$key]['extension'] = $exten_tmp? $exten_tmp. '(' . $list[$key]['extension'] . ')':$list[$key]['extension'];//被质检的座席		
						}
						/******************** 添加'通话次数'和'质检比例' end *****************/
					
						//处理合计数组
						if (count($list) > 1) {
							$i = count($list);
							$arr_total['quality_score'] = round($arr_total['quality_score'] / $i, 2);
							$arr_total['ded_score'] = round($arr_total['ded_score'] / $i, 2);

							//计算合计数组的准确率
							$accuracy = '0';
							if ($arr_total['cnt']>0) $accuracy = round(($arr_total['cnt_passed']*100/$arr_total['cnt']), 1) . '%';
							$arr_total['accuracy'] = $accuracy;
						}
						
						// 通话次数
						foreach($list as $key => $val){
							if(!empty($val['cnt']) && $val['deparment'] == $value){
								$arr_total['number_of_calls'] += $val['number_of_calls'];
							}
						}
						// 质检比例
						$arr_total['quality_proportion'] =  number_format($arr_total['cnt']/$arr_total['number_of_calls'],4)  * 100 . '%';
						
					}
					
					// 如果质检数为 0 ，则不显示该条记录
					foreach($list as $key => $val){
						if($val['cnt'] == 0){
							unset($list[$key]);
						}
					}
					
					// 如果按步长查询，则最后做一个时间从现在到以前的排序
					if($step == 'month' || $step == 'day' || $step == 'hour'){
						$list = $this -> arraySort($list,'time');
					}elseif($step == 'week' ){
						$list = $this -> arraySort($list,'create_time');
					}
					
					$offset = ($current_page-1) * $page_size;
					$list = array_slice($list,$offset,$page_size);
				}
				
			// var_dump($list);
			/*********************** add by daicr 2017/11/10 按部门和统计维度筛选 end ***********************/
				
			//导出excel
			if ('export' == $_REQUEST['do']) {
				//导出表头
				ob_end_clean();
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=order_quality_total.xls ");
				header("Content-Transfer-Encoding: binary ");

				xlsBOF();

				//第一行, 输出“导出时间”
				$export_time = date('Y-m-d H:i:s');
				xlsWriteLabel(0, 0, '导出时间:');
				xlsWriteLabel(0, 1, $export_time);
				
				if ($_REQUEST['step'] == 'day') {
					$step_name = c('日期');
				} else if ($_REQUEST['step'] == 'hour') {
					$step_name = c('小时');
				} else if ($_REQUEST['step'] == 'week') {
					$step_name = c('周');
				} else if ($_REQUEST['step'] == 'month') {
					$step_name = c('月份');
				}
				
				if($step_name){
					$xls_columns = array(
						'time' => array(
							'name' => $step_name,
						)
					);
					$cfg_column = $xls_columns + $cfg_column;
				}

				//
				//第二行, 输出标准的列、详细的“订单类型2”及“任务子状态”
				//
				//输出标准表头
				$cols = 0;
				
				foreach ($cfg_column as $key => $val){
					if($statistical_dimension == 'by_department' && $key == 'dept_id'){	
						xlsWriteLabel(1, $cols, iconv("UTF-8", "GBK", $val['name']));		// 如果按部门查询，不导出坐席
					}else{
						xlsWriteLabel(1, $cols++, iconv("UTF-8", "GBK", $val['name']));
					}
				} 
				
				//
				//第三行, 输出详细数据
				//
				$rows = 2;
				foreach ($list as $fields)
				{
					$cols = 0;
					
					//按cfg_column的配置项导出
					foreach ($cfg_column as $key => $val)
					{
						if ('number' == $val['type']) {
							xlsWriteNumber($rows, $cols++, $fields[$key]);
						}
						else {
							$str = iconv("UTF-8", "GBK//IGNORE", $fields[$key]);
							if($statistical_dimension == 'by_department' && $key == 'dept_id'){	
								xlsWriteLabel($rows, $cols, $str);
							}else{
								xlsWriteLabel($rows, $cols++, $str);
							}
						}
					} //end foreach ($cfg_column as $key => $val)

					$rows ++;
				}

				//
				//第三行, 输出合计数组
				//
				if (count($list)>1) {
					if($statistical_dimension == 'by_department'){	
						xlsWriteLabel($rows, 1, '合计：');
						$cols = 2;
					}else{
						xlsWriteLabel($rows, 2, '合计：');
						$cols = 3;
					}
					//按cfg_column的配置项导出
					foreach ($cfg_column as $key => $val)
					{
						if ('dept_id' == $key || 'extension' == $key || 'time_range' == $key) continue;

						if ('number' == $val['type']) {
							xlsWriteNumber($rows, $cols++, $arr_total[$key]);
						}
						else {
							$str = iconv("UTF-8", "GBK//IGNORE", $arr_total[$key]);
							xlsWriteLabel($rows, $cols++, $str);
						}
					} //end foreach ($cfg_column as $key => $val)
				} // end if (count($list)>1)

				xlsEOF();
				exit;

			} // end if ('export' == $_REQUEST['do'])
		}

		$this->Tmpl['list'] = $list;
		$this->Tmpl['arr_total'] = $arr_total;
		$this->Tmpl['disabled_extension'] = $disabled_extension;
		$this->display();
	}

	//录单次数(坐席)
	public function recordCount($condition,$step=''){
		$db = $this->loadDB();
		//查件 订单 问题件 投诉 咨询 理赔 催件 贷款  服务记录
		$arrTable = array('se_query','se_pick','se_trouble','se_complaint','se_consultation','se_claims','se_hasten','se_payment','se_service');
		$arrData = array();
		if($step){
			foreach($arrTable as $val){
				$sql = "SELECT create_time,count(0) as num,extension,$step as tdate  FROM {$val} where {$condition} group by extension,$step";
				$arr = $db->GetAll($sql);
				foreach($arr as $v){
					$arrData[$v['extension']][$v['tdate']] += $v['num'];
				}
			}
		}else{
			foreach($arrTable as $val){
				$sql = "SELECT count(0) as num,extension FROM {$val} where {$condition} group by extension";
				$arr = $db->GetAll($sql);
				foreach($arr as $v){
					$arrData[$v['extension']] += $v['num'];
				}
			}
		}

		return $arrData;
	}

	//录单次数(班组)
	public function groupRecordCount($extens,$condition,$step=''){
		$db = $this->loadDB();
		//查件 订单 问题件 投诉 咨询 理赔 催件 贷款
		$arrTable = array('se_query','se_pick','se_trouble','se_complaint','se_consultation','se_claims','se_hasten','se_payment','se_service');
		$arrData = array();
		if($step){
			foreach($arrTable as $val){
				$sql = "SELECT create_time,count(0) as num,$step as tdate  FROM {$val} where {$condition} and extension in ($extens) group by $step";
				$arr = $db->GetAll($sql);
				foreach($arr as $v){
					$arrData[$v['tdate']] += $v['num'];
				}
			}

			//var_dump($arrData);
			return $arrData;
		}else{
			$record = 0;
			foreach($arrTable as $val){
				$sql = "SELECT count(0) as num FROM {$val} where {$condition} and extension in ($extens)";
				$num = $db->GetOne($sql);
				$record += $num;
			}
			return $record;
		}

	}

	/**
	 * @Purpose		: 显示处理人查件催件列表
	 * @Author		: 代传荣（Carroll）
	 * @Method		: handlePersonQueryHastenReport()
	 * @Parameters	: (无)
	 * @Return 		: (无)
	 */
	public function showhandlePersonQueryHastenReport()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu($_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv); # 获取导航菜单
		$this->isAuth('handle_person_query_hasten_report_sel', $arr_local_priv, '您没有查看处理人查件催件报表的权限！');

		# 设置初始值
		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time());
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';
		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll($sql);

		//提供部门选择
		$deptOptions = $this->getCateOption($dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		$list = array();        						// 存放分页后查询的结果集
		$list_query = array();        					// 存放分页后query的结果集
		$list_hasten = array();        					// 存放分页后hasten的结果集
		$list_public = array();        					// 存放分页后query表和hasten表公共的结果集
		$appoint_query_extension = array();				// 分页后查件表用户分机号
		$appoint_hasten_extension = array();			// 分页后催件表用户分机号
		$appoint_list_extension = array();				// 分页后数据存放在一起后用户分机号
		$arr_total = array();							// 当前页的数据统计

		// 在满足查询条件的情况下
		$list_query_all = array();        				// 存放query的所有结果集
		$list_hasten_all = array();        				// 存放hasten的所有结果集
		$list_public_all = array();        				// 存放query表和hasten表公共的所有结果集
		$appoint_query_extension_all = array();			// 查件表所有用户分机号
		$appoint_hasten_extension_all = array();		// 催件表所有用户分机号

		$arr_list_tmp = array();						// 存放分页后查询的临时结果集，有子部门时使用
		$arr_total_tmp = array();						// 当前页的数据统计，有子部门时使用

		if ($change_header != '1') {
			$query = unserialize($_COOKIE['cfg_report_header']['query']);
			if (!is_array($query)) {
				$query = array(
					'query_quantity',                    // '查件量',
					'query_handle_time',            	 // '查件处理时长',
					'query_handle_average_time',    	 // '查件处理均长',
					'query_complete_30min',              // '查件30分钟内完成数',
					'query_complete_30min_rate',         // '查件30分钟内完成率',
				);
			}
			$_REQUEST['query'] = $query;
		}

		if ($change_header != '1') {
			$hasten = unserialize($_COOKIE['cfg_report_header']['hasten']);
			if (!is_array($hasten)) {
				$hasten = array(
					'hasten_quantity',                    	// '催件量',
					'hasten_handle_time',            		// '催件处理时长',
					'hasten_handle_average_time',    		// '催件处理均长',
					'hasten_complete_20min',            	// '催件20分钟内完成数',
					'hasten_complete_20min_rate',        	// '催件20分钟内完成率',
				);
			}
			$_REQUEST['hasten'] = $hasten;
		}

		if ($change_header != '1') {
			$total = unserialize($_COOKIE['cfg_report_header']['total']);
			if (!is_array($total)) {
				$total = array(
					'total_quantity',                    // '查询量',
					'total_handle_time',            	 // '处理时长',
					'total_handle_average_time',    	 // '处理均长',
				);
			}
			$_REQUEST['total'] = $total;
		}

		if (empty($_REQUEST['query'])) $_REQUEST['query'] = array();
		if (empty($_REQUEST['hasten'])) $_REQUEST['hasten'] = array();
		if (empty($_REQUEST['total'])) $_REQUEST['total'] = array();

		# 设置 cookie 是为了当在选项中选择了显示哪些列后，可以保存在cookie中，当刷新页面不至于搜选择的列丢失
		setcookie("cfg_report_header[query]", serialize($_REQUEST['query']), time() + 86400 * 1);        //cookie默认有效时间为1天
		setcookie("cfg_report_header[hasten]", serialize($_REQUEST['hasten']), time() + 86400 * 1);
		setcookie("cfg_report_header[total]", serialize($_REQUEST['total']), time() + 86400 * 1);

		//表头(字段)配置
		$cfg_column = array(
			'query' => array(
				'query_quantity' => '查件量',
				'query_handle_time' => '处理时长',
				'query_handle_average_time' => '处理均长',
				'query_complete_30min' => '30分钟内完成数',
				'query_complete_30min_rate' => '30分钟内完成率',
			),
			'hasten' => array(
				'hasten_quantity' => '催件量',
				'hasten_handle_time' => '处理时长',
				'hasten_handle_average_time' => '处理均长',
				'hasten_complete_20min' => '20分钟内完成数',
				'hasten_complete_20min_rate' => '20分钟内完成率',
			),
			'total' => array(
				'total_quantity' => '查询量',
				'total_handle_time' => '处理时长',
				'total_handle_average_time' => '处理均长',
			),
		);

		// 当前页合计数组
		$arr_total = array(
			'query_quantity' => '0',
			'query_handle_time' => '0',
			'query_handle_average_time' => '0',
			'query_complete_30min' => '0',
			'query_complete_30min_rate' => '0',
			'hasten_quantity' => '0',
			'hasten_handle_time' => '0',
			'hasten_handle_average_time' => '0',
			'hasten_complete_20min' => '0',
			'hasten_complete_20min_rate' => '0',
			'total_quantity' => '0',
			'total_handle_time' => '0',
			'total_handle_average_time' => '0',
		);

		# 定义一个没有数据的查件数组
		$query_empty = array(
			"query_quantity" =>"0",
			"query_handle_time" =>"0",
			"query_handle_average_time" => '0',
			"query_complete_30min" => "0" ,
			"query_complete_30min_rate" => "0%",
		);

		# 定义一个没有数据的催件数组
		$hasten_empty = array(
			"hasten_quantity" =>"0",
			"hasten_handle_time" =>"0",
			"hasten_handle_average_time" => '0',
			"hasten_complete_20min" => "0" ,
			"hasten_complete_20min_rate" => "0%",
		);

		if ('' != $do) {
			# 按统计步长
			if ('all' == $step || empty($step)) {
				$_REQUEST['step'] = 'all';
			} else if ('day' == $step) {
				$_REQUEST['step'] = 'day';
				$time = " SUBSTRING(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'), 1, 10) ";
			} else if ('hour' == $step) {
				$time = " SUBSTRING(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'), 12, 2) ";
			} else if ('week' == $step) {
				$time = " WEEK(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'),1) ";
			} else if ('month' == $step) {
				$time = " SUBSTRING(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'), 1, 7) ";
			}

			# 按统计维度
			if ('by_seats' == $statistical_dimension || empty($statistical_dimension)) {
				$_REQUEST['statistical_dimension'] = 'by_seats';
			} else {
				$_REQUEST['statistical_dimension'] = 'by_department';
			}

			# 如果选择了部门没选坐席，根据部门进行查询，否则按坐席查询
			if(! empty($depart_id) && empty($extension)){
				$depart_ids = $this->getNextChildDepartId($depart_id);		// 获取该部门的子部门
				# 若该部门没有子部门
				if($depart_ids == $depart_id){
					$depart_id = $depart_id;

					// 若选择了统计步长，则将统计步长加入查询条件
					if($time){
						$sql_query_select = "SELECT COUNT(*) AS query_quantity ,$time as time,GROUP_CONCAT(id) as qids, appoint_extension,SUM(handle_time) AS query_handle_time,(SUM(handle_time)/COUNT(*)) AS query_handle_average_time ";
						$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,$time as time,GROUP_CONCAT(id) as hids, appoint_extension,SUM(handle_time) AS hasten_handle_time,(SUM(handle_time)/COUNT(*)) AS hasten_handle_average_time ";
					}else{
						$sql_query_select = "SELECT COUNT(*) AS query_quantity ,GROUP_CONCAT(id) as qids,appoint_extension,SUM(handle_time) AS query_handle_time,(SUM(handle_time)/COUNT(*)) AS query_handle_average_time ";
						$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,GROUP_CONCAT(id) as hids,appoint_extension,SUM(handle_time) AS hasten_handle_time,(SUM(handle_time)/COUNT(*)) AS hasten_handle_average_time ";
					}

					$sql_from_query = $sql_query_select ." FROM se_query ";			// 从查件表中查询
					$sql_from_hasten = $sql_hasten_select ." FROM se_hasten ";		// 从催件表中查询

					# 根据起止时间组合查询条件
					if (empty($fromdate) && empty($todate)) {
						$from_date = strtotime('-1 week');
						$to_date = time();
						$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
					}
					if (empty($fromdate) && !empty($todate)) {
						$todate = $todate . ' 23:59:59';
						$from_date = strtotime('-1 week');
						$to_date = strtotime($todate);
						$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
					}
					if (!empty($fromdate) && empty($todate)) {
						$from_date = strtotime($fromdate);
						$to_date = time();
						$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
					}
					if (!empty($fromdate) && !empty($todate)) {
						$todate = $todate . ' 23:59:59';
						$fromdate = strtotime($fromdate);
						$todate = strtotime($todate);
						$sql_condition .= " WHERE create_time BETWEEN"." '$fromdate' AND '$todate'";
					}

					# 根据部门组合查询条件
					if(! empty($depart_id) && empty($extension)){
						$depart_ids = $this->getChildDepartId($depart_id);		// 获取该部门的子部门
						$depart_ids = ltrim($depart_ids,',');
						$sql_extension = "SELECT GROUP_CONCAT(extension) as extension FROM org_user WHERE dept_id IN($depart_ids)";
						$result_extension = $db -> GetOne($sql_extension);
						if($result_extension){
							$sql_condition .= " AND appoint_extension IN($result_extension) ";
						}else{
							$sql_condition .= " AND appoint_extension IN('') ";
						}
					}

					# 根据坐席组合查询条件
					if(! empty($extension)) {
						$sql_condition .= " AND appoint_extension = $extension ";
					}
					if($time){
						if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
							$sql_groupby = 'GROUP BY time DESC ';
						}else{
							$sql_groupby = " GROUP BY time DESC ,appoint_extension ";
						}
					}else{
						if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
							$sql_groupby = '';
						}else{
							$sql_groupby = " GROUP BY appoint_extension ";
						}
					}
					# 查件表的查询 sql
					$sql_query = $sql_from_query . $sql_condition .$sql_groupby;
//					echo $sql_query; echo '</br>';
					# 催件表的查询 sqdisabled_extensionl
					$sql_hasten = $sql_from_hasten . $sql_condition .$sql_groupby;
					// echo $sql_hasten;

					/******************* 计算总记录数 start *******************/
					$result_query_all = $db -> GetAll($sql_query);
					$result_hasten_all = $db -> GetAll($sql_hasten);
					foreach ($result_query_all as $key => $value) {
						if(empty($value['appoint_extension'])){
							unset($result_query_all[$key]);
						}
					}
					foreach ($result_hasten_all as $key => $value) {
						if(empty($value['appoint_extension'])){
							unset($result_hasten_all[$key]);
						}
					}

					// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
					if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
						$result_query_all[0]['appoint_extension'] = $result_hasten_all[0]['appoint_extension'];
					}

					# 将所有用户的分机号保存到一个数组里面去
					foreach($result_query_all as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_query_extension_all,$value['appoint_extension']);
							}else{
								array_push($appoint_query_extension_all,$value['appoint_extension'].$value['time']);
							}
						}
					}

					foreach($result_hasten_all as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_hasten_extension_all,$value['appoint_extension']);
							}else{
								array_push($appoint_hasten_extension_all,$value['appoint_extension'].$value['time']);
							}
						}
					}
					# 将 $result_query 的 key 换成 坐席工号
					$result_query_all = array_combine($appoint_query_extension_all,$result_query_all);
					$result_hasten_all = array_combine($appoint_hasten_extension_all,$result_hasten_all);

					$result_query_all = empty($result_query_all) ? array(array()) : $result_query_all ;
					$result_hasten_all = empty($result_hasten_all) ? array(array()) : $result_hasten_all ;

					# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
					foreach($result_query_all as $key => $value){
						foreach($result_hasten_all as $k => $v){
							if($key == $k){
								$list_public_all[$key] = array_merge($value,$v);
							}else{
								$list_query_all[$key] = array_merge($value,$hasten_empty);
								$list_hasten_all[$k] = array_merge($query_empty,$v);
								if(empty($key)){
									unset($list_query_all[$key]);
								}if(empty($k)){
									unset($list_hasten_all[$k]);
								}
							}
						}
					}

					# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
					foreach($list_public_all as $key => $value){
						foreach($list_query_all as $k2 => $v2){
							if($key == $k2){
								unset($list_query_all[$k2]);
							}
						}
						foreach($list_hasten_all as $k3 => $v3){
							if($key == $k3){
								unset($list_hasten_all[$k3]);
							}
						}

					}

					# 合并这三个数组
					$list_all = array_merge($list_public_all,$list_query_all,$list_hasten_all);
					$total_count = count($list_all);
					/******************* 计算总记录数 end *******************/

					# 加载分页类
					$pg = loadClass('tool','page',$this);
					$pg -> setPageVar('p');       // 页数传递变量
					$pg -> setNumPerPage(20);		// 每页显示 10 条
					unset($_REQUEST['p']);
					$pg -> setVar($_REQUEST);     // 将请求的参数进行 url 编码
					$this -> tmpl['allowRows'] = $total_count;
					$pg -> set($total_count);		// 分页设置，传入总记录数即可，当前页数默认会自动读取
					$this -> Tmpl['show_pages'] = $pg -> output(1);

					# 查件表的查询结果
					$object_query = $db -> SelectLimit($sql_query, $pg->getNumPerPage(), $pg->getOffset());
					if($object_query == false){
						echo $db->ErrorMsg();
						$db->close();
						exit;
					}else{
						while(!$object_query->EOF){
							$result_query[] = $object_query -> fields;
							$object_query -> moveNext();
						}
					}

					# 催件表的查询结果
					$object_hasten = $db -> SelectLimit($sql_hasten, $pg->getNumPerPage(), $pg->getOffset());
					if($object_hasten == false){
						echo $db->ErrorMsg();
						$db->close();
						exit;
					}else{
						while(!$object_hasten->EOF){
							$result_hasten[] = $object_hasten -> fields;
							$object_hasten -> moveNext();
						}
					}

					// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
					if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
						$result_hasten[0]['appoint_extension'] = $result_query[0]['appoint_extension'];
					}

					# 将用户的分机号保存到一个数组里面去
					foreach($result_query as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_query_extension,$value['appoint_extension']);
							}else{
								array_push($appoint_query_extension,$value['appoint_extension'].$value['time']);
							}
						}
					}

					foreach($result_hasten as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_hasten_extension,$value['appoint_extension']);
							}else{
								array_push($appoint_hasten_extension,$value['appoint_extension'].$value['time']);
							}
						}
					}

					# 处理查件查询结果
					foreach($result_query as $key => $value){
						if(empty($value['appoint_extension'])){
							unset($result_query[$key]);
						}else{
							// 获取处理人所在部门
							$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
							$depart_name = $db -> GetOne($depart_sql);
							$result_query[$key]['dept_name'] = $depart_name;
							$result_query[$key]['query_handle_average_time'] = round($value['query_handle_average_time']);

							// 获取 30 分钟完成数
							$sql_query_30min = $sql_from_query . $sql_condition." AND handle_time <= 30 AND id IN($value[qids])";
							$reuslt_query_30min = $db -> GetOne($sql_query_30min);
							$query_complete_30min_rate = number_format($reuslt_query_30min/$value['query_quantity'],4)  * 100 . '%';
							$result_query[$key]['query_complete_30min'] = empty($reuslt_query_30min) ? 0 : $reuslt_query_30min;
							$result_query[$key]['query_complete_30min_rate'] = empty($query_complete_30min_rate) ? 0 : $query_complete_30min_rate;

							// 若为空则都显示 0
							$result_query[$key]['query_quantity'] = empty($value['query_quantity']) ? 0 : $value['query_quantity'];
							$result_query[$key]['query_handle_time'] = empty($value['query_handle_time']) ? 0 : $value['query_handle_time'];
						}
					}

					# 处理催件查询结果
					foreach($result_hasten as $key => $value){
						if(empty($value['appoint_extension'])){
							unset($result_hasten[$key]);
						}else{
							// 获取处理人所在部门
							$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
							$depart_name = $db -> GetOne($depart_sql);
							$result_hasten[$key]['dept_name'] = $depart_name;
							$result_hasten[$key]['hasten_handle_average_time'] = round($value['hasten_handle_average_time']);

							// 获取 20 分钟完成数和完成率
							$sql_hasten_20min = $sql_from_hasten . $sql_condition." AND handle_time <= 20 AND id IN($value[hids])";
							$reuslt_hasten_20min = $db -> GetOne($sql_hasten_20min);
							$hasten_complete_20min_rate = number_format($reuslt_hasten_20min/$value['hasten_quantity'],4)  * 100 . '%';
							$result_hasten[$key]['hasten_complete_20min'] = empty($reuslt_hasten_20min) ? 0 : $reuslt_hasten_20min;
							$result_hasten[$key]['hasten_complete_20min_rate'] = empty($hasten_complete_20min_rate) ? 0 : $hasten_complete_20min_rate;

							// 若为空则都显示 0
							$result_hasten[$key]['hasten_quantity'] = empty($value['hasten_quantity']) ? 0 : $value['hasten_quantity'];
							$result_hasten[$key]['hasten_handle_time'] = empty($value['hasten_handle_time']) ? 0 : $value['hasten_handle_time'];
						}
					}

					# 将 $result_query 的 key 换成 坐席工号
					$result_query = array_combine($appoint_query_extension,$result_query);
					$result_hasten = array_combine($appoint_hasten_extension,$result_hasten);

					$result_query = empty($result_query) ? array(array()) : $result_query ;
					$result_hasten = empty($result_hasten) ? array(array()) : $result_hasten ;

					# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
					foreach($result_query as $key => $value){
						foreach($result_hasten as $k => $v){
							if($key == $k){
								$list_public[$key] = array_merge($value,$v);
							}else{
								$list_query[$key] = array_merge($value,$hasten_empty);
								$list_hasten[$k] = array_merge($query_empty,$v);
								if(empty($key)){
									unset($list_query[$key]);
								}if(empty($k)){
									unset($list_hasten[$k]);
								}
							}
						}
					}

					# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
					foreach($list_public as $key => $value){
						foreach($list_query as $k2 => $v2){
							if($key == $k2){
								unset($list_query[$k2]);
							}
						}
						foreach($list_hasten as $k3 => $v3){
							if($key == $k3){
								unset($list_hasten[$k3]);
							}
						}

					}

					# 合并这三个数组
					$list = array_merge($list_public,$list_query,$list_hasten);

					# 将用户的分机号保存到一个数组里面去
					foreach($list as $key => $value){
						if(! in_array($value['time'],$value)){
							array_push($appoint_list_extension,$value['appoint_extension']);
						}else{
							array_push($appoint_list_extension,$value['time'].$value['appoint_extension']);
						}
					}

					foreach($list as $key => $value){
						// 查询处理人的中文名
						$appoint_extension_name =  $this -> getNameByExten($value['appoint_extension']);
						$list[$key]['appoint_extension'] = $appoint_extension_name.'('.$value['appoint_extension'].')';

						# 该坐席总查件量、总处理时长、总处理均长
						$list[$key]['total_quantity'] = $value['query_quantity'] + $value['hasten_quantity'];
						$list[$key]['total_handle_time'] = $value['query_handle_time'] + $value['hasten_handle_time'];
						$list[$key]['total_handle_average_time'] =  round($list[$key]['total_handle_time']/$list[$key]['total_quantity']);
					}

					# 将 $list 的 key 换成 坐席工号
					$list = array_combine($appoint_list_extension,$list);
					krsort($list);

					# 计算当前页的合计
					foreach($list as $key => $value){
						if(empty($key)){
							unset($list[$key]);
						}
						$arr_total['query_quantity'] += $value['query_quantity'];
						$arr_total['query_handle_time'] += $value['query_handle_time'];
						$arr_total['query_handle_average_time'] = round($arr_total['query_handle_time']/$arr_total['query_quantity']);
						$arr_total['query_complete_30min'] += $value['query_complete_30min'];
						$arr_total['query_complete_30min_rate'] = number_format($arr_total['query_complete_30min']/$arr_total['query_quantity'],4)  * 100 . '%';

						$arr_total['hasten_quantity'] += $value['hasten_quantity'];
						$arr_total['hasten_handle_time'] += $value['hasten_handle_time'];
						$arr_total['hasten_handle_average_time'] =  round($arr_total['hasten_handle_time']/$arr_total['hasten_quantity']);
						$arr_total['hasten_complete_20min'] += $value['hasten_complete_20min'];
						$arr_total['hasten_complete_20min_rate'] = number_format($arr_total['hasten_complete_20min']/$arr_total['hasten_quantity'],4)  * 100 . '%';

						$arr_total['total_quantity'] += $value['total_quantity'];
						$arr_total['total_handle_time'] += $value['total_handle_time'];
						$arr_total['total_handle_average_time'] = round($arr_total['total_handle_time']/$arr_total['total_quantity']);
					}
				}else{
					# 若该部门有子部门，则按子部门分别汇总
					$arr_departs = explode(',',$depart_ids);
					$depart_parent_id = $depart_id;
					array_push($arr_departs,$depart_parent_id);
					foreach($arr_departs as $key => $value){
						$depart_id = $value;
						// 若选择了统计步长，则将统计步长加入查询条件
						if($time){
							$sql_query_select = "SELECT COUNT(*) AS query_quantity ,$time as time,GROUP_CONCAT(id) as qids, appoint_extension,SUM(handle_time) AS query_handle_time,(SUM(handle_time)/COUNT(*)) AS query_handle_average_time ";
							$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,$time as time,GROUP_CONCAT(id) as hids, appoint_extension,SUM(handle_time) AS hasten_handle_time,(SUM(handle_time)/COUNT(*)) AS hasten_handle_average_time ";
						}else{
							$sql_query_select = "SELECT COUNT(*) AS query_quantity ,GROUP_CONCAT(id) as qids,appoint_extension,SUM(handle_time) AS query_handle_time,(SUM(handle_time)/COUNT(*)) AS query_handle_average_time ";
							$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,GROUP_CONCAT(id) as hids,appoint_extension,SUM(handle_time) AS hasten_handle_time,(SUM(handle_time)/COUNT(*)) AS hasten_handle_average_time ";
						}

						$sql_from_query = $sql_query_select ." FROM se_query ";			// 从查件表中查询
						$sql_from_hasten = $sql_hasten_select ." FROM se_hasten ";		// 从催件表中查询

						# 查询条件
						$sql_condition = '';

						# 如果是时间戳，则换成 年 - 月 -日 格式
						if(is_numeric($fromdate)){
							$fromdate = date('Y-m-d',$fromdate);
						}
						if(is_numeric($todate)){
							$todate = date('Y-m-d',$todate);
						}

						# 根据起止时间组合查询条件
						if (empty($fromdate) && empty($todate)) {
							$from_date = strtotime('-1 week');
							$to_date = time();
							$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
						}
						if (empty($fromdate) && !empty($todate)) {
							$todate = $todate . ' 23:59:59';
							$from_date = strtotime('-1 week');
							$to_date = strtotime($todate);
							$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
						}
						if (!empty($fromdate) && empty($todate)) {
							$from_date = strtotime($fromdate);
							$to_date = time();
							$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
						}
						if (!empty($fromdate) && !empty($todate)) {
							$todate = $todate . ' 23:59:59';
							$fromdate = strtotime($fromdate);
							$todate = strtotime($todate);
							$sql_condition .= " WHERE create_time BETWEEN"." '$fromdate' AND '$todate'";
						}

						# 根据部门组合查询条件
						if(! empty($depart_id) && empty($extension)){
							$depart_ids = $this->getChildDepartId($depart_id);		// 获取该部门的子部门
							$depart_ids = ltrim($depart_ids,',');
                            $depart_ids .= ','.$depart_id;
							if($depart_id == $depart_parent_id){
								$sql_extension = "SELECT GROUP_CONCAT(extension) as extension FROM org_user WHERE dept_id IN($depart_parent_id)";
							}else{
								$sql_extension = "SELECT GROUP_CONCAT(extension) as extension FROM org_user WHERE dept_id IN($depart_ids)";
							}
							$result_extension = $db -> GetOne($sql_extension);
							if($result_extension){
								$sql_condition .= " AND appoint_extension IN($result_extension) ";
							}else{
								$sql_condition .= " AND appoint_extension IN('') ";
							}
						}

						# 根据坐席组合查询条件
						if(! empty($extension)) {
							$sql_condition .= " AND appoint_extension = $extension ";
						}
						if($time){
							if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
								$sql_groupby = 'GROUP BY time DESC';
							}else{
								$sql_groupby = " GROUP BY time DESC ,appoint_extension ";
							}
						}else{
							if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
								$sql_groupby = '';
							}else{
								$sql_groupby = " GROUP BY appoint_extension ";
							}
						}
						# 查件表的查询 sql
						$sql_query = $sql_from_query . $sql_condition .$sql_groupby;
						// echo $sql_query; echo "</br>";

						# 催件表的查询 sqdisabled_extensionl
						$sql_hasten = $sql_from_hasten . $sql_condition .$sql_groupby;
						// echo $sql_hasten;

						// 由于此处需要循环处理，所以每次选好处理之前都需要将数组清空。
						$list = array();        						// 存放分页后查询的结果集
						$list_query = array();        					// 存放分页后query的结果集
						$list_hasten = array();        					// 存放分页后hasten的结果集
						$list_public = array();        					// 存放分页后query表和hasten表公共的结果集
						$appoint_query_extension = array();				// 分页后查件表用户分机号
						$appoint_hasten_extension = array();			// 分页后催件表用户分机号
						$appoint_list_extension = array();				// 分页后数据存放在一起后用户分机号
						$arr_total = array();							// 当前页的数据统计
						
						// 在满足查询条件的情况下
						$list_query_all = array();        				// 存放query的所有结果集
						$list_hasten_all = array();        				// 存放hasten的所有结果集
						$list_public_all = array();        				// 存放query表和hasten表公共的所有结果集
						$appoint_query_extension_all = array();			// 查件表所有用户分机号
						$appoint_hasten_extension_all = array();		// 催件表所有用户分机号

		
						/******************* 计算总记录数 start *******************/
						$total_count = count($arr_departs);
						/******************* 计算总记录数 end *******************/

						# 加载分页类
						$pg = loadClass('tool','page',$this);
						$pg -> setPageVar('p');       // 页数传递变量
						$pg -> setNumPerPage(20);		// 每页显示 10 条
						unset($_REQUEST['p']);
						$pg -> setVar($_REQUEST);     // 将请求的参数进行 url 编码
						$this -> tmpl['allowRows'] = $total_count;
						$pg -> set($total_count);		// 分页设置，传入总记录数即可，当前页数默认会自动读取
						$this -> Tmpl['show_pages'] = $pg -> output(1);

						# 查件表的查询结果
						$object_query = $db -> SelectLimit($sql_query, $pg->getNumPerPage(), $pg->getOffset());
						if($object_query == false){
							echo $db->ErrorMsg();
							$db->close();
							exit;
						}else{
							while(!$object_query->EOF){
								$result_query[] = $object_query -> fields;
								$object_query -> moveNext();
							}
						}

						# 催件表的查询结果
						$object_hasten = $db -> SelectLimit($sql_hasten, $pg->getNumPerPage(), $pg->getOffset());
						if($object_hasten == false){
							echo $db->ErrorMsg();
							$db->close();
							exit;
						}else{
							while(!$object_hasten->EOF){
								$result_hasten[] = $object_hasten -> fields;
								$object_hasten -> moveNext();
							}
						}

						// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
						if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
							foreach($result_query as $key => $value){
								foreach($result_hasten as $k => $v){
									if($key == $k){
										$result_hasten[$k]['appoint_extension'] = $result_query[$key]['appoint_extension'];
									}
								}
							}
						}

						# 将用户的分机号保存到一个数组里面去
						foreach($result_query as $key => $value){
							if(! empty($value['appoint_extension'])){
								if(! in_array($value['time'],$value)){
									array_push($appoint_query_extension,$value['appoint_extension']);
								}else{
									array_push($appoint_query_extension,$value['appoint_extension'].$value['time']);
								}
							}
						}

						foreach($result_hasten as $key => $value){
							if(! empty($value['appoint_extension'])){
								if(! in_array($value['time'],$value)){
									array_push($appoint_hasten_extension,$value['appoint_extension']);
								}else{
									array_push($appoint_hasten_extension,$value['appoint_extension'].$value['time']);
								}
							}
						}

						# 处理查件查询结果
                                                //var_dump($result_query);
						foreach($result_query as $key => $value){
							if(empty($value['appoint_extension'])){
								unset($result_query[$key]);
							}else{
								// 获取处理人所在部门
								$depart_ids = $this->getNextChildDepartId($depart_id);		// 获取该部门的子部门
								# 若该部门没有子部门
								if($depart_ids == $depart_id){
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
								}else{
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = $depart_id";
								}
								$depart_name = $db -> GetOne($depart_sql);
								if($result_query[$key]['dept_name']){
									$result_query[$key]['dept_name'] = $result_query[$key]['dept_name'];
								}else{
									$result_query[$key]['dept_name'] = $depart_name;
								}
								$result_query[$key]['query_handle_average_time'] = round($value['query_handle_average_time']);


								// 获取 30 分钟完成数
								$value['qids'] = rtrim($value['qids'],',');
								$sql_query_30min = $sql_from_query . $sql_condition." AND handle_time <= 30 AND id IN($value[qids])";
								$reuslt_query_30min = $db -> GetOne($sql_query_30min);
								$query_complete_30min_rate = number_format($reuslt_query_30min/$value['query_quantity'],4)  * 100 . '%';

								if($result_query[$key]['query_complete_30min']){
									$result_query[$key]['query_complete_30min'] = $result_query[$key]['query_complete_30min'];
								}else{
									$result_query[$key]['query_complete_30min'] = empty($reuslt_query_30min) ? 0 : $reuslt_query_30min;
								}
								if($result_query[$key]['query_complete_30min_rate']){
									$result_query[$key]['query_complete_30min_rate'] = $result_query[$key]['query_complete_30min_rate'];
								}else{
									$result_query[$key]['query_complete_30min_rate'] = empty($query_complete_30min_rate) ? 0 : $query_complete_30min_rate;
								}

								// 若为空则都显示 0
								$result_query[$key]['query_quantity'] = empty($value['query_quantity']) ? 0 : $value['query_quantity'];
								$result_query[$key]['query_handle_time'] = empty($value['query_handle_time']) ? 0 : $value['query_handle_time'];
							}
						}

						# 处理催件查询结果
						foreach($result_hasten as $key => $value){
							if(empty($value['appoint_extension'])){
								unset($result_hasten[$key]);
							}else{
								// 获取处理人所在部门
								$depart_ids = $this->getNextChildDepartId($depart_id);		// 获取该部门的子部门
								# 若该部门没有子部门
								if($depart_ids == $depart_id){
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
								}else{
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = $depart_id";
								}
								$depart_name = $db -> GetOne($depart_sql);
								if($result_hasten[$key]['dept_name']){
									$result_hasten[$key]['dept_name'] = $result_hasten[$key]['dept_name'];
								}else {
									$result_hasten[$key]['dept_name'] = $depart_name;
								}
								$result_hasten[$key]['hasten_handle_average_time'] = round($value['hasten_handle_average_time']);

								// 获取 20 分钟完成数和完成率
								$value['hids'] = rtrim($value['hids'],',');
								$sql_hasten_20min = $sql_from_hasten . $sql_condition." AND handle_time <= 20 AND id IN($value[hids])";
								$reuslt_hasten_20min = $db -> GetOne($sql_hasten_20min);
								$hasten_complete_20min_rate = number_format($reuslt_hasten_20min/$value['hasten_quantity'],4)  * 100 . '%';

								if($result_hasten[$key]['hasten_complete_20min']){
									$result_hasten[$key]['hasten_complete_20min'] = $result_hasten[$key]['hasten_complete_20min'];
								}else {
									$result_hasten[$key]['hasten_complete_20min'] = empty($reuslt_hasten_20min) ? 0 : $reuslt_hasten_20min;
								}
								if($result_hasten[$key]['hasten_complete_20min_rate']){
									$result_hasten[$key]['hasten_complete_20min_rate'] = $result_hasten[$key]['hasten_complete_20min_rate'];
								}else{
									$result_hasten[$key]['hasten_complete_20min_rate'] = empty($hasten_complete_20min_rate) ? 0 : $hasten_complete_20min_rate;
								}

								// 若为空则都显示 0
								$result_hasten[$key]['hasten_quantity'] = empty($value['hasten_quantity']) ? 0 : $value['hasten_quantity'];
								$result_hasten[$key]['hasten_handle_time'] = empty($value['hasten_handle_time']) ? 0 : $value['hasten_handle_time'];
							}
						}

						# 将 $result_query 的 key 换成 坐席工号
						$result_query = array_combine($appoint_query_extension,$result_query);
						$result_hasten = array_combine($appoint_hasten_extension,$result_hasten);

						$result_query = empty($result_query) ? array(array()) : $result_query ;
						$result_hasten = empty($result_hasten) ? array(array()) : $result_hasten ;

						# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
						foreach($result_query as $key => $value){
							foreach($result_hasten as $k => $v){
								if($key == $k){
									$list_public[$key] = array_merge($value,$v);
								}else{
									$list_query[$key] = array_merge($value,$hasten_empty);
									$list_hasten[$k] = array_merge($query_empty,$v);
									if(empty($key)){
										unset($list_query[$key]);
									}if(empty($k)){
										unset($list_hasten[$k]);
									}
								}
							}
						}

						# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
						foreach($list_public as $key => $value){
							foreach($list_query as $k2 => $v2){
								if($key == $k2){
									unset($list_query[$k2]);
								}
							}
							foreach($list_hasten as $k3 => $v3){
								if($key == $k3){
									unset($list_hasten[$k3]);
								}
							}

						}

						# 合并这三个数组
						$list = array_merge($list_public,$list_query,$list_hasten);
						
						# 将用户的分机号保存到一个数组里面去
						foreach($list as $key => $value){
							if(! in_array($value['time'],$value)){
								array_push($appoint_list_extension,$value['appoint_extension']);
							}else{
								array_push($appoint_list_extension,$value['time'].$value['appoint_extension']);
							}
						}

						foreach($list as $key => $value){
							// 查询处理人的中文名
							$appoint_extension_name =  $this -> getNameByExten($value['appoint_extension']);
							$list[$key]['appoint_extension'] = $appoint_extension_name.'('.$value['appoint_extension'].')';

							# 该坐席总查件量、总处理时长、总处理均长
							$list[$key]['total_quantity'] = $value['query_quantity'] + $value['hasten_quantity'];
							$list[$key]['total_handle_time'] = $value['query_handle_time'] + $value['hasten_handle_time'];
							$list[$key]['total_handle_average_time'] =  round($list[$key]['total_handle_time']/$list[$key]['total_quantity']);
						}

						# 将 $list 的 key 换成 坐席工号
						$list = array_combine($appoint_list_extension,$list);
						krsort($list);

						# 计算当前页的合计
						foreach($list as $key => $value){
							if(empty($key)){
								unset($list[$key]);
							}
							$arr_total['query_quantity'] += $value['query_quantity'];
							$arr_total['query_handle_time'] += $value['query_handle_time'];
							$arr_total['query_handle_average_time'] = round($arr_total['query_handle_time']/$arr_total['query_quantity']);
							$arr_total['query_complete_30min'] += $value['query_complete_30min'];
							$arr_total['query_complete_30min_rate'] = number_format($arr_total['query_complete_30min']/$arr_total['query_quantity'],4)  * 100 . '%';

							$arr_total['hasten_quantity'] += $value['hasten_quantity'];
							$arr_total['hasten_handle_time'] += $value['hasten_handle_time'];
							$arr_total['hasten_handle_average_time'] =  round($arr_total['hasten_handle_time']/$arr_total['hasten_quantity']);
							$arr_total['hasten_complete_20min'] += $value['hasten_complete_20min'];
							$arr_total['hasten_complete_20min_rate'] = number_format($arr_total['hasten_complete_20min']/$arr_total['hasten_quantity'],4)  * 100 . '%';

							$arr_total['total_quantity'] += $value['total_quantity'];
							$arr_total['total_handle_time'] += $value['total_handle_time'];
							$arr_total['total_handle_average_time'] = round($arr_total['total_handle_time']/$arr_total['total_quantity']);
						}
					}

				}
			}else{
				
				// 若选择了统计步长，则将统计步长加入查询条件
				if($time){
					$sql_query_select = "SELECT COUNT(*) AS query_quantity ,$time as time,GROUP_CONCAT(id) as qids, appoint_extension,SUM(handle_time) AS query_handle_time,(SUM(handle_time)/COUNT(*)) AS query_handle_average_time ";
					$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,$time as time,GROUP_CONCAT(id) as hids, appoint_extension,SUM(handle_time) AS hasten_handle_time,(SUM(handle_time)/COUNT(*)) AS hasten_handle_average_time ";
				}else{
					$sql_query_select = "SELECT COUNT(*) AS query_quantity ,GROUP_CONCAT(id) as qids,appoint_extension,SUM(handle_time) AS query_handle_time,(SUM(handle_time)/COUNT(*)) AS query_handle_average_time ";
					$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,GROUP_CONCAT(id) as hids,appoint_extension,SUM(handle_time) AS hasten_handle_time,(SUM(handle_time)/COUNT(*)) AS hasten_handle_average_time ";
				}

				$sql_from_query = $sql_query_select ." FROM se_query ";			// 从查件表中查询
				$sql_from_hasten = $sql_hasten_select ." FROM se_hasten ";		// 从催件表中查询

				# 根据起止时间组合查询条件
				if (empty($fromdate) && empty($todate)) {
					$from_date = strtotime('-1 week');
					$to_date = time();
					$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
				}
				if (empty($fromdate) && !empty($todate)) {
					$todate = $todate . ' 23:59:59';
					$from_date = strtotime('-1 week');
					$to_date = strtotime($todate);
					$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
				}
				if (!empty($fromdate) && empty($todate)) {
					$from_date = strtotime($fromdate);
					$to_date = time();
					$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
				}
				if (!empty($fromdate) && !empty($todate)) {
					$todate = $todate . ' 23:59:59';
					$fromdate = strtotime($fromdate);
					$todate = strtotime($todate);
					$sql_condition .= " WHERE create_time BETWEEN"." '$fromdate' AND '$todate'";
				}

				# 根据坐席组合查询条件
				if(! empty($extension)) {
					$sql_condition .= " AND appoint_extension = $extension ";
				}

				if($time){
					$sql_groupby = " GROUP BY time DESC ,appoint_extension ";
				}else{
					$sql_groupby = " GROUP BY appoint_extension ";
				}
				# 查件表的查询 sql
				$sql_query = $sql_from_query . $sql_condition .$sql_groupby;

				# 催件表的查询 sqdisabled_extensionl
				$sql_hasten = $sql_from_hasten . $sql_condition .$sql_groupby;
				// echo $sql_hasten;

				/******************* 计算总记录数 start *******************/
				$result_query_all = $db -> GetAll($sql_query);
				$result_hasten_all = $db -> GetAll($sql_hasten);
				foreach ($result_query_all as $key => $value) {
					if(empty($value['appoint_extension'])){
						unset($result_query_all[$key]);
					}
				}
				foreach ($result_hasten_all as $key => $value) {
					if(empty($value['appoint_extension'])){
						unset($result_hasten_all[$key]);
					}
				}

				// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
				if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
					$result_query_all[0]['appoint_extension'] = $result_hasten_all[0]['appoint_extension'];
				}

				# 将所有用户的分机号保存到一个数组里面去
				foreach($result_query_all as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_query_extension_all,$value['appoint_extension']);
						}else{
							array_push($appoint_query_extension_all,$value['appoint_extension'].$value['time']);
						}
					}
				}

				foreach($result_hasten_all as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_hasten_extension_all,$value['appoint_extension']);
						}else{
							array_push($appoint_hasten_extension_all,$value['appoint_extension'].$value['time']);
						}
					}
				}
				# 将 $result_query 的 key 换成 坐席工号
				$result_query_all = array_combine($appoint_query_extension_all,$result_query_all);
				$result_hasten_all = array_combine($appoint_hasten_extension_all,$result_hasten_all);

				$result_query_all = empty($result_query_all) ? array(array()) : $result_query_all ;
				$result_hasten_all = empty($result_hasten_all) ? array(array()) : $result_hasten_all ;

				# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
				foreach($result_query_all as $key => $value){
					foreach($result_hasten_all as $k => $v){
						if($key == $k){
							$list_public_all[$key] = array_merge($value,$v);
						}else{
							$list_query_all[$key] = array_merge($value,$hasten_empty);
							$list_hasten_all[$k] = array_merge($query_empty,$v);
							if(empty($key)){
								unset($list_query_all[$key]);
							}if(empty($k)){
								unset($list_hasten_all[$k]);
							}
						}
					}
				}

				# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
				foreach($list_public_all as $key => $value){
					foreach($list_query_all as $k2 => $v2){
						if($key == $k2){
							unset($list_query_all[$k2]);
						}
					}
					foreach($list_hasten_all as $k3 => $v3){
						if($key == $k3){
							unset($list_hasten_all[$k3]);
						}
					}

				}

				# 合并这三个数组
				$list_all = array_merge($list_public_all,$list_query_all,$list_hasten_all);
				$total_count = count($list_all);
				/******************* 计算总记录数 end *******************/

				# 加载分页类
				$pg = loadClass('tool','page',$this);
				$pg -> setPageVar('p');       // 页数传递变量
				$pg -> setNumPerPage(20);		// 每页显示 10 条
				unset($_REQUEST['p']);
				$pg -> setVar($_REQUEST);     // 将请求的参数进行 url 编码
				$this -> tmpl['allowRows'] = $total_count;
				$pg -> set($total_count);		// 分页设置，传入总记录数即可，当前页数默认会自动读取
				$this -> Tmpl['show_pages'] = $pg -> output(1);

				# 查件表的查询结果
				$object_query = $db -> SelectLimit($sql_query, $pg->getNumPerPage(), $pg->getOffset());
				if($object_query == false){
					echo $db->ErrorMsg();
					$db->close();
					exit;
				}else{
					while(!$object_query->EOF){
						$result_query[] = $object_query -> fields;
						$object_query -> moveNext();
					}
				}

				# 催件表的查询结果
				$object_hasten = $db -> SelectLimit($sql_hasten, $pg->getNumPerPage(), $pg->getOffset());
				if($object_hasten == false){
					echo $db->ErrorMsg();
					$db->close();
					exit;
				}else{
					while(!$object_hasten->EOF){
						$result_hasten[] = $object_hasten -> fields;
						$object_hasten -> moveNext();
					}
				}

				// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
				if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
					$result_hasten[0]['appoint_extension'] = $result_query[0]['appoint_extension'];
				}

				# 将用户的分机号保存到一个数组里面去
				foreach($result_query as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_query_extension,$value['appoint_extension']);
						}else{
							array_push($appoint_query_extension,$value['appoint_extension'].$value['time']);
						}
					}
				}

				foreach($result_hasten as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_hasten_extension,$value['appoint_extension']);
						}else{
							array_push($appoint_hasten_extension,$value['appoint_extension'].$value['time']);
						}
					}
				}

				# 处理查件查询结果
				foreach($result_query as $key => $value){
					if(empty($value['appoint_extension'])){
						unset($result_query[$key]);
					}else{
						// 获取处理人所在部门
						$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
						$depart_name = $db -> GetOne($depart_sql);
						$result_query[$key]['dept_name'] = $depart_name;
						$result_query[$key]['query_handle_average_time'] = round($value['query_handle_average_time']);

						// 获取 30 分钟完成数
						$sql_query_30min = $sql_from_query . $sql_condition." AND handle_time <= 30 AND id IN($value[qids])";
						$reuslt_query_30min = $db -> GetOne($sql_query_30min);
						$query_complete_30min_rate = number_format($reuslt_query_30min/$value['query_quantity'],4)  * 100 . '%';
						$result_query[$key]['query_complete_30min'] = empty($reuslt_query_30min) ? 0 : $reuslt_query_30min;
						$result_query[$key]['query_complete_30min_rate'] = empty($query_complete_30min_rate) ? 0 : $query_complete_30min_rate;

						// 若为空则都显示 0
						$result_query[$key]['query_quantity'] = empty($value['query_quantity']) ? 0 : $value['query_quantity'];
						$result_query[$key]['query_handle_time'] = empty($value['query_handle_time']) ? 0 : $value['query_handle_time'];
					}
				}

				# 处理催件查询结果
				foreach($result_hasten as $key => $value){
					if(empty($value['appoint_extension'])){
						unset($result_hasten[$key]);
					}else{
						// 获取处理人所在部门
						$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
						$depart_name = $db -> GetOne($depart_sql);
						$result_hasten[$key]['dept_name'] = $depart_name;
						$result_hasten[$key]['hasten_handle_average_time'] = round($value['hasten_handle_average_time']);

						// 获取 20 分钟完成数和完成率
						$sql_hasten_20min = $sql_from_hasten . $sql_condition." AND handle_time <= 20 AND id IN($value[hids])";
						$reuslt_hasten_20min = $db -> GetOne($sql_hasten_20min);
						$hasten_complete_20min_rate = number_format($reuslt_hasten_20min/$value['hasten_quantity'],4)  * 100 . '%';
						$result_hasten[$key]['hasten_complete_20min'] = empty($reuslt_hasten_20min) ? 0 : $reuslt_hasten_20min;
						$result_hasten[$key]['hasten_complete_20min_rate'] = empty($hasten_complete_20min_rate) ? 0 : $hasten_complete_20min_rate;

						// 若为空则都显示 0
						$result_hasten[$key]['hasten_quantity'] = empty($value['hasten_quantity']) ? 0 : $value['hasten_quantity'];
						$result_hasten[$key]['hasten_handle_time'] = empty($value['hasten_handle_time']) ? 0 : $value['hasten_handle_time'];
					}
				}

				# 将 $result_query 的 key 换成 坐席工号
				$result_query = array_combine($appoint_query_extension,$result_query);
				$result_hasten = array_combine($appoint_hasten_extension,$result_hasten);

				$result_query = empty($result_query) ? array(array()) : $result_query ;
				$result_hasten = empty($result_hasten) ? array(array()) : $result_hasten ;

				# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
				foreach($result_query as $key => $value){
					foreach($result_hasten as $k => $v){
						if($key == $k){
							$list_public[$key] = array_merge($value,$v);
						}else{
							$list_query[$key] = array_merge($value,$hasten_empty);
							$list_hasten[$k] = array_merge($query_empty,$v);
							if(empty($key)){
								unset($list_query[$key]);
							}if(empty($k)){
								unset($list_hasten[$k]);
							}
						}
					}
				}

				# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
				foreach($list_public as $key => $value){
					foreach($list_query as $k2 => $v2){
						if($key == $k2){
							unset($list_query[$k2]);
						}
					}
					foreach($list_hasten as $k3 => $v3){
						if($key == $k3){
							unset($list_hasten[$k3]);
						}
					}

				}

				# 合并这三个数组
				$list = array_merge($list_public,$list_query,$list_hasten);

				# 将用户的分机号保存到一个数组里面去
				foreach($list as $key => $value){
					if(! in_array($value['time'],$value)){
						array_push($appoint_list_extension,$value['appoint_extension']);
					}else{
						array_push($appoint_list_extension,$value['time'].$value['appoint_extension']);
					}
				}

				foreach($list as $key => $value){
					// 查询处理人的中文名
					$appoint_extension_name =  $this -> getNameByExten($value['appoint_extension']);
					$list[$key]['appoint_extension'] = $appoint_extension_name.'('.$value['appoint_extension'].')';

					# 该坐席总查件量、总处理时长、总处理均长
					$list[$key]['total_quantity'] = $value['query_quantity'] + $value['hasten_quantity'];
					$list[$key]['total_handle_time'] = $value['query_handle_time'] + $value['hasten_handle_time'];
					$list[$key]['total_handle_average_time'] =  round($list[$key]['total_handle_time']/$list[$key]['total_quantity']);
				}

				# 将 $list 的 key 换成 坐席工号
				$list = array_combine($appoint_list_extension,$list);
				krsort($list);

				# 计算当前页的合计
				foreach($list as $key => $value){
					if(empty($key)){
						unset($list[$key]);
					}
					$arr_total['query_quantity'] += $value['query_quantity'];
					$arr_total['query_handle_time'] += $value['query_handle_time'];
					$arr_total['query_handle_average_time'] = round($arr_total['query_handle_time']/$arr_total['query_quantity']);
					$arr_total['query_complete_30min'] += $value['query_complete_30min'];
					$arr_total['query_complete_30min_rate'] = number_format($arr_total['query_complete_30min']/$arr_total['query_quantity'],4)  * 100 . '%';

					$arr_total['hasten_quantity'] += $value['hasten_quantity'];
					$arr_total['hasten_handle_time'] += $value['hasten_handle_time'];
					$arr_total['hasten_handle_average_time'] =  round($arr_total['hasten_handle_time']/$arr_total['hasten_quantity']);
					$arr_total['hasten_complete_20min'] += $value['hasten_complete_20min'];
					$arr_total['hasten_complete_20min_rate'] = number_format($arr_total['hasten_complete_20min']/$arr_total['hasten_quantity'],4)  * 100 . '%';

					$arr_total['total_quantity'] += $value['total_quantity'];
					$arr_total['total_handle_time'] += $value['total_handle_time'];
					$arr_total['total_handle_average_time'] = round($arr_total['total_handle_time']/$arr_total['total_quantity']);
				}
			}

			# 根据"部门条件"组合条件
			$extenSelect = array();
			if (!empty($depart_id)) {
				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";    //加上所选部门
				//获取所选部门的座席列表
				$rs = $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while (!$rs->EOF) {
					$extenSelect[] = $rs->fields;
					$rs->MoveNext();
				}
				$this->Tmpl['extenSelect'] = $extenSelect;
			} # end if (!empty($depart_id))

		}

		// 导出功能
		if($do == 'export'){
			//导出文件名
			$filename = "handle_person_query_hasten_".date('Y_m_d');

			$this -> createQueryAndHastenExcel($cfg_column,$filename,$list,$arr_total);
		}

		$this->Tmpl['disabled_extension'] = $disabled_extension;	// 保存坐席的灰选状态
		$this->Tmpl['cfg_column'] = $cfg_column;					// 表格表头
		$this->Tmpl['list'] = $list;								// 统计数据
		$this->Tmpl['arr_total'] = $arr_total;						// 最下面一行合计
		$this->display();
	}

	/**
	 * @Purpose		: 显示受理人查件催件列表
	 * @Author		: 代传荣（Carroll）
	 * @Method		: acceptManQueryHastenReport()
	 * @Parameters	: (无)
	 * @Return 		: (无)
	 */
	public function showacceptManQueryHastenReport()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();

		//获取当前用户权限
		$local_priv = $this->getUserPriv();
		$arr_local_priv = explode(',', $local_priv);
		$this->getNavigationMenu($_REQUEST['menu_id'], $_REQUEST['cate_id'], $_REQUEST['sub_id'], $arr_local_priv); # 获取导航菜单
		$this->isAuth('accept_man_query_hasten_report_sel', $arr_local_priv, '您没有查看受理人查件催件报表的权限！');

		// 设置初始值
		if (!isset($_REQUEST['fromdate'])) $_REQUEST['fromdate'] = date('Y-m-d', time() - 86400 * 7);
		if (!isset($_REQUEST['todate'])) $_REQUEST['todate'] = date('Y-m-d', time());
		if (!isset($_REQUEST['do'])) $_REQUEST['do'] = 'search';

		$_REQUEST = varFilter($_REQUEST);
		extract($_REQUEST);

		//获得部门列表
		$sql = "SELECT * FROM org_department";
		$dept = $db->GetAll($sql);

		//提供部门选择end
		$deptOptions = $this->getCateOption($dept, 'dept', $depart_id);
		$this->Tmpl['deptSelect'] = $deptOptions;

		$list = array();        						// 存放分页后查询的结果集
		$list_query = array();        					// 存放分页后query的结果集
		$list_hasten = array();        					// 存放分页后hasten的结果集
		$list_public = array();        					// 存放分页后query表和hasten表公共的结果集
		$appoint_query_extension = array();				// 分页后查件表用户分机号
		$appoint_hasten_extension = array();			// 分页后催件表用户分机号
		$appoint_list_extension = array();				// 分页后数据存放在一起后用户分机号
		$arr_total = array();							// 当前页的数据统计

		// 在满足查询条件的情况下
		$list_query_all = array();        				// 存放query的所有结果集
		$list_hasten_all = array();        				// 存放hasten的所有结果集
		$list_public_all = array();        				// 存放query表和hasten表公共的所有结果集
		$appoint_query_extension_all = array();			// 查件表所有用户分机号
		$appoint_hasten_extension_all = array();		// 催件表所有用户分机号

		if ($change_header != '1') {
			$query = unserialize($_COOKIE['cfg_accept_header']['query']);
			if (!is_array($query)) {
				$query = array(
					'query_quantity',                    // '查件量',
					'query_accept_time',            	 // '查件受理时长',
					'query_accept_average_time',    	 // '查件受理均长',
					'query_complete_60min',              // '查件60分钟内完成数',
					'query_complete_60min_rate',         // '查件60分钟内完成率',
				);
			}
			$_REQUEST['query'] = $query;
		}

		if ($change_header != '1') {
			$hasten = unserialize($_COOKIE['cfg_accept_header']['hasten']);
			if (!is_array($hasten)) {
				$hasten = array(
					'hasten_quantity',                    	// '催件量',
					'hasten_accept_time',            		// '催件受理时长',
					'hasten_accept_average_time',    		// '催件受理均长',
					'hasten_complete_30min',            	// '催件30分钟内完成数',
					'hasten_complete_30min_rate',        	// '催件30分钟内完成率',
				);
			}
			$_REQUEST['hasten'] = $hasten;
		}

		if ($change_header != '1') {
			$total = unserialize($_COOKIE['cfg_accept_header']['total']);
			if (!is_array($total)) {
				$total = array(
					'total_quantity',                    // '查询量',
					'total_accept_time',            	 // '受理时长',
					'total_accept_average_time',    	 // '受理均长',
				);
			}
			$_REQUEST['total'] = $total;
		}

		if (empty($_REQUEST['query'])) $_REQUEST['query'] = array();
		if (empty($_REQUEST['hasten'])) $_REQUEST['hasten'] = array();
		if (empty($_REQUEST['total'])) $_REQUEST['total'] = array();

		setcookie("cfg_accept_header[query]", serialize($_REQUEST['query']), time() + 86400 * 1);        //cookie默认有效时间为1天
		setcookie("cfg_accept_header[hasten]", serialize($_REQUEST['hasten']), time() + 86400 * 1);
		setcookie("cfg_accept_header[total]", serialize($_REQUEST['total']), time() + 86400 * 1);

		//表头(字段)配置
		$cfg_column = array(
			'query' => array(
				'query_quantity' => '查件量',
				'query_accept_time' => '受理时长',
				'query_accept_average_time' => '受理均长',
				'query_complete_60min' => '60分钟内完成数',
				'query_complete_60min_rate' => '60分钟内完成率',
			),
			'hasten' => array(
				'hasten_quantity' => '催件量',
				'hasten_accept_time' => '受理时长',
				'hasten_accept_average_time' => '受理均长',
				'hasten_complete_30min' => '30分钟内完成数',
				'hasten_complete_30min_rate' => '30分钟内完成率',
			),
			'total' => array(
				'total_quantity' => '查询量',
				'total_accept_time' => '受理时长',
				'total_accept_average_time' => '受理均长',
			),
		);

		// 当前页合计数组
		$arr_total = array(
			'query_quantity' => '0',
			'query_accept_time' => '0',
			'query_accept_average_time' => '0',
			'query_complete_60min' => '0',
			'query_complete_60min_rate' => '0',
			'hasten_quantity' => '0',
			'hasten_accept_time' => '0',
			'hasten_accept_average_time' => '0',
			'hasten_complete_30min' => '0',
			'hasten_complete_30min_rate' => '0',
			'total_quantity' => '0',
			'total_accept_time' => '0',
			'total_accept_average_time' => '0',
		);

		# 定义一个没有数据的查件数组
		$query_empty = array(
			"query_quantity" =>"0",
			"query_accept_time" =>"0",
			"query_accept_average_time" => '0',
			"query_complete_60min" => "0" ,
			"query_complete_60min_rate" => "0%",
		);

		# 定义一个没有数据的催件数组
		$hasten_empty = array(
			"hasten_quantity" =>"0",
			"hasten_accept_time" =>"0",
			"hasten_accept_average_time" => '0',
			"hasten_complete_30min" => "0" ,
			"hasten_complete_30min_rate" => "0%",
		);

		if ('' != $do) {
			# 按统计步长
			if ('all' == $step || empty($step)) {
				$_REQUEST['step'] = 'all';
			} else if ('day' == $step) {
				$_REQUEST['step'] = 'day';
				$time = " SUBSTRING(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'), 1, 10) ";
			} else if ('hour' == $step) {
				$time = " SUBSTRING(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'), 12, 2) ";
			} else if ('week' == $step) {
				$time = " WEEK(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'),1) ";
			} else if ('month' == $step) {
				$time = " SUBSTRING(FROM_UNIXTIME(create_time, '%Y-%m-%d %H:%i:%s'), 1, 7) ";
			}

			# 按统计维度
			if ('by_seats' == $statistical_dimension || empty($statistical_dimension)) {
				$_REQUEST['statistical_dimension'] = 'by_seats';
			} else {
				$_REQUEST['statistical_dimension'] = 'by_department';
			}
			
			# 如果选择了部门没选坐席，根据部门进行查询，否则按坐席查询
			if(! empty($depart_id) && empty($extension)){
				$depart_ids = $this->getNextChildDepartId($depart_id);		// 获取该部门的子部门

				# 若该部门没有子部门
				if($depart_ids == $depart_id){
					$depart_id = $depart_id;
					// 若选择了统计步长，则将统计步长加入查询条件
					if($time){
						$sql_query_select = "SELECT COUNT(*) AS query_quantity ,$time as time,GROUP_CONCAT(id) as qids, appoint_extension,SUM(accept_time) AS query_accept_time,(SUM(accept_time)/COUNT(*)) AS query_accept_average_time ";
						$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,$time as time,GROUP_CONCAT(id) as hids, appoint_extension,SUM(accept_time) AS hasten_accept_time,(SUM(accept_time)/COUNT(*)) AS hasten_accept_average_time ";
					}else{
						$sql_query_select = "SELECT COUNT(*) AS query_quantity ,GROUP_CONCAT(id) as qids,appoint_extension,SUM(accept_time) AS query_accept_time,(SUM(accept_time)/COUNT(*)) AS query_accept_average_time ";
						$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,GROUP_CONCAT(id) as hids,appoint_extension,SUM(accept_time) AS hasten_accept_time,(SUM(accept_time)/COUNT(*)) AS hasten_accept_average_time ";
					}

					$sql_from_query = $sql_query_select ." FROM se_query ";			// 从查件表中查询
					$sql_from_hasten = $sql_hasten_select ." FROM se_hasten ";		// 从催件表中查询

					# 根据起止时间组合查询条件
					if (empty($fromdate) && empty($todate)) {
						$from_date = strtotime('-2 week');
						$to_date = time();
						$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
					}
					if (empty($fromdate) && !empty($todate)) {
						$todate = $todate. ' 23:59:59';
						$from_date = strtotime('-1 week');
						$to_date = strtotime($todate);
						$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
					}
					if (!empty($fromdate) && empty($todate)) {
						$from_date = strtotime($fromdate);
						$to_date = time();
						$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
					}
					if (!empty($fromdate) && !empty($todate)) {
						$todate = $todate. ' 23:59:59';
						$fromdate = strtotime($fromdate);
						$todate = strtotime($todate);
						$sql_condition .= " WHERE create_time BETWEEN"." '$fromdate' AND '$todate'";
					}

					# 根据部门组合查询条件
					if(! empty($depart_id) && empty($extension)){
						$depart_ids = $this->getChildDepartId($depart_id);		// 获取该部门的子部门
						$depart_ids = ltrim($depart_ids,',');
						$sql_extension = "SELECT GROUP_CONCAT(extension) as extension FROM org_user WHERE dept_id IN($depart_ids)";
						$result_extension = $db -> GetOne($sql_extension);
						if($result_extension){
							$sql_condition .= " AND appoint_extension IN($result_extension) ";
						}else{
							$sql_condition .= " AND appoint_extension IN('') ";
						}
					}

					# 根据坐席组合查询条件
					if(! empty($extension)) {
						$sql_condition .= " AND appoint_extension = $extension ";
					}
					if($time){
						if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
							$sql_groupby = 'GROUP BY time DESC';
						}else{
							$sql_groupby = " GROUP BY time DESC ,appoint_extension ";
						}
					}else{
						if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
							$sql_groupby = '';
						}else{
							$sql_groupby = " GROUP BY appoint_extension ";
						}
					}
					# 查件表的查询 sql
					$sql_query = $sql_from_query . $sql_condition .$sql_groupby;
					//echo $sql_query;
					# 催件表的查询 sql
					$sql_hasten = $sql_from_hasten . $sql_condition .$sql_groupby;
		//			echo $sql_hasten;

					/******************* 计算总记录数 start *******************/
					$result_query_all = $db -> GetAll($sql_query);
					$result_hasten_all = $db -> GetAll($sql_hasten);

					foreach ($result_query_all as $key => $value) {
						if(empty($value['appoint_extension'])){
							unset($result_query_all[$key]);
						}
					}
					foreach ($result_hasten_all as $key => $value) {
						if(empty($value['appoint_extension'])){
							unset($result_hasten_all[$key]);
						}
					}

					// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
					if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
						$result_query_all[0]['appoint_extension'] = $result_hasten_all[0]['appoint_extension'];
					}

					# 将所有用户的分机号保存到一个数组里面去
					foreach($result_query_all as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_query_extension_all,$value['appoint_extension']);
							}else{
								array_push($appoint_query_extension_all,$value['appoint_extension'].$value['time']);
							}
						}
					}

					foreach($result_hasten_all as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_hasten_extension_all,$value['appoint_extension']);
							}else{
								array_push($appoint_hasten_extension_all,$value['appoint_extension'].$value['time']);
							}
						}
					}
					# 将 $result_query 的 key 换成 坐席工号
					$result_query_all = array_combine($appoint_query_extension_all,$result_query_all);
					$result_hasten_all = array_combine($appoint_hasten_extension_all,$result_hasten_all);

					$result_query_all = empty($result_query_all) ? array(array()) : $result_query_all ;
					$result_hasten_all = empty($result_hasten_all) ? array(array()) : $result_hasten_all ;

					# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
					foreach($result_query_all as $key => $value){
						foreach($result_hasten_all as $k => $v){
							if($key == $k){
								$list_public_all[$key] = array_merge($value,$v);
							}else{
								$list_query_all[$key] = array_merge($value,$hasten_empty);
								$list_hasten_all[$k] = array_merge($query_empty,$v);
								if(empty($key)){
									unset($list_query_all[$key]);
								}if(empty($k)){
									unset($list_hasten_all[$k]);
								}
							}
						}
					}

					# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
					foreach($list_public_all as $key => $value){
						foreach($list_query_all as $k2 => $v2){
							if($key == $k2){
								unset($list_query_all[$k2]);
							}
						}
						foreach($list_hasten_all as $k3 => $v3){
							if($key == $k3){
								unset($list_hasten_all[$k3]);
							}
						}

					}

					# 合并这三个数组
					$list_all = array_merge($list_public_all,$list_query_all,$list_hasten_all);
					$total_count = count($list_all);
					/******************* 计算总记录数 end *******************/

					# 加载分页类
					$pg = loadClass('tool','page',$this);
					$pg -> setPageVar('p');       // 页数传递变量
					$pg -> setNumPerPage(20);		// 每页显示 10 条
					unset($_REQUEST['p']);
					$pg -> setVar($_REQUEST);     // 将请求的参数进行 url 编码
					$this -> tmpl['allowRows'] = $total_count;
					$pg -> set($total_count);		// 分页设置，传入总记录数即可，当前页数默认会自动读取
					$this -> Tmpl['show_pages'] = $pg -> output(1);

					# 查件表的查询结果
					$object_query = $db -> SelectLimit($sql_query, $pg->getNumPerPage(), $pg->getOffset());
					if($object_query == false){
						echo $db->ErrorMsg();
						$db->close();
						exit;
					}else{
						while(!$object_query->EOF){
							$result_query[] = $object_query -> fields;
							$object_query -> moveNext();
						}
					}

					# 催件表的查询结果
					$object_hasten = $db -> SelectLimit($sql_hasten, $pg->getNumPerPage(), $pg->getOffset());
					if($object_hasten == false){
						echo $db->ErrorMsg();
						$db->close();
						exit;
					}else{
						while(!$object_hasten->EOF){
							$result_hasten[] = $object_hasten -> fields;
							$object_hasten -> moveNext();
						}
					}

					// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
					if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
						$result_query[0]['appoint_extension'] = $result_hasten[0]['appoint_extension'];
					}

					# 将用户的分机号保存到一个数组里面去
					foreach($result_query as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_query_extension,$value['appoint_extension']);
							}else{
								array_push($appoint_query_extension,$value['appoint_extension'].$value['time']);
							}
						}
					}

					foreach($result_hasten as $key => $value){
						if(! empty($value['appoint_extension'])){
							if(! in_array($value['time'],$value)){
								array_push($appoint_hasten_extension,$value['appoint_extension']);
							}else{
								array_push($appoint_hasten_extension,$value['appoint_extension'].$value['time']);
							}
						}
					}

					# 处理查件查询结果
					foreach($result_query as $key => $value){
						if(empty($value['appoint_extension'])){
							unset($result_query[$key]);
						}else{
							// 获取受理人所在部门
							$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
							$depart_name = $db -> GetOne($depart_sql);
							$result_query[$key]['dept_name'] = $depart_name;
							$result_query[$key]['query_accept_average_time'] = round($value['query_accept_average_time']);

							// 获取 60 分钟完成数
							$sql_query_60min = $sql_from_query . $sql_condition." AND accept_time <= 60 AND id IN($value[qids])";
							$reuslt_query_60min = $db -> GetOne($sql_query_60min);
							$query_complete_60min_rate = number_format($reuslt_query_60min/$value['query_quantity'],4)  * 100 . '%';
							$result_query[$key]['query_complete_60min'] = empty($reuslt_query_60min) ? 0 : $reuslt_query_60min;
							$result_query[$key]['query_complete_60min_rate'] = empty($query_complete_60min_rate) ? 0 : $query_complete_60min_rate;

							// 若为空则都显示 0
							$result_query[$key]['query_quantity'] = empty($value['query_quantity']) ? 0 : $value['query_quantity'];
							$result_query[$key]['query_accept_time'] = empty($value['query_accept_time']) ? 0 : $value['query_accept_time'];
						}
					}

					# 处理催件查询结果
					foreach($result_hasten as $key => $value){
						if(empty($value['appoint_extension'])){
							unset($result_hasten[$key]);
						}else{
							// 获取受理人所在部门
							$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
							$depart_name = $db -> GetOne($depart_sql);
							$result_hasten[$key]['dept_name'] = $depart_name;
							$result_hasten[$key]['hasten_accept_average_time'] = round($value['hasten_accept_average_time']);

							// 获取 30 分钟完成数和完成率
							$sql_hasten_30min = $sql_from_hasten . $sql_condition." AND accept_time <= 30 AND id IN($value[hids])";
							$reuslt_hasten_30min = $db -> GetOne($sql_hasten_30min);
							$hasten_complete_30min_rate = number_format($reuslt_hasten_30min/$value['hasten_quantity'],4)  * 100 . '%';
							$result_hasten[$key]['hasten_complete_30min'] = empty($reuslt_hasten_30min) ? 0 : $reuslt_hasten_30min;
							$result_hasten[$key]['hasten_complete_30min_rate'] = empty($hasten_complete_30min_rate) ? 0 : $hasten_complete_30min_rate;

							// 若为空则都显示 0
							$result_hasten[$key]['hasten_quantity'] = empty($value['hasten_quantity']) ? 0 : $value['hasten_quantity'];
							$result_hasten[$key]['hasten_accept_time'] = empty($value['hasten_accept_time']) ? 0 : $value['hasten_accept_time'];
						}
					}

					# 将 $result_query 的 key 换成 坐席工号
					$result_query = array_combine($appoint_query_extension,$result_query);
					$result_hasten = array_combine($appoint_hasten_extension,$result_hasten);

					$result_query = empty($result_query) ? array(array()) : $result_query ;
					$result_hasten = empty($result_hasten) ? array(array()) : $result_hasten ;

					# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
					foreach($result_query as $key => $value){
						foreach($result_hasten as $k => $v){
							if($key == $k){
								$list_public[$key] = array_merge($value,$v);
							}else{
								$list_query[$key] = array_merge($value,$hasten_empty);
								$list_hasten[$k] = array_merge($query_empty,$v);
								if(empty($key)){
									unset($list_query[$key]);
								}if(empty($k)){
									unset($list_hasten[$k]);
								}
							}
						}
					}

					# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
					foreach($list_public as $key => $value){
						foreach($list_query as $k2 => $v2){
							if($key == $k2){
								unset($list_query[$k2]);
							}
						}
						foreach($list_hasten as $k3 => $v3){
							if($key == $k3){
								unset($list_hasten[$k3]);
							}
						}

					}

					# 合并这三个数组
					$list = array_merge($list_public,$list_query,$list_hasten);

					# 将用户的分机号保存到一个数组里面去
					foreach($list as $key => $value){
						if(! in_array($value['time'],$value)){
							array_push($appoint_list_extension,$value['appoint_extension']);
						}else{
							array_push($appoint_list_extension,$value['time'].$value['appoint_extension']);
						}
					}

					foreach($list as $key => $value){
						// 查询受理人的中文名
						$appoint_extension_name =  $this -> getNameByExten($value['appoint_extension']);
						$list[$key]['appoint_extension'] = $appoint_extension_name.'('.$value['appoint_extension'].')';

						# 该坐席总查件量、总受理时长、总受理均长
						$list[$key]['total_quantity'] = $value['query_quantity'] + $value['hasten_quantity'];
						$list[$key]['total_accept_time'] = $value['query_accept_time'] + $value['hasten_accept_time'];
						$list[$key]['total_accept_average_time'] =  round($list[$key]['total_accept_time']/$list[$key]['total_quantity']);
					}

					# 将 $list 的 key 换成 坐席工号
					$list = array_combine($appoint_list_extension,$list);
					krsort($list);

					# 计算当前页的合计
					foreach($list as $key => $value){
						if(empty($key)){
							unset($list[$key]);
						}
						$arr_total['query_quantity'] += $value['query_quantity'];
						$arr_total['query_accept_time'] += $value['query_accept_time'];
						$arr_total['query_accept_average_time'] = round($arr_total['query_accept_time']/$arr_total['query_quantity']);
						$arr_total['query_complete_60min'] += $value['query_complete_60min'];
						$arr_total['query_complete_60min_rate'] = number_format($arr_total['query_complete_60min']/$arr_total['query_quantity'],4)  * 100 . '%';

						$arr_total['hasten_quantity'] += $value['hasten_quantity'];
						$arr_total['hasten_accept_time'] += $value['hasten_accept_time'];
						$arr_total['hasten_accept_average_time'] =  round($arr_total['hasten_accept_time']/$arr_total['hasten_quantity']);
						$arr_total['hasten_complete_30min'] += $value['hasten_complete_30min'];
						$arr_total['hasten_complete_30min_rate'] = number_format($arr_total['hasten_complete_30min']/$arr_total['hasten_quantity'],4)  * 100 . '%';

						$arr_total['total_quantity'] += $value['total_quantity'];
						$arr_total['total_accept_time'] += $value['total_accept_time'];
						$arr_total['total_accept_average_time'] = round($arr_total['total_accept_time']/$arr_total['total_quantity']);
					}

				}else{
					# 若该部门有子部门，则按子部门分别汇总
					$arr_departs = explode(',',$depart_ids);
					$depart_parent_id = $depart_id;
					array_push($arr_departs,$depart_parent_id);
					foreach($arr_departs as $key => $value){
						$depart_id = $value;
						// 若选择了统计步长，则将统计步长加入查询条件
						if($time){
							$sql_query_select = "SELECT COUNT(*) AS query_quantity ,$time as time,GROUP_CONCAT(id) as qids, appoint_extension,SUM(accept_time) AS query_accept_time,(SUM(accept_time)/COUNT(*)) AS query_accept_average_time ";
							$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,$time as time,GROUP_CONCAT(id) as hids, appoint_extension,SUM(accept_time) AS hasten_accept_time,(SUM(accept_time)/COUNT(*)) AS hasten_accept_average_time ";
						}else{
							$sql_query_select = "SELECT COUNT(*) AS query_quantity ,GROUP_CONCAT(id) as qids,appoint_extension,SUM(accept_time) AS query_accept_time,(SUM(accept_time)/COUNT(*)) AS query_accept_average_time ";
							$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,GROUP_CONCAT(id) as hids,appoint_extension,SUM(accept_time) AS hasten_accept_time,(SUM(accept_time)/COUNT(*)) AS hasten_accept_average_time ";
						}

						$sql_from_query = $sql_query_select ." FROM se_query ";			// 从查件表中查询
						$sql_from_hasten = $sql_hasten_select ." FROM se_hasten ";		// 从催件表中查询

									# 查询条件
						$sql_condition = '';

						# 如果是时间戳，则换成 年 - 月 -日 格式
						if(is_numeric($fromdate)){
							$fromdate = date('Y-m-d',$fromdate);
						}
						if(is_numeric($todate)){
							$todate = date('Y-m-d',$todate);
						}
						
						# 根据起止时间组合查询条件
						if (empty($fromdate) && empty($todate)) {
							$from_date = strtotime('-2 week');
							$to_date = time();
							$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
						}
						if (empty($fromdate) && !empty($todate)) {
							$todate = $todate. ' 23:59:59';
							$from_date = strtotime('-1 week');
							$to_date = strtotime($todate);
							$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
						}
						if (!empty($fromdate) && empty($todate)) {
							$from_date = strtotime($fromdate);
							$to_date = time();
							$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
						}
						if (!empty($fromdate) && !empty($todate)) {
							$todate = $todate. ' 23:59:59';
							$fromdate = strtotime($fromdate);
							$todate = strtotime($todate);
							$sql_condition .= " WHERE create_time BETWEEN"." '$fromdate' AND '$todate'";
						}

						# 根据部门组合查询条件
						if(! empty($depart_id) && empty($extension)){
							$depart_ids = $this->getChildDepartId($depart_id);		// 获取该部门的子部门
							$depart_ids = ltrim($depart_ids,',');
                            $depart_ids .= ','.$depart_id;
							if($depart_id == $depart_parent_id){
								$sql_extension = "SELECT GROUP_CONCAT(extension) as extension FROM org_user WHERE dept_id IN($depart_parent_id)";
							}else{
								$sql_extension = "SELECT GROUP_CONCAT(extension) as extension FROM org_user WHERE dept_id IN($depart_ids)";
							}
							$result_extension = $db -> GetOne($sql_extension);
							if($result_extension){
								$sql_condition .= " AND appoint_extension IN($result_extension) ";
							}else{
								$sql_condition .= " AND appoint_extension IN('') ";
							}
						}

				
						# 根据坐席组合查询条件
						if(! empty($extension)) {
							$sql_condition .= " AND appoint_extension = $extension ";
						}
						if($time){
							if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
								$sql_groupby = 'GROUP BY time DESC';
							}else{
								$sql_groupby = " GROUP BY time DESC ,appoint_extension ";
							}
						}else{
							if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
								$sql_groupby = '';
							}else{
								$sql_groupby = " GROUP BY appoint_extension ";
							}
						}
						
						# 查件表的查询 sql
						$sql_query = $sql_from_query . $sql_condition .$sql_groupby;
					    //echo $sql_query;echo "</br>";
						# 催件表的查询 sql
						$sql_hasten = $sql_from_hasten . $sql_condition .$sql_groupby;
						//echo $sql_hasten;
			
			
						// 由于此处需要循环处理，所以每次选好处理之前都需要将数组清空。
						$list = array();        						// 存放分页后查询的结果集
						$list_query = array();        					// 存放分页后query的结果集
						$list_hasten = array();        					// 存放分页后hasten的结果集
						$list_public = array();        					// 存放分页后query表和hasten表公共的结果集
						$appoint_query_extension = array();				// 分页后查件表用户分机号
						$appoint_hasten_extension = array();			// 分页后催件表用户分机号
						$appoint_list_extension = array();				// 分页后数据存放在一起后用户分机号
						$arr_total = array();							// 当前页的数据统计
						
						// 在满足查询条件的情况下
						$list_query_all = array();        				// 存放query的所有结果集
						$list_hasten_all = array();        				// 存放hasten的所有结果集
						$list_public_all = array();        				// 存放query表和hasten表公共的所有结果集
						$appoint_query_extension_all = array();			// 查件表所有用户分机号
						$appoint_hasten_extension_all = array();		// 催件表所有用户分机号

			
						/******************* 计算总记录数 start *******************/
						$total_count = count($arr_departs);
						/******************* 计算总记录数 end *******************/

						# 加载分页类
						$pg = loadClass('tool','page',$this);
						$pg -> setPageVar('p');       // 页数传递变量
						$pg -> setNumPerPage(20);		// 每页显示 10 条
						unset($_REQUEST['p']);
						$pg -> setVar($_REQUEST);     // 将请求的参数进行 url 编码
						$this -> tmpl['allowRows'] = $total_count;
						$pg -> set($total_count);		// 分页设置，传入总记录数即可，当前页数默认会自动读取
						$this -> Tmpl['show_pages'] = $pg -> output(1);

						# 查件表的查询结果
						$object_query = $db -> SelectLimit($sql_query, $pg->getNumPerPage(), $pg->getOffset());
						if($object_query == false){
							echo $db->ErrorMsg();
							$db->close();
							exit;
						}else{
							while(!$object_query->EOF){
								$result_query[] = $object_query -> fields;
								$object_query -> moveNext();
							}
						}

						# 催件表的查询结果
						$object_hasten = $db -> SelectLimit($sql_hasten, $pg->getNumPerPage(), $pg->getOffset());
						if($object_hasten == false){
							echo $db->ErrorMsg();
							$db->close();
							exit;
						}else{
							while(!$object_hasten->EOF){
								$result_hasten[] = $object_hasten -> fields;
								$object_hasten -> moveNext();
							}
						}

						// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
						if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
							foreach($result_query as $key => $value){
								foreach($result_hasten as $k => $v){
									if($key == $k){
										$result_hasten[$k]['appoint_extension'] = $result_query[$key]['appoint_extension'];
									}
								}
							}
						}
						
						# 将用户的分机号保存到一个数组里面去
						foreach($result_query as $key => $value){
							if(! empty($value['appoint_extension'])){
								if(! in_array($value['time'],$value)){
									array_push($appoint_query_extension,$value['appoint_extension']);
								}else{
									array_push($appoint_query_extension,$value['appoint_extension'].$value['time']);
								}
							}
						}

						foreach($result_hasten as $key => $value){
							if(! empty($value['appoint_extension'])){
								if(! in_array($value['time'],$value)){
									array_push($appoint_hasten_extension,$value['appoint_extension']);
								}else{
									array_push($appoint_hasten_extension,$value['appoint_extension'].$value['time']);
								}
							}
						}

						# 处理查件查询结果
						foreach($result_query as $key => $value){
							if(empty($value['appoint_extension'])){
								unset($result_query[$key]);
							}else{
								// 获取受理人所在部门
								$depart_ids = $this->getNextChildDepartId($depart_id);		// 获取该部门的子部门
								# 若该部门没有子部门
								if($depart_ids == $depart_id){
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
								}else{
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = $depart_id";
								}
								$depart_name = $db -> GetOne($depart_sql);
								if($result_query[$key]['dept_name']){
									$result_query[$key]['dept_name'] = $result_query[$key]['dept_name'] ;
								}else{
									$result_query[$key]['dept_name'] = $depart_name;
								}
								$result_query[$key]['query_accept_average_time'] = round($value['query_accept_average_time']);

								// 获取 60 分钟完成数
								$value['qids'] = rtrim($value['qids'],',');
								$sql_query_60min = $sql_from_query . $sql_condition." AND accept_time <= 60 AND id IN($value[qids])";
								$reuslt_query_60min = $db -> GetOne($sql_query_60min);
								$query_complete_60min_rate = number_format($reuslt_query_60min/$value['query_quantity'],4)  * 100 . '%';

								if($result_query[$key]['query_complete_60min']){
									$result_query[$key]['query_complete_60min'] = $result_query[$key]['query_complete_60min'] ;
								}else{
									$result_query[$key]['query_complete_60min'] = empty($reuslt_query_60min) ? 0 : $reuslt_query_60min;
								}
								if($result_query[$key]['query_complete_60min_rate']){
									$result_query[$key]['query_complete_60min_rate'] = $result_query[$key]['query_complete_60min_rate'];
								}else{
									$result_query[$key]['query_complete_60min_rate'] = empty($query_complete_60min_rate) ? 0 : $query_complete_60min_rate;
								}

								// 若为空则都显示 0
								$result_query[$key]['query_quantity'] = empty($value['query_quantity']) ? 0 : $value['query_quantity'];
								$result_query[$key]['query_accept_time'] = empty($value['query_accept_time']) ? 0 : $value['query_accept_time'];
							}
						}

						# 处理催件查询结果
						foreach($result_hasten as $key => $value){
							if(empty($value['appoint_extension'])){
								unset($result_hasten[$key]);
							}else{
								// 获取处理人所在部门
								$depart_ids = $this->getNextChildDepartId($depart_id);		// 获取该部门的子部门
								# 若该部门没有子部门
								if($depart_ids == $depart_id){
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
								}else{
									$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = $depart_id";
								}
								$depart_name = $db -> GetOne($depart_sql);
								if($result_hasten[$key]['dept_name']){
									$result_hasten[$key]['dept_name'] = $result_hasten[$key]['dept_name'];
								}else{
									$result_hasten[$key]['dept_name'] = $depart_name;
								}
								$result_hasten[$key]['hasten_accept_average_time'] = round($value['hasten_accept_average_time']);

								// 获取 30 分钟完成数和完成率
								$value['hids'] = rtrim($value['hids'],',');
								$sql_hasten_30min = $sql_from_hasten . $sql_condition." AND accept_time <= 30 AND id IN($value[hids])";
								$reuslt_hasten_30min = $db -> GetOne($sql_hasten_30min);
								$hasten_complete_30min_rate = number_format($reuslt_hasten_30min/$value['hasten_quantity'],4)  * 100 . '%';

								if($result_hasten[$key]['hasten_complete_30min']){
									$result_hasten[$key]['hasten_complete_30min'] = $result_hasten[$key]['hasten_complete_30min'];
								}else{
									$result_hasten[$key]['hasten_complete_30min'] = empty($reuslt_hasten_30min) ? 0 : $reuslt_hasten_30min;
								}
								if($result_hasten[$key]['hasten_complete_30min_rate']){
									$result_hasten[$key]['hasten_complete_30min_rate'] = $result_hasten[$key]['hasten_complete_30min_rate'];
								}else{
									$result_hasten[$key]['hasten_complete_30min_rate'] = empty($hasten_complete_30min_rate) ? 0 : $hasten_complete_30min_rate;
								}

								// 若为空则都显示 0
								$result_hasten[$key]['hasten_quantity'] = empty($value['hasten_quantity']) ? 0 : $value['hasten_quantity'];
								$result_hasten[$key]['hasten_accept_time'] = empty($value['hasten_accept_time']) ? 0 : $value['hasten_accept_time'];
							}
						}

						# 将 $result_query 的 key 换成 坐席工号
						$result_query = array_combine($appoint_query_extension,$result_query);
						$result_hasten = array_combine($appoint_hasten_extension,$result_hasten);

						$result_query = empty($result_query) ? array(array()) : $result_query ;
						$result_hasten = empty($result_hasten) ? array(array()) : $result_hasten ;

						# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
						foreach($result_query as $key => $value){
							foreach($result_hasten as $k => $v){
								if($key == $k){
									$list_public[$key] = array_merge($value,$v);
								}else{
									$list_query[$key] = array_merge($value,$hasten_empty);
									$list_hasten[$k] = array_merge($query_empty,$v);
									if(empty($key)){
										unset($list_query[$key]);
									}if(empty($k)){
										unset($list_hasten[$k]);
									}
								}
							}
						}

						# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
						foreach($list_public as $key => $value){
							foreach($list_query as $k2 => $v2){
								if($key == $k2){
									unset($list_query[$k2]);
								}
							}
							foreach($list_hasten as $k3 => $v3){
								if($key == $k3){
									unset($list_hasten[$k3]);
								}
							}

						}

						# 合并这三个数组
						$list = array_merge($list_public,$list_query,$list_hasten);

						# 将用户的分机号保存到一个数组里面去
						foreach($list as $key => $value){
							if(! in_array($value['time'],$value)){
								array_push($appoint_list_extension,$value['appoint_extension']);
							}else{
								array_push($appoint_list_extension,$value['time'].$value['appoint_extension']);
							}
						}

						foreach($list as $key => $value){
							// 查询受理人的中文名
							$appoint_extension_name =  $this -> getNameByExten($value['appoint_extension']);
							$list[$key]['appoint_extension'] = $appoint_extension_name.'('.$value['appoint_extension'].')';

							# 该坐席总查件量、总受理时长、总受理均长
							$list[$key]['total_quantity'] = $value['query_quantity'] + $value['hasten_quantity'];
							$list[$key]['total_accept_time'] = $value['query_accept_time'] + $value['hasten_accept_time'];
							$list[$key]['total_accept_average_time'] =  round($list[$key]['total_accept_time']/$list[$key]['total_quantity']);
						}

						# 将 $list 的 key 换成 坐席工号
						$list = array_combine($appoint_list_extension,$list);
						krsort($list);

						# 计算当前页的合计
						foreach($list as $key => $value){
							if(empty($key)){
								unset($list[$key]);
							}
							$arr_total['query_quantity'] += $value['query_quantity'];
							$arr_total['query_accept_time'] += $value['query_accept_time'];
							$arr_total['query_accept_average_time'] = round($arr_total['query_accept_time']/$arr_total['query_quantity']);
							$arr_total['query_complete_60min'] += $value['query_complete_60min'];
							$arr_total['query_complete_60min_rate'] = number_format($arr_total['query_complete_60min']/$arr_total['query_quantity'],4)  * 100 . '%';

							$arr_total['hasten_quantity'] += $value['hasten_quantity'];
							$arr_total['hasten_accept_time'] += $value['hasten_accept_time'];
							$arr_total['hasten_accept_average_time'] =  round($arr_total['hasten_accept_time']/$arr_total['hasten_quantity']);
							$arr_total['hasten_complete_30min'] += $value['hasten_complete_30min'];
							$arr_total['hasten_complete_30min_rate'] = number_format($arr_total['hasten_complete_30min']/$arr_total['hasten_quantity'],4)  * 100 . '%';

							$arr_total['total_quantity'] += $value['total_quantity'];
							$arr_total['total_accept_time'] += $value['total_accept_time'];
							$arr_total['total_accept_average_time'] = round($arr_total['total_accept_time']/$arr_total['total_quantity']);
						}
					}
				}
			}else{
				// 若选择了统计步长，则将统计步长加入查询条件
				if($time){
					$sql_query_select = "SELECT COUNT(*) AS query_quantity ,$time as time,GROUP_CONCAT(id) as qids, appoint_extension,SUM(accept_time) AS query_accept_time,(SUM(accept_time)/COUNT(*)) AS query_accept_average_time ";
					$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,$time as time,GROUP_CONCAT(id) as hids, appoint_extension,SUM(accept_time) AS hasten_accept_time,(SUM(accept_time)/COUNT(*)) AS hasten_accept_average_time ";
				}else{
					$sql_query_select = "SELECT COUNT(*) AS query_quantity ,GROUP_CONCAT(id) as qids,appoint_extension,SUM(accept_time) AS query_accept_time,(SUM(accept_time)/COUNT(*)) AS query_accept_average_time ";
					$sql_hasten_select = "SELECT COUNT(*) AS hasten_quantity ,GROUP_CONCAT(id) as hids,appoint_extension,SUM(accept_time) AS hasten_accept_time,(SUM(accept_time)/COUNT(*)) AS hasten_accept_average_time ";
				}

				$sql_from_query = $sql_query_select ." FROM se_query ";			// 从查件表中查询
				$sql_from_hasten = $sql_hasten_select ." FROM se_hasten ";		// 从催件表中查询

				# 根据起止时间组合查询条件
				if (empty($fromdate) && empty($todate)) {
					$from_date = strtotime('-2 week');
					$to_date = time();
					$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
				}
				if (empty($fromdate) && !empty($todate)) {
					$todate = $todate. ' 23:59:59';
					$from_date = strtotime('-1 week');
					$to_date = strtotime($todate);
					$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
				}
				if (!empty($fromdate) && empty($todate)) {
					$from_date = strtotime($fromdate);
					$to_date = time();
					$sql_condition .= " WHERE create_time BETWEEN"." '$from_date' AND '$to_date'";
				}
				if (!empty($fromdate) && !empty($todate)) {
					$todate = $todate. ' 23:59:59';
					$fromdate = strtotime($fromdate);
					$todate = strtotime($todate);
					$sql_condition .= " WHERE create_time BETWEEN"." '$fromdate' AND '$todate'";
				}

				# 根据坐席组合查询条件
				if(! empty($extension)) {
					$sql_condition .= " AND appoint_extension = $extension ";
				}
				if($time){
					$sql_groupby = " GROUP BY time DESC ,appoint_extension ";
				}else{
					$sql_groupby = " GROUP BY appoint_extension ";
				}
				# 查件表的查询 sql
				$sql_query = $sql_from_query . $sql_condition .$sql_groupby;
				echo $sql_query;
				# 催件表的查询 sql
				$sql_hasten = $sql_from_hasten . $sql_condition .$sql_groupby;
	//			echo $sql_hasten;

				/******************* 计算总记录数 start *******************/
				$result_query_all = $db -> GetAll($sql_query);
				$result_hasten_all = $db -> GetAll($sql_hasten);

				foreach ($result_query_all as $key => $value) {
					if(empty($value['appoint_extension'])){
						unset($result_query_all[$key]);
					}
				}
				foreach ($result_hasten_all as $key => $value) {
					if(empty($value['appoint_extension'])){
						unset($result_hasten_all[$key]);
					}
				}

				// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
				if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
					$result_query_all[0]['appoint_extension'] = $result_hasten_all[0]['appoint_extension'];
				}

				# 将所有用户的分机号保存到一个数组里面去
				foreach($result_query_all as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_query_extension_all,$value['appoint_extension']);
						}else{
							array_push($appoint_query_extension_all,$value['appoint_extension'].$value['time']);
						}
					}
				}

				foreach($result_hasten_all as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_hasten_extension_all,$value['appoint_extension']);
						}else{
							array_push($appoint_hasten_extension_all,$value['appoint_extension'].$value['time']);
						}
					}
				}
				# 将 $result_query 的 key 换成 坐席工号
				$result_query_all = array_combine($appoint_query_extension_all,$result_query_all);
				$result_hasten_all = array_combine($appoint_hasten_extension_all,$result_hasten_all);

				$result_query_all = empty($result_query_all) ? array(array()) : $result_query_all ;
				$result_hasten_all = empty($result_hasten_all) ? array(array()) : $result_hasten_all ;

				# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
				foreach($result_query_all as $key => $value){
					foreach($result_hasten_all as $k => $v){
						if($key == $k){
							$list_public_all[$key] = array_merge($value,$v);
						}else{
							$list_query_all[$key] = array_merge($value,$hasten_empty);
							$list_hasten_all[$k] = array_merge($query_empty,$v);
							if(empty($key)){
								unset($list_query_all[$key]);
							}if(empty($k)){
								unset($list_hasten_all[$k]);
							}
						}
					}
				}

				# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
				foreach($list_public_all as $key => $value){
					foreach($list_query_all as $k2 => $v2){
						if($key == $k2){
							unset($list_query_all[$k2]);
						}
					}
					foreach($list_hasten_all as $k3 => $v3){
						if($key == $k3){
							unset($list_hasten_all[$k3]);
						}
					}

				}

				# 合并这三个数组
				$list_all = array_merge($list_public_all,$list_query_all,$list_hasten_all);
				$total_count = count($list_all);
				/******************* 计算总记录数 end *******************/

				# 加载分页类
				$pg = loadClass('tool','page',$this);
				$pg -> setPageVar('p');       // 页数传递变量
				$pg -> setNumPerPage(20);		// 每页显示 10 条
				unset($_REQUEST['p']);
				$pg -> setVar($_REQUEST);     // 将请求的参数进行 url 编码
				$this -> tmpl['allowRows'] = $total_count;
				$pg -> set($total_count);		// 分页设置，传入总记录数即可，当前页数默认会自动读取
				$this -> Tmpl['show_pages'] = $pg -> output(1);

				# 查件表的查询结果
				$object_query = $db -> SelectLimit($sql_query, $pg->getNumPerPage(), $pg->getOffset());
				if($object_query == false){
					echo $db->ErrorMsg();
					$db->close();
					exit;
				}else{
					while(!$object_query->EOF){
						$result_query[] = $object_query -> fields;
						$object_query -> moveNext();
					}
				}

				# 催件表的查询结果
				$object_hasten = $db -> SelectLimit($sql_hasten, $pg->getNumPerPage(), $pg->getOffset());
				if($object_hasten == false){
					echo $db->ErrorMsg();
					$db->close();
					exit;
				}else{
					while(!$object_hasten->EOF){
						$result_hasten[] = $object_hasten -> fields;
						$object_hasten -> moveNext();
					}
				}

				// 如果按部门查询，查件催件采用同一个坐席是为了把他们显示在同一行
				if(! empty($depart_id) && empty($extension) && ($disabled_extension == 'true')){
					$result_query[0]['appoint_extension'] = $result_hasten[0]['appoint_extension'];
				}

				# 将用户的分机号保存到一个数组里面去
				foreach($result_query as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_query_extension,$value['appoint_extension']);
						}else{
							array_push($appoint_query_extension,$value['appoint_extension'].$value['time']);
						}
					}
				}

				foreach($result_hasten as $key => $value){
					if(! empty($value['appoint_extension'])){
						if(! in_array($value['time'],$value)){
							array_push($appoint_hasten_extension,$value['appoint_extension']);
						}else{
							array_push($appoint_hasten_extension,$value['appoint_extension'].$value['time']);
						}
					}
				}

				# 处理查件查询结果
				foreach($result_query as $key => $value){
					if(empty($value['appoint_extension'])){
						unset($result_query[$key]);
					}else{
						// 获取受理人所在部门
						$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
						$depart_name = $db -> GetOne($depart_sql);
						$result_query[$key]['dept_name'] = $depart_name;
						$result_query[$key]['query_accept_average_time'] = round($value['query_accept_average_time']);

						// 获取 60 分钟完成数
						$sql_query_60min = $sql_from_query . $sql_condition." AND accept_time <= 60 AND id IN($value[qids])";
						$reuslt_query_60min = $db -> GetOne($sql_query_60min);
						$query_complete_60min_rate = number_format($reuslt_query_60min/$value['query_quantity'],4)  * 100 . '%';
						$result_query[$key]['query_complete_60min'] = empty($reuslt_query_60min) ? 0 : $reuslt_query_60min;
						$result_query[$key]['query_complete_60min_rate'] = empty($query_complete_60min_rate) ? 0 : $query_complete_60min_rate;

						// 若为空则都显示 0
						$result_query[$key]['query_quantity'] = empty($value['query_quantity']) ? 0 : $value['query_quantity'];
						$result_query[$key]['query_accept_time'] = empty($value['query_accept_time']) ? 0 : $value['query_accept_time'];
					}
				}

				# 处理催件查询结果
				foreach($result_hasten as $key => $value){
					if(empty($value['appoint_extension'])){
						unset($result_hasten[$key]);
					}else{
						// 获取受理人所在部门
						$depart_sql = "SELECT dept_name FROM org_department WHERE dept_id = (SELECT dept_id FROM org_user WHERE extension = $value[appoint_extension])";
						$depart_name = $db -> GetOne($depart_sql);
						$result_hasten[$key]['dept_name'] = $depart_name;
						$result_hasten[$key]['hasten_accept_average_time'] = round($value['hasten_accept_average_time']);

						// 获取 30 分钟完成数和完成率
						$sql_hasten_30min = $sql_from_hasten . $sql_condition." AND accept_time <= 30 AND id IN($value[hids])";
						$reuslt_hasten_30min = $db -> GetOne($sql_hasten_30min);
						$hasten_complete_30min_rate = number_format($reuslt_hasten_30min/$value['hasten_quantity'],4)  * 100 . '%';
						$result_hasten[$key]['hasten_complete_30min'] = empty($reuslt_hasten_30min) ? 0 : $reuslt_hasten_30min;
						$result_hasten[$key]['hasten_complete_30min_rate'] = empty($hasten_complete_30min_rate) ? 0 : $hasten_complete_30min_rate;

						// 若为空则都显示 0
						$result_hasten[$key]['hasten_quantity'] = empty($value['hasten_quantity']) ? 0 : $value['hasten_quantity'];
						$result_hasten[$key]['hasten_accept_time'] = empty($value['hasten_accept_time']) ? 0 : $value['hasten_accept_time'];
					}
				}

				# 将 $result_query 的 key 换成 坐席工号
				$result_query = array_combine($appoint_query_extension,$result_query);
				$result_hasten = array_combine($appoint_hasten_extension,$result_hasten);

				$result_query = empty($result_query) ? array(array()) : $result_query ;
				$result_hasten = empty($result_hasten) ? array(array()) : $result_hasten ;

				# 将 $result_query 和 $result_hasten 的公共部分合并，不是公共部分的分别和 对应的空数组合并
				foreach($result_query as $key => $value){
					foreach($result_hasten as $k => $v){
						if($key == $k){
							$list_public[$key] = array_merge($value,$v);
						}else{
							$list_query[$key] = array_merge($value,$hasten_empty);
							$list_hasten[$k] = array_merge($query_empty,$v);
							if(empty($key)){
								unset($list_query[$key]);
							}if(empty($k)){
								unset($list_hasten[$k]);
							}
						}
					}
				}

				# 若 $list_query 和 $list_hasten 中存在和公共 部分相同的 key ,则 uset 掉
				foreach($list_public as $key => $value){
					foreach($list_query as $k2 => $v2){
						if($key == $k2){
							unset($list_query[$k2]);
						}
					}
					foreach($list_hasten as $k3 => $v3){
						if($key == $k3){
							unset($list_hasten[$k3]);
						}
					}

				}

				# 合并这三个数组
				$list = array_merge($list_public,$list_query,$list_hasten);

				# 将用户的分机号保存到一个数组里面去
				foreach($list as $key => $value){
					if(! in_array($value['time'],$value)){
						array_push($appoint_list_extension,$value['appoint_extension']);
					}else{
						array_push($appoint_list_extension,$value['time'].$value['appoint_extension']);
					}
				}

				foreach($list as $key => $value){
					// 查询受理人的中文名
					$appoint_extension_name =  $this -> getNameByExten($value['appoint_extension']);
					$list[$key]['appoint_extension'] = $appoint_extension_name.'('.$value['appoint_extension'].')';

					# 该坐席总查件量、总受理时长、总受理均长
					$list[$key]['total_quantity'] = $value['query_quantity'] + $value['hasten_quantity'];
					$list[$key]['total_accept_time'] = $value['query_accept_time'] + $value['hasten_accept_time'];
					$list[$key]['total_accept_average_time'] =  round($list[$key]['total_accept_time']/$list[$key]['total_quantity']);
				}

				# 将 $list 的 key 换成 坐席工号
				$list = array_combine($appoint_list_extension,$list);
				krsort($list);

				# 计算当前页的合计
				foreach($list as $key => $value){
					if(empty($key)){
						unset($list[$key]);
					}
					$arr_total['query_quantity'] += $value['query_quantity'];
					$arr_total['query_accept_time'] += $value['query_accept_time'];
					$arr_total['query_accept_average_time'] = round($arr_total['query_accept_time']/$arr_total['query_quantity']);
					$arr_total['query_complete_60min'] += $value['query_complete_60min'];
					$arr_total['query_complete_60min_rate'] = number_format($arr_total['query_complete_60min']/$arr_total['query_quantity'],4)  * 100 . '%';

					$arr_total['hasten_quantity'] += $value['hasten_quantity'];
					$arr_total['hasten_accept_time'] += $value['hasten_accept_time'];
					$arr_total['hasten_accept_average_time'] =  round($arr_total['hasten_accept_time']/$arr_total['hasten_quantity']);
					$arr_total['hasten_complete_30min'] += $value['hasten_complete_30min'];
					$arr_total['hasten_complete_30min_rate'] = number_format($arr_total['hasten_complete_30min']/$arr_total['hasten_quantity'],4)  * 100 . '%';

					$arr_total['total_quantity'] += $value['total_quantity'];
					$arr_total['total_accept_time'] += $value['total_accept_time'];
					$arr_total['total_accept_average_time'] = round($arr_total['total_accept_time']/$arr_total['total_quantity']);
				}

			}
			
			# 根据"部门条件"组合条件
			$extenSelect = array();
			if (!empty($depart_id)) {
				//获取所有的子部门
				$list_depart = $this->getNodeChild($dept, $depart_id, 'dept');
				$list_depart .= "$depart_id";    //加上所选部门
				//获取所选部门的座席列表
				$rs = $db->Execute("SELECT * FROM org_user WHERE dept_id in ($list_depart)");
				while (!$rs->EOF) {
					$extenSelect[] = $rs->fields;
					$rs->MoveNext();
				}
				$this->Tmpl['extenSelect'] = $extenSelect;
			} # end if (!empty($depart_id))

		}

		// 导出功能
		if($do == 'export'){
			//导出文件名
			$filename = "accept_man_query_hasten_".date('Y_m_d');

			$this -> createQueryAndHastenExcel($cfg_column,$filename,$list,$arr_total);
		}

		$this->Tmpl['disabled_extension'] = $disabled_extension;	// 保存坐席的灰选状态
		$this->Tmpl['cfg_column'] = $cfg_column;					// 表格表头
		$this->Tmpl['list'] = $list;								// 统计数据
		$this->Tmpl['arr_total'] = $arr_total;						// 最下面一行合计
		$this->display();
	}


	/**
	 * @Purpose      :  导出处理人或者受理人查件催件报表
	 * @Author 		 :  代传荣
	 * @Method Name  :  createQueryAndHastenExcel()
	 * @parameter	 :  array  $cfg_column 查件、催件和合计的列名组成的数组
	 * 					array  $filename   导出的文件名
	 * 					array  $list  	   列表中的统计数据
	 * 					array  $arr_total  列表最下边那一行合计数据
	 * @return	     :  (无)
	 */
	public function createQueryAndHastenExcel($cfg_column,$filename,$list,$arr_total)
	{
		ini_set("max_execution_time",0);  // 默认的该页最久执行时间为

		//导出
		ob_end_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=".$filename.".xls ");
		header("Content-Transfer-Encoding: binary ");
		xlsBOF();

		//第一行
		$export_time = date('Y-m-d H:i:s');
		xlsWriteLabel(0, 0, '导出时间:');
		xlsWriteLabel(0, 1, $export_time);

		if ($_REQUEST['step'] == 'day') {
			$step_name = '日期';
		} else if ($_REQUEST['step'] == 'hour') {
			$step_name = '小时';
		} else if ($_REQUEST['step'] == 'week') {
			$step_name = '周';
		} else if ($_REQUEST['step'] == 'month') {
			$step_name = '月份';
		}

		if($step_name){
			if($_REQUEST['disabled_extension'] == 'true'){
				$xls_columns = array(
					'step_name' => $step_name,
					'dept_name'	 => '部门',
				);
			}else{
				$xls_columns = array(
					'step_name' => $step_name,
					'dept_name'	 => '部门',
					'agent_name' => '座席',
				);
			}
		}else{
			if($_REQUEST['disabled_extension'] == 'true'){
				$xls_columns = array(
					'dept_name'	 => '部门',
				);
			}else{
				$xls_columns = array(
					'dept_name'	 => '部门',
					'agent_name' => '座席',
				);
			}
		}

		//导出excel, 写表头
		if($step_name) {
			$cols = 3;
		}else{
			$cols = 2;
		}
		if (count($_REQUEST['query']) > 0) {
			xlsWriteLabel(1, $cols, '查件');		// 从行号为 1 列好为 3 ，即第 2 行 第 4 列写入
			$cols += count($_REQUEST['query']);
		}

		if (count($_REQUEST['hasten']) > 0) {
			xlsWriteLabel(1, $cols, '催件');
			$cols += count($_REQUEST['hasten']);
		}

		if (count($_REQUEST['total']) > 0) {
			xlsWriteLabel(1, $cols, '合计');
		}

		$cols = 0;
		foreach ($xls_columns as $value) xlsWriteLabel(2, $cols++, $value);

		foreach ($cfg_column['query'] as $key => $value) {
			if (!in_array($key, $_REQUEST['query'])) continue;

			xlsWriteLabel(2, $cols++, $value);
		}

		foreach ($cfg_column['hasten'] as $key => $value) {
			if (!in_array($key, $_REQUEST['hasten'])) continue;
			xlsWriteLabel(2, $cols++, $value);
		}

		foreach ($cfg_column['total'] as $key => $value) {
			if (!in_array($key, $_REQUEST['total'])) continue;
			xlsWriteLabel(2, $cols++, $value);
		}

		//写入统计数据
		$rows = 3 ;
		foreach($list as $value){
			$cols = 0;

			# 写入坐席和部门
			$appoint_extension =  iconv("UTF-8", "GB2312//IGNORE", $value['appoint_extension']);
			$dept_name =  iconv("UTF-8", "GB2312//IGNORE", $value['dept_name']);
			if($step_name){
				xlsWriteLabel($rows, $cols++,$value['time']);
			}
			xlsWriteLabel($rows, $cols++,$dept_name );
			if($_REQUEST['disabled_extension'] == 'true'){

			}else{
				xlsWriteLabel($rows, $cols++,$appoint_extension);
			}

			# 写入查件统计数据
			foreach($cfg_column['query'] as $k1 => $v1){
				if(!in_array($k1,$_REQUEST['query'])){
					continue;
				}else{
					xlsWriteLabel($rows, $cols++, $value[$k1]);
				}
			}

			# 写入催件统计数据
			foreach($cfg_column['hasten'] as $k2 => $v2){
				if(!in_array($k2,$_REQUEST['hasten'])){
					continue;
				}else{
					xlsWriteLabel($rows, $cols++, $value[$k2]);
				}
			}

			# 写入列表右侧那个合计统计数据
			foreach($cfg_column['total'] as $k3 => $v3){
				if(!in_array($k3,$_REQUEST['total'])){
					continue;
				}else{
					xlsWriteLabel($rows, $cols++, $value[$k3]);
				}
			}

			$rows++;

		}

		# 写入列表最下边那个合计统计数据
		if(count($list) > 1 && $arr_total){
			if($step_name){
				if($_REQUEST['disabled_extension'] == 'true'){
					$cols = 1;
				}else{
					$cols = 2;
				}
			}else{
				if($_REQUEST['disabled_extension'] == 'true'){
					$cols = 0;
				}else{
					$cols = 1;
				}
			}
			xlsWriteLabel($rows, $cols++, '合计：');

			# 写入查件合计数据
			foreach($cfg_column['query'] as $k1 => $v1){
				if(!in_array($k1,$_REQUEST['query'])){
					continue;
				}else{
					xlsWriteLabel($rows, $cols++, $arr_total[$k1]);
				}
			}

			# 写入催件合计数据
			foreach($cfg_column['hasten'] as $k2 => $v2){
				if(!in_array($k2,$_REQUEST['hasten'])){
					continue;
				}else{
					xlsWriteLabel($rows, $cols++, $arr_total[$k2]);
				}
			}

			# 写入列表右侧那个合计的合计数据
			foreach($cfg_column['total'] as $k3 => $v3){
				if(!in_array($k3,$_REQUEST['total'])){
					continue;
				}else{
					xlsWriteLabel($rows, $cols++, $arr_total[$k3]);
				}
			}
		}
		xlsEOF();
		exit;
	}

	
	/**
	 * @Purpose		: 质检日志表白日志对话框
	 * @Author		: daicr
	 * @Method		: qualityRemarks()
	 * @Time		: 2017/11/08
	 * @Parameters	: (无)
	 * @Return 		: (无)
	 */
	public function showfeedbackRemarks()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		$uniqueid = $_REQUEST['uniqueid'];
		$sql = "SELECT remarks FROM ss_cdr_feedback WHERE uniqueid LIKE '$uniqueid%'";
		$remarks = $db -> GetOne($sql);
		$this->Tmpl['remarks'] = $remarks;
		$this->Tmpl['uniqueid'] = $uniqueid;
		$this -> display();
	}
	
		
	/**
	 * @Purpose		: 质检日志报表对话框保存备注
	 * @Author		: daicr
	 * @Method		: qualityRemarks()
	 * @Time		: 2017/11/08
	 * @Parameters	: (无)
	 * @Return 		: (无)
	 */
	public function dofeedbackRemarks()
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		$uniqueid = $_REQUEST['uniqueid'];
		$remarks = $_REQUEST['remarks'];
		$sql = "UPDATE `ss_cdr_feedback` SET `remarks` = '$remarks' WHERE `uniqueid` LIKE '$uniqueid%'";
		$result = $db -> Execute($sql);
		if($result){
			goBack(c("保存成功"),"art_reload");
		}else{
			goBack(c("保存失败"),"art_close");
		}
	}
	
	
	/**
	 * @Purpose		: 获取部门的子部门
	 * @Author		: 代传荣（Carroll）
	 * @Method		: getChildDepartId()
	 * @Parameters	: string $departId 部门 id
	 * @Return 		: string $department_ids 该部门的子孙部门
	 */
	public function getChildDepartId($departId,$department_ids='')
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		$sql = "SELECT GROUP_CONCAT(dept_id) as dept_ids FROM org_department WHERE dept_parent IN($departId)";
		$result = $db->getOne($sql);
		if($result){
			$department_ids .= ','.$result;
			return $this-> getChildDepartId($result,$department_ids);
		}else{
			$department_ids .= ','.$departId;
			return $department_ids;
		}
	}

	/**
	 * @Purpose		: 获取部门的下一级子部门
	 * @Author		: 代传荣（Carroll）
	 * @Method		: getNextChildDepartId()
	 * @Parameters	: string $departId 部门 id
	 * @Return 		: string $department_ids 该部门的下一级子部门
	 */
	public function getNextChildDepartId($departId)
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		$sql = "SELECT GROUP_CONCAT(dept_id) as dept_ids FROM org_department WHERE dept_parent =$departId";
		$result = $db->getOne($sql);
		if($result){
			return $result;
		}else{
			return $departId;
		}
	}

	/**
	 * @Purpose		: 获取部门最上级的部门 id
	 * @Author		: 代传荣（Carroll）
	 * @Method		: getParentDepartId()
	 * @Parameters	: string $departId 部门 id
	 * @Return 		: string $parent_id 该部门的最上级部门
	 */
	public function getParentDepartId($departId)
	{
		$this->publicCheckLogin();
		$db = $this->loadDB();
		$sql = "SELECT dept_parent FROM org_department WHERE dept_id=$departId";
		$result = $db->getOne($sql);
		if($result != 0){
			return $this-> getParentDepartId($result);
		}else{
			$parent_id = $departId;
			return $parent_id;
		}
	}

	/*
	 * @Purpose	:	根据所给年份和该年的第几周，得出该周在本年的开始日期和结束日期
	 * @time	:	2017/11/03
	 * @Author	:	daicr
	 * @method	:	getWeekStartAndEnd()
	*/
	function getWeekStartAndEnd ($year,$week=1) {
		header("Content-type:text/html;charset=utf-8");
		date_default_timezone_set("Asia/Shanghai");
		$year = (int)$year;
		$week = (int)$week;
		//按给定的年份计算本年周总数
		$date = new DateTime;
		$date->setISODate($year, 53);	// 参见 http://php.net/manual/zh/datetime.setisodate.php
		// 一年周的最大值为 52
		$weeks = max($date->format("W"),52);	// $date->format("W") 将日期格式化为 周，
		//如果给定的周数大于周总数或小于等于0
		if($week>$weeks || $week<=0){
			return false;
		}
		//如果周数小于10
		if($week<10){
			$week = '0'.$week;
		}
		//当周起止时间戳
		$timestamp['start'] = strtotime($year.'W'.$week);	// strtotime('2009W32');Get timestamp of 32nd week in 2009. 参见：http://cn2.php.net/manual/zh/function.strtotime.php	
		$timestamp['end'] = strtotime('+1 week -1 day',$timestamp['start']);
		//当周起止日期
		$timeymd['start'] = date("m/d",$timestamp['start']);
		$timeymd['end'] = date("m/d",$timestamp['end']);
		
		//返回起始时间戳
		//return $timestamp;
		//返回日期形式
		return $timeymd;
	 }
	 
	/**
     *Purpose       :                 二维数组排序
     *@parameters   :   array  $arr   未处理的结果集
     *                  string $row   需要排序的行
     *@return       ：  array  $arr   排序后的结果
     */
    function arraySort($arr,$row){
        $arr_tmp = array();    
        foreach ($arr as $key => $value) {
            $arr_tmp[] = $value[$row];
        }
        array_multisort($arr_tmp, SORT_DESC, $arr);     // 将 $arr 按 $arr_tmp 的值 ASC 排序
        return $arr;
     }
	 
 
}//end class report
