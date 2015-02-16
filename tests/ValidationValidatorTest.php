<?php

use Mockery as m;
use Cohensive\Validation\Validator;
use Symfony\Component\HttpFoundation\File\File;

class ValidationValidatorTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testHasFailedValidationRules()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'bar', 'baz' => 'boom'), array('foo' => 'Same:baz'));
		$this->assertFalse($v->passes());
		$this->assertEquals(array('foo' => array('Same' => array('baz'))), $v->failed());
	}


	public function testHasNotFailedValidationRules()
	{
		$trans = $this->getTranslator();
		$trans->shouldReceive('trans')->never();
		$v = new Validator($trans, array('foo' => 'taylor'), array('name' => 'Confirmed'));
		$this->assertTrue($v->passes());
		$this->assertEmpty($v->failed());
	}


	public function testSometimesCanSkipRequiredRules()
	{
		$trans = $this->getTranslator();
		$trans->shouldReceive('trans')->never();
		$v = new Validator($trans, array(), array('name' => 'sometimes|required'));
		$this->assertTrue($v->passes());
		$this->assertEmpty($v->failed());
	}


	public function testInValidatableRulesReturnsValid()
	{
		$trans = $this->getTranslator();
		$trans->shouldReceive('trans')->never();
		$v = new Validator($trans, array('foo' => 'taylor'), array('name' => 'Confirmed'));
		$this->assertTrue($v->passes());
	}


	public function testProperLanguageLineIsSet()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.required' => 'required!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('required!', $v->messages()->first('name'));
	}


	public function testCustomReplacersAreCalled()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.required' => 'foo bar'), 'en', 'messages');
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$v->addReplacer('required', function($message, $attribute, $rule, $parameters) { return str_replace('bar', 'taylor', $message); });
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo taylor', $v->messages()->first('name'));
	}


	public function testClassBasedCustomReplacers()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.foo' => 'foo!'), 'en', 'messages');
		$v = new Validator($trans, array(), array('name' => 'required'));
		$v->setContainer($container = m::mock('Illuminate\Container\Container'));
		$v->addReplacer('required', 'Foo@bar');
		$container->shouldReceive('make')->once()->with('Foo')->andReturn($foo = m::mock('StdClass'));
		$foo->shouldReceive('bar')->once()->andReturn('replaced!');
		$v->passes();
		$v->messages()->setFormat(':message');
		$this->assertEquals('replaced!', $v->messages()->first('name'));
	}


	public function testAttributeNamesAreReplaced()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.required' => ':attribute is required!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('name is required!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.required' => ':attribute is required!', 'validation.attributes.name' => 'Name'), 'en', 'messages');
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('Name is required!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.required' => ':attribute is required!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$v->setAttributeNames(array('name' => 'Name'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('Name is required!', $v->messages()->first('name'));
	}


	public function testCustomValidationLinesAreRespected()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.required' => 'required!', 'validation.custom.name.required' => 'really required!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('really required!', $v->messages()->first('name'));
	}


	public function testInlineValidationMessagesAreRespected()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'), array('name.required' => 'require it please!'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('require it please!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'), array('required' => 'require it please!'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('require it please!', $v->messages()->first('name'));
	}


	public function testValidateRequired()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array(), array('name' => 'Required'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('name' => ''), array('name' => 'Required'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('name' => 'foo'), array('name' => 'Required'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('arr' => ['foo' => ['bar' => 'baz']]), array('arr.foo.bar' => 'Required'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('arr' => [['foo' => 'bar', 'foo' => 'baz']]), array('arr:*:foo' => 'Required'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('arr' => [['foo' => 'bar', 'foo' => '']]), array('arr:*:foo' => 'Required'));
		$this->assertFalse($v->passes());

		$file = new File('', false);
		$v = new Validator($trans, array('name' => $file), array('name' => 'Required'));
		$this->assertFalse($v->passes());

		$file = new File(__FILE__, false);
		$v = new Validator($trans, array('name' => $file), array('name' => 'Required'));
		$this->assertTrue($v->passes());
	}


	public function testValidateRequiredWith()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('first' => 'Taylor'), array('last' => 'required_with:first'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('first' => 'Taylor', 'last' => ''), array('last' => 'required_with:first'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('first' => ''), array('last' => 'required_with:first'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array(), array('last' => 'required_with:first'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('first' => 'Taylor', 'last' => 'Otwell'), array('last' => 'required_with:first'));
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$v = new Validator($trans, array('file' => $file, 'foo' => ''), array('foo' => 'required_with:file'));
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File(__FILE__, false);
		$v = new Validator($trans, array('file' => $file, 'foo' => $foo), array('foo' => 'required_with:file'));
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File('', false);
		$v = new Validator($trans, array('file' => $file, 'foo' => $foo), array('foo' => 'required_with:file'));
		$this->assertFalse($v->passes());
	}


	public function testRequiredWithAll()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('first' => 'foo'), array('last' => 'required_with_all:first,foo'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('first' => 'foo'), array('last' => 'required_with_all:first'));
		$this->assertFalse($v->passes());
	}


	public function testValidateRequiredWithout()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('first' => 'Taylor'), array('last' => 'required_without:first'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('first' => 'Taylor', 'last' => ''), array('last' => 'required_without:first'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('first' => ''), array('last' => 'required_without:first'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array(), array('last' => 'required_without:first'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('first' => 'Taylor', 'last' => 'Otwell'), array('last' => 'required_without:first'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('last' => 'Otwell'), array('last' => 'required_without:first'));
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$v = new Validator($trans, array('file' => $file), array('foo' => 'required_without:file'));
		$this->assertFalse($v->passes());

		$foo = new File('', false);
		$v = new Validator($trans, array('foo' => $foo), array('foo' => 'required_without:file'));
		$this->assertFalse($v->passes());

		$foo = new File(__FILE__, false);
		$v = new Validator($trans, array('foo' => $foo), array('foo' => 'required_without:file'));
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File(__FILE__, false);
		$v = new Validator($trans, array('file' => $file, 'foo' => $foo), array('foo' => 'required_without:file'));
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File('', false);
		$v = new Validator($trans, array('file' => $file, 'foo' => $foo), array('foo' => 'required_without:file'));
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$foo  = new File(__FILE__, false);
		$v = new Validator($trans, array('file' => $file, 'foo' => $foo), array('foo' => 'required_without:file'));
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$foo  = new File('', false);
		$v = new Validator($trans, array('file' => $file, 'foo' => $foo), array('foo' => 'required_without:file'));
		$this->assertFalse($v->passes());
	}


	public function testRequiredWithoutMultiple()
	{
		$trans = $this->getRealTranslator();

		$rules = array(
			'f1' => 'required_without:f2,f3',
			'f2' => 'required_without:f1,f3',
			'f3' => 'required_without:f1,f2',
		);

		$v = new Validator($trans, array(), $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('f1' => 'foo'), $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('f2' => 'foo'), $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('f3' => 'foo'), $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('f1' => 'foo', 'f2' => 'bar'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f1' => 'foo', 'f3' => 'bar'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f2' => 'foo', 'f3' => 'bar'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f1' => 'foo', 'f2' => 'bar', 'f3' => 'baz'), $rules);
		$this->assertTrue($v->passes());
	}


	public function testRequiredWithoutAll()
	{
		$trans = $this->getRealTranslator();

		$rules = array(
			'f1' => 'required_without_all:f2,f3',
			'f2' => 'required_without_all:f1,f3',
			'f3' => 'required_without_all:f1,f2',
		);

		$v = new Validator($trans, array(), $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('f1' => 'foo'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f2' => 'foo'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f3' => 'foo'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f1' => 'foo', 'f2' => 'bar'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f1' => 'foo', 'f3' => 'bar'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f2' => 'foo', 'f3' => 'bar'), $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('f1' => 'foo', 'f2' => 'bar', 'f3' => 'baz'), $rules);
		$this->assertTrue($v->passes());
	}


	public function testRequiredIf()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('first' => 'taylor'), array('last' => 'required_if:first,taylor'));
		$this->assertTrue($v->fails());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('first' => 'taylor', 'last' => 'otwell'), array('last' => 'required_if:first,taylor'));
		$this->assertTrue($v->passes());
	}


	public function testValidateConfirmed()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('password' => 'foo'), array('password' => 'Confirmed'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('password' => 'foo', 'password_confirmation' => 'bar'), array('password' => 'Confirmed'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('password' => 'foo', 'password_confirmation' => 'foo'), array('password' => 'Confirmed'));
		$this->assertTrue($v->passes());
	}


	public function testValidateSame()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'bar', 'baz' => 'boom'), array('foo' => 'Same:baz'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'bar'), array('foo' => 'Same:baz'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'bar', 'baz' => 'bar'), array('foo' => 'Same:baz'));
		$this->assertTrue($v->passes());
	}


	public function testValidateDifferent()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'bar', 'baz' => 'boom'), array('foo' => 'Different:baz'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => 'bar'), array('foo' => 'Different:baz'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'bar', 'baz' => 'bar'), array('foo' => 'Different:baz'));
		$this->assertFalse($v->passes());
	}


	public function testValidateAccepted()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'no'), array('foo' => 'Accepted'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => null), array('foo' => 'Accepted'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array(), array('foo' => 'Accepted'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 0), array('foo' => 'Accepted'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => false), array('foo' => 'Accepted'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'false'), array('foo' => 'Accepted'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'yes'), array('foo' => 'Accepted'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => 'on'), array('foo' => 'Accepted'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '1'), array('foo' => 'Accepted'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => 1), array('foo' => 'Accepted'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => true), array('foo' => 'Accepted'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => 'true'), array('foo' => 'Accepted'));
		$this->assertTrue($v->passes());
	}


	public function testValidateNumeric()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'asdad'), array('foo' => 'Numeric'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '1.23'), array('foo' => 'Numeric'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => '1.23'], ['foo' => 1.23]]], array('arr:*:foo' => 'Numeric'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '-1'), array('foo' => 'Numeric'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => '-1'], ['foo' => -1]]], array('arr:*:foo' => 'Numeric'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '1'), array('foo' => 'Numeric'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => '1'], ['foo' => 1]]], array('arr:*:foo' => 'Numeric'));
		$this->assertTrue($v->passes());
	}


	public function testValidateInteger()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'asdad'), array('foo' => 'Integer'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '1.23'), array('foo' => 'Integer'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => '1.23'], ['foo' => 1.23]]], array('arr:*:foo' => 'Integer'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '-1'), array('foo' => 'Integer'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '1'), array('foo' => 'Integer'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => '1'], ['foo' => 1]]], array('arr:*:foo' => 'Integer'));
		$this->assertTrue($v->passes());
	}


	public function testValidateDigits()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => '12345'), array('foo' => 'Digits:5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '123'), array('foo' => 'Digits:200'));
		$this->assertFalse($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => '12345'), array('foo' => 'digits_between:1,6'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '123'), array('foo' => 'digits_between:4,5'));
		$this->assertFalse($v->passes());
	}


	public function testValidateSize()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'asdad'), array('foo' => 'Size:3'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'anc'), array('foo' => 'Size:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '123'), array('foo' => 'Numeric|Size:3'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '3'), array('foo' => 'Numeric|Size:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3)), array('foo' => 'Array|Size:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3)), array('foo' => 'Array|Size:4'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => ['bar']], ['foo' => ['baz']]]], array('arr:*:foo' => 'Array|Size:1'));
		$this->assertTrue($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, array(), array('photo' => 'Size:3'));
		$v->setFiles(array('photo' => $file));
		$this->assertTrue($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, array(), array('photo' => 'Size:3'));
		$v->setFiles(array('photo' => $file));
		$this->assertFalse($v->passes());
	}


	public function testValidateBetween()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'asdad'), array('foo' => 'Between:3,4'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'anc'), array('foo' => 'Between:3,5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => 'ancf'), array('foo' => 'Between:3,5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => 'ancfs'), array('foo' => 'Between:3,5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['arr' => [['foo' => 'bar'], ['foo' => 'baz']]], array('arr:*:foo' => 'Between:2,5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '123'), array('foo' => 'Numeric|Between:50,100'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '3'), array('foo' => 'Numeric|Between:1,5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3)), array('foo' => 'Array|Between:1,5'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3)), array('foo' => 'Array|Between:1,2'));
		$this->assertFalse($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, array(), array('photo' => 'Between:1,5'));
		$v->setFiles(array('photo' => $file));
		$this->assertTrue($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, array(), array('photo' => 'Between:1,2'));
		$v->setFiles(array('photo' => $file));
		$this->assertFalse($v->passes());
	}


	public function testValidateMin()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => '3'), array('foo' => 'Min:3'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'anc'), array('foo' => 'Min:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '2'), array('foo' => 'Numeric|Min:3'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '5'), array('foo' => 'Numeric|Min:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3, 4)), array('foo' => 'Array|Min:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2)), array('foo' => 'Array|Min:3'));
		$this->assertFalse($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, array(), array('photo' => 'Min:2'));
		$v->setFiles(array('photo' => $file));
		$this->assertTrue($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, array(), array('photo' => 'Min:10'));
		$v->setFiles(array('photo' => $file));
		$this->assertFalse($v->passes());
	}


	public function testValidateMax()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('foo' => 'aslksd'), array('foo' => 'Max:3'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => 'anc'), array('foo' => 'Max:3'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => '211'), array('foo' => 'Numeric|Max:100'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('foo' => '22'), array('foo' => 'Numeric|Max:33'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3)), array('foo' => 'Array|Max:4'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('foo' => array(1, 2, 3)), array('foo' => 'Array|Max:2'));
		$this->assertFalse($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('isValid', 'getSize'), array(__FILE__, basename(__FILE__)));
		$file->expects($this->at(0))->method('isValid')->will($this->returnValue(true));
		$file->expects($this->at(1))->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, array(), array('photo' => 'Max:10'));
		$v->setFiles(array('photo' => $file));
		$this->assertTrue($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('isValid', 'getSize'), array(__FILE__, basename(__FILE__)));
		$file->expects($this->at(0))->method('isValid')->will($this->returnValue(true));
		$file->expects($this->at(1))->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, array(), array('photo' => 'Max:2'));
		$v->setFiles(array('photo' => $file));
		$this->assertFalse($v->passes());

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('isValid'), array(__FILE__, basename(__FILE__)));
		$file->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$v = new Validator($trans, array(), array('photo' => 'Max:10'));
		$v->setFiles(array('photo' => $file));
		$this->assertFalse($v->passes());
	}


	public function testProperMessagesAreReturnedForSizes()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.min.numeric' => 'numeric', 'validation.size.string' => 'string', 'validation.max.file' => 'file'), 'en', 'messages');
		$v = new Validator($trans, array('name' => '3'), array('name' => 'Numeric|Min:5'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('numeric', $v->messages()->first('name'));

		$v = new Validator($trans, array('name' => 'asasdfadsfd'), array('name' => 'Size:2'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('string', $v->messages()->first('name'));

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\File', array('getSize'), array(__FILE__, false));
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, array(), array('photo' => 'Max:3'));
		$v->setFiles(array('photo' => $file));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('file', $v->messages()->first('photo'));
	}


	public function testValidateIn()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => 'foo'), array('name' => 'In:bar,baz'));
		$this->assertFalse($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => 0), array('name' => 'In:bar,baz'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('name' => 'foo'), array('name' => 'In:foo,baz'));
		$this->assertTrue($v->passes());
	}


	public function testValidateNotIn()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => 'foo'), array('name' => 'NotIn:bar,baz'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('name' => 'foo'), array('name' => 'NotIn:foo,baz'));
		$this->assertFalse($v->passes());
	}


	public function testValidateUnique()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Unique:users'));
		$mock = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock->shouldReceive('getCount')->once()->with('users', 'email', 'foo', null, null, array())->andReturn(0);
		$v->setPresenceVerifier($mock);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Unique:users,email_addr,1'));
		$mock2 = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock2->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', '1', 'id', array())->andReturn(1);
		$v->setPresenceVerifier($mock2);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Unique:users,email_addr,1,id_col'));
		$mock3 = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock3->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', '1', 'id_col', array())->andReturn(2);
		$v->setPresenceVerifier($mock3);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Unique:users,email_addr,NULL,id_col,foo,bar'));
		$mock3 = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock3->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', null, 'id_col', array('foo' => 'bar'))->andReturn(2);
		$v->setPresenceVerifier($mock3);
		$this->assertFalse($v->passes());
	}


	public function testValidationExists()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Exists:users'));
		$mock = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock->shouldReceive('getCount')->once()->with('users', 'email', 'foo', null, null, array())->andReturn(true);
		$v->setPresenceVerifier($mock);
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Exists:users,email,account_id,1,name,taylor'));
		$mock4 = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock4->shouldReceive('getCount')->once()->with('users', 'email', 'foo', null, null, array('account_id' => 1, 'name' => 'taylor'))->andReturn(true);
		$v->setPresenceVerifier($mock4);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('email' => 'foo'), array('email' => 'Exists:users,email_addr'));
		$mock2 = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock2->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', null, null, array())->andReturn(false);
		$v->setPresenceVerifier($mock2);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('email' => array('foo')), array('email' => 'Exists:users,email_addr'));
		$mock3 = m::mock('Illuminate\Validation\PresenceVerifierInterface');
		$mock3->shouldReceive('getMultiCount')->once()->with('users', 'email_addr', array('foo'), array())->andReturn(false);
		$v->setPresenceVerifier($mock3);
		$this->assertFalse($v->passes());
	}


	public function testValidateIp()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('ip' => 'aslsdlks'), array('ip' => 'Ip'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('ip' => '127.0.0.1'), array('ip' => 'Ip'));
		$this->assertTrue($v->passes());
	}


	public function testValidateEmail()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'aslsdlks'), array('x' => 'Email'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('x' => 'foo@gmail.com'), array('x' => 'Email'));
		$this->assertTrue($v->passes());
	}


	public function testValidateUrl()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'aslsdlks'), array('x' => 'Url'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('x' => 'http://google.com'), array('x' => 'Url'));
		$this->assertTrue($v->passes());
	}


	public function testValidateActiveUrl()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'aslsdlks'), array('x' => 'active_url'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('x' => 'http://google.com'), array('x' => 'active_url'));
		$this->assertTrue($v->passes());
	}


	public function testValidateImage()
	{
		$trans = $this->getRealTranslator();
		$uploadedFile = array(__FILE__, '', null, null, null, true);

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension'), $uploadedFile);
		$file->expects($this->any())->method('guessExtension')->will($this->returnValue('php'));
		$v = new Validator($trans, array(), array('x' => 'Image'));
		$v->setFiles(array('x' => $file));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array(), array('x' => 'Image'));
		$file2 = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension'), $uploadedFile);
		$file2->expects($this->any())->method('guessExtension')->will($this->returnValue('jpeg'));
		$v->setFiles(array('x' => $file2));
		$this->assertTrue($v->passes());

		$file3 = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension'), $uploadedFile);
		$file3->expects($this->any())->method('guessExtension')->will($this->returnValue('gif'));
		$v->setFiles(array('x' => $file3));
		$this->assertTrue($v->passes());

		$file4 = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension'), $uploadedFile);
		$file4->expects($this->any())->method('guessExtension')->will($this->returnValue('bmp'));
		$v->setFiles(array('x' => $file4));
		$this->assertTrue($v->passes());

		$file5 = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension'), $uploadedFile);
		$file5->expects($this->any())->method('guessExtension')->will($this->returnValue('png'));
		$v->setFiles(array('x' => $file5));
		$this->assertTrue($v->passes());
	}


	public function testValidateMime()
	{
		$trans = $this->getRealTranslator();
		$uploadedFile = array(__FILE__, '', null, null, null, true);

		$file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension'), $uploadedFile);
		$file->expects($this->any())->method('guessExtension')->will($this->returnValue('php'));
		$v = new Validator($trans, array(), array('x' => 'mimes:php'));
		$v->setFiles(array('x' => $file));
		$this->assertTrue($v->passes());

		$file2 = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array('guessExtension', 'isValid'), $uploadedFile);
		$file2->expects($this->any())->method('guessExtension')->will($this->returnValue('php'));
		$file2->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$v = new Validator($trans, array(), array('x' => 'mimes:php'));
		$v->setFiles(array('x' => $file2));
		$this->assertFalse($v->passes());
	}


	public function testValidateAlpha()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'aslsdlks'), array('x' => 'Alpha'));
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'aslsdlks
1
1'), array('x' => 'Alpha'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('x' => 'http://google.com'), array('x' => 'Alpha'));
		$this->assertFalse($v->passes());
	}


	public function testValidateAlphaNum()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'asls13dlks'), array('x' => 'AlphaNum'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('x' => 'http://g232oogle.com'), array('x' => 'AlphaNum'));
		$this->assertFalse($v->passes());
	}


	public function testValidateAlphaDash()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'asls1-_3dlks'), array('x' => 'AlphaDash'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('x' => 'http://-g232oogle.com'), array('x' => 'AlphaDash'));
		$this->assertFalse($v->passes());
	}


	public function testValidateRegex()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'asdasdf'), array('x' => 'Regex:/^([a-z])+$/i'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('x' => 'aasd234fsd1'), array('x' => 'Regex:/^([a-z])+$/i'));
		$this->assertFalse($v->passes());

		$v = new Validator($trans, array('x' => 'a,b'), array('x' => 'Regex:/^a,b$/i'));
		$this->assertTrue($v->passes());
	}


	public function testValidateDateAndFormat()
	{
		date_default_timezone_set('UTC');
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => '2000-01-01'), array('x' => 'date'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('x' => 'Not a date'), array('x' => 'date'));
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('x' => '2000-01-01'), array('x' => 'date_format:Y-m-d'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('x' => '01/01/2001'), array('x' => 'date_format:Y-m-d'));
		$this->assertTrue($v->fails());
	}


	public function testBeforeAndAfter()
	{
		date_default_timezone_set('UTC');
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => '2000-01-01'), array('x' => 'Before:2012-01-01'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('x' => '2012-01-01'), array('x' => 'After:2000-01-01'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('start' => '2012-01-01', 'ends' => '2013-01-01'), array('start' => 'After:2000-01-01', 'ends' => 'After:start'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('start' => '2012-01-01', 'ends' => '2000-01-01'), array('start' => 'After:2000-01-01', 'ends' => 'After:start'));
		$this->assertTrue($v->fails());

		$v = new Validator($trans, array('start' => '2012-01-01', 'ends' => '2013-01-01'), array('start' => 'Before:ends', 'ends' => 'After:start'));
		$this->assertTrue($v->passes());

		$v = new Validator($trans, array('start' => '2012-01-01', 'ends' => '2000-01-01'), array('start' => 'Before:ends', 'ends' => 'After:start'));
		$this->assertTrue($v->fails());
	}


	public function testSometimesAddingRules()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'foo'), array('x' => 'Required'));
		$v->sometimes('x', 'Confirmed', function($i) { return $i->x == 'foo'; });
		$this->assertEquals(array('x' => array('Required', 'Confirmed')), $v->getRules());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'foo'), array('x' => 'Required'));
		$v->sometimes('x', 'Confirmed', function($i) { return $i->x == 'bar'; });
		$this->assertEquals(array('x' => array('Required')), $v->getRules());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'foo'), array('x' => 'Required'));
		$v->sometimes('x', 'Foo|Bar', function($i) { return $i->x == 'foo'; });
		$this->assertEquals(array('x' => array('Required', 'Foo', 'Bar')), $v->getRules());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('x' => 'foo'), array('x' => 'Required'));
		$v->sometimes('x', array('Foo', 'Bar:Baz'), function($i) { return $i->x == 'foo'; });
		$this->assertEquals(array('x' => array('Required', 'Foo', 'Bar:Baz')), $v->getRules());
	}


	public function testCustomValidators()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.foo' => 'foo!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => 'taylor'), array('name' => 'foo'));
		$v->addExtension('foo', function() { return false; });
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.foo_bar' => 'foo!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => 'taylor'), array('name' => 'foo_bar'));
		$v->addExtension('FooBar', function() { return false; });
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => 'taylor'), array('name' => 'foo_bar'));
		$v->addExtension('FooBar', function() { return false; });
		$v->setFallbackMessages(array('foo_bar' => 'foo!'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array('name' => 'taylor'), array('name' => 'foo_bar'));
		$v->addExtensions(array('FooBar' => function() { return false; }));
		$v->setFallbackMessages(array('foo_bar' => 'foo!'));
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));
	}


	public function testClassBasedCustomValidators()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', array('validation.foo' => 'foo!'), 'en', 'messages');
		$v = new Validator($trans, array('name' => 'taylor'), array('name' => 'foo'));
		$v->setContainer($container = m::mock('Illuminate\Container\Container'));
		$v->addExtension('foo', 'Foo@bar');
		$container->shouldReceive('make')->once()->with('Foo')->andReturn($foo = m::mock('StdClass'));
		$foo->shouldReceive('bar')->once()->andReturn(false);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));
	}


	public function testCustomImplicitValidators()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, array(), array('implicit_rule' => 'foo'));
		$v->addImplicitExtension('implicit_rule', function() { return true; });
		$this->assertTrue($v->passes());
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testExceptionThrownOnIncorrectParameterCount()
	{
		$trans = $this->getTranslator();
		$v = new Validator($trans, array(), array('foo' => 'required_if:foo'));
		$v->passes();
	}


	protected function getTranslator()
	{
		return m::mock('Symfony\Component\Translation\TranslatorInterface');
	}


	protected function getRealTranslator()
	{
		$trans = new Symfony\Component\Translation\Translator('en', new Symfony\Component\Translation\MessageSelector);
		$trans->addLoader('array', new Symfony\Component\Translation\Loader\ArrayLoader);
		return $trans;
	}

}
