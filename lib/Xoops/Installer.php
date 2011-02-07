<?php
/**
 * XOOPS installer
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
 * @package         Xoops_Core
 * @version         $Id$
 */

class Xoops_Installer
{
    private static $instance;
    protected $installer = array();
    private $result;
    public $config;
    public $options;

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
    }

    protected function getInstaller($type = "app")
    {
        $type = ucfirst($type ?: "app");
        if (!isset($this->installer[$type])) {
            $class = 'Xoops_Installer_' . $type;
            $this->installer[$type] = new $class($this);
        }
        return $this->installer[$type];
    }

    public function __call($method, $args)
    {
        $name = array_shift($args);
        $options = empty($args) ? array() : array_shift($args);
        $this->options = $options;
        if (!$this->result = $this->process($method, $name)) {
            return false;
        }
        $status = true;
        foreach ($this->result as $action => $state) {
            if ($state['status'] === false) {
                $status = false;
                break;
            }
        }
        return $status;
    }

    public function getMessage($rawMessage = null)
    {
        $content = "";
        $message = is_null($rawMessage) ? $this->result : $rawMessage;
        if (empty($message)) {
            return $content;
        }
        foreach ($message as $action => $state) {
            $content .= "<p>";
            $content .= $action .": " . (($state['status'] === false) ? "failed" : "passed");
            if (!empty($state['message'])) {
                $content .= "<br />&nbsp;&nbsp;" . implode("<br />&nbsp;&nbsp;", $state['message']);
            }
            $content .= "</p>";
        }
        return $content;
    }

    private function process($action, $name)
    {
        $msgs = array();
        $type = XOOPS::service("module")->getType($name);
        $handler = $this->getInstaller($type == "legacy" ? "legacy" : "app");
        if (!is_callable(array($handler, $action))) {
            $msgs[] = "Installer '{$class}::{$action}' is not callable.";
            return $msgs;
        }

        $result = $handler->$action($name);

        XOOPS::persist()->clean();
        XOOPS::service('registry')->module->flush();
        XOOPS::service('registry')->modulelist->flush();
        if ($view = XOOPS::registry('view')) {
            $view->getEngine()->clearModuleCache($name);
        }

        if ($name != "system") {
            XOOPS::service('event')->trigger("module_" . $action, $name, "system");
        }
        return $result;
    }

    public function loadConfig($dirname)
    {
        if (!isset($this->config) || $this->config["dirname"] != $dirname) {
            $this->config = Xoops::service('module')->loadInfo($dirname, true);
        }
        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function loadExtension($extension, $module, $options = null, $oldVersion = null)
    {
        $dirname = is_string($module) ? $module : $module->dirname;
        //$class = "app_" . $dirname . "_installer_" . $extension;
        $class = $dirname . "_installer_" . $extension;
        if (!class_exists($class)) {
            $class = "Xoops_Installer_Module_" . ucfirst($extension);
            if (!class_exists($class)) {
               return false;
            }
        }
        if (is_null($options)) {
            $options = isset($this->config["extensions"][$extension]) ? $this->config["extensions"][$extension] : array();
        }
        $extensionHandler = new $class($options, $module, $oldVersion);
        $extensionHandler->setInstaller($this);
        return $extensionHandler;
    }
}