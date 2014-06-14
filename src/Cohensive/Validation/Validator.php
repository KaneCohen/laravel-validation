<?php
namespace Cohensive\Validation;

use Closure;
use Illuminate\Validation\Validator as BaseValidator;
use Illuminate\Support\Contracts\MessageProviderInterface;

class Validator extends BaseValidator implements MessageProviderInterface
{

	/**
	 * The non iterable validation rules.
	 *
	 * @var array
	 */
	protected $nonIterableRules = array('Exists');

	/**
	 * All of the custom validator replacements.
	 *
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Validate a given attribute against a rule.
	 *
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @return void
	 */
	protected function validate($attribute, $rule)
	{
		list($rule, $parameters) = $this->parseRule($rule);

		// We will get the value for the given attribute from the array of data and then
		// verify that the attribute is indeed validatable. Unless the rule implies
		// that the attribute is required, rules are not run for missing values.
		$value = $this->getValue($attribute);

		$method = "validate{$rule}";

		if ($this->isIterable($attribute, $value))
		{
			// Required to test items even if array is empty.
			$value = empty($value) ? array(null) : $value;

			foreach ($value as $item)
			{
				$validatable = $this->isValidatable($rule, $attribute, $item);

				if ($validatable and ! $this->$method($attribute, $item, $parameters, $this))
				{
					$this->addFailure($attribute, $rule, $parameters);
				}
			}
		}
		else
		{
			$validatable = $this->isValidatable($rule, $attribute, $value);

			if ($validatable and ! $this->$method($attribute, $value, $parameters, $this))
			{
				$this->addFailure($attribute, $rule, $parameters);
			}
		}

	}

	protected function isIterable($attribute, $rule)
	{
		$value = $this->getValue($attribute);

		// Preg Match checks if asterisk is a wildcard, not part of an attribute name.
		// Possible wildcard positions: *:foo | foo:*:bar | foo:*
		if (is_array($value) and preg_match('/(^|\:)\*(\:|$)/', $attribute))
		{
			// Do not iterate over value if rule is one of nonIterableRules (namely, Exists or any Extended).
			if (in_array($rule, $this->nonIterableRules))
				return false;

			return true;
		}

		return false;
	}

	/**
	 * Get the value of a given attribute.
	 *
	 * @param  string  $attribute
	 * @return mixed
	 */
	protected function getValue($attribute)
	{
		if ( ! is_null($value = $this->arrayGet($this->data, $attribute)))
		{
			return $value;
		}
		elseif ( ! is_null($value = $this->arrayGet($this->files, $attribute)))
		{
			return $value;
		}
	}

	/*
	 * Get the size of an attribute.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return mixed
	 */
	protected function getSize($attribute, $value)
	{
		$hasNumeric = $this->hasRule($attribute, $this->numericRules);

		// This method will determine if the attribute is a number, string, or file and
		// return the proper size accordingly. If it is a number, then number itself
		// is the size. If it is a file, we take kilobytes, and for a string the
		// entire length of the string will be considered the attribute size.
		if (is_numeric($value) and $hasNumeric)
		{
			return $value;
		}
		elseif (is_array($value))
		{
			return count($value);
		}
		elseif ($value instanceof File)
		{
			return $value->getSize() / 1024;
		}
		else
		{
			return $this->getStringSize($value);
		}
	}

