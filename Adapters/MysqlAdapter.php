<?php

namespace Classes\Utilities\Database\Adapters;

use \Config\App\Setting as Setting;
use \Classes\Utilities\Database\DatabaseModel as DatabaseModel;
use \Classes\Utilities\Database\Interfaces\DatabaseInterface as DatabaseInterface;

/**
 * Class MysqlAdapter
 * @package Config\Database
 * @author Brandon Carlson <brandon@aphion.com>
 * @version 1
 * @Description MySQL adapter class to interface with a MySQL database using native (deprecated) mysql_ php functions
 */
class MysqlAdapter extends DatabaseModel implements DatabaseInterface
{

    private $database;
    private $username;
    private $password;
    private $host;
    private $port;
    private $resource = null;

    const DEFAULT_CACHE = false;
    const DEFAULT_CACHE_TIME = 3600;

    /**
     * connect()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Constructor method for the class
     */
    public function connect($env = '')
    {
        if($env=='') {
            $env = Setting::getInstance()->getEnvironment();
        }
        $this->loadCredentials($env);
        mysql_connect($this->host, $this->username, $this->password) or die('Database Unavailable');
        $this->resource = mysql_select_db($this->database) or die('No Permission to database');
        $this->current_environment = $env;
    }

    /**
     * loadCredentials()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description loads the proper database credentials based on the selected environment
     * @param $env String environment to load
     */
    public function loadCredentials($env)
    {
        switch($env) {
            case "local":
                $this->database = MYSQL_LOCAL_DATABASE;
                $this->username = MYSQL_LOCAL_USER;
                $this->password = MYSQL_LOCAL_PASSWORD;
                $this->host     = MYSQL_LOCAL_HOST;
                $this->port     = MYSQL_PORT;
                break;
            case "dev":
                $this->database = MYSQL_DEV_DATABASE;
                $this->username = MYSQL_DEV_USER;
                $this->password = MYSQL_DEV_PASSWORD;
                $this->host     = MYSQL_DEV_HOST;
                $this->port     = MYSQL_PORT;
                break;
            default:
                $this->database = MYSQL_PRODUCTION_DATABASE;
                $this->username = MYSQL_PRODUCTION_USER;
                $this->password = MYSQL_PRODUCTION_PASSWORD;
                $this->host     = MYSQL_PRODUCTION_HOST;
                $this->port     = MYSQL_PORT;
        }
    }

    /**
     * disconnect()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description disconnects from the database
     *
     */
    public function disconnect()
    {
        mysql_close($this->resource);
        $this->resource = null;
    }

    /**
     * query()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description allows for a direct SQL query against the database
     * @param String $query query to run
     * @return Object $this current object
     */
    public function query($query)
    {
        //reset the error
        $this->error = '';

        //run the query
        $this->result = mysql_query($query) or $this->error = mysql_error()."[".$query."]";

        if($this->error != '') {
            error_log($this->error);
        }

        //return the object
        return $this;
    }


    /**
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Allows for a direct query, but uses Memcache to cache the results
     * @param String $query - SQL Query to run
     * @param String $operation - What fetch type to use [fetch, fetchAll]
     * @return array
     */
    public function queryCache($query,$operation)
    {
        //reset the error
        $this->error = '';

        $data = null;
        //check cache settings...
        if($this->getCache()) {
            //this query should be cached
            $key = strtoupper(get_class($this)).'_1'.md5($query);
            $cached_value = \Custom_Memcached::getInstance()->get($key);

            //if there's a cached value, load it, otherwise, query the DB and cache the result.
            if($cached_value) {
                //we have the key, load it...
                $data = $cached_value;
            }
            else {
                switch($operation) {
                    case 'fetchAll':
                        $data = $this->query($query)->fetchAll();
                        break;
                    default:
                        $data = $this->query($query)->fetch();
                }
                try
                {
                    \Custom_Memcached::getInstance()->set($key,$data,0,$this->getCacheTime());
                }
                catch(\Exception $e) {

                }
            }

            //reset the cache setting so we don't run multiple queries from cache when we might want them loaded.
            $this->setCache(self::DEFAULT_CACHE);
            $this->setCacheTime(self::DEFAULT_CACHE_TIME);

        }
        else {
            //run the query passed in.
            switch($operation) {
                case 'fetchAll':
                    $data = $this->query($query)->fetchAll();
                    break;
                default:
                    $data = $this->query($query)->fetch();
            }

        }

        //return the data.
        return $data;
    }

