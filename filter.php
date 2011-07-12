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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');


/**
 *
 */
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
            // error or not filtered
            mtrace('link empty');
            unset($newtext);
            return $text;
        }
        
        return $newtext;
    }

}

function filter_soundcloud_callback($link) {
    global $CFG;
    $count = 0;
    
    if (filter_soundcloud_ignore($link[0])) {
        return $link[0];
    }
    // required parts to build track url for player
    $username   = trim($link[1]);
    $permalink  = trim($link[2]);
    $secretlink = trim($link[3]);
    $info       = trim($link[4]);
    
    // is it a private track? 
    $trackurl = 'http://soundcloud.com/'.$username.'/'.$permalink;
    if ($secretlink) {
        $trackurl = 'http://soundcloud.com/'.$username.'/'.$permalink.'/'.$secretlink;
    }
    
    $count++;
    $id = 'filter_soundcloud_'.time().'_'.$count; //we need something unique because it might be stored in text cache
    
    $playerurl = 'http://player.soundcloud.com/player.swf';
    $parameters = array('url'=>$trackurl,
                        'object_id'=>$id,
                        'single_active'=>'false',
                        'enable_api'=>'true',
                        'download'=>'false',
                        'sharing'=>'false',
                        'show_comments'=>'false');
    
    // set play button colour if configured
    if (!empty($CFG->filter_soundcloud_colour)) {
       $parameters['color'] = $CFG->filter_soundcloud_colour;
    }
    // set theme colour if configured
    if (!empty($CFG->filter_soundcloud_theme_colour)) {
       $parameters['theme_color'] = $CFG->filter_soundcloud_theme_colour;
    }
 
    // track url, options for player included in url as params
    $src = new moodle_url($playerurl, $parameters); 
    
    if (empty($info) or strpos($info, 'http') === 0) {
        $info = get_string('sitesoundcloud', 'filter_soundcloud');
    }
    $printlink = html_writer::link($trackurl, $info, array('class'=>'mediafallbacklink'));
        
$output = <<<OET
<object height="81" width="100%" id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000">
  <param name="movie" value="$src"></param>
  <param name="allowscriptaccess" value="always"></param> 
  <param name="wmode" value="opaque"></param>   
  <embed allowscriptaccess="always" 
         height="81"
         width="100%" 
         src="$src" 
         type="application/x-shockwave-flash" 
         wmode="opaque" 
         name="soundcloudplayer">
  </embed> 
  $printlink
</object> 
OET;

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