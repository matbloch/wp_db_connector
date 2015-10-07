<?php

abstract class Utils{

    /* data binding and action queuing */
    private $bound_callbacks;   // queued action callbacks (definition in class extension through definition method)

    // stores error messages when a member method returns false
    private $errors;

    /* databinding/functionbinding (permanent, binding evaluated at instance creation) */

    /**
     * Used for data binding. The currently available data is passed to the callback function and the return is saved back.
     * To abort the parent function, set $eval_return to true and return false/0 in the bound method
     * @param string $context string context where the queued functions are executed
     * @param string $callback name of the callback function (extended class method) as string
     * @param bool $eval_return if set to true, the parent function returns 0/false if return is 0/false
     * @param int $order relative order the function is executed
     */

    protected function bind_action($context, $callback, $eval_return = false, $order = 0){
        if(method_exists ( $this ,  $callback )){
            if($order == 0){
                $this->bound_callbacks[$context][] = array($callback, $eval_return);
            }else{
                while(!empty($this->bound_callbacks[$context][$order])){
                    $order++;
                }
                $this->bound_callbacks[$context][$order] = array($callback, $eval_return);
            }
        }
    }

    /**
     * @param string $context Context the bound actions to execute
     * @param mixed $input Contextual argument of the parent function
     * @return bool Returns false to force parent function to quit
     */
    protected function execute_bound_actions($context, &$input){
        if(!empty($this->bound_callbacks[$context])){
            krsort($this->bound_callbacks[$context]);
            foreach($this->bound_callbacks[$context] as $cb){
                $result = call_user_func( array($this,$cb), $input );

                // if the function returns something - save to the input
                if(!is_null($result)){
                    // force parent function to return false
                    if($result === false){
                        $this->add_emsg($context, 'The bound function "'.$cb.'" returned false.');
                        return false;
                    }else{
                        $input = $result;
                    }
                }
            }
        }

        // stay in parent function
        return true;

    }


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

    // holds the db values of the object
    private $properties;
    public $table; // db setup of type DBTable, inherit public access

    // set the db table
    abstract protected function define_db_table();

    public function define_data_binding(){
        // placeholder function
        // use bind_action here to define the data binding
    }

    public function __construct()
    {

        // todo: initiation from values

        if(!is_subclass_of($this->define_db_table(), '\wpdc\DBTable')){
            throw new \Exception('Illegal class extension. DBObjectInterface method "define_db_table" must return an object of type "DBTable"');
        }

        // define the corresponding table
        $this->table = $this->define_db_table();

        // define the data binding
        $this->define_data_binding();

        // reset errors messages
        $this->errors = new ErrorHandler();

        /*
        // parse field values
        $values = array_intersect_key($field_values, $this->table->get_db_format());

        // validate
        if(!$this->table->validate($values, 'construct')){
            throw new \Exception('Object has been initiated with illegal values.');
        }

        $this->properties = $values;
        */

    }

    // checks if data is loaded
    public function loaded(){
        if(empty($this->properties)){
            throw new \Exception('Object is not loaded.');
        }


        // todo: load from init - check if one of the primary keys is loaded
    }

    public function is_loaded(){
        return !empty($this->properties);
    }

