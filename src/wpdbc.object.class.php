<?php
namespace wpdbc;  // db connector
/**
 * Class DBObjectInterface
 * @package wpdbc
 * Used as object handler for single entries
 */
if (!class_exists('\wpdbc\DBObjectInterface')):
	abstract class DBObjectInterface extends Utils{
		/**
		 * @var array loaded object properties
		 */
		private $properties;

		/**
		 * @var DBTable Singleton
		 */
		protected $table;

		/**
		 * @var bool display the performed sql queries and errors
		 */
		protected $debug;

		/**
		 * @return string Name of extended Database Table object instance
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

		public function __construct(){

			// get table (extended singleton) instance
			$this->table = call_user_func(array('wpdbc\\'.$this->define_db_table(), 'getInstance'));

			if(!is_subclass_of($this->table, 'wpdbc\DBTable')){
				throw new \Exception('Illegal class extension. DBObjectInterface method "define_db_table" must return an object of type "DBTable"');
			}

			// define the data binding
			$this->define_data_binding();

			// todo: initiation from values
		}

		public function __destruct(){
			$this->table = null;
		}

		/**
		 * To check whether object is loaded or not
		 * @throws \Exception
		 */
		public function loaded(){
			// todo: load from init - check if one of the primary keys is loaded
			if(empty($this->properties)){
				throw new \Exception('Object is not loaded.');
			}
		}

		/**
		 * To check whether object is loaded or not
		 * @return bool
		 */
		public function is_loaded(){
			return !empty($this->properties);
		}

		/**
		 * Check if entry exists. Either by single unique key+value or by unique key-pair
		 * @param array $data unique identification data
		 * @return mixed row as object or false if entry does not exist
		 * @throws \Exception if multiple entries with unique values exist
		 */
		// TODO: refactor to cheaper method for existence check usecases (use count, drop values)
		public function exists(array $data){

			// sanitation
			$data = $this->table->validator->sanitize($data, 'get');

			// validation - also necessary in this context (user feedback)
			$result = $this->table->validator->validate('get', $data);
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return false;
			}

			// where
			$where = $this->sql_unique_where($data);

			if(!$where[0]){
				throw new \Exception('No unique search values given.');
			}

			global $wpdb;
			$sql = $wpdb->prepare( 'SELECT * FROM '.$this->table->get_db_table_name().' WHERE '.$where[0], $where[1]);
			$results = $wpdb->get_results( $sql );

			if($this->debug){
				$this->debug('query');
			}

			if(empty($results)){
				// database error - no value found
				return false;
			}elseif(count($results) == 1){
				return $results[0];
			}else{
				throw new \Exception('Database has multiple unique entries.');
			}
		}

		/**
		 * Load all information into the object. Only possible for unique key/key-pair information.
		 * @param array $data unique search data
		 * @param array $return_keys column values to return on success
		 * @return mixed bool if succeeded or array of data specified in $return_keys
		 * @throws \Exception if input key(s) are not a unique identifier
		 */
		public function load(array $data, array $return_keys = array()){

			$obj = $this->exists($data);

			if($obj == false){
				return false;
			}else{
				$this->properties = (array) $obj;
				if(!empty($return_keys)){
					return $this->get($return_keys);
				}else{
					return true;
				}
			}
		}

		/**
		 * Get column values from the loaded object interface. Requires a previous load().
		 * @param array $keys array of columns names to return
		 * @return bool|object single value, object with attributes equal to the extracted column names
		 *                     or false if the column names do not exist
		 * @throws \Exception if object is not loaded
		 */
		public function get($keys = array()){

			// object MUST be loaded
			$this->loaded();

			// string input - single value
			if(is_string($keys)){
				if(isset($this->properties[$keys])){
					return $this->properties[$keys];
				}

				// array input
			}elseif(is_array($keys)){

				// return all
				if(empty($keys)){
					return $this->properties;
				}

				// extract specific fields
				$extracted = array_intersect_key($this->properties, array_flip($keys));

				if(empty($extracted)){
					return false;
				}elseif(count($extracted) == 1){
					$extracted = array_values($extracted);
					return $extracted[0];
				}else{
					return (object) $extracted;
				}
			}

			return false;

		}

		/**
		 * Updates the currently loaded object or an object uniquely specified by $where
		 * @param array $data update data with format: array($column_name => $value)
		 * @param array $where (optional) unique identification with format: array($key => $value)
		 * @return bool|integer 1: success, 0: nothing updated, false: fail/entry does not exist
		 * @throws \Exception
		 */
		public function update(array $data, $where = null){

			// object must be loaded if no search is available
			if($where === null){
				$this->loaded();
			}

			// sanitation
			$data = $this->table->validator->sanitize($data, 'update_data');

			// validation
			$result = $this->table->validator->validate('update_data', $data);
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return false;
			}

			// sanitation
			if($where !== null){
				$where = $this->table->validator->sanitize($where, 'update_where');
				if(empty($where)){
					throw new \Exception("No correct WHERE in UPDATE clause.");
				}
			}

			// validation
			if($where !== null){
				$result = $this->table->validator->validate('update_where', $where);
				if($result === false){
					if($this->debug){
						$this->debug('validation');
					}
					return false;
				}
			}

			// direct manipulation: use current representation as search value
			if($where === null){
				$where = (array)$this->get();
			}

			// data binding
			$result = $this->execute_bound_actions('update_before', $data, $where);
			if($result === false){
				return false;
			}

			// filter update values
			$data = array_intersect_key($data, $this->table->get_db_format());
			if(empty($data)){return false;}
			$value_format = array_intersect_key($this->table->get_db_format(), $data);

			// sort values and format
			ksort($data);
			ksort($value_format);
			$value_format = array_values($value_format);

			// where
			// todo: use sql_unique_where()
			$pk = $this->table->get_db_primary_key();
			if($where !== null){
				$obj = $this->exists($where);
				if($obj == false){
					return false;
				}else{
					$pk_val = $obj->{$pk};
				}
			}else{
				$pk_val = $this->get($pk);
			}

			$where = array($pk => $pk_val);
			$where_format = array($this->table->get_db_format($pk));

			// perform update
			global $wpdb;
			$success = $wpdb->update(
				$this->table->get_db_table_name(),
				$data,          // data
				$where,         // where
				$value_format,  // data format
				$where_format   // where format
			);

			if($this->debug){
				$this->debug('query', array('result'=>$success));
			}

			if($success === false){
				throw new \Exception("An error occurred during the update.");
			}

			$result =  $this->execute_bound_actions('update_after', $data, $success);
			if($result === false){
				return false;
			}

			if($success === 1){
				// update object properties
				$this->properties = array_merge($this->properties, $data);
			}

			return $success;

		}

		/**
		 * Deletes the loaded object or an object uniquely specified by $where
		 * @param array $where (optional) unique identification with format: array($key => $value)
		 * @return bool|integer 1 on success, false on fail
		 * @throws \Exception If $where is invalid or the object is not loaded
		 */
		public function delete($where = null){

			// object must be loaded if no search is available
			if($where === null){
				$this->loaded();
			}

			// sanitation
			if($where !== null){
				$where = $this->table->validator->sanitize($where, 'delete');
				if(empty($where)){
					throw new \Exception("No correct WHERE in DELETE clause.");
				}
			}

			// validation
			if($where !== null){
				$result = $this->table->validator->validate('delete', $where);
				if($result === false){
					if($this->debug){
						$this->debug('validation');
					}
					$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
					return false;
				}
			}

			// direct manipulation: use current representation as search value
			if($where === null){
				$where = (array)$this->get();
			}

			// data binding
			$result = $this->execute_bound_actions('delete_before', $where);
			if($result === false){
				return false;
			}

			$where_sql = '';
			$where_values = array();

			// get where format
			if($where !== null){
				$extract = $this->sql_unique_where($where);
				$where_sql = $extract[0];
				$where_values = $extract[1];

				if(empty($where_sql)){
					throw new \Exception("Objects can only be deleted from a unique identifier e.g. primary key or unique key pairs");
				}

			}else{
				// delete from instance data
				$pk = $this->table->get_db_primary_key();
				$where_sql = $pk.'='.$this->table->get_db_format($pk);
				$where_values = $this->get($pk);
			}

			// delete entry
			global $wpdb;
			$sql = $wpdb->prepare('DELETE FROM '.$this->table->get_db_table_name().' WHERE '.$where_sql, $where_values);
			$success = $wpdb->query($sql);

			if($this->debug){
				$this->debug('query', array('result'=>$success));
			}

			// unset properties to ensure no further manipulations
			if($success === 1){
				$this->properties = array();
			}

			$result = $this->execute_bound_actions('delete_after', $where, $success);
			if($result === false){
				return false;
			}

			return $success;

		}

		/**
		 * Inserts a new table row
		 * @param array $data data with format: array('col1'=>'col1val', 'col2'=>'col2val')
		 * @param bool $force_reload (optional) whether to update the object representation with the inserted values
		 * @return bool 1 on success, false if entry could not be inserted
		 * @throws \Exception if invalid input values are given
		 */
		public function insert(array $data, $force_reload = true){

			// sanitation
			$data = $this->table->validator->sanitize($data, 'insert');

			// validation
			$result = $this->table->validator->validate('insert', $data);
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return false;
			}

			// data binding
			$result = $this->execute_bound_actions('insert_before', $data);
			if($result === false){
				return false;
			}

			// extract valid data columns
			$data = array_intersect_key($data, $this->table->get_db_format());

			if(empty($data)){
				throw new \Exception('No valid input data.');
			}

			// check if entries with same unique key (apart from the primary keys) values exist
			$exists = $this->exists($data);

			if($exists){
				return false;
			}

			$value_format = array_intersect_key($this->table->get_db_format(), $data);

			ksort($value_format);
			ksort($data);

			$value_format = array_values($value_format);

			global $wpdb;
			$success = $wpdb->insert(
				$this->table->get_db_table_name(),
				$data,
				$value_format
			);

			if($this->debug){
				$this->debug('query', array('result'=>$success));
			}

			// === data binding
			$result = $this->execute_bound_actions('insert_after', $data, $success);
			if($result === false){
				return false;
			}

			if($success == 1 && $force_reload == true){
				// reload inserted data. Necessary for default fields
				$this->load(array($this->table->get_db_primary_key() => $wpdb->insert_id));
			}

			return $success;

		}

		/**
		 * Generates a SQL query string to find a unique entry. Does not escape, validate or sanitize data
		 * @param array $data associative array with key names equal column names
		 * @return array array[0]: SQL WHERE string, array[1]: values
		 */
		protected function sql_unique_where(array $data){

			$output = array();
			$unique_data = array();
			$pair_data = array();

			// sql data
			$sql_where = array();
			$values = array();
			$sql_string = '';

			// first check for primary key
			$pk = $this->table->get_db_primary_key();
			if(isset($data[$pk])){
				$data_format = $this->table->get_db_format($pk);
				$sql_where[] = $pk.'='.$data_format;
				$values = array($data[$pk]);
			}else{

				// single unique keys
				if($this->table->get_unique_keys()){
					$unique_data = array_intersect_key($data, array_flip($this->table->get_unique_keys()));

					$data_format = array_intersect_key($this->table->get_db_format(), $unique_data);
					ksort($data_format);
					ksort($unique_data);
					$sql_where[] = urldecode(http_build_query($data_format,'',' OR '));
					$values = array_merge($values, array_values($unique_data));
				}

				// paired unique keys
				if($this->table->get_unique_key_pairs()){
					foreach($this->table->get_unique_key_pairs() as $i=>$pair){

						$inters = array_intersect_key($data, array_flip($pair));
						if(count($pair) == count($inters)){

							// get format
							$format = array_intersect_key($this->table->get_db_format(), $inters);

							ksort($format);
							ksort($inters);

							// build sql: (key1 = %s AND key2 = %d)
							$sql_where[] = '('.urldecode(http_build_query($format,'',' AND ')).')';

							// store values
							$values = array_merge($values, array_values($inters));
						}
					}
				}
			}

			// build string
			$sql_string = implode(' OR ', array_filter($sql_where));
			return array($sql_string, $values);

		}
	}
endif;  // include guard
