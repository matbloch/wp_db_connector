<?php
namespace wpdbc;  // db connector
/**
 * Class DBObjectsHandler
 * @package wpdbc
 * Used to load and manipulate multiple database entries
 */
if (!class_exists('\wpdbc\DBObjectsHandler')):
	abstract class DBObjectsHandler extends Utils{

		protected $table;      // db setup of type DBTable, inherit public access
		private $objects;      // the loaded objects

		// identifiers for the currently queried objects
		private $sql_where; // current escaped sql WHERE qualifier
		private $group_by;  // column name used to group $objects
		private $limit;
		private $offset;

		/**
		 * @return string Class name of extended DBTable Database Table class
		 */
		abstract protected function define_db_table();

		/**
		 * Defines data binding by making calls to the bind_cation method
		 * @return void
		 */
		protected function define_data_binding(){
			// placeholder function
			// use bind_action here to define the data binding
		}

		public function __construct()
		{
			// get table (singleton) instance
			$this->table = call_user_func(array('wpdbc\\'.$this->define_db_table(), 'getInstance'));

			if(!is_subclass_of($this->table, 'wpdbc\DBTable')){
				throw new \Exception('Illegal class extension. DBObjectInterface method "define_db_table" must return an object of type "DBTable"');
			}

			// define the data binding
			$this->define_data_binding();

		}

		public function __destruct()
		{
			$this->table = null;
		}

		/**
		 * Checks if any objects are loaded
		 * @throws \Exception if no objects are loaded
		 */
		public function loaded(){
			if(empty($this->objects)){
				throw new \Exception('No objects are loaded.');
			}
		}

		public function is_loaded(){
			return !empty($this->objects);
		}

		/**
		 * @return bool whether a search query has been performed or not (objects might be empty)
		 */
		public function is_queried(){
			return !empty($this->sql_where);
		}

		public function queried(){
			if(empty($this->sql_where)){
				throw new \Exception('No query has been performed yet. Method operates on queried objects.');
			}
		}

		/**
		 * Load objects specified by search data
		 * @param array $fields_and column values with 'AND' relation.
		 *              Format: array('col1'=>'searchval1', 'col2'=>array('col2_val1', 'col2_val1'))
		 * @param array $fields_or column values with 'OR' relation.
		 *              Format: array('col1'=>'searchval1', 'col2'=>array('col2_val1', 'col2_val1'))
		 * @param array $args (optional) additional query pagination parameters: limit, offset, group_by
		 * @return bool|int false if query failed (check validation) or number of search results (including 0)
		 * @throws \Exception if invalid search data supplied
		 */
		public function load(
			array $fields_and,
			array $fields_or = array(),
			$args = array('limit'=>-1, 'offset'=>0, 'group_by'=>null)
		){

			// sanitation
			$fields_and = $this->table->validator->sanitize($fields_and, 'get');
			$fields_or = $this->table->validator->sanitize($fields_or, 'get');

			// validation - also necessary in this context (user feedback)
			$result = $this->table->validator->validate('get', $fields_and);
			if($result === true){
				$result = $this->table->validator->validate('get', $fields_or);
			}
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return false;
			}

			// data binding
			$ref_data = array('where_and'=>$fields_and, 'where_or'=>$fields_or);
			$result = $this->execute_bound_actions('get_before', $continue_query, $ref_data, $args);
			if($result === false){
				return false;
			}
			$fields_and = $ref_data['where_and'];
			$fields_or = $ref_data['where_or'];

			// build sql query
			$where_and = $this->prepare_sql_where($fields_and, 'AND');
			$where_or = $this->prepare_sql_where($fields_or, 'OR');

			if(!$where_and && !$where_or){
				throw new \Exception('Invalid search data.');
			}

			$sql_where = array();
			$sql_attr = '';
			$values = array();

			if($where_and){
				$sql_where[] = '('.$where_and['sql'].')';
				$values = array_merge($values, $where_and['values']);
			}
			if($where_or){
				$sql_where[] = '('.$where_or['sql'].')';
				$values = array_merge($values, $where_or['values']);
			}

			$sql_where = implode(' AND ', $sql_where);

			global $wpdb;

			// limit number of results
			if(isset($args['limit']) && $args['limit'] !== -1){
				$sql_attr .= $wpdb->prepare(' LIMIT %d', $args['limit']);
			}
			// offset
			if(isset($args['offset']) && $args['offset'] !== 0){
				$sql_attr .= $wpdb->prepare(' OFFSET %d', $args['offset']);
			}

			// escape where sql
			$sql_where = $wpdb->prepare($sql_where, $values);

			// perform query
			$sql = "SELECT * FROM ".$this->table->get_db_table_name()." WHERE ".$sql_where.$sql_attr;
			$result = $wpdb->get_results($sql);

			if($this->debug){
				$this->debug('query', array('result'=>$result));
			}

			if($result !== NULL){

				// store query parameters
				$this->sql_where = $sql_where;
				$this->objects = $result;

				// group by column name
				if($args['group_by'] !== null){
					$this->group_by = $args['group_by'];
					$this->objects = $this->group($args['group_by'], $result);
				}
				if(isset($args['limit']) && $args['limit'] !== -1){
					$this->limit = $args['limit'];
				}
				if(isset($args['offset']) && $args['offset'] !== 0){
					$this->offset = $args['offset'];
				}

				return count($result);
			}

			return false;
		}

		// TODO: complete. Reload objects based on current query
		public function reload(){
			$this->queried();
		}

		/**
		 * Loads all table entries into the object
		 * @param array $args (optional) additional query pagination parameters: limit, offset, group_by
		 * @return bool|int false if query failed or number of search results (including 0)
		 */
		// TESTED
		public function load_all(
			$args = array('limit'=>-1, 'offset'=>0, 'group_by'=>null)
		){

			// perform query
			global $wpdb;

			$sql = "SELECT * FROM ".$this->table->get_db_table_name();

			// limit number of results
			if(isset($args['limit']) && $args['limit'] !== -1){
				$sql .= ' LIMIT %d';
				$sql = $wpdb->prepare($sql, $args['limit']);
			}
			// offset
			if(isset($args['offset']) && $args['offset'] !== 0){
				$sql .= ' OFFSET %d';
				$sql = $wpdb->prepare($sql, $args['offset']);
			}

			$result = $wpdb->get_results($sql);

			if($this->debug){
				$this->debug('query', array('result'=>$result));
			}

			if($result !== NULL){
				$this->objects = $result;
				$this->sql_where = '1';

				// store pagination values
				if(isset($args['group_by']) && $args['group_by'] !== null){
					$this->grouped_by = $args['group_by'];
					// group objects by column value
					$this->objects = $this->group($args['group_by'], $result);
				}
				if(isset($args['limit']) && $args['limit'] !== -1){
					$this->limit = $args['limit'];
				}
				if(isset($args['offset']) && $args['offset'] !== 0){
					$this->offset = $args['offset'];
				}

				return count($result);
			}

			return false;
		}

		/**
		 * @param array $fields_and
		 * @param array $fields_or
		 * @return bool|integer false on fail, or number of deleted entries (including 0)
		 * @throws \Exception if invalid search fields or no search terms and no previous search
		 */
		// TESTED
		public function delete($continue_query = false, $fields_and = array(), $fields_or = array()){

			// sanitation
			$fields_and = $this->table->validator->sanitize($fields_and, 'delete');
			$fields_or = $this->table->validator->sanitize($fields_or, 'delete');

			// data binding
			$result = $this->execute_bound_actions('delete_before', $continue_query, array('where_and'=>$fields_and, 'where_or'=>$fields_or));
			if($result === false){
				return false;
			}

			// validation
			$result = $this->table->validator->validate('delete', $fields_and);
			if($result === true){
				$result = $this->table->validator->validate('delete', $fields_or);
			}
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return false;
			}

			// build query
			$values = array();
			$sql_where = '';

			if($continue_query){
				// delete currently queried objects
				$this->queried();
				// take currently selected objects
				$sql_where .= $this->sql_where;
			}

			if($fields_and || $fields_or){
				$query = $this->sql_and_or($fields_and, $fields_or);
				if($continue_query){
					$sql_where .= ' AND ';
				}
				$sql_where .= $query['sql'];
				$values = $query['values'];
			}elseif(!$continue_query){
				// no search values given
				throw new \Exception("No search query given.");
			}

			global $wpdb;

			// perform query
			$sql = "DELETE FROM ".$this->table->get_db_table_name()." WHERE ".$sql_where;

			// escape if values are present
			if($values){
				$sql = $wpdb->prepare($sql, $values);
			}

			// perform query
			$success = $wpdb->query($sql);

			if($this->debug){
				$this->debug('query', array('result'=>$success));
			}

			$result = $this->execute_bound_actions('delete_after', $values, $success);
			if($result === false){
				return false;
			}

			return $success;
		}

		/**
		 * @return int number of entries in table
		 */
		public function count_all(){

			global $wpdb;

			// perform query
			$sql = "SELECT COUNT(1) as nr FROM ".$this->table->get_db_table_name();
			$result = $wpdb->get_results($sql);

			if($result){
				return $result[0]->nr;
			}

			return 0;
		}

		public function count(
			array $fields_and,
			array $fields_or = array(),
			$args = array('offset'=>0, 'group_by'=>null)
		){

			// todo: include group by count items with same column values

			if($fields_and || $fields_or){

				$where = $this->sql_and_or($fields_and, $fields_or);

				// perform query
				global $wpdb;
				$sql = "SELECT COUNT(".$this->table->get_db_primary_key().") FROM ".$this->table->get_db_table_name()." WHERE ".$where['sql'];

				// escape if values are present
				$sql = $wpdb->prepare($sql, $where['values']);
				$result = $wpdb->get_results($sql);

				if($result !== NULL){
					return $result;
				}

				return false;

			}else{
				if(!$this->is_loaded()){
					if($this->is_queried()){
						// queried 0 results
						return 0;
					}else{
						$this->loaded();
					}
				}

				return count($this->objects);
			}

		}

		/**
		 * If objects are loaded: update selected group
		 * Else: update all entries in db that match the criteria
		 * @param array $update
		 * @param array $where_and
		 * @param array $where_or
		 * @return bool|int
		 * @throws \Exception
		 */
		// TESTED
		public function update(array $update, $continue_query = false, $where_and = array(), $where_or = array()){

			// extract update values
			$update_values = array_intersect_key($update, $this->table->get_db_format());
			$update_format = array_intersect_key($this->table->get_db_format(), $update);

			// sanitation
			$update = $this->table->validator->sanitize($update, 'update');
			$where_and = $this->table->validator->sanitize($where_and, 'update');
			$where_or = $this->table->validator->sanitize($where_or, 'update');

			// data binding
			$result = $this->execute_bound_actions('update_before', $update, array('where_and'=>$where_and, 'where_or'=>$where_or));
			if($result === false){
				return false;
			}

			// validation - also necessary in this context (user feedback)
			$result = $this->table->validator->validate('update', $update);
			if($result === true){
				$result = $this->table->validator->validate('update', $where_and);
			}
			if($result === true){
				$result = $this->table->validator->validate('update', $where_or);
			}
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return false;
			}

			if(empty($update_values)){
				throw new \Exception('Nothing to update.');
			}

			// check update values for unique values
			if($this->contains_unique_fields($update_values)){
				throw new \Exception('Illegal update: updating unique values on multiple objects.');
			}

			// build sql
			$sql_update = urldecode(http_build_query($update_format,'',', '));
			$where_values = array();
			$sql_where = '';

			if($continue_query){
				// delete currently queried objects
				$this->queried();
				// take currently selected objects
				$sql_where .= $this->sql_where;
			}

			// prepare where
			if($where_and || $where_or){
				$query = $this->sql_and_or($where_and, $where_or);
				if($continue_query){
					$sql_where .= ' AND ';
				}
				$sql_where .= $query['sql'];
				$where_values = $query['values'];
			}elseif(!$continue_query){
				// no search values given
				throw new \Exception("No search query given.");
			}

			global $wpdb;
			$sql = "UPDATE ".$this->table->get_db_table_name()." SET $sql_update WHERE $sql_where";

			// escape if values are present
			if($where_values){
				$sql = $wpdb->prepare($sql, array_merge(array_values($update_values), $where_values));
			}else{
				$sql = $wpdb->prepare($sql, $update_values);
			}

			// perform update
			$result = $wpdb->get_results($sql);

			if($this->debug){
				$this->debug('query', array('result'=>$result));
			}

			// update current representation
			if($where_values){
				// $this->reload()
			}

			$bound_result =  $this->execute_bound_actions('update_after', $update_values, $result);
			if($bound_result === false){
				return false;
			}

			if($result !== NULL){
				return $result;
			}

			return false;

		}

		private function contains_unique_fields(array $data){

			$unique_data = array_intersect_key($data, array_flip($this->table->get_unique_keys()));

			// unique columns
			if(!empty(array_intersect_key($data, array_flip($this->table->get_unique_keys())))){
				return true;
			}

			// unique column pairs
			if($this->table->get_unique_key_pairs()) {
				foreach ($this->table->get_unique_key_pairs() as $i => $pair) {
					$inters = array_intersect_key($data, array_flip($pair));
					if (count($pair) == count($inters)) {
						return true;
					}
				}
			}

			return false;
		}

		/** get values for a specific column
		 * @param $key string column name
		 * @return array column values
		 */
		public function get_col($key){
			return array_map(function($o) use ($key){
				return $o->{$key};
			}, $this->objects);
		}

		/**
		 * @param null $key optional grouping by a column name
		 * @return array objects
		 */
		public function get_objects($key = null){

			if($key != null){
				return $this->group($key, $this->objects);
			}else{
				return $this->objects;
			}

		}

		// TODO: direct grouping in SQL-Query
		/**
		 * @param $key string column name
		 * @param array $data
		 * @return array
		 * @throws \Exception if key has invalid format
		 */
		protected function group($key, array $data){
			$d = array();

			if(!preg_match('^[a-zA-Z_][a-zA-Z0-9_]*^', $key)){
				throw new \Exception('Invalid grouping column name "'.$key.'".');
			}

			foreach($data as $entry){
				if(isset($entry->{$key})){
					$d[$entry->{$key}][] = $entry;
				}
			}

			return $d;
		}

		// TODO: include additional where condition
		public function edit($data){



		}

		/**
		 * removes sub-arrays from input data and returns them
		 * @param $data array input data
		 * @return array sub-arrays in input data
		 */
		public function extract_or_fields(&$data){

			$or = array();
			foreach($data as $i=>&$field){
				if(is_array($field)) {
					$or[$i] = $field;
					unset($data[$i]);
				}
			}
			return $or;
		}

		/**
		 * Generates a valid (no validation/sanitation, available columns) WHERE query for the input data
		 * @param $data array format: array('col1'=>'val1', 'col2'=>array('or_val1', 'or_val2'))
		 * @param string $relation relation between the data (AND/OR)
		 * @return array|bool false if no valid columns. array('sql'=>string $where_sql, 'values'=>array $data)
		 * @throws \Exception if invalid relation specified
		 */
		private function prepare_sql_where($data, $relation = 'AND'){

			$relation = strtolower($relation);

			if($relation != 'and' && $relation != 'or'){
				throw new \Exception('Invalid relation specified: '.$relation);
			}

			$keys_and = array();
			$keys_or = array();

			// extract OR conditions
			$or_data = $this->extract_or_fields($data);

			// extract valid fields
			$format_and = array_intersect_key($this->table->get_db_format(), $data);
			$format_or = array_intersect_key($this->table->get_db_format(), $or_data);

			// do not execute query if no arguments given
			if(empty($format_and) && empty($format_or)){
				return false;
			}

			// sort by keys
			ksort($format_and);
			ksort($format_or);

			// extract corresponding values
			$and_values = array_intersect_key($data, $this->table->get_db_format());
			$or_values = array_intersect_key($or_data, $this->table->get_db_format());

			ksort($and_values);
			ksort($or_values);

			// build sql string
			$sql_where = '';
			$sql_array = array();

			if($format_and){
				// form sql by merging key and formats
				if($relation == 'and'){
					$sql_array[] = urldecode(http_build_query($format_and,'',' AND '));
				}else{
					$sql_array[] = urldecode(http_build_query($format_and,'',' OR '));
				}
			}

			// $or data always contains array of more than one value
			foreach($or_data as $col_name=>$values){

				$format = $format_or[$col_name];
				$or = $col_name.' = '.$format;
				// repeat OR condition for same key = format
				$sql_array[] = $col_name.' IN ('.$format.str_repeat(','.$format, count($values)-1).')';
			}

			// form string
			$sql_where = implode(' '.$relation.' ', $sql_array);

			// merge values
			$and_values = array_values($and_values);
			if(!empty($or_values)){
				// collapse array or values
				$or_values = call_user_func_array('array_merge', $or_values);
			}

			return array('sql' =>$sql_where, 'values'=>array_merge($and_values, $or_values));

		}

		/**
		 * Builds a SQL Query analog to 'prepare_sql_where' but with 'and' & 'or' fields combined (AND)
		 * @param array $fields_and array format: array('col1'=>'val1', 'col2'=>array('or_val1', 'or_val2'))
		 *                          output: (col1=val1 AND col2 in (or_val1, or_val2))
		 * @param array $fields_or  array format: array('col1'=>'val1', 'col2'=>array('or_val1', 'or_val2'))
		 *                          output: (col1=val1 OR col2 in (or_val1, or_val2))
		 * @return array array('sql'=>string SQL query with format specifiers, 'values'=>array corresponding values)
		 * @throws \Exception   invalid query fields
		 */
		protected function sql_and_or(array $fields_and, $fields_or = array()){

			$where_and = $this->prepare_sql_where($fields_and, 'AND');
			$where_or = $this->prepare_sql_where($fields_or, 'OR');

			if(!$where_and && !$where_or){
				throw new \Exception('Invalid search data.');
			}

			$sql = array();
			$values = array();

			if($where_and){
				$sql[] = '('.$where_and['sql'].')';
				$values = array_merge($values, $where_and['values']);
			}
			if($where_or){
				$sql[] = '('.$where_or['sql'].')';
				$values = array_merge($values, $where_or['values']);
			}

			$sql = implode(' AND ', $sql);

			return array('sql'=>$sql, 'values'=>$values);

		}

	}
endif;  // include guard
