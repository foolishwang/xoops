<?php
/**
 * Zend Framework for Xoops Engine
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       The Xoops Engine http://sourceforge.net/projects/xoops/
 * @license         http://www.fsf.org/copyleft/gpl.html GNU public license
 * @author          Taiwen Jiang <phppp@users.sourceforge.net>
 * @since           3.0
 * @category        Xoops_Zend
 * @package         Layout
 * @version         $Id$
 */

class Lite_Zend_Layout extends Zend_Layout
{
    public static $paths = array(
        'app'       => 'apps',
        'module'    => 'modules',
        'plugin'    => 'plugins',
        'theme'     => '',
    );
    public $plugin;
    public $helper;

    /**
     * Layout theme
     * @var string
     */
    protected $theme = 'default';
    /**
     * Options for cache
     * @var array
     */
    private $cacheOptions;

    public $skipCache = false;
    public $cacheLevel = null;

    private $cacheInfo = null;
    /**
     * Cache object
     * @var Xoops_Zend_Cache
     */
    private $cache;

    /**
     * Navigation flag: false or empty - disable; 'admin' - Admin navigation; 'front' - Front navigation
     * @var string
     */
    protected $navigation = '';

    /**
     * Placeholder container for layout variables
     * @var array
     */
    protected $_container;

    /**
     * Key used to store content from 'default' named response segment
     * @var string
     */
    protected $_contentKey = 'xoops_contents';

    /**
     * Key used to store comment/subscription contents to named response segment
     * For intenal only
     * @var string
     */
    protected $_extensionKey = 'xoops_extensions';

    /**
     * Helper class
     * @var string
     */
    protected $_helperClass = 'Lite_Zend_Layout_Controller_Action_Helper_Layout';

    /**
     * Flag: is inflector enabled?
     * @var bool
     */
    protected $_inflectorEnabled = false;

    /**
     * Layout view
     * @var string
     */
    protected $_layout = 'layout';

    /**
     * Plugin class
     * @var string
     */
    protected $_pluginClass = 'Lite_Zend_Layout_Controller_Plugin_Layout';

    /**
     * View script suffix for layout script
     * @var string
     */
    protected $_viewSuffix = 'html';

    protected $metaList = array(
            "doctype"       => "__XOOPS_THEME_DOCTYPE__",
            "headTitle"     => "__XOOPS_THEME_HEAD_TITLE__",
            "headMeta"      => "__XOOPS_THEME_HEAD_META__",
            "headLink"      => "__XOOPS_THEME_HEAD_LINK__",
            "headScript"    => "__XOOPS_THEME_HEAD_SCRIPT__",
            "headStyle"     => "__XOOPS_THEME_HEAD_STYLE__",
    );

    /**
     * Constructor
     *
     * Accepts either:
     * - A string path to layouts
     * - An array of options
     * - A Zend_Config object with options
     *
     * Layout script path, either as argument or as key in options, is
     * required.
     *
     * If mvcEnabled flag is false from options, simply sets layout script path.
     * Otherwise, also instantiates and registers action helper and controller
     * plugin.
     *
     * @param  string|array|Zend_Config $options
     * @return void
     */
    public function __construct($options = null, $initMvc = false)
    {
        if (null !== $options) {
            if (is_string($options)) {
                $this->setTheme($options);
            } elseif (is_array($options)) {
                $this->setOptions($options);
            } elseif ($options instanceof Zend_Config) {
                $this->setConfig($options);
            } else {
                //require_once 'Zend/Layout/Exception.php';
                throw new Zend_Layout_Exception('Invalid option provided to constructor');
            }
        }

        $this->_initVarContainer();

        if ($initMvc) {
            $this->_setMvcEnabled(true);
            $this->_initMvc();
        } else {
            $this->_setMvcEnabled(false);
        }
    }

    /**
     * Static method for initialization with MVC support
     *
     * @param  string|array|Zend_Config $options
     * @return Zend_Layout
     */
    public static function startMvc($options = null)
    {
        if (null === self::$_mvcInstance) {
            self::$_mvcInstance = new self($options, true);
        } else {
            if (is_string($options)) {
                self::$_mvcInstance->setLayoutPath($options);
            } elseif (is_array($options) || $options instanceof Zend_Config) {
                self::$_mvcInstance->setOptions($options);
            }
        }

        return self::$_mvcInstance;
    }

