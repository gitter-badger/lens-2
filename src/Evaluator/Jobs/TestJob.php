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

namespace Lens\Evaluator\Jobs;

use Lens\Lens;
use Lens\Evaluator\Test;
use SpencerMortensen\Exceptions\Exceptions;
use SpencerMortensen\ParallelProcessor\Fork\ForkJob;
use SpencerMortensen\ParallelProcessor\Shell\ShellJob;
use SpencerMortensen\ParallelProcessor\ServerProcess;

class TestJob implements ForkJob, ShellJob
{
	/** @var string */
	private $executable;

	/** @var string */
	private $srcDirectory;

	/** @var string */
	private $autoloadPath;

	/** @var string */
	private $namespace;

	/** @var array */
	private $uses;

	/** @var string */
	private $prePhp;

	/** @var null|array */
	private $script;

	/** @var string */
	private $postPhp;

	/** @var null|ServerProcess */
	private $process;

	/** @var null|array */
	private $results;

	/** @var null|array */
	private $coverage;

	public function __construct($executable, $srcDirectory, $autoloadPath, $namespace, array $uses, $prePhp, array $script = null, $postPhp, &$process = null, array &$results = null, array &$coverage = null)
	{
		$this->executable = $executable;
		$this->srcDirectory = $srcDirectory;
		$this->autoloadPath = $autoloadPath;
		$this->namespace = $namespace;
		$this->uses = $uses;
		$this->prePhp = $prePhp;
		$this->script = $script;
		$this->postPhp = $postPhp;
		$this->process = &$process;
		$this->results = &$results;
		$this->coverage = &$coverage;
	}

	public function getCommand()
	{
		$arguments = array($this->srcDirectory, $this->autoloadPath, $this->namespace, $this->uses, $this->prePhp, $this->script, $this->postPhp);
		$serialized = serialize($arguments);
		$compressed = gzdeflate($serialized, -1);
		$encoded = base64_encode($compressed);

		return "{$this->executable} --internal-test={$encoded}";
	}

	public function start()
	{
		$worker = $this->process;
		$test = new Test($this->srcDirectory, $this->autoloadPath);

		$sendResults = function () use ($worker, $test) {
			$results = array(
				'pre' => $test->getPreState(),
				'post' => $test->getPostState()
			);

			$coverage = $test->getCoverage();

			$worker->sendResult(array($results, $coverage));
		};

		Exceptions::on($sendResults);

		$test->run($this->namespace, $this->uses, $this->prePhp, $this->script, $this->postPhp);

		Exceptions::off();

		call_user_func($sendResults);
	}

	public function stop($message)
	{
		list($this->results, $this->coverage) = $message;
	}
}
