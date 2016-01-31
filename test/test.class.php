<?php

namespace wpdbc;

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

/* define test item handler */
class TestItem extends DBObjectInterface{
	
	protected function define_db_table(){
        return new TestTable();
    }

    protected function define_data_binding(){
        // placeholder function
        // use bind_action here to define the data binding
        $this->bind_action('insert_before', array($this,'bound_insert'));
        $this->bind_action('delete_before', array($this,'bound_delete'));
    }

    public function bound_insert($data, $where){

        echo '----------------------------<br>bound action: create bounded item<br>----------------------------<br>';

        $bound_item = new BoundTestItem();

        $result = $bound_item->exists($data);
        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';


        $result = $bound_item->insert(array('id_nummer'=>$data['id_nummer']));

        $result = $bound_item->exists($data);
        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';

        echo '----------------------------<br>';
    }
    public function bound_delete($where){

        echo '----------------------------<br>bound action: delete bounded item<br>----------------------------<br>';

        $bound_item = new BoundTestItem();

        $result = $bound_item->exists($where);
        echo '<strong>Bound testitem exits:</strong> '.($result?'YES':'NO').'<br>';

        $result = $bound_item->delete($where);

        $result = $bound_item->exists($where);
        echo '<strong>Bound testitem exits:</strong> '.($result?'YES':'NO').'<br>';

        echo '----------------------------<br>';

    }

}

class BoundTestTable extends DBTable{

    /* define required fields */
    protected function define_db_table_name(){
        return 'db_connector_test_bound';
    }
    protected function define_db_format(){
        return array(
            'id' => '%d',
            'id_nummer' => '%d'
        );
    }
    protected function define_db_primary_key(){
        return 'id';
    }

    /* unique keys or key-pairs */
    protected function define_unique_keys(){
        return array('id_nummer');
    }

    /* validation and sanitation */
    protected function define_validation_rules(){
        return array(
            'id_nummer' => 'integer|required:insert'
        );
    }

}


class BoundTestItem extends DBObjectInterface{

    protected function define_db_table(){
        return new BoundTestTable();
    }

}


class TableInstaller{

    // install db tables
    // remember, use: register_activation_hook

    public function __construct(){

        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        //create the name of the table including the wordpress prefix (wp_ etc)
        $search_table = 'db_connector_test';

        //check if there are any tables of that name already
        if($wpdb->get_var("show tables like '".$search_table."'") !== $search_table)
        {
            $sql =  "CREATE TABLE ". $search_table . " (
					 `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					 `id_nummer` int(11) UNSIGNED COMMENT 'unique key',
					 `alter` int(11) UNSIGNED COMMENT '',
					 `code` varchar(255) COLLATE utf8mb4_unicode_ci COMMENT '',
					 `name` varchar(255) COLLATE utf8mb4_unicode_ci COMMENT '',
					 `vorname` varchar(255) COLLATE utf8mb4_unicode_ci COMMENT '',
					 PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table Connector test table';";
            dbDelta($sql);
        }

        $search_table = 'db_connector_test_bound';

        //check if there are any tables of that name already
        if($wpdb->get_var("show tables like '".$search_table."'") !== $search_table)
        {
            $sql =  "CREATE TABLE ". $search_table . " (
					 `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					 `id_nummer` int(11) UNSIGNED COMMENT 'unique key',
					 PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table Connector test table';";
            dbDelta($sql);
        }

    }
    public function clear($table_classes){

    }
    public function uninstall($table_classes){


    }

}

/* testing methods */
class WPDBCTest{
	
	public function __construct(){
        $table = new TableInstaller();
		$this->add_dummy_data();
	}

	public function add_dummy_data(){
		
		$item = new TestItem();
		// insert item
		$result = $item->insert(array(
			'name'=>'Freddy',
			'vorname'=>'Krueger',
			'code'=>'#8239709',
			'alter'=> '22',
			'id_nummer' => 2543524
		));
		// insert item
		$result = $item->insert(array(
			'name'=>'Martin',
			'vorname'=>'Solveign',
			'alter'=> '54',
            'code'=>'#000382',
			'id_nummer' => 134523
		));
		
	}
	
	public function start_msg($msg){
		echo '-----------------------------<br><span style="color:red;">Starting: '.$msg.'</span><br>-----------------------------<br>';
		
	}

    public function test_identifier_extraction($data, $pairs=false){
        $item = new TestItem();
        var_dump($item->extract_unique_identifier_values($data, $pairs));
    }
	
