#!/usr/bin/php
<?php
$DR = dirname(__FILE__);
include_once ($DR.'/../_lib/xmlrpc-3.0.0.beta/xmlrpc.inc');
$debug = false;
if(is_file($DR."/config.php"))
	include $DR."/config.php";
else {
	echo "config.php not found";
	die;
}
if($debug) {
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
}
set_time_limit(0);
mb_internal_encoding("UTF-8");

$moviesToSend = array();

$cur_day = date('d-m-Y');
$days    = array($cur_day);
for ($i = 1; $i <= 7; $i++) {
	$cur_day = date('d-m-Y', strtotime($cur_day . ' +1 day'));
	$days[]  = date('d-m-Y', strtotime($cur_day));
}

$all_cinemas = array();
foreach ($days as $num => $day) {
	$url = 'http://mobile.afisha.ru/ekaterinburg/movie/theme/' . $day . '/';

	if ($debug) echo "parse " . $url . "\n";

	$all_cinemas = getFilms($url, $day, $all_cinemas);
}

if ($debug) {
	echo "cnt films: " . sizeof($all_cinemas) . "\n";
	//print_R($all_cinemas);
}


$fn = fopen($DR . '/afisha.mobile.parse.txt', 'w');
fwrite($fn, serialize($all_cinemas));
fclose($fn);

if ($debug) echo "Sending afisha.listMovies\n";
$eMovies = sendData('afisha.listMovies');
foreach ($all_cinemas as $movieId => $movie) {
	if ($debug) {
		echo $movie['name'];
		echo ": ";
	}

	$found   = false;
	foreach ($eMovies as $eMovieId => $eMovie) {
		if (matchName($movie['name'], $eMovie['title'])) $found = $eMovie;
		if (matchName($movie['name'], $eMovie['originalTitle'])) $found = $eMovie;
		if($eMovie['synonym'])
			foreach(unserialize($eMovie['synonym']) as $syn)
				if (matchName($movie['name'], $syn)) $found = $eMovie;

	}

	if (!$found) {
		if ($debug) echo "film not found in syn\n";
		$moviesToSend[] = $movie;
	}
	elseif ($found && $found['edited'] == '0') {
		$movie['externalId']    = $found['id'];
		$moviesToSend[] = $movie;
		if ($debug) echo $movie['externalId'] . "\n";
	}
}
if ($debug) echo "Sending afisha.postMovie, count: " . sizeof($moviesToSend) . "\n";
sendData('afisha.postMovie', $moviesToSend);

if ($debug) echo "Sending afisha.listMovies\n";
$eMovies = sendData('afisha.listMovies');
if ($debug) echo "Sending afisha.listPlaces\n";
$ePlaces = sendData('afisha.listPlaces');

foreach ($all_cinemas as $movieId => $movie) {
	$found = false;
	foreach ($eMovies as $eMovieId => $eMovie) {
		if (matchName($movie['name'], $eMovie['title'])) $found = $eMovie;
		if (matchName($movie['name'], $eMovie['originalTitle'])) $found = $eMovie;
		if($eMovie['synonym'])
			foreach(unserialize($eMovie['synonym']) as $syn)
				if (matchName($movie['name'], $syn)) $found = $eMovie;
	}
	if ($found) {
		$all_cinemas[$movieId]['externalId'] = $found['id'];
	} else {
		unset($all_cinemas[$movieId]);
	}
}

$placesToSend = array();

foreach ($all_cinemas as $movieId => $movie) {

	foreach ($movie['places'] as $placeName => $place) {
		if (isset($accessPlaces[$placeName]) && $accessPlaces[$placeName] == false) {
			if ($debug) echo $placeName . " - закрыт для импорта\n";
			continue;
		}
		if ($debug) echo "\t" . $placeName . ": \n";
		$found = false;
		foreach ($ePlaces as $ePlaceId => $ePlace) {
			if (matchName($placeName, $ePlace['name'])) $found = $ePlace;
			if($ePlace['synonym'])
				foreach(unserialize($ePlace['synonym']) as $syn)
					if (matchName($placeName, $syn)) $found = $ePlace;
		}
		if (!$found) {
			if ($debug) echo "place not found in syn\n";
			$placesToSend[$placeName] = array('name' => $placeName);
		} else
			if ($debug) echo "place found in syn\n";
	}
}
$pre = array();
foreach ($placesToSend as $p) {
	$pre[] = $p;
}
if (sizeof($pre)) {
	if ($debug) echo "Sending afisha.postPlace, count: " . sizeof($pre) . "\n";
	sendData('afisha.postPlace', $pre);
	if ($debug) echo "Sending afisha.listPlaces\n";
	$ePlaces = sendData('afisha.listPlaces');
}

$placeStack  = array();
$seanceStack = array();

