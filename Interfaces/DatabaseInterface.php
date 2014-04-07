<?php

namespace Classes\Utilities\Database\Interfaces;
/**
 * Class DatabaseInterface
 * @package Classes\Utilities\Database\Interfaces
 * @author Brandon Carlson <brandon@aphion.com>
 * @description Database Interface methods
 */

interface DatabaseInterface
{
    function connect($env = '');
     
    function disconnect();
     
    function query($query);
     
    function fetch();
    
    function fetchAll();

    function delete($table, $options);
     
    function getInsertId();
     
    function countRows();
     
    function getAffectedRows();

    function find($action, $options);

    function save($table, $options);

    function update($table, $options);

    function insert($table, $options);
}
