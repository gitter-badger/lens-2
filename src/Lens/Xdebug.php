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

namespace Lens_0_0_56\Lens;

class Xdebug
{
	/** @var int */
	private $options;

	public static function isEnabled()
	{
		return function_exists('xdebug_start_code_coverage');
	}

	public function __construct($isExecuted)
	{
		$this->options = $isExecuted ? 0 : XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE;
	}

	public function start()
	{
		if (function_exists('xdebug_start_code_coverage')) {
			xdebug_start_code_coverage($this->options);
		}
	}

	public function stop()
	{
		if (function_exists('xdebug_stop_code_coverage')) {
			xdebug_stop_code_coverage(false);
		}
	}

	public function getCoverage()
	{
		if (function_exists('xdebug_get_code_coverage')) {
			$coverage = xdebug_get_code_coverage();
			xdebug_stop_code_coverage(true);
			return $this->getLineNumbers($coverage);
		}

		return null;
	}

	private function getLineNumbers(array $coverage)
	{
		$output = [];

		foreach ($coverage as $file => $status) {
			$output[$file] = $this->getFileLineNumbers($status);
		}

		return $output;
	}

	private function getFileLineNumbers(array $status)
	{
		$output = [];

		foreach ($status as $lineNumber => $lineStatus) {
			$output[] = $lineNumber - 1;
		}

		return $output;
	}
}
