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

use SpencerMortensen\Paths\Paths;

class Finder
{
	/** @var string */
	const LENS = 'lens';

	/** @var string */
	const COVERAGE = 'coverage';

	/** @var string */
	const TESTS = 'tests';

	/** @var string */
	const AUTOLOAD = 'autoload.php';

	/** @var string */
	const SETTINGS = 'settings.ini';

	/** @var string */
	const SRC = 'src';

	/** @var string */
	const VENDOR = 'vendor';

	/** @var Paths */
	private $paths;

	/** @var Filesystem */
	private $filesystem;

	/** @var  string|null */
	private $project;

	/** @var string|null */
	private $lens;

	/** @var string|null */
	private $coverage;

	/** @var string|null */
	private $tests;

	/** @var string|null */
	private $autoload;

	/** @var Settings */
	private $settings;

	/** @var string|null */
	private $src;

	public function __construct(Paths $paths, Filesystem $filesystem)
	{
		$this->paths = $paths;
		$this->filesystem = $filesystem;
	}

	public function find(array &$paths)
	{
		$this->findLensTests($paths);

		// Simple tests might not exist within a Lens directory
		if ($this->lens === null) {
			return;
		}

		$this->findProject();
		$this->findSettings();

		$settings = new Settings($this->paths, $this->filesystem, $this->settings);

		$this->findSrc($settings);
		$this->findAutoload($settings);
		$this->findCoverage();
	}

	private function findLensTests(array &$paths)
	{
		if (0 < count($paths)) {
			$this->findLensAndTestsByPaths($paths);
		} else {
			$this->findLensAndTestsByCurrentDirectory();
			$paths[] = $this->tests;
		}
	}

	private function findLensAndTestsByPaths(array $paths)
	{
		foreach ($paths as &$path) {
			$data = $this->paths->deserialize($path);
			$path = $data->getAtoms();
		}

		$parent = $this->getParent($paths);

		if ($this->getTarget($parent, $atoms)) {
			$data->setAtoms($atoms);
			$this->lens = $this->paths->serialize($data);

			$atoms[] = self::TESTS;
			$data->setAtoms($atoms);
			$this->tests = $this->paths->serialize($data);
		} else {
			$atoms = $this->getDirectory($parent);
			$data->setAtoms($atoms);

			$this->tests = $this->paths->serialize($data);
		}
	}

	private function getParent(array $paths)
	{
		$n = count($paths);

		if ($n === 1) {
			return $paths[0];
		}

		$m = min(array_map('count', $paths));

		$parent = array();

		for ($i = 0; $i < $m; ++$i) {
			$atom = $paths[0][$i];

			for ($j = 1; $j < $n; ++$j) {
				if ($paths[$j][$i] !== $atom) {
					return $parent;
				}
			}

			$parent[$i] = $atom;
		}

		return $parent;
	}

	private function getTarget(array $atoms, &$output)
	{
		for ($i = count($atoms) - 1; 0 < $i; --$i) {
			if (($atoms[$i - 1] === self::LENS) && ($atoms[$i] === self::TESTS)) {
				$output = array_slice($atoms, 0, $i);
				return true;
			}
		}

		return false;
	}

	private function getDirectory(array $atoms)
	{
		$atom = end($atoms);

		if (is_string($atom) && (substr($atom, -4) === '.php')) {
			return array_slice($atoms, 0, -1);
		}

		return $atoms;
	}

	private function findLensAndTestsByCurrentDirectory()
	{
		$basePath = $this->filesystem->getCurrentDirectory();

		$data = $this->paths->deserialize($basePath);
		$atoms = $data->getAtoms();
		$atoms[] = self::LENS;

		$i = count($atoms) - 2;

		do {
			$data->setAtoms($atoms);
			$path = $this->paths->serialize($data);

			if ($this->filesystem->isDirectory($path)) {
				$this->lens = $path;
				$this->tests = $this->paths->join($this->lens, self::TESTS);
				return;
			}

			unset($atoms[$i]);
		} while (0 <= $i--);

		throw LensException::unknownLensDirectory();
	}

	private function findProject()
	{
		$this->project = dirname($this->lens);
	}

	private function findSettings()
	{
		$this->settings = $this->paths->join($this->lens, self::SETTINGS);
	}

	private function findSrc(Settings $settings)
	{
		$this->findSrcFromSettings($settings) ||
		$this->findSrcFromProject();

		if ($this->src === null) {
			throw LensException::unknownSrcDirectory();
		}

		$this->setSrc($settings);
	}

	private function findSrcFromSettings(Settings $settings)
	{
		$srcValue = $settings->getSrc();

		if ($srcValue === null) {
			return false;
		}

		$srcPath = $this->paths->join($this->project, $srcValue);
		$srcPath = $this->filesystem->getAbsolutePath($srcPath);
		return $this->isDirectory($srcPath, $this->src);
	}

	private function findSrcFromProject()
	{
		$srcPath = $this->paths->join($this->project, self::SRC);

		return $this->isDirectory($srcPath, $this->src);
	}

	private function isDirectory($path, &$variable)
	{
		if (!$this->filesystem->isDirectory($path)) {
			return false;
		}

		$variable = $path;
		return true;
	}

	private function setSrc(Settings $settings)
	{
		$srcValue = $this->paths->getRelativePath($this->project, $this->src);

		$settings->setSrc($srcValue);
	}

	private function findAutoload(Settings $settings)
	{
		$this->findAutoloadFromSettings($settings) ||
		$this->findAutoloadFromLens() ||
		$this->findAutoloadFromProject();

		if ($this->autoload === null) {
			throw LensException::unknownAutoloadFile();
		}

		$this->setAutoload($settings);
	}

	private function findAutoloadFromSettings(Settings $settings)
	{
		$autoloadValue = $settings->getAutoload();

		if ($autoloadValue === null) {
			return false;
		}

		$autoloadPath = $this->paths->join($this->project, $autoloadValue);
		$autoloadPath = $this->filesystem->getAbsolutePath($autoloadPath);
		return $this->isFile($autoloadPath, $this->autoload);
	}

	private function findAutoloadFromLens()
	{
		$autoloadPath = $this->paths->join($this->lens, self::AUTOLOAD);
		return $this->isFile($autoloadPath, $this->autoload);
	}

	private function findAutoloadFromProject()
	{
		$autoloadPath = $this->paths->join($this->project, self::VENDOR, self::AUTOLOAD);
		return $this->isFile($autoloadPath, $this->autoload);
	}

	private function isFile($path, &$variable)
	{
		if (!$this->filesystem->isFile($path)) {
			return false;
		}

		$variable = $path;
		return true;
	}

	private function setAutoload(Settings $settings)
	{
		$value = $this->paths->getRelativePath($this->project, $this->autoload);

		$settings->setAutoload($value);
	}

	private function findCoverage()
	{
		$this->coverage = $this->paths->join($this->lens, self::COVERAGE);
	}

	public function getProject()
	{
		return $this->project;
	}

	public function getLens()
	{
		return $this->lens;
	}

	public function getCoverage()
	{
		return $this->coverage;
	}

	public function getTests()
	{
		return $this->tests;
	}

	public function getAutoload()
	{
		return $this->autoload;
	}

	public function getSettings()
	{
		return $this->settings;
	}

	public function getSrc()
	{
		return $this->src;
	}
}