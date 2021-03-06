<?php

/**
 * @package   	OneAll Social Sharing
 * @copyright 	Copyright 2016 http://www.oneall.com - All rights reserved.
 * @license   	GNU/GPL 2 or later
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,USA.
 *
 * The "GNU General Public License" (GPL) is available at
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 */

namespace oneall\socialsharing\acp;

class socialsharing_acp_module
{
	/** @var string */
	public $u_action;


	/**
	 * Main Function
	 */
	public function main ($id, $mode)
	{
		global $request;

		if ($request->variable ('task', '', true) == 'verify_subdomain')
		{
			$api_subdomain = trim (strtolower ($request->variable ('api_subdomain', '', true)));
			return $this->admin_ajax_verify_subdomain ($api_subdomain);
		}
		// Default.
		return $this->admin_main ();
	}

	
	/**
	 * Admin Main Page
	 */
	public function admin_main ()
	{
		global $db, $user, $auth, $template, $config, $phpbb_root_path, $phpbb_admin_path, $phpEx, $table_prefix, $request;

		// Add the language file.
		$user->add_lang_ext ('oneall/socialsharing', 'backend');

		// Set up the page
		$this->tpl_name = 'socialsharing';
		$this->page_title = $user->lang ['OA_SOCIAL_SHARING_ACP'];

		// Config to temp vars.
		// Enable Social Sharing?
		$oass_disable = (empty ($config ['oa_social_sharing_disable']) ? 0 : 1);

		// Pre-populate the subdomain from Social Login, if present and enabled:
		if (empty ($config ['oa_social_login_disable']) && 
			!empty ($config ['oa_social_login_api_subdomain']))
		{
			$oass_api_subdomain = trim ($config ['oa_social_login_api_subdomain']);
		}
		else if (isset ($config ['oa_social_sharing_api_subdomain']))
		{
			$oass_api_subdomain = $config ['oa_social_sharing_api_subdomain'];
		}
		else 
		{
			$oass_api_subdomain = '';
		}
		
		$oass_library_available = !empty ($oass_api_subdomain); 

		// Social Networks.
		$default_providers = array ('facebook');
		$oass_providers = (empty ($config ['oa_social_sharing_providers']) ?
				 $default_providers : explode (",", $config ['oa_social_sharing_providers']));

		// Caption for icons, for all locations.
		$oass_caption = (isset ($config ['oa_social_sharing_caption']) ?
				$config ['oa_social_sharing_caption'] : $user->lang ['OA_SOCIAL_SHARING_CAPTION_DEFAULT']);

		// page_body_after
		$oass_pagebodyafter_disable = (
				empty ($config ['oa_social_sharing_pagebodyafter_disable']) ?
				0 : 1);
		// header_content_before
		$oass_headercontentbefore_disable = (
				empty ($config ['oa_social_sharing_headercontentbefore_disable']) ?
				0 : 1);
		// viewtopic_body_contact_fields
		$oass_viewtopicbodycontactfields_disable = (
				empty ($config ['oa_social_sharing_viewtopicbodycontactfields_disable']) ?
				0 : 1);
		// viewtopic_pagination_top_after
		$oass_viewtopicpaginationtopafter_disable = (
				empty ($config ['oa_social_sharing_viewtopicpaginationtopafter_disable']) ?
				0 : 1);
		// viewtopic_dropdown_bottom_custom
		$oass_viewtopicdropdownbottomcustom_disable = (
				empty ($config ['oa_social_sharing_viewtopicdropdownbottomcustom_disable']) ?
				0 : 1);
		// style of buttons
		$default_btns = 'btns_s';
		$oass_btns = (
				empty ($config ['oa_social_sharing_btns']) ? 
				$default_btns : $config ['oa_social_sharing_btns']);
		// social insight
		$oass_insight_disable = (
				isset ($config ['oa_social_sharing_insight_disable']) && $config ['oa_social_sharing_insight_disable'] == 0) ?
				0 : 1;

		// Triggers a form message.
		$oass_settings_saved = false;

		// Security Check.
		add_form_key ('oa_social_sharing');

		// Form submitted.
		if (!empty ($request->variable ('submit', '', true)))
		{

			// Security Check
			if (!check_form_key ('oa_social_sharing'))
			{
				trigger_error ($user->lang ['FORM_INVALID'] . adm_back_link ($this->u_action), E_USER_WARNING);
			}

			$oass_disable = $request->variable ('oa_social_sharing_disable', $oass_disable);
			$oass_library_available = true;
			
			// Verify the API subdomain.
			$warning_msg = '';
			$oass_api_subdomain = trim (strtolower ($request->variable ('oa_social_sharing_api_subdomain', '', true)));
			if (empty ($oass_api_subdomain))
			{
				$warning_msg = $user->lang ['OA_SOCIAL_SHARING_API_CREDENTIALS_FILL_OUT'];
				$oass_library_available = false;
			}
			// Check for full subdomain.
			if (empty ($warning_msg) && preg_match ("/([a-z0-9\-]+)\.api\.oneall\.com/i", $oass_api_subdomain, $matches))
			{
				$oass_api_subdomain = $matches [1];
			}
			// Try to load library.js
			$library_url = (self::is_https_on () ? 'https' : 'http') .'://'. $oass_api_subdomain .'.api.oneall.com/socialize/library.js';
			if (empty ($warning_msg) && !self::is_library_url_valid ($library_url))
			{
				$warning_msg = $user->lang ['OA_SOCIAL_SHARING_API_CREDENTIALS_SUBDOMAIN_WRONG'];
				$oass_library_available = false;
			}
			// Only report problems if the extension is enabled:
			if (!$oass_disable && !empty ($warning_msg))
			{
				$template->assign_vars (array (
					'U_ACTION' => $this->u_action,
					'CURRENT_SID' => $user->data ['session_id'],
					'S_WARNING' => true,
					'WARNING_MSG' => $warning_msg,
					'OA_SOCIAL_SHARING_API_ERROR_CLASS' => 'error_message',
					'OA_SOCIAL_SHARING_LIBRARY_AVAILABLE' => $oass_library_available,
					'OA_SOCIAL_SHARING_SETTINGS_SAVED' => false,
					'OA_SOCIAL_SHARING_DISABLE' => $oass_disable,
					'OA_SOCIAL_SHARING_API_SUBDOMAIN' => $oass_api_subdomain,
				));
				return false;
			}

			//get providers order
			$providers_order = array();
			foreach ($request->variable_names() as $variable_name) {
				if (strpos($variable_name, 'oa_social_sharing_provider_') !== false ){
					$providers_order[] = str_replace('oa_social_sharing_provider_', '', $variable_name);
				}
			}

			// Social Networks.
			$req_providers = array();
			foreach ($providers_order as $provider_name)
			{
				if ($request->variable ('oa_social_sharing_provider_' . $provider_name, 0) == 1)
				{
					$req_providers[] = $provider_name;
				}
			}
			$oass_providers = empty ($req_providers) ? $oass_providers : $req_providers;
			
			$oass_caption = ($request->variable ('oa_social_sharing_caption', '_NOT_REQ_', true) == '_NOT_REQ_') ? 
					$oass_caption : $request->variable ('oa_social_sharing_caption', '', true);
			$oass_pagebodyafter_disable = $request->variable ('oa_social_sharing_pagebodyafter_disable', 
					$oass_pagebodyafter_disable);
			$oass_headercontentbefore_disable = $request->variable ('oa_social_sharing_headercontentbefore_disable', 
					$oass_headercontentbefore_disable);
			$oass_viewtopicbodycontactfields_disable = $request->variable ('oa_social_sharing_viewtopicbodycontactfields_disable', 
					$oass_viewtopicbodycontactfields_disable);
			$oass_viewtopicpaginationtopafter_disable = $request->variable ('oa_social_sharing_viewtopicpaginationtopafter_disable', 
					$oass_viewtopicpaginationtopafter_disable);
			$oass_viewtopicdropdownbottomcustom_disable = $request->variable ('oa_social_sharing_viewtopicdropdownbottomcustom_disable', 
					$oass_viewtopicdropdownbottomcustom_disable);
			$oass_btns = $request->variable ('oa_social_sharing_btns', 
					$oass_btns, true);
			$oass_insight_disable = $request->variable ('oa_social_sharing_insight_disable', 
					$oass_insight_disable);

			// Save configuration.
			$config->set ('oa_social_sharing_disable', $oass_disable);
			$config->set ('oa_social_sharing_api_subdomain', $oass_api_subdomain);
			$config->set ('oa_social_sharing_providers', implode (',', $oass_providers));
			$config->set ('oa_social_sharing_caption', $oass_caption);
			$config->set ('oa_social_sharing_pagebodyafter_disable', $oass_pagebodyafter_disable);
			$config->set ('oa_social_sharing_headercontentbefore_disable', $oass_headercontentbefore_disable);
			$config->set ('oa_social_sharing_viewtopicbodycontactfields_disable', $oass_viewtopicbodycontactfields_disable);
			$config->set ('oa_social_sharing_viewtopicpaginationtopafter_disable', $oass_viewtopicpaginationtopafter_disable);
			$config->set ('oa_social_sharing_viewtopicdropdownbottomcustom_disable', $oass_viewtopicdropdownbottomcustom_disable);
			$config->set ('oa_social_sharing_btns', $oass_btns);
			$config->set ('oa_social_sharing_insight_disable', $oass_insight_disable);
		} /* end submit */

		// Setup Style of buttons
		foreach (self::get_icons () as $grp => $icn)
		{
			$template->assign_block_vars ('icongroup', array(
				'LBL' => $grp,
			));
			foreach ($icn as $val => $lbl) 
			{
				$template->assign_block_vars('icongroup.icons', array(
					'VAL' => $val,
					'LBL' => $lbl,
					'SELECTED' => $val === $oass_btns,
				));
			}
		}

		
		// Setup Social Network vars
		$all_providers = self::ordered_providers ($oass_providers);
		foreach ($all_providers as $key => $prov)
		{
			$template->assign_block_vars ('provider', array (
					'KEY' => $key,
					'VAL' => $prov['val'],
					'LBL' => $prov['lbl'],
					'ENABLE' => in_array ($prov['val'], $oass_providers)
			));
		}

		// Setup vars
		$template->assign_vars (array (
				'U_ACTION' => $this->u_action,
				'CURRENT_SID' => $user->data ['session_id'],
				'OA_SOCIAL_SHARING_LIBRARY_AVAILABLE' => $oass_library_available,
				'OA_SOCIAL_SHARING_SETTINGS_SAVED' => true,
				'OA_SOCIAL_SHARING_DISABLE' => $oass_disable,
				'OA_SOCIAL_SHARING_API_SUBDOMAIN' => $oass_api_subdomain,
				'OA_SOCIAL_SHARING_CAPTION' => $oass_caption,
				'OA_SOCIAL_SHARING_PAGEBODYAFTER_DISABLE' => $oass_pagebodyafter_disable,
				'OA_SOCIAL_SHARING_HEADERCONTENTBEFORE_DISABLE' => $oass_headercontentbefore_disable,
				'OA_SOCIAL_SHARING_VIEWTOPICBODYCONTACTFIELDS_DISABLE' => $oass_viewtopicbodycontactfields_disable,
				'OA_SOCIAL_SHARING_VIEWTOPICPAGINATIONTOPAFTER_DISABLE' => $oass_viewtopicpaginationtopafter_disable,
				'OA_SOCIAL_SHARING_VIEWTOPICDROPDOWNBOTTOMCUSTOM_DISABLE' => $oass_viewtopicdropdownbottomcustom_disable,
				'OA_SOCIAL_SHARING_INSIGHT_DISABLE' => $oass_insight_disable,
				'OA_SOCIAL_SHARING_SELECTED_BTNS' => $oass_btns,
				'OA_SOCIAL_SHARING_SELECTED_PROVIDERS' => json_encode ($oass_providers),
				'OA_SOCIAL_SHARING_PROVIDERS' => json_encode (self::flatten_providers ()),
				'OA_SOCIAL_SHARING_PROVIDERS_DEFAULT' => json_encode (self::get_providers ()),
			));
		
		// Done
		return true;
	}

	
	/**
	 * Returns the list of available button styles.
	 */
	private static function get_icons ()
	{
		return array (
			'Beveled Squares' => array( 
				'btns_s' => 'Beveled Squares (Small, Colored)',
				'btns_m' => 'Beveled Squares (Medium, Colored)',
				'btns_l' => 'Beveled Squares (Large, Colored)',
				'count_h' => 'Beveled Squares (Small, Colored, Horizontal Counters)',
				'count_v' => 'Beveled Squares (Small, Colored, Vertical Counters)',
			),
			'Flat Rectangles' => array(
				'btns_hf' => 'Flat Rectangles (Colored)',
				'btns_hf_b' => 'Flat Rectangles (Black)',
				'btns_hf_g' => 'Flat Rectangles (Grey)',
			),
			'Flat Rectangles With Counters' => array(
				'btns_hf_count' => 'Flat Rectangles (Colored, Counters)',
				'btns_hf_b_count' => 'Flat Rectangles (Black, Counters)',
				'btns_hf_g_count' => 'Flat Rectangles (Grey, Counters)',
			),
			'Flat Rounded Rectangles' => array(
				'btns_hfrr' => 'Flat Rectangles (Colored, Rounded)',
				'btns_hfrr_b' => 'Flat Rectangles (Black, Rounded)',
				'btns_hfrr_g' => 'Flat Rectangles (Grey, Rounded)',
			),
			'Flat Rounded Rectangles With Counters' => array( 
				'btns_hfrr_count' => 'Flat Rectangles (Colored, Rounded, Counters)',
				'btns_hfrr_b_count' => 'Flat Rectangles (Black, Rounded, Counters)',
				'btns_hfrr_g_count' => 'Flat Rectangles (Grey, Counters, Counters)',
			),
			'Flat Blocks Without Margin' => array(
				'btns_lfnm' => 'Flat Blocks (Colored, No Margin)',
				'btns_lfnm_b' => 'Flat Blocks (Black, No Margin)',
				'btns_lfnm_g' => 'Flat Blocks (Grey, No Margin)',
			),
			'Flat Circles' => array(
				'btns_lfr' => 'Flat Circles (Colored)',
				'btns_lfr_b' => 'Flat Circles (Black)',
				'btns_lfr_g' => 'Flat Circles (Grey)',
			),
			'Flat Squares' => array(
				'btns_lf' => 'Flat Squares (Colored)',
				'btns_lf_b' => 'Flat Squares (Black)',
					'btns_lf_g' => 'Flat Squares (Grey)',
			),
			'Flat Squares With Counters' => array(
				'btns_lf_count' => 'Flat Squares (Colored, Counters)',
				'btns_lf_b_count' => 'Flat Squares (Black, Counters)',
				'btns_lf_g_count' => 'Flat Squares (Grey, Counters)',
			),
			'Flat Rounded Squares' => array(
				'btns_lfrr' => 'Flat Squares (Colored, Rounded)',
				'btns_lfrr_b' => 'Flat Squares (Black, Rounded)',
				'btns_lfrr_g' => 'Flat Squares (Grey, Rounded)',
			),
			'Flat Rounded Squares With Counters' => array(
				'btns_lfrr_count' => 'Flat Squares (Colored, Rounded, Counters)',
				'btns_lfrr_b_count' => 'Flat Squares (Black, Rounded, Counters)',
				'btns_lfrr_g_count' => 'Flat Squares (Grey, Rounded, Counters)',
			),
		);
	}

