<?php

namespace Nubium\Tests\Logger;

use Nubium\Logger\RavenFileClient;
use Nubium\Logger\RavenLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class RavenLoggerTest extends TestCase
{


	/**
	 * Basic test for RavenLogger::log() with less params possible
	 */
	public function testLog_withMessageOnly()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log message';

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureMessage')
			->with(
				$logMessage,
				[],
				['level' => $logLevel]
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureException');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage); // assertions made by mock
	}


	/**
	 * Basic test for RavenLogger::log() without exception in $context
	 */
	public function testLog_withMessageAndAditionalData()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log message';
		$logContext = [
			'more' => ['some', 'more', 'data'],
		];

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureMessage')
			->with(
				$logMessage,
				[],
				['level' => $logLevel, 'extra' => $logContext]
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureException');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage, $logContext); // assertions made by mock
	}


	/**
	 * Basic test for RavenLogger::log() with exception in $context
	 *
	 * @param string $logMessage
	 * @param \Exception $logException
	 *
	 * @dataProvider provider_testLog_withExceptionOnly
	 */
	public function testLog_withExceptionOnly($logMessage, $logException)
	{
		$logLevel = LogLevel::ERROR;
		$logContext = [
			'exception' => $logException,
		];

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureException')
			->with(
				static::callback(function ($paramException) use ($logContext) {
					static::assertSame($logContext['exception'], $paramException);
					return true;
				}),
				static::callback(function ($paramContext) use ($logLevel) {
					static::assertArrayNotHasKey('exception', $paramContext);
					static::assertArrayHasKey('level', $paramContext);
					static::assertEquals($logLevel, $paramContext['level']);
					static::assertArrayNotHasKey('logger message', $paramContext);
					return true;
				})
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureMessage');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage, $logContext); // assertions made by mock
	}


	/**
	 * @return array
	 */
	public function provider_testLog_withExceptionOnly()
	{
		$exception = new \Exception('exception message');
		return [
			'null message' => [null, $exception],
			'empty message' => ['', $exception],
			'duplicate message' => [$exception->getMessage(), $exception],
		];
	}


	/**
	 * Basic test for RavenLogger::log() with exception in $context and other data
	 */
	public function testLog_withExceptionAndMessageAndAditionalData()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log message';
		$logContext = [
			'exception' => new \Exception('exception message'),
			'more' => ['some', 'more', 'data'],
		];

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureException')
			->with(
				static::callback(function ($paramException) use ($logContext) {
					static::assertSame($logContext['exception'], $paramException);
					return true;
				}),
				static::callback(function ($paramContext) use ($logLevel, $logContext) {
					static::assertArrayNotHasKey('exception', $paramContext);
					static::assertArrayHasKey('level', $paramContext);
					static::assertEquals($logLevel, $paramContext['level']);
					static::assertArrayHasKey('extra', $paramContext);
					static::assertEquals($logContext['more'], $paramContext['extra']['more']);
					return true;
				}),
				null,
				null,
				$logMessage
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureMessage');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage, $logContext); // assertions made by mock
	}


	/**
	 * Basic test for RavenLogger::log() with exception in $context and other data without given message
	 */
	public function testLog_withExceptionAndMessageAndAditionalDataWihoutMessage()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log message';
		$logContext = [
			'exception' => new \Exception('exception message'),
			'more' => ['some', 'more', 'data'],
		];

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureException')
			->with(
				static::callback(function ($paramException) use ($logContext) {
					static::assertSame($logContext['exception'], $paramException);
					return true;
				}),
				static::callback(function ($paramContext) use ($logLevel, $logContext) {
					static::assertArrayNotHasKey('exception', $paramContext);
					static::assertArrayHasKey('level', $paramContext);
					static::assertEquals($logLevel, $paramContext['level']);
					static::assertArrayHasKey('extra', $paramContext);
					static::assertEquals($logContext['more'], $paramContext['extra']['more']);
					return true;
				}),
				null,
				null,
				null
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureMessage');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, null, $logContext); // assertions made by mock
	}


	/**
	 * Basic test for placeholders
	 */
	public function testLog_messagePlaceholder()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log {more2} {more3} message {more}';
		$mock = $this->getMockBuilder('MockClass')
			->setMethods(['__toString'])
			->getMock();
		$mock->expects(static::once())->method('__toString')->willReturn('test2');
		$logContext = [
			'more' => 'test',
			'more2' => $mock,
			'more3' => ['testit'],
		];

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureMessage')
			->with(
				'log test2 {more3} message test',
				[],
				['level' => $logLevel, 'extra' => ['more3' => ['testit']]]
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureException');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage, $logContext); // assertions made by mock
	}


	/**
	 * Basic test for placeholders
	 */
	public function testLog_messagePlaceholder_Exception()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log {more2} {more3} message {more}';
		$mock = $this->getMockBuilder('MockClass')
			->setMethods(['__toString'])
			->getMock();
		$mock->expects(static::once())->method('__toString')->willReturn('test2');
		$logContext = [
			'more' => 'test',
			'more2' => $mock,
			'exception' => new \Exception('exception message'),
		];

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureException')
			->with(
				static::callback(function ($paramException) use ($logContext) {
					static::assertSame($logContext['exception'], $paramException);
					return true;
				}),
				static::callback(function ($paramContext) use ($logLevel) {
					static::assertArrayNotHasKey('exception', $paramContext);
					static::assertArrayHasKey('level', $paramContext);
					static::assertEquals($logLevel, $paramContext['level']);
					static::assertArrayNotHasKey('extra', $paramContext);
					return true;
				}),
				null,
				null,
				'log test2 {more3} message test'
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureMessage');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage, $logContext); // assertions made by mock
	}


	/**
	 * Test level mapping
	 * @param string $inLevel
	 * @param string $outLevel
	 *
	 * @dataProvider provider_testMapLevel
	 */
	public function testMapLevel($inLevel, $outLevel)
	{
		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureMessage')
			->with(
				static::anything(),
				static::anything(),
				static::callback(function ($paramContext) use ($outLevel) {
					static::assertSame($outLevel, $paramContext['level']);
					return true;
				}),
				static::anything(),
				static::anything()
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureException');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($inLevel, 'Error', []); // assertions made by mock
	}


	/**
	 * Test level mapping data provider
	 * @return array
	 */
	public function provider_testMapLevel()
	{
		return [
			[ LogLevel::DEBUG,     RavenFileClient::DEBUG],
			[ LogLevel::INFO,      RavenFileClient::INFO],
			[ LogLevel::NOTICE,    RavenFileClient::WARNING],
			[ LogLevel::WARNING,   RavenFileClient::WARNING],
			[ LogLevel::EMERGENCY, RavenFileClient::FATAL],
			[ LogLevel::CRITICAL,  RavenFileClient::FATAL],
			[ LogLevel::ALERT,     RavenFileClient::ERROR],
			[ LogLevel::ERROR,     RavenFileClient::ERROR],
		];
	}


	/**
	 * Basic test for RavenLogger::log() with PHP 7 \Throwable error in $context['exception']
	 *
	 * @requires PHP 7.1
	 */
	public function testLog_withThrowable()
	{
		$logLevel = LogLevel::ERROR;
		$logMessage = 'log message';
		$logContext = [];
		try {
			callToUndefinedMethod();
		} catch (\Throwable $error) {
			$logContext = ['exception' => $error];
		}

		$client = $this->getMockBuilder('Raven_Client')
			->disableOriginalConstructor()
			->setMethods(['captureMessage', 'captureException'])
			->getMock();

		$client->expects(static::once())
			->method('captureException')
			->with(
				static::callback(function ($paramException) use ($logContext) {
					static::assertSame($logContext['exception'], $paramException);
					return true;
				}),
				static::callback(function ($paramContext) use ($logLevel) {
					static::assertArrayNotHasKey('exception', $paramContext);
					static::assertArrayHasKey('level', $paramContext);
					static::assertEquals($logLevel, $paramContext['level']);
					static::assertArrayNotHasKey('logger message', $paramContext);
					return true;
				})
			)
			->willReturn('event ID');

		$client->expects(static::never())
			->method('captureMessage');

		$logger = new RavenLogger('logDir', 'http://key:secret@hostname.nds/1');

		$reflection = new \ReflectionClass($logger);
		$reflectionProperty = $reflection->getProperty('ravenClient');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($logger, $client);

		$logger->log($logLevel, $logMessage, $logContext); // assertions made by mock
	}

}
