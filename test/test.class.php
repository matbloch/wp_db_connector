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
            'age' => '%d'
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
            'code' => 'starts #',
            'name' => 'required:insert|name',
            'vorname' => 'required:insert',
            'age' => 'integer'
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

/* define table */
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
        return 'BoundTestTable';
    }

}

/* define test item handler */
class TestItem extends DBObjectInterface{
	
	protected function define_db_table(){
        return 'TestTable';
    }

    protected function define_data_binding(){
        // placeholder function
        // use bind_action here to define the data binding
        //$this->bind_action('insert_before', array($this,'bound_insert'));
        //$this->bind_action('delete_before', array($this,'bound_delete'));
    }

    protected function bound_insert($data, $where){

        echo '<br><br>bound action: create bounded item<br>';

        if(isset($data['id_nummer'])){

            $i = new BoundTestItem();

            $a = $i->exists($data);
            echo '<strong>Bound item exits:</strong> '.($a?'YES':'NO').'<br>';
            $a = $i->insert(array('id_nummer'=>$data['id_nummer']));
            echo '<strong>Bound item inserted:</strong> '.($a === false?'NO': 'YES').'<br>';
            $a = $i->exists($data);
            echo '<strong>Bound item exits:</strong> '.($a?'YES':'NO').'<br>';
        }

        echo '<br><br>';
    }
    protected function bound_delete($where){

        echo '<br><br>bound action: delete bounded item<br>';

        $id = 0;

        // if the primary key is not provided: get it from parent
        if($where && !isset($where['id_nummer'])){

            $parent = new TestItem();
            $succ = $parent->load($where);

            if(!$succ){
                echo '<br>Failed to load parent!<br>';
                return;
            }
            $id = $parent->get('id_nummer');

        }else{

            $id = $where['id_nummer'];
        }

        $where_bounded = array('id_nummer'=>$id);
        $bound_item = new BoundTestItem();
        $result = $bound_item->exists($where_bounded);
        echo '<strong>Bound item exits:</strong> '.($result?'YES':'NO').'<br>';
        $result = $bound_item->delete($where_bounded);
        echo '<strong>Bound Item deleted:</strong> '.($result === false?'NO': 'YES').'<br>';
        $result = $bound_item->exists($where_bounded);
        echo '<strong>Bound item exits:</strong> '.($result?'YES':'NO').'<br>';

        echo '<br><br>';

    }
}

class MultiObjectHandler extends DBObjectsHandler{

    protected function define_db_table(){
        return 'TestTable';
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
					 `age` int(11) UNSIGNED COMMENT '',
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
	}

    public function object_memory_usage(){

        $before = memory_get_usage();
        $item = new TestItem();
        $after = memory_get_usage();

        $kb = round(($after - $before)/1024,2);

        // object size
        echo 'Instance memory usage: '.$kb.' kb<br>';

        // total peak memory
        echo 'Peak memory usage: ';
        echo round(memory_get_peak_usage()/1048576,2).' MB';
    }

	public function add_dummy_data(){

        // item handler
		$item = new TestItem();
        //$item->debugging(true);

		// insert item
		$result = $item->insert(array(
			'name'=>'Freddy',
			'vorname'=>'Krueger',
			'code'=>'#8239709',
			'age'=> 22,
			'id_nummer' => 2543524
		));
		// insert item
		$result = $item->insert(array(
			'name'=>'Martin',
			'vorname'=>'Solveign',
			'age'=> 54,
            'code'=>'#000382',
			'id_nummer' => 134523
		));

        $result = $item->insert(array(
            'name'=>'Warhole',
            'vorname'=>'Martin',
            'age'=> 15,
            'code'=>'#19994',
            'id_nummer' => 3567835
        ));
        $result = $item->insert(array(
            'name'=>'Warhole',
            'vorname'=>'Andy',
            'age'=> 45,
            'code'=>'#92389',
            'id_nummer' => 02345256
        ));
        $result = $item->insert(array(
            'name'=>'Bowie',
            'vorname'=>'David',
            'age'=> 45,
            'code'=>'#993asd',
            'id_nummer' => 4175634
        ));
        $result = $item->insert(array(
            'name'=>'Warhole',
            'vorname'=>'Alex',
            'age'=> 11,
            'code'=>'#2904AC',
            'id_nummer' => 2893833
        ));
        $result = $item->insert(array(
            'name'=>'Warhole',
            'vorname'=>'Jason',
            'age'=> 87,
            'code'=>'#8481dA',
            'id_nummer' => 7738923
        ));
		
	}
	
	public function start_msg($msg){
		echo '-----------------------------<br><span style="color:red;">Starting: '.$msg.'</span><br>-----------------------------<br>';
		
	}

    public function test_identifier_extraction($data, $pairs=false){
        $item = new TestItem();
        var_dump($item->extract_unique_identifier_values($data, $pairs));
    }