	public static function flatten_providers ()
	{
		global $config;

		//get ordered providers
		$default_providers = array ('facebook');
		$oass_providers = (empty ($config ['oa_social_sharing_providers']) ? $default_providers : explode (",", $config ['oa_social_sharing_providers']));
		$all_providers = self::ordered_providers ($oass_providers);

		$flat = array ();
		foreach ($all_providers as $prov)
		{
			$flat[$prov['val']] = $prov['lbl'];
		}
		return $flat;
	}

	public static function ordered_providers ($oass_providers)
	{
		$ordered = array ();

		$all_providers = self::get_providers ();

		// Setup Selected Social Network vars
		foreach ($oass_providers as $prov)
		{
			$prov_info = self::get_providers ()[$prov];
			$ordered [$prov] = array(
				'val' => $prov_info['val'],
				'lbl' => $prov_info['lbl']
			);
			unset($all_providers[$prov]);
		}
		// Setup Social Network vars
		foreach ($all_providers as $key => $prov)
		{
			$ordered [$key] = array(
				'val' => $prov['val'],
				'lbl' => $prov['lbl']
			);
		}
		return $ordered;
	}
	
	/**
	 * Returns the list of available social networks.
	 */
	public static function get_providers ()
	{
		return array (
			'counter'             => array ('val' => 'counter',             'lbl' => 'Total Shares Counter'),
			'facebook'            => array ('val' => 'facebook',            'lbl' => 'Facebook'),
			'twitter'             => array ('val' => 'twitter',             'lbl' => 'Twitter'),
			'linkedin'            => array ('val' => 'linkedin',            'lbl' => 'LinkedIn'),
			'google_bookmarks'    => array ('val' => 'google_bookmarks',    'lbl' => 'Google Bookmarks'),
			'google_plus'         => array ('val' => 'google_plus',         'lbl' => 'Google Plus'),
			'google_plus_one_but' => array ('val' => 'google_plus_one_but', 'lbl' => 'Google +1 Button'),
			'delicious'           => array ('val' => 'delicious',           'lbl' => 'Delicious'),
			'digg'                => array ('val' => 'digg',                'lbl' => 'Digg'),
			'stumbleupon'         => array ('val' => 'stumbleupon',         'lbl' => 'StumbleUpon'),
			'reddit'              => array ('val' => 'reddit',              'lbl' => 'Reddit'),
			'tumblr'              => array ('val' => 'tumblr',              'lbl' => 'Tumblr'),
			'vkontakte'           => array ('val' => 'vkontakte',           'lbl' => 'В Контакте'),
			'pinterest'           => array ('val' => 'pinterest',           'lbl' => 'Pinterest'),
			'facebook_like_but'   => array ('val' => 'facebook_like_but',   'lbl' => 'Facebook Like Button'),
			'twitter_tweet_but'   => array ('val' => 'twitter_tweet_but',   'lbl' => 'Twitter Tweet Button'),
			'linkedin_share_but'  => array ('val' => 'linkedin_share_but',  'lbl' => 'LinkedIn Share Button'),
			'vkontakte_share_but' => array ('val' => 'vkontakte_share_but', 'lbl' => 'В Контакте Share Button'),
			'email'               => array ('val' => 'email',               'lbl' => 'Email')
		);
	}


