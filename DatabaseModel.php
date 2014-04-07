<?php
namespace Classes\Utilities\Database;

/**
 * Class DatabaseModel
 * @package Classes\Utilities\Database
 * @author Brandon Carlson <brandon@aphion.com>
 * @description Database helper class
 */
class DatabaseModel
{

    //properties
    private $schema = array();
    private $cache = false;
    private $cachetime = 3600;
    protected $text_fields = array('text','bigtext','smalltext','varchar','date','datetime','time','timestamp','enum','char');
    public $result = null;
    public $return_type = 'array';
    public $error = '';
    protected $current_environment = null;


    /**
     * setReturnType()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Sets the return type used when fetching data
     * @param String $type - Type of data to return [array | object]
     */
    public function setReturnType($type)
    {
        $this->return_type = $type;
    }

    /**
     * prepField()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Preps data for entry into the database. Checks the field against the data schema, and determines
     *      whether to quote or not the field data. Also runs mysql_real_escape_string to reduce SQL injection attacks.
     *
     * @param String $field - name of the field to prep in the schema
     * @param String $value - value to prep and check
     * @return string
     */
    public function prepField($field, $value, $field_type='string') {
        $schema = $this->getSchema();
        //try to split the field name by dots...
        $field_info = preg_split('/\./',$field);
        switch(count($field_info)) {
            case "1":
                //no table or DB, just a field name
                $field_table_name = empty($schema[0]['name']) ? '' : strtolower($schema[0]['name']);
                $field_name = empty($field_info[0]) ? '' : $field_info[0];
                break;
            case "2":
                //table.field format
                $field_table_name = $field_info[0];
                $field_name = $field_info[1];
                break;
            default:
                //this could in theory be DB.Table.Field format, but we're not implementing that right now.
                if(count($field_info)>0) {
                    $field_table_name = strtolower($schema[count($field_info)-2]['name']);
                    $field_name = $field_info[count($field_info)-1]['name'];
                }
                else {
                    $field_table_name = '';
                    $field_name = '';
                }
        }
        //now we have the Table name, and field name, let's see if that's in our schema...
        foreach($schema as $table_schema) {
            $table_name = $table_schema['name'];
            if(strtolower($table_name) == strtolower($field_table_name)) {
                //we're on the right table, now get the field type...
                foreach($table_schema['schema'] as $definition_cnt=>$definition_value) {
                    if(isset($definition_value['Field']) && strtolower($definition_value['Field']) == strtolower($field_name)) {
                        $field_type = $this->getType($table_schema['schema'][$definition_cnt]['Type']);
                    }
                }
            }
        }

        //create the value with or without strings...
        switch($field_type) {
            case 'number':
            case 'numeric':
                return ($value != '0' && empty($value)) ? 'null' : mysql_real_escape_string($value);
                break;
            default:
                return "'".mysql_real_escape_string($value)."'";

        }
    }
    /**
     * getType()
     * @author Brandon Carlson <brandon@aphion.com>
     * @param $type
     * @return string Type of data
     */
    public function getType($type)
    {
        if(in_array(preg_replace("/\(.*?\)/","",$type),$this->text_fields)) {
            return 'string';
        }
        return 'numeric';
    }

