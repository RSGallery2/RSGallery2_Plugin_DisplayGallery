<?php
/**
 * RSGallery2 Gallery display plugin
 * This plugin supports the display of a gallery in an article
 *
 * This plugin will replace {rsg2_display: template, GID} or 
 * {rsg2_display: template, GID, parameter=value, parameter=value, ...}
 * in an article with the corresponding RSGallery2 gallery using the template 
 *  specified, with as many parameters as you specify
 * 
 * @package		RSGallery2
 * @subpackage	Content plugin
 * @copyright	Copyright (C) 2008-2019 RSGallery2 Team
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * RSGallery is Free Software
*/
  
// No direct access
defined( '_JEXEC' ) or die('');

// Import the general plugin file of Joomla!'s library
jimport( 'joomla.plugin.plugin' );

// Load RSGallery2 component (site) language file
$lang = JFactory::getLanguage();
$extension = 'com_rsgallery2';
$base_dir = JPATH_SITE;
$reload = true;
$lang->load($extension, $base_dir, null, $reload);

// Initialize RSGallery2 
//require_once( JPATH_ROOT.'/administrator/components/com_rsgallery2/init.rsgallery2.php' );
require_once( JPATH_ADMINISTRATOR.'/components/com_rsgallery2/init.rsgallery2.php' );
// ToDo: Remove following line
//require_once( JPATH_ROOT.'/administrator/components/com_rsgallery2/helpers/parameter.php' );



/**
 * ... gallerydisplay
 *
 * @since version 3.1
 */
class plgContentRsgallery2_gallerydisplay extends JPlugin {

	var $debugActive			= 0;

    /**
     * Load the language file on instantiation
     *
     * @var    boolean
     * @since  3.1 Joomla
     */
    protected $autoloadLanguage = true;

    /**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 *
     * @since       3.x
	 *
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
    /**/

	/**
	 * @param	string $context	The context of the content being passed to the plugin.
	 * @param	object $article	The article object.  Note $article->text is also available
	 * @param	object $params 	The article params
	 * @param	int $page 		The 'page' number ($limitstart)
     * @return bool
     *
     * @since       3.x
     */
	public function onContentPrepare($context, &$article, &$params, $page = 0) {

		// Simple high performance check to determine whether bot should process further.
		if (JString::strpos($article->text, 'rsg2_display') === false) {
			// 150319 old: return true;
			return false;
		}

		try {	
			// Define the regular expression for the bot.
            //$regex = "#{rsg2_display\:*(.*?)}#s";
		    $regex = "/\{rsg2_display:(.*?)\}/";

			// Perform the replacement
			$article->text = preg_replace_callback($regex, array(&$this, '_replacer'), $article->text);
		}
		catch(Exception $e) {
			$msg = JText::_('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY') . ' Error (01): ' . $e->getMessage();
            $app = JFactory::getApplication();
			$app->enqueueMessage($msg,'error');			
			return false;
		}

		return true;
	}	

