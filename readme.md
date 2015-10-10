# Wordpress Database Connector

# Quickstart
- define table objects
- hook table installer
- definie table abstraction classes
- have fun

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

# Usage

