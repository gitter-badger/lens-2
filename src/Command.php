<?php

/**
 * Copyright (C) 2017 Spencer Mortensen
 *
 * This file is part of Lens.
 *
 * Lens is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Lens is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Lens. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <spencer@lens.guide>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2017 Spencer Mortensen
 */

namespace Lens;

use Exception;
use Lens\Evaluator\Evaluator;
use Lens\Evaluator\Processor;
use Lens\Reports\Tap;
use Lens\Reports\Text;
use SpencerMortensen\Paths\Paths;
use Throwable;

class Command
{
	/** @var integer */
	private static $maximumLineLength = 96;

	/** @var string */
	private $executable;

	/** @var Displayer */
	private $displayer;

	/** @var Logger */
	private $logger;

	/** @var boolean */
	private static $isInternalCommand;

	public function __construct()
	{
		set_error_handler(array($this, 'errorHandler'));
		set_exception_handler(array($this, 'exceptionHandler'));

		$this->displayer = new Displayer();
		$this->logger = new Logger('lens');
		$this->executable = $GLOBALS['argv'][0];

		$this->run();

		restore_exception_handler();
		restore_error_handler();
	}

	// lens --version  # get the installed version of Lens
	// lens --report=text|tap|xunit --coverage=none|html|clover|crap4j|text $path $path ...  # run the specified tests
	// lens --internal-coverage=... # INTERNAL: get code coverage
	// lens --internal-test=... # INTERNAL: get test results
	private function run()
	{
		$options = array();

		$parser = new OptionsParser($GLOBALS['argv']);

		if ($parser->getLongKeyValue($options)) {
			list($key, $value) = each($options);

			$this->getWorker($key, $value);

			return;
		}

		if ($parser->getLongFlag($options)) {
			if (isset($options['version'])) {
				$this->getVersion();
			} else {
				// TODO: throw exception
			}

			return;
		}

		$paths = array();

		while ($parser->getValue($paths));

		$this->getRunner($paths);
	}

	private function getWorker($name, $value)
	{
		$worker = new Worker($this->executable);
		$worker->run($name, $value);
	}

	private function getVersion()
	{
		echo "lens 0.0.39\n";
		exit(0);
	}

	private function getRunner(array $paths)
	{
		$filesystem = new Filesystem();
		$platform = Paths::getPlatformPaths();
		$settingsFile = new IniFile($filesystem);
		$settings = new Settings($settingsFile, $this->logger);
		$browser = new Browser($filesystem, $platform);
		$parser = new SuiteParser();
		$processor = new Processor();
		$evaluator = new Evaluator($this->executable, $filesystem, $processor);
		$verifier = new Verifier();
		$report = new Text();
		$web = new Web($filesystem);

		$runner = new Runner($settings, $filesystem, $platform, $browser, $parser, $evaluator, $verifier, $report, $web);
		$runner->run($paths);
	}

	public function errorHandler($level, $message, $file, $line)
	{
		throw LensException::error($level, trim($message), $file, $line);
	}

	/**
	 * @param Throwable|Exception $exception
	 */
	public function exceptionHandler($exception)
	{
		try {
			throw $exception;
		} catch (LensException $throwable) {
		} catch (Throwable $throwable) {
			$exception = LensException::exception($throwable);
		} catch (Exception $throwable) {
			$exception = LensException::exception($throwable);
		}

		$severity = $exception->getSeverity();
		$code = $exception->getCode();
		$message = $exception->getMessage();
		$data = $exception->getData();

		$output = $this->getSyslogText($severity, $code, $message, $data);
		$this->logger->log($severity, $output);

		if (self::$isInternalCommand !== true) {
			$help = $exception->getHelp();

			$output = $this->getStderrText($severity, $code, $message, $help, $data);
			file_put_contents("php://stderr", "{$output}\n");
		}

		exit($code);
	}

	private function getStderrText($severity, $code, $message, $help, $data)
	{
		$output = self::getSeverityText($severity) . " {$code}: {$message}";

		if (0 < count($help)) {
			$output .= "\n\nTROUBLESHOOTING\n\n" . $this->getHelpText($help);
		}

		if (0 < count($data)) {
			$output .= "\n\nINFORMATION\n\n" . $this->getDataText($data);
		}

		return $output;
	}

	private function getSyslogText($severity, $code, $message, $data)
	{
		$output = self::getSeverityText($severity) . " {$code}: {$message}";

		if (0 < count($data)) {
			$output .= ' ' . json_encode($data);
		}

		return $output;
	}

	private static function getSeverityText($severity)
	{
		switch ($severity) {
			case LensException::SEVERITY_NOTICE:
				return 'Note';

			case LensException::SEVERITY_WARNING:
				return 'Warning';

			default:
				return 'Error';
		}
	}

	private function getHelpText(array $help)
	{
		$output = array();

		foreach ($help as $paragraph) {
			$line = self::wrap($paragraph);
			$line = self::pad($line, '   ');
			$line = substr_replace($line, '*', 1, 1);

			$output[] = $line;
		}

		return implode("\n\n", $output);
	}

	private static function wrap($string)
	{
		return wordwrap($string, self::$maximumLineLength, "\n", true);
	}

	private static function pad($string, $prefix)
	{
		$pattern = self::getPattern('^(.+)$', 'm');
		$replacement = preg_quote($prefix) . '$1';

		return preg_replace($pattern, $replacement, $string);
	}

	private static function getPattern($expression, $flags = null)
	{
		$delimiter = "\x03";

		return "{$delimiter}{$expression}{$delimiter}{$flags}";
	}

	private function getDataText(array $data)
	{
		$output = array();

		foreach ($data as $key => $value) {
			$keyText = ucfirst($key);
			$valueText = $this->displayer->display($value);

			$output[] = " * {$keyText}: {$valueText}";
		}

		return implode("\n", $output);
	}

	public static function setIsInternalCommand($isInternalCommand)
	{
		self::$isInternalCommand = $isInternalCommand;
	}
}