    /**
     * Initialize front controller plugin
     *
     * @return void
     */
    protected function _initPlugin()
    {
        $pluginClass = $this->getPluginClass();
        $plugin = new $pluginClass($this);

        if ($this->plugin === true) {
            $this->plugin = $plugin;
            return;
        } else {
            $this->plugin = $plugin;
        }
        $front = Zend_Controller_Front::getInstance();
        if (!$front->hasPlugin($pluginClass)) {
            $front->registerPlugin(
                // register to run last | BUT before the ErrorHandler (if its available)
                $plugin,
                99
           );
        }
    }

    /**
     * Initialize action helper
     *
     * @return void
     */
    protected function _initHelper()
    {
        $helperClass = $this->getHelperClass();
        if (!Zend_Controller_Action_HelperBroker::hasHelper('layout')) {
            $this->helper = new $helperClass($this);
            Zend_Controller_Action_HelperBroker::getStack()->offsetSet(-90, $this->helper);
        } else {
            $this->helper = Zend_Controller_Action_HelperBroker::getHelper('layout');
        }
    }

    /**
     * Initialize placeholder container for layout vars
     *
     * @return Zend_View_Helper_Placeholder_Container
     */
    protected function _initVarContainer()
    {
        if (null === $this->_container) {
            $this->_container = array();
        }

        return $this->_container;
    }

    /**
     * Set flag for block render
     *
     * @param  boolean $enable
     * @return Zend_Layout
     */
    public function setBlock($enable)
    {
        $this->_blockRender = (bool) $enable;
        return $this;
    }

    /**
     * Set navigation section
     *
     * @param  bool|string $section
     * @return Zend_Layout
     */
    public function setNavigation($section)
    {
        /*
        if (empty($section)) {
            $this->loader["navigation"] = false;
        }
        */
        $this->navigation = $section;
        return $this;
    }

    /**
     * Set layout script to use
     *
     * Note: enables layout by default, can be disabled
     *
     * @param  string $name
     * @param  boolean $enabled
     * @return Zend_Layout
     */
    public function setLayout($name, $enabled = true)
    {
        parent::setLayout($name, $enabled);
        return $this;
    }

    public function getViewScriptPath()
    {
        if (empty($this->_viewScriptPath)) {
            $this->_viewScriptPath = "theme/" . $this->theme;
        }

        return $this->_viewScriptPath;
    }


    /**
     * Assign one or more layout variables
     *
     * @param  mixed $spec Assoc array or string key; if assoc array, sets each
     * key as a layout variable
     * @param  mixed $value Value if $spec is a key
     * @return Zend_Layout
     * @throws Zend_Layout_Exception if non-array/string value passed to $spec
     */
    public function assign($spec, $value = null)
    {
        if (is_array($spec)) {
            $this->_container = array_merge($this->_container, $spec);
            /*
            $orig = $this->_container->getArrayCopy();
            $merged = array_merge($orig, $spec);
            $this->_container->exchangeArray($merged);
            */
            return $this;
        }

        if (is_string($spec)) {
            //$this->getView()->getEngine()->append("layout", $spec);
            if (empty($this->_container[$spec])) {
                $this->_container[$spec] = $value;
            } else {
                $this->_container[$spec] .= $value;
            }
            return $this;
        }

        throw new Zend_Layout_Exception('Invalid values passed to assign()');
    }