    /**
     * fetch()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description return an associative array on the result from the query
     * @return array
     * @throws \Exception e
     */
    public function fetch()
    {
        if($this->error == '') {
            if($this->return_type=='object') {
                return mysql_fetch_object($this->result);
            }
            else {
                return mysql_fetch_assoc($this->result);
            }
        }
        else {
            throw new \Exception('Invalid query:'.$this->error);
        }
    }

    /**
     * fetchAll()
     * @author Brandon Carlson <brandon@aphion.com>
     * @return array an associative array of all the result data from the query
     */
    public function fetchAll()
    {
        $results = array();
        while($r = $this->fetch()) {
            $results[] = $r;
        }
        return $results;
    }

    /**
     * delete()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description deletes an entry from a table
     * @param String $table name of table
     * @param Array $options options
     *
     * @return Boolean Whether the delete is successful or not
     */
    public function delete($table, $options)
    {
        //build the appropriate where clause
        $where = '';
        if(!empty($options['conditions'])) {
            $conditions = $this->buildConditions($options['conditions'],$this->getTableSchema($table));
            if($conditions != '') {
                $where = " WHERE ".$conditions;
            }
        }

        //generate the SQL
        $sql = 'DELETE FROM '.$table.$where;

        //run the SQL
        $this->query($sql);
        if($this->error == '') {
            return true;
        }
        return false;
    }

    /**
     * getInsertId()
     * @author Brandon Carlson <brandon@aphion.com>
     * @return String the last ID inserted into the database
     */
    public function getInsertId()
    {
        return mysql_insert_id();
    }

    /**
     * countRows()
     * @author Brandon Carlson <brandon@aphion.com>
     * @return Int number of rows returned from the query
     */
    public function countRows()
    {
        return mysql_num_rows($this->result);
    }

    /**
     * getEffectedRows()
     * @author Brandon Carlson <brandon@aphion.com>
     * @return Int number of rows in the database that are changed
     */
    public function getAffectedRows()
    {
        return mysql_affected_rows($this->result);
    }
    /**
     * getResult()
     * @author Brandon Carlson <brandon@aphion.com>
     * @return Resource MySQL resource stream reference
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * update()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Function to update a table given a set of conditions
     * @param String $table - table to update
     * @param Array $options - list of options
     * @return Boolean whether the query was successful or not
     */
    public function update($table, $options)
    {
        $fields_to_update = array();
        $schema = $this->getTableSchema($table);

        $schema_info = array();
        foreach($schema[0]['schema'] as $db_field) {
            $schema_info[$db_field['Field']] = $this->getType($db_field['Type']);
        }
        foreach($options['data'] as $field=>$value) {
            $value = $this->prepField($field,$value);
            $fields_to_update[] = "$field = $value";
        }

        //build conditions clause.
        $conditions = "";
        if(!empty($options['conditions'])) {
            $conditions = $this->buildConditions($options['conditions'], $schema);
            if($conditions != '') {
                $conditions = " WHERE ".$conditions;
            }
        }
        $sql = "
            UPDATE
                $table
            SET
              ".implode(", ",$fields_to_update)."
            $conditions
        ";
        if($this->query($sql)->getResult()) {
            return true;
        }
        return false;

    }

