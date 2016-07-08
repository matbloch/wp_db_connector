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
		 * Generates a SQL query string to find a unique entry. Does not escape, validate or sanitize data
		 * @param array $data associative array with key names equal column names
		 * @return array/false false: no unique data, array[0]: SQL WHERE string, array[1]: values
		 */
		// TODO: only use single unique constraint instead of all
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

			// use primary key for unique query
			if(isset($data[$pk])){
				$data_format = $this->table->get_db_format($pk);
				$sql_where[] = $pk.'='.$data_format;
				$values = array($data[$pk]);

				// custom unique keys for unique query
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

				if(empty($values)){
					return false;
				}
			}

			// build string: entry must have ALL unique identifiers
			$sql_string = implode(' AND ', array_filter($sql_where));
			return array($sql_string, $values);

		}

		/**
		 * Check if entry exists. Either by single unique key+value or by unique key-pair
		 * Used to check if an entry exists already. No sanitation/validation!
		 * @param array $data unique identification data
		 * @return true: 	entry exists
		 * 		   0:	 	entry does not exist
		 * 		   null:	no unique search data
		 * 		   false:	misc error
		 * @throws	\Exception if multiple unique values exist in the database
		 */
		public function exists(array $data){

			// extract where sql and data
			if(!$where = $this->sql_unique_where($data)){
				return null;
			}

			global $wpdb;
			$sql = $wpdb->prepare( 'SELECT * FROM '.$this->table->get_db_table_name().' WHERE '.$where[0], $where[1]);
			$results = $wpdb->get_results( $sql );

			if($this->debug){
				$this->debug('query');
			}

			if(is_array($results) && empty($results)){
				// database error or no value found
				return 0;
			}elseif(count($results) == 1){
				return true;
			}else{
				throw new \Exception('Database has multiple unique entries.');
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

		// main functions

		/**
		 * Load all information into the object. Only possible for unique key/key-pair information.
		 * @param array $data unique search data
		 * @return mixed true: 	object successfully loaded
		 * 		   		 0:	 	entry does not exist
		 * 		   		 null:	no unique search data
		 * 		   		 false:	misc error
		 * @throws	\Exception if multiple unique values exist in the database
		 */
		public function load(array $data){

			$this->reset_emsg(array('validation', 'load'));

			// sanitation
			$data = $this->table->validator->sanitize($data, 'load');

			// validation - also necessary in this context (user feedback)
			$result = $this->table->validator->validate('load', $data);
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return null;
			}

			// extract where sql and data
			if(!$where = $this->sql_unique_where($data)){
				return null;
			}

			global $wpdb;
			$sql = $wpdb->prepare( 'SELECT * FROM '.$this->table->get_db_table_name().' WHERE '.$where[0], $where[1]);
			$results = $wpdb->get_results( $sql );

			if($this->debug){
				$this->debug('query');
			}

			if(is_array($results) && empty($results)){
				// database error or no value found
				return 0;
			}elseif(count($results) == 1){
				$this->properties = (array) $results[0];
				return true;
			}elseif(count($results > 1)){
				throw new \Exception('Database has multiple unique entries.');
			}

			// misc error
			return false;
		}

		/**
		 * Updates the currently loaded object or an object uniquely specified by $where
		 * @param array $data update data with format: array($column_name => $value)
		 * @param array $search_terms (optional) unique identification with format: array($key => $value)
		 * @return mixed true: 	object successfully updated
		 * 		   		 0:	 	update same data/entry does not exist
		 * 		   		 null:	validation error/no unique search data
		 * 		   		 false:	misc error
		 * @throws	\Exception if object is not loaded and no search terms are provided
		 */
		public function update(array $data, $search_terms = null){

			$this->reset_emsg(array('validation', 'update'));

			// object must be loaded if no search is available
			if($search_terms === null && !$this->is_loaded()){
				throw new \Exception('Object is not loaded.');
			}

			// sanitation
			$data = $this->table->validator->sanitize($data, 'update');

			// validation
			$result = $this->table->validator->validate('update', $data);
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return null;
			}

			// sanitation
			if($search_terms !== null){
				$search_terms = $this->table->validator->sanitize($search_terms, 'update_where');
			}

			// validation
			if($search_terms !== null){
				$result = $this->table->validator->validate('update_where', $search_terms);
				if($result === false){
					if($this->debug){
						$this->debug('validation');
					}
					$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
					return null;
				}
			}

			// direct manipulation: use current representation as search value
			if($search_terms === null){
				$search_terms = (array)$this->get();
			}

			// data binding
			$result = $this->execute_bound_actions('update_before', $data, $search_terms);
			if($result === false){
				return false;
			}

			// extract where sql and data
			if(!$where = $this->sql_unique_where($search_terms)){
				$this->add_emsg('update', 'No unique search data provided.');
				return null;
			}


			// todo: add update values

			// filter update values
			$data = array_intersect_key($data, $this->table->get_db_format());
			if(empty($data)){
				$this->add_emsg('update', 'No valid update data provided.');
				return null;
			}
			$value_format = array_intersect_key($this->table->get_db_format(), $data);

			// build sql: (key1 = %s AND key2 = %d)
			$sql_update_format = urldecode(http_build_query($value_format,'',', '));

			global $wpdb;
			// escape values
			$sql = $wpdb->prepare( 'UPDATE '.$this->table->get_db_table_name().' SET '.$sql_update_format.' WHERE '.$where[0],
				array_merge($data, $where[1]));

			$success = $wpdb->query( $sql );

			if($this->debug){
				$this->debug('query', array('result'=>$success));
			}

			// data binding
			$result =  $this->execute_bound_actions('update_after', $data, $success);
			if($result === false){
				return false;
			}

			if($success === 1){
				// update object properties
				$this->properties = array_merge($this->properties, $data);
				return true;
			} elseif ($success === 0){
				// no rows affected
				return 0;
			}

			// misc error
			return false;
		}

		/**
		 * Deletes the loaded object or an object uniquely specified by $where
		 * @param array $where (optional) unique identification with format: array($key => $value)
		 * @return mixed true: 	object successfully deleted
		 * 		   		 0:	 	entry does not exist
		 * 		   		 null:	validation error/no unique search data
		 * 		   		 false:	misc error
		 * @throws \Exception If $where is invalid and the object is not loaded
		 */
		public function delete($where = null){

			$this->reset_emsg(array('validation', 'delete'));

			// object must be loaded if no search is available
			if($where === null){
				$this->loaded();
			}

			// sanitation
			if($where !== null){
				$where = $this->table->validator->sanitize($where, 'delete');
				if(empty($where)){
					$this->add_emsg('delete', 'Invalid search format.');
					return null;
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
					return null;
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
				if(!$extract = $this->sql_unique_where($where)){
					$this->add_emsg('delete', 'Invalid identifier.');
					return null;
				}

				$where_sql = $extract[0];
				$where_values = $extract[1];
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

			$result = $this->execute_bound_actions('delete_after', $where, $success);
			if($result === false){
				return false;
			}
			// unset properties to ensure no further manipulations
			if($success === 1){
				$this->properties = array();
				return true;
			} elseif ($success === 0){
				// entry does not exist
				return 0;
			}

			// misc error
			return false;
		}

		/**
		 * Inserts a new table row
		 * @param array $data data with format: array('col1'=>'col1val', 'col2'=>'col2val')
		 * @param bool $force_reload (optional) whether to update the object representation with the inserted values
		 * @return mixed true: 	success
		 * 		   		 0:	 	entry exists already
		 * 		   		 null:	validation error/not enough insertion data
		 * 		   		 false:	data binding exit/misc error
		 */
		// TODO: insert if not exists in single query
		public function insert(array $data, $force_reload = true){

			$this->reset_emsg(array('validation', 'insert'));

			// sanitation
			$data = $this->table->validator->sanitize($data, 'insert');

			// validation
			$result = $this->table->validator->validate('insert', $data);
			if($result === false){
				if($this->debug){
					$this->debug('validation');
				}
				$this->add_emsg('validation', $this->table->validator->get_clear_error_msgs());
				return null;
			}

			// data binding
			$result = $this->execute_bound_actions('insert_before', $data);
			if($result === false){
				return false;
			}

			// extract valid data columns
			$data = array_intersect_key($data, $this->table->get_db_format());

			if(empty($data)){
				$this->add_emsg('insert', 'No valid input data available.');
				return null;
			}

			// check if entries with same unique key (apart from the primary keys) values exist
			$exists = $this->exists($data);

			if($exists){
				return 0;
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

			if($success === 1){
				// reload inserted data. Necessary for default fields
				if($force_reload == true){
					$this->load(array($this->table->get_db_primary_key() => $wpdb->insert_id));
				}
				return true;
			}

			// misc error
			return false;

		}
	}
endif;  // include guard

