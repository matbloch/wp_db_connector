# Wordpress Database Connector
# License
Wordpress Database Connector may not be used without the authors permission.

# Requirements
- PHP 5.3

# Quickstart
- define table objects
- hook table installer
- define table abstraction classes
- ...profit

# Upcoming Features
- Extend Validator inline by static validation and sanitation methods
- Support of multiple unique key in table creation
- ~~Error messages for standard object handler routine crashes~~
- ~~DBTable as singleton~~

# Installation

```php
require_once wp_db_connector.php
```

In the following example, an abstraction for a table with name `test_table` is derived.

## 1. Table definition
The first step is to define a class that holds the table properties.


**Required Methods**
- `protected function define_db_table_name()`
- `protected function define_db_format()`
- `protected function define_db_primary_key()`


**Optional Methods**
- `protected function define_unique_keys()`
- `protected function define_unique_key_pairs()`
- `protected function define_validation_rules()`: see Section 1.1 Validation
- `protected function define_sanitation_rules()`: see Section 1.1 Validation

```php
/* define test table */
class TestTable extends DBTable{

    /* define required fields */
    protected function define_db_table_name(){
        return 'db_connector_test';
    }
    protected function define_db_format(){
        return array(
            'id' => '%d',
            'id_nummer' => '%d',
            'code' => '%s',
            'name' => '%s',
            'vorname' => '%s',
            'alter' => '%d'
        );
    }
    protected function define_db_primary_key(){
        return 'id';
    }

    /* unique keys or key-pairs */
    protected function define_unique_keys(){
        return array('id_nummer');
    }
    protected function define_unique_key_pairs(){
        return array(
            array('vorname', 'name'),
            array('code', 'id_nummer')
        );
    }

    /* validation and sanitation */
    protected function define_validation_rules(){
        return array(
            'id_nummer' => 'integer|required:insert',
            'code' => 'starts,#',
            'name' => 'required:insert',
            'vorname' => 'required:insert',
            'alter' => 'integer'
        );
    }
    protected function define_sanitation_rules(){
        return array(
            'code' => 'trim',
            'name' => 'trim',
            'vorname' => 'trim'
        );
    }

}
```



### 1.1 Validation

- *Escaping* done by $wpdb (string or numeric entry) and by the class **Validator** (trim etc.)
- *Validation* done by **Validator** class
- rules separated by `|`
- Don't use `|`, ` ` in parameters

**Examples**
`rule1:context1:context2|rule2:context2 param1 param2|rule3 param1`

**Available validation rules:**

`rule:context1;context2,param1;param2`

- `ban` ban a key or multiple values
- `required` check if key is set and if array: `!empty` or single value `!==""` (0 validates to true)
- `numeric`
- `integer`
- `float`
- `alpha_numeric`
- `alpha_space`(a-z, A-Z, 0-9, \s)
- `alpha_dash` (a-z, A-Z, 0-9, _-)
- `min_len,6`
- `max_len,100`
- `boolean` true, false, 0, 1
- `array`
- `url`
- `email`
- `name`
- `date`
- `starts,a;b;c`
- `ends,a;b;c`
- `contains,m;f` must contain one of the values

**Available filter rules:**
- `lowercase`
- `uppercase`
- `trim`
- `exclude`
- `exclude_keys`
- `exclude_values`

**Contexts:**
- `update_data` update data
- `update_where` unique {where} search terms
- `delete` unique {where} search terms
- `insert` new entry data to insert
- `get` unique {where} search terms to check if entry exists

**Usage in DB Interface**
Validator instance is bound to table instance
```php
$interface->table->validator->sanitize($context, $data);
$interface->table->validator->validate($context, $data);
```

## 2. Table abstraction

### 2.1 Interface Definition

**Required Methods**
- `protected function define_db_table()`

**Optional Methods**
- `protected function define_data_binding()`: See Section **2.2 Data Binding**

```php
/* define test item handler */
class TestItem extends DBObjectInterface{

	protected function define_db_table(){
		// instance of the table class
        return 'TestTable';
    }

    protected function define_data_binding(){
        // placeholder function
        // use bind_action here to define the data binding
        $this->bind_action('insert_before', array($this,'bound_insert'));
        $this->bind_action('delete_before', array($this,'bound_delete'));
    }

    protected function bound_insert($data, $where){

        $bound_item = new BoundTestItem();

        $result = $bound_item->exists($data);
        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';
        $result = $bound_item->insert(array('id_nummer'=>$data['id_nummer']));
        $result = $bound_item->exists($data);
        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';

    }
    protected function bound_delete($where){

        $bound_item = new BoundTestItem();

        $result = $bound_item->exists($where);
        echo '<strong>Bound testitem exits:</strong> '.($result?'YES':'NO').'<br>';
        $result = $bound_item->delete($where);
        $result = $bound_item->exists($where);
        echo '<strong>Bound testitem exits:</strong> '.($result?'YES':'NO').'<br>';

    }

}
```

