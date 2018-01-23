<?php

namespace Nubium\Logger;

class RavenFileClient extends \Raven_Client
{

	/** @var string */
	protected $directory;


	/**
	 * @param string $directory Target logging directory
	 * @param string $sentryDSN
	 */
	public function __construct($directory, $sentryDSN)
	{
		parent::__construct($sentryDSN, array('auto_log_stacks' => true));
		$this->directory = $directory;
	}


	/**
	 * @param array $data
	 */
	public function send(&$data)
	{
		$message = base64_encode(\Raven_Compat::json_encode($data));

		$message = array('secret' => $this->secret_key, 'key' => $this->public_key, 'message' => $message);

		$this->log($message);
	}


	/**
	 * @param string|array $message
	 */
	protected function log($message)
	{
		if (!is_dir($this->directory)) {
			mkdir($this->directory, 0755, true);
		}

		$dateTime = new \DateTime();
		$micro = substr(microtime(), 2, 6);

		$logFilePath = $this->directory . '/' . $dateTime->format('YmdHis') . $micro . '-' . random_int(100, 999);

		file_put_contents($logFilePath . '.json', json_encode($message));
		chmod($logFilePath . '.json', 0666);
	}


	/**
	 * Log an exception to sentry
	 *
	 * @param \Exception|\Throwable $exception The Exception object.
	 * @param array $data Additional attributes to pass with this event (see Sentry docs).
	 * @param mixed $logger
	 * @param mixed $vars
	 * @param mixed $message
	 *
	 * @return string|null
	 */
	public function captureException($exception, $data = null, $logger = null, $vars = null, $message = null)
	{
		if ($message) {
			$data['message'] = get_class($exception) . ': ' . $message;
		} else {
			$data['message'] = get_class($exception) . ': ' . $exception->getMessage();
		}
		return parent::captureException($exception, $data, $logger, $vars);
	}

}
