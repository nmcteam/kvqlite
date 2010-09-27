<?php
define("DATABASE", 'database.sqlite');

// Collect input from CLI or web server
if($argc > 0) {
    if($argc === 3) {
        list($script, $method, $key) = $argv;
        $value = null;
    } else if ($argc === 4) {
        list($script, $method, $key, $value) = $argv;
    } else {
        die("Usage: php $file [method=get|post] [key] [json if post]\n");
    }
} else {
    $script = $_SERVER['PHP_SELF'];
    $dir = dirname($script);
    $regex = "!^$dir|$script!";
    $key = preg_replace($regex,'',$_SERVER['REQUEST_URI']); 
    $method = $_SERVER['REQUEST_METHOD'];
    $value = file_get_contents('php://input');
}

$kvqlite = new KVQLite(DATABASE);
switch ($method) {
    case 'GET': case 'get':
        $result = $kvqlite->get($key);
        break;
    case 'POST': case 'post':
        $result = $kvqlite->post($key,$value);
        break;
    default:
        die("$method not yet supported.");
}

if(!isset($result)) {
    header('HTTP/1.0 404 Not Found');
    echo "Not found.\n";
} else {
    header('HTTP/1.1 200 OK');
    header('Content-Type: application/json');
    echo $result;
    echo "\n";
}
die;

// Utility Class
class KVQLite {

    public $_pdo;

    public $_table = 'kvqlite';

    function __construct($database) {
        $this->_pdo = new PDO("sqlite:$database");
        $this->_ensureTableExists();
    }

    function _ensureTableExists() {
        $createTable = 
            "CREATE TABLE IF NOT EXISTS \"$this->_table\" (
                \"key\" VARCHAR PRIMARY KEY NOT NULL UNIQUE,
                \"value\" TEXT 
            )";
        $this->_pdo->query($createTable);
    }

    function get($key) {
       $key = preg_replace('/\*/','%',$key);
       $query = "SELECT key, value FROM \"$this->_table\" WHERE key LIKE :key";
       $statement = $this->_pdo->prepare($query);
       $statement->execute(array(':key'=>$key));
       $results = $statement->fetchAll(PDO::FETCH_CLASS,get_class((object)array()));
       
       $count = count($results);
       if($count === 1) {
           return $results[0]->value;
       } else if($count > 1) {
           $keyed = array();
           foreach($results as $result) {
               $keyed[$result->key] = json_decode($result->value);
           }
           return json_encode($keyed);
       }
       return null; 
    }

    function post($key, $value) {
        $query = "INSERT OR REPLACE INTO \"$this->_table\" (key,value) VALUES (:key,:value);";
        $statement = $this->_pdo->prepare($query);
        $statement->execute(array(':key'=>$key,':value'=>$value));
        return $this->get($key);
    }

}
