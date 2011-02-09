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
 * @package         Application
 * @version         $Id$
 */

class Xoops_Zend_Application extends Zend_Application
{
    protected $engine;

    /**
     * Constructor
     *
     * Initialize application. Potentially initializes include_paths, PHP
     * settings, and bootstrap class.
     *
     * @param  string                   $environment
     * @param  string|array|Zend_Config $options String path to configuration file, or array/Zend_Config of configuration options
     * @throws Zend_Application_Exception When invalid options are provided
     * @return void
     */
    public function __construct($environment = null, $options = null)
    {
        $this->_environment = (string) $environment;

        if (is_null($options)) {
            $bootfile = "application";
            $options = array();
        } elseif (is_array($options)) {
            if (array_key_exists("bootfile", $options)) {
                $bootfile = $options["bootfile"];
                unset($options["bootfile"]);
            }
        } elseif (is_string($options) && !empty($options)) {
            $bootfile = $options;
            $options = array();
        }
        $options = (array) $options;
        if (!empty($bootfile)) {
            $bootFile = XOOPS::path("var") . "/etc/bootstrap.{$bootfile}.ini";
            $options = array_merge($this->_loadConfig($bootFile), $options);
        }
        $this->setOptions($options);
    }

    /**
     * Set application options
     *
     * @param  array $options
     * @return Zend_Application
     */
    public function setOptions(array $options)
    {
        if (!empty($options['autoloader']) && is_object($options['autoloader'])) {
            $this->setAutoloader($options['autoloader']);
            unset($options['autoloader']);
        }
        if (!empty($options['config'])) {
            $options = $this->mergeOptions($options, $this->_loadConfig($options['config']));
            unset($options['config']);
        }
        if (!empty($options['bootstrap'])) {
            $bootstrap = $options['bootstrap'];
            if (is_string($bootstrap)) {
                $bootstrap['path'] = XOOPS::path("lib") . "/Xoops/Zend/Application/Bootstrap/" . ucfirst($bootstrap) . ".php";
                $bootstrap['class'] = "Xoops_Zend_Application_Bootstrap_" . ucfirst($bootstrap);
            } elseif (is_array($bootstrap)) {
                if (empty($bootstrap['path']) && !empty($bootstrap['class'])) {
                    $bootstrap['path'] = XOOPS::path("lib") . "/Xoops/Zend/Application/Bootstrap/" . ucfirst($bootstrap['class']) . ".php";
                    $bootstrap['class'] = "Xoops_Zend_Application_Bootstrap_" . ucfirst($bootstrap['class']);
                }
            }
            $options['bootstrap'] = $bootstrap;
        }

        $options_default = array(
            "bootstrap" => array(
                "path"  => XOOPS::path("lib") . "/Xoops/Zend/Application/Bootstrap/Bootstrap.php",
                "class" => "Xoops_Zend_Application_Bootstrap_BootStrap"
            ),
            /*
            "router" => "Xoops_Zend_Controller_Router_Application"
            */
        );
        $baseUrl = XOOPS::host()->get("baseUrl");
        if (!empty($options['resources']['frontController'])) {
            $options_default['resources']['frontController'] = array(
                "dispatcher"            => "application",
                "defaultModule"         => "default",
                "defaultControllerName" => "index",
                "defaultAction"         => "index",
                "baseurl"               => empty($baseUrl) ? '/' : $baseUrl,
                "params"                => array(
                    //"noViewRenderer"    => true,
                    // Set noErrorHandler to skip default Zend_Controller_Plugin_ErrorHandler so that we can use custom error handler
                    "noErrorHandler"    => true,
                ),
            );
        }

        $options = array_change_key_case($options, CASE_LOWER);
        $options_default = array_change_key_case($options_default, CASE_LOWER);
        $options = $this->mergeOptions($options_default, $options);

        return parent::setOptions($options);
    }

    /**
     * Load bootstrap class
     *
     * @param  string   $name   Bootstrap name
     * @return Zend_Application
     */
    public function loadBootstrap($name = null)
    {
        if (empty($name)) {
            $class = 'Xoops_Zend_Application_Bootstrap_BootStrap';
        } else {
            $class = 'Xoops_Zend_Application_Bootstrap_' . $name;
        }

        $this->_bootstrap = new $class($this);

        if (!$this->_bootstrap instanceof Zend_Application_Bootstrap_Bootstrapper) {
            throw new Zend_Application_Exception('Bootstrap class does not implement Zend_Application_Bootstrap_Bootstrapper');
        }

        return $this;
    }

    public function setAutoloader($autoloader)
    {
        $this->_autoloader = $autoloader;
        return $this;
    }

    public function setEngine($engine)
    {
        $this->engine = $engine;
        return $this;
    }

    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * Load configuration file of options
     *
     * Return from cached file if available, otherwise read from ini file and save to cache
     *
     * @param  string $file
     * @return array
     */
    protected function _loadConfig($file)
    {
        $persistKey = "config.bootstrap." . md5($file);
        if (!$config = XOOPS::persist()->load($persistKey)) {
            $config = parent::_loadConfig($file);
            if (!empty($config["resources"])) {
                foreach ($config["resources"] as $key => &$cfg) {
                    if (!is_array($cfg) || empty($cfg["config"])) {
                        continue;
                    }
                    $opt = Xoops_Config::load(XOOPS::path("var") . "/etc/resource." . $cfg["config"] . ".ini");
                    unset($cfg["config"]);
                    $cfg = array_merge($cfg, $opt);
                }
            }
            $status = XOOPS::persist()->save($config, $persistKey);
        }
        return $config;
    }
}