### 2.2 Databinding
- Databinding allows to bind a user-defined function to a standard object method such as `delete` or `insert`.
- The `{hook_name}` parameter specifies the position and method when the function is executed.
- Setting **$eval_return** to `true` and returning `false` causes the object method to terminate with return `false` (allows to cancel the execution of standard methods on user-defined conditions)


####Add Function Trigger

`bind_action($context, $callback, $eval_return = false, $order = 0)`
> Used for data binding in __construct() method. The currently available data is passed to the callback function and the return is saved back.
> To abort the parent function, set $eval_return to true and return false in the bound method
> **@param** string $context string context where the queued functions are executed
> **@param** string $callback name of the callback function (extended class method) as string
> **@param** bool $eval_return (optional) if set to true, the parent function returns false if return is false
> **@param** int $order (optional) relative order the function is executed
> **@throws** \Exception if callback does not exist

- General method: `bind_action({hook_name}, 'function_name')`
- Object method: `bind_action({hook_name}, array($this, 'function_name'))`

```php
class File extends DBObjectInterface{
    protected function define_data_binding(){
        bind_action('insert_before', array($this,'echo_sth')
    }
    protected function echo_sth(){
    	echo 'I am called before an insertion.';
    }
}
```

####Available Databinding Hooks

The arguments passed to the bounded functions **are always sanitized and validated yet not filtered** to the available columns (allows to pass aditional parameters to the bounded functions).

**Hooked function**: `myfunction(array $data, $args = null)`

| Hook | description | arguments |
|-|-|-|
|`delete_before`|Before an item is deleted|**array** $where (optional)|
|`delete_after`|After an item is deleted|**array** $where (optional), **bool** $success|
|`insert_before`|Before an item is deleted|**array** $data|
|`insert_after`|Before an item is deleted|**array** $data, **bool** $success|
|`update_before`|Before an item is deleted|**array** $data, **array** $where|
|`update_after`|Before an item is deleted|**array** $where|

**Multi-Object Handler**

| Hook | description | arguments |
|-|-|-|
|`get_before`|Before items are loaded|**array** $where (optional)|
|`delete_before`|Before an item is deleted|**array** $where (optional)|
|`delete_after`|After an item is deleted|**array** $where (optional), **bool** $success|
|`insert_before`|Before an item is deleted|**array** $data|
|`insert_after`|Before an item is deleted|**array** $data, **bool** $success|
|`update_before`|Before an item is deleted|**array** $data, **array** $where|
|`update_after`|Before an item is deleted|**array** $where|

####Example: Changing Data with hooked functions


```php
function myFunction($data, $args = null){
	// do some modifications
	if($data['count'] > 30){
		$data['bulk'] = true;
	}
	// return the data array
	return $data;
}

```

### 2.3 Interface Usage


#### Main Functions

**Return Codes**
- success: `true`
- entry exists/does not exist: `0`
- validation error: `null`
- misc error: `false`

**Data format**
```php
array(
'col1' => 'value1',
'col2' => 'value2'
)
```

- - -


`insert`(array $data, bool $force_reload = true)
**Optional**: $force_reload if inserted data should be loaded into the object instance (`load`() applied after insert)
```php
$handler = new \wpdbc\TestItem();
if($r = !$handler->insert($data){
	if($r === 0){
		// entry exists already
	} else if($r == null){
		// validation error
		var_dump($r->get_error_messages())
	} else {
		// misc error
	}
}
```

- - -


`load`(array $search_terms)
**Description**: Loads ++specific++ data entry (no `or` terms)
```php
$handler = new \wpdbc\TestItem();
if($r = !$handler->load($data){
	if($r === 0){
		// entry does not exist
	} else if($r == null){
		// validation error (e.g. no unique search key)
		var_dump($r->get_error_messages())
	} else {
		// misc error
	}
}
```

- - -


`update`(array $data, array $search_terms = null)
**Description**: Updates ++specific++ data entry (no `or` terms)
**Optional**: $search_terms if update not applied to object instance
```php
$handler = new \wpdbc\TestItem();
if($r = !$handler->update($data){
	if($r === 0){
		// entry does not exist
	} else if($r == null){
		// validation error (e.g. no unique search key)
		var_dump($r->get_error_messages())
	} else {
		// misc error
	}
}
```