    /**
     * insert()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Inserts a row into a table
     * @param String $table Name of table to insert a record
     * @param Array $options Array of options in key-value pairs
     * @return bool Whether the insert succeeded
     */
    public function insert($table, $options)
    {
        $fields = array();
        $values = array();
        $schema = $this->getTableSchema($table);
        $schema_info = array();
        foreach($schema[0]['schema'] as $db_field) {
            $schema_info[$db_field['Field']] = $this->getType($db_field['Type']);
        }
        foreach($options['data'] as $field=>$value) {
            $fields[] = $field;
            $values[] = $this->prepField($field,$value);
        }
        $sql = "
            INSERT
                INTO $table (
                    ".implode(",",$fields)."
                )
                VALUES (
                    ".implode(",",$values)."
                )
        ";
        if($this->query($sql)->getResult()) {
            return true;
        }
        return false;
    }

    /**
     * save()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Method to save data. Calls update or insert based on whether an id exists in the data
     * @param String $table Table to save data to
     * @param Array $options Array of data in key-value pairs
     * @return Bool Whether the save was successful.
     */
    public function save($table, $options)
    {
        $success = false;
        //find the primary key on the table...
        $primary_key = '';
        $schema_info = $this->getTableSchema($table);
        foreach($schema_info[0]['schema'] as $field_offset=>$info) {
            if(isset($info['Key']) && $info['Key']=='PRI') {
                $primary_key = $schema_info[0]['schema'][$field_offset]['Field'];
            }
        }
        if(empty($options['data'][$primary_key])) {
            unset($options['data'][$primary_key]);
        }

        //build the query conditions, and execute the appropriate query.
        if((isset($options['data'][$primary_key]) && $options['data'][$primary_key]!='') || (isset($options['conditions']) && count($options['conditions'])>0) ) {
            $options['conditions'][$primary_key] = $options['data'][$primary_key];
            $success = $this->update($table,$options);
        }
        else {
            $success = $this->insert($table,$options);
        }

        return $success;
    }

