<?php
ini_set("error_reporting", 'on');
error_reporting(E_ALL);
class GLPI_API {
	private $username;

	private $password;

	private $apikey;

	private $host;

	private $sessionkey;

    private $verifypeer;


	function __construct($arguments) {
		$arguments = func_get_args();

        if(!empty($arguments)){
            foreach($arguments[0] as $key => $property) {
                if(property_exists($this, $key)) {
                    $this->{$key} = $property;
				}
			}
		}

		$result = $this->initSession();
		$this->sessionkey = $result['data']->session_token;
	}

    /**
     * PHP Rest CURL
     * https://github.com/jmoraleda/php-rest-curl
     * (c) 2014 Jordi Moraleda
     */

    public function exec($method, $endpoint, $obj = array()) {
        $url = $this->host;
        $url = $this->fixpath($url);
        $url .= $endpoint;
        $url = $this->fixpath($url);

        $curl = curl_init();

        switch($method) {
            case 'GET':
                if(!empty($obj)) {
                    $url .= '?' . http_build_query($obj);
                }
                break;

            case 'POST':
                curl_setopt($curl, CURLOPT_POST, TRUE);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($obj));
                break;

            case 'PUT':
            case 'DELETE':
            default:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method)); // method
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($obj)); // body
        }


        if($endpoint !== 'initSession' && $this->sessionkey == ''){
            echo "Failed: No Session Key";
            return;
        }

        $headers = array(
            'Content-Type: application/json',
			(isset($this->sessionkey) && !empty($this->sessionkey) ? 'Session-Token: ' . $this->sessionkey : 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)),
			'App-Token: ' . $this->apikey
		);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, TRUE);



        if ($this->verifypeer === FALSE){
		    curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		    curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
        }
        // Exec
        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);

        // Data
        $header = trim(substr($response, 0, $info['header_size']));
        $body = substr($response, $info['header_size']);

        return array('status' => $info['http_code'], 'header' => $header, 'data' => json_decode($body));
    }

    public function get($url, $obj = array()) {
        return $this->exec("GET", $url, $obj);
    }

    public function post($url, $obj = array()) {
        return $this->exec("POST", $url, $obj);
    }

    public function put($url, $obj = array()) {
        return $this->exec("PUT", $url, $obj);
    }

    public function delete($url, $obj = array()) {
        return $this->exec("DELETE", $url, $obj);
    }

    public function fixpath($p) {
        $p=str_replace('\\','/',trim($p));
        return (substr($p,-1)!='/') ? $p.='/' : $p;
    }

	public function initSession() {
        return $this->get('initSession');
	}

	public function killSession() {
        return $this->get('killSession');
	}

	public function getMyProfiles() {
        return $this->get('getMyProfiles');
	}

	public function getActiveProfile() {
        return $this->get('getActiveProfile');
	}

	public function changeActiveProfile($postarray) {
        return $this->post('changeActiveProfile', $postarray);
	}

	public function getMyEntities() {
		return $this->get('getMyEntities');
	}

	public function getActiveEntities() {
		return $this->get('getActiveEntities');
	}

	public function changeActiveEntities($postarray) {
		return $this->post('changeActiveEntities', $postarray);
	}

	public function getFullSession() {
		return $this->get('getFullSession');
	}

	public function getItem($item) {
		return $this->get($item);
	}

	public function getMultipleItems($getarray) {
		return $this->get('getMultipleItems', $getarray);
	}

	public function listSearchOptions($item) {
		return $this->get('listSearchOptions/'.$item);
	}

	public function search($item, $getarray) {
		return $this->get('search/'.$item, $getarray);
	}

	public function addItem($item, $post) {
		return $this->post($item, $post);
	}

	//public function addFile($file) {
	//	return $this->post('Document', $file);
	//}

	public function updateItem($item, $post) {
		return $this->put($item, $post);
	}

	public function deleteItem($item, $post) {
        return $this->delete($item, $post);
	}
}