    /**
     * @param array $fields
     * @param array $return_keys
     * @return bool
     */
    public function load(array $fields = array(), array $return_keys = array()){
        /*
         * success: true
         * fail: false
         */

        // load from additional information
        $keys_input = array();
        if(!empty($fields)){

            $fields = $this->table->validate($fields, 'load');
            if($fields === false){
                return false;
            }

            // get overlapping db_key => db_format array
            $keys_input = array_intersect_key($this->table->get_db_format(), $fields);

            // do not execute query if no arguments given
            if(empty($keys_input)){
                return false;
            }
        }

        // todo: complete merge with initiator data
        $keys_prop = array();
        if($this->get()){
            $keys_prop = array_intersect_key($this->table->get_db_format(), $this->get());
        }

        // get keys and sort them
        $keys = array_merge($keys_input, $keys_prop);
        ksort($keys);

        // form sql by merging key and formats
        $sql_where = urldecode(http_build_query($keys,'',' AND '));

        // get available values (compare input keys to reference)
        // sort and flatten them
        $values = array_intersect_key($fields, $this->table->get_db_format());
        ksort($values);
        $values = array_values($values);

        // perform query
        global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM ".$this->table->get_db_table_name()." WHERE ".$sql_where, $values);
        $result = $wpdb->get_row($sql, ARRAY_A, 0);

        if($result !== NULL){
            // TODO: convert properties to correct var type
            $this->properties = $result;

            if(!empty($return_keys)){
                $this->get($return_keys);
            }else{
                return true;
            }
        }

        return false;

    }

