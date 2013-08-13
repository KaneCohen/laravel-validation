# Extended Laravel Validation

This class extends Laravel 4 Validation package changing some basic functionality
to provide validation of data with wildcards. Description:

![wildcrads](https://f.cloud.github.com/assets/578455/448315/c6de7cf4-b23e-11e2-97b0-aa0296c92d22.jpg)

## Installation

Add following require to your `composer.json` file:

~~~
    "cohensive/validation": "1.0.*"
~~~

Then run `cimposer install` or `composer update` to download it and autoload.

Once package is installed you need to register it as a service provider. Find `app.php` file in your `app/config` deirectory.
Firs, since this package extends default Validation, you need to comment out or remove this line from `providers` array: `'Illuminate\Validation\ValidationServiceProvider',` which loads default Laravel Validation class.

Now, in the same `providers` array you need to add new package:

~~~
'providers' => array(

	//...
	'Cohensive\Validation\ValidationServiceProvider',
	//...

)
~~~
