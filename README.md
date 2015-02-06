# Extended Laravel Validation

This class extends Laravel Validation package changing some basic functionality
to provide validation of data with wildcards. How do wildcrads work:

![wildcrads](https://f.cloud.github.com/assets/578455/448315/c6de7cf4-b23e-11e2-97b0-aa0296c92d22.jpg)

## Installation

Add following line to your `composer.json` file:

For Laravel 4.x
~~~
"cohensive/validation": "4.1.*"
~~~

For Laravel 5.x
~~~
"cohensive/validation": "5.0.*"
~~~

Then run `composer install` or `composer update` to download it and autoload.

Once package is installed you need to register it as a service provider. Find `app.php` file in your `config` deirectory.
First, since this package extends default Validation, you need to comment out or remove this line from `providers` array: `'Illuminate\Validation\ValidationServiceProvider'`.

Now in the same `providers` array you need to add new package:

~~~
'providers' => array(

    //...
    'Cohensive\Validation\ValidationServiceProvider',
    //...

)
~~~

No need to add anything in `aliases`.

## Usage

Mostly the same as in core Validation. When it comes to validation with wildcrads here's an example:

````php

$input = array('input' => array('foo', 'bar', 'baz'));
$rules = array(
    'input:*' => 'Alpha|Min:3'
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
    'users:*:name' => 'Alpha|Min:3',
    'users:*:age'  => 'Numeric|Min:18|Max:80'
);

$v = Validator::make($input, $rules);

````
