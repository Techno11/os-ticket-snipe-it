<?php
require_once (INCLUDE_DIR . 'class.signal.php');
require_once ('config.php');

/**
 * TODO At some point write a plugin description here
 */
class SnipeITIntegrator extends Plugin {
    
    /************* DEBUG VARIABLES ********************/
	const DEBUG = false;

	const DEBUG_SNIPE_API_CALLS = false;

    const DEBUG_PRINT_JSON_RESPONSE = false;

    /************* END DEBUG VARIABLES ****************/

	/**
	 * Which config to use (in config.php)
	 *
	 * @var string
	 */
	public $config_class = 'SnipeITPluginConfig';

	/**
	 * To prevent buffer overflows, let's set the max length of a name we'll ever use to this:
	 *
	 * @var integer
	 */
	const MAX_LENGTH_ASSET = 128;


	/**
	 * Run on every instantiation of osTicket..
	 * needs to be concise
	 *
	 * {@inheritdoc}
	 *
	 * @see Plugin::bootstrap()
	 */
	function bootstrap() {
		Signal::connect ( 'threadentry.created', function (ThreadEntry $entry) {
			if (self::DEBUG) {
				error_log ( "ThreadEntry detected, checking for assets and adding links." );
			}
			$this->checkThreadTextForAssets ( $entry );
		} );
	}

