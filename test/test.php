<?php

// include class
include('wp_db_connector.class.php');


// create table
class TestTable extends DBTable{

    protected function define_db_table_name(){
        return 'db_connector_test';
    }
    protected function define_db_format(){
        return array(
            'id' => '%d',
            'id_nummer' => '%d',
            'name' => '%s',
            'vorname' => '%s',
            'alter' => '%d'
        );
    }
    protected function define_db_primary_key(){
        return 'id';
    }
    protected function define_validation_rules(){
        return array(
            'id' => 'integer',
            'manage_by_date' => 'exact_len,1|contains,1 0'
        );
    }
	
    protected function define_unique_key_pairs(){
        return array(array());
    }
    protected function define_unique_keys(){
        return array();
    }

    protected function define_validation_rules(){
        return array(array());
    }
    protected function define_sanitation_rules(){
        return array(array());

}


class TestItem extends DBObjectInterface{
	
	protected function define_db_table(){
        return new TestTable();
    }
	
}


class WPDBCTest{
	
	public function __construct(){
		
		$this->add_dummy_data();
		
	}
	
	
	public function add_dummy_data(){
		
		$item = new TestItem();
		// insert item
		$result = $item->insert(array(
			'name'=>'Freddy',
			'vorname'=>'Krueger',
			'alter'=> '22',
			'id_nummer' => 2543524
		));		
		// insert item
		$result = $item->insert(array(
			'name'=>'Martin',
			'vorname'=>'Solveign',
			'alter'=> '54',
			'id_nummer' => 134523
		));
		
	}
	
	public function start_msg($msg){
		echo '-----------------------------<br>Starting: '.$msg.'<br>-----------------------------<br>';
		
	}
	
	public function test_direct_manipulation(){
		
		$this->start_msg('test_direct_manipulation');
		
		// load from coupled key
		$result = $item->load(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));
		
		// get value
		echo '<strong>$item->get('vorname'):</strong> '.$item->get('vorname').'<br>';
		
		// delete
		$item->delete();
	}
	
	public function test_coupled_key_item(){
		

		$this->start_msg('test_coupled_key_item');
		
		// test single item
		$item = new TestItem();
		
		// check if exists
		$result = $item->exists(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));
		
		echo '<strong>Testitem exits:</strong> '.$result.'<br>';
		
		// insert new
		$result = $item->insert(array(
			'name'=>'Muster',
			'vorname'=>'Hans',
			'alter'=> '22',
			'id_nummer' => 234234
		));

		echo '<strong>Item inserted:</strong> '.$result.'<br>';
		
		// check if exists
		$result = $item->exists(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));

		echo '<strong>Testitem exits:</strong> '.$result.'<br>';

		// load
		$result = $item->load(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));

		echo '<strong>Loading old Item:</strong> '.$result.'<br>';
		echo '<strong>Old values:</strong> Alter:'.$item->get('alter').' | ID-NR.:'.$item->get('id_nummer').'<br>';
		
		// update
		$result = $item->update(array(
			'alter' => 13,
			'id_nummer' => 1337
		));

		// load
		$result = $item->load(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));
		echo '<strong>Loading updated Item:</strong> '.$result.'<br>';
		
		// get
		echo '<strong>New values:</strong> Alter:'.$item->get('alter').' | ID-NR.:'.$item->get('id_nummer').'<br>';
		
		// delete
		$result = $item->delete(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));
		
		echo '<strong>Deleting item:</strong> '.$result.'<br>';
				
		// check if exists
		$result = $item->exists(array(
			'name'=>'Muster',
			'vorname'=>'Hans'
		));
		
		echo '<strong>Testitem exits:</strong> '.$result.'<br>';

		
	}
	
	/* test regular routines - unique key */
	public function test_unique_key_item(){
		
		$this->start_msg('test_unique_key_item');
		
		$item = new TestItem();
		
		// check if exists: unique key
		$result = $item->exists(array(
			'id_nummer'=>234234
		));
		
		echo '<strong>Testitem exits:</strong> '.$result.'<br>';
		
		// insert new
		$result = $item->insert(array(
			'name'=>'Muster',
			'vorname'=>'Hans',
			'alter'=> '22',
			'id_nummer' => 234234
		));
		
		echo '<strong>Item inserted:</strong> '.$result.'<br>';
		
		// check if exists: unique key
		$result = $item->exists(array(
			'id_nummer'=>234234
		));
		
		echo '<strong>Testitem exits:</strong> '.$result.'<br>';
		
		// load from coupled key
		$result = $item->load(array(
			'id_nummer'=>234234
		));
		
		echo '<strong>Loading old Item:</strong> '.$result.'<br>';
		echo '<strong>Old values:</strong> Alter:'.$item->get('alter').' | ID-NR.:'.$item->get('id_nummer').'<br>';
		// update
		$result = $item->update(array(
			'id_nummer'=>1337
		));
		
		// load from coupled key
		$result = $item->load(array(
			'id_nummer'=>1337
		));
		
		// get
		echo '<strong>New values:</strong> Alter:'.$item->get('alter').' | ID-NR.:'.$item->get('id_nummer').'<br>';
		
		
		// delete from coupled key
		$item->delete(array(
			'id_nummer'=>1337
		));
		
		echo '<strong>Deleting item:</strong> '.$result.'<br>';
		
		// check if exists: unique key
		$result = $item->exists(array(
			'id_nummer'=>1337
		));
		
		echo '<strong>Testitem exits:</strong> '.$result.'<br>';
		
	}
	
	public function test_validation(){
		
		
	}
	
	public function test_sanitation(){
		
	}
	
	public function test_function_binding(){
		
		
		
	}
	
	
}


$test = new WPDBCTest();

$test->test_coupled_key_item();
$test->test_unique_key_item();
$test->test_validation();
$test->test_sanitation();

?>