<?php

final class CDFConsoleApplication
{
	public function __construct()
	{
		// register handlers
		//set_exception_handler(array($this, 'errorHandler'));
		//set_error_handler(array($this, 'errorHandler'), E_ERROR);
	}

	//
	// Error handler
	//

	static function errorHandler()
	{
		// kill any locks
		self::clearLock();

		return true;
	}

	//
	// Logging
	//

	const LOGMESSAGE_DEBUG = 0;
	const LOGMESSAGE_NORMAL = 1;
	const LOGMESSAGE_CRITICAL = 2;

	/** @var bool */
	private $_debug;
	/** @var resource|null */
	private $_logFile = null; // if null, stdout

	/**
	 * Sets where to write a disk-based log. Null to use STDOUT.
	 * You should make the path exclusive for writing log files, do not mix it with other content.
	 * @param string $path
	 * @return void
	 */
	public function setLogPath($path)
	{
		if($path != null)
		{
			// only set up a log file if not already done so
			if($this->_logFile == null)
			{
				// generate a filename
				$fName = sprintf('%s/%s.log', $path, date('Ymd.His'));
				echo $fName;
				$res = @fopen($fName, 'wt');
				if($res !== false)
					$this->_logFile = $res;
			}
		}
		else
		{
			if($this->_logFile != null)
			{
				fclose($this->_logFile);
				$this->_logFile = null;
			}
		}
	}

	/**
	 * Outputs a message to the current log.
	 * @param string $msg Message to write.
	 * @param int $severity Value of LOGMESSAGE constants.
	 * @return void
	 */
	public function writeLog($msg, $severity = self::LOGMESSAGE_DEBUG)
	{
		// if message is debug and we're not running debug, skip writing.
		if($severity === self::LOGMESSAGE_DEBUG && !$this->getDebug())
			return;
		$msg = trim($msg);
		if($severity === self::LOGMESSAGE_CRITICAL)
			$msg = 'ERROR: ' . $msg;

		// output message
		$msg = sprintf("[%s]: %s\n", date('H:i:s.u'), $msg);
		if($this->_logFile != null)
			@fwrite($this->_logFile, $msg);
		else
			echo $msg; // std out
	}

	/**
	 * @return bool
	 */
	public function getDebug()
	{
		return $this->_debug;
	}

	/**
	 * @param bool $debug
	 * @return void
	 */
	public function setDebug($debug)
	{
		$this->_debug = $debug;
	}

	//
	// Locking
	//

	/** @var bool */
	private static $_locked = false;
	/** @var resource  */
	private static $_lockhandle;

	private static function getLockFilename()
	{
		global $argv;
		return sprintf('%s%s.lock', sys_get_temp_dir(), basename($argv[0]));
	}

	/**
	 * Sets this instance of the application in a "locked" state, preventing other instances.
	 * @param bool $force If true, attempts to lock regardless.
	 * @return bool True if successfully able to lock, false otherwise
	 */
	public static function setLocked($force = false)
	{
		if(self::$_locked)
			return true; // already locked

		$fname = self::getLockFilename();

		if(!$force && file_exists($fname))
			return false; // cannot lock

		// attempt to create lock
		self::$_lockhandle = @fopen($fname, 'w');
		if(self::$_lockhandle !== false)
		{
			// locking successful, keep file open so it can't be written to
			self::$_locked = true;
		}

		return self::$_locked;
	}

	/**
	 * Removes the application from a "locked" state.
	 * @return void
	 */
	public static function clearLock()
	{
		if(self::$_lockhandle !== null)
		{
			@fclose(self::$_lockhandle);
			self::$_lockhandle = null;
		}

		@unlink(self::getLockFilename());
		self::$_locked = false;
	}

	/**
	 * Returns true if this instance of the application or another instance is in a "locked" state.
	 * @return bool
	 */
	public static function isLocked()
	{
		if(self::$_locked)
			return true; //  this instance of the application has the lock and is locked

		if(file_exists(self::getLockFilename()))
			return true; // external application has the lock

		return false; // no lock file found
	}
}