    /**
     * Render layout
     *
     * Sets internal script path as last path on script path stack, assigns
     * layout variables to view, determines layout name using inflector, and
     * renders layout view script.
     *
     * $name will be passed to the inflector as the key 'script'.
     *
     * @param  mixed $name  layout or template to render
     * @return mixed
     */
    public function render($name = null)
    {
        static $registered;
        // Load locale translation
        //XOOPS::service('translate')->loadTranslation("main", "theme:" . $this->theme);

        $template = $this->getView()->getEngine();
        $template->caching = 0;
        $template->setCacheId();
        $template->setCompileId($this->theme);
        $template->assign($this->_container);
        $template->template_dir = array(
            XOOPS::path("theme") . "/" . $this->theme,
            XOOPS::path("theme"),
        );
        $template->assign($this->metaList);

        if (!$registered) {
            //$template->register->templateFunction("blocks", array($this, "loadBlocks"));
            //$template->register->templateFunction("navigation", array($this, "loadNavigation"));
            //$template->register->outputFilter(array($this, "loadHead"));
            $template->registerPlugin("function", "blocks", array($this, "loadBlocks"));
            $template->registerPlugin("function", "navigation", array($this, "loadNavigation"));
            $template->registerFilter("output", array($this, "loadHead"));
            $registered = true;
        }

        $name = !is_null($name) ? $name : $this->getLayout();
        //$path = is_readable($name) ? $name : $this->getLayoutPath() . "/" . $name . "." . $this->getViewSuffix();
        //return $template->fetch(XOOPS::path($path));
        $path = is_readable($name) ? $name : "theme/" . $name . "." . $this->getViewSuffix();
        return $template->fetch($path);
        //$content = $template->fetch($path);
        //Debug::e(__METHOD__.': content fetched');
        //return $content;
    }

