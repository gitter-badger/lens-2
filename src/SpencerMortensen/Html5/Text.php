<?php

/**
 * Copyright (C) 2018 Spencer Mortensen
 *
 * This file is part of Html5.
 *
 * Html5 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Html5 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Html5. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <spencer@lens.guide>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2018 Spencer Mortensen
 */

namespace Lens_0_0_56\SpencerMortensen\Html5;

class Text implements Node
{
	/** @var string */
	private $text;

	public function __construct($text)
	{
		$this->text = $text;
	}

	public function __toString()
	{
		$text = htmlspecialchars($this->text, ENT_HTML5 | ENT_DISALLOWED | ENT_NOQUOTES, 'UTF-8');
		$text = str_replace("\t", '    ', $text);
		$text = str_replace(' ', '&nbsp;', $text);

		return $text;
	}
}