	/**
	 * Replaces the matched tags with gallery html output
	 *
	 * @param	array	$matches An array of matches (see preg_match_all)
     * @return bool|string
     * @throws Exception
     */
	protected function _replacer ( $matches ) {
		global $rsgConfig;

		if( ! $matches ) 
		{
			return false;
		}

		$app = JFactory::getApplication();

		try {
		    //
            $this->debugActive = $this->params->get('debug', '0');

            // Save the default configuration because a user might change the
			// parameters via the plugin but can also use the plugin multiple
			// times on one page (use "clone" because in PHP5 objects are passed 
			// by reference).
			$original_rsgConfig = clone $rsgConfig;	

			$Rsg2DebugActive = $rsgConfig->get('debug');
			if ($Rsg2DebugActive)
			{
				// Include the JLog class.
				jimport('joomla.log.log');

				// Get the date for log file name
				$date = JFactory::getDate()->format('Y-m-d');

				// Add the logger.
				JLog::addLogger(
					// Pass an array of configuration options
					array(
							// Set the name of the log file
							//'text_file' => substr($application->scope, 4) . ".log.php",
							'text_file' => 'rsgallery2.GalleryDisplay.log.'.$date.'.php',

							// (optional) you can change the directory
							'text_file_path' => 'logs'
					 ) ,
						//JLog::ALL ^ JLog::DEBUG // leave out db messages
						JLog::ALL
				);
				
				// start logging...
				JLog::add('Start plg_rsg2_gallerydisplay: debug active in RSGallery2', JLog::DEBUG);
			}

			//----------------------------------------------------------------
			// Get attributes from matches and create "clean" array from them
			//----------------------------------------------------------------
			$attribs = explode (',', $matches[1]);
			if (is_array($attribs)) {
				$clean_attribs = array ();
				foreach ($attribs as $attribute) {
					// Remove spaces (&nbsp;) from attributes and trim with space
					$clean_attrib = $this->plg_rsg2_clean_string ( $attribute );
					array_push( $clean_attribs, $clean_attrib );
				}
			} else {
                if ($DebugActive) {
                    JLog::add('No attributes', JLog::DEBUG);
                }
				return false;
			}

			// Go over attribs to get template, gid and possible parameters
			foreach ($clean_attribs as $key => $value) {//$key is 0, 1, etc. $value is semantic, etc.
				switch ($key) {
					// template (required), e.g. semantic
					case 0:
						if (isset( $clean_attribs[0]) AND (string) $clean_attribs[0]){
							$template = strtolower( $clean_attribs[0] );
						} else {
							$template = Null;
						}			
					break;
					//  gallery id(required), e.g. 2
					case 1:
						if (isset( $clean_attribs[1]) AND (int) $clean_attribs[1]){
							$gallery_id = $clean_attribs[1];
						} else {
							$gallery_id = Null;
						}			
					break;
					// parameters like displaySearch=0;
					default:
                        $pieces = explode("=", $clean_attribs[$key]);
                        // Change the configuration parameter with the value
                        if (count($pieces) > 1) {
                            //$rsgConfig->$pieces[0] = $pieces[1];
                            //$rsgConfig [$pieces] = $pieces[1];
                            $rsgConfig->set ($pieces[0], $pieces[1]);
                        }
				}
			}

			//--- Start: Several checks on template and gallery id --------------------------------

			// Check we have a template name
			if (!isset($template)) {
				if ($DebugActive) {
					$msg = JText::_('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY_NO_TEMPLATE_NAME_GIVEN');
					$app->enqueueMessage($msg,'message');
                    JLog::add('Template not found: "' . $template . '"', JLog::DEBUG);
				}

				return false;
			}

			// Check the template is indeed installed
			$templateLocation = JPATH_RSGALLERY2_SITE . '/templates/' . $template . '/index.php';
			if( !file_exists( $templateLocation )) {
				if ($DebugActive) {
					$msg = JText::sprintf('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY_TEMPLATE_DIRECTORY_NOT_FOUND', $template);
					$app->enqueueMessage($msg,'message');
                    JLog::add('Template location not found: "' . $templateLocation. '"', JLog::DEBUG);
				}
				return false;
			}

			// Check we have a gallery id
			if (!isset($gallery_id)){
				if ($DebugActive) {
					$msg = JText::_('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY_NO_GALLERY_ID_GIVEN');
					$app->enqueueMessage($msg,'message');
                    JLog::add('no gallery id found: "' . $gallery_id. '"', JLog::DEBUG);
				}
				return false;
			}

			// Check if a gallery with gallery id exists
			// Get gallery details first
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id, name, published'); // ToDo: Perhaps access could be checked as well
			$query->from('#__rsgallery2_galleries');
			$query->where('id = '. (int) $gallery_id);
			$db->setQuery($query);
			$galleryDetails = $db->loadAssoc();

			// Does the gallery exist?
			if (!$galleryDetails) {
				if ($DebugActive) {
					$msg = JText::sprintf('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY_NO_SUCH_GALLERY_ID_EXISTS',$gallery_id);
					$app->enqueueMessage($msg,'message');
                    JLog::add('gallery id not found in DB: "' . $gallery_id. '"', JLog::DEBUG);
				}
				return false;
			}

			// Is the gallery published?
			if (!$galleryDetails['published']) {
				if ($DebugActive) {
					$msg = JText::sprintf('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY_GALLERY_UNPUBLISHED',$galleryDetails['name'],$gallery_id);
					$app->enqueueMessage($msg,'message');
                    JLog::add('gallery not published: "' . $gallery_id. '"', JLog::DEBUG);
				}
				return false;
			}
			//--- End: Several checks on template and gallery id ---------------------------------


			// Cache the current request array to a variable before doing anything
			$original_request 	= $_REQUEST;
			$original_get 		= $_GET;
			$original_post 		= $_POST;

			//--- patch the input variables ---------------

			//The article has lang, language, Itemid, option, view, catid and id
			//Get rid of catid and id, change option and view, set gallery_id (gid).
			$input = JFactory::getApplication()->input;
			//JRequest::setVar('catid',Null);	//Is there a way to unset this?

			// Id may otherwise try to retrieve a image
			JRequest::setVar('id',Null);	//Is there a way to unset this?
			//JRequest::setVar('option','com_rsgallery2');
			//JRequest::setVar('view', 'gallery');

			//JRequest::setVar('gid', $gallery_id);
			$input->set ('gid', $gallery_id);
			//JRequest::setVar('rsgTemplate', $template);
			$input->set ('rsgTemplate', $template);

			//--- Get the RSGallery2 gallery template HTML! -----------------------
			ob_start();
    		rsgInstance::instance(); // With option $showTemplate = true
			$content_output = ob_get_contents();
			ob_end_clean();

            /**
            if ($DebugActive) {
                JLog::add('$content_output\n' . $content_output . '\n', JLog::DEBUG);
            }
            /**/

			// Reset the original request array when finished
			$_REQUEST 	= $original_request;
			$_GET 		= $original_get;
			$_POST 		= $original_post;
			$rsgConfig	= clone $original_rsgConfig;

			return $content_output;

		}
		catch(Exception $e) {
			$msg = JText::_('PLG_CONTENT_RSGALLERY2_GALLERYDISPLAY') . ' Error (02): ' . $e->getMessage();
            $app = JFactory::getApplication();
			$app->enqueueMessage($msg,'error');			
			return false;
		}

        return false;
	}

	/**
	 * Remove spaces (&nbsp;) from attributes and trim white space
	 *
	 * @param string $attributeIn
	 * @return	string
     */
	function plg_rsg2_clean_string ( $attributeIn ) {
	    $attribute = str_replace( "&nbsp;", '', "$attributeIn" );
		// $attribute = trim ($attribute); // surprisingly only one blank removed
		$attribute = preg_replace('/\s/u', '', $attribute); // '/u' -> unicode
		return $attribute;
	}	
}