foreach ($all_cinemas as $movieId => $movie) {

	foreach ($movie['places'] as $placeName => $seances) {
		if (isset($accessPlaces[$placeName]) && $accessPlaces[$placeName] == false) {
			if ($debug) echo $placeName . " - закрыт для импорта\n";
			continue;
		}
		$found = false;
		foreach ($ePlaces as $ePlaceId => $ePlace) {
			if (matchName($placeName, $ePlace['name'])) $found = $ePlace;
			if($ePlace['synonym'])
				foreach(unserialize($ePlace['synonym']) as $syn)
					if (matchName($placeName, $syn)) $found = $ePlace;
		}
		if ($found) {
			$placeStack[$placeName] = $found;
			foreach ($seances as $num => $datetime) {
				$time = strtotime($datetime);

				$seanceStack[] = array(
					'placeId'    => $found['id'],
					'movieId'    => $movie['externalId'],
					'seanceTime' => $time,
				);
			}
		}
	}
}

for($i = 0; $i<sizeof($seanceStack);$i += 250){
	if($debug) echo "afisha.postSeances " .$i . " - " . min(sizeof($seanceStack),($i+250)) . " of total " . sizeof($seanceStack) ."\n";
	sendData('afisha.postSeances',array_slice($seanceStack,$i,250));
}

/**
 * Получаем список фильмов
 *
 * @param unknown_type $url            -    URL дня парсинга
 * @param unknown_type $dayparse    -    дата парсинга
 * @param unknown_type $all_cinemas    -    Массив ранее обработанных фильмов
 * @return unknown
 */
function getFilms($url, $dayparse, $all_cinemas)
{
	$data_Page = loadUtfData($url);

	// Получаем ссылки на фильмы и название фильмов
	preg_match_all('#<div class="item">.+?href="(.+?)">(.+?)</a>#sim', $data_Page, $pars_found);
	$parsedFilmsHref   = $pars_found[1];
	$parsedFilmsTitles = $pars_found[2];


	// Получаем сформированный список фильмов, которых надо обработать
	if (sizeof($parsedFilmsHref)) {
		foreach ($parsedFilmsHref as $hrefnum => $currentFilmHref) {
			$currentFilmTitle = trim($parsedFilmsTitles[$hrefnum]);
			$currentFilmHref  = 'http://mobile.afisha.ru' . $currentFilmHref;

			$found = -1;
			foreach ($all_cinemas as $foundnum => $fc) {
				if (trim($fc['name']) == $currentFilmTitle) {
					$found = $foundnum;
					break;
				}
			}

			if ($found < 0) {
				$all_cinemas[] = array(
					'href'=> $currentFilmHref,
					'name'=> $currentFilmTitle,
				);

				$currentFilm = $all_cinemas[sizeof($all_cinemas) - 1];
				$currentFilmIndex = sizeof($all_cinemas) - 1;
			}
			else {
				$currentFilm = $all_cinemas[$found];
				$currentFilmIndex = $found;
			}


			$card_Page  = loadUtfData($currentFilmHref);
			$loadedFilm = getCinemaData($card_Page, $currentFilmHref, $dayparse, $currentFilm, $found);

			$currentFilm = array_merge($currentFilm, $loadedFilm);
			$all_cinemas[$currentFilmIndex] = $currentFilm;
		}
	}

	return $all_cinemas;
}

/**
 * Парсинг карточки фильма
 *
 * @param unknown_type $pageData        -    HTML страницы
 * @param unknown_type $url_card_film    -    Урл с которого парсим
 * @param unknown_type $dayparse        -     Дата сеансов
 * @return unknown
 */
function getCinemaData($pageData, $url_card_film, $dayparse, $foundedFilm, $found_num)
{
	if ($found_num < 0) {
		$cinemaData = array();

		preg_match('#<p>(.+?)</p>#sim', $pageData, $data);
		$data = $data[1];
		$data = str_replace('<br />', '<br/>', $data);
		$data = str_replace('<br>', '<br/>', $data);
		$data = str_replace('<br/>', '{|}', $data);
		$data = strip_tags($data);

		$roles   = '';
		$reziser = '';
		$zanr    = '';

		$data = explode('{|}', $data);
		foreach ($data as $num => $line) {
			$line = trim($line);

			if (mb_stristr($line, 'Теги', false, 'UTF-8')) {
				$tmp  = explode(':', $line);
				$zanr = normalText($tmp[1]);
				continue;
			}
			elseif (mb_stristr($line, 'Режиссер', false, 'UTF-8')) {
				$tmp     = explode(':', $line);
				$reziser = normalText($tmp[1]);
				continue;
			}
			elseif (mb_stristr($line, 'В ролях', false, 'UTF-8')) {
				$tmp   = explode(':', $line);
				$roles = normalText($tmp[1]);
				continue;
			}
			else {
				$other = normalText($line);
				continue;
			}
		}

		$time = 0;
		$cs   = array();

		$tmp = $other;
		$tmp = explode(',', $tmp);

		foreach ($tmp as $num => $item) {
			if (preg_match('#\d{1,3}\s#sim', trim($item))) {
				$time = (int)$item;
				continue;
			}
			elseif (preg_match('#\d\d\d\d#', trim($item))) {
				$year = (int)$item;
				continue;
			}
			else {
				$cs[] = trim($item);
				continue;
			}
		}

		preg_match('#</h1>.+?<p class="no-mr-b">(.+?)<br>.+?</p>#sim', $pageData, $m);
		if (strlen($m[1]) < 64) $cinemaData['originalTitle'] = $m[1]; else $cinemaData['originalTitle'] = '';

		$cinemaData['genre']    = $zanr;
		$cinemaData['staring']  = trim($roles);
		$cinemaData['director'] = $reziser;

		if (isset($year)) $cinemaData['year'] = $year;
		if (isset($year)) {
			$x                     = implode(', ', $cs);
			$cinemaData['country'] = normalText($cs[0]);
		}
		if (isset($time) && $time > 0) $cinemaData['duration'] = $time;

		$places = array();

		$cinemaData['places'] = getPlacesPage($pageData, $url_card_film, $dayparse, $places);
	}
	else {
		$foundedFilm['places'] = getPlacesPage($pageData, $url_card_film, $dayparse, $foundedFilm['places']);

		$cinemaData = $foundedFilm;
	}

	return $cinemaData;
}

