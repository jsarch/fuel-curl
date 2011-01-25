<?php
/**
 * An example Controller.  This shows the most basic usage of a Controller.
 */
class Controller_CurlTest extends Controller {

	public function action_index()
	{
		$this->render('welcome/index');
	}

	public function action_initialize()
	{
		$curl = new Curl();
		print("<pre>");
		print_r($curl);

		print $curl->initialized() . "\n";
		print_r($curl);
		
	}

	public function action_enabled()
	{
		echo 'Curl is enabled: '. (Curl::is_enabled()) ? 'yes' : 'no';
	}

	public function action_simpleget()
	{
		$curl = new Curl();
		$curl->simple_get("http://www.example.com");
		var_dump($curl->response());
		// $this->render('welcome/index');
	}

	public function action_get()
	{
		$curl = new Curl();
		$curl->initialize("https://www.google.com");
		
		print $curl->initialized() . "\n";
		print_r($curl);

		$curl->get();
		print_r($curl);

		$curl->add_http_header("X-API-KEY", 12345);

		$curl->execute();
		print_r($curl);

		$curl->debug();
		var_dump($curl->response());
	}

	public function action_404()
	{
		// Set a HTTP 404 output header
		Output::$status = 404;
		$this->render('welcome/404');
	}
}