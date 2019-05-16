<?php
require_once (INCLUDE_DIR . 'class.signal.php');
require_once ('config.php');

/**
 * The goal of this Plugin is to read messages, find things that look like
 *
 * Checks @email prefix for admin-defined domain.com it will find that user/agent via address lookup, then add them as a a collaborator.
 */
class SnipeITIntegrator extends Plugin {
	const DEBUG = FALSE;

    /**
     * The Sign that Triggers our software to look for an asset ID
     */
	// const TRIGGER_KEY = '[';
	/**
	 * Which config to use (in config.php)
	 *
	 * @var string
	 */
	public $config_class = 'MentionerPluginConfig';
	
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
				error_log ( "ThreadEntry detected, checking for mentions and notifying staff." );
			}
			$this->checkThreadTextForMentions ( $entry );
			$this->notifyCollaborators ( $entry );
		} );
	}
	
	/**
	 * Hunt through the text of a ThreadEntry's body text for mentions of Staff or Users
	 *
	 * @param ThreadEntry $entry        	
	 */
	private function checkThreadTextForMentions(ThreadEntry $entry) {
		// Get the contents of the ThreadEntryBody to check the text
		$text = $entry->getBody ()->getClean ();
		$config = $this->getConfig ();

		// Match every instance of [asset in the thread text
		if ($mentions = $this->getAssetsFromBody ( $text, '[' )) {
			// Each unique name will get added as a Collaborator to the ticket thread.
			foreach ( $mentions as $idx => $asset_id ) {
				// $this->addCollaborator ( $entry, $name );
                //Here is where we need to get the Assets from snipe-it's API and then get a link
                //After that, we can search through the body of the messsage for the "[" again and
                //Set a link to the entirety of the asset id
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
		if (preg_match_all ( "/(^|\s)?$prefix([\.\w]+)/i", $text, $matches ) !== FALSE) {
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
     * @param $asset_id Snipe-IT Asset ID
     */
	private function getAssetLinkFromAsset($asset_id) {
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

        $context = stream_context_create($options);

        // Open the file using the HTTP headers set above
        $snipe_response = file_get_contents($snipe_link . 'api/v1/hardware/bytag/' . $asset_id, false, $context);

        //Parse Response

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
		return array ();
	}
}