    /**
     * @param array $keys
     * @return bool|object
     */
    public function get($keys = array()){

        try {
            $this->loaded();
        } catch (\Exception $e) {
            // object can not be loaded return empty result
            return false;
        }

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
     * @param array $data
     * @return bool 1: success, 0: nothing updated, false: fail
     */
    public function update(array $data){

        $this->loaded();

        try{
            $this->execute_action_callbacks('update_before');
        }catch (\Exception $e){
            return false;
        }

        // validate input arguments
        $data = $this->table->validate($data, 'update');
        if($data === false){
            return false;
        }

        // unset readonly files
        $available_fields = array_diff_key($this->table->get_db_format(),array_flip($this->table->get_db_readonly()));
        if(empty($available_fields)){return false;}

        // get values
        $values = array_intersect_key($data, $available_fields);
        ksort($values);

        // do not execute query if no arguments given
        if(empty($values)){return false;}

        // get format
        $value_format = array_intersect_key($available_fields, $values);
        ksort($value_format);

        global $wpdb;

        $pk = $this->table->get_db_primary_key();
        $db_format = $this->table->get_db_format();

        $where_format = array($db_format[$pk]);
        $where_value = array($pk => $this->get($pk));

        // do update on this file
        $result = $wpdb->update(
            $this->table->get_db_table_name(),
            $values,    // data
            $where_value,  // where
            $value_format,
            $where_format   // pk format
        );

        if($result === 1){
            // update object properties
            $this->properties = array_merge($this->properties, $data);
        }

        try{
            $this->execute_action_callbacks('update_after');
        }catch (\Exception $e){
            return false;
        }

        return $result;

    }

    /**
     * @return mixed
     */
    public function delete(){

        // reset error messages
        $this->reset_emsg(array('delete', 'delete_before', 'delete_after'));

        // TODO: make independent from load
        $this->loaded();

        try{
            $this->execute_action_callbacks('delete_before');
        }catch (\Exception $e){
            return false;
        }

        global $wpdb;

        $pk = $this->table->get_db_primary_key();
        $pk_val = $this->get($pk);
        $pk_format = $this->table->get_db_format($pk);

        // delete entry
        $result = $wpdb->delete( $this->table->get_db_table_name(), array($pk=>$pk_val), array($pk_format) );

        // unset properties to ensure no further manipulations
        $this->properties = array();

        try{
            $this->execute_action_callbacks('delete_after');
        }catch (\Exception $e){
            return false;
        }

        return $result;

    }

    /**
     * @param array $data Data format: array of 'col_name' => 'col_value' tuples
     * @return mixed False: Entry could not be inserted, 1: Successfully inserted
     */
    public function insert(array $data){

        // reset error messages
        $this->reset_emsg(array('insert', 'insert_before', 'insert_after'));

        // validate input arguments
        $data = $this->table->validate($data, 'insert');
        if($data === false){
            $this->add_emsg('insert', 'The input data valitaion failed.');
            return false;
        }

        // get values
        $values = array_intersect_key($data, $this->table->get_db_format());
        $value_format = array_intersect_key($this->table->get_db_format(), $values);
        ksort($value_format);
        ksort($values);

        // === data binding
        $exec = $this->execute_bound_actions('insert_before', $values);
        if($exec === false){return false;};

        global $wpdb;

        // check if entries with same unique key (apart from the primary keys) values exist
        $unique = $this->table->get_unique_keys();
        if(!empty($unique)){

            $unique_values = array_intersect_key($values, array_flip($unique));
            $unique_values_format = array_intersect_key($value_format, array_flip($unique));

            // search for equal entries
            $unique_values = array_values($unique_values);

            // form sql by merging key and formats
            $sql_where = urldecode(http_build_query($unique_values_format,'',' OR '));

            $sql = $wpdb->prepare( 'SELECT * FROM '.$this->table->get_db_table_name().' WHERE '.$sql_where, $unique_values);
            $res = $wpdb->get_results( $sql );

            // return if results with same unique key values were found
            if(!empty($res)){
                $this->add_error_msg('insert', 'An entry with the same unique keys already exists.');
                return false;
            }
        }

        $result = $wpdb->insert(
            $this->table->get_db_table_name(),
            $values,
            $value_format
        );

        if($result == 1){
            // load inserted data - todo: dont perform another SQL query
            $this->load(array($this->table->get_db_primary_key() => $wpdb->insert_id));
        }

        // === data binding
        $exec = $this->execute_bound_actions('insert_after', $result);
        if($exec === false){return false;};

        return $result;

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


/**
 * Used to define table properties
 *
 * Class DBTable
 * @package wpdc
 */
abstract class DBTable{

    protected $db_table_name;            // holds the db values of the object
    protected $db_primary_key;
    protected $db_format;      // defines the value format
    protected $db_readonly;    // defines the read-only fields

    // input validation
    protected $validation_rules;            // define the validation rules for all fields (no required fields)
    protected $required_fields;
    protected $unique_keys;   // todo: implement unique key pairs

    // force definition of object properties
    abstract protected function define_db_table_name();
    abstract protected function define_db_primary_key();
    abstract protected function define_db_format();
    abstract protected function define_db_readonly();

    // input validation
    abstract protected function define_validation_rules();

    /**
     * @return array, format: array('{context}'=>array({fieldnames}))
     */
    protected function define_required_fields(){
        return array();
    }

    /** Unique field values: crucial for insert/update
     * @return array
     */
    protected function define_unique_keys(){
        return array();
    }

    public function __construct()
    {
        // load settings from forced parent setters into properties
        $this->load_settings();
    }

    // getters
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
            return '';
        }

        return $this->db_format;
    }
    public function get_db_readonly(){
        return $this->db_readonly;
    }
    public function get_unique_keys(){
        return $this->unique_keys;
    }
    public function get_validation_rules(){
        return $this->validation_rules;
    }

    // save return value of forced settings functions to object
    protected function load_settings(){

        // load the settings into the object
        $this->db_primary_key = $this->define_db_primary_key();
        $this->db_table_name = $this->define_db_table_name();
        $this->db_format = $this->define_db_format();
        $this->db_readonly = $this->define_db_readonly();

        // validation
        $this->validation_rules = $this->define_validation_rules();
        $this->required_fields = $this->define_required_fields();
        $this->unique_keys = $this->define_unique_keys();
    }

    public function validate(array $fields, $context = 'std', $disp_error = false){

        // TODO: context and manual validation (db table defined)

        // check required fields
        if(!empty($this->required_fields[$context])){
            // get in this context required fields
            $req_fields = array_filter(array_intersect_key($fields, array_flip($this->required_fields[$context])));
            if(empty($req_fields)){
                return false;
            }
        }

        // check value format
        $gump = new \GUMP();
        $gump->validation_rules($this->get_validation_rules());

        $fields = $gump->sanitize($fields);
        $validated = $gump->run($fields);

        if($validated === false) {
            if($disp_error){echo $gump->get_readable_errors(true);}
            return false;
        }

        return $validated;

    }

}

?>