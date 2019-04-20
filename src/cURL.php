<?php
/**
 * Author:  Satori
 * Email:   i@ttt.moe
 * Version: 1.0.0
 *
 * https://github.com/komeiji-satori/curl
 * 一个轻量级的网络操作类，实现GET、POST、UPLOAD、DOWNLOAD常用操作，支持链式写法。
 */
namespace Satori;

use Exception;

class cURL {
	private $post;
	private $retry = 0;
	private $custom = array();
	private $option = array(
		'CURLOPT_HEADER' => 0,
		'CURLOPT_TIMEOUT' => 30,
		'CURLOPT_ENCODING' => '',
		'CURLOPT_IPRESOLVE' => 1,
		'CURLOPT_RETURNTRANSFER' => true,
		'CURLOPT_SSL_VERIFYPEER' => false,
		'CURLOPT_CONNECTTIMEOUT' => 10,
	);
	private $is_post_json = false;

	private $info;
	private $data;
	private $error;
	private $message;
	private $raw_config;

	private static $instance;

	/**
	 * Instance
	 * @return self
	 */
	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Task info
	 *
	 * @return array
	 */
	public function info() {
		return $this->info;
	}

	/**
	 * Result Data
	 *
	 * @return string
	 */
	public function data() {
		return $this->data;
	}
	/**
	 * Result JSON
	 *
	 * @return string
	 */
	public function json() {
		$prepareJSON = $this->prepareJSON($this->data);
		$decoded = \json_decode($prepareJSON, true);
		if (!function_exists('json_last_error')) {
			if ($decoded === false || $decoded === null) {
				throw new Exception('Could not decode JSON!');
			}
		} else {
			$jsonError = json_last_error();
			if (is_null($decoded) && $jsonError == JSON_ERROR_NONE) {
				throw new Exception('Could not decode JSON!');
			}
			if ($jsonError != JSON_ERROR_NONE) {
				$error = 'Could not decode JSON! ';
				switch ($jsonError) {
				case JSON_ERROR_DEPTH:
					$error .= 'Maximum depth exceeded!';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$error .= 'Underflow or the modes mismatch!';
					break;
				case JSON_ERROR_CTRL_CHAR:
					$error .= 'Unexpected control character found';
					break;
				case JSON_ERROR_SYNTAX:
					$error .= 'Malformed JSON';
					break;
				case JSON_ERROR_UTF8:
					$error .= 'Malformed UTF-8 characters found!';
					break;
				default:
					$error .= 'Unknown error!';
					break;
				}
				throw new Exception($error);
			}
		}
		return $decoded;
	}