	/**
	 * Validate that a required attribute exists.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateRequired($attribute, $value)
	{
		if (is_null($value))
		{
			return false;
		}
		elseif (is_string($value) and trim($value) === '')
		{
			return false;
		}
		elseif (is_array($value) and empty($value))
		{
			return false;
		}
		elseif ($value instanceof File)
		{
			return (string) $value->getPath() != '';
		}

		return true;
	}

	/**
	 * Get the number of attributes in a list that are present.
	 *
	 * @param  array  $attributes
	 * @return int
	 */
	protected function getPresentCount($attributes)
	{
		$count = 0;

		foreach ($attributes as $attribute)
		{
			if ( ! is_null($this->getValue($attribute)))
			{
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Validate that two attributes match.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return void
	 */
	protected function validateSame($attribute, $value, $parameters)
	{
		$other = $parameters[0];

		return ! is_null($otherValue = $this->getValue($other)) and $value == $otherValue;
	}

	/**
	 * Validate that an attribute is different from another attribute.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateDifferent($attribute, $value, $parameters)
	{
		$other = $parameters[0];

		return ! is_null($otherValue = $this->getValue($other)) and $value != $otherValue;
	}

	/**
	 * Get the displayable name of the attribute.
	 *
	 * @param  string  $attribute
	 * @return string
	 */
	protected function getAttribute($attribute)
	{
		if (strpos($attribute, ':') !== false) {
			$attribute = preg_replace('/:((\d+)|(\*)):/', ':', $attribute);
		}

		return parent::getAttribute($attribute);
	}

	/**
	 * Replace all error message place-holders with actual values.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function doReplacements($message, $attribute, $rule, $parameters)
	{
		$message = str_replace(':attribute', $this->getAttribute($attribute), $message);

		if (method_exists($this, $replacer = "replace{$rule}"))
		{
			$message = $this->$replacer($message, $attribute, $rule, $parameters);
		}
		elseif (isset($this->replacements[studly_case($rule)]))
		{
			$message = $this->replacements[$rule]($message, $attribute, $rule, $parameters);
		}

		return $message;
	}

	/**
	 * Register an array of custom validator extensions.
	 *
	 * @param  array  $extensions
	 * @return void
	 */
	public function addExtensions(array $extensions)
	{
		foreach ($extensions as $rule => $extension)
		{
			$this->addExtension($rule, $extension);
		}
	}

	/**
	 * Register an array of custom implicit validator extensions.
	 *
	 * @param  array  $extensions
	 * @return void
	 */
	public function addImplicitExtensions(array $extensions)
	{
		$this->addExtensions($extensions);

		foreach ($extensions as $rule => $extension)
		{
			$this->implicitRules[] = studly_case($rule);
			$this->nonIterableRules[] = studly_case($rule);
		}
	}

	/**
	 * Register a custom validator extension.
	 *
	 * @param  string   $rule
	 * @param  \Closure|string  $extension
	 * @return void
	 */
	public function addExtension($rule, $extension)
	{
		$this->extensions[$rule] = $extension;
		$this->nonIterableRuleVys[] = studly_case($rule);
	}

	/**
	 * Register a custom implicit validator extension.
	 *
	 * @param  string   $rule
	 * @param  \Closure  $extension
	 * @return void
	 */
	public function addImplicitExtension($rule, $extension)
	{
		$this->addExtension($rule, $extension);

		$this->implicitRules[] = studly_case($rule);
		$this->nonIterableRules[] = studly_case($rule);
	}


	/**
	 * Get the array of custom validator replacements.
	 *
	 * @return array
	 */
	public function getReplacements()
	{
		return $this->replacements;
	}

	/**
	 * Register an array of custom validator replacements.
	 *
	 * @param  array  $replacements
	 * @return void
	 */
	public function addReplacements(array $replacements)
	{
		foreach ($replacements as $rule => $replacement)
		{
			$this->addReplacement($rule, $replacement);
		}
	}

	/**
	 * Register a custom validator replacement.
	 *
	 * @param  string   $rule
	 * @param  \Closure|string  $replacement
	 * @return void
	 */
	public function addReplacement($rule, $replacement)
	{
		$this->replacements[studly_case($rule)] = $replacement;
	}


	/**
	 * Get an item from an array using "dot" notation and "wildcards".
	 *
	 * @param  array    $array
	 * @param  string   $key
	 * @param  mixed    $default
	 * @return mixed
	 */
	public function arrayGet($array, $key, $default = null)
	{
		if (is_null($key)) return $array;

		if (isset($array[$key])) return $array[$key];

		// Store resulting array if key contains wildcard.
		$deepArray = array();
		$keys = explode(':', $key);
		foreach ($keys as $n => $segment)
		{
			if ($segment == '*') {
				// Get the rest of the keys besides current one.
				$keySlice = array_slice($keys, $n+1);
				// Generate new dot notation key string.
				$innerKey = implode(':', $keySlice);
				if (is_array($array))
				{
					foreach ($array as $item)
					{
						// Empty slice - last segment is a wildcard.
						if (empty($keySlice))
						{
							// Last segment is a wildcard. Put item into deepArray which will be returned
							// containing all of the items of the current array.
							$deepArray[] = $item;
						}
						else
						{
							// Pass current array item deeper.
							$innerItem = $this->arrayGet($item, $innerKey, $default);
							if (is_array($innerItem) and count(array_keys($keys, '*')) > 1)
							{
								// Multiple wildcards, add each item of inner array to the resulting new array.
								foreach ($innerItem as $innerItem)
								{
									$deepArray[] = $innerItem;
								}
							}
							else
							{
								// Only one wildcard in current key string. Add whole inner array to the resulting array.
								$deepArray[] = $innerItem;
							}
						}
					}
					// Return new resulting array.
					return $deepArray;
				}
				elseif ($n == count($keys)-1)
				{
					// This is the last key, so we can simply return whole array.
					return $array;
				}
				else
				{
					// This is not the last key and $array is not really an array
					// so we can't proceed deeper. Return default.
					return value($default);
				}
			}
			elseif ( ! is_array($array) or ! array_key_exists($segment, $array))
			{
				return value($default);
			}

			$array = $array[$segment];
		}

		return $array;
	}

}
