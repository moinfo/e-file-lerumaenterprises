<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of router
 *
 * @author davidkagoma
 */
class Router {

    /**
     * Load a subpage
     * @param $page
     */
    public static function load($page){
        $file = "./pages/".$page.".php";
        $file = is_file($file)?$file:"./pages/404.php";
        include($file);
    }

    /**
     * Validate if a page can be accessed by the specified user/ user_group
     * @param $link
     * @param $user_id
     * @return bool
     */
    public static function validateAccess($link, $user_id){
        if(in_array($link,["404","dashboard","profile","profile.default","reports","notifications", "chat"], false)){ /// List of pages that are not to be validated
            return true;
        }

        $user = new User($user_id);
        $user_groups =  $user->getAssociatedGroups(true);
        $user_delegated_groups =  $user->getActiveDelegatedGroups(false);
        // Group ids are concatenated into an IN() clause — cast to int so they can't carry SQL.
        $combined_groups = array_filter(array_map('intval', array_unique(array_merge($user_groups, $user_delegated_groups))));
        if (empty($combined_groups)) { // no groups => no access (also avoids an invalid "IN ()")
            return false;
        }
        $user_groups_str = implode(',', $combined_groups);

        $parts = explode(".",$link);
        if(count($parts)>1){
           $root = array_slice($parts, 0, count($parts)-1);
           if(count($parts)>3){
             $root = array_slice($parts, 0, 2);
           }
            $root_link = implode(".",$root);
        } else {
            $root_link = $link;
        }

        // $link / $root_link derive from user input ($_GET['p']) — bind, never interpolate.
        $q = "SELECT a.* FROM config_access_rights as a, menu as m
                WHERE a.user_group IN({$user_groups_str}) AND a.menu = m.id AND
                (m.link LIKE ? OR m.link LIKE ? OR m.link LIKE ? OR m.link LIKE ?)";
        $params = ['%p=' . $link, '%p=' . $root_link, '%p=' . $root_link . '.%', '%p=' . $root_link . '&%'];
        $db = new DB();
        $access = $db->query($q, 'SELECT', 'ALL', $params) ?: [];

        return is_array($access) && count($access) > 0;
    }
}