function getPlacesPage($pageData, $url_card_film, $dayparse, $foundPlaces = array())
{
	$places = $foundPlaces;

	$regexp = $url_card_film . 'p\d+/';

	$new = getPlaces($pageData, $dayparse, $foundPlaces);

	if ($places == NULL) $places = array();

	$places = array_merge($places, $new);

	preg_match_all('#' . $regexp . '#sim', $pageData, $urlsPages);
	$urlsPages = array_unique($urlsPages[0]);
	if (sizeof($urlsPages)) {
		foreach ($urlsPages as $urlPage) {
			$pageDataNext = loadUtfData($urlPage);

			$places = array_merge($places, getPlaces($pageDataNext, $dayparse, $foundPlaces));
		}
	}

	return $places;
}

function getPlaces($pageData, $dayparse, $foundPlaces)
{
	preg_match_all('#<div class="b-t pd-t pd-l item">(.+?:\d\d[\s]+)</div>#sim', $pageData, $result);

	$result = $result[1];

	$tmp = array();

	foreach ($result as $num => $item) {
		preg_match('#<a.+?>(.+?)</a>#sim', $item, $m);
		$place_name = $m[1];

		preg_match_all('#\b(\d{1,2}:\d\d)\b#sim', $item, $m);
		$seanses = $m[1];

		$foundPlace = '';
		if (sizeof($foundPlaces)) {
			foreach ($foundPlaces as $_placeName => $_seances) {
				if ($_placeName == $place_name) {
					$foundPlace = $_placeName;
					break;
				}
			}
		}

		if ($foundPlace == '') {
			$tmp[$place_name] = array();
		} else {
			$tmp[$place_name] = $foundPlaces[$foundPlace];
		}

		foreach ($seanses as $num => $s) {
			$seanses[$num] = date('Y-m-d H:i:s', strtotime($dayparse . ' ' . $s));
		}

		$tmp[$place_name] = array_merge($tmp[$place_name], $seanses);
	}

	return $tmp;
}

/****************************************************/

function normalText($s)
{
	$s = str_replace("\n", "", str_replace("\r", "", str_replace("\t", "", trim($s))));
	$s = preg_Replace('#[ ]+#', ' ', $s);
	$s = str_replace(' , ', ', ', $s);
	return $s;
}

function loadUtfData($url)
{
	$page = @file_get_contents($url);

	if (strlen($page) < 300) $page = '';

	return $page;
}

function sendData($name, $params = array())
{
	global $apiKey, $apiUrl;
	$client                           = new xmlrpc_client($apiUrl);
	$client->request_charset_encoding = 'UTF-8';
	$client->return_type              = 'phpvals';
	$client->debug                    = 0;
	$msg                              = new xmlrpcmsg($name);
	$p1                               = new xmlrpcval($apiKey, 'string');
	$msg->addparam($p1);

	if ($params) {
		$p2 = php_xmlrpc_encode($params);
		$msg->addparam($p2);
	}
	$client->accepted_compression = 'deflate';
	$res                          = $client->send($msg, 60 * 5, 'http11');
	if ($res->faultcode()) {
		print "An error occurred: ";
		print " Code: " . htmlspecialchars($res->faultCode());
		print " Reason: '" . htmlspecialchars($res->faultString()) . "' \n";
		die;
	} else
		return $res->val;
}

function matchName($a, $b)
{
	$a = mb_strtolower($a);
	$b = mb_strtolower($b);
	$a = preg_replace('|[^\p{L}\p{Nd}]|u', '', $a);
	$b = preg_replace('|[^\p{L}\p{Nd}]|u', '', $b);
	if ($a == $b) return true;
	return false;
}

?>