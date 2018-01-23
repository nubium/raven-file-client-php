<?php

namespace Nubium\Tests\Logger;

use Nubium\Logger\RavenFileClient;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RavenFileClientTest extends TestCase
{


	/**
	 * Setup virtual filesystem
	 */
	public function setUp()
	{
		vfsStream::setup('root');
	}


	/**
	 * Basic test for RavenFileClientTest::send()
	 */
	public function testSend()
	{
		$dsn = 'http://key:secret@hostname.nds/1';
		$data = ['key' => 'value'];

		$expectedMessage = [
			'secret' => 'secret',
			'key' => 'key',
			'message' => base64_encode(json_encode($data)),
		];

		$client = $this->getMockBuilder(RavenFileClient::class)
			->setConstructorArgs(['logDir', $dsn])
			->setMethods(['log'])
			->getMock();

		$client->expects(static::once())
			->method('log')
			->with($expectedMessage)
			->willReturn(null);

		$client->send($data); // assertions made by mock
	}


	/**
	 * Basic test for RavenFileClientTest::log()
	 */
	public function testLog()
	{
		$directory = 'logDir';
		$message = [
			'secret' => 'a',
			'key' => 'b',
			'message' => 'c',
		];

		$client = new RavenFileClient(
			vfsStream::url('root/' . $directory),
			'http://key:secret@hostname.nds/1'
		);

		$method = new ReflectionMethod(RavenFileClient::class, 'log');
		$method->setAccessible(true);
		$method->invokeArgs($client, [$message]);

		$vfsStructure = vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure();

		static::assertArrayHasKey($directory, $vfsStructure['root'], 'Log directory not created.');

		$logs = $vfsStructure['root'][$directory];
		static::assertCount(1, $logs, 'Expected to found exactly 1 logfile.');

		foreach ($logs as $actualFileName => $actualFileContents) {
			static::assertEquals(json_encode($message), $actualFileContents, 'Log file contents is invalid.');
			static::assertRegExp(
				'/^[0-9]{20}(-[0-9]+)?\.json$/', $actualFileName,
				'Log file name does not match LogExporter specification.'
			);
		}
	}


	/**
	 * Basic test for our extension of RavenFileClientTest::captureException() with message
	 */
	public function testExceptionWithMessageFormatting()
	{
		$dsn = 'http://key:secret@hostname.nds/1';


		$client = $this->getMockBuilder(RavenFileClient::class)
			->setConstructorArgs(['logDir', $dsn])
			->setMethods(['log'])
			->getMock();

		$client->expects(static::once())
			->method('log')
			->with(static::callback(function ($paramContext) {
				$context = json_decode(base64_decode($paramContext['message']), true);
				static::assertEquals('Exception: test2', $context['message']);
				static::assertArrayHasKey('exception', $context);
				static::assertEquals('test', $context['exception']['values']['0']['value']);
				return true;
			}));

		$method = new ReflectionMethod(RavenFileClient::class, 'captureException');
		$method->setAccessible(true);
		$method->invokeArgs($client, [new \Exception('test'), null, null, null, 'test2']);
	}


	/**
	 * Basic test for our extension of RavenFileClientTest::captureException() with message
	 */
	public function testExceptionWithoutMessageFormatting()
	{
		$dsn = 'http://key:secret@hostname.nds/1';


		$client = $this->getMockBuilder(RavenFileClient::class)
			->setConstructorArgs(['logDir', $dsn])
			->setMethods(['log'])
			->getMock();

		$client->expects(static::once())
			->method('log')
			->with(static::callback(function ($paramContext) {
				$context = json_decode(base64_decode($paramContext['message']), true);
				static::assertArrayHasKey('exception', $context);
				static::assertEquals('test', $context['exception']['values']['0']['value']);
				return true;
			}));

		$method = new ReflectionMethod(RavenFileClient::class, 'captureException');
		$method->setAccessible(true);
		$method->invokeArgs($client, [new \Exception('test'), null, null, null, null]);
	}


}
