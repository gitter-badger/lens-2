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

use Lens_0_0_56\Lens\Archivist\Archives\ObjectArchive;
use Lens_0_0_56\Lens\Php\Namespacing;

class Formatter
{
	/** @var Namespacing */
	private $namespacing;

	/** @var array */
	private $objectNames;

	/** @var Displayer */
	private $displayer;

	/** @var string */
	private $currentDirectory;

	public function __construct(Namespacing $namespacing, array $objectNames)
	{
		$this->namespacing = $namespacing;
		$this->objectNames = $objectNames;
		$this->displayer = new Displayer();
		$this->currentDirectory = getcwd(); // TODO
	}

	public function getOutput($string)
	{
		$stringText = $this->displayer->display($string);

		return "echo {$stringText};";
	}

	public function getVariable($name, $value)
	{
		$valueText = $this->displayer->display($value);

		return "\${$name} = {$valueText};";
	}

	public function getGlobal($name, $value)
	{
		$valueText = $this->displayer->display($value);

		return "\$GLOBALS['{$name}'] = {$valueText};";
	}

	public function getConstant($name, $value)
	{
		$valueText = $this->displayer->display($value);

		return "define('{$name}', {$valueText});";
	}

	public function getException(ObjectArchive $exception)
	{
		$exceptionText = $this->displayer->display($exception);

		return "throw {$exceptionText};";
	}

	public function getError(array $error)
	{
		list($level, $message, $file, $line) = $error;

		$nameText = self::getErrorLevelName($level);

		$output = "{$nameText}: ";

		if (is_string($file)) {
			$file = rtrim($this->getRelativePath($file), '/');
			$fileText = self::getFilePosition($file, $line);

			$output .= "{$fileText}: ";
		}

		$output .= $message;

		return $output;
	}

	private static function getErrorLevelName($level)
	{
		switch ($level)
		{
			case E_ERROR: return 'E_ERROR';
			case E_WARNING: return 'E_WARNING';
			case E_PARSE: return 'E_PARSE';
			case E_NOTICE: return 'E_NOTICE';
			case E_CORE_ERROR: return 'E_CORE_ERROR';
			case E_CORE_WARNING: return 'E_CORE_WARNING';
			case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
			case E_USER_ERROR: return 'E_USER_ERROR';
			case E_USER_WARNING: return 'E_USER_WARNING';
			case E_USER_NOTICE: return 'E_USER_NOTICE';
			case E_STRICT: return 'E_STRICT';
			case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: return 'E_DEPRECATED';
			case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
			case E_ALL: return 'E_ALL';
			default: return null;
		}
	}

	// TODO: abstract this out and use it elsewhere
	private function getRelativePath($targetPath)
	{
		$currentTrail = self::getTrail($this->currentDirectory);
		$targetTrail = self::getTrail($targetPath);

		$n = min(count($currentTrail), count($targetTrail));

		for ($i = 0; ($i < $n) && ($currentTrail[$i] === $targetTrail[$i]); ++$i);

		$relativeDirectory = str_repeat('../', count($currentTrail) - $i);

		if (0 < count($targetTrail)) {
			$relativeDirectory .= implode('/', array_slice($targetTrail, $i)) . '/';
		}

		return $relativeDirectory;
	}

	private static function getTrail($path)
	{
		if (strlen($path) === 0) {
			return [];
		}

		return explode('/', $path);
	}

	private static function getFilePosition($file, $line)
	{
		$displayer = new Displayer();

		if (is_string($file)) {
			$fileText = $displayer->display($file);
		} else {
			$fileText = null;
		}

		$lineText = (string)$line;

		return "{$fileText} (line {$lineText})";
	}

	public function getCall(array $call, $action)
	{
		list($context, $method, $arguments) = $call;

		if ($context === null) {
			$relativeMethod = $this->namespacing->getRelativeFunction($method);
			return $this->getFunctionCall($relativeMethod, $arguments) . $this->getActionText($action);
		}

		if (is_string($context)) {
			return $this->getStaticMethodCall($context, $method, $arguments, $action);
		}

		return $this->getMethodCall($context, $method, $arguments, $action);
	}

	private function getStaticMethodCall($class, $method, array $arguments, $action)
	{
		$relativeContext = $this->namespacing->getRelativeClass($class);
		$functionText = $this->getFunctionCall($method, $arguments);
		$actionText = $this->getActionText($action);

		return "{$relativeContext}::{$functionText}{$actionText}";
	}

	private function getMethodCall(ObjectArchive $object, $method, array $arguments, $action)
	{
		if ($method === '__construct') {
			$absoluteClass = $object->getClass();
			$relativeClass = $this->namespacing->getRelativeClass($absoluteClass);
			$argumentsPhp = $this->getArgumentsText($arguments);

			if ($action === null) {
				$actionText = '';
			} else {
				$actionText = $this->getActionText($action);
			}

			// TODO: use variable name (if available)

			return "new {$relativeClass}({$argumentsPhp});{$actionText}";
		}

		$objectText = $this->getObjectText($object);
		$functionText = $this->getFunctionCall($method, $arguments);
		$actionText = $this->getActionText($action);

		return "{$objectText}->{$functionText}{$actionText}";
	}

	private function getActionText($action)
	{
		if ($action === null) {
			$action = 'return null;';
		}

		return " // {$action}";
	}

	private function getFunctionCall($function, array $arguments)
	{
		$argumentsText = $this->getArgumentsText($arguments);

		return "{$function}({$argumentsText});";
	}

	private function getArgumentsText(array $argumentsArchive)
	{
		if (count($argumentsArchive) === 0) {
			return '';
		}

		$output = [];

		foreach ($argumentsArchive as $argumentValueArchive) {
			$output[] = $this->displayer->display($argumentValueArchive);
		}

		return implode(', ', $output);
	}

	private function getObjectText(ObjectArchive $objectArchive)
	{
		$id = $objectArchive->getId();

		if (isset($this->objectNames[$id])) {
			$objectName = $this->objectNames[$id];
			return "\${$objectName}";
		}

		return $this->displayer->display($objectArchive);
	}
}
