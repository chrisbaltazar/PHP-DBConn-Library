<?php

namespace DBConn;

use http\Exception\UnexpectedValueException;

/**
 * Class DBConn
 * Easy and fast DB queries for everyone
 */
class DBConn {

	const RETURN_FORMAT_ARRAY = 'ARRAY';
	const JOIN_TYPE_INNER = 'INNER';
	const JOIN_TYPE_LEFT = 'LEFT';
	const JOIN_TYPE_RIGHT = 'RIGHT';

	private $_host;
	private $_user;
	private $_password;
	private $_name;
	private $_session;
	private $_debug;
	private $_timezone;
	private $_summertime;
	private $_charset;

	private $cn;
	private $id;
	private $rows;
	private $sql;
	private $fields;
	private $key;
	private $tablestamps;
	private $flags;
	private $tables;
	private $select;
	private $order;
	private $group;
	private $where;
	private $includeDeleted;
	private $ignore;
	private $with;
	private $rawWhere;


	/**
	 * DBConn constructor.
	 */
	public function __construct() {
		$this->_host       = defined( 'DB_HOST' ) ? DB_HOST : $_ENV['DB_HOST'] ?? null;
		$this->_user       = defined( 'DB_USER' ) ? DB_USER : $_ENV['DB_USER'] ?? null;
		$this->_password   = defined( 'DB_PWD' ) ? DB_PWD : $_ENV['DB_PWD'] ?? null;
		$this->_name       = defined( 'DB_NAME' ) ? DB_NAME : $_ENV['DB_NAME'] ?? null;
		$this->_debug      = defined( 'DB_DEBUG' ) ? DB_DEBUG : $_ENV['DB_DEBUG'] ?? false;
		$this->_session    = defined( 'SESSION_ID' ) ? SESSION_ID : $_ENV['SESSION_ID'] ?? null;
		$this->_timezone   = defined( 'TIME_ZONE' ) ? TIME_ZONE : $_ENV['TIME_ZONE'] ?? 'GMT+0';
		$this->_summertime = defined( 'SUMMER_TIME' ) ? SUMMER_TIME : $_ENV['SUMMER_TIME'] ?? true;
		$this->_charset    = defined( 'CHARSET' ) ? CHARSET : $_ENV['CHARSET'] ?? 'utf8';

		$this->init();
	}

	/**
	 * initialize class vars to use them
	 */
	private function init() {
		if ( ! $this->validateData() ) {
			throw new \UnexpectedValueException( 'Some of the db fields are missing' );
		}

		$this->tablestamps = [
			'updated_by' => $_SESSION[ $this->_session ] ?? null,
			'updated_at' => 'NOW()'
		];
		$this->flags       = [ 'active' ];

		$this->where          = [];
		$this->select         = [];
		$this->order          = [];
		$this->group          = [];
		$this->ignore         = [];
		$this->rawWhere       = [];
		$this->includeDeleted = false;
	}

	/**
	 * get the current time zone according with the summer schedule
	 *
	 * @return string
	 */
	private function getTimezone() {
		preg_match( '<GMT([+-]\d{1,2})>', $this->_timezone, $matches );
		if ( empty( $matches ) ) {
			throw new \UnexpectedValueException( 'Timezone value incorrect ' + $this->_timezone );
		}

		$timezone = $matches[1];
		if ( $this->_summertime ) {
			$year         = date( 'Y' );
			$now          = strtotime( date( 'Y-m-d' ) );
			$start_summer = strtotime( $year . '-03-31 next Sunday' );
			$end_summer   = strtotime( $year . '-11-01 last Sunday' );
			if ( $now >= $start_summer && $now < $end_summer ) {
				$timezone += 1;
			}
		}

		return $timezone . ':00';
	}

	/**
	 * set the session var name for current user
	 *
	 * @param string $session
	 *
	 * @return $this
	 */
	public function setSession( string $session ) {
		$this->_session = $session;

		return $this;
	}

	/**
	 * set the fields for stamps used for updates automatically on any table
	 *
	 * @param array $stamps
	 *
	 * @return $this
	 */
	public function setTablestamps( array $stamps ) {
		$this->tablestamps = $stamps;

		return $this;
	}

	/**
	 * set the fields used as flags for updates instead of deletes on any table
	 *
	 * @param array $flags
	 *
	 * @return $this
	 */
	public function setFlags( array $flags ) {
		$this->flags = $flags;

		return $this;
	}

