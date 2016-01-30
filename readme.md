# Wordpress Database Connector

# Quickstart
- define table objects
- hook table installer
- definie table abstraction classes
- have fun

# Upcomming Features
- Generate static DBTable class from constructor
- Extend Validator inline by static validation and sanitation methods
- DBTable as singleton

# Installation

```php
include_once wp_db_connector.php
```

In the following example, an abstraction for a table with name `test_table` is derived.

## 1. Table definition
The first step is to define a class that holds the table properties.

```php

class TestTable extends DBTable{

    protected function define_db_table_name(){
        return 'test_table';
    }
    protected function define_db_format(){
        return array(
            'id' => '%d',
            'name' => '%s'
        );
    }
    protected function define_db_readonly(){
        return array(
            'id'
        );
    }
    protected function define_db_primary_key(){
        return 'id';
    }
    protected function define_validation_rules(){
        return array(
            'id' => 'integer',
            'test_table' => 'exact_len,1|contains,1 0'
        );
    }

}
```

## 2. Table creation

## 3. Table abstraction

## 4. Validator

- *Escaping* done by $wpdb (string or numeric entry) and by **Validator** (trim etc.)
- *Validation* done by **Validator**
- rules separated by `|`



**Available validation rules:**

`rule:context1;context2,param1;param2`

- `ban` ban a key or multiple values
- `required` required key
- `numeric`
- `int`
- `float`
- `alpha_numeric`
- `alpha_space`(a-z, A-Z, 0-9, \s)
- `alpha_dash` (a-z, A-Z, 0-9, _-)
- `min_len,6`
- `max_len,100`
- `boolean` true, false, 0, 1
- `array`
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


**Usage in DB Interface**
Validator instance is bound to table instance
```php
$interface->table->validator->sanitize($context, $data);
$interface->table->validator->validate($context, $data);
```

## 5. DBObjectInterface

Represents a single unique entry in the database table.

**Representation specific methods**
- `load($fields, $return_keys)`
	Loads the whole representation stored in the db into the object. Loading is only possible from a nique key(-pair)
- `get($keys)`
	Only available if object is loaded.

**Direct methods**



### Databinding

```php
class File extends DBObjectInterface{
    protected function define_data_binding(){
        bind_action('insert_before', 'echo_sth')
    }
    protected function echo_sth(){
    	echo 'I am called before an insertion.';
    }
}
```


# Usage

## General Method Return Behavior

- Empty result: false
- Wrong input format: Exception