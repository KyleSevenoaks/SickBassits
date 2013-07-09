<?php
/*
	Plugin Name: SG CachePress
	Version: 1.0.11
	Author: George Penkov, <a href='http://www.siteground.com' target='_blank'>SiteGround</a>
	Description: Through the settings of this plugin you can manage how your Wordpress interracts with SG Cache.
	Before you can use this plugin you need to have the SG Cache service installed and activated.
*/

include('SGCachePressView.php');

class SGCachePress
{
	/**
	 * Web path to the wordpress application.
	 * @var string
	 */
	private $_applicationPath = null;
	private $_cacheEnabled = false;
	/**
	 * Auto flush flag
	 * @var bool
	 */
	private $_autoflush = false;
	/**
	 * Array of settings for the plugin.
	 * @var array
	 */
	public $options=array();
	/**
	 * Flag which raises when we already flushed on this page exec.
	 * @var array
	 */
	private static $_flushed = false;

	function __construct()
	{
		if(isset($_GET['sgCacheCheck']) && $_GET['sgCacheCheck'] == md5('wpCheck'))
			die('OK');
		// Enable SG Cache automatically after installation
		if( get_option('SGCP_Installed') !== "Yes" )
		{
			add_option('SGCP_Use_SG_Cache', 0);
			add_option('SGCP_Autoflush', 1);
			add_option('SGCP_Installed', 'Yes');
		}
		// Extract options from the database
		$this->retrieveSettings();

		// Attach AJAX Post hooks
		add_action('wp_ajax_PurgeSGCacheNow',array(&$this, 'purgeCache'));
		add_action('wp_ajax_SGCPSaveSettings',array(&$this, 'updateSettings'));
		add_action('admin_menu',array(&$this,'adminMenu'));
		add_action('admin_head',array(&$this,'loadJS'));
		add_filter('plugin_action_links', array(&$this,'settingsLink'), 10, 2);

		// Obtain and set application's path and URL for further usage
		$homeUrl = home_url('/');
		$urlExplode = explode($_SERVER['HTTP_HOST'], $homeUrl);
		$this->_applicationPath = $urlExplode[1];

		if (get_option('SGCP_Use_SG_Cache') != 1)
		{
			header('X-Cache-Enabled: False');
		}
		else
		{	// Enabled SG Cache
			header('X-Cache-Enabled: True');

			//Logged In Users
			if(is_user_logged_in() || $_POST['wp-submit'] == 'Log In')
			{
				//Enable the cache bypass for logged users by setting a cache bypass cookie
	 			setcookie('wpSGCacheBypass',1,time() + 6000,'/');
			}
			else
			if(!is_user_logged_in() || $_GET['action'] == 'logout')
			{
				setcookie('wpSGCacheBypass',0, time() - 3600,'/');
			}

			// Attach filtering hooks for automatic cache flush
			$this->assignHooks();
		}
	}

	// Adding links to the Tools section, and to the bottom of the side panel
	public function adminMenu()
	{
		if(is_super_admin())
		{
			add_submenu_page('tools.php','SG Cache Settings','SG Cache','manage_options','SGCP',array(&$this,'settingsPage'));
			add_menu_page('Purge Cache','Purge Cache','manage_options','SGCachePress::purgeCache',array(&$this,'purgeCachePage'));
		}
	}

	// Add link to the Settings page in the Plugins section
	public function settingsLink($links, $file)
	{
		if(!$this_plugin)
		{
			$this_plugin = plugin_basename(__FILE__);
		}

		if($file == $this_plugin)
		{
			$uri = explode('?',$_SERVER['REQUEST_URI']);
			array_unshift($links,'<a href="http://'.$_SERVER['SERVER_NAME'].str_replace("plugins.php",'tools.php?page=SGCP',$uri[0]).'">Settings</a>');
		}

		return $links;
	}

	// Add the message holder
	public static function notice($message = null)
	{
		echo '<style>//SGCP_Message{display:none;}</style><div id="SGCP_Message" class="updated"><p>'.$message.'</p></div>';
	}

