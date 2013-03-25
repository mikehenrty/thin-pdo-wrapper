<?php
/**
 * Thin PDO Wrapper: A simple database client utilizing PHP PDO.
 *
 * Copyright (c) 20010-2011 Michael Henretty
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright  2010-2011 Michael Henretty <michael.henretty@gmail.com>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/mikehenrty/thin-pdo-wrapper
 */

/**
 * Wrapper object for a PDO connection to the database
 *
 * @package PDO Wrapper
 * @author Michael Henretty - 08/24/2010
 * @version 1.1
 */
class PDOWrapper {
	
	
	/**
	 * Hardcoded database configuration
	 */
	const DB_DSN_PREFIX_MASTER = '';
	const DB_HOST_MASTER = '';
	const DB_NAME_MASTER = '';
	const DB_USER_MASTER = '';
	const DB_PASSWORD_MASTER = '';
	const DB_PORT_MASTER = '';
	
	const SLAVE1_DSN_PREFIX = '';
	const SLAVE1_HOST = '';
	const SLAVE1_NAME = '';
	const SLAVE1_USER = '';
	const SLAVE1_PASSWORD = '';
	const SLAVE1_PORT = '';
	
	const SLAVE2_DSN_PREFIX = '';
	const SLAVE2_HOST = '';
	const SLAVE2_NAME = '';
	const SLAVE2_USER = '';
	const SLAVE2_PASSWORD = '';
	const SLAVE2_PORT = '';
	// note: to add more, stick with the naming convention
	
	/**
	 * Write all errors to error log
	 * 
	 * @var boolean
	 */
	public static $LOG_ERRORS = true;

	/**
	 * Automatically add/update created/updated fields
	 * 
	 * @var boolean
	 */
	public static $TIMESTAMP_WRITES = false;

	/**
	 * Dynamic master config creds
	 * 
	 * @var Array - representing config details
	 */
	protected $config_master;

	/**
	 * Dynamic slave config creds
	 * 
	 * @var Array of Arrays - associative arrays of slave creds
	 */
	protected $config_slaves;

	/**
	 * The PDO objects for the master connection
	 *
	 * @var PDO - the Pear Data Object
	 */
	protected $pdo_master;
	
	
	/**
	 * The PDO objects for the slave connection
	 *
	 * @var PDO - the Pear Data Object
	 */
	protected $pdo_slave;
	
	
	/**
	 * We will cache any PDO errors in case we want to get out them externally
	 *
	 * @var PDOException - for keeping track of any exceptions in PDO
	 */
	protected $pdo_exception;
	
	
	/**
	 * A reference to the singleton instance
	 *
	 * @var PDOWrapper
	 */
	protected static $instance = null;
	
	
	/**
	 * method instance.
	 * 	- static, for singleton, for creating a global instance of this object
	 *
	 * @return - PDOWrapper Object
	 */
	public static function instance() {
		if (!isset(self::$instance)) {
			self::$instance = new PDOWrapper();
		}
		return self::$instance;
	}
	
	 
	/**
	 * Constructor.
	 * 	- make protected so only subclasses and self can create this object (singleton)
	 */
	protected function __construct() {}

	/**
	 * method configMaster
	 * 	- configure connection credentials to the master db server
	 * 	
	 * @param host - the host name of the db to connect to
	 * @param name - the database name
	 * @param user - the user name
	 * @param password - the users password
	 * @param port (optional) - the port to connect using, default to 3306
	 * @param driver - the dsn prefix
	 */
	public function configMaster($host, $name, $user, $password, $port=null, $driver='mysql') {
		if (!$this->validateDriver($driver)) {
			throw new Exception('DATABASE WRAPPER::error, the database you wish to connect to is not supported by your install of PHP.');
		}

		if (isset($this->pdo_master)) {
			error_log('DATABASE WRAPPER::warning, attempting to config master after connection exists');
		}

		$this->config_master = array(
			'driver' => $driver,
			'host' => $host,
			'name' => $name,
			'user' => $user,
			'password' => $password,
			'port' => $port
		);
	}


