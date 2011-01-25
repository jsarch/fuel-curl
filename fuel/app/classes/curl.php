<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Network
 * @version		1.0
 * @author		Jeremy Archuleta
 * @license		MIT License
 * @copyright	2010 - 2011 SeqCentral Team
 * @link		https://www.seqcentral.com
 */

namespace Fuel\Core;

/**
 * Curl Class
 *
 * @package		Network
 * @category	Core
 * @author		Jeremy Archuleta
 * @link		http://fuelphp.com/docs/classes/curl.html
 */
class Curl
{
	protected $_url			= null;
	protected $_username	= '';
	protected $_password	= '';
	protected $_cookie		= '';
	protected $_ssl_mode	= false;
	protected $_debug		= false;
	protected $_curlopts	= array();
	protected $_headers		= array();

    protected $_session			= null; // Contains the cURL handler for a session
	protected $_method			= null;
	protected $_error_code		= 0;
	protected $_error_string	= '';
	protected $_response		= '';
	protected $_info			= array();

	/**
	 * Sets the initial Curl filename and local data.
	 *
	 * @param   string  Curl filename
	 * @param   array   array of values
	 * @return  void
	 */
	public function __construct($url = null, $config = 'default')
	{
		if (! static::is_enabled())
		{
			exit('PHP was not built with cURL enabled. Rebuild PHP with --with-curl to use cURL');
		}

		\Config::load('curl', true);

		// If it is a string we're looking at a predefined config group
		if (is_string($config))
		{
			$config_arr = \Config::get('curl.'.$config);

			// Check that it exists
			if ( ! is_array($config_arr) or $config_arr === array())
			{
				throw new \Exception('You have specified an invalid curl connection group: '.$config);
			}

			$config = $config_arr;
		}

		// Prep the connection
		$this->_url			= (! is_null($config['url']))	? $config['url']				: $url;
		$this->_username	= (isset($config['username']))	? $config['username']			: '';
		$this->_password	= (isset($config['password']))	? $config['password']			: '';
		$this->_cookie		= (isset($config['cookie']))	? $config['cookie']				: '';
		$this->_ssl_mode	= (isset($config['ssl_mode']))	? (bool) $config['ssl_mode']	: false;
		$this->_debug		= (isset($config['debug']))		? (bool) $config['debug']		: false;
		$this->_options		= (isset($config['options']))	? $config['options']			: array();
		$this->_headers		= (isset($config['headers']))	? $config['headers']			: array();

		$url = (! is_null($url)) ? $url : (isset($config['url'])) ? $config['url'] : null;

		($url) ? $this->initialize($url) : false;
	}

	public function initialized() {
		return $this->_session === null;
	}

	public function initialize($url)
	{
		$this->reset();

		$this->_url = $url;
		$this->_session = curl_init($this->_url);

		return $this;
	}

	public function reset()
	{
		$this->_method			= '';
		$this->_error_code		= 0;
		$this->_error_string	= '';
		$this->_response		= '';
		$this->_info			= array();
		$this->_session			= null;
		$this->_url				= '';

		$this->add_curl_options(
			array(
				CURLOPT_TIMEOUT => 30,
				CURLOPT_RETURNTRANSFER =>true,
				CURLOPT_FAILONERROR => true
			)
		);

		// Only set follow location if not running securely
		if ( ! ini_get('safe_mode') && ! ini_get('open_basedir'))
		{
			$this->add_curl_option(CURLOPT_FOLLOWLOCATION, true);
		}

		if($this->_ssl_mode === true)
		{
			$this->set_ssl($this->_verify_peer, $this->_verify_host, $this->_path_to_cert);
		}

		return $this;
	}

	public function simple_get($url, $options = array())
	{
		$this->initialize($url);
		$this->get($options);
		return $this->execute();
	}

	public function simple_post($url, $params = array(), $options = array())
	{
		return $this->simple_call('post', $url, $params, $options);
	}

	public function simple_put($url, $params = array(), $options = array())
	{
		return $this->simple_call('put', $url, $params, $options);
	}

	public function simple_delete($url, $params = array(), $options = array())
	{
		return $this->simple_call('delete', $url, $params, $options);
	}

	private function simple_call($method, $url, $params, $options)
	{
		$this->initialize($url);
		$this->{$method}($params, $options);
		return $this->execute();
	}

	public function get($options = array())
	{
		$this->set_http_method('get');
		// Add in the specific options provided
		$this->add_curl_options($options);
	}

	public function post($params = array(), $options = array())
	{
		$this->_prepare('post', $params, $options);
		$this->add_curl_option(CURLOPT_POST, TRUE);
	}

