<?php namespace Cohensive\Validation;

use Illuminate\Validation\Factory as BaseFactory;

class Factory extends BaseFactory {

	/**
	 * Resolve a new Validator instance.
	 *
	 * @param  array  $data
	 * @param  array  $rules
	 * @param  array  $messages
	 * @return \Cohensive\Validation\Validator
	 */
	protected function resolve($data, $rules, $messages)
	{
		if (is_null($this->resolver))
		{
			return new Validator($this->translator, $data, $rules, $messages);
		}
		else
		{
			return call_user_func($this->resolver, $this->translator, $data, $rules, $messages);
		}
	}

}
