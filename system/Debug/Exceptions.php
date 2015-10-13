<?php namespace CodeIgniter\Debug;

class Exceptions
{

	/**
	 * Nesting level of the output buffering mechanism
	 *
	 * @var    int
	 */
	public $ob_level;

	//--------------------------------------------------------------------

	public function __construct()
	{
		$this->ob_level = ob_get_level();
	}

	//--------------------------------------------------------------------

	/**
	 * Responsible for registering the error, exception and shutdown
	 * handling of our application.
	 */
	public function initialize()
	{
		//Set the Exception Handler
		set_exception_handler([$this, 'exceptionHandler']);

		// Set the Error Handler
		// Don't think this is needed in PHP7?
//		set_error_handler(['\CodeIgniter\Core\Exceptions', 'errorHandler']);

		// Set the handler for shutdown to catch Parse errors
		// Do we need this in PHP7?
//		register_shutdown_function(['\CodeIgniter\Core\Exceptions', 'shutdownHandler']);
	}

	//--------------------------------------------------------------------

	/**
	 * Catches any uncaught errors and exceptions, including Fatal errors
	 * (Yay PHP7!). Will log the error, display it if display_errors is on,
	 * and fire an event that allows custom actions to be taken at this point.
	 *
	 * @param \Throwable $e
	 */
	public function exceptionHandler(\Throwable $exception)
	{
		// Get Exception Info
		$type    = get_class($exception);
		$code    = $exception->getCode();
		$message = $exception->getMessage();
		$file    = $exception->getFile();
		$line    = $exception->getLine();
		$trace   = $exception->getTrace();

		if (empty($message))
		{
			$message = '(null)';
		}

		// Log it

		// Fire an Event

		$view = 'production.html';

		if (str_ireplace(['off', 'none', 'no', 'false', 'null'], '', ini_get('display_errors')))
		{
			$view = 'exception';
		}

		// @todo Get template path from config
		$templates_path = '';
		if (empty($templates_path))
		{
			$templates_path = APPPATH.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR;
		}

		// Make a nicer title based on the type of Exception.
		$title = get_class($exception);

		if (is_cli())
		{
			$templates_path .= 'cli'.DIRECTORY_SEPARATOR;
		}
		else
		{
			header('HTTP/1.1 401 Unauthorized', true, 500);
			$templates_path .= 'html'.DIRECTORY_SEPARATOR;
		}

		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}

		ob_start();
		include($templates_path.'error_exception.php');
		$buffer = ob_get_contents();
		ob_end_clean();
		echo $buffer;
	}

	//--------------------------------------------------------------------

	/**
	 * Checks to see if any errors have happened during shutdown that
	 * need to be caught and handle them.
	 */
	public function shutdownHandler()
	{
		die('In Shutdown Handler');
	}

	//--------------------------------------------------------------------

	//--------------------------------------------------------------------
	// Display Methods
	//--------------------------------------------------------------------

	/**
	 * Clean Path
	 *
	 * This makes nicer looking paths for the error output.
	 *
	 * @param    string $file
	 *
	 * @return    string
	 */
	public static function cleanPath($file)
	{
		if (strpos($file, APPPATH) === 0)
		{
			$file = 'APPPATH/'.substr($file, strlen(APPPATH));
		}
		elseif (strpos($file, BASEPATH) === 0)
		{
			$file = 'BASEPATH/'.substr($file, strlen(BASEPATH));
		}
		elseif (strpos($file, SYSDIR) === 0)
		{
			$file = 'SYSDIR/'.substr($file, strlen(SYSDIR));
		}
		elseif (strpos($file, FCPATH) === 0)
		{
			$file = 'FCPATH/'.substr($file, strlen(FCPATH));
		}

		return $file;
	}

	//--------------------------------------------------------------------

	/**
	 * Describes memory usage in real-world units. Intended for use
	 * with memory_get_usage, etc.
	 *
	 * @param $bytes
	 *
	 * @return string
	 */
	public static function describeMemory(int $bytes): string
	{
		if ($bytes < 1024)
		{
			return $bytes.'B';
		}
		else if ($bytes < 1048576)
		{
			return round($bytes/1024, 2).'KB';
		}

		return round($bytes/1048576, 2).'MB';
	}

	//--------------------------------------------------------------------


	/**
	 * Creates a syntax-highlighted version of a PHP file.
	 *
	 * @param     $file
	 * @param     $lineNumber
	 * @param int $lines
	 *
	 * @return bool|string
	 */
	public static function highlightFile($file, $lineNumber, $lines = 15)
	{
		if (empty ($file) || ! is_readable($file))
		{
			return false;
		}

		// Set our highlight colors:
		if (function_exists('ini_set'))
		{
			ini_set('highlight.comment', '#767a7e; font-style: italic');
			ini_set('highlight.default', '#c7c7c7');
			ini_set('highlight.html', '#06B');
			ini_set('highlight.keyword', '#f1ce61;');
			ini_set('highlight.string', '#869d6a');
		}

		$source = @file_get_contents($file);

		if (empty($source))
		{
			return false;
		}

		$source = str_replace(["\r\n", "\r"], "\n", $source);
		$source = explode("\n", highlight_string($source, true));
		$source = str_replace('<br />', "\n", $source[1]);

		$source = explode("\n", str_replace("\r\n", "\n", $source));

		// Get just the part to show
		$start = $lineNumber - (int)round($lines / 2);
		$start = $start < 0 ? 0 : $start;

		// Get just the lines we need to display, while keeping line numbers...
		$source = array_splice($source, $start, $lines, true);

		// Used to format the line number in the source
		$format = '% '.strlen($start + $lines).'d';

		$out = '';
		// Because the highlighting may have an uneven number
		// of open and close span tags on one line, we need
		// to ensure we can close them all to get the lines
		// showing correctly.
		$spans = 1;

		foreach ($source as $n => $row)
		{
			$spans += substr_count($row, '<span') - substr_count($row, '</span');
			$row = str_replace(["\r", "\n"], ['', ''], $row);

			if ($n == $lineNumber)
			{
				preg_match_all('#<[^>]+>#', $row, $tags);
				$out .= sprintf("<span class='line highlight'><span class='number'>{$format}</span> %s\n</span>%s",
						$n + $start,
						strip_tags($row),
						implode('', $tags[0])
				);
			}
			else
			{
				$out .= sprintf('<span class="line"><span class="number">'.$format.'</span> %s', $n + $start, $row) ."\n";
			}
		}

		$out .= str_repeat('</span>', $spans);

		return '<pre><code>'.$out.'</code></pre>';
	}

	//--------------------------------------------------------------------

}