- - -


`delete`(array $search_terms = null)
**Description**: Deletes ++specific++ data entry (no `or` terms)
**Optional**: $search_terms if deletion not applied to object instance
```php
$handler = new \wpdbc\TestItem();
if($r = !$handler->update($data){
	if($r === 0){
		// entry does not exist
	} else if($r == null){
		// validation error (e.g. no unique search key)
		var_dump($r->get_error_messages())
	} else {
		// misc error
	}
}
```


#### Available methods (outdated)
- `debugging`(**$active**)
	> **@param** bool $active activate/deactivate debugging for object instance
- `loaded()`
	> **@throws** \Exception if the current object is not loaded
- `is_loaded()`
	> **@return** bool whether the object is loaded
- `exists`(array **$data**)
	> Check if entry exists. Either by single unique key+value or by unque key-pair
	> **@param** array $data unique identification data
	> **@return** mixed Row as **object**. bool **false**, if no matching entry exists
	> **@throws** \Exception if multiple entries with unique values exist
- `load`(array **$data**, array **$return_keys** = array())
	> Load all information into the object. Only possible for unique key/key-pair information.
	> **@param** array $data unique search data
	> **@param** array $return_keys column values to return on success
	> **@return** mixed bool if succeeded or array of data specified in $return_keys
	> **@throws** \Exception if input key(s) are not a unique identifier
- `get`(**$keys** = array())
	> Get column values from the loaded object interface. Requires a previous load().
	> **@param **array $keys array of columns names to return
	> **@return** bool|object single value, object with attributes equal to the extracted column names or false if the column names do not exist
	> **@throws** \Exception if object is not loaded
- `update`(array **$data**, **$where** = null)
	> Updates the currently loaded object or an object uniquely specified by $where
	> **@param** array $data update data with format: array($column_name => $value)
	> **@param** array $where (optional) unique identification with format: array($key => $value)
	> **@return** bool|integer 1: success, 0: nothing updated, false: fail/entry does not exist
	> **@throws** \Exception
- `delete`(**$where** = null)
	> Deletes the loaded object or an object uniquely specified by $where
	> **@param** array $where (optional) unique identification with format: array($key => $value)
	> **@return** bool|integer 1 on success, false on fail
	> **@throws** \Exception If $where is invalid or the object is not loaded
- `insert`(array **$data**, **$force_reload** = true)
	> Inserts a new table row
	> **@param** array $data data with format: array('col1'=>'col1val', 'col2'=>'col2val')
	> **@param** bool $force_reload (optional) whether to update the object representation with the inserted values
	> **@return** bool 1 on success, false if entry could not be inserted
	> **@throws** \Exception if invalid input values are given

Todo:
- get_error_msgs (validation and context reset after each main function, output format)
- add_emsg

## Multi-Object Handler


### 2.1 Interface Definition

**Required Methods**
- `protected function define_db_table()`
> @return string Class name of extended DBTable Database Table class

**Optional Methods**
- `protected function define_data_binding()`: See Section **2.2


```php

```

### Interface Methods

**Available methods**

- `debugging`(**$active**)
	> **@param** bool $active activate/deactivate debugging for object instance
- `loaded()`
	> **@throws** \Exception no objects have been loaded
- `is_loaded()`
	> **@return** bool whether some objects have been loaded (e.g. in case no object been found)
- `is_queried()`
	> **@return**  bool whether a search query has been performed or not (objects might be empty)
- `load`(array **$fields_and**, array **$fields_or**, array **$args**)

	> Load objects specified by search data
	> **@param** array $fields_and column values with 'AND' relation.
	> Format: array('col1'=>'searchval1', 'col2'=>array('col2_val1', 'col2_val1'))
	> **@param** array $fields_or column values with 'OR' relation.
	> Format: array('col1'=>'searchval1', 'col2'=>array('col2_val1', 'col2_val1'))
	> **@param** array $args (optional) additional query pagination parameters: limit, offset, group_by (column namae)
	> **@return** bool|int false if query failed or number of search results (including 0)
	> **@throws** \Exception if invalid search data supplied
- `load_all`(array **$args**)
	> Loads all table entries into the object
	> **@param** array $args (optional) additional query pagination parameters: limit, offset, group_by (column name)
	> **@return** bool|int false if query failed or number of search results (including 0)
- `delete`(array **$fields_and**, array **$fields_or**, array **$args**)



# Usage

## General Method Return Behavior

- Empty result: false
- Wrong input format: Exception