<?php

/**
 * @author      Matthias Bloch (https://github.com/matbloch)
 * @copyright   Copyright (c) 2016
 *
 * @version     1.0
 */

namespace wpdbc;  // db connector

/**
 * Class Utils
 * @package wpdbc
 * Used in DB Object Interfaces
 */
abstract class Utils{

    /*
     * Example:
     * public function insert($data, $args){
     *
     *      $result = $this->utils->execute_bound_actions('insert_before', $data, $args);
     *      if($result === false){
     *          return false;
     *      }
     * }
     */

    /**
     * data binding and action queuing
     * @var array({order}=>array($callback, $eval_return))
     */
    private $bound_callbacks;   // queued action callbacks (definition in class extension through definition method)

    /* data-binding/function-binding (permanent, binding evaluated at instance creation) */

    /**
     * Used for data binding in __construct() method. The currently available data is passed to the callback function and the return is saved back.
     * To abort the parent function, set $eval_return to true and return false in the bound method
     * @param string $context string context where the queued functions are executed
     * @param string $callback name of the callback function (extended class method) as string
     * @param bool $eval_return if set to true, the parent function returns false if return is false
     * @param int $order relative order the function is executed
     * @throws \Exception if callback does not exist
     */

    protected function bind_action($context, $callback, $eval_return = false, $order = 0){
        if(
            (is_array($callback) && is_callable (array( $callback[0] ,  $callback[1] ))) ||
             is_callable($callback)
        ){
            if($order == 0){
                $this->bound_callbacks[$context][] = array($callback, $eval_return);
            }else{
                while(!empty($this->bound_callbacks[$context][$order])){
                    $order++;
                }
                $this->bound_callbacks[$context][$order] = array($callback, $eval_return);
            }
        }else{
            if(is_array($callback)){
                throw new \Exception("Bound action '$callback[0]' does not exist in '".get_class($callback[1])."'.");
            }else{
                throw new \Exception("Bound action '$callback' does not exist.");
            }
        }
    }

    /**
     * @param string $context Context the bound actions to execute
     * @param mixed $data Contextual argument of the parent function
     * @param mixed $args
     * @return bool Returns false to force parent function to quit
     */
    protected function execute_bound_actions($context, &$data, $args = null){

        if(!empty($this->bound_callbacks[$context])){
            krsort($this->bound_callbacks[$context]);

            foreach($this->bound_callbacks[$context] as $order=>$binding){

                if(is_array($binding[0])){
                    // class method. First argument is the class reference
                    $result = $binding[0][0]->$binding[0][1]($data, $args);
                }else{
                    $result = $this->$binding[0]($data, $args);
                }

                if($result === false && $binding[1] === true){
                    //$this->add_emsg($context, 'The bound function "'.$binding[0].'" returned false.');
                    // force parent function to return false
                    return false;
                }elseif($result !== null){
                    // if the function returns something - save to the input
                    $data = $result;
                }
            }
        }

        // stay in parent function
        return true;

    }

    // stores error messages when a member method returns false
    private $errors;

    /* error handling */
    public function add_emsg($context, $msg){
        $this->errors[$context][] = $msg;
    }
    public function reset_emsg($context = array()){
        if(empty($context)){
            $this->errors = array();
        }else{
            foreach($context as $c){
                if(!empty($this->errors[$c])){
                    $this->errors[$c] = array();
                }
            }
        }
    }
    public function get_emsg($context = ''){
        if($context == ''){
            $e = $this->errors;
        }else{
            $e = (empty($this->errors[$context])?array():$this->errors[$context]);
        }

        //$this->reset_error_msgs($context);
        return $e;
    }

}

abstract class DBObjectInterface extends Utils{

    private $properties;    // holds the db values of the object
    public $table;          // db setup of type DBTable, inherit public access

    /**
     * @return DBTable Extended Database Table object instance
     */
    abstract protected function define_db_table();

    protected function define_data_binding(){
        // placeholder function
        // use bind_action here to define the data binding
    }

    public function __construct()
    {
        // create corresponding db table instance
        $this->table = $this->define_db_table();

        if(!is_subclass_of($this->table, 'wpdbc\DBTable')){
            throw new \Exception('Illegal class extension. DBObjectInterface method "define_db_table" must return an object of type "DBTable"');
        }

        // define the data binding
        $this->define_data_binding();

        // todo: initiation from values

    }

