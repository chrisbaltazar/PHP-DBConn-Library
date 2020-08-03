<?php

namespace DBConn;

/**
 * Class DBConn
 * Easy and fast DB queries for everyone
 */
class DBConn {

	const RETURN_FORMAT_ARRAY = 'ARRAY';
	const RETURN_FORMAT_OBJECT = 'OBJECT';
	const JOIN_TYPE_INNER = 'INNER';
	const JOIN_TYPE_LEFT = 'LEFT';
	const JOIN_TYPE_RIGHT = 'RIGHT';
	const OPERATORS = [ '>=', '<=', '<>', '!=', '=', '>', '<', 'LIKE', 'IS NULL', 'IS NOT NULL' ];

	protected $_host;
	protected $_user;
	protected $_password;
	protected $_name;
	protected $_session;
	protected $_debug;
	protected $_timezone;
	protected $_summertime;
	protected $_charset;

	protected $tablestamps = [];
	protected $primaryKey = 'id';

	private $cn;
	private $id;
	private $rows;
	private $sql;
	private $fields;
	private $key;
	private $tables;
	private $select;
	private $order;
	private $group;
	private $where;
	private $includeDeleted;
	private $ignore;
	private $with;
	private $rawWhere;
	private $activeFlags;
	private $meta;
	private $values;
	private $limit;
	private $offset;


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

		$this->tablestamps = [
			'updated_by' => $_SESSION[ $this->_session ] ?? 'null',
			'updated_at' => 'NOW()'
		];

		$this->addActiveFlag( 'deleted_at', 'IS_NULL', 'NOW()' );