	public function put($params = array(), $options = array())
	{
		$this->_prepare('put', $params, $options);

		// Override method, I think this overrides $_POST with PUT data but... we'll see eh?
		$this->add_curl_option(CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: PUT'));
	}

	public function delete($params = array(), $options = array())
	{
		$this->_prepare('delete', $params, $options);
	}

	private function _prepare($method, $params, $options)
	{
		$this->set_http_method($method);

		// If its an array (instead of a query string) then format it correctly
		if (is_array($params))
		{
			$params = http_build_query($params, NULL, '&');
		}
		$this->add_curl_option(CURLOPT_POSTFIELDS, $params);

		// Add in the specific options provided
		$this->add_curl_options($options);
	}

	public function execute()
	{
		if (! empty($this->_headers))
		{
			$this->add_curl_option(CURLOPT_HTTPHEADER, $this->_headers);
		}

		curl_setopt_array($this->_session, $this->get_curl_options());

		// Execute the request & and hide all output
		if (\Fuel::$profiling)
		{
			\Profiler::mark($this->_method.' '.$this->_url.' Start');
		}
		$this->_response = curl_exec($this->_session);
		if (\Fuel::$profiling)
		{
			\Profiler::mark($this->_method.' '.$this->_url.' End');
		}
		$this->_info = curl_getinfo($this->_session);

		// Request failed
		if ($this->_response === false)
		{
			$this->_error_code = curl_errno($this->_session);
			$this->_error_string = curl_error($this->_session);
		}
		// Request successful
		else
		{
			// do nothing
		}

		curl_close($this->_session);
		$this->_session = null;
		return $this->_response;
	}

	public function set_ssl( $verify_peer = TRUE, $verify_host = 2, $path_to_cert = NULL) {
		if ($verify_peer)
		{
			$this->add_curl_options(
				array(
					CURLOPT_SSL_VERIFYPEER => TRUE,
					CURLOPT_SSL_VERIFYHOST => $verify_host,
					CURLOPT_CAINFO => $path_to_cert
				)
			);
		}
		else
		{
			$this->add_curl_option(CURLOPT_SSL_VERIFYPEER, FALSE);
		}
		return $this;
	}

	public function set_http_method($method)
	{
		$this->_method = $method;
		return $this->add_curl_option(CURLOPT_CUSTOMREQUEST, strtoupper($method));
	}

	public function add_http_header($header, $content = null)
	{
		$this->_headers[] = $content ? "$header: $content" : $header;
	}

	public function response()
	{
		return $this->_response;
	}

	public function info()
	{
		return $this->_info;
	}

	public function get_curl_options()
	{
		return $this->_curlopts;
	}

	public function add_curl_options($options)
	{
		foreach($options as $code => $value) {
			$this->add_curl_option($code, $value);
		}
		return $this;
	}

	public function add_curl_option($code, $value)
	{
		if (is_string($code) && !is_numeric($code))
		{
			$code = constant('CURLOPT_' . strtoupper($code));
		}
		$this->_curlopts[$code] = $value;
		return $this;
	}

	public static function is_enabled()
	{
		return function_exists('curl_init');
	}

	public function debug()
	{
		echo "=============================================<br/>\n";
		echo "<h2>CURL Test</h2>\n";
		if ($this->_error_string)
		{
			echo "=============================================<br/>\n";
			echo "<h3>Errors</h3>";
			echo "<strong>Code:</strong> ".$this->_error_code."<br/>\n";
			echo "<strong>Message:</strong> ".$this->_error_string."<br/>\n";
		}
		echo "=============================================<br/>\n";
		echo "<h3>Info</h3>";
		echo "<pre>";
		print_r($this->_info);
		echo "</pre>";
		echo "<h3>Headers</h3>";
		echo "<pre>";
		print_r($this->_headers);
		echo "</pre>";
		echo "<h3>Curl Options</h3>";
		echo "<pre>";
		print_r($this->_curlopts);
		echo "</pre>";
		echo "=============================================<br/>\n";
		echo "<h3>response</h3>\n";
		echo "<code>".nl2br(htmlentities($this->_response))."</code><br/>\n\n";
	}




    public function http_login($username = '', $password = '', $type = 'any')
    {
		$this->option(CURLOPT_HTTPAUTH, constant('CURLAUTH_'.strtoupper($type) ));
        $this->option(CURLOPT_USERPWD, $username.':'.$password);
        return $this;
    }

    public function proxy($url = '', $port = 80)
    {
        $this->option(CURLOPT_HTTPPROXYTUNNEL, TRUE);
        $this->option(CURLOPT_PROXY, $url.':'. $port);
        return $this;
    }

    public function proxy_login($username = '', $password = '')
    {
        $this->option(CURLOPT_PROXYUSERPWD, $username.':'.$password);
        return $this;
    }

    public function set_cookies($params = array())
    {
        if (is_array($params))
        {
            $params = http_build_query($params, NULL, '&');
        }

        $this->option(CURLOPT_COOKIE, $params);
        return $this;
    }



}

/* End of file curl.php */