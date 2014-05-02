<?php

namespace ManiaControl\Database;

use ManiaControl\Callbacks\TimerListener;
use ManiaControl\ManiaControl;

/**
 * Database Connection Class
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class Database implements TimerListener {
	/*
	 * Public Properties
	 */
	public $mysqli = null;
	public $migrationHelper = null;

	/*
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Construct database connection
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Get mysql server information
		$host = $this->maniaControl->config->database->xpath('host');
		$port = $this->maniaControl->config->database->xpath('port');
		$user = $this->maniaControl->config->database->xpath('user');
		$pass = $this->maniaControl->config->database->xpath('pass');

		if (!$host) {
			trigger_error("Invalid database configuration (host).", E_USER_ERROR);
		}
		if (!$port) {
			trigger_error("Invalid database configuration (port).", E_USER_ERROR);
		}
		if (!$user) {
			trigger_error("Invalid database configuration (user).", E_USER_ERROR);
		}
		if (!$pass) {
			trigger_error("Invalid database configuration (pass).", E_USER_ERROR);
		}

		$host = (string)$host[0];
		$port = (int)$port[0];
		$user = (string)$user[0];
		$pass = (string)$pass[0];

		// Enable mysqli Reconnect
		ini_set('mysqli.reconnect', 'on');

		// Open database connection
		$this->mysqli = @new \mysqli($host, $user, $pass, null, $port);
		if ($this->mysqli->connect_error) {
			trigger_error($this->mysqli->connect_error, E_USER_ERROR);
		}
		$this->mysqli->set_charset("utf8");

		$this->initDatabase();
		$this->optimizeTables();

		// Register Method which checks the Database Connection every 5 seconds
		$this->maniaControl->timerManager->registerTimerListening($this, 'checkConnection', 5000);

		// Create migration helper
		$this->migrationHelper = new MigrationHelper($maniaControl);
	}

	/**
	 * Connect to the defined database (create it if needed)
	 *
	 * @return bool
	 */
	private function initDatabase() {
		$dbName = $this->maniaControl->config->database->xpath('db_name');
		if (!$dbName) {
			trigger_error("Invalid database configuration (database).", E_USER_ERROR);
			return false;
		}
		$dbName = (string)$dbName[0];

		// Try to connect
		$result = $this->mysqli->select_db($dbName);
		if ($result) {
			return true;
		}

		// Create database
		$databaseQuery     = "CREATE DATABASE ?;";
		$databaseStatement = $this->mysqli->prepare($databaseQuery);
		if ($this->mysqli->error) {
			trigger_error($this->mysqli->error, E_USER_ERROR);
			return false;
		}
		$databaseStatement->bind_param('s', $dbName);
		$databaseStatement->execute();
		if ($databaseStatement->error) {
			trigger_error($databaseStatement->error, E_USER_ERROR);
			return false;
		}
		$databaseStatement->close();

		// Connect to new database
		$this->mysqli->select_db($dbName);
		if ($this->mysqli->error) {
			trigger_error("Couldn't select database '{$dbName}'. " . $this->mysqli->error, E_USER_ERROR);
			return false;
		}
		return true;
	}

	/**
	 * Optimize all existing tables
	 *
	 * @return bool
	 */
	private function optimizeTables() {
		$showQuery = "SHOW TABLES;";
		$result    = $this->mysqli->query($showQuery);
		if ($this->mysqli->error) {
			trigger_error($this->mysqli->error);
			return false;
		}
		$count = $result->num_rows;
		if ($count <= 0) {
			$result->close();
			return true;
		}
		$optimizeQuery = "OPTIMIZE TABLE ";
		$index         = 0;
		while ($row = $result->fetch_row()) {
			$tableName = $row[0];
			$optimizeQuery .= "`{$tableName}`";
			if ($index < $count - 1) {
				$optimizeQuery .= ", ";
			}
			$index++;
		}
		$result->close();
		$optimizeQuery .= ";";
		$this->mysqli->query($optimizeQuery);
		if ($this->mysqli->error) {
			trigger_error($this->mysqli->error);
			return false;
		}
		return true;
	}

	/**
	 * Check if Connection still exists every 5 seconds
	 *
	 * @param $time
	 */
	public function checkConnection($time) {
		if (!$this->mysqli->ping()) {
			$this->maniaControl->quit("The MySQL server has gone away");
		}
	}

	/**
	 * Destruct database connection
	 */
	public function __destruct() {
		if ($this->mysqli) {
			$this->mysqli->close();
		}
	}
}