	/**
	 * get the number of rows affected by the last operation
	 * @return int
	 */
	public function affectedRows() {
		return $this->rows ?? 0;
	}


	/**
	 * get the auto inserted ID on the last query
	 * @return int
	 */
	public function getInserted() {
		return $this->id ?? 0;
	}

	/**
	 * opens the database connection and get the active link
	 * @return false|\mysqli|resource
	 * @throws \Exception
	 */
	public function connect() {
		try {
			$this->cn = mysqli_connect(
				$this->_host,
				$this->_user,
				$this->_password,
				$this->_name
			);
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		return $this->cn;
	}

	/**
	 * Close the active connection
	 * @throws \Exception
	 */
	public function close() {
		try {
			mysqli_close( $this->cn );
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}
	}

	/**
	 * get the results from a query and return them as a list in ARRAY or OBJECT format
	 *
	 * @param string $sql
	 * @param string $format
	 *
	 * @return type
	 * @throws \Exception
	 */
	public function getArray( string $sql = '', string $format = self::RETURN_FORMAT_ARRAY ) {
		if ( ! $sql ) {
			$sql = $this->getSQL();
		}

		$ds     = $this->query( $sql );
		$result = array();
		try {
			switch ( $this->protocol ) {
				case "MYSQL":
					switch ( $format ) {
						case "ARRAY":
							$result = mysqli_fetch_all( $ds, "MYSQLI_ASSOC" );
							break;
						case "OBJECT":
							while ( $row = mysqli_fetch_object( $ds ) ) {
								$result[] = $row;
							}
							break;
					}
					break;
				case "SQLDRIVER":
					switch ( $format ) {
						case "ARRAY":
							while ( $r = sqlsrv_fetch_array( $ds, SQLSRV_FETCH_ASSOC ) ) {
								$result[] = $r;
							}
							break;
						case "OBJECT":
							while ( $r = sqlsrv_fetch_object( $ds ) ) {
								$result[] = $r;
							}
							break;
					}
					break;
			}
		} catch ( Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		return $this->getWiths( $result );
	}

	/**
	 * get the results from a query in JSON list format
	 *
	 * @param string $sql
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getJSON( string $sql = '' ) {
		if ( ! $sql ) {
			$sql = $this->getSQL();
		}

		return json_encode( $this->getArray( $sql ) );
	}

	/**
	 * get only one row from a query in OBJECT format
	 *
	 * @param string $sql
	 *
	 * @return object|null
	 * @throws \Exception
	 */
	public function getObject( string $sql = '' ) {
		if ( ! $sql ) {
			$sql = $this->getSQL();
		}

		$ds = $this->query( $sql );
		try {
			if ( mysqli_num_rows( $ds ) ) {
				return mysqli_fetch_object( $ds );
			}

			return null;
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}
	}

	/**
	 * get only the first value from a query
	 *
	 * @param string $sql
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	public function getOne( string $sql = '' ) {
		if ( ! $sql ) {
			$sql = $this->getSQL();
		}

		$ds = $this->query( $sql );
		try {
			if ( mysqli_num_rows( $ds ) ) {
				return current( mysqli_fetch_row( $ds ) );
			}

			return null;
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}
	}

	/**
	 * check existence of something into a table based on the condition given
	 *
	 * @param type $field
	 * @param type $table
	 * @param type $condition
	 *
	 * @return type
	 */
	public function exist( $field, $table, $condition ) {
		try {
			switch ( $this->protocol ) {
				case "MYSQL":
					$sql = "select IFNULL(" . $field . ", 0) from " . $table . " where " . $condition;
					$ds  = $this->query( $sql );
					$res = mysqli_fetch_row( $ds );
					break;
				case "SQLDRIVER":
					$sql = "select ISNULL(" . $field . "), NULL) from " . $table . ( $condition == "" ? "" : " where " . $condition );
					$ds  = $this->query( $sql );
					$res = sqlsrv_fetch_array( $ds );
					break;
			}
		} catch ( Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		return $res[0];
	}

	/**
	 * executes a query, only for insert, update, delete operations
	 *
	 * @param string $sql
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function execute( string $sql ) {
		$this->query( $sql );
		$this->close();

		return $this->affectedRows();
	}

	/**
	 * maps an array list, getting a plain value array or concated string
	 *
	 * @param type $data
	 * @param type $value
	 * @param type $implode
	 *
	 * @return type
	 */
	public function lists( $data, $value, $implode = false ) {
		$array = Array();
		foreach ( $data as $r ) {
			$array[] = $r[ $value ];
		}
		if ( $implode ) {
			return implode( ",", $array );
		}

		return $array;
	}

	/**
	 * save a element into the table, based on the WHERE condition for insert or update
	 *
	 * @param type $table
	 * @param type $data
	 * @param type $where
	 *
	 * @return type
	 */
	public function save( $table, $data, $where = array() ) {
		$this->getTable( $table );
		if ( array_filter( $where ) ) {
			$sql = "update $table set ";
		} else {
			$sql = "insert into $table set ";
		}
		$sql .= implode( ",", array_merge( $this->build( $data ), $this->getStamps() ) );
		if ( array_filter( $where ) ) {
			$sql .= " where " . $this->build( $where, " and " );
		}

		return $this->execute( $sql );
	}

	/**
	 * delete or update with the declared flags, some records based on the where conditions
	 *
	 * @param type $table
	 * @param type $where
	 *
	 * @return type
	 */
	public function delete( $table, $where ) {
		if ( $where ) {
			$this->getTable( $table );
			if ( $delete = $this->softDelete() ) {
				$sql = "update $table set " . implode( ", ", array_merge( $this->build( $delete ), $this->getStamps() ) );
			} else {
				$sql = "delete from $table";
			}
			$sql .= " where ";
			if ( is_numeric( $where ) ) {
				$sql .= $this->key->name . " = " . $where;
			} elseif ( array_filter( $where ) ) {
				$sql .= $this->build( $where, " and " );
			} else {
				$this->error( "Where format not valid" );
			}

			return $this->execute( $sql );
		} else {
			$this->error( "No Where Statement declared" );
		}
	}

	/**
	 * set the fields to be included on the select part for the query builder
	 *
	 * @param array $args
	 *
	 * @return $this
	 */
	public function select( ...$args ) {
		$this->select = $args;

		return $this;
	}

	/**
	 * set the main table to be the source for the query builder
	 *
	 * @param string $source
	 *
	 * @return $this
	 */
	public function from( string $source ) {
		$this->tables   = [];
		$this->with     = [];
		$this->tables[] = [ 'name' => $source ];

		return $this;
	}

	/**
	 * set one table to make a join with another in the tables array, pointing out the
	 * name of this table, the target table to join with an index reference,
	 *
	 * @param string $tableName
	 * @param int $targetTable
	 * @param string $join
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function join( string $tableName, int $targetTable = 0, string $join = self::JOIN_TYPE_INNER ) {
		if ( ! $this->tables[0] ) {
			$this->error( "No main source selected yet" );
		}

		if ( ! $this->tables[ $targetTable ] ) {
			$this->error( 'Target table out of range' );
		}

		$this->tables[] = [
			'name'   => $tableName,
			'target' => $targetTable,
			'join'   => $join
		];

		return $this;
	}

	public function on( string $tableField, string $foreignFieldOrOperator = '', string $justForeignField = '' ) {
		if ( ! count( $this->tables ) > 1 ) {
			$this->error( 'No join tables declared yet' );
		}

		$tableJoin = end( $this->tables );
		if ( isset( $tableJoin['relation'] ) ) {
			$this->error( 'ON method MUST be called right after making a JOIN' );
		}

		if ( empty( $foreignFieldOrOperator ) ) {
			$operator     = '=';
			$targetTable  = $this->getNormalizedTarget( $tableJoin['target'] );
			$foreignField = $targetTable . '_id';
		} else {
			$operators = [ '>=', '<=', '<>', '!=', '>', '<', '=', 'IS NULL' ];
			if ( in_array( trim( strtoupper( $foreignFieldOrOperator ) ), $operators ) ) {
				$operator     = $foreignFieldOrOperator;
				$foreignField = $justForeignField;
			} else {
				$operator     = '=';
				$foreignField = $foreignFieldOrOperator;
			}
		}
		$index = count( $this->tables ) - 1;

		return $this->setRelation( $index, $tableField, $operator, $foreignField );
	}

	/**
	 * set the order fields for the query builder
	 *
	 * @param type $params
	 *
	 * @return $this
	 */
	public function order( $params ) {
		if ( is_array( $params ) ) {
			$this->order = $params;
		} else {
			$this->order = array( $params );
		}

		return $this;
	}

	/**
	 * set the group fields for the query builer
	 *
	 * @param type $params
	 *
	 * @return $this
	 */
	public function group( $params ) {
		if ( is_array( $params ) ) {
			$this->group = $params;
		} else {
			$this->group = array( $params );
		}

		return $this;
	}

	/**
	 * set the group where conditions for the query builer
	 *
	 * @param type $params
	 *
	 * @return $this
	 */
	public function where( $params ) {
		if ( is_array( $params ) ) {
			$this->where = $params;
		} else {
			$this->where = array( $params );
		}

		return $this;
	}

	/**
	 * set the RAW special conditions for the query builer
	 *
	 * @param type $params
	 *
	 * @return $this
	 */
	public function whereRaw( $params ) {
		if ( is_array( $params ) ) {
			foreach ( $params as $i => $raw ) {
				$this->rawWhere[ $i ][] = $raw;
			}
		} else {
			$this->error( "RAW Statement must be an array" );
		}

		return $this;
	}

	/**
	 * include records marked as DELETED with the flags, to be included in query
	 *
	 * @param type $params
	 *
	 * @return $this
	 */
	public function withDeletes() {

		$this->includeDeleted = true;

		return $this;
	}

	/**
	 * set one relation with relative data that has to be extracted with the main query,
	 * attached as an ARRAY LIST on the results
	 *
	 * @param type $table
	 * @param type $foreign
	 * @param type $alias
	 * @param type $pluck
	 *
	 * @return $this
	 */
	public function withMany( $table, $foreign, $alias = "", $pluck = "" ) {
		$this->addWith( "MANY", $table, $foreign, $alias, $pluck );

		return $this;
	}

	/**
	 * set one relation with relative data that has to be extracted with the main query,
	 * attached as a SINGLE OBJECT on the results
	 *
	 * @param type $table
	 * @param type $foreign
	 * @param type $alias
	 * @param type $pluck
	 *
	 * @return $this
	 */
	public function withOne( $table, $foreign, $alias = "", $pluck = "" ) {
		$this->addWith( "ONE", $table, $foreign, $alias, $pluck );

		return $this;
	}


	/**
	 * Ignore the flags declared to reach all the records "DELETEDS" on any table
	 *
	 * @param type $params
	 *
	 * @return $this
	 */
	public function ignoreActive( $params ) {
		if ( is_array( $params ) ) {
			$this->ignore = $params;

			return $this;
		} else {
			$this->error( "Ignore statement must be an array" );
		}
	}

	/**
	 * Build and return all the query saved in the instance
	 * @return string
	 */
	public function getSQL() {
		var_dump( $this->tables );
		$sql = "";
		$sql .= $this->getSelect();
		$sql .= $this->getSource();
		$sql .= $this->getWhere();
		$sql .= $this->getGroup();
		$sql .= $this->getOrder();

		$this->init();

		return $sql;
	}

	/**
	 * receive a sql query ready to execute on the database and return the results
	 *
	 * @param string $sql
	 *
	 * @return bool|\mysqli_result
	 * @throws \Exception
	 */
	private function query( string $sql ) {
		$this->sql = $sql;
		$this->connect();
		try {
			mysqli_query( $this->cn, "SET NAMES '" . $this->_charset . "'" );
			mysqli_query( $this->cn, "SET time_zone = '" . $this->getTimezone() . "'" );
			$result     = mysqli_query( $this->cn, $sql );
			$this->rows = mysqli_affected_rows( $this->cn );
			$this->id   = mysqli_insert_id( $this->cn );
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		return $result ?? false;
	}

	private function addWith( $type, $table, $foreign, $alias, $pluck ) {
		if ( $this->tables ) {
			$this->with[ $type ][] = array(
				"table"   => $table,
				"foreign" => $foreign,
				"alias"   => $alias ? $alias : $table,
				"pluck"   => $pluck
			);
		} else {
			$this->error( "With statement needs a main source table selected" );
		}

		return $this;
	}

	private function getTable( $table ) {
		try {
			$meta         = new static();
			$columns      = mysqli_fetch_all( $this->query( "SHOW COLUMNS FROM $table" ), MYSQLI_ASSOC );
			$this->fields = $this->lists( $columns, "Field" );
			$meta->fields = $this->fields;

			$fn        = function ( $item ) {
				return ( $item['Key'] == "PRI" );
			};
			$this->key = new static();
			if ( $k = array_filter( $columns, $fn ) ) {
				$this->key->name = $k[0]['Field'];
				$this->key->auto = substr_count( $k[0]['Extra'], "auto_increment" );
			}
			$meta->key = $this->key;
		} catch ( Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		return $meta;
	}

	private function softDelete() {
		if ( $this->flags ) {
			$delete = array();
			foreach ( $this->flags as $flag ) {
				if ( in_array( $flag, $this->fields ) ) {
					$delete[ $flag ] = 0;
				}
			}

			return $delete;
		}

		return false;
	}

	private function getStamps() {
		if ( $this->tablestamps ) {
			$stamps = array();
			foreach ( $this->tablestamps as $k => $v ) {
				if ( in_array( $k, $this->fields ) && $v ) {
					$stamps[] = "$k = $v";
				}
			}

			return $stamps;
		}
	}

	private function build( $array, $join = "" ) {
		foreach ( $array as $k => $v ) {
			if ( in_array( $k, $this->fields ) && ! in_array( $k, array_keys( $this->tablestamps ) ) ) {
				$builder[ $k ] = $v;
			}
		}

		$fn_map = function ( $k, $v ) {
			return $k . " = " . ( isset( $v ) ? "'$v'" : "null" );
		};
		$map    = array_map( $fn_map, array_keys( $builder ), $builder );

		if ( $join ) {
			return implode( $join, $map );
		}

		return $map;
	}

	private function getSelect() {
		$sql = "SELECT ";
		if ( $this->select ) {
			if ( count( $this->select ) <= count( $this->tables ) ) {
				foreach ( $this->select as $i => $select ) {
					foreach ( explode( ",", $select ) as $field ) {
						$array[] = $this->tables[ $i ]['name'] . "." . trim( $field );
					}
				}
				$sql .= implode( ", ", $array );
			} else {
				$this->error( "Select statement doesn't match with tables count" );
			}
		} else {
			$sql .= " * ";
		}

		return $sql;
	}

	private function getSource() {
		if ( $this->tables ) {
			$source[] = " FROM " . $this->tables[0]['name'];
			for ( $i = 1; $i < count( $this->tables ); $i ++ ) {
				$source[] = $this->tables[ $i ]['join'] . " JOIN "
				            . $this->tables[ $i ]['name'] . " ON "
				            . $this->getRelation( $i );
			}
		} else {
			$this->error( "You have no tables registered yet" );
		}

		return implode( " ", $source );
	}

	private function getRelation( $index ) {
		$rel = $this->tables[ $index ]['relation'];
		if ( substr_count( $rel, "=" ) ) {
			$rel      = explode( "=", $rel );
			$on[]     = $this->tables[ $index ]['name'] . "." . trim( $rel[0] );
			$on[]     = $this->tables[ $this->tables[ $index ]['target'] ]['name'] . "." . trim( $rel[1] );
			$relation = implode( " = ", $on );
		} else {
			$relation = $rel . " = " . $this->tables[ $this->tables[ $index ]['target'] ]['name'] . ".id";
		}

		return $relation;
	}

	private function getOrder() {
		if ( $this->order ) {
			if ( count( $this->order ) <= count( $this->tables ) ) {
				foreach ( $this->order as $i => $ord ) {
					foreach ( explode( ",", $ord ) as $o ) {
						$order[] = $this->tables[ $i ]['name'] . "." . trim( $o );
					}
				}
			} else {
				$this->error( "Order statement doesn't match with tables count" );
			}

			return " ORDER BY " . implode( ", ", $order );
		}
	}

	private function getGroup() {
		if ( $this->group ) {
			if ( count( $this->group ) <= count( $this->tables ) ) {
				foreach ( $this->group as $i => $group ) {
					foreach ( explode( ",", $group ) as $g ) {
						$array[] = $this->tables[ $i ]['name'] . "." . trim( $g );
					}
				}
			} else {
				$this->error( "Group statement doesn't match with tables count" );
			}

			return " GROUP BY " . implode( ", ", $array );
		}
	}

	private function getWhere() {
		if ( $this->flags && ! $this->includeDeleted ) {
			foreach ( $this->tables as $i => $table ) {
				if ( ! in_array( $i, $this->ignore ) ) {
					$this->getTable( $table['name'] );
					$actives = array();
					foreach ( $this->flags as $f ) {
						if ( in_array( $f, $this->fields ) ) {
							$actives[] = "$f = 1";
						}
					}
					if ( $actives ) {
						$w                 = $this->where[ $i ] ? explode( ",", $this->where[ $i ] ) : array();
						$this->where[ $i ] = implode( ",", array_merge( $w, $actives ) );
					}
				}
			}
		}

		if ( $this->where ) {
			if ( count( $this->where ) <= count( $this->tables ) ) {
				$operators = array( ">=", "<=", "<>", "!=", "=", ">", "<", "like", "is null", "is not null" );
				foreach ( $this->where as $i => $where ) {
					foreach ( explode( ",", $where ) as $w ) {
						foreach ( $operators as $ope ) {
							if ( substr_count( $w, $ope ) ) {
								$condition = explode( $ope, $w );
								$value     = "'" . trim( $condition[1] ) . "'";
								$array[]   = $this->tables[ $i ]['name'] . "." . trim( $condition[0] ) . " " . $ope . " " . $value;
							}
						}
					}
				}
			} else {
				$this->error( "Where statement doesn't match with tables count" );
			}
		}
		if ( $this->rawWhere ) {
			foreach ( $this->rawWhere as $i => $raw ) {
				foreach ( $raw as $r ) {
					$array[] = $this->tables[ $i ]['name'] . "." . $r;
				}
			}
		}

		if ( $array ) {
			return " WHERE " . implode( " and ", $array );
		}

	}

	private function getWiths( $data ) {
		if ( $this->with ) {
			$key = $this->getTable( $this->tables[0]['name'] )->key->name;
			try {
				foreach ( $data as $i => $d ) {
					foreach ( $this->with as $type => $with ) {
						foreach ( $with as $w ) {
							$where = array( $w['foreign'] . " = '" . $d[ $key ] . "'" );
							if ( $this->flags ) {
								$map = $this->getTable( $w['table'] )->fields;
								foreach ( $this->flags as $flag ) {
									if ( in_array( $flag, $map ) ) {
										$where[] = $flag . " = 1";
									}
								}
							}
							$sql    = "select * from " . $w['table'] . " where " . implode( " and ", $where );
							$result = $this->query( $sql );
							if ( $type == 'MANY' ) {
								$add = mysqli_fetch_all( $result, MYSQLI_ASSOC );
							} elseif ( $type == 'ONE' ) {
								$add = mysqli_fetch_assoc( $result );
							}
							if ( $w['pluck'] ) {
								$data[ $i ][ $w['alias'] ] = $this->lists( $add, $w['pluck'] );
							} else {
								$data[ $i ][ $w['alias'] ] = $add;
							}
						}
					}
				}
			} catch ( Exception $ex ) {
				$this->error( $ex->getMessage() );
			}
		}

		return $data;
	}

	/**
	 * @param int $index
	 *
	 * @return false|string
	 */
	private function getNormalizedTarget( int $index ) {
		$target = $this->tables[ $index ]['name'];
		if ( substr( $target, - 1 ) == 's' ) {
			return substr( $target, 0, strlen( $target ) - 1 );
		}

		return $target;
	}

	/**
	 * @param string $msg
	 *
	 * @throws \Exception
	 */
	private function error( string $msg = '' ) {
		if ( $this->_debug ) {
			$error = [
				mysqli_error( $this->cn ),
				$msg,
				$this->sql
			];
		}

		throw new \Exception( join( ' | ', array_filter( $error ?? [] ) ) );
	}

	/**
	 * @return bool
	 */
	private function validateData() {
		$checkList = [
			$this->_host,
			$this->_user,
			$this->_password,
			$this->_name,
		];

		foreach ( $checkList as $field ) {
			if ( empty( $field ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param int $index
	 * @param string $tableField
	 * @param string $operator
	 * @param string $foreigField
	 *
	 * @return $this
	 */
	private function setRelation( int $index, string $tableField, string $operator, string $foreigField ) {
		$tableJoin   = $this->tables[ $index ];
		$tableTarget = $this->tables[ $tableJoin['target'] ];

		$relaion                            = sprintf( '%s.%s %s %s.%s', $tableJoin['name'], $tableField, $operator, $tableTarget['name'], $foreigField );
		$this->tables[ $index ]['relation'] = $relaion;

		return $this;
	}

}