    public function setTheme($theme = "default")
    {
        if ("default" != $theme) {
            if (!array_key_exists($theme, XOOPS::service("registry")->theme->read())) {
                return $this;
            }
        }

        $this->theme = $theme;
        return $this;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function setCache($options)
    {
        $this->cacheOptions = $options;
    }

    /**
     * Load cache object
     *
     * @return Xoops_Zend_Cache
     */
    public function cache()
    {
        if (!is_object($this->cache)) {
            $this->cache = XOOPS::registry('cache');
        }
        return $this->cache;
    }

    /**
     * Set page cache level
     *
     * @param   string  $level  potential values: user, role, language, public by default
     * @return  void
     */
    public function cacheLevel($level = null)
    {
        if (!is_null($level)) {
            $this->cacheLevel = $level;
        }
        return $this->cacheLevel;
    }

    /**
     * Skip page cache
     *
     * @return  void
     */
    public function skipCache($flag = true)
    {
        $this->skipCache = (bool) $flag;
    }

    /**
     * Set plugin
     *
     * @param  array    $options
     * @return Zend_Layout
     */
    public function setPlugin($options)
    {
        if (!empty($options['class'])) {
            $this->setPluginClass($options['class']);
        }
        if (isset($options['register'])) {
            $this->plugin = empty($options['register']) ? true : false;
        }
    }

    public function assemble($request)
    {
        if ($request->isXmlHttpRequest() || $request->isFlashRequest()) {
            $this->setLayout("empty");
        } elseif (!$this->getLayout()) {
            $this->setLayout("layout");
        }

        // Assemble various contents
        $fullContent = $this->render();

        return $fullContent;
    }

    /**
     * Initialize view variables
     *
     * @return void
     */
    public function initView()
    {
        //global $xoops, $xoopsUser, $xoopsConfig, $xoopsModule;
        $template = $this->getView()->getEngine();

        /**#@+
         * Legacy data
         */
        $variables = array();
        // User data
        if (!XOOPS::registry('user')->isGuest()) {
            $variables += array(
                'xoops_isuser'  => true,
                'xoops_userid'  => XOOPS::registry('user')->id,
                'xoops_uname'   => XOOPS::registry('user')->identity,
                'xoops_isadmin' => XOOPS::registry('user')->role == 'admin'
            );
        } else {
            $variables += array(
                'xoops_isuser'  => false,
                'xoops_isadmin' => false
            );
        }

        // Site data
        $variables += array(
            'xoops_url'         => XOOPS::url('www'),
            'xoops_rootpath'    => XOOPS::path('www'),
            'xoops_langcode'    => XOOPS::registry('locale')->getLanguage(),
            'xoops_upload_url'  => XOOPS::url('upload'),
            'xoops_sitename'    => htmlspecialchars(XOOPS::config('sitename'), ENT_QUOTES),
            'xoops_dirname'     => XOOPS::registry("module") ? XOOPS::registry("module")->dirname : 'system',
        );
        foreach ($variables as $key => $val) {
            $template->assignGlobal($key, $val);
        }
        /**#@-*/

        $this->loadMeta();

        //$this->getView()->doctype('XHTML1_TRANSITIONAL');
    }

    private function initHead()
    {
        $pageConfig = XOOPS::config('page');
        $view = $this->getView();
        $view->doctype($pageConfig['doctype']);
        $this->assign("xoops_footer", $pageConfig['footer']);

        // Page meta tags
        $headMeta = array();
        $metaConfig = (array) XOOPS::config('meta');
        foreach ($metaConfig as $key => $value) {
            $view->headMeta()->appendName($key, $value);
        }
        $view->headMeta()->appendHttpEquiv("content-language", XOOPS::registry('locale')->getLanguage());
        $view->headMeta()->appendHttpEquiv("content-type", "text/html; charset=" . XOOPS::registry('locale')->getCharset());

        $view->headTitle()->setSeparator(" - ");
        // Append site name to head title
        $view->headTitle(XOOPS::config('slogan'));
        $view->headTitle(XOOPS::config('sitename'));

        $view->headLink()->prependStylesheet("theme/" . $this->theme . "/style.css", "all");
        $view->headLink(array(
            "rel"   => "favicon",
            "type"  => "image/ico",
            "href"  => "favicon.ico"
        ));

        return $headMeta;
    }

    public function loadHead($data, $complier, $template)
    {
        $view = $this->getView();
        foreach ($this->metaList as $func => $tag) {
            if (false === ($pos = strpos($data, $tag))) {
                continue;
            }
            $meta = $view->$func();
            $data = substr($data, 0, $pos) . $meta . substr($data, $pos + strlen($tag));
        }
        return $data;
    }

    private function loadMeta()
    {
        $meta = $this->initHead();

        $this->assign($meta);
    }

    public function loadNavigation($params, $smarty, $template)
    {
        return false;
        if (empty($params["assign"])) {
            return false;
        }
        if (empty($this->navigation)) {
            return;
        }

        $view = $this->getView();
        $request = XOOPS::registry("frontController")->getRequest();
        $module = $request->getModuleName();
        $config = XOOPS::service("registry")->navigation->read($this->navigation, $module);
        $container = new Xoops_Zend_Navigation($config);
        $view->navigation($container);
        $ulClass = empty($params["ulClass"]) ? 'jd_menu' : $params["ulClass"];
        $navigation = array(
            "menu"          => $view->navigation()->menu()->setUlClass($ulClass)->render(),
            "breadcrumbs"   => $view->navigation()->breadcrumbs()->setMinDepth(0)->setLinkLast(false)->render()
        );
        $template->assign($params["assign"], $navigation);
        return;
    }

    public function loadBlocks($params, $smarty, $template)
    {
        return false;
        if (empty($params["assign"])) {
            return false;
        }
        $request = XOOPS::registry("frontController")->getRequest();
        $blocks = $this->getView()->blocks($request);
        $template->assign($params["assign"], $blocks);
        return;
    }


    /**
     * Return a web resource file path
     *
     * Path options:
     * theme/, module/, app/, www/apps
     *
     * @param string $path
     * @return string
     */
    public function resourcePath($path, $isAbsolute = false)
    {
        // themes/themeName/modules/moduleName/templates/template.html, themes/themeName/app/moduleName/templates/template.html
        // themes/themeName/modules/moduleName/module.js, themes/themeName/app/moduleName/app.js
        // themes/themeName/modules/moduleName/images/img.png, themes/themeName/app/moduleName/images/img.png

        // File name prepended with resource type, for db:file.name, file:file.name or app:file.name
        // Or full path under WIN: C:\Path\To\Template
        // Return directly
        if (!empty($path) && false !== strpos($path, ":")) {
            return $path;
        }

        $path       = trim($path, "/");
        $section    = "";
        $module     = "";
        $append     = "";
        $segs = explode("/", $path, 2);
        $section = $segs[0];
        if (!empty($segs[1])) {
            $append = $segs[1];
        }
        if (isset(static::$paths[$section])) {
            $sectionPath = (empty(static::$paths[$section]) ? "" : static::$paths[$section] . "/") . $append;
        } else {
            $sectionPath = $path;
        }
        $theme_path = XOOPS::path("theme") . "/{$this->theme}/{$sectionPath}";
        // Found in theme
        if (file_exists($theme_path)) {
            return $isAbsolute ? $theme_path : "theme/{$this->theme}/{$sectionPath}";
        }
        // Check for application resource path
        if ("app" == $section && !empty($append)) {
            $app_resource_path = XOOPS::path("www/apps") . "/" . $append;
            // Found in www/apps
            if (file_exists($app_resource_path)) {
                return $isAbsolute ? $app_resource_path : "www/apps/" . $append;
            }
            $segs = explode("/", $append, 2);
            $module = $segs[0];
            $dirname = Xoops::service('module')->getDirectory($module);
            // Check parent in www/apps
            if ($dirname && strcmp($dirname, $module)) {
                $parent_append = $dirname . (empty($segs[1]) ? "" : "/" . $segs[1]);
                $parent_resource_path = XOOPS::path("www/apps") . "/" . $parent_append;
                // Found parent in www/apps
                if (file_exists($parent_resource_path)) {
                    return $isAbsolute ? $parent_resource_path : "www/apps/" . $parent_append;
                }
                // Return parent original file
                return $isAbsolute ? XOOPS::path("app/" . $parent_append) : "app/" . $parent_append;
            // Check original, actually not necessary
            } else {
            }
        }
        // Check for plugin resource path
        if ("plugin" == $section && !empty($append)) {
            $plugin_resource_path = XOOPS::path("www/plugins") . "/" . $append;
            // Found in www/plugins
            if (file_exists($app_resource_path)) {
                return $isAbsolute ? $app_resource_path : "www/plugins/" . $append;
            }
            // Return original file
            return $isAbsolute ? XOOPS::path("plugin/" . $append) : "plugin/" . $append;
        }

        return false;
    }

    /**
     * Attempt to load content from template cache if cache is valid
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @retrun boolean  true if cache is load
     */
    public function ____loadContent($request/*, $cache*/)
    {
        if (empty($this->cacheInfo)) {
            return false;
        }
        $cache = $this->cacheInfo;
        $template = $this->getView()->getEngine();
        if (empty($cache['expire']) || $this->skipCache) {
            $template->caching = 0;
        } else {
            $template->caching = 1;
            $template->cache_lifetime = $cache['expire'];
            $template->setCacheId($cache['cache_id']);
        }
        $template->setCompileId($this->getTheme(), $request->getModuleName());
        //Debug::e("compileId: " . $this->getTheme() .'-'. $request->getModuleName());

        if (empty($cache['template']) || !$template->is_cached($cache['template'])) {
            return false;
        }
        $content = $template->fetch($cache['template']);
        //Debug::e($this->getContentKey());
        //Debug::e($content);
        $this->assign($this->getContentKey(), $content);
        return true;
    }

    /**
     * Load content from response container
     *
     * @param  Zend_Controller_Response_Abstract $response
     * @retrun boolean
     */
    public function setContent($response)
    {
        $content = $response->getBody(true);
        $response->clearBody();
        $contentKey = $this->getContentKey();
        $extensionKey = $this->getExtensionKey();

        if (isset($content['default'])) {
            $content[$contentKey] = $content['default'];
        }
        if (isset($content[$extensionKey])) {
            $content[$contentKey] .= $content[$extensionKey];
        }
        if ('default' != $contentKey) {
            unset($content['default']);
        }
        $this->assign($content);

        return true;

        $template = $this->getView()->getEngine();
        $cache = array('template' => $template->currentTemplate);

        return $cache;
    }

    /**
     * Set extension key
     *
     * Key in namespace container denoting extension content
     *
     * @param  string $extensionKey
     * @return Zend_Layout
     */
    public function setExtensionKey($extensionKey)
    {
        $this->_extensionKey = (string) $extensionKey;
        return $this;
    }

    /**
     * Retrieve extension key
     *
     * @return string
     */
    public function getExtensionKey()
    {
        return $this->_extensionKey;
    }
}