	/**
	 * method configSlave
	 * 	- configure a connection to a slave (can be called multiple times)
	 * 
	 * @param host - the host name of the db to connect to
	 * @param name - the database name
	 * @param user - the user name
	 * @param password - the users password
	 * @param port (optional) - the port to connect using, default to 3306
	 * @param driver - the dsn prefix
	 */
	public function configSlave($host, $name, $user, $password, $port=null, $driver='mysql') {
		if (!$this->validateDriver($driver)) {
			throw new Exception('DATABASE WRAPPER::error, the database you wish to connect to is not supported by your install of PHP.');
		}
        
		if (isset($this->pdo_slave)) {
			error_log('DATABASE WRAPPER::warning, attempting to config slave after connection exists');
		}

		if (!isset($this->config_slaves)) {
			$this->config_slaves = array();
		}

		$this->config_slaves[] = array(
			'driver' => $driver,
			'host' => $host,
			'name' => $name,
			'user' => $user,
			'password' => $password,
			'port' => $port
		);
	}


	/**
	 * method createConnection.
	 * 	- create a PDO connection using the credentials provided
	 * 
     * @param driver - the dsn prefix
	 * @param host - the host name of the db to connect to
	 * @param name - the database name
	 * @param user - the user name
	 * @param password - the users password
	 * @param port (optional) - the port to connect using, default to 3306
	 * @return PDO object with a connection to the database specified
	 */
	protected function createConnection($driver, $host, $name, $user, $password, $port=null) {
		if (!$this->validateDriver($driver)) {
			throw new Exception('DATABASE WRAPPER::error, the database you wish to connect to is not supported by your install of PHP.');
		}
        
		// attempt to create pdo object and connect to the database
		try {
			//@TODO the following drivers are NOT supported yet: odbc, ibm, informix, 4D
			// build the connection string from static constants based on the selected PDO Driver.
			if ($driver == "sqlite" || $driver == "sqlite2") {
				$connection_string = $driver.':'.$host;
			} elseif ($driver == "sqlsrv") {
				$connection_string = "sqlsrv:Server=".$host.";Database=".$name;
			} elseif ($driver == "firebird" || $driver == "oci") {
				$connection_string = $driver.":dbname=".$name;
			} else {
				$connection_string = $driver.':host='.$host.';dbname='.$name;
			}
			
			// add the port if one was specified
			if (!empty($port)) {
				$connection_string .= "port=$port";
			}
			
			// initialize the PDO object
			$new_connection = new PDO($connection_string, $user, $password);
			
			// set the error mode
			$new_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			
			// return the new connection
			return $new_connection;
		}
		
		// handle any exceptions by catching them and returning false
		catch (PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
	
	/**
	 * method getMaster.
	 * 	- grab the PDO connection to the master DB
	 */
	protected function getMaster() {
		// if we have not been configured, use hard coded values
		if (!isset($this->config_master)) {
			$this->config_master = array(
				'driver' => self::DB_DSN_PREFIX_MASTER,
				'host' => self::DB_HOST_MASTER,
				'name' => self::DB_NAME_MASTER,
				'user' => self::DB_USER_MASTER,
				'password' => self::DB_PASSWORD_MASTER,
				'port' => self::DB_PORT_MASTER
			);
		}

		// if we have not created the master db connection yet, create it now
		if (!isset($this->pdo_master)) {
			$this->pdo_master = $this->createConnection(
				$this->config_master['driver'],
				$this->config_master['host'],
				$this->config_master['name'],
				$this->config_master['user'],
				$this->config_master['password'],
				$this->config_master['port']
			);
		}
		
		return $this->pdo_master;
	}
	
 	/**
	 * method getSlave.
	 * 	- grab the PDO connection to the slave DB, create it if not there
	 */
	protected function getSlave() {
		// if we have not created a slave db connection, create it now
		if (!isset($this->pdo_slave)) {
			
			// if no slaves were configured, use hardcoded values
			if (!isset($this->config_slaves)) {
				$i = 1;
				while (defined('self::SLAVE' . $i . '_HOST') 
					&& constant('self::SLAVE' . $i . '_HOST')) {
					$this->config_slaves[] = array(
						'driver' => constant('self::SLAVE' . $i . '_DSN_PREFIX'),
						'host' => constant('self::SLAVE' . $i . '_HOST'),
						'name' => constant('self::SLAVE' . $i . '_NAME'),
						'user' => constant('self::SLAVE' . $i . '_USER'),
						'password' => constant('self::SLAVE' . $i . '_PASSWORD'),
						'port' => constant('self::SLAVE' . $i . '_PORT'),
					);
					$i++;
				}
			}
			
			// if no slaves are configured, use the master connection
			if (empty($this->config_slaves)) {
				$this->pdo_slave = $this->getMaster();
			}
			
			// if we have slaves, randomly choose one to use for this request and connect
			else {
				$random_slave = $this->config_slaves[array_rand($this->config_slaves)];
				$this->pdo_slave = $this->createConnection(
					$random_slave['driver'],
					$random_slave['host'],
					$random_slave['name'],
					$random_slave['user'],
					$random_slave['password'],
					$random_slave['port']
				);
			}
		}
		
		return $this->pdo_slave;
	}

	/**
	 * method select.
	 * 	- retrieve information from the database, as an array
	 *
	 * @param string $table - the name of the db table we are retreiving the rows from
	 * @param array $params - associative array representing the WHERE clause filters
	 * @param int $limit (optional) - the amount of rows to return
	 * @param int $start (optional) - the row to start on, indexed by zero
	 * @param array $order_by (optional) - an array with order by clause
	 * @param bool $use_master (optional) - use the master db for this read
	 * @return mixed - associate representing the fetched table row, false on failure
	 */
	public function select($table, $params = null, $limit = null, $start = null, $order_by=null, $use_master = false) {
		// building query string
		$sql_str = "SELECT * FROM $table";
		// append WHERE if necessary
		$sql_str .= ( count($params)>0 ? ' WHERE ' : '' );
		
		$add_and = false;
		// add each clause using parameter array
		if (empty($params)) {
			$params = array();
		}
		foreach ($params as $key=>$val) {
			// only add AND after the first clause item has been appended
			if ($add_and) {
				$sql_str .= ' AND ';
			} else {
				$add_and = true;
			}
			
			// append clause item
			$sql_str .= "$key = :$key";
		}
		
		// add the order by clause if we have one
		if (!empty($order_by)) {
			$sql_str .= ' ORDER BY';
			$add_comma = false;
			foreach ($order_by as $column => $order) {
				if ($add_comma) {
					$sql_str .= ', ';
				}
				else {
					$add_comma = true;
				}
				$sql_str .= " $column $order";
			}
		}
		
		// now we attempt to retrieve the row using the sql string
		try {
			
			// decide which database we are selecting from
			$pdo_connection = $use_master ? $this->getMaster() : $this->getSlave();
            $pdoDriver = $pdo_connection->getAttribute(PDO::ATTR_DRIVER_NAME);
            
			//@TODO MS SQL Server & Oracle handle LIMITs differently, for now its disabled but we should address it later.
			$disableLimit = array("sqlsrv", "mssql", "oci");
            
			// add the limit clause if we have one
			if (!is_null($limit) && !in_array($pdoDriver, $disableLimit)) {
				$sql_str .= ' LIMIT '.(!is_null($start) ? "$start, ": '')."$limit";
			}
			
			$pstmt = $pdo_connection->prepare($sql_str);
			
			// bind each parameter in the array			
			foreach ($params as $key=>$val) {
				$pstmt->bindValue(':'.$key, $val);
			}
			
			$pstmt->execute();
			
			// now return the results, depending on if we want all or first row only
			if ( !is_null($limit) && $limit == 1 ) {
				return $pstmt->fetch(PDO::FETCH_ASSOC);
			} else {
				return $pstmt->fetchAll(PDO::FETCH_ASSOC);
			}
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
	
	/**
	 * method selectMaster.
	 * 	- retrieve information from the master database, as an array
	 *
	 * @param table - the name of the db table we are retreiving the rows from
	 * @param params - associative array representing the WHERE clause filters
	 * @param int $limit (optional) - the amount of rows to return
	 * @param int $start (optional) - the row to start on, indexed by zero
	 * @param array $order_by (optional) - an array with order by clause
	 * @return mixed - associate representing the fetched table row, false on failure
	 */
	public function selectMaster($table, $params = array(), $limit = null, $start = null, $order_by=null) {
		return $this->select($table, $params, $limit, $start, $order_by, true);
	}
	
	
	/**
	 * method selectFirst.
	 * 	- retrieve the first row returned from a select statement
	 *
	 * @param table - the name of the db table we are retreiving the rows from
	 * @param params - associative array representing the WHERE clause filters
	 * @param array $order_by (optional) - an array with order by clause
	 * @return mixed - associate representing the fetched table row, false on failure
	 */
	public function selectFirst($table, $params = array(), $order_by=null) {
		return $this->select($table, $params, 1, null, $order_by);
	}
	
	
 	/**
	 * method selectFirstMaster.
	 * 	- retrieve the first row returned from a select statement using the master database
	 *
	 * @param table - the name of the db table we are retreiving the rows from
	 * @param params - associative array representing the WHERE clause filters
	 * @param array $order_by (optional) - an array with order by clause
	 * @return mixed - associate representing the fetched table row, false on failure
	 */
	public function selectFirstMaster($table, $params = array(), $order_by=null) {
		return $this->select($table, $params, 1, null, $order_by, true);
	}
	
	
	/**
	 * method delete.
	 * 	- deletes rows from a table based on the parameters
	 *
	 * @param table - the name of the db table we are deleting the rows from
	 * @param params - associative array representing the WHERE clause filters
	 * @return bool - associate representing the fetched table row, false on failure
	 */
	public function delete($table, $params = array()) {
		// building query string
		$sql_str = "DELETE FROM $table";
		// append WHERE if necessary
		$sql_str .= ( count($params)>0 ? ' WHERE ' : '' );
		
		$add_and = false;
		// add each clause using parameter array
		foreach ($params as $key=>$val) {
			// only add AND after the first clause item has been appended
			if ($add_and) {
				$sql_str .= ' AND ';
			} else {
				$add_and = true;
			}
			
			// append clause item
			$sql_str .= "$key = :$key";
		}
		
		// now we attempt to retrieve the row using the sql string
		try {
			$pstmt = $this->getMaster()->prepare($sql_str);
			
			// bind each parameter in the array			
			foreach ($params as $key=>$val) {
				$pstmt->bindValue(':'.$key, $val);
			}
			
			// execute the delete query
			$successful_delete = $pstmt->execute();
			
			// if we were successful, return the amount of rows updated, otherwise return false
			return ($successful_delete == true) ? $pstmt->rowCount() : false;
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
 	/**
	 * method update.
	 * 	- updates a row to the specified table
	 *
	 * @param string $table - the name of the db table we are adding row to
	 * @param array $params - associative array representing the columns and their respective values to update
	 * @param array $wheres (Optional) - the where clause of the query
	 * @param bool $timestamp_this (Optional) - if true we set date_created and date_modified values to now
	 * @return int|bool - the amount of rows updated, false on failure
	 */
	public function update($table, $params, $wheres=array(), $timestamp_this=null) {
		if (is_null($timestamp_this)) {
			$timestamp_this = self::$TIMESTAMP_WRITES;
		}
		// build the set part of the update query by
		// adding each parameter into the set query string
		$add_comma = false;
		$set_string = '';
		foreach ($params as $key=>$val) {
			// only add comma after the first parameter has been appended
			if ($add_comma) {
				$set_string .= ', ';
			} else {
				$add_comma = true;
			}
			
			// now append the parameter
			$set_string .= "$key=:param_$key";
		}
		
		// add the timestamp columns if neccessary
		if ($timestamp_this === true) {
			$set_string .= ($add_comma ? ', ' : '') . 'date_modified='.time();
		}
		
		// lets add our where clause if we have one
		$where_string = '';
		if (!empty($wheres)) {
			// load each key value pair, and implode them with an AND
			$where_array = array();
			foreach($wheres as $key => $val) {
				$where_array[] = "$key=:where_$key";
			}
			// build the final where string
			$where_string = 'WHERE '.implode(' AND ', $where_array);
		}
		
		// build final update string
		$sql_str = "UPDATE $table SET $set_string $where_string";
		
		// now we attempt to write this row into the database
		try {
			$pstmt = $this->getMaster()->prepare($sql_str);
			
			// bind each parameter in the array			
			foreach ($params as $key=>$val) {
				$pstmt->bindValue(':param_'.$key, $val);
			}
			
			// bind each where item in the array			
			foreach ($wheres as $key=>$val) {
				$pstmt->bindValue(':where_'.$key, $val);
			}
			
			// execute the update query
			$successful_update = $pstmt->execute();
			
			// if we were successful, return the amount of rows updated, otherwise return false
			return ($successful_update == true) ? $pstmt->rowCount() : false;
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
	/**
	 * method insert.
	 * 	- adds a row to the specified table
	 *
	 * @param string $table - the name of the db table we are adding row to
	 * @param array $params - associative array representing the columns and their respective values
	 * @param bool $timestamp_this (Optional), if true we set date_created and date_modified values to now
	 * @return mixed - new primary key of inserted table, false on failure
	 */
	public function insert($table, $params = array(), $timestamp_this = null) {
		if (is_null($timestamp_this)) {
			$timestamp_this = self::$TIMESTAMP_WRITES;
		}

		// first we build the sql query string
		$columns_str = '(';
		$values_str = 'VALUES (';
		$add_comma = false;
		
		// add each parameter into the query string
		foreach ($params as $key=>$val) {
			// only add comma after the first parameter has been appended
			if ($add_comma) {
				$columns_str .= ', ';
				$values_str .= ', ';
			} else {
				$add_comma = true;
			}
			
			// now append the parameter
			$columns_str .= "$key";
			$values_str .= ":$key";
		}
		
		// add the timestamp columns if neccessary
		if ($timestamp_this === true) {
			$columns_str .= ($add_comma ? ', ' : '') . 'date_created, date_modified';
			$values_str .= ($add_comma ? ', ' : '') . time().', '.time();
		}
		
		// close the builder strings
		$columns_str .= ') ';
		$values_str .= ')';
		
		// build final insert string
		$sql_str = "INSERT INTO $table $columns_str $values_str";
		
		// now we attempt to write this row into the database
		try {
			$pstmt = $this->getMaster()->prepare($sql_str);
			
			// bind each parameter in the array			
			foreach ($params as $key=>$val) {
				$pstmt->bindValue(':'.$key, $val);
			}
			
			$pstmt->execute();
			$newID = $this->getMaster()->lastInsertId();
			
			// return the new id
			return $newID;
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
	/**
	 * method insertMultiple.
	 * 	- adds multiple rows to a table with a single query
	 *
	 * @param string $table - the name of the db table we are adding row to
	 * @param array $columns - contains the column names
	 * @param bool $timestamp_these (Optional), if true we set date_created and date_modified values to NOW() for each row
	 * @return mixed - new primary key of inserted table, false on failure
	 */
	public function insertMultiple($table, $columns = array(), $rows = array(), $timestamp_these = null) {
		if (is_null($timestamp_these)) {
			$timestamp_these = self::$TIMESTAMP_WRITES;
		}

		// generate the columns portion of the insert statment
		// adding the timestamp fields if needs be
		if ($timestamp_these) {
			$columns[] = 'date_created';
			$columns[] = 'date_modified';
		}
		$columns_str = '(' . implode(',', $columns) . ') ';
		
		// generate the values portions of the string
		$values_str = 'VALUES ';
		$add_comma = false;
		
		foreach ($rows as $row_index => $row_values) {
			// only add comma after the first row has been added
			if ($add_comma) {
				$values_str .= ', ';
			} else {
				$add_comma = true;
			}
			
			// here we will create the values string for a single row
			$values_str .= '(';
			$add_comma_forvalue = false;
			foreach ($row_values as $value_index => $value) {
				if ($add_comma_forvalue) {
					$values_str .= ', ';
				} else {
					$add_comma_forvalue = true;
				}
				// generate the bind variable name based on the row and column index
				$values_str .= ':'.$row_index.'_'.$value_index;
			}
			// append timestamps if necessary
			if ($timestamp_these) {
				$values_str .= ($add_comma_forvalue ? ', ' : '') . time().', '.time();
			}
			$values_str .= ')';
		}
		
		// build final insert string
		$sql_str = "INSERT INTO $table $columns_str $values_str";
		
		// now we attempt to write this multi inster query to the database using a transaction
		try {
			$this->getMaster()->beginTransaction();
			$pstmt = $this->getMaster()->prepare($sql_str);
			
			// traverse the 2d array of rows and values to bind all parameters
			foreach ($rows as $row_index => $row_values) {
				foreach ($row_values as $value_index => $value) {
					$pstmt->bindValue(':'.$row_index.'_'.$value_index, $value);
				}
			}
			
			// now lets execute the statement, commit the transaction and return
			$pstmt->execute();
			$this->getMaster()->commit();
			return true;
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			$this->getMaster()->rollback();
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			$this->getMaster()->rollback();
			return false;
		}
	}
	
	/**
	 * method execute.
	 * 	- executes a query that modifies the database
	 *
	 * @param string $query - the SQL query we are executing
	 * @param bool $use_master (Optional) - whether or not to use the master connection
	 * @return mixed - the affected rows, false on failure
	 */
	public function execute($query, $params=array()) {
		try {
			// use the master connection
			$pdo_connection = $this->getMaster();
			
			// prepare the statement
			$pstmt = $pdo_connection->prepare($query);
			
			// bind each parameter in the array			
			foreach ((array)$params as $key=>$val) {
				$pstmt->bindValue($key, $val);
			}
			
			// execute the query
			$result = $pstmt->execute();
			
			// only if return value is false did this query fail
			return ($result == true) ? $pstmt->rowCount() : false;
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
	/**
	 * method query.
	 * 	- returns data from a free form select query
	 *
	 * @param string $query - the SQL query we are executing
	 * @param array $params - a list of bind parameters
	 * @param bool $use_master (Optional) - whether or not to use the master connection
	 * @return mixed - the affected rows, false on failure
	 */
	public function query($query, $params=array(), $use_master=false) {
		try {
			// decide which database we are selecting from
			$pdo_connection = $use_master ? $this->getMaster() : $this->getSlave();
			
			$pstmt = $pdo_connection->prepare($query);
			
			// bind each parameter in the array			
			foreach ((array)$params as $key=>$val) {
				$pstmt->bindValue($key, $val);
			}
			
			// execute the query
			$pstmt->execute();
			
			// now return the results
			return $pstmt->fetchAll(PDO::FETCH_ASSOC);
		}
		catch(PDOException $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
		catch(Exception $e) {
			if (self::$LOG_ERRORS == true) {
				error_log('DATABASE WRAPPER::'.print_r($e, true));
			}
			$this->pdo_exception = $e;
			return false;
		}
	}
	
	/**
	 * method queryFirst.
	 * 	- returns the first record from a free form select query
	 *
	 * @param string $query - the SQL query we are executing
	 * @param array $params - a list of bind parameters
	 * @param bool $use_master (Optional) - whether or not to use the master connection
	 * @return mixed - the affected rows, false on failure
	 */
	public function queryFirst($query, $params=array(), $use_master=false) {
		$result = $this->query($query, $params, $use_master);
		if (empty($result)) {
			return false;
		}
		else {
			return $result[0];
		}
	}
	
	/**
	 * method getErrorMessage.
	 * 	- returns the last error message caught
	 */
	public function getErrorMessage() {
		if ($this->pdo_exception)
			return $this->pdo_exception->getMessage();
		else
			return 'Database temporarily unavailable';
	}
	
	/**
	 * method getError.
	 * 	- returns the actual PDO exception
	 */
	public function getPDOException() {
		return $this->pdo_exception;
	}
	
	/**
	 * Validate the database in question is supported by your installation of PHP.
	 * @param string $driver The DSN prefix
	 * @return boolean true, the database is supported; false, the database is not supported.
	 */
	private function validateDriver($driver) {
		if (!in_array($driver, PDO::getAvailableDrivers())) {
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * Destructor.
	 * 	- release the PDO db connections
	 */
	function __destruct() {
		unset($this->pdo_master);
		unset($this->pdo_slave);
	}
}