    public function test_main_function_response(){

        $this->start_msg('test_main_function_response()');
        $item = new TestItem();

        $testdata = array(
            'name'=>'Mustermann',
            'vorname'=>'Max',
            'age'=> 35,
            'id_nummer' => 2464323);

        // ---- INSERT

        // insert new
        $result = $item->insert($testdata);
        echo 'insert new: '.print_r($result, true).'<br>';

        // insert already existing entry
        $result = $item->insert($testdata);
        echo 'insert already existing entry: '.print_r($result, true).'<br>';

        // cleanup
        $item->delete($testdata);

        // insert with validation error (validator)
        $testdata['age'] = '123a';
        $result = $item->insert($testdata);
        echo 'insert with validation error: '.var_export($result, true).'<br>';
        $item->delete($testdata);

        // ---- LOAD

        $testdata = array(
            'name'=>'Mustermann',
            'vorname'=>'Max',
            'age'=> 35,
            'id_nummer' => 2464323);
        $item->insert($testdata);

        // load
        $result = $item->load($testdata);
        echo 'load: '.print_r($result, true).'<br>';

        // load non existing (always by primary key)
        $item->delete();
        $result = $item->load($testdata);
        echo 'load non existing: '.var_export($result, true).'<br>';

        // validation error
        $testdata['name'] = 12341234;
        $result = $item->load($testdata);
        echo 'loading with validation error: '.var_export($result, true).'<br>';

        // ---- UPDATE
        $testdata = array(
            'name'=>'Mustermann',
            'vorname'=>'Max',
            'age'=> 35,
            'id_nummer' => 2464323);
        $item->insert($testdata);

        // update
        $update = array('age' => 1337);
        $result = $item->update($update);
        echo 'update: '.var_export($result, true).'<br>';

        // update same values
        $result = $item->update($update);
        echo 'update same values: '.var_export($result, true).'<br>';

        // update non existing
        $item->delete();
        $result = $item->update($update, $testdata);
        echo 'update non existing: '.var_export($result, true).'<br>';

        // validation error
        $update['age'] = 'asdf';
        $result = $item->update($update, $testdata);
        echo 'validation error: '.var_export($result, true).'<br>';

        // ---- DELETE
        $item->insert($testdata);

        // delete
        $result = $item->delete(array('name'=>'Mustermann', 'vorname'=>'Max'));
        echo 'delete: '.var_export($result, true).'<br>';

        // delete non existing
        $result = $item->delete(array('name'=>'Mustermann', 'vorname'=>'Max'));
        echo 'delete non existing: '.var_export($result, true).'<br>';

        // validation error
        $result = $item->delete(array('name'=>1234, 'vorname'=>'test'));
        echo 'validation error: '.var_export($result, true).'<br>';

    }

    /* STATUS: TESTED */
	public function test_direct_manipulation(){
		
		$this->start_msg('test_direct_manipulation()');
        $item = new TestItem();

		// insert new
		$result = $item->insert(array(
			'name'=>'Mustermann',
			'vorname'=>'Max',
			'age'=> 35,
			'id_nummer' => 2464323
		), true);

        // get value
        echo '<strong>Old values:</strong> '.print_r($item->get(), true).'<br>';

        // update
        $result = $item->update(array(
			'age' => 55
		));

       echo '<strong>New values:</strong> '.print_r($item->get(), true).'<br>';

        // delete
        $item->debugging(true);
		$result = $item->delete();
        echo '<strong>Deleted item:</strong> '.($result?'YES':'NO').'<br>';
        $item->debugging(false);

		// check if exists
		$result = $item->exists(array(
            'name'=>'Mustermann',
            'vorname'=>'Max'
		));
        echo '<strong>Testitem exits:</strong> '.($result?'YES':'NO').'<br>';
	}

    /* STATUS: TESTED */
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
            'age'=> 22,
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
			'age' => 50
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

    /* STATUS: TESTED */
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
			'age'=> 22,
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
			'age'=>66
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

    /* STATUS: TESTED */
	public function test_validation(){

        $this->start_msg('test_validation()');

        $item = new TestItem();
        $item ->debugging(true);

        // insert new
        $result = $item->insert(array(
            'name'=>'Muster',
            'vorname'=>'         Hans',
            'age'=> 22,
            'id_nummer' => '45647435345'
        ));
		
	}
	
	public function test_sanitation(){

	}

    /* STATUS: PENDING */
    public function test_multi_obj_handler(){

        $this->add_dummy_data();
        $h = new MultiObjectHandler();


        // load from db
        $h->load(
            array(
                'name'=>array('      Warhole    ', 'Bowie'),
                //'name'=>array('Warhole', 'Bowie'),
            ),
           array(
               //'name'=>array('Warhole', 'Bowie', 'Muster'),
               //'vorname'=> 'Martin'
           ),
           array(
               'limit' => 5,
               'group_by' => 'name'
           )
        );

        // display results
        $familiy_members = $h->get_objects();
        foreach($familiy_members as $f=>$m){
            echo '<strong>Family '.$f.'</strong><br>';
            foreach($m as $i){
                print_r($i);echo '<br>';
            }
        }

        $h->debugging(true);

        // update
        $h->update(
            array(
                'name'=>'name_updated',
                //'vorname'=>'Bill',      // illegal: unique key pair
            ),
            true
        );

        // delete
        $h->delete(
            false,
            array(),
            array(
                'vorname' => array('David', 'Alex', 'Jason'),
                'name' => 'name_updated'
            )
        );

        $h->count_all();

    }
}

?>