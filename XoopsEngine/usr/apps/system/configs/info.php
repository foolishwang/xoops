<?php
/**
 * System module config
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
 * @category        Xoops_Module
 * @package         System
 * @version         $Id$
 */

return array(
    'name'          => _SYSTEM_MI_MODULENAME,
    'description'   => _SYSTEM_MI_MODULEDESC,
    'version'       => "3.0.0 Alpha1",
    'email'         => "infomax@gmail.com",
    'author'        => "Taiwen Jiang <phppp@users.sourceforge.net>",
    'license'       => "GPL v2",
    'logo'          => 'resources/images/logo.png',

    'info'          => array(
        'translate'     => array(
                'adapter'   => 'legacy',
                'data'      => ''),
                ),

    //'onInstall'     => "App_System_Installer",
    //'onUpdate'      => "App_System_Installer",
    //'onUninstall'   => "App_System_Installer",

    'onInstall'     => "System_Installer",
    'onUpdate'      => "System_Installer",
    'onUninstall'   => "System_Installer",

    'extensions'    => array(
        'database'  => array(
            'sqlfile'   => array('mysql' => "sql/mysql.sql"),
        ),

        'event'         => "event.php",
        'block'         => "block.php",
        'acl'           => "acl.php",
        'page'          => "page.php",
        'navigation'    => "navigation.php",
        //'route'         => "route.ini",
        )
);