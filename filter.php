<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Soundcloud filter
 * 
 * This filter will add replace links to Soundcloud tracks
 * with Soundcloud player
 *
 * @package    filter
 * @subpackage Soundcloud
 * @copyright  2011 Troy Williams
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/filelib.php');

class filter_soundcloud extends moodle_text_filter {
    function filter($text, array $options = array()) {
        global $CFG;

        if (!is_string($text) or empty($text)) {
            // non string data can not be filtered anyway
            return $text;
        }
        if (stripos($text, '</a>') === false) {
            // performance shortcut - all regexes bellow end with the </a> tag,
            // if not present nothing can match
            return $text;
        }

        $newtext = $text; // we need to return the original value if regex fails!
        
        $search = '/<a\s[^>]*href="http:\/\/soundcloud\.com\/([0-9A-Za-z]+)\/([0-9A-Za-z-]+)(?:\/([0-9A-Za-z-]+))?[^>]*>([^>]*)<\/a>/is';
        $newtext = preg_replace_callback($search, 'filter_soundcloud_callback', $newtext);
        
        if (empty($newtext) or $newtext === $text) {
            unset($newtext);
            return $text;
        }
        
        return $newtext;
    }

}

/**
 * Change link to soundcloud player
 *
 * @global stdClass $CFG
 * @param array $link
 * @return string $output
 */
function filter_soundcloud_callback($link) {
    global $CFG;

    // class may be loaded through repository, apparently require_once only looks at paths
    if (! class_exists('Services_Soundcloud', false)) {
        require_once($CFG->dirroot . '/filter/soundcloud/soundcloudapi.php');
    }

    $output = '';
    
    $config     = get_config('soundcloud');
    $username   = $link[1];
    $permalink  = $link[2];
    $secretlink = isset($link[3]) ? $link[3] : false;
    $info       = isset($link[4]) ? $link[4] : false;

    // create a client object with your app credentials
    $client = new Services_Soundcloud($config->clientid, $config->clientsecret);

    $client->setCurlOptions(array(CURLOPT_FOLLOWLOCATION => 1));

    $trackurl = 'http://soundcloud.com/' . $username . '/' . $permalink;
    if ($secretlink) {
        $trackurl = 'http://soundcloud.com/' . $username . '/' .$permalink . '/' .$secretlink;
    }
    // get a tracks oembed data
    $embedinfo = json_decode($client->get('oembed', array('url' => $trackurl)));
    
    // render the html for the player widget
    $output .= html_writer::start_div('soundcloud-widget');
    $output .= $embedinfo->html;
    $output .= html_writer::end_div();

    if ($info) {
       $output .= html_writer::link($trackurl, $info, array('class' => 'mediafallbacklink'));
    }

    return $output;
}

/**
 * Should the current tag be ignored in this filter?
 * @param string $tag
 * @return bool
 */
function filter_soundcloud_ignore($tag) {
    if (preg_match('/class="[^"]*nomediaplugin/i', $tag)) {
        return true;
    } else {
        false;
    }
}