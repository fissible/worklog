<?php

namespace Tests\Unit;

use Tests\TestCase;
use Worklog\CommandLine\Command;
use Worklog\CommandLine\BinaryCommand;

class CommandTest extends TestCase {

	// private $key;

	// private $data;


	protected function setUp()
	{
		parent::setUp();

        Command::bind('bin', 'Worklog\CommandLine\BinaryCommand');
        Command::bind('help', 'Worklog\CommandLine\UsageCommand');
	}


    /**
     */
    public function testCommandBinding()
    {
        $Command = $this->make();
        $this->assertTrue($Command->validate_command('bin'));
        $this->assertTrue($Command->validate_command('help'));
        $BinaryCommand = Command::instance('bin');
        $UsageCommand = Command::instance('help');
        $this->assertTrue($BinaryCommand instanceof \Worklog\CommandLine\BinaryCommand);
        $this->assertTrue($UsageCommand instanceof \Worklog\CommandLine\UsageCommand);
    }

    /**
     */
    public function testInstantiation()
    {
        $Com = $this->make('help');
        $this->assertEquals('help', $Com->name());
        $BinCom = $Com->build(BinaryCommand::class);
        $BinCom->setBinary('echo');
        $this->assertEquals('echo', $BinCom->getBinary());
    }

    /**
     */
    public function testBinaryCommand()
    {
        $input = escapeshellarg('¸.·´¯`·.´¯`·.¸¸.·´¯`·.¸><(((º>');
        $BinCom = $this->makeBinary([ 'echo', $input ]);
        $output = $BinCom->run();
        $this->assertEquals($input, escapeshellarg($output[0]));
    }

    /**
     */
    public function testCallCommand()
    {
        $original_value = BinaryCommand::collect_output(true);
        $output = BinaryCommand::call(['ls']);
        $this->assertContains('database', $output);
        $this->assertContains('src', $output);
        $this->assertContains('tests', $output);
        BinaryCommand::collect_output($original_value);
    }

    /**
     * @expect 
     */
    public function testCommandBindException()
    {
        $this->expectException(\InvalidArgumentException::class);
        Command::bind('fails', []);
    }


    private function make($name = [])
	{
		return new Command($name);
	}

    private function makeBinary($command = [])
    {
        return new BinaryCommand($command);
    }

	protected function tearDown()
	{
		// unset($this->key);
		// unset($this->data);

		parent::tearDown();
	}
}