	/**
	 * Check API Settings - Ajax Call
	 */
	public function admin_ajax_verify_subdomain ($api_subdomain)
	{
		global $user;

		// Add language file.
		$user->add_lang_ext ('oneall/socialsharing', 'backend');

		// Returns a status message.
		$status_message = null;
		if (empty ($api_subdomain))
		{
			$status_message = 'error_|' . $user->lang ['OA_SOCIAL_SHARING_API_CREDENTIALS_FILL_OUT'];
		}
		if (empty ($status_message))
		{
			// The full domain has been entered.
			if (preg_match ("/([a-z0-9\-]+)\.api\.oneall\.com/i", $api_subdomain, $matches))
			{
				$api_subdomain = $matches [1];
			}
			// Check format of the subdomain.
			if (!preg_match ("/^[a-z0-9\-]+$/i", $api_subdomain))
			{
				$status_message = 'error|' . $user->lang ['OA_SOCIAL_SHARING_API_CREDENTIALS_SUBDOMAIN_WRONG'];
			}
		}
		if (empty ($status_message))
		{
			// Check if subdomain is correct with HEAD to library.
			$library_url = (self::is_https_on () ? 'https' : 'http') .'://'. $api_subdomain .'.api.oneall.com/socialize/library.js';
			$status_message = self::is_library_url_valid ($library_url) ?
				'success|' . $user->lang ['OA_SOCIAL_SHARING_API_CREDENTIALS_OK'] :
				'error|' . $user->lang ['OA_SOCIAL_SHARING_API_CREDENTIALS_SUBDOMAIN_WRONG'];
		}
		garbage_collection ();
		// Output for Ajax.
		die ($status_message);
	}


