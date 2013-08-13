# Extended Laravel Validation

This class extends Laravel 4 Validation package changing some basic functionality
to provide validation of data with wildcards. How does wildcrads work:

![wildcrads](https://f.cloud.github.com/assets/578455/448315/c6de7cf4-b23e-11e2-97b0-aa0296c92d22.jpg)

## Installation

Add following require to your `composer.json` file:

~~~
    "cohensive/validation": "1.0.*"
~~~

Then run `composer install` or `composer update` to download it and autoload.

Once package is installed you need to register it as a service provider. Find `app.php` file in your `app/config` deirectory.
First, since this package extends default Validation, you need to comment out or remove this line from `providers` array: `'Illuminate\Validation\ValidationServiceProvider',` which loads default Laravel Validation class.

Now, in the same `providers` array you need to add new package:

~~~
'providers' => array(

	//...
	'Cohensive\Validation\ValidationServiceProvider',
	//...

)
~~~

## Usage

Mostly the same as in core Validation. Whenit comes to validation with wildcrads here's an example:


````php

$input = array('input' => array('foo', 'bar', 'baz'));
$rules = array(
	'input.*' => 'Alpha|Min:3'
);

$v = Validator::make($input, $rules);

````

Shall we go deeper?


````php

$input = array('users' => array(
	0 => array(
		'name' => 'Mike',
		'age'  =>  30
	),
	1 => array(
		'name' => 'Rob',
		'age'  => '28'
	)
));
$rules = array(
	'users.*.name' => 'Alpha|Min:3',
	'users.*.age'  => 'Numeric|Min:18|Max:80'
);

$v = Validator::make($input, $rules);

````
