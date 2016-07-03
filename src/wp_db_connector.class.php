<?php

/**
 * @author      Matthias Bloch (https://github.com/matbloch)
 * @copyright   Copyright (c) 2016 - All Rights Reserved
 * @version     1.0
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */

namespace wpdbc;  // db connector

/**
 * Class Utils
 * @package wpdbc
 * Used in DB Object Interfaces
 */
if (!class_exists('\wpdbc\Utils')):
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
     * placeholder: implemented in object handlers
     */
    protected $table;
    /**
     * @var $debug bool if debugging is active or not
     */
    protected $debug;

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
     * @param bool $eval_return (optional) if set to true, the parent function returns false if return is false
     * @param int $order (optional) relative order the function is executed
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

    /**
     * @var $errors array stores error messages when a member method returns false
     */
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

    /**
     * @param bool $active activate/deactivate debugging for object instance
     */
    public function debugging($active){
        $this->debug = ($active?true:false);
    }
    protected function debug($type, $data = array()){

        $debug = debug_backtrace();

        echo '<div style="opacity:0.3; margin: 10px 0;">';
        ?>

        <table cellpadding="4" border="1" style="border-spacing: 1px; border-collapse: separate;">
            <tr>
                <td>
                    Context: <strong style="color: red"><?php echo $debug[1]['function']; ?></strong>
                </td>
                <td>
                    Class: <strong><?php echo get_class($this); ?></strong>
                </td>
            </tr>

        <?php
        if($type == 'query'){
            global $wpdb;
            ?>
            <tr>
                <td>
                    Query
                </td>
                <td>
                    <?php print_r($wpdb->last_query); ?>
                </td>
            </tr>
            <tr>
                <td>
                    Result
                </td>
                <td>
                    <?php
                    if(isset($data['result'])){
                        print_r($data['result']);
                    }else{
                        print_r($wpdb->last_result);
                    }
                    ?>
                </td>
            </tr>
            <?php if($wpdb->last_error){ ?>
                <tr>
                    <td>
                        Errors
                    </td>
                    <td>
                        <?php print_r($wpdb->last_error); ?>
                    </td>
                </tr>
            <?php }

        }elseif($type == 'validation'){
            ?>
            <tr>
                <td>
                    Validation Error
                </td>
                <td>
                    <?php
                        print_r($this->table->validator->get_errors());
                    ?>
                </td>
            </tr>
            <?php
        }
        echo '</table>';
        echo '</div>';

    }

}
endif;  // include guard

/**
 * Class DBObjectInterface
 * @package wpdbc
 * Used as object handler for single entries
 */
if (!class_exists('\wpdbc\DBObjectInterface')):
abstract class DBObjectInterface extends Utils{

    private $properties;    // holds the db values of the object
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

    public function __construct()
    {

        // get table (extended singleton) instance
        $this->table = call_user_func(array('wpdbc\\'.$this->define_db_table(), 'getInstance'));

        if(!is_subclass_of($this->table, 'wpdbc\DBTable')){
            throw new \Exception('Illegal class extension. DBObjectInterface method "define_db_table" must return an object of type "DBTable"');
        }

        // define the data binding
        $this->define_data_binding();

        // todo: initiation from values
    }

    public function __destruct()
    {
        $this->table = null;
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
     * Check if entry exists. Either by single unique key+value or by unque key-pair
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

}
endif;  // include guard

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

/**
 * Class Validator
 * @package wpdbc
 * Data validation and sanitation
 */
if (!class_exists('\wpdbc\Validator')):
class Validator{

    // todo: move to object instance and build singleton
    protected $errors;   // collects validation errors temporarily

    /*
     * structure:
     * array(
     *  'name1' => 'rule1'
     *  'name2' => 'rule2'
     * )
     */
    protected $validation_rules;            // define the validation rules
    /*
     * structure:
     * array(
     *  'colname1' => array('rule1'=>'error msg 1', 'rule2'=>'error msg 2'),
     *  'colname1' => array('rule1'=>'error msg 1', 'rule2'=>'error msg 2')
     * )
     */
    protected $validation_error_msgs;       // user defined validation error messages
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

