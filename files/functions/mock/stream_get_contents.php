<?php

namespace Lens;

use Lens_0_0_56\Lens\Evaluator\Agent;

function stream_get_contents($source, $maxlen = null, $offset = null)
{
	return eval(Agent::call(null, __FUNCTION__, func_get_args()));
}
