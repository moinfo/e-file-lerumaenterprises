<?php


class Menu extends Entity {
    public $table = 'menu';

    /**
     * The following two methods bellow work together as recursive functions for infinity menu levels
     * @param $menu
     * @param $user_groups string
     * @return string
     */
    public static function printMenu($menu,$active) {
        $content = '';
        $is_active = ($active == $menu['title']) ? 'active' : '';
        if ($menu['parent_menu'] == 0) {
            $content .= "<li class='nav-item {$is_active}'><a class='nav-link' href='{$menu['link']}'#'>{$menu['name']} <span class='sr-only'>()</span></a></li>";

        }
        return $content;
    }


    /**
     * Genenrate Menu based on the logged in user
     * @param $user_id
     * @param string $user_groups
     * @return string
     */
    public static function getUserMenu($user_id,$active) {
        $user = new User($user_id);
        $user_groups =  $user->getAssociatedGroups(true);
        $user_delegated_groups =  $user->getActiveDelegatedGroups(false);
        // Cast group ids to int — concatenated into an IN() clause below.
        $combined_groups = array_filter(array_map('intval', array_unique(array_merge($user_groups, $user_delegated_groups))));
        $user_groups_str = !empty($combined_groups) ? implode(',', $combined_groups) : '0'; // IN (0) matches nothing

        $user_groups = $user_groups ?? $_SESSION['pf.usergroup'];
        if ($user_groups == '') { die("</ul><strong style='color:#fff;'>You have not been assigned to any group</strong>"); }
        $parents = Utility::query("SELECT distinct(b.id) as id, parent_menu, name, link, icon,title, list_order, status FROM `config_access_rights` a, `menu` b 
                                WHERE  a.user_group IN ($user_groups_str) AND a.menu = b.id AND (b.parent_menu = 0 OR b.parent_menu IS NULL) AND status = 'ACTIVE' ORDER BY parent_menu, list_order ASC, name ASC");
        $menu_data = "";
        foreach ($parents as $key => $parent) {
            $menu_data .= self::printMenu($parent,$active);
        }
        return $menu_data;
    }

}