    /**
     * Generates a SQL query string to find a unique entry. Does not escape, validate or sanitize data
     * @param array $data associative array with key names equal column names
     * @return array array[0]: SQL WHERE string, array[1]: values
     */
    public function sql_unique_where(array $data){

        $output = array();
        $unique_data = array();
        $pair_data = array();

        // sql data
        $sql_where = array();
        $values = array();
        $sql_string = '';

        // first check for primary key
        $pk = $this->table->get_db_primary_key();
        if($data[$pk]){
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

    // checks if data is loaded
    public function loaded(){
        // todo: load from init - check if one of the primary keys is loaded
        if(empty($this->properties)){
            throw new \Exception('Object is not loaded.');
        }
    }

    public function is_loaded(){
        return !empty($this->properties);
    }

    /**
     * Check if entry exists. Either by single unique key+value or by unque key-pair
     * @param array $data the entry, if it exists
     * @return bool false, if no matching entry exists
     * @throws \Exception if multiple entries with unique values exist
     */
    public function exists(array $data){

        // sanitation
        $data = $this->table->validator->sanitize($data, 'get');

        // validation
        $result = $this->table->validator->validate('get', $data);
        if($result === false){
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

        if(empty($results)){
            return false;
        }elseif(count($results) == 1){
            return $results[0];
        }else{
            throw new \Exception('Database has multiple unique entries.');
        }
    }

    /*
     * extracts a unique primary key/keypair data set from $data
     */
    public function extract_unique_identifier_values($data, $pairs = false){

        $output = array();

        if($pairs == false && $this->table->get_unique_keys()){
            return array_intersect_key($data, array_flip($this->table->get_unique_keys()));
        }elseif($pairs == true && $this->table->get_unique_key_pairs()){
            foreach($this->table->get_unique_key_pairs() as $pair){
                $inters = array_intersect_key($data, array_flip($pair));
                if(count($pair) == count($inters)){
                    $output[] =  $inters;
                }
            }
        }

        return $output;
    }

    /**
     * Load all information into the object. Only possible for unique key/key-pair information.
     * @param array $fields
     * @param array $return_keys
     * @return bool
     * @throws \Exception if input key(s) are not a unique identifier
     */
    public function load(array $fields, array $return_keys = array()){

        $obj = $this->exists($fields);

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
     * @param array $keys
     * @return bool|object
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
     * @param array $data   data format: array($key => $value)
     * @param null $where   where format: array($key => $value)
     * @return bool         1: success, 0: nothing updated, false: fail/entry does not exist
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
                return false;
            }
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
                $pk_val = $obj[$pk];
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
                return false;
            }
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
     * @param array $data data with format: array('col1'=>'col1val', 'col2'=>'col2val')
     * @param bool $force_reload whether to update the object representation with the inserted values
     * @return bool 1 on success, false if entry could not be inserted
     * @throws \Exception if invalid input values are given
     */
    public function insert(array $data, $force_reload = true){

        // sanitation
        $data = $this->table->validator->sanitize($data, 'insert');

        // validation
        $result = $this->table->validator->validate('insert', $data);
        if($result === false){
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

}


abstract class DBObjectsHandler extends Utils{

    private $and;       // the common search property
    private $or;        // the common search property
    private $objects;   // the loaded objects

    public $table;      // db setup of type DBTable, inherit public access

    public function __construct()
    {

        if(!is_subclass_of($this->define_db_table(), '\wpdc\DBTable')){
            throw new \Exception('Illegal class extension. DBObjectInterface method "define_db_table" must return an object of type "DBTable"');
        }

        $this->table = $this->define_db_table();

    }

    // set the db table
    abstract protected function define_db_table();
    abstract protected function define_order();

    // checks if data is loaded
    public function loaded(){
        if(empty($this->objects)){
            throw new \Exception('No objects are loaded.');
        }
    }

    /**
     * @param $fields array('fieldname'=>array(), 'field2' => 'val2') - array: or
     * @return bool
     */
    private function prepare_sql_where($fields){

        ksort($fields);

        // load from additional information
        $keys_and = array();
        $keys_or = array();

        $or_fields = $this->extract_or_fields($fields);

        // get overlapping db_key => db_format array
        $keys_and = array_intersect_key($this->table->get_db_format(), $fields);
        $keys_or = array_intersect_key($this->table->get_db_format(), $or_fields);

        // do not execute query if no arguments given
        if(empty($keys_and) && empty($keys_or)){
            return false;
        }

        // validate 'or' fields
        foreach($or_fields as $col=>$group){
            foreach($group as $val){
                if($this->table->validate(array($col=>$val), 'load') === false){
                    return false;
                }
            }
        }

        // validate normal fields
        if($this->table->validate($fields, 'load') === false){
            return false;
        }

        // sort by keys
        ksort($keys_and);
        ksort($keys_or);

        // form sql by merging key and formats
        $sql_where = urldecode(http_build_query($keys_and,'',' AND '));

        $sql_or = '';

        foreach($keys_or as $key => $format){

            $nr_conditions = count($or_fields[$key]);

            if($nr_conditions > 0){
                $sql_or = $key.' = '.$format;
                $sql_or = $sql_or.str_repeat(' OR '.$sql_or, $nr_conditions-1);

                // append to main sql
                if($sql_where != ''){
                    $sql_where .= ' AND ';
                }
                $sql_where .= '('.$sql_or.')';
            }

        }

        // order
        // get available values (compare input keys to reference)
        $and_values = array_intersect_key($fields, $this->table->get_db_format());
        $or_values = array_intersect_key($or_fields, $this->table->get_db_format());

        ksort($and_values);
        ksort($or_values);

        // flatten them
        $and_values = array_values($and_values);
        if(!empty($or_values)){
            $or_values = call_user_func_array('array_merge', $or_values);
        }

        return array('sql' =>$sql_where, 'values'=>array_merge($and_values, $or_values));

    }

    public function load(array $fields){

        if(!$where = $this->prepare_sql_where($fields)){
            return false;
        }

        // todo: remove duplicate code
        ksort($fields);

        // load from additional information
        $keys_and = array();
        $keys_or = array();

        $or_fields = $this->extract_or_fields($fields);

        // get overlapping db_key => db_format array
        $keys_and = array_intersect_key($this->table->get_db_format(), $fields);
        $keys_or = array_intersect_key($this->table->get_db_format(), $or_fields);

        // perform query
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM ".$this->table->get_db_table_name()." WHERE ".$where['sql'], $where['values']);

        $result = $wpdb->get_results($sql);

        if($result !== NULL){
            $this->and = $fields;
            $this->or = $or_fields;
            $this->objects = $result;
            return true;
        }

        return false;
    }

    public function load_all($group_by = ''){

        // perform query
        global $wpdb;

        $sql = "SELECT * FROM ".$this->table->get_db_table_name();

        if($group_by != ''){
            $cols = $this->table->get_db_format();
            if(!empty($cols[$group_by])){
                $sql .= ' GROUP BY '.$group_by;
            }
        }

        $result = $wpdb->get_results($sql);

        if($result !== NULL){
            $this->objects = $result;
            return true;
        }

        return false;
    }

    /**
     * @param $fields
     * @return bool
     */
    public function delete($fields){

        if(!$where = $this->prepare_sql_where($fields)){
            return false;
        }

        // perform query
        global $wpdb;
        $sql = $wpdb->prepare("DELETE FROM ".$this->table->get_db_table_name()." WHERE ".$where['sql'], $where['values']);

        $result = $wpdb->get_results($sql);

        if($result !== NULL){
            $this->objects = array();
            return true;
        }

        return false;

    }

    public function count_all(){

        //todo: finish
    /*
        SELECT
          category,
          COUNT(*) AS `num`
        FROM
          posts
        GROUP BY
          category

        */

    }

    public function count($group_by, $where = array()){

        $col_format = $this->table->get_db_format($group_by);
        if(empty($col_format)){
            return false;
        }

        // validate where
        if($this->table->validate(array_values($where), 'count') === false){
            return false;
        }

        // get overlapping format
        $format_where = array_intersect_key($this->table->get_db_format(), $where);

        global $wpdb;

        if(!empty($format_where)){

            // extract overlapping where data
            $where = array_intersect_key($where, $format_where);

            ksort($format_where);
            ksort($where);
            $sql_where = urldecode(http_build_query($format_where, '',' AND '));

            // values to escape
            $values = array_values($where);

            // group by column name (select and group by)
            array_unshift($values, $group_by);
            array_push($values, $group_by);

            $sql = $wpdb->prepare("SELECT ".$col_format.", COUNT(*) FROM ".$this->table->get_db_table_name()." WHERE ".$sql_where." GROUP BY ".$col_format, $values);
        }else{
            $sql = $wpdb->prepare("SELECT ".$col_format.", COUNT(*) FROM ".$this->table->get_db_table_name()." GROUP BY ".$col_format, array($group_by,$group_by));
        }

        $result = $wpdb->get_results($sql);

        if($result !== NULL){
            return $result;
        }

        return false;

    }

    /**
     * If objects are loaded: update selected group
     * Else: update all entries in db that match the criteria
     */
    public function multi_update($where, $update){

    }

    /** get the columns values for a key
     * @param $key
     * @return array
     */
    public function get_col($key){
        return array_map(function($o) use ($key){
            return $o->{$key};
        }, $this->objects);
    }

    public function get_objects($group_by = ''){

        if($group_by != ''){

            $col = array();
            // group by parent id

            foreach($this->objects as $obj){
                $col[$obj->{$group_by}][] = $obj;
            }
            return $col;

        }else{
            return $this->objects;
        }

    }

    public function edit($data){
        // todo: include additional where condition


    }

    /**
     * @param $fields
     * @return bool, removes subarrays from search conditions and returns them
     */
    public function extract_or_fields(&$fields){

        $or = array();
        foreach($fields as $i=>&$field){
            if(is_array($field)) {
                $or[$i] = $field;
                unset($fields[$i]);
            }
        }
        return $or;

    }

}

class Validator{

    protected $errors;   // collects validation errors temporarily

    /*
     * structure:
     * array(
     *  'name1' => 'rule1'
     *  'name2' => 'rule2'
     * )
     */
    protected $validation_rules;            // define the validation rules
    public static $validation_methods;      // todo: not yet implemented
    /*
     * structure:
     * array(
     *  'name1' => 'rule1'
     *  'name2' => 'rule2'
     * )
     */
    protected $sanitation_rules;            // define sanitation rules
    public static $sanitation_methods;      // todo: not yet implemented

    public function __construct(array $validation_rules = array(), array $sanitation_rules = array())
    {
        // copy validation rules
        foreach($validation_rules as $field_name => $rules){
            $this->validation_rules[$field_name] = explode('|', $rules);
        }

        // copy validation rules
        foreach($sanitation_rules as $field_name => $rules){
            $this->sanitation_rules[$field_name] = explode('|', $rules);
        }
    }

    public function get_errors(){
        return $this->errors;
    }

    public function sanitize(array $data, $context = null){

        if(empty($this->sanitation_rules)){
            return $data;
        }

        foreach($data as $field_name => $value){

            if(array_key_exists($field_name, $this->sanitation_rules)){
                foreach($this->sanitation_rules[$field_name] as $rule){

                    $method = null;
                    $sanitation_context = null;
                    $param = null;

                    // parse rule for contexts and parameters
                    if (strstr($rule, ',') !== false) {
                        $rule   = explode(',', $rule);
                        $rule   = $rule[0];
                        $param  = $rule[1];
                        if (strstr($rule, ':') !== false) {
                            $rule   = $rule[0];
                            $sanitation_context  = $rule[1];
                        }
                    }

                    $method = 'sanitize_'.$rule;
                    if($sanitation_context == null || in_array($context, explode(';', $sanitation_context))){
                        // predefined sanitation rules
                        if (is_callable(array($this, $method))) {
                            if(isset($data[$field_name])){
                                $this->$method($field_name, $context, $data, $param);
                            }
                        } else {
                            throw new \Exception("Validator sanitation method '$method' does not exist.");
                        }
                    }
                }
            }
        }

        return $data;
    }

    // white-list validation
    public function validate($context, array $data){

        // clear errors
        $this->errors = array();

        if(!$this->validation_rules){
            return true;
        }

        foreach($data as $field_name => $value){
            if(array_key_exists($field_name, $this->validation_rules)){
                foreach($this->validation_rules[$field_name] as $rule){

                    $valid = true;
                    $method = null;
                    $rule_context = null;
                    $param = null;

                    // parse parameters
                    if (strstr($rule, ',') !== false) {
                        $rule   = explode(',', $rule);
                        $rule   = $rule[0];
                        $param  = $rule[1];
                    }
                    // parse context
                    if (strstr($rule, ':') !== false) {
                        $rule   = explode(':', $rule);
                        $rule = $rule[0];
                        $rule_context  = $rule[1];
                    }

                    $method = 'validate_'.$rule;

                    // predefined rules - check if in correct context
                    if($rule_context == null || in_array($context, explode(';', $rule_context))){
                        if (is_callable(array($this, $method))) {
                            $valid = $this->$method($field_name, $context, $data, $param);
                        // inline rule definition
                        } elseif(isset(self::$validation_methods[$rule])) {
                            $valid = call_user_func(self::$validation_methods[$rule], $field_name, $context, $data, $param);
                        } else {
                            throw new \Exception("Validator method '$method' does not exist.");
                        }
                    }

                    // save validation error
                    if($valid === false){
                        $this->errors[] = array(
                            'field' => $field_name,
                            'context' => $context,
                            'value' => $value,
                            'rule' => $rule,
                            'param' => $param,
                        );
                    }

                }
            }
        }

        if(empty($this->errors)){
            return true;
        }

        return false;
    }

    /* sanitation functions */
    private function sanitize_exclude_keys($field, $context, &$data, $param = null){
        if($param != null && is_array($data[$field])){
            $keys_to_remove = array_flip(explode(';', $param));
            $data[$field] = array_diff_key($data[$field], $keys_to_remove);
        }
    }
    private function sanitize_exclude_values($field, $context, &$data, $param = null){
        if($param != null && is_array($data[$field])){
            $data[$field] = array_diff($data[$field], explode(';', $param));
        }
    }
    private function sanitize_exclude($field, $context, &$data, $param = null){
        unset($data[$field]);
    }
    private function sanitize_trim($field, $context, &$data, $param = null){
        $data[$field] = trim($data[$field]);
    }
    private function sanitize_lowercase($field, $context, &$data, $param = null){
        $data[$field] = strtolower($data[$field]);
    }
    private function sanitize_uppercase($field, $context, &$data, $param = null){
        $data[$field] = strtoupper($data[$field]);
    }

    /* validation functions */
    private function validate_ban($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        if($param != null){
            if(in_array($data[$field], explode(';', $param))){
                return false;
            }
        }
        return !isset($data[$field]);
    }
    private function validate_required($field, $context, $data, $param = null){
        return isset($data[$field]);
    }
    private function validate_numeric($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return is_numeric($data[$field]);
    }
    private function validate_float($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return is_float($data[$field]);
    }
    private function validate_integer($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return is_int($data[$field]);
    }
    private function validate_alpha_numeric($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return (preg_match("/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ\s])+$/i", $data[$field])?true:false);
    }
    private function validate_alpha_space($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return (preg_match('/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i', $data[$field])?true:false);
    }
    private function validate_min_len($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($data[$field]) >= (int) $param) {
                return true;
            }
        } else {
            if (strlen($data[$field]) >= (int) $param) {
                return true;
            }
        }
        return false;
    }
    private function validate_max_len($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($data[$field]) <= (int) $param) {
                return true;
            }
        } else {
            if (strlen($data[$field]) <= (int) $param) {
                return true;
            }
        }
        return false;
    }
    private function validate_boolean($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return (is_bool($data[$field]) || ($data[$field]==1 || $data[$field]==0));
    }
    private function validate_array($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return is_array($data[$field]);
    }
    private function validate_starts($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;

        foreach(explode(';', $param) as $start){
            if(strpos($data[$field], $start) == 0){
                return true;
            }
        }
        return false;
    }
    private function validate_ends($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        foreach(explode(';', $param) as $end){
            if(strlen($data[$field]) - strlen($end) == strrpos($data[$field],$end)){
                return true;
            }
        }
        return false;
    }
    private function validate_regex($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        return (preg_match($param, $data[$field])?true:false);
    }
    private function validate_contains($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return;
        if(in_array($data[$field], explode(';', $param))){
            return true;
        }
        return false;
    }
}


/**
 * Used to define table properties
 * SINGLETON - only one instance
 *
 * Class DBTable
 * @package wpdbc
 */
abstract class DBTable{

    protected $db_table_name;   // holds the db values of the object
    protected $db_primary_key;
    protected $db_format;      // defines the value format for $wpdb escaping

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

    public function __construct()
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
    }

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

    protected function define_validation_rules(){
        return array();
    }
    protected function define_sanitation_rules(){
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



?>