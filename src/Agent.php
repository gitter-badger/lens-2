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

abstract class Agent
{
	/** @var array */
	private static $actual;

	/** @var array */
	private static $calls;

	/** @var array */
	private static $classes;

	/** @var array */
	private static $objects;

	/** @var array */
	private static $map;

	public static function setExpected($expectedJson)
	{
		$expected = json_decode($expectedJson, true);

		foreach ($expected as $callArchive) {
			list($callableArchive, $argumentsArchive, $result) = current($callArchive);
			$arguments = current($argumentsArchive);
			list($objectArchive, $method) = current($callableArchive);
			list($objectId, $objectClass)  = current($objectArchive);

			self::$calls[$objectId][] = array($method, $arguments, $result);
			self::$classes[$objectId] = $objectClass;
		}
	}

	public static function replay(array $callable, array $arguments)
	{
		$result = self::getResult($callable);

		self::record($callable, $arguments, $result);

		return self::perform($result);
	}

	private static function getResult(array $callable)
	{
		$object = $callable[0];
		list($actualObjectId, $class) = self::getObjectProperties($object);

		$expectedObjectId = &self::$map[$actualObjectId];

		if ($expectedObjectId === null) {
			$expectedObjectId = self::getExpectedObjectId($object, $class);
		}

		if ($expectedObjectId === null) {
			return array(0, null);
		}

		$call = array_shift(self::$calls[$expectedObjectId]);

		if ($call === null) {
			return array(0, null);
		}

		$result = array_pop($call);

		return self::unpack($result);
	}

	private static function getObjectProperties($object)
	{
		$value = Archivist::archive($object);
		return current($value);
	}

	private static function getExpectedObjectId($object, $targetClass)
	{
		if (!is_array(self::$classes)) {
			return null;
		}

		foreach (self::$classes as $id => $class) {
			if ($class !== $targetClass) {
				continue;
			}

			unset(self::$classes[$id]);
			self::$objects[$id] = $object;
			return $id;
		}

		return null;
	}

	private static function unpack($token)
	{
		if (!is_array($token)) {
			return $token;
		}

		list($type, $value) = each($token);

		switch ($type) {
			default: return $value;
			case Archivist::TYPE_ARRAY: return self::unpackArray($value);
			case Archivist::TYPE_OBJECT: return self::unpackObject($value);
			case Archivist::TYPE_RESOURCE: return self::unpackResource($value);
		}
	}

	private static function unpackArray(array $array)
	{
		$output = array();

		foreach ($array as $key => $token) {
			$output[$key] = self::unpack($token);
		}

		return $output;
	}

	private static function unpackObject(array $object)
	{
		$id = $object['id'];

		$output = &self::$objects[$id];

		return $output;
	}

	private static function unpackResource(array $resource)
	{
		// TODO:
		return null;
	}

	public static function record(array $callable, array $arguments, array $result)
	{
		self::$actual[] = array($callable, $arguments, $result);

		return $result;
	}

	private static function perform(array $result)
	{
		list($action, $value) = $result;

		if ($action === 1) {
			echo json_encode($value);
			exit;
			throw $value;
		}

		return $value;
	}

	public static function getCalls()
	{
		if (!is_array(self::$actual)) {
			return array();
		}

		return self::$actual;
	}
}