	public function test_direct_manipulation(){
		
		$this->start_msg('test_direct_manipulation()');

        $item = new TestItem();
		// insert new
		$result = $item->insert(array(
			'name'=>'Mustermann',
			'vorname'=>'Max',
			'alter'=> '35',
			'id_nummer' => 2464323
		));

		// get value
        echo '<strong>Old values:</strong> '.print_r($item->get(), true).'<br>';
		
		// update
		$result = $item->update(array(
			'alter' => 55
		));

        echo '<strong>New values:</strong> '.print_r($item->get(), true).'<br>';

		// delete
		$result = $item->delete();
        echo '<strong>Deleted item:</strong> '.($result?'YES':'NO').'<br>';

		// check if exists
		$result = $item->exists(array(
            'name'=>'Mustermann',
            'vorname'=>'Max'
		));
        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';
	}
	
	public function test_coupled_key_item(){
		

		$this->start_msg('test_coupled_key_item()');
		
		// test single item
		$item = new TestItem();
		
		// check if exists
		$result = $item->exists(array(
			'name'=>'Muster',
			'vorname'=>'Freddi'
		));

        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';

        $test_data = array(
            'name'=>'Muster',
            'vorname'=>'Freddi',
            'alter'=> 22,
            'id_nummer' => 3451274
        );

		// insert new
		$result = $item->insert($test_data);
        echo '<strong>Item inserted:</strong> '.($result === false?'NO': 'YES').'<br>';
		
		// check if exists
		$result = $item->exists(array(
			'name'=>'Muster',
			'vorname'=>'Freddi'
		));

        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';

		// load
		$result = $item->load(array(
			'name'=>'Muster',
			'vorname'=>'Freddi'
		));

        echo '<strong>Loading old Item:</strong> '.($result?'OK':'Not loaded').'<br>';
        echo '<strong>Old values:</strong> '.print_r($item->get(), true).'<br>';
		
		// update
		$result = $item->update(array(
			'alter' => 50
		));

		// load
		$result = $item->load(array(
			'name'=>'Muster',
			'vorname'=>'Freddi'
		));
		echo '<strong>Loading updated Item:</strong> '.$result.'<br>';
		
		// get
		echo '<strong>New values:</strong> '.print_r($item->get(), true).'<br>';
		
		// delete
		$result = $item->delete(array(
			'name'=>'Muster',
			'vorname'=>'Freddi'
		));

        echo '<strong>Deleted item:</strong> '.($result?'YES':'NO').'<br>';

		// check if exists
		$result = $item->exists(array(
			'name'=>'Muster',
			'vorname'=>'Freddi'
		));

        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';

		
	}
	
	/* test regular routines - unique key */
	public function test_unique_key_item(){
		
		$this->start_msg('test_unique_key_item()');
		
		$item = new TestItem();
		
		// check if exists: unique key
		$result = $item->exists(array(
			'id_nummer'=>4444
		));

        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';
		
		// insert new
		$result = $item->insert(array(
			'name'=>'Muster',
			'vorname'=>'Hans',
			'alter'=> 22,
			'id_nummer' => 4444
		));

		echo '<strong>Item inserted:</strong> '.($result === false?'NO': 'YES').'<br>';
		
		// check if exists: unique key
		$result = $item->exists(array(
			'id_nummer'=>4444
		));
		
		echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';
		
		// load from coupled key
		$result = $item->load(array(
			'id_nummer'=>4444
		));
		
		echo '<strong>Loading old Item:</strong> '.($result?'OK':'Not loaded').'<br>';
        echo '<strong>Old values:</strong> '.print_r($item->get(), true).'<br>';

		// update
		$result = $item->update(array(
			'alter'=>66
		));
		
		// load from coupled key
		$result = $item->load(array(
			'id_nummer'=>4444
		));
		
		// get
        echo '<strong>New values:</strong> '.print_r($item->get(), true).'<br>';
		

		// delete from single unique key
		$item->delete(array(
			'id_nummer'=>4444
		));
		
		echo '<strong>Deleting item:</strong> '.($result?'OK':'Not deleted').'<br>';
		
		// check if exists: unique key
		$result = $item->exists(array(
			'id_nummer'=>4444
		));

        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';
		
	}
	
	public function test_validation(){

        $this->start_msg('test_validation()');

        $item = new TestItem();

        // insert new
        $result = $item->insert(array(
            'name'=>'Muster',
            'vorname'=>'         Hans',
            'alter'=> 22,
            'id_nummer' => 143543
        ));

        echo '<strong>Validation errors:</strong> '.print_r($item->table->validator->get_errors(), true);
		
	}
	
	public function test_sanitation(){
		
	}
	
	public function test_function_binding(){

		
	}
	
	
}



?>