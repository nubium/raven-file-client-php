<?php

namespace Nubium\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class RavenLogger extends AbstractLogger
{

	/** @var \Raven_Client */
	protected $ravenClient;


	/**
	 * @param string $directory
	 * @param string $sentryDsn
	 */
	public function __construct($directory, $sentryDsn)
	{
		$this->ravenClient = new RavenFileClient($directory, $sentryDsn);
	}


	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function log($level, $message, array $context = array())
	{
		$data = array();
		$data['level'] = $this->mapLevel($level);

		if (isset($context['user'])) {
			$data['sentry.interfaces.User'] = $context['user'];
			unset($context['user']);
		}
		$message = $this->interpolatePlaceholders($message, $context);

		if (isset($context['exception']) && ($context['exception'] instanceof \Exception || $context['exception'] instanceof \Throwable)) {
			/** @var \Throwable $exception */
			$exception = $context['exception'];
			unset($context['exception']);

			if (!empty($context)) {
				$data['extra'] = $context;
			}

			$this->ravenClient->captureException($exception, $this->normalizeData($data), null, null, $message);
			return;
		}

		if (!empty($context)) {
			$data['extra'] = $context;
		}

		$this->ravenClient->captureMessage($message, array(), $this->normalizeData($data));
	}


	/**
	 * Pokud je v poli nekde nejaky objekt, prevede ho na pole
	 *
	 * @param array $data
	 * @param int $level
	 *
	 * @return array
	 */
	protected function normalizeData(array $data, $level = 0)
	{
		$normalized = [];

		foreach ($data as $key => $value) {
			if (is_object($value) || is_array($value)) {
				if ($level < 10) {
					$normalized[$key] = $this->normalizeData((array)$value, $level + 1);
				} else {
					$normalized[$key] = '<recursion too deep>';
				}
			} else {
				$normalized[$key] = $value;
			}
		}

		return $normalized;
	}


	/**
	 * Mapuje uroven logu z LogLevel konstant na konstanty z RavenFileClient
	 *
	 * @param string $level
	 *
	 * @return string
	 */
	protected function mapLevel($level)
	{
		switch ($level) {
			case LogLevel::DEBUG:
				return RavenFileClient::DEBUG;
			case LogLevel::INFO:
				return RavenFileClient::INFO;
			case LogLevel::NOTICE:
			case LogLevel::WARNING:
				return RavenFileClient::WARNING;
			case LogLevel::EMERGENCY:
			case LogLevel::CRITICAL:
				return RavenFileClient::FATAL;
			case LogLevel::ALERT:
			case LogLevel::ERROR:
			default:
				return RavenFileClient::ERROR;
		}
	}


	/**
	 * Process placeholders in message
	 * Modify $data array (if placeholder found, key from $data will be deleted)
	 *
	 * @param string $message Message
	 * @param array $data Additional data (placeholders values -- we support only string/object->__toString values of placeholders)
	 *
	 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message
	 * @return string Message with replaced placeholders
	 */
	protected function interpolatePlaceholders($message, &$data)
	{
		$replace = array();
		foreach ($data as $key => $val) {
			if (strpos($message, '{' . $key . '}') !== false) {
				$string = '';

				// We support only string or object placeholder value
				if (is_object($val) && method_exists($val, '__toString')) {
					$string = $val->__toString();
				} else if (is_string($val)) {
					$string = $val;
				}

				if ($string) {
					$replace['{' . $key . '}'] = $string;
					unset($data[$key]);
				}
			}
		}

		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}


}
