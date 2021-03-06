<?php

/**
 * Copyright (C) 2017 Spencer Mortensen
 *
 * This file is part of parallel-processor.
 *
 * Parallel-processor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Parallel-processor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with parallel-processor. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <spencer@lens.guide>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2017 Spencer Mortensen
 */

namespace Lens_0_0_56\SpencerMortensen\ParallelProcessor\Stream\Exceptions;

use Exception;

class ReadIncompleteException extends Exception
{
	const CODE_ERROR = 0;

	/** @var array */
	private $data;

	public function __construct($bytesRead)
	{
		$code = self::CODE_ERROR;

		$message = "Read {$bytesRead} bytes from the input stream before the connection was interrupted.";

		$data = array(
			'read' => $bytesRead
		);

		parent::__construct($message, $code);

		$this->data = $data;
	}

	public function getData()
	{
		return $this->data;
	}
}