    /**
     * Calls the cache server to purge the cache
     *
     * @access public
     * @param string|bool $message Message to be displayed if purge is successful. If this param is false no output would be done
     * @return null
     */
	public function purgeCache( $message = true )
	{
		if (self::$_flushed)
			return;

		$purgeRequest = $this->_applicationPath . '(.*)';

		// Check if caching server is online
		$hostname = trim(file_get_contents('/etc/sgcache_ip',true));
		$cacheServerSocket = fsockopen($hostname, 80, $errno, $errstr, 2);
		if(!$cacheServerSocket)
		{
			$this->SGCP_Notify('Connection to cache server failed!');
			return;
		}

		$request = "BAN {$purgeRequest} HTTP/1.0\r\n";
      	$request .= "Host: {$_SERVER['SERVER_NAME']}\r\n";
      	$request .= "Connection: Close\r\n\r\n";

      	fwrite($cacheServerSocket, $request);
      	$response = fgets($cacheServerSocket);
      	fclose($cacheServerSocket);

      	if($message != false)
      	{
      		if(preg_match('/200/',$response))
      		{
      			self::$_flushed = true;
      			if ($message === true)
      				self::notify('SG Cache Successfully Purged!');
      			else
      				self::notify($message);
      		}
      		else
      		{
      			self::notify('SG Cache: Purge was not successful!Error: ' . $response);
      		}
      	}

		if (isset($_POST['action']) && $_POST['action'] == 'PurgeSGCacheNow')
			if(preg_match('/200/',$response))
				die('purged!');
			else
				die('SG Cache: Purge was not successful!Error: ' . $response);
	}

	/**
	 * Print notification in the admin section, or via AJAX
	 *
	 * @access public
	 * @param string|bool $message Message to be displayed if purge is successful. If this param is false no output would be done
	 * @return null
	 */
	public static function notify($message)
	{
		add_action('muplugins_loaded',SGCachePress::notice($message));
	}

	/**
	 * Retrieve values for the settings from the database and assign them to the private array $options
	 *
	 * @access public
	 * @param string|bool $message Message to be displayed if purge is successful. If this param is false no output would be done
	 * @return null
	 */
	public function retrieveSettings()
	{
		$SGCoptions = array(
			'SGCP_Use_SG_Cache',	// SG CachePress is disabled
			'SGCP_Autoflush',		// Autoflush is enabled
		);

		foreach($SGCoptions as $optionName)
			$this->options[$optionName] = get_option($optionName) !== false ? get_option($optionName) : 0;
	}

	public function updateSettings()
	{
		if( isset($_POST['options']) && !empty($_POST['options']) )
		{
			$errors = 0;
			foreach($_POST['options'] as $a=>$b)
			{
				foreach($b as $setting => $value)
				{
					if($setting == 'SGCP_Use_SG_Cache' && ($this->options['SGCP_Use_SG_Cache'] !== $value) )
						$this->purgeCache(false);

					update_option($setting,$value);

					if( get_option($setting) !== $value )
						$errors++;
				}
			}

			if(!$errors)
				echo'Saved!';
			else
				echo'Backward verification failed!';
		}
			else
				echo"No data was passed!";

		die();
	}

	public function assignHooks()
	{
		add_action('save_post',array(&$this,'SGCP_Hook_AddPost'));
		add_action('edit_post',array(&$this,'SGCP_Hook_AddPost'));
		add_action('publish_phone',array(&$this,'SGCP_Hook_AddPost'));
		add_action('publish_future_post',array(&$this,'SGCP_Hook_AddPost'));
		add_action('xmlrpc_publish_post',array(&$this,'SGCP_Hook_AddPost'));
		add_action('before_delete_post',array(&$this,'SGCP_Hook_DelPost'));
		add_action('trash_post',array(&$this,'SGCP_Hook_DelPost'));
		add_action('add_category',array(&$this,'SGCP_Hook_AddCat'));
		add_action('edit_category',array(&$this,'SGCP_Hook_EditCat'));
		add_action('delete_category',array(&$this,'SGCP_Hook_DelCat'));
		add_action('add_link',array(&$this,'SGCP_Hook_AddLink'));
		add_action('edit_link',array(&$this,'SGCP_Hook_EditLink'));
		add_action('delete_link',array(&$this,'SGCP_Hook_DelLink'));
		add_action('comment_post',array(&$this,'SGCP_Hook_AddComment'),10,2);
		add_action('comment_unapproved_to_approved',array(&$this,'SGCP_Hook_ApprComment'));
		add_action('comment_approved_to_unapproved',array(&$this,'SGCP_Hook_ApprComment'));
		add_action('delete_comment',array(&$this,'SGCP_Hook_DelComment'));
		add_action('trash_comment',array(&$this,'SGCP_Hook_DelComment'));
		add_action('switch_theme',array(&$this,'SGCP_Hook_Themes'));

		$purgeActions = array('widgets-order','save-widget','delete-selected');

		if (!empty($_POST) && $this->options['SGCP_Autoflush'] != 1)
		{
			if (isset($_POST['action']))
			{
				if (in_array($_POST['action'], $purgeActions))
					$this->purgeCache(false);


				if (isset($_POST['submit']))
				{
					if ($_POST['action'] == 'update' && $_POST['submit']=='Update File')
						$this->purgeCache(false);
					if ($_POST['action'] == 'update' && $_POST['submit']=='Save Changes')
						$this->purgeCache(false);
					if ($_POST['action'] == 'update' && $_POST['submit']=='Save Changes')
						$this->purgeCache(false);
				}

			}

			// Settings -> Permalinks
			if (isset($_POST['submit']) && $_POST['submit'] == 'Save Changes' && isset($_POST['_wp_http_referer']))
			{
				$ref=explode('/',$_POST['_wp_http_referer']);
				$ref=array_pop($ref);

				if($ref == 'options-permalink.php')
				{
					if($_POST['action'] == 'update' && $_POST['submit'] == 'Save Changes' && $_POST['option_page'] == 'permalinks')
						$this->purgeCache(false);
				}
			}

			if(isset($_POST['save_menu']))
			{
				// Add Menu
				if(in_array($_POST['save_menu'],array('Create Menu','Save Menu')))
					$this->purgeCache(false);

			}
		}

		if( !empty($_GET) && isset($_GET['action']))
		{
			if(isset($_GET['menu']) && $_GET['action'] == 'delete')
				$this->purgeCache(false);
			if(isset($_GET['plugin']) && $_GET['action'] == 'activate')
				$this->purgeCache(false);
		}
	}

