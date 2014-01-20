<?php
/*
 * (c) Bigcommerce Pty Ltd <developers@bigcommerce.com>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace StatsD;

use Exception;

/**
 * A very simple StatsD client.
 * 
 * Captures metrics and sends them to the StatsD daemon over UDP.
 */
class Client
{
	/**
	 * Host name for statsd deamon
	 *
	 * @var string
	 */
	private $_host;

	/**
	 * port number for on which the statsd deamon is listening on
	 *
	 * @var integer
	 */
	private $_port;

	/**
	 * boolean flag to enable/disable sending metrics to statsd
	 * true by deafult
	 *
	 * @var bool
	 */
	private $_enabled = true;

	/**
	 * Log timing information
	 *
	 * @param string $stats The metric to in log timing info for.
	 * @param float $time The ellapsed time (ms) to log
	 * @param float|1 $sampleRate the rate (0-1) for sampling.
	 *
	 * @return bool
	 **/
	public function timing($stat, $time, $sampleRate = 1)
	{
		return $this->_send(array($stat => $time . '|ms'), $sampleRate);
	}

	/**
	 * Log count information
	 *
	 * @param string $stats The metric to in log timing info for.
	 * @param integer $count The number of count to log
	 * @param float|1 $sampleRate the rate (0-1) for sampling.
	 *
	 * @return bool
	 **/
	public function count($stat, $count, $sampleRate = 1)
	{
		return $this->_send(array($stat => $count . '|c'), $sampleRate);
	}

	/**
	 * Increments one or more stats counters
	 *
	 * @param string|array $stats The metric(s) to increment.
	 * @param float|1 $sampleRate the rate (0-1) for sampling.
	 *
	 * @return bool
	 **/
	public function increment($stats, $sampleRate = 1)
	{
		return $this->updateStats($stats, 1, $sampleRate);
	}

	/**
	 * Decrements one or more stats counters.
	 *
	 * @param string|array $stats The metric(s) to decrement.
	 * @param float|1 $sampleRate the rate (0-1) for sampling.
	 *
	 * @return bool
	 **/
	public function decrement($stats, $sampleRate = 1)
	{
		return $this->updateStats($stats, -1, $sampleRate);
	}

	/**
	 * Updates one or more stats counters by arbitrary amounts.
	 *
	 * @param string|array $stats The metric(s) to update. Should be either a string or
	 *		  array of metrics.
	 * @param int|1 $delta The amount to increment/decrement each metric by.
	 * @param float|1 $sampleRate the rate (0-1) for sampling.
	 *
	 * @return bool
	 **/
	public function updateStats($stats, $delta = 1, $sampleRate = 1)
	{
		if ($stats == null) {
			return false;
		}

		if (!is_array($stats)) {
			$stats = array($stats);
		}

		foreach($stats as $stat) {
			$data[$stat] = $delta . '|c';
		}

		return $this->_send($data, $sampleRate);
	}

	/**
	 * Squirt the metrics over UDP
	 *
	 * @param array $data The metric(s) to to send to statsd
	 * @param float|1 $sampleRate the rate (0-1) for sampling.
	 * @return bool true if success, false otherwise
	 **/
	protected function _send($data, $sampleRate = 1)
	{
		if (!$this->getEnabled()) {
			return false;
		}

		// sampling
		$sampledData = array();
		if ($sampleRate < 1) {
			foreach ($data as $stat => $value) {
				if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
					$sampledData[$stat] = $value . '|@' . $sampleRate;
				}
			}
		} else {
			$sampledData = $data;
		}

		if (empty($sampledData)) {
			return false;
		}

		// Wrap this in a try/catch - failures in any of this should be silently ignored
		try {
			$fp = fsockopen('udp://' . $this->getHost(), $this->getPort(), $errno, $errstr);
			if (! $fp) {
				return false;
			}

			foreach ($sampledData as $stat => $value) {
				$this->_writeDataToSocket($fp, $stat . ':' . $value);
			}
			fclose($fp);
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

	/**
	 * Write given data to the given socket. This is broken out into a method to enable unit
	 * testing of the send method easily by mocking this method.
	 **/
	protected function _writeDataToSocket($socket, $string)
	{
		fwrite($socket, $string);
	}

	/**
	 * Set hostname for statsd deamon host.
	 * This setter returns $this to allow for chaining.
	 *
	 * @param string the hose name to set
	 * @return Interspire_Statsd
	 */
	public function setHost($host)
	{
		$this->_host = (string)$host;
		return $this;
	}

	/**
	 * Get the statsd host name
	 *
	 * @return string host name
	 */
	public function getHost()
	{
		return $this->_host;
	}

	/**
	 * Set hostname for statsd deamon host.
	 * This setter returns $this to allow for chaining.
	 *
	 * @param string the hose name to set
	 * @return Interspire_Statsd
	 */
	public function setPort($port)
	{
		$this->_port = (int)$port;
		return $this;
	}

	/**
	 * Get the statsd deamon port number.
	 *
	 * @return integer port number
	 */
	public function getPort()
	{
		return $this->_port;
	}

	/**
	 * Set the enabled flag which controls if reporting to statsd is on or off
	 * Set it to true for reporting to be on, false otherwise
	 * This setter returns $this to allow for chaining
	 *
	 * @param bool true or false
	 * @return Interspire_Statsd
	 */
	public function setEnabled($enabled)
	{
		$this->_enabled = (bool)$enabled;
		return $this;
	}

	/**
	 * Get enabled flag logging to statsd deamon,
	 * if so returns true, false otherwise.
	 *
	 * @return bool true if enabled, false otherwise
	 */
	public function getEnabled()
	{
		return $this->_enabled;
	}
}