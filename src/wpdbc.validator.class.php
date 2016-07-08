<?php

namespace wpdbc;

/**
 * Class Validator
 * @package wpdbc
 * Data validation and sanitation
 */
class Validator
{

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
        foreach ($validation_rules as $field_name => $rules) {
            $this->validation_rules[$field_name] = explode('|', $rules);
        }

        // copy validation rules
        foreach ($sanitation_rules as $field_name => $rules) {
            $this->sanitation_rules[$field_name] = explode('|', $rules);
        }

        // copy validation error feedback messages
        if (!empty($validation_error_msgs)) {
            $this->validation_error_msgs = $validation_error_msgs;
        }
    }

    /**
     * Get validation errors (debugging format)
     * @return mixed
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * Add error
     * @param $field_name
     * @param $context
     * @param $value
     * @param $rule
     * @param $param
     */
    protected function add_error($field_name, $context, $value, $rule, $param)
    {
        $this->errors[] = array(
            'field' => $field_name,
            'context' => $context,
            'value' => $value,
            'rule' => $rule,
            'param' => $param,
        );
    }

    /**
     * Get human readable error messages
     * @return array
     */
    public function get_clear_error_msgs()
    {
        $msgs = array();
        foreach ($this->errors as $e) {
            $msgs[$e['field']][$e['rule']] = $this->get_validation_error_msg($e['field'], $e['rule'], $e['value']);

        }
        return $msgs;
    }

    /**
     * Get single validation error message
     * @param $col
     * @param $rule
     * @param $val
     * @return mixed|string
     */
    private function get_validation_error_msg($col, $rule, $val)
    {
        $messages = array(
            'required' => 'Dieses Feld ist ein Pflichtfeld.',
            'ban' => 'Dieses Feld ist nicht erlaubt.',
            'numeric' => 'Dieses Feld ist keine Zahl.',
            'float' => 'Dieses Feld ist keine Fliesskomma Zahl.',
            'alpha_numeric' => 'Dieses Feld muss einen alphanumerischen Wert haben.',
            'boolean' => 'Dieses Feld muss einen boolean Wert haben.',
            'url' => 'Dieses Feld ist keine gültige URL.',
            'email' => 'Dieses Feld ist keine gültige Email-Adresse.',
            'date' => 'Dieses Feld ist kein gültiges Datum.'
        );

        if (isset($this->validation_error_msgs[$col][$rule])) {
            return $this->validation_error_msgs[$col][$rule];
        } else if ($rule && isset($messages[$rule])) {
            return $messages[$rule];
        } else {
            return 'Data has wrong format.';
        }
    }

    public function sanitize(array $data, $context = null)
    {

        if (empty($this->sanitation_rules)) {
            return $data;
        }

        foreach ($data as $field_name => $value) {
            // do sanitation if a rule is defined for the column name
            if (array_key_exists($field_name, $this->sanitation_rules)) {
                // check all rules for this field
                foreach ($this->sanitation_rules[$field_name] as $rule_str) {

                    $method = null;
                    $param_str = null;
                    $context_arr = null;
                    $rule = null;
                    $rule_parts = array();

                    // TODO: replace rule param delimiter to allow "|" and " " in options

                    // extract parameters
                    $rule_parts = explode(' ', $rule_str, 2);
                    if (count($rule_parts) == 2) {
                        // parameters present
                        $param_str = $rule_parts[1];
                        $rule_str = $rule_parts[0];
                    }

                    $rule_parts = explode(':', $rule_str);
                    // contexts present
                    if (count($rule_parts) == 2) {
                        $rule = array_shift($rule_parts);   // remove first element = rule
                        $context_arr = $rule_parts;
                    } else {
                        $rule = $rule_str;
                    }

                    $method = 'sanitize_' . $rule;

                    // perform sanitation if its in the right context
                    if ($context_arr == null || in_array($context, $context_arr)) {
                        // predefined sanitation rules
                        if (is_callable(array($this, $method))) {
                            // sanitize
                            if (is_array($value)) {
                                // multiple values at once
                                foreach ($value as $k => $single_val) {
                                    $this->$method($k, $context, $data[$field_name], $param_str);
                                }
                            } else {
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
    public function validate($context, array $data)
    {
        // clear errors
        $this->errors = array();

        if (!$this->validation_rules) {
            return true;
        }

        foreach ($data as $field_name => $value) {
            if (array_key_exists($field_name, $this->validation_rules)) {
                foreach ($this->validation_rules[$field_name] as $rule_str) {

                    $valid = true;
                    $method = null;
                    $param_str = null;
                    $context_arr = null;
                    $rule = null;
                    $rule_parts = array();

                    // TODO: replace rule param delimiter to allow "|" and " " in options

                    // extract parameters
                    $rule_parts = explode(' ', $rule_str, 2);
                    if (count($rule_parts) == 2) {
                        // parameters present
                        $param_str = $rule_parts[1];
                        $rule_str = $rule_parts[0];
                    }

                    $rule_parts = explode(':', $rule_str);
                    // contexts present
                    if (count($rule_parts) == 2) {
                        $rule = array_shift($rule_parts);   // remove first element = rule
                        $context_arr = $rule_parts;
                    } else {
                        $rule = $rule_str;
                    }

                    $method = 'validate_' . $rule;

                    // predefined rules - check if in correct context
                    if ($context_arr == null || in_array($context, $context_arr)) {
                        if (is_callable(array($this, $method))) {

                            // sanitize
                            if (is_array($value)) {
                                // multiple values at once
                                foreach ($value as $k => $single_val) {
                                    $valid = $this->$method($k, $context, $data[$field_name], $param_str);
                                    if (!$valid) {
                                        $this->add_error($field_name . '[' . $k . ']', $context, $data[$field_name], $rule, $param_str);
                                    }
                                }
                            } else {

                                $valid = $this->$method($field_name, $context, $data, $param_str);

                                if (!$valid) {
                                    $this->add_error($field_name, $context, $value, $rule, $param_str);
                                }
                            }
                            // user defined rules
                        } elseif (isset(self::$validation_methods[$rule])) {
                            $valid = call_user_func(self::$validation_methods[$rule], $field_name, $context, $data, $param_str);
                            if (!$valid) {



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

        if (empty($this->errors)) {
            return true;
        }

        return false;
    }

    /* sanitation functions */
    private function sanitize_exclude_keys($field, $context, &$data, $param = null)
    {
        if ($param != null && is_array($data[$field])) {
            $keys_to_remove = array_flip(explode(' ', $param));
            $data[$field] = array_diff_key($data[$field], $keys_to_remove);
        }
    }

    private function sanitize_exclude_values($field, $context, &$data, $param = null)
    {
        if ($param != null && is_array($data[$field])) {
            $data[$field] = array_diff($data[$field], explode(' ', $param));
        }
    }

    private function sanitize_exclude($field, $context, &$data, $param = null)
    {
        unset($data[$field]);
    }

    private function sanitize_trim($field, $context, &$data, $param = null)
    {
        $data[$field] = trim($data[$field]);
    }

    private function sanitize_lowercase($field, $context, &$data, $param = null)
    {
        $data[$field] = strtolower($data[$field]);
    }

    private function sanitize_uppercase($field, $context, &$data, $param = null)
    {
        $data[$field] = strtoupper($data[$field]);
    }

    /* validation functions */
    private function validate_ban($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        if ($param != null) {
            if (in_array($data[$field], explode(' ', $param))) {
                return false;
            }
        }
        return !isset($data[$field]);
    }

    private function validate_required($field, $context, $data, $param = null)
    {
        if (!isset($data[$field])) {
            return false;
        }
        if (is_array($data[$field])) {
            return !empty($data[$field]);
        } else {
            return ($data[$field] !== "");
        }
    }

    private function validate_numeric($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return is_numeric($data[$field]);
    }

    private function validate_float($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return filter_var($data[$field], FILTER_VALIDATE_FLOAT);
    }

    private function validate_integer($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return preg_match('/^\d+$/', $data[$field]);
    }

    private function validate_alpha_numeric($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return (preg_match("/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ\s])+$/i", $data[$field]) ? true : false);
    }

    private function validate_alpha_space($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return (preg_match('/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i', $data[$field]) ? true : false);
    }

    private function validate_min_len($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($data[$field]) >= (int)$param) {
                return true;
            }
        } else {
            if (strlen($data[$field]) >= (int)$param) {
                return true;
            }
        }
        return false;
    }

    private function validate_max_len($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($data[$field]) <= (int)$param) {
                return true;
            }
        } else {
            if (strlen($data[$field]) <= (int)$param) {
                return true;
            }
        }
        return false;
    }

    private function validate_boolean($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return (is_bool($data[$field]) || ($data[$field] == 1 || $data[$field] == 0));
    }

    private function validate_array($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return is_array($data[$field]);
    }

    private function validate_url($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return filter_var($data[$field], FILTER_VALIDATE_URL);
    }

    private function validate_email($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return filter_var($data[$field], FILTER_VALIDATE_EMAIL);
    }

    private function validate_name($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return preg_match("/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïñðòóôõöùúûüýÿ '-])+$/i", $data[$field]);
    }

    private function validate_date($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        $timestamp = strtotime($data[$field]);
        return $timestamp ? true : false;
    }

    private function validate_starts($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        foreach (explode(' ', $param) as $start) {
            if (strpos($data[$field], $start) === 0) {
                return true;
            }
        }
        return false;
    }

    private function validate_ends($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        foreach (explode(' ', $param) as $end) {
            if (strlen($data[$field]) - strlen($end) == strrpos($data[$field], $end)) {
                return true;
            }
        }
        return false;
    }

    private function validate_regex($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        return (preg_match($param, $data[$field]) ? true : false);
    }

    private function validate_contains($field, $context, $data, $param = null)
    {
        if (!isset($data[$field]))
            return true;
        if (in_array($data[$field], explode(' ', $param))) {
            return true;
        }
        return false;
    }

}