	public function SGCP_Hook_AddPost($postID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		if ((isset($_POST['publish']) && $_POST['publish'] == 'Publish') || (isset($_POST['action']) && in_array($_POST['action'],array('inline-save','editpost'))))
			$this->purgeCache(false);
	}

	public function SGCP_Hook_DelPost($postID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		if(isset($_GET['action']) && ($_GET['action']=='delete' || $_GET['action']=='trash'))
			$this->purgeCache(false);
	}

	public function SGCP_Hook_AddCat($catID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;
		if (isset($_POST['action']) && $_POST['action'] == 'add-tag')
			$this->purgeCache(false);
	}

	public function SGCP_Hook_EditCat($catID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;
		if (isset($_POST['action']) && $_POST['action'] == 'editedtag')
			$this->purgeCache(false);
	}

	public function SGCP_Hook_DelCat($catID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;
		if (isset($_POST['action']) && $_POST['action'] == 'delete-tag')
			$this->purgeCache(false);
	}

	public function SGCP_Hook_AddLink($linkID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		if(isset($_POST['action']) && isset($_POST['save']) && $_POST['action'] == 'add' && $_POST['save'] == 'Add Link')
			$this->purgeCache(false);
	}

	public function SGCP_Hook_EditLink( $linkID )
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;
		if (isset($_POST['action']) && isset($_POST['submit']) && $_POST['action'] == 'editedtag' && $_POST['submit'] == 'Update')
			$this->purgeCache(false);
	}

	public function SGCP_Hook_DelLink( $linkID )
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		if( isset($_POST['action']) && isset($_POST['taxonomy'])&& $_POST['taxonomy'] == 'link_category' && $_POST['action'] == 'delete-tag' )
			$this->purgeCache(false);
	}

	public function SGCP_Hook_AddComment( $commentId, $status = null )
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		if( isset($_POST['comment_post_ID']) )
		{
			$comment = get_comment($commentId);

			//  Purge post page
			if($comment)
				$this->purgeCache(false);
		}
	}

	public function SGCP_Hook_ApprComment( $commentId, $status = null )
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		if( isset($_POST['id']) )
		{
			$comment = get_comment($commentId);

			//  Purge post page
			if($comment)
				$this->purgeCache(false);
		}
	}


	public function SGCP_Hook_DelComment($commentID)
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		$commentActions = array('dim-comment','delete-comment');

		if( (isset($_POST['action']) && isset($_POST['id']) && in_array($_POST['action'],$commentActions))
			|| (isset($_GET['action']) && isset($_GET['c']) && $_GET['action'] == 'trashcomment'))
		{
			$comment=get_comment($_POST['id']);
			if($comment)
				$this->purgeCache(false);
		}
	}

	public function SGCP_Hook_Themes()
	{
		if ($this->options['SGCP_Autoflush'] != 1)
			return;

		$this->purgeCache(false);
	}

}

add_action("init","startSGCache");

function startSGCache()
{
	global $SGCachePress,$SGCachePressView;
	$SGCachePressView= new SGCachePressView;
}