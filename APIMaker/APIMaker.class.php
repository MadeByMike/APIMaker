<?php
class APIMaker {

	function __construct($options = false){
		// Merge options
		$defaults = array(
			'config_path' => dirname(__FILE__),
			'rules' => '',
			'echo' => true
		);
		ob_start();
		if($options){
			$this->options = array_merge($defaults,$options);
		} else {
			$this->options = $defaults;
		}

		// Load config file
		$this->debug = new debug;
		$this->templateEngine = new templateEngine;
		
		if(isset($this->options['engine'])){
			$this->templateEngine->custom($this->options['engine']);
		}
		// Load config file
		if (file_exists($this->options['config_path'] . '/api_config.php')) {
			require $this->options['config_path'] . '/api_config.php';
		}

		// Configure db
		try {
			$this->db = new PDO('mysql:host='. DB_HOST .';dbname='. DB_NAME .';charset=utf8', DB_USERNAME, DB_PASSWORD);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE); // Emulated prepare wrongly encapsulates Limit and Order values
			$this->init();
			$this->template_resuls = ob_get_contents();
			if($this->options['echo']){
				if($this->rules_file->attributes()->mime || ($this->rules_file->attributes()->format == "json") ){
					header("Content-Type: ".$this->rules_file->attributes()->mime);
				}
				ob_end_flush();
			}else{
				ob_end_clean();
			}
		} catch(Exception $exc) {
			$this->debug->log($exc->getMessage(),'error');
	 		$this->throw_error();
		}

	}

	function load_rules_file(){
		if($this->options['rules']){
			$get_rules = $this->options['rules'];
		}else{
			$get_rules = isset($_REQUEST['rules']) ? $_REQUEST['rules'] : false;
		}
		if(!$get_rules) {
			if(!$get_rules) {
				$this->debug->log('No rules file','error');
				$this->throw_error();
			}
		}
		$get_rules = urldecode($get_rules);
		if(preg_match("/[^A-Za-z0-9-_]/", $get_rules)){
			$this->debug->log('Unsafe rules filename','error');
			$this->throw_error(true, 'HTTP/1.0 403 Forbidden');
		}
		$this->rules_file = @simplexml_load_file(RULES_PATH . $get_rules . '.xml');
		if(!$this->rules_file) {
			$this->debug->log('Could not load XML rules file '. RULES_PATH . $get_rules . '.xml', 'error');
			$this->throw_error();
		}
	}
	function validate_rules_file(){
		$xml = new DOMDocument();
		libxml_use_internal_errors(true);
		$xml->loadXML($this->rules_file->asXML());
		if(!$xml->schemaValidate(RULES_PATH . 'rules.xsd')){
			$errors = libxml_get_errors(); 
			$this->debug->log('XML rules file did not validate','error');
			$this->debug->log($errors ,'error');
			$this->throw_error();
		}
	}
	function get_results(){

		$groups = $this->process_groups($this->rules_file->filter->group);
		$sql_select = '';
		$sql_from = '';
		$sql_limit = '';
		$sql_sort = '';
		$sql_join = '';
		$page = 0;
		$start = 0;
		$tables = explode(',',$this->rules_file->attributes()->table);
		foreach($tables as $table){
			$sql_from .= '`'. $table .'`, ';
		}
		$sql_from = rtrim($sql_from, ' ');
		$sql_from = rtrim($sql_from, ',');
		if($this->rules_file->select){
			foreach($this->rules_file->select->children() as $child){
				if($child->attributes()->table ){
					$sql_select .= $child->attributes()->table .'.'. $child .', ';
				}else{
					$sql_select .= $tables[0] . '.' . $child .', ';
				}
			}
			$sql_select = rtrim($sql_select, ' ');
			$sql_select = rtrim($sql_select, ',');
		} else {
			$sql_select = '*';
		}
		if($this->rules_file->join){
			if($this->rules_file->join->attributes()->type){
				$sql_join .= $this->rules_file->join->attributes()->type .' JOIN ';
			} else {
				$sql_join .= 'LEFT JOIN ';
			}
			$sql_join .= $this->rules_file->join->attributes()->table .' ON ';
			if($this->rules_file->join->objectName[0]->attributes()->table ){
				$sql_join .= $this->rules_file->join->objectName[0]->attributes()->table .'.'. $this->rules_file->join->objectName[0];
			}else{
				$sql_join .= $this->rules_file->join->objectName[0];
			}
			$sql_join .= ' = ';
			if($this->rules_file->join->objectName[0]->attributes()->table ){
				$sql_join .= $this->rules_file->join->objectName[1]->attributes()->table .'.'. $this->rules_file->join->objectName[1];
			}else{
				$sql_join .= $this->rules_file->join->objectName[1];
			}
		}
		$sql_count = 'SELECT count(*) FROM ' . $sql_from  .' '. $sql_join;
		$sql_select = 'SELECT '. $sql_select .' FROM ' . $sql_from  .' '. $sql_join;
		$sql_where = ($groups->query_part == '') ? '' : 'WHERE ' . $groups->query_part;
		$sql_count .= ' '. $sql_where;

		if($this->rules_file->sort){
			if($this->rules_file->sort->objectName->attributes()->table ){
				$table = $this->rules_file->sort->objectName->attributes()->table .'.';
			}else{
				$table = '';
			}
			$sql_sort = 'ORDER BY ' . $table . $this->rules_file->sort->objectName . ' ' . $this->rules_file->sort->sortDirection;
		}
		$this->debug->log('Count sql: ' .$sql_count, 'info');
		$this->debug->log('Prepared variables:');
		$this->debug->log($groups->query_values, 'info');
		$count = $this->db->prepare($sql_count);
		$count->execute($groups->query_values);
		$rows_count = $count->fetchColumn();
		if($rows_count == 0){
			$this->debug->log('No results found','error');
	 		$this->throw_error();		
		}
		$per_page = $rows_count;
		
		if($this->rules_file->attributes()->resultsPerPage){
			$per_page = (int) $this->rules_file->attributes()->resultsPerPage;
			$pages = ceil($rows_count/$per_page);
			$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			if(!is_numeric($page) || !($page>0 && $page<=$pages) ){
				$this->debug->log('Invalid value for page', 'warning');
				$page = 1;
			}
			$start = ($page-1) * $per_page;
			
			array_push($groups->query_values, $start);
			$sql_limit = 'LIMIT ?, ' . $per_page;
			$end = $start + $per_page;
		}else if($this->rules_file->attributes()->recordsAllowed){
			$per_page = $this->rules_file->attributes()->recordsAllowed;
			$end = $this->rules_file->attributes()->recordsAllowed;
			$sql_limit = 'LIMIT 0, ' . $end;
		}
		
		$this->debug->log('Prepared sql: ' .$sql_select . ' ' . $sql_where . ' ' . $sql_sort . ' ' . $sql_limit, 'info');
		$this->debug->log('Prepared variables:');
		$this->debug->log($groups->query_values, 'info');
		$prepare = $this->db->prepare($sql_select . ' ' . $sql_where . ' ' . $sql_sort . ' ' .$sql_limit);
		$prepare->execute($groups->query_values);
		$results = $prepare->fetchAll(PDO::FETCH_ASSOC);
		//Create a results object
		$summary = array(
			'total' => (int)$rows_count, 
			'per_page' => (int)$per_page, 
			'page' => (int)($page), 
			'pages' => (int)$pages, 
			'start' => (int)$start+1, 
			'end' => (int)(isset($end) ? $end : $rows_count)
		);
		$this->results = (object) array('summary'=> $summary, 'results' => $results);
		if(isset($this->rules_file->withResult)){
			$this->results =  $this->with_result($this->rules_file->withResult->children(), $this->results);
		}
		$this->debug->log('Query result:');
		$this->debug->log($this->results, 'info');
	}
	function render(){
		if(isset($_REQUEST['json']) || ($this->rules_file->attributes()->format == "json") ){
			return $this->templateEngine->json($this->results);
		}else{
			return $this->templateEngine->render($this->rules_file->template, $this->results);
		}		
	}
	function init(){
		$this->load_rules_file();
		$this->validate_rules_file();
		$this->get_results();
		// Render template
		echo $this->render();
	}	
	function with_result($with_result, $rows){
		foreach($with_result as $child){
			if($child->getName() == 'field'){
				$i=0;
				$field_name = (string)$child->objectName;
				foreach($rows->results as $row){
					if(isset($row[$field_name])){
						switch ($child->function) {
							case "replace":
								$rows->results[$i][$field_name] = str_ireplace($child->find,  $child->replace, $row[$field_name]);
							break;
							case "formatDate":
								$parseDate = strtotime($row[$field_name]);
								if($parseDate){
									$rows->results[$i][$field_name] = date($child->format, $parseDate);
								}
							break;
							case "htmlEscape":
								$rows->results[$i][$field_name] = htmlspecialchars($row[$field_name]);
							break;
							case "jsonEncode":
								$rows->results[$i][$field_name] = json_encode($row[$field_name]);
							break;
						}
					}
					$i++;
				}
			}
		}
		return $rows;
	}
	function throw_error($die=true, $header='HTTP/1.0 404 Not Found'){
			if(isset($this->rules_file->errorMsg)){
				echo $this->rules_file->errorMsg;
			} else {
				echo DEFAULT_ERROR_MSG;
			}
			if($die){
				header($header);
				die();
			}
	}
	function process_groups($group){
		$operator =  'AND';
		if(strtoupper($group->attributes()->operator) == 'OR'){
			$operator =  'OR';
		}
		$query_values = array();
		$query_part = '';
		
		$i = 0; $f = 0;
		foreach($group->children() as $child){
			if($child->getName() == 'field'){
				if(isset($child->formName)){
					$match = (isset($_REQUEST["{$child->formName}"]) && $_REQUEST["{$child->formName}"] != '') ? $_REQUEST["{$child->formName}"] : $child->defaultValue;
				} else {
					$match = $child->defaultValue;
				}
				$match = (string) $match;
				
				if($child->attributes()->required && (!$match || $match == '')){
					$this->debug->log('Required field '.$child->objectName.' missing', 'error');
					$this->throw_error();
				}
				
				//Attempt to select the correct type for exact matches in PDO
                if(is_numeric($match)){
					if($match % 1 === 0){
						settype($match, 'integer');
					} else {
						settype($match, 'float');
					}
                }else{
                    settype($match, 'string');
                }
				if($match != ''){
					if($f > 0 && $query_part != ''){
						//This is the operator between fields
						$query_part .= ' ' . $operator . ' ';						
					}
					if($child->objectName->attributes()->table ){
						$query_part .= $child->objectName->attributes()->table .'.';
					}
					$query_part .= (string)($child->objectName);
					switch ($child->condition) {
						case "equals":
							$query_part .= ' = ';
							if(is_numeric($match)){
								$query_part .= '?';
							}
							$query_values[] = $match ;
							break;
						case "notEquals":
							$query_part .= ' != ';
							$query_part .= '?';
							$query_values[] = $match;
							break;
						case "contains":
							$query_part .= ' LIKE ';
							$query_part .= '?';
							$query_values[] = "%". $match . "%";
							break;
						case "notContains":
							$query_part .= ' NOT LIKE ';
							$query_part .= '?';
							$query_values[] = "%". $match . "%";
							break;
						case "greaterThan":
							$query_part .= ' > ';
							$query_part .= '?';
							$query_values[] = $match ;
							break;
						case "lessThan":
							$query_part .= ' < ';
							$query_part .= '?';
							$query_values[] = $match ;
							break;
						case "greaterThanOrEqual":
							$query_part .= ' >= ';
							$query_part .= '?';
							$query_values[] = $match ;
							break;
						case "lessThanOrEqual":
							$query_part .= ' <= ';
							$query_part .= '?';
							$query_values[] = $match ;
							break;
						case "startsWith":
							$query_part .= ' LIKE ';
							$query_part .= '?';
							$query_values[] = $match ."%";
							break;
						case "endsWith":
							$query_part .= ' LIKE ';
							$query_part .= '?';
							$query_values[] = "%".$match;
							break;
						default: // Default LIKE
							$query_part .= ' LIKE ';
							$query_part .= '?';
							$query_values[] = "%". $match . "%";
							break;
					}
				}
				$f++;
			}
			
			if($child->getName() == 'group'){
				//This is the operator between groups
				$process_group = $this->process_groups($child);
				if(($i > 0 || $f > 0) && $query_part != "" && $process_group->query_part !=""){
					$query_part .= ' ' . $operator . ' ';
				}
				$query_part .=  $process_group->query_part;
				$query_values =  array_merge($query_values, $process_group->query_values);
				$i++;
			}
			
		}
		if($query_part != ""){
			$query_part = '('.$query_part.')';
		}

		return  (object) array('query_part' => $query_part, 'query_values' => $query_values );
	}
}

