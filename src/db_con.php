<?php

class DBManager
{
    /* Tables Information */
    const EODB_SERVICE = 'sp_custom.rtps_eodb_intgr_services';
    const APPLICATION_TABLE = 'sp_custom.application_processing_json';
    const ID_LABEL_MAPPINGS_TABLE = 'sp_custom.id_label_mappings';
    const ATTRIBUTE_MAST_TABLE = 'schm_sp.appl_attribute_mast';
    const TRACK_TABLE = 'sp_custom.rtps_track_table';


    /* DBs  */
    const RTPS_PROD = 'rtps_prod';
    const EODB_PROD = 'eodb_prod';
    const RTPS_CONFIGURE = 'rtps_preprod';    //rtps_prepod
    const EODB_CONFIGURE = 'eodb_preprod';    //eodb_prepod


    /* Postgress Credentials */
    private $pg_host = '10.194.162.120';
    private $db_name = 'rtps_prod';
    private $db_name_eodb = 'eodb_prod';
    private $username = 'serviceplusrole';
    private $password = 'Artps@p05tgres';
    private $pg_port = '5432';


    /* MongoDB Credentials */
    private $mongo_host = null;
    private $mongo_port = null;


    // RTPS database connection
    public function get_postgres_connection()
    {
        $dsn = "pgsql:host=$this->pg_host;port=$this->pg_port;dbname=$this->db_name;";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $this->username, $this->password, $options);
        } catch (\PDOException $ex) {
            echo $ex->getMessage();
            exit();
        }
    }

    // EODB database connection
    public function get_postgres_connection_eodb()
    {
        $dsn = "pgsql:host=$this->pg_host;port=$this->pg_port;dbname=$this->db_name_eodb;";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $this->username, $this->password, $options);
        } catch (\PDOException $ex) {
            echo $ex->getMessage();
            exit();
        }
    }

    public function get_mongo_connection()
    {

        $this->mongo_host = getenv('MONGO_HOST', true);
        $this->mongo_port = getenv('MONGO_PORT', true);

        try {

            return new \MongoDB\Client("mongodb://{$this->mongo_host}:{$this->mongo_port}");
        } catch (\Exception $ex) {
            echo $ex->getMessage();
            exit();
        }
    }


    public function get_postgres_connection_new($db_name = '')
    {
        $dsn = "pgsql:host=$this->pg_host;port=$this->pg_port;dbname=$db_name;";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $this->username, $this->password, $options);
        } catch (\PDOException $ex) {
            echo $ex->getMessage();
            exit();
        }
    }
}
