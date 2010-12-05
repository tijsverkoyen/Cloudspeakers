<?php
/**
 * Cloudspeakers class
 *
 * This source file can be used to communicate with Cloudspeakers (http://cloudsspeakers.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-cloudspeakers-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-cloudspeakers@verkoyen.eu>
 * @version			1.0.0
 *
 * @copyright		Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class Cloudspeakers
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the cloudspeakers-api
	const API_URL = 'http://api.cloudspeakers.com';

	// port for the cloudspeakers-API
	const API_PORT = 80;

	// cloudspeakers-API version
	const API_VERSION = '2.0';

	// current version
	const VERSION = '1.0.0';


	/**
	 * The API-key that will be used for authenticating
	 *
	 * @var	string
	 */
	private $apiKey = null;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $apiKey	The API-key that has to be used for authentication
	 */
	public function __construct($apiKey = null)
	{
		if($apiKey !== null) $this->setApiKey($apiKey);
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $url
	 * @param	array[optional] $parameters
	 */
	private function doCall($url, $parameters = array())
	{
		// redefine
		$url = (string) $url;
		$parameters = (array) $parameters;

		// add required parameters
		$apiKey = $this->getApiKey();

		if($apiKey != null) $parameters['api_key'] = $apiKey;

		// init var
		$queryString = '';

		// loop parameters and add them to the queryString
		foreach($parameters as $key => $value) $queryString .= '&'. $key .'='. urlencode(utf8_encode($value));

		// cleanup querystring
		$queryString = trim($queryString, '&');

		// append to url
		$url .= '?'. $queryString;

		// prepend
		$url = self::API_URL .'/'. self::API_VERSION .'/'. $url;

		// set options
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();

		// init
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute
		$response = curl_exec($curl);
		$headers = curl_getinfo($curl);

		// fetch errors
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);

		// close
		curl_close($curl);

		// invalid headers
		if(!in_array($headers['http_code'], array(0, 200)))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';

				// stop the script
				exit;
			}

			// throw error
			throw new CloudspeakersException('Invalid headers ('. $headers['http_code'] .')', (int) $headers['http_code']);
		}

		// error?
		if($errorNumber != '') throw new CloudspeakersException($errorMessage, $errorNumber);

		// we expect XML so decode it
		$xml = @simplexml_load_string($response, null, LIBXML_NOCDATA);

		// validate json
		if($xml === false) throw new CloudspeakersException('Invalid XML-response');

		// is error?
		if(isset($xml->errors->error))
		{
			$code = (isset($xml->code)) ? (int) $xml->code : null;
			$message = (string) $xml->errors->error;

			// throw error as an exception
			throw new CloudspeakersException($message, $code);
		}

		// return
		return $xml;
	}


	/**
	 * Get the APIkey
	 *
	 * @return	mixed
	 */
	private function getApiKey()
	{
		return $this->apiKey;
	}


	/**
	 * Get the timeout that will be used
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Cloudspeakers/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP Cloudspeakers/'. self::VERSION .' '. $this->userAgent;
	}


	/**
	 * Set the API-key that has to be used
	 *
	 * @return	void
	 * @param	string $apiKey
	 */
	private function setApiKey($apiKey)
	{
		$this->apiKey = (string) $apiKey;
	}


	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP Cloudspeakers/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


	/**
	 * Retrieve the artists or albums that are most written about
	 *
	 * @return	array
	 * @param	string $entity				Which entity should be retrieved? Possible values are: artists, albums.
	 * @param 	int[optional] $maxWeblinks	Maximum number of weblinks to display.
	 */
	public function getHotlist($entity, $max = 10)
	{
		// possible values
		$possibleEntities = array('artists', 'albums');

		// redefine
		$entity = (string) $entity;
		$max = (int) $max;

		// validate
		if(!in_array($entity, $possibleEntities)) throw new CloudspeakersException('Invalid entity.');

		// build url
		$url = 'hotlists/'. $entity .'.xml';

		// build parameters
		$parameters['max'] = $max;

		// make the call
		$response = $this->doCall($url, $parameters);

		// init var
		$return = array();

		// validate
		if(!isset($response->hotlist)) throw new CloudspeakersException('Invalid response.');

		// artists?
		if(isset($response->hotlist->artist))
		{
			// loop artists
			foreach($response->hotlist->artist as $artist)
			{
				// init var
				$temp = array();

				// set properties
				$temp['gid'] = (string) $artist->gid;
				$temp['name'] = utf8_decode((string) $artist->name);
				$temp['rank'] = (int) $artist->rank;

				// add
				$return[] = $temp;
			}
		}

		// albums?
		if(isset($response->hotlist->album))
		{
			// loop artists
			foreach($response->hotlist->album as $album)
			{
				// init var
				$temp = array();

				$temp['gid'] = (string) $album->gid;
				$temp['name'] = utf8_decode((string) $album->name);
				$temp['artist']['gid'] = (string) $album->artist->gid;
				$temp['artist']['name'] = utf8_decode((string) $album->artist->name);
				$temp['rank'] = (int) $album->rank;

				// add
				$return[] = $temp;
			}
		}

		// return
		return $return;
	}


	/**
	 * With this command you can get the playlists for audio files or video files
	 *
	 * @return	array
	 * @param	string $entity				Which entity should be retrieved? Possible values are: artist, user, source, festival.
	 * @param	string[optional] $mbid		ID of the artist from MusicBrainz. Only used when entity is artist.
	 * @param	int[optional] $username		Username of the user or festival from teh Cloudspeakers database.
	 * @param	string[optional] $max		Maximum number of tracks/videos to display.
	 * @param	string[optional] $type		Type of playlist. Possible values are: both, audio, video
	 */
	public function getPlaylists($entity, $mbid = null, $username = null, $max = 50, $type = 'both')
	{
		// possible values
		$possibleEntities = array('artist', 'user', 'source', 'festival');
		$possibleTypes = array('both', 'audio', 'video');

		// redefine
		$entity = (string) $entity;
		$mbid = ($mbid !== null) ? (string) $mbid : null;
		$username = ($username !== null) ? (string) $username : null;
		$max = (int) $max;
		$type = (string) $type;

		// validate
		if(!in_array($entity, $possibleEntities)) throw new CloudspeakersException('Invalid entity.');
		if(!in_array($type, $possibleTypes)) throw new CloudspeakersException('Invalid type.');
		if(($entity == 'user' || $entity == 'festival') && $username == '') throw new CloudspeakersException('Username is required.');

		// build url
		$url = 'playlists/'. $entity;
		if($mbid !== null) $url .= '/'. $mbid;
		if($username !== null) $url .= '/'. $username;
		$url .= '.xml';

		// build parameters
		$parameters['max'] = $max;

		// make the call
		$response = $this->doCall($url, $parameters);

		// init
		$return = array();

		// set properties
		$return['title'] = utf8_decode((string) $response->title);
		$return['info'] = utf8_decode((string) $response->info);
		$return['date'] = (int) strtotime((string) $response->date);
		$return['tracklist'] = array();

		// loop tracks
		foreach($response->trackList->item as $item)
		{
			// init var
			$temp = array();

			// set properties
			$temp['title'] = utf8_decode((string) $item->title);
			$temp['annotation'] = utf8_decode((string) $item->annotation);
			$temp['creator'] = utf8_decode((string) $item->creator);
			$temp['info'] = (string) $item->info;
			$temp['location'] = utf8_decode((string) $item->location);
			$temp['type'] = (string) $item->type;
			$temp['image'] = (string) $item->image;
			$temp['created'] = (int) strtotime((string) $item->created);

			// add
			$return['tracklist'][] = $temp;
		}

		// return
		return $return;
	}


	/**
	 * Retrieve reviews of an artist, album or source
	 *
	 * @return	array
	 * @param	string $entity				Which entity should be retrieved? Possible values are: artists, albums, sources.
	 * @param	string[optional] $mbid		ID of the artist or album from MusicBrainz. Only used when entity is artist and album.
	 * @param	string[optional] $name		The name of the source.
	 * @param	int[optional] $max			Maximum number of weblinks to display.
	 * @param	int[optional] $page			What page number?
	 * @param	array[optional] $lang		The language of the reviews shown, multiple languages should be comma separated.
	 */
	public function getReviews($entity, $mbid = null, $name = null, $max = 20, $page = 1, $lang = null)
	{
		// possible values
		$possibleEntities = array('artists', 'albums', 'sources');

		// redefine
		$entity = (string) $entity;
		$mbid = ($mbid !== null) ? (string) $mbid : null;
		$name = ($name !== null) ? (string) $name : null;
		$max = (int) $max;
		$page = (int) $page;
		$lang = (array) $lang;

		// validate
		if(!in_array($entity, $possibleEntities)) throw new CloudspeakersException('Invalid entity.');
		if($entity == 'sources' && $name == '') throw new CloudspeakersException('Invalid name.');

		// build url
		$url = 'reviews/'. $entity;
		if($mbid !== null) $url .= '/'. $mbid;
		if($name !== null) $url .= '/'. urlencode($name);
		$url .= '.xml';

		// build parameters
		$parameters['max'] = $max;
		$parameters['page'] = $page;
		if(!empty($lang)) $parameters['lang'] = implode(',', $lang);

		// make the call
		$response = $this->doCall($url, $parameters);

		// init var
		$return = array();

		// loop reviews
		foreach($response->reviews->review as $review)
		{
			// init var
			$temp = array();

			// set properties
			$temp['lang'] = (string) $review->lang;
			$temp['url'] = (string) $review->url;
			$temp['rating'] = (float) $review->csrating;
			$temp['publish_date'] = (int) strtotime((string) $review->publishdate);
			$temp['artist']['gid'] = ((string) $review->mb_artist_gid != '') ? (string) $review->mb_artist_gid : null;
			$temp['artist']['name'] = ((string) $review->artist_name != '') ? utf8_decode((string) $review->artist_name) : null;
			$temp['album']['gid'] = ((string) $review->mb_album_gid != '') ? (string) $review->mb_album_gid : null;
			$temp['album']['name'] = ((string) $review->album_name != '') ? utf8_decode((string) $review->album_name) : null;
			$temp['track']['id'] = ((string) $review->mb_track_id != '') ? (string) $review->mb_track_id : null;
			$temp['track']['gid'] = ((string) $review->mb_track_gid != '') ? (string) $review->mb_track_gid : null;
			$temp['track']['name'] = ((string) $review->track_name != '') ? utf8_decode((string) $review->track_name) : null;
			$temp['abstract'] = utf8_decode((string) $review->abstract);
			$temp['reviewer']['name'] = utf8_decode((string) $review->reviewer->name);
			$temp['reviewer']['username'] = utf8_decode((string) $review->reviewer->username);
			$temp['source']['name'] = utf8_decode((string) $review->source->name);
			$temp['source']['url'] = (string) $review->source->url;
			$temp['coverart_url'] = (string) $review->coverarturl;

			// add
			$return[] = $temp;
		}

		// return
		return $return;
	}


	/**
	 * Retrieve social and official URLs of an artist. This will return f.i. MySpace, LastFM, official homepage, Cloudspeakers URLs and more including icons of the sources used.
	 *
	 * @return	array
	 * @param	string $mbid		ID of the artist from MusicBrainz.
	 * @param	int[optional] $max	Maximum number of weblinks to display.
	 */
	public function getWeblinks($mbid, $max = 50)
	{
		// redefine
		$mbid = (string) $mbid;
		$max = (int) $max;

		// build url
		$url = 'weblinks/'. $mbid .'.xml';

		// build parameters
		$parameters['max'] = $max;

		// make the call
		$response = $this->doCall($url, $parameters);

		// init var
		$return = array();

		// set artist
		$return['artist'] = utf8_decode((string) $response->artist);

		// loop links
		foreach($response->urls->url as $url)
		{
			// init var
			$temp = array();

			// set properties
			$temp['url'] = (string) $url;
			$temp['source'] = (string) $url['source'];
			$temp['image'] = (string) $url['img'];
			$temp['position'] = (int) $url['pos'];

			// add
			$return['urls'][] = $temp;
		}

		// return
		return $return;
	}
}


/**
 * Cloudspeakers Exception class
 *
 * @author	Tijs Verkoyen <php-cloudspeakers@verkoyen.eu>
 */
class CloudspeakersException extends Exception
{
}

?>