		$this->init();
	}

	/**
	 * initialize class vars to use them
	 */
	protected function init() {
		if ( ! $this->validateData() ) {
			throw new \UnexpectedValueException( 'Some of the db config fields are missing' );
		}

		$this->where          = [];
		$this->select         = [];
		$this->order          = [];
		$this->group          = [];
		$this->ignore         = [];
		$this->rawWhere       = [];
		$this->limit          = null;
		$this->offset         = null;
		$this->includeDeleted = false;
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
		if ( $this->cn ) {
			return $this->cn;
		}

		try {
			$this->cn = mysqli_connect(
				$this->_host,
				$this->_user,
				$this->_password,
				$this->_name
			);
			mysqli_query( $this->cn, "SET NAMES '" . $this->_charset . "'" );
			mysqli_query( $this->cn, "SET time_zone = '" . $this->getTimezone() . "'" );
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
			$this->cn = null;
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
	 * @return array
	 * @throws \Exception
	 */
	public function getArray( string $sql = '', string $format = self::RETURN_FORMAT_ARRAY ) {
		if ( ! $sql ) {
			$sql = $this->getSQL();
		}

		$result = [];
		$ds     = $this->query( $sql );
		try {
			switch ( $format ) {
				case self::RETURN_FORMAT_OBJECT:
					while ( $row = mysqli_fetch_object( $ds ) ) {
						$result[] = $row;
					}
					break;
				case self::RETURN_FORMAT_ARRAY:
				default:
					$result = mysqli_fetch_all( $ds, MYSQLI_ASSOC );
					break;
			}
		} catch ( \Exception $ex ) {
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
				$obj = mysqli_fetch_object( $ds );
			}
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}
		$this->close();

		return $obj ?? null;
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
				$item = current( mysqli_fetch_row( $ds ) );
			}
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}
		$this->close();

		return $item ?? null;
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
	 * save a element into the table, based on the WHERE condition for insert or update
	 *
	 * @param string $table
	 * @param array $data
	 * @param mixed $update
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function save( string $table, array $data, $update = null ) {
		$this->values = [];
		$metaTable    = $this->getTable( $table );
		if ( $update ) {
			$sql = "UPDATE $table SET ";
		} else {
			$sql = "INSERT INTO $table SET ";
		}

		$sql .= join( ',', $this->build( $metaTable->fields, $data, true ) );

		if ( $update ) {
			$this->createExecuteWhere( $sql, $metaTable, $update );
		}

		return $this->execute( $sql );
	}

	/**
	 * delete or update with the declared flags, some records based on the where conditions
	 *
	 * @param string $table
	 * @param type $where
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function delete( string $table, $where ) {
		if ( ! $where ) {
			$this->error( "No Where Statement declared for DELETE" );
		}
		$this->values = [];
		$metaTable    = $this->getTable( $table );
		if ( $softDelete = $this->softDelete() ) {
			$sql = "UPDATE $table SET " . join( ', ', $this->build( $metaTable->fields, $softDelete, true ) );
		} else {
			$sql = "DELETE FROM $table";
		}

		$this->createExecuteWhere( $sql, $metaTable, $where );

		return $this->execute( $sql );
	}

	/**
	 * @param string $sql
	 * @param $metaTable
	 * @param $param
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function createExecuteWhere( string &$sql, $metaTable, $param ) {
		$where = ' WHERE ';
		if ( is_numeric( $param ) ) {
			$where .= $metaTable->key->name . ' = ' . $param;
		} elseif ( is_array( $param ) && array_filter( $param ) ) {
			$where .= join( ' AND ', $this->build( $metaTable->fields, $param, false ) );
		} else {
			$this->error( "Where format not valid for DELETE" );
		}

		$sql .= $where;
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

	/**
	 * @param string $tableField
	 * @param string $foreignFieldOrOperator
	 * @param string $justForeignField
	 *
	 * @return $this
	 * @throws \Exception
	 */
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
			$foreignField = '';
		} else {
			if ( in_array( trim( strtoupper( $foreignFieldOrOperator ) ), self::OPERATORS ) ) {
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
	 * @param array $args
	 *
	 * @return $this
	 */
	public function order( ...$args ) {
		$this->order = $args;

		return $this;
	}

	/**
	 * set the group fields for the query builder
	 *
	 * @param array $args
	 *
	 * @return $this
	 */
	public function group( ...$args ) {
		$this->group = $args;

		return $this;
	}

	/**
	 * set the group where conditions for the query builder
	 *
	 * @param array $args
	 *
	 * @return $this
	 */
	public function where( ...$args ) {
		$this->where  = $args;
		$this->values = [];

		return $this;
	}

	/**
	 * @param int $limit
	 * @param int|null $offset
	 *
	 * @return $this
	 */
	public function only( int $limit, int $offset = null ) {
		$this->limit  = $limit;
		$this->offset = $offset;

		return $this;
	}

	/**
	 * set the RAW special conditions for the query builder
	 *
	 * @param array $args
	 *
	 * @return $this
	 */
	public function whereRaw( array $args ) {
		foreach ( $args as $i => $raw ) {
			$this->rawWhere[ $i ][] = $raw;
		}

		return $this;
	}

	/**
	 * include records marked as DELETED with the flags, to be included in query
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
	 * @param string $table
	 * @param string $foreignField
	 * @param string $alias
	 * @param string $pluck
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function withMany( string $table, string $foreignField, string $alias = '', string $pluck = '' ) {
		$this->addWith( "MANY", $table, $foreignField, $alias, $pluck );

		return $this;
	}

	/**
	 * set one relation with relative data that has to be extracted with the main query,
	 * attached as a SINGLE OBJECT on the results
	 *
	 * @param string $table
	 * @param string $foreignField
	 * @param string $alias
	 * @param string $pluck
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function withOne( string $table, string $foreignField, string $alias = '', string $pluck = '' ) {
		$this->addWith( "ONE", $table, $foreignField, $alias, $pluck );

		return $this;
	}


	/**
	 * Ignore the flags declared to reach all the records "DELETED" on any table
	 *
	 * @param array $tableIndex
	 *
	 * @return $this
	 */
	public function ignoreActive( array $tableIndex ) {
		$this->ignore = $tableIndex;

		return $this;
	}

	/**
	 * Build and return all the query saved in the instance
	 * @return string
	 * @throws \Exception
	 */
	public function getSQL() {
		$sql = '';
		$sql .= $this->getSelect();
		$sql .= $this->getSource();
		$sql .= $this->getWhere();
		$sql .= $this->getGroup();
		$sql .= $this->getOrder();
		$sql .= $this->getLimits();

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
		$this->connect();
		// Sanitize values before querying and once we have the connection
		$sql = vsprintf( $sql, array_map( function ( $item ) {
			if ( strpos( $item, '\'' ) === 0 ) {
				return "'" . mysqli_real_escape_string( $this->cn, trim( $item, '\'' ) ) . "'";
			}

			return mysqli_real_escape_string( $this->cn, $item );
		}, $this->values ) );

		$this->sql = $sql;

		try {
			$result     = mysqli_query( $this->cn, $sql );
			$this->rows = mysqli_affected_rows( $this->cn );
			$this->id   = mysqli_insert_id( $this->cn );
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		return $result ?? false;
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
	 * @param string $type
	 * @param string $table
	 * @param string $foreign
	 * @param string $alias
	 * @param string $pluck
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function addWith( string $type, string $table, string $foreign, string $alias = '', string $pluck = '' ) {
		if ( $this->tables ) {
			$this->with[ $type ][] = [
				'table'   => $table,
				'foreign' => $foreign,
				'alias'   => $alias ?: $table,
				'pluck'   => $pluck
			];
		} else {
			$this->error( "With statement needs a main source table selected" );
		}

		return $this;
	}

	/**
	 * @param string $table
	 *
	 * @return object
	 * @throws \Exception
	 */
	private function getTable( string $table ) {
		if ( $this->meta[ $table ] ) {
			return $this->meta[ $table ];
		}

		$metaTable = [];
		$metaKey   = [];
		try {
			$tableRows = mysqli_fetch_all( $this->query( "SHOW COLUMNS FROM $table" ), MYSQLI_ASSOC );
			foreach ( $tableRows as $row ) {
				$metaTable['fields'][ $row['Field'] ] = $row['Type'];
				if ( $row['Key'] == 'PRI' && ! $metaKey ) {
					$metaKey['name'] = $row['Field'];
					$metaKey['auto'] = substr_count( $row['Extra'], "auto_increment" ) > 0;
				}
			}
			$metaTable['key'] = (object) $metaKey;
		} catch ( \Exception $ex ) {
			$this->error( $ex->getMessage() );
		}

		$this->meta[ $table ] = (object) $metaTable;

		return $this->meta[ $table ];
	}

	/**
	 * @return array|bool
	 */
	private function softDelete() {
		if ( $this->activeFlags ) {
			$softDelete = [];
			foreach ( $this->activeFlags as $flag => $value ) {
				$softDelete[] = sprintf( '%s = %s', $flag, $value['fill'] );
			}

			return $softDelete;
		}

		return false;
	}

	/**
	 * @return array
	 */
	private function getStamps() {
		$stamps = [];
		if ( $this->tablestamps ) {
			foreach ( $this->tablestamps as $k => $v ) {
				if ( in_array( $k, $this->fields ) && $v ) {
					$stamps[] = "$k = $v";
				}
			}
		}

		return $stamps;
	}

	/**
	 * @param array $fields
	 * @param array $data
	 * @param bool $withStapms
	 *
	 * @return array
	 */
	private function build( array $fields, array $data, bool $withStapms ) {
		$builder = [];
		$stack   = array_intersect_key( $data, $fields );
		foreach ( $stack as $key => $value ) {
//			$value = $data[ $key ] ?? null;
			if ( isset( $value ) ) {
				$matches = [];
				// Try to find out if the value is a DB function to avoid using quotes
				preg_match( '<^(\w+[(](.*)[)])$>', $value, $matches );
				if ( ! $matches ) {
					// Is not a function but a plain value
					$value = $this->getInputValue( "'$value'" );
				}
			} else {
				$value = 'null';
			}

			$builder[] = sprintf( '%s = %s', $key, $value );
		}

		if ( $withStapms && $this->tablestamps ) {
			$stack = array_intersect_key( $this->tablestamps, $fields );
			foreach ( $stack as $stamp => $default ) {
				$builder[] = sprintf( '%s = %s', $stamp, $default );
			}
		}

		return $builder;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getSelect() {
		$sql = "SELECT ";
		if ( $this->select ) {
			if ( count( $this->select ) > count( $this->tables ) ) {
				$this->error( "Select statement doesn't match with tables count" );
			}

			$stack = [];
			foreach ( $this->select as $i => $select ) {
				if ( $select ) {
					foreach ( explode( ",", $select ) as $field ) {
						$stack[] = $this->tables[ $i ]['name'] . "." . trim( $field );
					}
				}
			}
			$sql .= implode( ", ", $stack );
		} else {
			$sql .= " * ";
		}

		return $sql;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getSource() {
		$source = [];
		if ( $this->tables ) {
			$source[] = ' FROM ' . $this->tables[0]['name'];
			for ( $i = 1; $i < count( $this->tables ); $i ++ ) {
				if ( ! $this->tables[ $i ]['relation'] ) {
					$this->setRelation( $i, $this->primaryKey, '=', '' );
				}
				$source[] = $this->tables[ $i ]['join'] . ' JOIN '
				            . $this->tables[ $i ]['name'] . ' ON '
				            . $this->tables[ $i ]['relation'];
			}
		} else {
			$this->error( 'You have no tables registered yet' );
		}

		return join( ' ', $source );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getOrder() {
		if ( $this->order ) {
			if ( count( $this->order ) > count( $this->tables ) ) {
				$this->error( 'Order statement doesn\'t match with tables count' );
			}
			$stack = [];
			foreach ( $this->order as $i => $order ) {
				foreach ( explode( ',', $order ) as $o ) {
					$stack[] = $this->tables[ $i ]['name'] . '.' . trim( $o );
				}
			}

			return ' ORDER BY ' . join( ', ', $stack );
		}

		return '';
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getGroup() {
		if ( $this->group ) {
			if ( count( $this->group ) > count( $this->tables ) ) {
				$this->error( 'Group statement doesn\'t match with tables count' );
			}
			$stack = [];
			foreach ( $this->group as $i => $group ) {
				foreach ( explode( ',', $group ) as $g ) {
					$stack[] = $this->tables[ $i ]['name'] . '.' . trim( $g );
				}
			}

			return ' GROUP BY ' . join( ', ', $stack );
		}

		return '';
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getWhere() {
		if ( $this->activeFlags && ! $this->includeDeleted ) {
			$tables = &$this->tables;
			foreach ( $tables as $i => $table ) {
				if ( in_array( $i, $this->ignore ) ) {
					continue;
				}

				$flags = $this->getActiveFlags( $table['name'] );
				if ( $flags ) {
					$where             = explode( ',', $this->where[ $i ] ?? '' );
					$this->where[ $i ] = join( ',', array_merge( $where, $flags ) );
				}
			}
		}
		$stack = [];
		if ( $this->where ) {
			if ( count( $this->where ) > count( $this->tables ) ) {
				$this->error( 'Where statement does not match with tables count' );
			}

			foreach ( $this->where as $i => $where ) {
				foreach ( explode( ",", $where ) as $w ) {
					foreach ( self::OPERATORS as $ope ) {
						if ( substr_count( strtoupper( $w ), $ope ) ) {
							$condition = explode( $ope, trim( $w ) );
							if ( empty( $condition[1] ) ) {
								$stack[] = sprintf( "%s.%s %s", $this->tables[ $i ]['name'], trim( $condition[0] ), $ope );
							} else {
								$value   = $this->getInputValue( trim( $condition[1] ) );
								$stack[] = sprintf( "%s.%s %s %s", $this->tables[ $i ]['name'], trim( $condition[0] ), $ope, $value );
							}
							break;
						}
					}
				}
			}
		}
//		if ( $this->rawWhere ) {
//			foreach ( $this->rawWhere as $i => $raw ) {
//				foreach ( $raw as $r ) {
//					$stack[] = $this->tables[ $i ]['name'] . "." . $r;
//				}
//			}
//		}
		if ( $stack ) {
			return ' WHERE ' . join( ' AND ', $stack );
		}

		return '';
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getWiths( array $data ) {
		if ( $data && $this->with ) {
			$key = $this->getTable( $this->tables[0]['name'] )->key->name;
			if ( ! isset( $data[0][ $key ] ) ) {
				$this->error( 'You have to SELECT the PRIMARY KEY of the MAIN table to LOAD additional records OR use the same ALIAS' );
			}

			try {
				foreach ( $data as $i => $d ) {
					foreach ( $this->with as $type => $with ) {
						foreach ( $with as $w ) {
							$where = [ $w['foreign'] . " = '" . $d[ $key ] . "'" ];
							if ( $this->activeFlags ) {
								$flags = $this->getActiveFlags( $w['table'] );
								$where = array_merge( $where, $flags );
							}
							$sql    = 'select * from ' . $w['table'] . ' where ' . join( ' AND ', $where );
							$result = $this->query( $sql );
							switch ( $type ) {
								case 'ONE':
									$added = mysqli_fetch_assoc( $result );
									break;
								case 'MANY':
								default:
									$added = mysqli_fetch_all( $result, MYSQLI_ASSOC );
									break;
							}

							if ( $w['pluck'] ) {
								$data[ $i ][ $w['alias'] ] = array_column( $added, $w['pluck'] );
							} else {
								$data[ $i ][ $w['alias'] ] = $added;
							}
						}
					}
				}
			} catch ( \Exception $ex ) {
				$this->error( $ex->getMessage() );
			}
		}
		$this->close();

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
	protected function error( string $msg = '' ) {
		$error = [ $msg ];
		if ( $this->_debug ) {
			$error = array_merge( $error, [
				mysqli_error( $this->cn ),
				$this->sql
			] );
		}

		throw new \Exception( join( ' | ', array_filter( $error ) ) );
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
	 * @param string $foreignField
	 *
	 * @return $this
	 */
	private function setRelation( int $index, string $tableField, string $operator, string $foreignField ) {
		$tableJoin   = $this->tables[ $index ];
		$tableTarget = $this->tables[ $tableJoin['target'] ];
		if ( empty( $foreignField ) ) {
			$foreignField = $this->getNormalizedTarget( $tableJoin['target'] ) . '_' . $this->primaryKey;
		}

		$this->tables[ $index ]['relation'] = sprintf( '%s.%s %s %s.%s', $tableJoin['name'], $tableField, $operator, $tableTarget['name'], $foreignField );

		return $this;
	}

	/**
	 * @param string $fieldName
	 * @param string $condition
	 * @param string $fillValue
	 *
	 * @return DBConn
	 */
	protected function addActiveFlag( string $fieldName, string $condition, string $fillValue ) {
		$this->activeFlags[ $fieldName ] = [ 'condition' => $condition, 'fill' => $fillValue ];

		return $this;
	}

	/**
	 * @param string $table
	 * @param string $field
	 * @param string $operator
	 * @param string $value
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function createCondition( string $table, string $field, string $operator, string $value = '' ) {
		if ( empty( $value ) ) {
			return sprintf( "%s.%s %s", $table, trim( $field ), $operator );
		}

		$metaTable      = $this->getTable( $table );
		$fieldType      = strtolower( $metaTable->fields[ $field ] );
		$quotesRequired = [ 'char', 'text', 'blob', 'date', 'time' ];
		// Evaluate if the value needs to use quotes
		if ( array_filter( $quotesRequired, function ( $item ) use ( $fieldType ) {
			return substr_count( $fieldType, $item ) > 0;
		} ) ) {
			$value = "'$value'";
		}

		return sprintf( "%s.%s %s %s", $table, trim( $field ), $operator, $value );
	}

	/**
	 * @param string $input
	 *
	 * @return string
	 */
	private function getInputValue( string $input ) {
		$this->values[] = $input;

		return '%' . count( $this->values ) . '$s';
	}

	/**
	 * @param $table
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getActiveFlags( string $table ): array {
		$flags  = [];
		$fields = $this->getTable( $table['name'] )->fields;
		foreach ( array_intersect_key( $this->activeFlags, $fields ) as $flag => $config ) {
			$flags[] = $flag . ' ' . $config['condition'];
		}

		return $flags;
	}

	/**
	 * @return string
	 */
	private function getLimits() {
		$limit = '';
		if ( $this->limit ) {
			$limit .= ' LIMIT ' . $this->limit;
		}

		if ( $this->offset ) {
			$limit .= ' OFFSET ' . $this->offset;
		}

		return $limit;
	}

}
