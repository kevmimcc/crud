<?php
require_once('mysql.class.php');
// makes $pdo available and connected to database

class CRUD {
	public $table;
	public $index;
	public $order;
	public $child_tables;
	public $parent_tables;
	public $delete_orphans;
	public $parent_classes;
	public $child_classes;
        public function __construct($new_db=array()){
		$database = 'database';
		$user = 'user';
		$password = 'password';
		if($new_db){
                	$db = new DB($new_db[0], $new_db[1], $new_db[2]);
		}
		else {
                	$db = new DB($database, $user, $password);
		}
		if(!$this->order){$this->order = $this->index;}
        }
        public function all($ignore_parents=0){
                global $pdo;
		$qry = "SELECT * FROM $this->table";
		foreach($this->parent_classes as $class => $key){
			if(!$ignore_parents){
				$parent_class = new $class;
				$parent_table = $parent_class->table;
				$parent_key = $parent_class->child_classes[get_class($this)];
				$qry .= " LEFT JOIN $parent_table ON $this->table.$key = $parent_table.$parent_key";
			}
		}
		$qry .= " ORDER BY $this->order";
                $result = $pdo->query($qry);
                $result_set = $result->fetchAll(PDO::FETCH_ASSOC);
		return $result_set;
        }
        public function find_by($column, $needle){
                global $pdo;
                $result = $pdo->query("SELECT * FROM $this->table WHERE $column = '$needle' ORDER BY $this->order");
                return $result->fetchAll(PDO::FETCH_ASSOC);
        }
	public function find($id){
		$set = $this->find_by($this->index, $id);
		return reset($set);
	}
	public function where($column, $needle){
		return $this->find_by($column, $needle);
	}
        public function update($id, $data){
                global $pdo;
                $qry = "UPDATE $this->table SET ";
                foreach($data as $key=>$value){
                        $qrys[] = "$key = '$value'";
                }
                $sub_query = implode(", ", $qrys);
                $qry.= $sub_query;
                $qry.= " WHERE $this->index = '$id' LIMIT 1";
		$this->console_log($qry);
                $result = $pdo->query($qry);
                if($result){
                        return $this->find_by($this->index, $id);
                }
                else{
                        $this->console_log("Error updating.");
                        return 0;
                }
        }
        public function insert($data){
                global $pdo;
                $qry = "INSERT INTO $this->table ";
                foreach($data as $key=>$value){
                        $keys[]=$key;
                        $values[]="'$value'";
                }
                $keys_string = implode(', ', $keys);
                $values_string = implode(', ', $values);
                $qry.= " (".$keys_string.")";
                $qry.= " VALUES (".$values_string.")";
                $result = $pdo->query($qry);
                return $this->check_and_return($result);
        }
        public function delete($id){
                global $pdo;
                $qry = "DELETE FROM $this->table WHERE $this->index = '$id' LIMIT 1";
                $result = $pdo->query($qry);
		if($result){
			if($this->delete_orphans){
				$response = $this->delete_child_records($id);
				if(!$response['Errors']){
					return true;
				}
				else{
					//There were errors
					echo "ERROR: Failed to delete all children, but did delete: ";
					print_r($response['Deleted']);
				}
			}
			else{
				return true;
			}
		}
		else{
			$this->console_log("Error trying to delete.");
			return 0;
		}
        }
        private function check_and_return($result){
                global $pdo;
                if($result){
                        $last_id = $pdo->lastInsertId();
                        return $this->find($last_id);
                }
                else {
                        return 0;
                }
        }
        private function console_log($messagex){
                $message = str_replace("'", "", $messagex);
                echo '<script type="text/javascript">';
                echo "console.error('No dog.', '$message');";
                echo '</script>';
        }
        public function children($class, $id){
                $children = new $class();
		$foreign_key = $this->child_classes[$class];
                $result = $children->find_by($foreign_key, $id);
                return $result;
        }
	public function child_tables(){
		foreach($this->child_classes as $class){
			$Class = new $class;
			$tables[] = $Class->table;
		}
		return $tables;
	}
	private function delete_child_records($id){
		//on delete, if delete_orphans, call this
		$errors=0;
		$deleted=array();
		foreach($this->child_classes as $class=>$foreign_key){
			$child_records = $this->children($class, $id);
			if($child_records){
				$Child = new $class;
				foreach($child_records as $record){
					$result = $Child->delete($record[$Child->index]);
					if($result){
						$deleted[$class][] = $record[$Child->index];
					}
					else {
						$errors++;
					}
				}
			}
		}
		return array("Deleted" => $deleted, "Errors" => $errors);
	}
	public function query($qry){
		global $pdo;
		$result = $pdo->query($qry);
		if($result){
			return $result->fetchAll(PDO::FETCH_ASSOC);	
		}
		else {
			return 0;
		}
	}

}

class Agent extends CRUD {
	public function __construct(){
		$this->table = 'Users';
		$this->index = 'rowID';
		$this->order = 'Username';
		$this->child_classes = array("Note"=>"agent_id");
		$this->parent_classes = array("Office"=>"office_id");
		$this->delete_orphans = false;
		parent::__construct();
	}
}

class Note extends CRUD {
	public function __construct(){
		$this->table = 'notes';
		$this->index = 'note_id';
		$this->order = 'date';
		$this->parent_classes = array("Agent"=>"agent_id");
		parent::__construct();
	}
}

class AgentLog extends CRUD {
	public function __construct(){
		$this->table = 'AgentLogs';
		$this->index = 'rowID';
		parent::__construct();
	}
}

class Office extends CRUD {
	public function __construct(){
		$this->table = 'offices';
		$this->index = 'office_id';
		$this->child_classes = array("Agent"=>"office_id");
		parent::__construct();
	}
}

?>

