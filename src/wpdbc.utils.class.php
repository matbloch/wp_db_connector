<?php
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
?>