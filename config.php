<?php
require_once INCLUDE_DIR . 'class.plugin.php';

class MentionerPluginConfig extends PluginConfig
{

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate()
    {
        if (! method_exists('Plugin', 'translate')) {
            return array(
                function ($x) {
                    return $x;
                },
                function ($x, $y, $n) {
                    return $n != 1 ? $y : $x;
                }
            );
        }
        return Plugin::translate('mentioner');
    }

    /**
     * Build an Admin settings page.
     * TODO: Update for my app
     * {@inheritdoc}
     *
     * @see PluginConfig::getOptions()
     */
    function getOptions()
    {
        list ($__, $_N) = self::translate();
        return array(
            'asl' => new SectionBreakField([ //Auto Snipe-IT Link
                'label' => $__('Enable Auto Snipe-IT Link'),
                'hint' => $__('By default, enable all [square-brackets] to link to Asset IDs'),
                'default' => true
            ]),
            'apikey' => new SectionBreakField([ //API Key
                'label' => $__('Snipe-IT Api Key'),
                'hint' => $__('Your Secret API Key for Snipe-IT'),
                'default' => true
            ]),
            'url' => new SectionBreakField([ //Snipe-IT Url
                'label' => $__('Snipe-IT URL'),
                'hint' => $__('The URL Of you Snipe-IT Server (http://url.com/)'),
                'default' => true
            ])/*,
            'aiec' => new BooleanField([ //Asset ID Encasement Character
                'label' => $__("Encasement Character(s) for Asset ID"),
                'hint' => $__('Character the software looks for to link an asset ID to Snipe-IT'),
                'default' => true
            ]),
            'snec' => new BooleanField([ //Serial Number Encasement Character
                'label' => $__("Encasement Character(s) for Asset ID"),
                'hint' => $__('Character the software looks for to link an asset ID to Snipe-IT'),
                'default' => true
            ]),*/
        );
    }
}
