<?php

namespace Neogate\SmsConnect;


class SmsConnect
{
    /** @var string */
    private $login;

    /** @var string */
    private $password;

	const API_URL = 'https://api.smsbrana.cz/smsconnect/http.php';

	const ACTION_SEND_SMS = 'send_sms';

	const ACTION_INBOX = 'inbox';

	const USER_AGENT = 'SmsConnect PHP v2.0';


	/**
	 * @param string $login
	 * @param string $password
	 */
	public function __construct($login, $password)
	{
        if ($login === NULL) {
            throw new InvalidArgumentException('Empty login');
        }

        if ($password === NULL) {
            throw new InvalidArgumentException('Empty password');
        }

        $this->login    = $login;
        $this->password = $password;
	}


	/**
	 * @return array
	 */
	public function getInbox()
	{
	    $data = $this->getAuth();
		$data['action'] = self::ACTION_INBOX;

		$requestUrl = $this->getRequestUrl($data);
		$response = $this->getRequest($requestUrl);

		return $response;
	}


	/**
	 * @param string $number phone number of receiver
	 * @param string $text message for receiver
	 * @return array
	 */
	public function sendSms($number, $text)
	{
	    $data = $this->getAuth();
		$data['action'] = self::ACTION_SEND_SMS;
		$data['number'] = $number;
		$data['message'] = urlencode($text);

		$requestUrl = $this->getRequestUrl($data);
		$response = $this->getRequest($requestUrl);

		return $response;
	}


	/**
	 * @return array
	 */
	protected function getAuth()
	{
		$time = date("Ymd")."T".date("His");
		$salt = $this->getSalt(20);

		$authData = array(
			'login' => $this->login,
			'salt' => $salt,
			'time' => $time,
			'hash' => md5($this->password . $time . $salt),
		);

		return $authData;
	}


	/**
	 * @param array $authData
	 * @return string
	 */
	protected function getRequestUrl($authData)
	{
		$get = array();
		foreach ($authData as $key => $item) {
			$get[] = $key . '=' . $item;
		}

		return self:: API_URL . '?' . implode('&', $get);
	}


	/**
	 * @param int $length
	 * @return string
	 */
	protected function getSalt($length = 10) {
	    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ:';
	    $maxPos = strlen($characters) - 1;
	    $string = '';
	    for ($p = 0; $p < $length; $p++) {
	        $string .= $characters[mt_rand(0, $maxPos)];
	    }

	    return $string;
	}


	/**
	 * @param $url
	 * @return mixed
	 */
	protected function makeRequest($url)
	{
		$curl = curl_init();
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => $url,
		    CURLOPT_USERAGENT => self::USER_AGENT,
		));
		$response = curl_exec($curl);
		curl_close($curl);

		$response = $this->convertToArray($response);

		return $response;
	}


	/**
	 * @param string $url
	 * @return array
	 */
	protected function getRequest($url)
	{
		$response = $this->makeRequest($url);
		$this->validateResponse($response);

		return $response;
	}


	/**
	 * @param array $response
	 */
	protected function validateResponse($response)
	{
		if (isset($response['err'])) {
			if ($response['err'] === '1') {
				throw new RuntimeException('Unknown error');

			} elseif ($response['err'] === '2' || $response['err'] === '3') {
				throw new MemberAccessException('Incorrect login or password');

			} elseif ($response['err'] === '5') {
				throw new InvalidStateException('Disallowed remote IP, see your SmsConnect setting');

			} elseif ($response['err'] === '8') {
				throw new InvalidStateException('Database connection error');

			} elseif ($response['err'] === '9') {
				throw new InvalidStateException('No credit');

			} elseif ($response['err'] === '10') {
				throw new InvalidArgumentException('Invalid recipient number');

			} elseif ($response['err'] === '11') {
				throw new InvalidArgumentException('Empty sms text');

			} elseif ($response['err'] === '12') {
				throw new InvalidArgumentException('Text is too long, allowed maximum is 495 chars');

			}
		}
	}


	/**
	 * @param string $xmlString
	 * @return array
	 */
	protected function convertToArray($xmlString)
	{
		$xml = simplexml_load_string($xmlString);
		$json = json_encode($xml);
		return json_decode($json,TRUE);
	}

}
