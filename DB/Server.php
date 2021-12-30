<?php
	namespace Me\Korolevsky\Api\DB;

	use mysqli;

	class Server {

		private mysqli $connect;
		private string $dbname;

		/**
		 * Server constructor.
		 *
		 * @param string $host
		 * @param string $user
		 * @param string $password
		 * @param string $database
		 * @param int $port
		 * @param string $charset
		 */
		public function __construct(string $host, string $user, string $password,
		                            string $database, int $port = 3306, string $charset = "utf8mb4") {
			$this->connect = @new mysqli($host, $user, $password, $database, $port);
			$this->dbname = $database;
			if($this->isConnected()) {
				$this->connect->set_charset($charset);
			}
		}

		public function select(string $query, array $params = []): array|null {
			foreach($params as $key => $param) {
				if(is_array($param) || is_object($param)) {
					$param = json_encode((array) $param);
				} elseif($param === null) {
					$param = "NULL";
				}

				$param = $this->connect->real_escape_string($param);
				if(is_string($params[$key])) {
					$param = "'".$param."'";
				}

				$query = preg_replace('/\?/', $param, $query, 1);
			}

			$rows = $this->connect->query($query);
			$result = [];

			if(is_array($rows) || is_object($rows)) {
				foreach($rows as $row) {
					$result[] = $row;
				}
			}

			return $this->autoTypeConversion($result);
		}

		public function findOne(string $table, string $query = '', array $params = []): DBObject {
			$query = $this->select("SELECT * FROM `$table` $query", $params);

			return new DBObject($query[0] ?? [], $table);
		}

		public function count(string $table, string $query = '', array $params = []): int {
			$query = $this->select("SELECT COUNT(*) FROM `$table` $query", $params);

			return $query[0]['COUNT(*)'] ?? 0;
		}

		public function dispense(string $table): DBObject {
			return new DBObject([ 'id' => null ], $table);
		}

		public function store(DBObject &$object): DBObject|false {
			$table = $object->getInfo('table');

			$keys = array_keys($object->getArrayCopy());
			$values = array_values($object->getArrayCopy());

			foreach($values as $key => $value) {
				if(is_array($value) || is_object($value)) {
					$value = json_encode((array) $value);
				} elseif($value === null) {
					$value = "NULL";
				}

				$values[$key] = $this->connect->real_escape_string($value);
				if(is_string($value)) {
					$values[$key] = "'".$values[$key]."'";
				}
			}

			if($object['id'] != null) {
				$query = "UPDATE `$table` SET ";
				foreach($values as $key => $value) {
					$key = $keys[$key];
					if($key == 'id') continue;

					$query .= "`$key` = $value, ";
				}
				$query = mb_strcut($query, 0, -2);

				$id = $values[array_search('id', $keys)];
				$id = !is_numeric($id) ? "'$id'" : intval($id);

				$query .= " WHERE `$table`.`id` = $id";
				$result = $this->connect->query($query);
			} else {
				$query = "INSERT INTO `$table` (`".implode('`,`', $keys)."`) VALUES (".implode(',',$values).")";

				$result = $this->connect->query($query);
				$object->setInfo('id', $this->connect->insert_id);
			}

			$object['id'] = $object->getInfo('id');
			return !$result ? false : $object;
		}

		/**
		 * @return string
		 */
		public function getDbName(): string {
			return $this->dbname;
		}

		public function isConnected(): bool {
			return $this->connect->connect_error == null;
		}

		public function getErrorConnect(): string {
			if($this->connect->connect_error == null) {
				return "You are successfully connected!";
			}

			return $this->connect->connect_error;
		}

		/**
		 * Auto set type to var.
		 *
		 * @param array|null $array $array
		 * @return array|null
		 */
		private function autoTypeConversion(?array $array): ?array {
			if($array === []) return [];
			elseif($array == null) return null;

			foreach($array as $key => $val) {
				if(is_array($val) || is_object($val)) $array[$key] = $this->autoTypeConversion( (array) $val );
				elseif(is_bool($val)) $array[$key] = boolval($val);
				else {
					$val_num = str_replace(',', '.', $val);
					if(is_numeric($val_num)) {
						if(strstr($val_num, '.') !== false) $array[$key] = floatval($val);
						else $array[$key] = intval($val_num);
					}
				}
			}

			return $array;
		}
	}