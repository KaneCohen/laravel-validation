<?php
namespace Cohensive\Validation;

use Closure;
use Illuminate\Validation\Factory as BaseFactory;
use Illuminate\Validation\PresenceVerifierInterface;

class Factory extends BaseFactory
{

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

	/**
	 * Set the Presence Verifier implementation.
	 *
	 * @param  \Cohensive\Validation\PresenceVerifierInterface  $presenceVerifier
	 * @return void
	 */
	public function setPresenceVerifier(PresenceVerifierInterface $presenceVerifier)
	{
		$this->verifier = $presenceVerifier;
	}

}
