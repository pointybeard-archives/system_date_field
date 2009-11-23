<?php

	Class fieldSystemDate extends Field{

		const SIMPLE = 0;
		const REGEXP = 1;
		const RANGE = 3;
		const ERROR = 4;
		
		private $key;
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('System Date');
			$this->key = 1;
		}

		/*function allowDatasourceOutputGrouping(){
			return true;
		}
		
		function allowDatasourceParamOutput(){
			return true;
		}		
		
		function canFilter(){
			return true;
		}
		
		public function canImport(){
			return true;
		}*/
		
		private static function __dateFromEntryID($entry_id){
			return Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_entries` WHERE `id` = {$entry_id} LIMIT 1");
		}

		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null) {
			return;
			
			$name = $this->get('element_name');
			$value = null;
			
			// New entry:
			if (is_null($data)) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, null);
			}
			
			// Empty entry:
			else if (isset($data['gmt']) && !is_null($data['gmt'])) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $data['gmt']);
			}
			
			$label = Widget::Label($this->get('label'));
			$span = new XMLElement('span', '<p>' . $value . '</p>');
			$label->appendChild($span);
			$label->setAttribute('class', 'file');
			
			//if (!is_null($error)) {
			//	$label = Widget::wrapFormElementWithError($label, $error);
			//}
			
			$wrapper->appendChild($label);
		}
		
		function checkPostFieldData($data, &$message, $entry_id=NULL){
			return self::__OK__; 	
		}
		
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;
 			return NULL;
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode=NULL, $entry_id=NULL) {
			$row = self::__dateFromEntryID($entry_id);
			
			if(isset($row['local']) && !is_null($row['local'])) {
				$wrapper->appendChild(General::createXMLDateObject($data['local'], $this->get('element_name')));
			}
		}

		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id=NULL) {
			
			$row = self::__dateFromEntryID($entry_id);

			/*
			Array
			(
			    [id] => 26240
			    [section_id] => 40
			    [author_id] => 0
			    [creation_date] => 2009-08-05 12:20:44
			    [creation_date_gmt] => 2009-08-05 02:20:44
			)
			*/

			$value = DateTimeObj::get(__SYM_DATE_FORMAT__, strtotime($row['creation_date_gmt'] . ' +00:00'));
			
			return parent::prepareTableValue(array('value' => $value), $link);
		}	
		
		public function getParameterPoolValue($data){
     		return DateTimeObj::get('Y-m-d H:i:s', $data['local']);
		}	
			
		public function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array('year' => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				
				$info = getdate($data['local']);
				
				$year = $info['year'];
				$month = ($info['mon'] < 10 ? '0' . $info['mon'] : $info['mon']);
				
				if(!isset($groups['year'][$year])) $groups['year'][$year] = array('attr' => array('value' => $year),
																				  'records' => array(), 
																				  'groups' => array());
				
				if(!isset($groups['year'][$year]['groups']['month'])) $groups['year'][$year]['groups']['month'] = array();
				
				if(!isset($groups['year'][$year]['groups']['month'][$month])) $groups['year'][$year]['groups']['month'][$month] = array('attr' => array('value' => $month),
																				  					  'records' => array(), 
																				  					  'groups' => array());		
																						

				$groups['year'][$year]['groups']['month'][$month]['records'][] = $r;

			}

			return $groups;

		}


		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			//$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`e`.`creation_date_gmt` $order");
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			
			if(self::isFilterRegex($data[0])) return parent::buildDSRetrivalSQL($data, $joins, $where, $andOperation);
			
			$parsed = array();

			foreach($data as $string){
				$type = self::__parseFilter($string);
				
				if($type == self::ERROR) return false;
				
				if(!is_array($parsed[$type])) $parsed[$type] = array();
				
				$parsed[$type][] = $string;
			}

			foreach($parsed as $type => $value){
				
				switch($type){
				
					case self::RANGE:
						$this->__buildRangeFilterSQL($value, $joins, $where, $andOperation);
						break;
				
					case self::SIMPLE:
						$this->__buildSimpleFilterSQL($value, $joins, $where, $andOperation);
						break;
								
				}
			}
			
			return true;
		}
		
		protected function __buildSimpleFilterSQL($data, &$joins, &$where, $andOperation=false){
			
			$field_id = $this->get('id');
			
			if($andOperation):
			
				foreach($data as $date){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".$this->key."` ON `e`.`id` = `t$field_id".$this->key."`.entry_id ";
					$where .= " AND DATE_FORMAT(`t$field_id".$this->key."`.value, '%Y-%m-%d') = '".DateTimeObj::get('Y-m-d', strtotime($date))."' ";
					
					$this->key++;
				}
							
			else:
				
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".$this->key."` ON `e`.`id` = `t$field_id".$this->key."`.entry_id ";
				$where .= " AND DATE_FORMAT(`t$field_id".$this->key."`.value, '%Y-%m-%d') IN ('".@implode("', '", $data)."') ";
				$this->key++;
				
			endif;			
			
			
		}
		
		protected function __buildRangeFilterSQL($data, &$joins, &$where, $andOperation=false){	
			
			$field_id = $this->get('id');
			
			if(empty($data)) return;
			
			if($andOperation):
				
				foreach($data as $date){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".$this->key."` ON `e`.`id` = `t$field_id".$this->key."`.entry_id ";
					$where .= " AND (DATE_FORMAT(`t$field_id".$this->key."`.value, '%Y-%m-%d') >= '".DateTimeObj::get('Y-m-d', strtotime($date['start']))."' 
								     AND DATE_FORMAT(`t$field_id".$this->key."`.value, '%Y-%m-%d') <= '".DateTimeObj::get('Y-m-d', strtotime($date['end']))."') ";
								
					$this->key++;
				}
							
			else:

				$tmp = array();
				
				foreach($data as $date){
					
					$tmp[] = "(DATE_FORMAT(`t$field_id".$this->key."`.value, '%Y-%m-%d') >= '".DateTimeObj::get('Y-m-d', strtotime($date['start']))."' 
								     AND DATE_FORMAT(`t$field_id".$this->key."`.value, '%Y-%m-%d') <= '".DateTimeObj::get('Y-m-d', strtotime($date['end']))."') ";
				}
				
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".$this->key."` ON `e`.`id` = `t$field_id".$this->key."`.entry_id ";
				$where .= " AND (".@implode(' OR ', $tmp).") ";
				
				$this->key++;
						
			endif;			
			
		}
		
		protected static function __cleanFilterString($string){
			$string = trim($string);
			$string = trim($string, '-/');
			
			return $string;
		}
		
		protected static function __parseFilter(&$string){
			
			$string = self::__cleanFilterString($string);
			
			## Check its not a regexp
			if(preg_match('/^regexp:/i', $string)){
				$string = str_replace('regexp:', '', $string);
				return self::REGEXP;
			}
			
			## Look to see if its a shorthand date (year only), and convert to full date
			elseif(preg_match('/^(1|2)\d{3}$/i', $string)){
				$string = "$string-01-01 to $string-12-31";
			}	
			
			elseif(preg_match('/^(earlier|later) than (.*)$/i', $string, $match)){
										
				$string = $match[2];
				
				if(!self::__isValidDateString($string)) return self::ERROR;	
				
				$time = strtotime($string);

				switch($match[1]){
					case 'later': $string = DateTimeObj::get('Y-m-d H:i:s', $time+1) . ' to 2038-01-01'; break;
					case 'earlier': $string = '1970-01-03 to ' . DateTimeObj::get('Y-m-d H:i:s', $time-1); break;
				}

			}

			## Look to see if its a shorthand date (year and month), and convert to full date
			elseif(preg_match('/^(1|2)\d{3}[-\/]\d{1,2}$/i', $string)){
				
				$start = "$string-01";
				
				if(!self::__isValidDateString($start)) return self::ERROR;
				
				$string = "$start to $string-" . date('t', strtotime($start));
			}
					
			## Match for a simple date (Y-m-d), check its ok using checkdate() and go no further
			elseif(!preg_match('/to/i', $string)){

				if(!self::__isValidDateString($string)) return self::ERROR;
				
				$string = DateTimeObj::get('Y-m-d H:i:s', strtotime($string));
				return self::SIMPLE;
				
			}
		
			## Parse the full date range and return an array
			
			if(!$parts = preg_split('/to/', $string, 2, PREG_SPLIT_NO_EMPTY)) return self::ERROR;
			
			$parts = array_map(array('self', '__cleanFilterString'), $parts);

			list($start, $end) = $parts;
			
			if(!self::__isValidDateString($start) || !self::__isValidDateString($end)) return self::ERROR;
			
			$string = array('start' => $start, 'end' => $end);

			return self::RANGE;
		}
		
		protected static function __isValidDateString($string){

			$string = trim($string);
			
			if(empty($string)) return false;
			
			## Its not a valid date, so just return it as is
			if(!$info = getdate(strtotime($string))) return false;
			elseif(!checkdate($info['mon'], $info['mday'], $info['year'])) return false;

			return true;	
		}
		
		public function isSortable(){
			return true;
		}		

		public function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;	
			
			$fields = array();

			$fields['field_id'] = $id;
			
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");			
			$this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());
					
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			$this->appendShowColumnCheckbox($wrapper);			
		}

		public function createTable(){
			return true;
		}

	}

