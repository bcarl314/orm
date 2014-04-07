<?php

namespace Classes\Utilities\Database;
/**
 * Class DataMapper
 * @package Classes\Utilities\Database
 * @author Brandon Carlson <brandon@aphion.com>
 * @description Generic DataMapper class to enable full DAO access to any class.
 * @version 1.0
 */
class DataMapper
{
    //Set up some properties
    public $_DAO = null;
    private $table_name = '';
    public $error = '';
    public $belongsTo = array();

    /**
     * __construct
     * @description Generic Constructor
     * @param Object $DAO The Data Access Object
     */
    public function __construct($DAO = null) {
        $this->setDAO($DAO);
    }

    /**
     * setDAO
     * @description Sets the DAO for the DataMapper
     * @param Object $DAO The Data Access Object
     */
    public function setDAO($DAO) {
        if(is_object($DAO)) {
            $this->_DAO = $DAO;
        }
        else {
            //use the default Database Object
            $this->_DAO = Database::getInstance();
        }

    }
    /**
     * setTable()
     * @description Sets the table for the DM object.
     * @param $table
     */
    public function setTable($table) {
        $this->table_name = $table;
    }

    /**
     * save
     * @description Invoked the DAO save method.
     * @param Array $options Options per the DAO documentation
     * @return bool
     */
    public function save($options) {
        if(!$this->_DAO->save($this->table_name,$options)) {
            $this->error = $this->_DAO->error;
            return false;
        }
        return true;
    }

    /**
     * query
     * @description Runs a direct query with the DAO.
     * @param String $sql SQL / Query to execute
     * @return bool
     */
    public function query($sql) {
        if(!$this->_DAO->query($sql)) {
            $this->error = $this->_DAO->error;
            return false;
        }
        return true;
    }
    /**
     * update
     * @description Invoked the DAO update method.
     * @param Array $options Options per the DAO documentation
     * @return boolean
     */
    public function update($options) {
        if(!$this->_DAO->update($this->table_name,$options)) {
            $this->error = $this->_DAO->error;
            return false;
        }
        return true;
    }

    /**
     * insert
     * @description Invoked the DAO insert method.
     * @param Array $options Options per the DAO documentation
     * @return boolean
     */
    public function insert($options) {
        if(!$this->_DAO->insert($this->table_name,$options)) {
            $this->error = $this->_DAO->error;
            return false;
        }
        return true;
    }

    /**
     * delete
     * @description Invoked the DAO delete method.
     * @param Array $options Options per the DAO documentation
     * @return boolean
     */
    public function delete($options) {
        if(!$this->_DAO->delete($this->table_name,$options)) {
            $this->error = $this->_DAO->error;
            return false;
        }
        return true;
    }

    /**
     * @param $type
     * @description Invoked the DAO find method.
     * @param String $type [all|first|list|count]
     * @param Array $options Options per the DAO documentation
     * @return mixed
     */
    public function find($type,$options=array()) {
        $options['tables'] = $this->table_name;
        if(isset($this->belongsTo) && is_array($this->belongsTo) && count($this->belongsTo)>0) {
            $options['join'] = $this->belongsTo;
        }
        return $this->_DAO->find($type,$options);
    }

    /**
     * changeDatabase
     * @description Changes connection for the _DAO object.
     * @param string $db Database to connect to.
     */
    public function changeDatabase($db = '') {
        $this->_DAO->connect($db);
    }

    /**
     * getInsertId
     * @description Returns the last insert id from a query
     */
    public function getInsertId() {
        return $this->_DAO->getInsertId();
    }

    /*
     * setBelongsTo
     * @description Sets any associations we may want which will be used in finder queries.
     */
    public function setBelongsTo($data) {
        $this->belongsTo = $data;
    }
}