    /**
     * buildConditions()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Function to take an array of conditions and build a valid QUERY from the array
     * @param Mixed $conditions - Array or string of a QUERY fragment
     * @return null
     */
    public function buildConditions($conditions, $schema, $type='and')
    {
        $this->setSchema($schema);
        $where_string = '';
        $clauses = array();
        if(is_array($conditions)) {
            //this is an array of fields and values, or AND / OR array
            foreach($conditions as $field=>$value) {
                switch(strtolower($field)) {
                    case "and":
                        $clauses[] = ' ('.$this->buildConditions($value, $schema).') ';
                        break;
                    case "or":
                        $clauses[] = ' ('.$this->buildConditions($value, $schema, 'or').') ';
                        break;
                    default:
                        if(is_numeric($field) && is_array($value)) {
                            if(array_key_exists('and',$value)) {
                                $clauses[] = ' ('.$this->buildConditions($value,$schema, 'and').') ';
                            }
                            elseif(array_key_exists('or',$value)) {
                                $clauses[] = ' ('.$this->buildConditions($value, $schema, 'or').') ';
                            }
                            else {
                                $field = isset($value['field']) ? $value['field'] : '';
                                if($field=='') {
                                    throw new \Exception('Missing required field entry in array');
                                }
                                $operator = isset($value['operator']) ? $this->getOperator($value['operator']) : ' = ';
                                if($operator == ' IN ') {
                                    //this is an IN clause, we need to db prep each part seperately...
                                    //need to handle things like ['1','2','3'] and ['Hello, how are you','another, what?']
                                    $in_values = array();
                                    $in_base_value = $value['value'];
                                    //remove beginning and ending apostrophes.
                                    $in_base_value = preg_replace("/^'/","",$in_base_value);
                                    $in_base_value = preg_replace("/'$/","",$in_base_value);
                                    $tmp_values = preg_split("/','/",$in_base_value);
                                    foreach($tmp_values as $tmp_value) {
                                        $in_values[] = $this->prepField($field,$tmp_value);
                                    }
                                    $db_value = '('.implode(',',$in_values).')';

                                }
                                else {
                                    $db_value = isset($value['value']) ? $this->prepField($field,$value['value']) : " '' ";
                                }
                                $clauses[] = $field . $operator . $db_value;
                            }
                        }
                        else {
                            $clauses[] = $field . ' = '. $this->prepField($field,$value);
                        }
                }
            }
            $where_string = implode(' '.$type.' ',$clauses);
        }
        else {
            //string...
            $parts = preg_split("/(and|or)/i",$conditions);
            foreach($parts as $part) {

            }
            $where_string = $conditions;
        }
        return $where_string;
    }

    /**
     * getOperator()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Gets the SQL comparison operator in part of a query
     *
     * @param String $o Operator type
     * @return string
     */
    private function getOperator($o) {
        $valid_operators = array(
            '='             =>  ' = ',
            'equals'        =>  ' = ',
            'greater'       =>  ' > ',
            'greaterthan'   =>  ' > ',
            '>'             =>  ' > ',
            'greaterequal'  =>  ' >= ',
            'greaterthanorequalto'  =>  ' >= ',
            '>='            =>  ' >= ',
            '<'             =>  ' < ',
            'lessthan'      =>  ' <= ',
            'lessthanorequalto'     =>  ' <= ',
            '<='            =>  ' <= ',
            'like'          =>  ' like ',
            'in'            =>  ' IN ',
            'notequals'     =>  ' <> ',
            '<>'            =>  ' <> '
        );
        if(array_key_exists(strtolower($o),$valid_operators)) {
            return $valid_operators[$o];
        }
        else {
            return ' = ';
        }
    }

    /**
     * getSchema()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Getter for schema property
     * @return array
     */
    protected function getSchema() {
        return $this->schema;
    }

    /**
     * setSchema()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Setter for schema property
     * @return array
     */
    protected function setSchema($schema) {
        $this->schema = $schema;
    }

    /**
     * getCache()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Getter for cache property
     * @return array
     */
    protected function setCache($c) {
        $this->cache = $c;
    }

    /**
     * getCache()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Setter for cache property
     * @return array
     */
    protected function getCache() {
        return $this->cache;
    }

    /**
     * getCacheTime()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Getter for cachetime property
     * @return array
     */
    protected function setCacheTime($t) {
        $this->cachetime = $t;
    }

    /**
     * getCacheTime()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Setter for cachetime property
     * @return array
     */
    protected function getCacheTime() {
        return $this->cachetime;
    }

    /**
     * getCurrentEnvironment
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Gets the current connection environment
     * @return string
     */
    public function getCurrentEnvironment() {
        return $this->current_environment;
    }
}