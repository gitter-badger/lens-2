<?php

namespace Lens;

use Lens_0_0_56\Lens\Evaluator\Agent;

function rename($old_name, $new_name, $context = null)
{
	return eval(Agent::call(null, __FUNCTION__, func_get_args()));
}
