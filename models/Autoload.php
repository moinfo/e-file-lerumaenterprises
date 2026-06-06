<?php
    /**
     * A step towards modularization of the system
     * When we have too many classes, we should autoload them only when required
     * (by Ringo)
     */


/**
 * Checks the class in ./modules/entities first and then in ./modules/
 */
    spl_autoload_register(function ($class_name) {
        $cur_dir  = __DIR__;
        $root_dir = preg_replace('/modules/', '', $cur_dir);
        if (file_exists($root_dir . 'modules/entities/' . $class_name . '.php')) {
            require_once $root_dir . 'modules/entities/' . $class_name . '.php';
            return true;
        } else if (file_exists($root_dir . 'modules/' . $class_name . '.php')) {
            require_once $root_dir . 'modules/' . $class_name . '.php';
            return true;
        } else if (file_exists($root_dir . 'modules/traits/' . $class_name . '.php')) {
            require_once $root_dir . 'modules/traits/' . $class_name . '.php';
            return true;
        }
        return false;
    });
