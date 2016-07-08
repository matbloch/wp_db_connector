<?php
namespace wpdbc;  // db connector
/**
 * Class DBTableSingleton
 * @package wpdbc
 * Singleton skeleton for the database interfaces
 */
if (!class_exists('\wpdbc\DBTableSingleton')):
	abstract class DBTableSingleton
	{

		/**
		 * @var DBTable The reference to *Singleton* instance of this class
		 */
		protected static $instances = array();

		protected function __construct()
		{
		}

		final public static function getInstance()
		{

			$calledClass = get_called_class();

			if (!isset(self::$instances[$calledClass]))
			{
				self::$instances[$calledClass] = new $calledClass();
			}

			return self::$instances[$calledClass];
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		final private function __clone()
		{
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		final private function __wakeup()
		{
		}
	}
endif;  // include guard

/**
 * Used to define table properties
 * Class DBTable
 * @package wpdbc
 */
if (!class_exists('\wpdbc\DBTable')):
	abstract class DBTable extends DBTableSingleton{

		/*
			Usage of extended Class:
			$db_table = DBTable
			$db_table->get_db_table_name();
		 */

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct()
		{
			// load settings from forced parent setters into properties
			$this->db_primary_key = $this->define_db_primary_key();
			$this->db_table_name = $this->define_db_table_name();
			$this->db_format = $this->define_db_format();

			// define unique keys and key pairs
			$this->unique_keys = $this->define_unique_keys();
			$this->unique_key_pairs = $this->define_unique_key_pairs();

			// validation and sanitation
			$this->validator = new Validator($this->define_validation_rules(), $this->define_sanitation_rules());
			$this->validator->add_validation_methods($this->extend_validation_rules());
		}

		protected $db_table_name;   // holds the db values of the object
		protected $db_primary_key;
		protected $db_format;      // defines the value format for $wpdb escaping

		// todo: maybe move to objectHandler? No: validation applies directly to database structure
		public $validator;          // validator object

		/*
		 * format:
		 *      array('col_name1', 'col_name2')
		 */
		protected $unique_keys;

		/*
		 * format:
		 * array(
		 *      array('col_name1', 'col_name2'),
		 *      array('col_name3', 'col_name4'),
		 * )
		 */
		protected $unique_key_pairs;

		/* forced definitions */
		abstract protected function define_db_table_name();
		abstract protected function define_db_primary_key();    // string, single value
		abstract protected function define_db_format();

		/* placeholder functions */
		/** Unique field values: crucial for insert/update
		 * @return array(array())
		 */
		protected function define_unique_key_pairs(){
			return null;
		}
		/** Unique field values: crucial for insert/update
		 * @return array
		 */
		protected function define_unique_keys(){
			return null;
		}
		/**
		 * Defines the validation rules
		 * @return array format: array('column_name'=>'rules')
		 */
		protected function define_validation_rules(){
			return array();
		}
		/**
		 * @return array format:
		 * array(
		 *      'col1'=>array('rule1'=>'message1', 'rule2'=>'message2'),
		 *      'col2'=>array('rule1'=>'message1', 'rule2'=>'message2')
		 *      )
		 */
		protected function define_validation_error_messages(){
			return array();
		}
		/**
		 * Defines the sanitation rules
		 * @return array format: array('column_name'=>'rules')
		 */
		protected function define_sanitation_rules(){
			return array();
		}
		/**
		 * User defined validation rules
		 * @return array format:
		 * array(
		 * 		'not_this_value' => function($field, $context, &$data, $param = null){
		 *			if(!isset($data[$field])){return true;}
		 * 			if($data[$field] == 'test'){
		 * 			}
		 * 		}
		 *
		 * )
		 */
		protected function extend_validation_rules(){
			return array();
		}

		/* getters */
		public function get_db_table_name(){
			return $this->db_table_name;
		}
		public function get_db_primary_key(){
			return $this->db_primary_key;
		}
		public function get_db_format($col_name = NULL){

			if($col_name !== NULL && is_string($col_name)){
				if(isset($this->db_format[$col_name])){
					return $this->db_format[$col_name];
				}
				return false;
			}

			return $this->db_format;
		}
		public function get_unique_keys(){
			return $this->unique_keys;
		}
		public function get_unique_key_pairs(){
			return $this->unique_key_pairs;
		}
	}
endif;  // include guard