	private function prepareJSON($input) {
		if (substr($input, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) {
			$input = substr($input, 3);
		}
		return $input;
	}

	/**
	 * Error status
	 *
	 * @return integer
	 */
	public function error() {
		return $this->error;
	}

	/**
	 * Error message
	 *
	 * @return string
	 */
	public function message() {
		return $this->message;
	}

	/**
	 * Set POST data
	 * @param array|string  $data
	 * @param null|string   $value
	 * @return self
	 */
	public function post($data, $value = null) {
		if ($this->is_post_json) {
			throw new Exception("JSON Post Body Already Set", -4);
		}
		if (is_array($data)) {
			foreach ($data as $key => $val) {
				$this->post[$key] = $val;
			}
		} else {
			if ($value === null) {
				$this->post = $data;
			} else {
				$this->post[$data] = $value;
			}
		}
		return $this;
	}
	/**
	 * Set JSON Post data
	 * @param array $data
	 * @return self
	 */
	public function postJSON($data) {
		if (!empty($this->post)) {
			throw new Exception("POST Body Already Set", -5);
		}
		$this->post = json_encode($data);
		$this->is_post_json = true;
		return $this;
	}

	/**
	 * File upload
	 * @param string $field
	 * @param string $path
	 * @param string $mimetype
	 * @param string $name
	 * @return self
	 */
	public function file($field, $path, $mimetype = null, $name = null) {
		if ($this->is_post_json) {
			throw new Exception("JSON Post Body Already Set", -4);
		}
		if (!function_exists("mime_content_type")) {
			throw new Exception("Function mime_content_type() Not Exists", -3);
		}
		if (!file_exists($path)) {
			throw new Exception("File Path " . $path . " Not Exists", -2);
		}
		if (is_null($name)) {
			$name = basename($path);
		}
		if (is_null($mimetype)) {
			$mimetype = mime_content_type($path);
		}

		if (class_exists('CURLFile')) {
			$this->set('CURLOPT_SAFE_UPLOAD', true);
			$file = curl_file_create($path, $mimetype, $name);
		} else {
			$file = "@{$path};type={$mimetype};filename={$name}";
		}
		return $this->post($field, $file);
	}

	/**
	 * Save file
	 * @param string $path
	 * @return self
	 * @throws Exception
	 */
	public function save($path) {
		if ($this->error) {
			throw new Exception($this->message, $this->error);
		}
		$fp = @fopen($path, 'w');
		if ($fp === false) {
			throw new Exception('Failed to save the content', 500);
		}
		fwrite($fp, $this->data);
		fclose($fp);
		return $this;
	}

	/**
	 * Request URL
	 * @param string $url
	 * @param array $data
	 * @return self
	 * @throws Exception
	 */
	public function url($url, $data = []) {
		if (filter_var($url, FILTER_VALIDATE_URL)) {
			if (!empty($data)) {
				if (strstr($url, "?")) {
					$url .= "&" . http_build_query($data);
				} else {
					$url .= "?" . http_build_query($data);
				}
			}
			return $this->set('CURLOPT_URL', $url);
		}
		throw new Exception('Target URL is required.', 500);
	}
	/**
	 * Start Request
	 * @return self
	 */
	public function go() {
		return $this->process();
	}

	/**
	 * Set Proxy
	 * @param string  $options
	 * @return self
	 */
	public function proxy($proxy = null) {
		if (is_array($proxy)) {
			$rand_proxy = $proxy[array_rand($proxy)];
			$this->set("CURLOPT_PROXY", $rand_proxy);
		} else {
			$this->set("CURLOPT_PROXY", $proxy);
		}

		return $this;
	}

	/**
	 * Set Timeout
	 * @param int  $timeout
	 * @return self
	 */
	public function timeout($timeout = 5) {
		$this->set("CURLOPT_TIMEOUT", $timeout);
		return $this;
	}

	/**
	 * Set User Agent
	 * @param string  $ua
	 * @return self
	 */
	public function useragent($ua) {
		$this->set("CURLOPT_USERAGENT", $ua);
		return $this;
	}

	/**
	 * Request Cookie
	 * @param array $cookie
	 * @return self
	 */
	public function cookie($cookies = []) {
		$this->raw_config['cookie'] = $cookies;
		foreach ($cookies as $key => $value) {
			$_cookie[] = $key . "=" . $value;
		}
		$cookie_str = implode('; ', $_cookie);
		$this->set('CURLOPT_COOKIE', $cookie_str);
		return $this;
	}

	/**
	 * Request Header
	 * @param array $header
	 * @return self
	 */
	public function header($header = []) {
		$this->raw_config['header'] = $header;
		$_header = [];
		foreach ($header as $key => $value) {
			$_header[] = $key . ": " . $value;
		}
		$this->set("CURLOPT_HTTPHEADER", $_header);
		return $this;
	}

	/**
	 * Set option
	 * @param array|string  $item
	 * @param null|string   $value
	 * @return self
	 */
	public function set($item, $value = null) {
		if (is_array($item)) {
			foreach ($item as $key => $val) {
				$this->custom[$key] = $val;
			}
		} else {
			$this->custom[$item] = $value;
		}
		return $this;
	}

	/**
	 * Set retry times
	 * @param int $times
	 * @return self
	 */
	public function retry($times = 0) {
		$this->retry = $times;
		return $this;
	}

	/**
	 * Task process
	 * @param int $retry
	 * @return self
	 */
	private function process($retry = 0) {
		$ch = curl_init();
		$headers = [];
		$cookies = [];
		$option = array_merge($this->option, $this->custom);
		foreach ($option as $key => $val) {
			if (is_string($key)) {
				$key = constant(strtoupper($key));
			}
			curl_setopt($ch, $key, $val);
		}

		if ($this->post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($this->is_post_json) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->post);
			} else {
				$post_field = http_build_query($this->convert($this->post));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field);
			}

		}
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
			$len = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) >= 2) {
				if (strtolower($header[0]) == "set-cookie") {
					$headers["set-cookie"][] = trim($header[1]);
				} else {
					$headers[strtolower($header[0])] = trim($header[1]);
				}
			}
			return $len;
		});

		$response = curl_exec($ch);
		$this->info = curl_getinfo($ch);

		$this->info['request_header'] = isset($this->raw_config['header']) ? $this->raw_config['header'] : [];
		$this->info['request_cookie'] = isset($this->raw_config['cookie']) ? $this->raw_config['cookie'] : [];

		$this->data = $response;
		if (isset($headers['set-cookie'])) {
			foreach ($headers['set-cookie'] as $cookie) {
				preg_match('/^\s*([^;]*)/mi', $cookie, $matches);
				list($key, $value) = explode("=", $matches[0], 2);
				$cookies[$key] = $value;
			}
		}

		$this->info['response_cookie'] = $cookies;
		$this->info['response_header'] = $headers;

		$this->error = curl_errno($ch);
		$this->message = $this->error ? curl_error($ch) : '';

		curl_close($ch);

		if ($this->error && $retry < $this->retry) {
			$this->process($retry + 1);
		}

		$this->post = array();
		$this->retry = 0;

		return $this;
	}

	/**
	 * Convert array
	 * @param array  $input
	 * @param string $pre
	 * @return array
	 */
	private function convert($input, $pre = null) {
		if (is_array($input)) {
			$output = array();
			foreach ($input as $key => $value) {
				$index = is_null($pre) ? $key : "{$pre}[{$key}]";
				if (is_array($value)) {
					$output = array_merge($output, $this->convert($value, $index));
				} else {
					$output[$index] = $value;
				}
			}
			return $output;
		}
		return $input;
	}
}
