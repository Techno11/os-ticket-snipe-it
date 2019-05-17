<?php
require_once (INCLUDE_DIR . 'class.signal.php');
require_once ('config.php');

/**
 * TODO At some point write a plugin description here
 */
class SnipeITIntegrator extends Plugin {
	const DEBUG = true;

	const DEBUG_SNIPE_API_CALLS = true;

    const DEBUG_PRINT_JSON_RESPONSE = true;

    /**
     * The Sign that Triggers our software to look for an asset ID
     */
	// const TRIGGER_KEY = '[';
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
	 * Define some class constants for the source of an entry
	 *
	 * @var integer
	 */
	const Staff = 0;
	const User = 1;
	const System = 2;
	
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
		$text = $entry->getBody ()->getClean ();
		$config = $this->getConfig ();

		// Match every instance of [asset in the thread text
		if ($assets = $this->getAssetsFromBody ( $text, '[' )) {
            if (self::DEBUG) {
                error_log ( "[DEBUG][checkThreadTextForAssets] Number of Assets found: " . array_count_values($assets));
            }
		    $snipe_asset_id = array();
		    // We are gonna contact Snipe-IT's API and their real IDs
			foreach ( $assets as $idx => $asset_id ) {
			    array_push($snipe_asset_id, $this -> getAssetLinkFromAsset($asset_id));
			}
            if (self::DEBUG) {
                error_log ( "[DEBUG][checkThreadTextForAssets] Finished Querying Snipe-IT API");
            }
			// We have the IDs, now we need to inject the links into the message
            $body_with_links = $this->injectLinks($text, $snipe_asset_id);
            if (self::DEBUG) {
                error_log ( "[DEBUG][checkThreadTextForAssets] Injected Links");
            }
            //Set Body
            $entry->setBody($body_with_links);

            if (self::DEBUG) {
                error_log ( "[DEBUG][checkThreadTextForAssets] Set Body. All done!");
            }

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
	private function getAssetsFromBody($text, $prefix = '@') {
		$matches = $mentions = array ();
		if (preg_match_all ( "/(^|\s)?\\$prefix([\.\w-]+)/i", $text, $matches ) !== FALSE) {
			if (count ( $matches [2] )) {
				$mentions = array_map ( function ($asset) {
					// restricts length of $asset's, prevent overflow
					return substr ( $asset, 0, self::MAX_LENGTH_ASSET );
				}, array_unique ( $matches [2] ) );
			}
		}
		if (self::DEBUG) {
			error_log ( "Matched $prefix " . count ( $mentions ) . ' matches.' );
			error_log ( print_r ( $mentions, true ) );
		}
		return isset ( $mentions [0] ) ? $mentions : null; // fastest validator ever.
	}

    /**
     * Get the Snipe-IT Internal Asset #, so we can link to the item's page properly
     *
     * @param $body string Body Text that needs  links
     * @param $snipe_ids string Snipe-IT Internal IDs for links
     * @return string Body text with links
     */
    private function injectLinks($body, $snipe_ids) {
        $search_finished = false;
        $i = 0;
        $snipe_link = $this->getConfig ()->get ( 'url' );
        while ($search_finished == false) {
            $pos = strpos($body, ']');
            if ($pos !== false) {
                $body = substr_replace($body, "(" . $snipe_link . "hardware/" . $snipe_ids[$i] . ")", $pos, 0);
                $i++;
                $body = $this->str_replace_first("[", "", $body);
                $body = $this->str_replace_first("]", "", $body);
            } else {
                $search_finished = true;
            }
        }
        return $body;
    }

    function str_replace_first($from, $to, $content)
    {
        $from = '/'.preg_quote($from, '/').'/';

        return preg_replace($from, $to, $content, 1);
    }

    /**
     * Get the Snipe-IT Internal Asset #, so we can link to the item's page properly
     *
     * @param $asset_id Snipe-IT Asset ID
     * @return string Snipe-IT's Internal Asset #
     */
	private function getAssetLinkFromAsset($asset_id) {
        if (self::DEBUG_SNIPE_API_CALLS) {
            error_log ( "[DEBUG_SNIPE_API_CALLS][getAssetLinkFromAsset] Starting Call for '" . $asset_id . "'");
        }
	    //Temporary Testing Variables
	    $api_key = $this->getConfig ()->get ( 'apikey' );
	    $snipe_link = $this->getConfig ()->get ( 'url' );
        // Create a stream
        $options = array(
            'http'=>array(
                'method'=>"GET",
                'header'=>
                    "accept: application/json\r\n" .
                    "authorization: Bearer " . $api_key . "\r\n" .
                    "content-type: application/json\r\n"
            )
        );
        if (self::DEBUG_SNIPE_API_CALLS) {
            error_log ( "[DEBUG_SNIPE_API_CALLS][getAssetLinkFromAsset] Response From API for '" . $asset_id . "'");
        }

        $context = stream_context_create($options);

        // Open the file using the HTTP headers set above
        $snipe_response = file_get_contents($snipe_link . 'api/v1/hardware/bytag/' . $asset_id, false, $context);

        if (self::DEBUG_PRINT_JSON_RESPONSE) {
            error_log ( "[DEBUG_PRINT_JSON_RESPONSE][getAssetLinkFromAsset] Queried With address '" . $snipe_link . 'api/v1/hardware/bytag/' . $asset_id);
            error_log ( "[DEBUG_PRINT_JSON_RESPONSE][getAssetLinkFromAsset] JSON Response '" . $snipe_response . "'");
        }

        //Parse Response
        $snipe_json = json_decode($snipe_response, true);

        if (self::DEBUG_PRINT_JSON_RESPONSE) {
            error_log ( "[DEBUG_PRINT_JSON_RESPONSE][getAssetLinkFromAsset] JSON Parsed '" . $snipe_json . "'");
        }

        if (self::DEBUG_SNIPE_API_CALLS) {
            error_log ( "[DEBUG_SNIPE_API_CALLS][getAssetLinkFromAsset] Parsed JSON for '" . $asset_id . "' Response is '" . $snipe_json->id . "'");
        }

        return $snipe_json["id"];
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


