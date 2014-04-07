<?php
namespace Classes\Utilities\Database;

/**
 * Class Database
 * @package Classes\Utilities\Database
 * @author Brandon Carlson <brandon@aphion.com>
 * @description An abstracted database object
 */
class Database
{

    //properties
    private $adapter = '';
    private static $Instance = null;
    public $DAO;

    /**
     * __construct()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description constructor method
     */
    public function __construct()
    {
        if(!defined('CONFIG_LOADED')) {
            throw new \Exception('Configuration Not Loaded');
        }
        else {
            //we have a configuration loaded, try to get the adapter we want.
            $this->useAdapter(DATABASE_DRIVER);
        }
    }

    /**
     * __destruct()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Clean up
     */
    public function __destruct()
    {
        //close the connection
        $this->DAO->disconnect();
    }

    /**
     * Get instance
     * @return Database instance
     */
    public static function getInstance()
    {
        if (!self::$Instance instanceof Database)  {
            self::$Instance = new Database();

        }

        return self::$Instance->DAO;
    }

    /**
     * setAdapter()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Sets adapter property
     * @param String $adapter - name of the adapter we want
     */
    private function setAdapter($adapter)
    {
        $this->adapter = $adapter;
    }


    /**
     * getAdapter()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Gets adapter property
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * load()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Loads the current adapter to the objects DAO property
     */
    private function load()
    {
        if($this->getAdapter()!='') {
            $interface_file = dirname(__FILE__)."/Interfaces/DatabaseInterface.php";
            $adaptor_file = dirname(__FILE__)."/Adapters/".ucfirst($this->getAdapter())."Adapter.php";

            if(file_exists($interface_file) && file_exists($adaptor_file)) {
                //require $interface_file;
                //require $adaptor_file;
                $this->loadAdapter(ucfirst($this->getAdapter())."Adapter");
                $this->DAO->connect();
            }
            else {
                throw new \Exception('Invalid or missing Database Adaptor');
            }
        }
        else {
            throw new \Exception('No Database Adaptor configured');
        }
    }

    /**
     * useAdapter()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Changes the adapter
     * @param String $adapter - name of the adapter we want to use
     */
    public function useAdapter($adapter)
    {
        $this->setAdapter($adapter);
        $this->load();
    }

    /**
     * loadAdapter()
     * @author Brandon Carlson <brandon@aphion.com>
     * @description Loads the given adapter Class and assigns it to the DAO property
     * @param String $adapter - Name of the Adapter Class
     */
    private function loadAdapter($adapter)
    {
        $class = "\\Classes\\Utilities\\Database\\Adapters\\". $adapter;
        $this->DAO = new $class();
    }
}