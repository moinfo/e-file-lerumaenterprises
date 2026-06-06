<?php


class User extends Entity
{
    var $table = 'users';
    var $ignore = ['updated_at'];

    public function add() {
//        $this->set('document_number', Utility::generateDocumentNumber($this->table, 'MCOMP'));
//        $this->set('entry_user', Utility::getLoggedInUser());
//        $this->set('entry_timestamp', date('Y-d-m H:i:s'));
        return parent::add();
    }

    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    public static function getUserGroups($user_id, bool $as_string, $include_delegations = false) {
        $today = date('Y-m-d');
        $query = "SELECT GROUP_CONCAT(user_group) as ids FROM user_group_relation WHERE user='$user_id'";
        $result = Utility::query($query, "SELECT", 1);
        $groups = $result['ids'] ?? '';
        if($include_delegations) {
            $query2 = "SELECT GROUP_CONCAT(group_id) as ids FROM delegations WHERE delegate_id ='$user_id' AND status='ACTIVE' AND (DATE('{$today}') BETWEEN start_date AND end_date)";
            $result2 = Utility::query($query2, "SELECT", 1);
            $delegated_groups = $result2['ids'] ?? '';
            $groups .= $delegated_groups;
        }
        return ($as_string) ? $groups : explode(",", $groups);
    }


    /**
     * This method introduces the future of user roles management within the system
     * The roll_keyword represents a role that the user CAN or CANNOT do (permissions)
     * Still under development
     * @param $role_keyword keyword of the role to check against
     * @param int $access This is an optional value that could store additional case of access for a particular individual
     * @return bool
     */
    public function can($role_keyword, $access = 1) {
        $query = "SELECT * FROM config_roles r
                 INNER JOIN config_role_access rs ON (rs.role_id = r.id)
                 WHERE r.keyword = ? AND rs.access = ?
                 AND ((rs.access_type = 'INDIVIDUAL' AND rs.user_id = ?)
                        OR (rs.access_type = 'GROUP' AND rs.user_id IN (SELECT user_group FROM user_group_relation WHERE user = ?))
                    )
        ";
        $db = new DB();
        $result = $db->query($query, 'SELECT', 'ALL', [$role_keyword, $access, $this->id, $this->id]);
        return is_array($result) && count($result) > 0;
        // TEMPORARILY We are using alternatives based on GROUPS.
        // TODO configure database to allow this
//        switch($role_keyword) {
//            case  'MAKE_PAYMENTS':
//                    return Utility::isFAM();
//                break;
//            case 'GENERATE_PAYMENT_VOUCHERS':
//                    return Utility::isADO() || Utility::isFAM();
//                break;
//            case 'ATTACH_PURCHASE_QUOTES':
//                return Utility::isGroupMember($this->id, 'STAFF'); // Temporary
//        }
    }

//    public function add() {
//        $this->set('date_registered', date('Y-m-d H:i:s'));
//        return parent::add();
//    }



    /**
     * This method checks role access for an individual and give an error message if access is denied
     * @param $role_keyword
     * @param int $access
     * @return bool
     */
    public function validateRoleAccess($role_keyword, $access = 1) {
        if($access = $this->can($role_keyword, $access)) {
            return true;
        } else {
            Utility::notify('You do not have access to '. strreplace('_', ' ', $role_keyword). '!' ,'error', 'Access Denied!');
            return false;
        }
    }


    public function getAvatar(){
        $profile_path  = "../allfiles/photos/";
        $default_avatar = './assets/images/default-avatar.jpg';
        if(!isset($this->id) || $this->get('id')){
            return $default_avatar;
        }
        $avatar = Utility::query("SELECT profile_image FROM {$this->table} WHERE id={$this->get('id')}", 'SELECT', 1);
        $avatar_file = is_array($avatar) ? $profile_path.$avatar['profile_image'] : null;
        $avatar = is_file($avatar_file) ? $avatar_file : $default_avatar;
        return $avatar;
    }

    /**
     * Returns list of Groups that the user exists
     * @param bool $ids_only
     * @return array|bool
     */
    public function getAssociatedGroups($ids_only = false) {
        $groups = Utility::query("SELECT * FROM user_groups WHERE id in (SELECT user_group FROM user_group_relation WHERE `user` = '{$this->get('id')}')", 'SELECT') ?? [];
        return $ids_only ? array_column($groups, 'id') : $groups;
    }

    /**
     * Get groups that the user is actively delegated to
     * @param bool $as_string
     * @return array|string
     */
    public function getActiveDelegatedGroups($as_string = false){
        $today = date('Y-m-d');
        $query = "SELECT GROUP_CONCAT(group_id) as ids FROM delegations WHERE delegate_id ='{$this->get('id')}' AND status='ACTIVE' AND (DATE('{$today}') BETWEEN start_date AND end_date)";
        $groups = Utility::query($query, 'SELECT', 1)['ids'] ?? '';
        return $as_string ? $groups : explode(',', $groups);
    }

    /**
     * Return if the user is set as a report_to for another user
     * @param $user_id
     * @return bool
     */
    public function isLineManagerFor($user_id) {
        return Utility::query("SELECT report_to FROM user WHERE id = '{$user_id}'", 'SELECT', 1)['report_to'] ?? null == $this->get('id');
    }

    public function isHodFor($user_id) {
        return Utility::isHOD($this->get('id')) && (Utility::isSameDepartment($user_id, $this->get('id')) || Utility::isSameDivision($user_id, $this->get('id'))); // TODO: Change it to work
    }


    public function isInGroup($group_id) {
        return in_array($group_id, $this->getAssociatedGroups(true), true);
    }

    //Get Total Acrued Days
    public function getAnnualLeaveTotalAccruedDays() {

    }

    //Get Department
    public function getDepartment() {
        return Utility::query("SELECT * FROM config_departments WHERE id={$this->department}", "SELECT", 1);
    }


    // STATIC

    public static function generateOtToken($user_id = null){
        return mt_rand(100000, 999999);
    }


    public static function generateEmployeeNumber() {
        $prefix = 'TAHA/HR/';
        $last = Utility::query("SELECT employee_number FROM user ORDER BY employee_number DESC", 'SELECT', 1)['employee_number'] ?? $prefix. '0000';
        $expl = explode("/", $last);
        $digit = (int) $expl[2];
        $next_digit = ($digit + 1);
        return $prefix . sprintf('%04d', $next_digit);
    }

    /**
     * Check if user is dormant
     * @return bool
     */
    public function isDormant() {
        return $this->get('status') == 'Dormant';
    }

    /**
     * Check if user is disabled
     * @return bool
     */
    public function isDisabled() {
        return $this->get('status') == 'Disabled';
    }
}