    /**
     * @param String $action - [first, all, list, count] What type of data to query for
     * @param Array $options - Array of options
     *      $options['conditions'] = where clause
     *      $options['order'] = order clause
     *      $options['page'] = limit page clause
     *      $options['limit'] = per limit clause
     *      $options['group'] = group by clause
     *      $options['fields'] = fields to return
     *      $options['join'] = array of join clauses
     *      $options['key'] = key to use for list query
     *      $options['name'] = field to use for list query
     *      $options['tables'] = initial from clause
     *      $options['cache'] = Store the results in cache, default false
     *      $options['cachetime'] = How long to store the cached results default 3600 - 1 hour.
     * @return Mixed
     * @throws \Exception e
     */
    public function find($action, $options)
    {
        //need the tables set, otherwise what would be be trying to find?
        if(empty($options['tables'])) {
            throw new \Exception('Missing Table information in find query');
        }

        //getting some values
        $limit = !empty($options['limit']) ? $options['limit'] : null;
        $page = !empty($options['page']) ? $options['page'] : null;
        $cache = !empty($options['cache']) ? $options['cache'] : self::DEFAULT_CACHE;
        $cachetime = !empty($options['cachetime']) ? $options['cachetime'] : self::DEFAULT_CACHE_TIME;
        $this->return_type = empty($options['return_type']) ? 'array' : $options['return_type'];

        //set up caching if needed
        $this->setCacheTime($cachetime);
        $this->setCache($cache);

        //build the conditions for the query
        $where = '';
        if(!empty($options['conditions'])) {
            $conditions = $this->buildConditions($options['conditions'],$this->getTableSchema($options['tables']));
            if($conditions != '') {
                $where = " WHERE ".$conditions;
            }
        }

        //set up the order
        $order = null;
        if(!empty($options['order'])) {
            if(is_array($options['order'])) {
                $order = " ORDER BY ".implode(", ",$options['order']);
            }
            else {
                $order = " ORDER BY ".$options['order'];
            }
        }

        //set up the limiting fields
        $fields = "*";
        if(!empty($options['fields'])) {
            if(is_array($options['fields'])) {
                $fields = implode(", ",$options['fields']);
            }
            else {
                $fields = $options['fields'];
            }
        }

        //build the group clauses
        $group = null;
        if(!empty($options['group'])) {
            if(is_array($options['group'])) {
                $group = " GROUP BY ".implode(", ",$options['group']);
            }
            else {
                $group = " GROUP BY ".$options['group'];
            }
        }

        //build the from
        $tables = null;
        if(!empty($options['tables'])) {
            if(is_array($options['tables'])) {
                $from = " FROM ".implode(', ',$options['tables']);
            }
            else {
                $from = " FROM ".$options['tables'];
            }
        }

        //handle the joings.
        $join = null;
        if(!empty($options['join']) && is_array($options['join'])) {
            $joins = array();
            foreach($options['join'] as $table=>$joinBase) {
                if(is_array($joinBase)) {
                    //very specific join
                    foreach($joinBase as $key=>$value) {
                        switch($key) {
                            case 'type':
                                break;
                            case 'foreignKey':
                                break;
                            case 'field':
                                break;
                        }
                    }
                    $joins[] = strtoupper($joinBase['type']).' JOIN '.$table.' ON '.$joinBase['field'].' = '.$joinBase['foreignKey'];
                }
                else {
                    //explicit join
                    $joins[] = $joinBase;
                }
            }
            $join = implode(' ',$joins);
        }
        //now, build the final query based on the type, and execute it.
        switch($action) {
            case "first":
                $limit = " LIMIT 1 ";
                $sql = "SELECT $fields $from $join $where $group $order $limit";
                return $this->queryCache($sql,'fetch');
                break;
            case "all":
                $sql = "SELECT $fields $from $join $where $group $order";
                if(!empty($limit)) {
                    if(!empty($page)) {
                        $offset = $page*$limit-1;
                        $sql .= " LIMIT $limit OFFSET $offset";
                    }
                    else {
                        $sql .= " LIMIT $limit ";
                    }
                }
                return $this->queryCache($sql,'fetchAll');
                break;
            case "list":
                $fields = $options['key'].", ".$options['name'];
                if(!empty($options['key']) && !empty($options['name'])) {
                    //build the list query
                    $sql = "SELECT $fields $from $join $where $group $order";
                    if(!empty($limit)) {
                        if(!empty($page)) {
                            $offset = $page*$limit-1;
                            $sql .= " LIMIT $limit OFFSET $offset";
                        }
                        else {
                            $sql .= " LIMIT $limit ";
                        }
                    }
                    return $this->queryCache($sql,'fetchAll');
                }
                else {
                    $this->error = "Invalid list query: Missing key or name parameter";
                }
                break;
            case "count":
                $fields = " COUNT(*) as total_count ";
                $sql = "SELECT $fields $from $join $where $group";
                $result = $this->queryCache($sql,'fetch');
                return $result['total_count'];
                break;
        }
    }

    /**
     * getTableSchema()
     * @author Brandon Carlson <brandon@aphion.com>
     * @param String $table - name of table to get schema data
     * @return Array - Array of column definitions
     */
    private function getTableSchema($tables)
    {
        $data = array();
        $tables_to_check = array();
        if(!is_array($tables)) {
            //force the string to an array
            $tables_to_check[] = $tables;
        }
        else {
            $tables_to_check = $tables;
        }
        foreach($tables_to_check as $table) {
            $schema_data = \Custom_Memcached::getInstance()->get('DATABASE_TABLE_SCHEMA_'.$table);
            if(!$schema_data || 2>1) {
                //no memcache key, query the db, and dump to memcache
                $sql = 'SHOW COLUMNS FROM '.$table;
                $schema_data = $this->query($sql)->fetchAll();
                \Custom_Memcached::getInstance()->set('DATABASE_TABLE_SCHEMA_'.$table,$schema_data,0,1800);
            }
            else {

            }
            $data[] = array(
                'name'=>$table,
                'schema'=>$schema_data
            );
            //$data[$table] = $schema_data;
        }
        $this->setSchema($data);
        return $data;
    }




}