    public function __construct(array $validation_rules = array(), array $sanitation_rules = array(), array $validation_error_msgs = array())
    {
        // copy validation rules
        foreach($validation_rules as $field_name => $rules){
            $this->validation_rules[$field_name] = explode('|', $rules);
        }

        // copy validation rules
        foreach($sanitation_rules as $field_name => $rules){
            $this->sanitation_rules[$field_name] = explode('|', $rules);
        }

        // copy validation error feedback messages
        if(!empty($validation_error_msgs)){
            $this->validation_error_msgs = $validation_error_msgs;
        }
    }

    public function get_errors(){
        return $this->errors;
    }


    protected function add_error($field_name, $context, $value, $rule, $param){
        $this->errors[] = array(
            'field' => $field_name,
            'context' => $context,
            'value' => $value,
            'rule' => $rule,
            'param' => $param,
        );
    }

    public function sanitize(array $data, $context = null){

        if(empty($this->sanitation_rules)){
            return $data;
        }

        foreach($data as $field_name => $value){
            // do sanitation if a rule is defined for the column name
            if(array_key_exists($field_name, $this->sanitation_rules)){
                // check all rules for this field
                foreach($this->sanitation_rules[$field_name] as $rule_str){

                    $method = null;
                    $param_str = null;
                    $context_arr = null;
                    $rule = null;
                    $rule_parts = array();

                    // TODO: replace rule param delimiter to allow "|" and " " in options

                    // extract parameters
                    $rule_parts = explode(' ',$rule_str, 2);
                    if($rule_parts){
                        // parameters present
                        $param_str = $rule_parts[1];
                        $rule_str = $rule_parts[0];
                    }

                    $rule_parts = explode(':', $rule_str);
                    // contexts present
                    if($rule_parts){
                        $rule = array_shift($rule_parts);   // remove first element = rule
                        $context_arr = $rule_parts;
                    }else{
                        $rule = $rule_str;
                    }

                    $method = 'sanitize_'.$rule;

                    // perform sanitation if its in the right context
                    if($context_arr == null || in_array($context, $context_arr)){
                        // predefined sanitation rules
                        if (is_callable(array($this, $method))) {
                            // sanitize
                            if(is_array($value)){
                                // multiple values at once
                                foreach($value as $k=>$single_val){
                                    $this->$method($k, $context, $data[$field_name], $param_str);
                                }
                            }else{
                                $this->$method($field_name, $context, $data, $param_str);
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
                foreach($this->validation_rules[$field_name] as $rule_str){

                    $valid = true;
                    $method = null;
                    $param_str = null;
                    $context_arr = null;
                    $rule = null;
                    $rule_parts = array();

                    // TODO: replace rule param delimiter to allow "|" and " " in options

                    // extract parameters
                    $rule_parts = explode(' ',$rule_str, 2);
                    if($rule_parts){
                        // parameters present
                        $param_str = $rule_parts[1];
                        $rule_str = $rule_parts[0];
                    }

                    $rule_parts = explode(':', $rule_str);
                    // contexts present
                    if($rule_parts){
                        $rule = array_shift($rule_parts);   // remove first element = rule
                        $context_arr = $rule_parts;
                    }else{
                        $rule = $rule_str;
                    }

                    $method = 'validate_'.$rule;

                    // predefined rules - check if in correct context
                    if($context_arr == null || in_array($context, $context_arr)){
                        if (is_callable(array($this, $method))) {

                            // sanitize
                            if(is_array($value)){
                                // multiple values at once
                                foreach($value as $k=>$single_val){
                                    $valid = $this->$method($k, $context, $data[$field_name], $param_str);
                                    if(!$valid){
                                        $this->add_error($field_name.'['.$k.']', $context, $data[$field_name], $rule, $param_str);
                                    }
                                }
                            }else{

                                $valid = $this->$method($field_name, $context, $data, $param_str);

                                if(!$valid){
                                    $this->add_error($field_name, $context, $value, $rule, $param_str);
                                }
                            }
                        // user defined rules
                        } elseif(isset(self::$validation_methods[$rule])) {
                            $valid = call_user_func(self::$validation_methods[$rule], $field_name, $context, $data, $param_str);
                            if(!$valid){
                                $this->add_error($field_name, $context, $value, $rule, $param_str);
                            }
                        } else {
                            throw new \Exception("Validator method '$method' does not exist.");
                        }
                    }
                }
            }

            // TODO: user defined validation rules
        }

        if(empty($this->errors)){
            return true;
        }

        return false;
    }

    /* sanitation functions */
    private function sanitize_exclude_keys($field, $context, &$data, $param = null){
        if($param != null && is_array($data[$field])){
            $keys_to_remove = array_flip(explode(' ', $param));
            $data[$field] = array_diff_key($data[$field], $keys_to_remove);
        }
    }
    private function sanitize_exclude_values($field, $context, &$data, $param = null){
        if($param != null && is_array($data[$field])){
            $data[$field] = array_diff($data[$field], explode(' ', $param));
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
            return true;
        if($param != null){
            if(in_array($data[$field], explode(' ', $param))){
                return false;
            }
        }
        return !isset($data[$field]);
    }
    private function validate_required($field, $context, $data, $param = null){
        if(!isset($data[$field])){
            return false;
        }
        if(is_array($data[$field])){
            return !empty($data[$field]);
        }else{
            return ($data[$field] !== "");
        }
    }
    private function validate_numeric($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return is_numeric($data[$field]);
    }
    private function validate_float($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return filter_var($data[$field], FILTER_VALIDATE_FLOAT);
    }
    private function validate_integer($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return preg_match('/^\d+$/',$data[$field]);
    }
    private function validate_alpha_numeric($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return (preg_match("/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ\s])+$/i", $data[$field])?true:false);
    }
    private function validate_alpha_space($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return (preg_match('/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i', $data[$field])?true:false);
    }
    private function validate_min_len($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
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
            return true;
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
            return true;
        return (is_bool($data[$field]) || ($data[$field]==1 || $data[$field]==0));
    }
    private function validate_array($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return is_array($data[$field]);
    }
    private function validate_url($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return filter_var($data[$field], FILTER_VALIDATE_URL);
    }
    private function validate_email($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return filter_var($data[$field], FILTER_VALIDATE_EMAIL);
    }
    private function validate_name($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return preg_match("/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïñðòóôõöùúûüýÿ '-])+$/i", $data[$field]);
    }
    private function validate_date($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        $timestamp = strtotime($data[$field]);
        return $timestamp ? true : false;
    }
    private function validate_starts($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        foreach(explode(' ', $param) as $start){
            if(strpos($data[$field], $start) === 0){
                return true;
            }
        }
        return false;
    }
    private function validate_ends($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        foreach(explode(' ', $param) as $end){
            if(strlen($data[$field]) - strlen($end) == strrpos($data[$field],$end)){
                return true;
            }
        }
        return false;
    }
    private function validate_regex($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        return (preg_match($param, $data[$field])?true:false);
    }
    private function validate_contains($field, $context, $data, $param = null){
        if(!isset($data[$field]))
            return true;
        if(in_array($data[$field], explode(' ', $param))){
            return true;
        }
        return false;
    }

    private function get_std_validation_error_msgs(){
        $messages = array(
            'required' => 'Dieses Feld ist ein Pflichtfeld.'
        );
        return $messages;
    }
}
endif;  // include guard

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
    }

    protected $db_table_name;   // holds the db values of the object
    protected $db_primary_key;
    protected $db_format;      // defines the value format for $wpdb escaping

    // todo: maybe move to objectHandler?
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

?>
