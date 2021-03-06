<?php
//Wrapper to abstract the method of database access
//(currently implented with PDO)
class Sequel {
    private $DB;
    
    function __construct(array $fig = array()) {
        $this->DB = $fig['connection'];
    }

    function select($query, array $values = array()) {
        $statement = "SELECT $query";
        $Results = $this->DB->prepare($statement);
        $Results->execute($values);
        return new Sequel_Results(array(
            "results" => $Results,
            "statement" => $statement,
            "values" => $values,
            "connection" => $this->DB
        ));
    }

    function insert($query, array $values = array()) {
        $this->DB->prepare("INSERT INTO $query")->execute($values);
        return $this->DB->lastInsertId();
    }
    
    function update($query, array $values = array()) {
        $this->DB->prepare("UPDATE $query")->execute($values);
    }

    function delete($query, array $values = array()) {
        $this->DB->prepare("DELETE FROM $query")->execute($values);
    }
}


//Results Set Wrapper returned by calls to select
class Sequel_Results implements Iterator {
    private $Results,
            $DB,
            $predicate,
            $values,
            $count = null,
            $key = -1,
            $current;

    function __construct(array $fig = array()) {
        $this->Results = $fig['results'];
        $this->predicate = $this->extract_select_predicate($fig['statement']);
        $this->values = $fig['values'];
        $this->DB = $fig['connection'];

        $this->Results->setFetchMode(PDO::FETCH_ASSOC);
        $this->next();
    }

    private function extract_select_predicate($query) {
        return substr($query, strpos($query, "FROM"));
    }

    function to_array() {
        $arrayResults = array();
        while($row = $this->next()) {
            $arrayResults[] = $row;
        }
        return $arrayResults;
    }

    //rowCount doesnt work for sqlite :(
    function count() {
        if($this->count === null) {
            $sql= "SELECT count(*) " . $this->predicate;
            $sth = $this->DB->prepare($sql);
            $sth->execute($this->values);
            $rows = $sth->fetch(\PDO::FETCH_NUM);
            $this->count = $rows[0];
        }
        return $this->count;
    }

    function rewind() {
        if($this->key !== 0) {
            throw new Exception("Sequel_Results does not support rewind.");
        }
    }
  
    function valid() {
        return ($this->current !== false);
    }

    function current() {
        return $this->current;
    }

    function key() {
        return $this->key;
    }

    function next() {
        $this->key += 1;
        $hold = $this->current;
        $this->current = $this->Results->fetch();
        return $hold;
    }
}
?>