class debug {
	public function log ($obj, $typ=''){
		if(isset($_REQUEST['debug']) && APP_DEBUG){
			/**
			 * Send debug info to the JavaScript console
			 */ 
		    if($typ == 'error'){
			    if(is_array($obj) || is_object($obj)){
					echo("<script>console.error('PHP: ');console.error(".json_encode($obj).");</script>");
				} else {
					echo("<script>console.error('PHP: ".$obj."');</script>");
				}
		    }elseif($typ == 'warn'){
			    if(is_array($obj) || is_object($obj)){
					echo("<script>console.warn('PHP: ');console.warn(".json_encode($obj).");</script>");
				} else {
					echo("<script>console.warn('PHP: ".$obj."');</script>");
				}
		    }elseif ($typ == 'info') {
			    if(is_array($obj) || is_object($obj)){
					echo("<script>console.info('PHP: ');console.info(".json_encode($obj).");</script>");
				} else {
					echo("<script>console.info('PHP: ".$obj."');</script>");
				}			    	
		    } else {
			    if(is_array($obj) || is_object($obj)){
					echo("<script>console.log('PHP: ');console.log(".json_encode($obj).");</script>");
				} else {
					echo("<script>console.log('PHP: ".$obj."');</script>");
				}
			}
		}

	}
}

class templateEngine {
	//load template engine
	function __construct($engine=false){
		if (file_exists(dirname(__FILE__) . '/Mustache/Autoloader.php')) {
			require dirname(__FILE__) . '/Mustache/Autoloader.php';
			Mustache_Autoloader::register();
			$this->engine = new Mustache_Engine();
			$this->render = array($this, 'default_render');
		} else {
			$this->render = array($this, 'no_engine');
		}
		if($engine){
			$this->custom($engine);
		}
	}

	public function custom($custom_function){
		$this->render = $custom_function;	
	}
	public function render ($template, $resutls){
		return call_user_func_array($this->render,array($template, $resutls));
	}
	public function json($resutls){
		return json_encode($resutls);
	}
	private function default_render($template, $resutls){
		return $this->engine->render($template, $resutls);
	}
	private function no_engine($template, $resutls){
		return 'Cannot show results no template engine found!';
	}
}