	/**
	 * Sends an API request by using the given handler.
	 */
	public static function is_library_url_valid ($url)
	{
		$headers = get_headers ($url);

		if ($headers === false)
		{
			return false;
		}
		if (stripos ($headers[0], '200') === false)
		{
			return false;
		}
		return true;
	}


	/**
	 * Check if the current connection is being made over https
	 */
	private static function is_https_on ()
	{
		global $request;

		if ($request->server ('SERVER_PORT') == 443)
		{
			return true;
		}

		if ($request->server ('HTTP_X_FORWARDED_PROTO') == 'https')
		{
			return true;
		}

		if (in_array (strtolower (trim ($request->server ('HTTPS'))), array(
				'on',
				'1'
		)))
		{
			return true;
		}

		return false;
	}

	/**
	 * Return the current url
	 */
	function get_current_url ($remove_vars = array ('oa_social_sharing_login_token', 'sid'))
	{
		global $request;

		// Extract Uri
		if (strlen (trim ($request->server ('REQUEST_URI'))) > 0)
		{
			$request_uri = trim ($request->server ('REQUEST_URI'));
		}
		else
		{
			$request_uri = trim ($request->server ('PHP_SELF'));
		}
		$request_uri = htmlspecialchars_decode ($request_uri);

		// Extract Protocol
		if (self::is_https_on ())
		{
			$request_protocol = 'https';
		}
		else
		{
			$request_protocol = 'http';
		}

		// Extract Host
		if (strlen (trim ($request->server ('HTTP_X_FORWARDED_HOST'))) > 0)
		{
			$request_host = trim ($request->server ('HTTP_X_FORWARDED_HOST'));
		}
		elseif (strlen (trim ($request->server ('HTTP_HOST'))) > 0)
		{
			$request_host = trim ($request->server ('HTTP_HOST'));
		}
		else
		{
			$request_host = trim ($request->server ('SERVER_NAME'));
		}

		// Port of this request
		$request_port = '';

		// We are using a proxy
		if (strlen (trim ($request->server ('HTTP_X_FORWARDED_PORT'))) > 0)
		{
			// SERVER_PORT is usually wrong on proxies, don't use it!
			$request_port = intval ($request->server ('HTTP_X_FORWARDED_PORT'));
		}
		// Does not seem like a proxy
		else if (strlen (trim ($request->server ('SERVER_PORT'))) > 0)
		{
			$request_port = intval ($request->server ('SERVER_PORT'));
		}

		// Remove standard ports
		$request_port = (!in_array ($request_port, array(
				80,
				443
		)) ? $request_port : '');

		// Build url
		$current_url = $request_protocol . '://' . $request_host . (!empty ($request_port) ? (':' . $request_port) : '') . $request_uri;

		// Remove query arguments.
		if (is_array ($remove_vars) && count ($remove_vars) > 0)
		{
			// Break up url
			list ($url_part, $query_part) = array_pad (explode ('?', $current_url), 2, '');
			parse_str ($query_part, $query_vars);

			// Remove argument.
			if (is_array ($query_vars))
			{
				foreach ($remove_vars as $var)
				{
					if (isset ($query_vars [$var]))
					{
						unset ($query_vars [$var]);
					}
				}

				// Build new url
				$current_url = $url_part . ((is_array ($query_vars) and count ($query_vars) > 0) ? ('?' . http_build_query ($query_vars)) : '');
			}
		}

		// Done
		return $current_url;
	}

}
