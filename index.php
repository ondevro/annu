<?php

set_time_limit(300);

/*
$proxy_ip_list = file(dirname(__FILE__) . '/proxys.txt', FILE_IGNORE_NEW_LINES);

define('IPort', $proxy_ip_list[array_rand($proxy_ip_list)]);
define('IP', explode(':', IPort)[0]);
*/
define('IP', $_SERVER['REMOTE_ADDR']);

final class scrape {
	public function form() {
		echo '<html>
				<head>
					<script src="js.js" type="text/javascript"></script>
				</head>
				<body>
					<div class="content">
						<p id="list_search">
							<input type="file" value="scan file" id="scan_file" />
							<span id="search_status"></span>
						</p>
					</div>
					<table id="contacts">
						<thead>
							<th>Name</th>
							<th>Address</th>
							<th>Phone</th>
						</thead>
						<tbody>
						</tbody>
					</table
				</body>
				</html>
		';
	}

	private function _cURL($url, $parameters) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0');
		curl_setopt($ch, CURLOPT_REFERER, 'http://www.annu.com/');

		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTREDIR, 0);

		//curl_setopt($ch, CURLOPT_PROXY, IPort);

		return $ch;
	}

	private function checkRequest($url, $parameters) {

	    $ch = $this->_cURL($url, $parameters);
	    $content = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    curl_close($ch);

	    if ($status != 200) {
	    	$this->checkRequest($url, $parameters);
	    }

	    return $content;
	}

	private function _processRequest($url, $parameters = array()) {
	    $content = $this->checkRequest($url, $parameters);

		$dom = new DOMDocument();
		@$dom->loadHTML($content);

	    $xPath = new DOMXPath($dom);

	    if($xPath->query('//div[@align="center"]//strong[contains(text(), "En raison d\'un trop grand nombre de requêtes , votre adresse ip a été momentanément bloquée. Nous vous rappelons que l\'usage de robots à des fins d\'aspiration de la base est formellement interdit et susceptible d\'entraîner des poursuites judiciaires ")]')->length > 0) {
	    	return array('error' => true);
	    } else {
	        return array('error' => false, 'data' => $content);
	    }
	}

	public function get_data($q, $p = 1) {
		$data = array();

		$data['scrape_parameters'] = array(
						'page' => $p,
						'q' => $q,
						'type' => 1
		);

		$get_content = $this->_processRequest('http://www.annu.com/includes/resultats.php', $data['scrape_parameters']);

		if($get_content['error'] === false) {
			$dom = new DOMDocument();
			@$dom->loadHTML($get_content['data']);

	//$xml = simplexml_import_dom($dom);
	//echo '<pre>', print_r($xml, true), '</pre>';

			$xPath = new DOMXPath($dom);

			$captcha_image_url = $xPath->query('//div[@align="center"]//img/@src');

			if($captcha_image_url->length == 0) {
				$data['pages'] = ceil((int) $xPath->query('//div[@class="top"]//span/text()')->item(0)->wholeText / 10);

					$j = 0;

					foreach ($xPath->query('//ol[@class="list"]//li[@class="entry"]') as $special) {

						$data['special_list'][$j] = array(
										'name' => $xPath->query('//h2', $special)->item($j)->nodeValue,
										'address' => $xPath->query('//p', $special)->item($j)->nodeValue
						);

							$data['special_list'][$j]['phone'] = $xPath->query('//ul[@class="phone"]//li[not(contains(text(), "Fax"))]//span', $special)->item($j)->nodeValue;

	   					$j++;
					}

				$return = array('error' => false, 'captcha' => false, 'data' => $data['special_list'], 'pages' => $data['pages']);
			} else {
				$return = array('error' => false, 'captcha' => $captcha_image_url->item(0)->value);
			}
		} else {
			$return = array('error' => true);
		}

		return json_encode($return);
	}

	public function save($data) {
		require_once('PHPExcel.php');

		$data = json_decode($data);

		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);
		$i = 1;

		foreach ($data->data as $value) {
		    $objPHPExcel->getActiveSheet()->SetCellValue('A' . $i, $value->Name);
		    $objPHPExcel->getActiveSheet()->SetCellValue('B' . $i, $value->Address);
		    $objPHPExcel->getActiveSheet()->SetCellValue('C' . $i, $value->Phone);

		    $i++;
		}

		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$objWriter->save('excel/' . $data->q . '.xls');
	}

	public function captha($post_fields) {

		/*echo '<img src="http://www.annu.com/' . $captcha_image_url->item(0)->value . '" />';
		echo '<form method="post">';
		echo '<input type="text" name="captcha" />';
		echo '</form>';*/

				/*$post_fields = array(
									'cap' => $_POST['captcha'],
									'ip' => '194.67.201.106',
									'n' => 10,
									'page' => 2,
									'q' => $q,
									's' => $q
								);*/
		$this->_processRequest('http://www.annu.com/', $post_fields);
	}
}


$init = new scrape();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'data':
        $p = empty($_GET['p']) ? 1 : (int) $_GET['p'];
        //header('Content-Type: application/json');
        echo $init->get_data($_GET['q'], $p);
        break;
    case 'save':
    	$init->save(file_get_contents("php://input"));
    	break;
    case 'captcha':
        $init->captha(array('cap' => $_GET['c'], 'ip' => IP, 'n' => 10, 'page' => $_GET['p'], 'q' => $_GET['q'], 's' => $_GET['q']));
        break;
    default:
		$init->form();
}


//echo '<p>' . microtime(true) - $data['script_start'] . '</p>';