	/**
	 * Hunt through the text of a ThreadEntry's body text for mentions of Staff or Users
	 *
	 * @param ThreadEntry $entry
	 */
	private function checkThreadTextForAssets(ThreadEntry $entry) {
		// Get the contents of the ThreadEntryBody to check the text
		$body_text = $entry->getBody ()->getClean ();
		$config = $this->getConfig ();

		if($config->get ( 'asl' )) {
            // Match every instance of [asset in the thread text
            if (strpos($body_text, '[')!== false && $assets = $this->getAssetsFromBody ( $body_text, '[' )) {

                if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Number of Assets found: " . array_count_values($assets));

                $snipe_internal_ids = array();

                // We are gonna contact Snipe-IT's API and their internal IDs
                foreach ( $assets as $idx => $asset_id ) {
                    array_push($snipe_internal_ids, $this -> getInternalIds($asset_id, false));
                }

                if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Finished Querying Snipe-IT API For Asset-IDs");

                // We have the IDs, now we need to inject the links into the message
                $body_text = $this->injectLinks($body_text, $snipe_internal_ids, false);

                if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Injected Links for Asset IDs");

            }else {if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] No Asset IDs Found!");}

            // Match every instance of {serial in the thread text
            if (strpos($body_text, '{')!== false && $serials = $this->getAssetsFromBody ( $body_text, '{' )) {

                if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Number of Serial Numbers found: " . array_count_values($serials));

                $snipe_internal_ids = array();

                // We are gonna contact Snipe-IT's API and their internal IDs
                foreach ( $serials as $idx => $asset_id ) {
                    array_push($snipe_internal_ids, $this -> getInternalIds($asset_id, true));
                }

                if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Finished Querying Snipe-IT API For Serial Numbers");

                // We have the IDs, now we need to inject the links into the message
                $body_text = $this->injectLinks($body_text, $snipe_internal_ids, true);

                if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Injected Links for Serial Numbers");

            } else {if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] No Serial Numbers Found!");}
            //Set Body
            $entry->setBody($body_text);

            if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] Set Body to'" . $body_text .  "''. All done!");
        } else {
            if (self::DEBUG) error_log ( "[DEBUG][checkThreadTextForAssets] SnipeIT-osTicket Integration Plugin disabled");
        }

	}

	/**
	 * Looks through $text & finds a list of words that are prefixed with $prefix char.
	 *
	 * Ensures Asset Names are not longer than MAX_LENGTH_Asset
	 *
	 * @param string $text
	 * @param string $prefix
	 * @return array either of names or false if no matches.
	 */
	private function getAssetsFromBody($text, $prefix) {
		$matches = $mentions = array ();
		if (preg_match_all ( "/(^|\s)?\\$prefix([\.\w-]+)/i", $text, $matches ) !== FALSE) {
			if (count ( $matches [2] )) {
				$mentions = array_map ( function ($asset) {
					// restricts length of $asset's, prevent overflow
					return substr ( $asset, 0, self::MAX_LENGTH_ASSET );
				}, array_unique ( $matches [2] ) );
			}
		}

		if (self::DEBUG) error_log ( "[DEBUG][getAssetsFromBody]Matched $prefix " . count ( $mentions ) . ' matches.' );

		return isset ( $mentions [0] ) ? $mentions : null; // fastest validator ever.
	}

    /**
     * Get the Snipe-IT Internal Asset #, so we can link to the item's page properly
     *
     * @param $body string Body Text that needs  links
     * @param $snipe_ids array Snipe-IT Internal IDs for links
     * @param $serial boolean Whether Text to-be-replaced is a serial number or not
     * @return string Body text with links
     */
    private function injectLinks($body, $snipe_ids, $serial) {
        $search_finished = false;
        $i = 0;
        $snipe_link = $this->getConfig ()->get ( 'url' );


        while ($search_finished == false) {
            if (sizeof($snipe_ids) > $i) {
                if(strlen($snipe_ids[$i]) < 1) { //Lookup failed, we just need to remove the braces and leave the tag with no link
                    if(self::DEBUG) error_log("[DEBUG][injectLinks] Lookup #" . $i . " failed. Skipping.  String Legnth: " . strlen($snipe_ids[$i]));

                    $first_char = ($serial) ? "{" : "[";
                    $last_char  = ($serial) ? "}" : "]";
                    $body = $this->str_replace_first($first_char, "", $body);
                    $body = $this->str_replace_first($last_char, "", $body);
                    $i++;
                } else {
                    if(self::DEBUG) error_log("[DEBUG][injectLinks] Injected Link #" . $i);

                    $first_char = ($serial) ? '{' : '[';
                    $last_char  = ($serial) ? '}' : ']';
                    $body = $this->str_replace_first($first_char, "<a href=\"" . $snipe_link . "hardware/" . $snipe_ids[$i] . "\">", $body);
                    $body = $this->str_replace_first($last_char, "</a>", $body);
                    $i++;
                }
            } else {
                $search_finished = true;
            }
        }
        return $body;
    }

    /**
     * Replaces First instance of $from to $to
     * @param $from string Search Value
     * @param $to string Replacement Value
     * @param $content string Value to be Searched
     * @return string|string[]|null Replaced Text
     */
    function str_replace_first($from, $to, $content)
    {
        $from = '/'.preg_quote($from, '/').'/';

        return preg_replace($from, $to, $content, 1);
    }

    /**
     * Get the Snipe-IT Internal Asset #, so we can link to the item's page properly
     *
     * @param $asset_id Snipe-IT Asset ID
     * @param $serial boolean Whether $asset_id is a serial number or not
     * @return string Snipe-IT's Internal Asset #
     */
	private function getInternalIds($asset_id, $serial) {
        if (self::DEBUG_SNIPE_API_CALLS) {
            error_log ( "[DEBUG_SNIPE_API_CALLS][getAssetLinkFromAsset] Starting Call for '" . $asset_id . "'");
        }
	    //Temporary Testing Variables
	    $api_key = strip_tags( $this->getConfig ()->get ( 'apikey' ) );
	    $snipe_link = strip_tags($this->getConfig ()->get ( 'url' ));

	    $full_url = $snipe_link . 'api/v1/hardware/' . (($serial) ? 'byserial' : 'bytag') . '/' . $asset_id;

	    $curl_h = curl_init($full_url);

        if (self::DEBUG_SNIPE_API_CALLS) {
            error_log ( "[DEBUG_SNIPE_API_CALLS][getAssetLinkFromAsset] Sending Request to '" . $full_url . "'");
        }

        curl_setopt($curl_h, CURLOPT_HTTPHEADER,
            array(
                "accept: application/json",
                "authorization: Bearer " . $api_key,
                "content-type: application/json",
            )
        );

        //do not output, but store to variable
        curl_setopt($curl_h, CURLOPT_RETURNTRANSFER, true);
        // Get Headers
        curl_setopt($curl_h, CURLOPT_VERBOSE, true);
        curl_setopt($curl_h, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl_h, CURLOPT_CONNECTTIMEOUT, 5); // TODO Remove for production
        curl_setopt($curl_h, CURLOPT_HEADER, true);

        $response = curl_exec($curl_h);

        // Then, after your curl_exec call:
        $header_size = curl_getinfo($curl_h, CURLINFO_HEADER_SIZE);
        $header_out = curl_getinfo($curl_h, CURLINFO_HEADER_OUT);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($curl_h);

        if (self::DEBUG_PRINT_JSON_RESPONSE) {
            error_log ( "[DEBUG_PRINT_JSON_RESPONSE][getAssetLinkFromAsset] HTTP Sent '"     . $header_out . "'");
            error_log ( "[DEBUG_PRINT_JSON_RESPONSE][getAssetLinkFromAsset] JSON Response '" . $body   . "'");
            //TODO: Handle Error from non-200 responses
        }

        //Parse Response
        $snipe_json = json_decode($body, true);

        $internal_id = ($serial) ? $snipe_json["rows"][0]["id"] : $snipe_json["id"];

        if (self::DEBUG_SNIPE_API_CALLS) {
            error_log ( "[DEBUG_SNIPE_API_CALLS][getAssetLinkFromAsset] Parsed JSON for '" . $asset_id . "' Response is '" . $internal_id . "'");
        }

        if($serial && ((int) preg_replace('/\D/', '', $snipe_json["total"])) < 1) { //If We get less than 1 value, it fails
            return '';
        } else {
            return $internal_id;
        }

    }

	/**
	 * Required stub.
	 *
	 * {@inheritdoc}
	 *
	 * @see Plugin::uninstall()
	 */
	function uninstall() {
		$errors = array ();
		parent::uninstall ( $errors );
	}

	/**
	 * Plugins seem to want this.
	 */
	public function getForm() {
        return array();
    }
}


