<?php

/**
 * Copyright (C) 2017 Spencer Mortensen
 *
 * This file is part of testphp.
 *
 * Testphp is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Testphp is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with testphp. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <spencer@testphp.org>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2017 Spencer Mortensen
 */

namespace TestPhp;

use Closure;

class Filesystem
{
	const TYPE_DIRECTORY = 1;
	const TYPE_FILE = 2;

	/** @var Closure */
	private $errorHandler;

	public function __construct()
	{
		// Suppress built-in PHP warnings that are inappropriately generated
		// under normal operating conditions
		$this->errorHandler = function () {};
	}

	public function read($path)
	{
		if (is_dir($path)) {
			return $this->readDirectory($path);
		}

		return $this->readFile($path);
	}

	private function readDirectory($path)
	{
		set_error_handler($this->errorHandler);

		$files = scandir($path, SCANDIR_SORT_NONE);

		if ($files === false) {
			// TODO: throw exception
			restore_error_handler();
			return null;
		}

		$contents = array();

		foreach ($files as $file) {
			if (($file === '.') || ($file === '..')) {
				continue;
			}

			$childPath = "{$path}/{$file}";
			$contents[$file] = $this->read($childPath);
		}

		restore_error_handler();
		return $contents;
	}

	private function readFile($path)
	{
		set_error_handler($this->errorHandler);

		$contents = self::getString(file_get_contents($path));

		restore_error_handler();
		return $contents;
	}

	public function write($path, $contents)
	{
		return $this->writeFile($path, $contents);
	}

	private function writeFile($path, $contents)
	{
		set_error_handler($this->errorHandler);

		$result = self::writeFileContents($path, $contents) ||
			(
				self::createDirectory(dirname($path)) &&
				self::writeFileContents($path, $contents)
			);

		restore_error_handler();
		return $result;
	}

	private static function createDirectory($path)
	{
		return mkdir($path, 0777, true);
	}

	private static function writeFileContents($path, $contents)
	{
		return file_put_contents($path, $contents) === strlen($contents);
	}

	public function search($expression)
	{
		return glob($expression);
	}

	public function isDirectory($path)
	{
		return is_dir($path);
	}

	public function getCurrentDirectory()
	{
		return self::getString(getcwd());
	}

	public function getAbsolutePath($path)
	{
		return self::getString(realpath($path));
	}

	private static function getString($value)
	{
		if (is_string($value)) {
			return $value;
		}

		return null;
	}
}
