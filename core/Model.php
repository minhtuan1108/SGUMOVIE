<?php
namespace core;

use app\utils\Database;
use PDO;

abstract class Model implements ICurdData {
    // Property must re-define for child class if it needs
    protected static string $tableName;
    protected static string $className;
    protected static string $namespace = "app\model\\";
    protected static $primaryKey = array("id");
    protected static bool $isAutoGenerated = true;

    //Property constant to define the way to get/find data with attribute isdeleted
    const UN_DELETED_OBJ = 1; //Don't get object which has isdeleted = false
    const ALL_OBJ = 2; //get all object ignore isdeleted attribute
    const ONLY_DELETED_OBJ = 3; // get object which has isdeleted = true

    // Return object $className
    public static function find($option = 1, ...$ids)
    {
        //Check if number of primary key < number of input id
        if(count(static::$primaryKey) < count($ids)){
            // echo "Số lượng id nhiều hơn số lượng khóa chính của bảng " .static::$tableName ."(PK: ".implode(", ",static::$primaryKey).")";
            return;
        }

        $conn = Database::getConnection();
        $containerArr = self::getWhereClauseAndRefferenceArray($ids);
        $whereClause = $containerArr["whereClause"];
        $arr = $containerArr['refArray'];

        $whereClause = self::handleWhereClause($option, $whereClause);
        $sql = "SELECT * FROM " .static::$tableName. " WHERE ".$whereClause;
        // echo $sql ."\n";
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::getClassName());
        $stmt->execute($arr);
        return $stmt->fetch();
    }

    // Return array if it success else return bool
    public static function findAll($option): array|bool
    {
        $conn = Database::getConnection();
        $whereClause = self::handleWhereClause($option, '');
        $sql = "SELECT * FROM " .static::$tableName .' WHERE ' .$whereClause;
//        echo $sql;
        // return true;
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::getClassName());
        $stmt->execute();
        return $stmt->fetchAll();
    }

    protected static function handleWhereClause($option, $whereClause):string{

        $clause = $whereClause;
        //Check if isdeleted exist
        if(property_exists(self::getClassName(), 'isDeleted')){

            //check if where clause != ''
            if($whereClause != ''){
                $whereClause .= ' and ';
            }

            //Check option to find object
            switch ($option) {
                case 1:
                    $clause = $whereClause .'isDeleted = false';
                    break;

                case 2:
                    $clause = $whereClause .'1';   
                    break; 

                case 3:
                    $clause = $whereClause .'isDeleted = true';
                    break;

                default:
                    break;
            }
        }
        else {
            if ($whereClause == ""){
                $clause = " 1";
            }
        }
        return $clause;
    }

    // Return index > 0 if insert successfully and 0 for primary key's type is string
    public static function save($object): int
    {
        $conn = Database::getConnection();
        $arr = self::reWritePrimaryToNULL(get_object_vars($object));
        $additionSQL = "";
        foreach ($arr as $key => $val){
            $additionSQL .= ":$key,";
        }
        $additionSQL = rtrim($additionSQL, ",");
        $sql = "INSERT INTO " .static::$tableName. " VALUES ($additionSQL)";
        // echo $sql ."\n";
        // echo json_encode($arr);
//        var_dump($arr);
        $stmt = $conn->prepare($sql);
        if ($stmt->execute($arr) == true){
            return $conn->lastInsertId();
        }
        return -1;
    }

    // Return true if success else false
    public static function update($object, ...$ids): bool
    {
        //Check if number of primary key < number of input id
        if(count(static::$primaryKey) < count($ids)){
            // echo "Số lượng id nhiều hơn số lượng khóa chính của bảng " .static::$tableName ."(PK: ".implode(", ",static::$primaryKey).")";
            return false;
        }

        $conn = Database::getConnection();
        $arr = self::removePrimaryKey2(get_object_vars($object));
        $additionSQL = "";
        $containerArr = self::getWhereClauseAndRefferenceArray($ids);
        $whereClause = $containerArr['whereClause'];
        foreach ($arr as $key => $val){
            $additionSQL .= " $key = :$key,";
        }
        $additionSQL = rtrim($additionSQL, ",");
        $sql = "UPDATE " .static::$tableName. " SET $additionSQL WHERE ".$whereClause;
        $arr = array_merge($arr, $containerArr['refArray']);
        // echo "\n".json_encode($arr);
        // echo "\n". $sql;
        // return true;
        $stmt = $conn->prepare($sql);
        return $stmt->execute($arr);
    }

    public static function delete($softDelete = true, ...$ids): bool
    {
        //Check if number of primary key < number of input id
        if(count(static::$primaryKey) < count($ids)){
            // echo "Số lượng id nhiều hơn số lượng khóa chính của bảng " .static::$tableName ."(PK: ".implode(", ",static::$primaryKey).")";
            return false;
        }

        //Check property isDeleted exist
        if(property_exists(self::getClassName(), 'isDeleted') && $softDelete == true){
            return self::softDelete();
        }

        $conn = Database::getConnection();
        $containerArr = self::getWhereClauseAndRefferenceArray($ids);
        $whereClause = $containerArr["whereClause"];
        $arr = $containerArr['refArray'];
        $sql = "DELETE FROM " .static::$tableName. " WHERE ". $whereClause;
        // echo $sql;
        // return true;
        $stmt = $conn->prepare($sql);
        return $stmt->execute($arr);
    }

    protected static function softDelete(...$ids): bool
    {
        //Check if number of primary key < number of input id
        if(count(static::$primaryKey) < count($ids)){
            // echo "Số lượng id nhiều hơn số lượng khóa chính của bảng " .static::$tableName ."(PK: ".implode(", ",static::$primaryKey).")";
            return false;
        }

        // $conn = Database::getConnection();
        $containerArr = self::getWhereClauseAndRefferenceArray($ids);
        $whereClause = $containerArr["whereClause"];
        $arr = $containerArr['refArray'];
        $sql = "UPDATE " .static::$tableName. " SET isDeleted = true WHERE ". $whereClause;
//        echo $sql;
        return true;
        // $stmt = $conn->prepare($sql);
        // return $stmt->execute($arr);
    }
    public static function where(string $whereClause, array $parameters = []): bool|array
    {
        $conn = Database::getConnection();
        if ($whereClause == "")
            return self::findAll(self::ALL_OBJ);
        $sql = "SELECT * FROM " .static::$tableName . " WHERE " .$whereClause;
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::getClassName());
        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    public static function query(string $sql, array $parameters = []): bool|array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::getClassName());
        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    protected static function reWritePrimaryToNULL($objectArr){
        //check object has 1 or more primary key
        if(static::$isAutoGenerated == true){
            // echo "This object has 1 primary";
            //Object has 1 primary key, set value for it by null
            foreach ($objectArr as $key => $value){
                if ($key == static::$primaryKey[0]){
                    $objectArr[$key] = NULL;
                    return $objectArr;
                }
            }
        }
        // echo "This object has more than 1 primary key";
        //Object has more than 1 primary key, keep value of attributes
        return $objectArr;
        
        
    }

    protected static function removePrimaryKey($objectArr){
        foreach ($objectArr as $key => $value){
            if ($key == static::$primaryKey[0]){
                unset($objectArr[$key]);
                return $objectArr;
            }
        }
        return null;
    }

    //This is version 2 of remove primary function
    protected static function removePrimaryKey2($objectArr){
        $numOfPriKey = count(static::$primaryKey);
        $i = 0;
        $countUnsetKey = 0;
        foreach ($objectArr as $key => $value){
            $i = 0;
            while ($i < $numOfPriKey) {
                if($key == static::$primaryKey[$i]){
                    unset($objectArr[$key]);
                    $countUnsetKey++;
                }
                $i++;
            }

            //Check amount of key were unset
            if($countUnsetKey >= $numOfPriKey){
                return $objectArr;
            }
        }
        return null;
    }

    protected static function getClassName(): string
    {
        return static::$namespace.static::$className;
    }

    public function hasList($class, $options = Model::UN_DELETED_OBJ): bool|array
    {
        $conn = Database::getConnection();
        $arrIds = [];
        foreach (static::$primaryKey as $key){
            $arrIds[] = $this->$key;
        }
        $containerArr = $this->getWhereClauseAndRefferenceArray($arrIds);

//        $sql = "SELECT * FROM " .$class::$tableName . " WHERE " .$containerArr["whereClause"];
//        $stmt = $conn->prepare($sql);
//        $stmt->setFetchMode(PDO::FETCH_CLASS, $class::getClassName());
//        $stmt->execute($containerArr["refArray"]);
//        return $stmt->fetchAll();

        return $class::where($class::handleWhereClause($options, $containerArr["whereClause"]), $containerArr["refArray"], $options);
    }


    public function belongTo($options, $class){
        $primaryKey = static::$primaryKey;
        return $class::find($options, ...$this->$primaryKey);
    }

    public static function getWhereClauseAndRefferenceArray($arrID){
        $additionSQL = "";
        $countKey = 0;
        $primaryKey = "";
        $arr = array();
        foreach($arrID as $value){
            $primaryKey = static::$primaryKey[$countKey];
            if(!empty($value)){
                $additionSQL .=  $primaryKey." = :".$primaryKey." and ";
            }
            $arr[$primaryKey] = $value;
            $countKey++;
        }

        $additionSQL = rtrim($additionSQL, "and ");
        return array('whereClause' => $additionSQL, 'refArray' => $arr);
    }
}

