<?php

// session_start();


class Utility
{

    /**
     *      Function for getting all url paremeters that
     *      have been passed via $_GET
     *
     */
    static function getUrlOptions()
    {

        $new_array = array_chunk($_GET, 1, true);
        $url_string = NULL;

        foreach ($new_array as $key => $value) {
            foreach ($value as $subkey => $subvalue) {
                $url_string .= $subkey . '=' . $subvalue . '&';
            }
        }
        $url_string = rtrim($url_string, '&');

        return $url_string;
    }

    /**
     *
     * Prepare data before inserting, the output of this function is expected to be a
     * clean insert statement. General assumption is that field names on post corresponds to
     * table columns on the database
     * @param type $table
     * @param type $post_array
     * @return string
     */
    public static function prepareInsertQuery($table, $post_array)
    {

        $query = "INSERT INTO $table SET ";

        foreach ($post_array as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) { continue; } // column name is an identifier, can't be bound
            // clearing quotes
            $value = self::clearQuotes($value);
            if ($value != null) {
                $query .= " $key ='$value',";
            }

        }

        $query = rtrim($query, ',');
        return $query;
    }

    /**
     *
     * @param type $table
     * @param type $id
     * @param type $post_array
     * @param type $id_column
     * @return type
     */
    public static function prepareUpdateQuery($table, $id, $post_array, $id_column = 'id')
    {

        $query = "UPDATE $table SET ";

        foreach ($post_array as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) { continue; } // column name is an identifier, can't be bound
            // clearing quotes
            $value = self::clearQuotes($value);
            if ($value != null) {
                $query .= " $key ='$value',";
            } else {
                $query .= " $key = NULL,";
            }
        }

        $query = rtrim($query, ',');

        $id = self::clearQuotes($id);
        $id_column = preg_match('/^[a-zA-Z0-9_]+$/', $id_column) ? $id_column : 'id';
        return $query . " WHERE " . $id_column . "='$id'";
    }

    /**
     * Inserts data into the specified table
     * @param string $table
     * @param array $array_data
     * @return int (id of the inserted row)
     */
    public static function insert($table, $array_data)
    {
        $q = Utility::prepareInsertQuery($table, $array_data);
        return Utility::query($q, "INSERT");
    }


    /**
     * Insert multiple rows into a table
     * @param $table
     * @param $columns
     * @param $data
     * @return bool
     */
    public static function bulkInsert($table, $columns, $data) {
        $q = "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES ';
        foreach ($data as $index => $datum) {
//            $q .= "('". implode("', '", $datum) . "')" . (($index + 1 < count($data)) ? ', ' : '');
            $line = "('". implode("', '", $datum) . "')" . (($index + 1 < count($data)) ? ', ' : '');
            $q .= str_replace("''", "NULL", $line);

        }
        return self::query($q, 'INSERT');
    }

    /**
     * Disable and enabling ONLY_FULL_GROUP_BY for GROUP BY in query
     */
    public static function disable_full_group_by_sql_mode($check=true){
        if(!$check){
            Utility::query("SET session sql_mode=(SELECT CONCAT('ONLY_FULL_GROUP_BY,',@@sql_mode))",'UPDATE');
        }else{
            Utility::query("SET session sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))",'UPDATE');
        }
    }

    /**
     * Updates a a row with a given key-value pair
     * @param String $table
     * @param int $id
     * @param Array $array_data
     * @return boolean
     */
    public static function update($table, $id, $array_data)
    {
        $q = Utility::prepareUpdateQuery($table, $id, $array_data);
        return Utility::query($q, "UPDATE");
    }

    public static function delete($table, $id, $id_column_name = 'id')
    {
        return Utility::query("DELETE FROM {$table} WHERE {$id_column_name} = '{$id}'", "DELETE");
    }

    /**
     * This functon sanitizes $_POST data received from forms so as to prevent sql errors/injections
     * It can handle both single strings or arrays of strings
     * It doesn't return a value, the changes are applied to the pointed variable directly
     * @param String/Array $data The to be sanitized
     * @return void
     */
    public static function sanitize(&$data) {
        if (is_array($data)) {
            foreach ($data as $key => &$item) {
                self::sanitize($item);
            } unset($item);
        } else {
            $db = Xcrud_db::get_instance();
            $data = mysqli_real_escape_string($db->getConnection(), $data);
        }
    }

    /**
     * Get the id of the currently logged in user (or pass true to get full user object)
     * @param bool $as_row
     * @return int|array
     */
    public static function getLoggedInUser($as_row = false)
    {
        if ($as_row) {
            return self::query("SELECT * FROM user WHERE id = " . $_SESSION['pf.id'], "SELECT", 1);
        } else {
            return $_SESSION['pf.id'];
        }
    }


    /**
     *
     * @param type $id
     * @return array
     */
    public static function userGroupsArray($id)
    {
        $user_group_id = Utility::selectAll('user_group_relation', ' user = ' . $id);
        $user_group_ids = array();
        foreach ($user_group_id as $group_id) {
            array_push($user_group_ids, $group_id['user_group']);
        }

        return $user_group_ids;
    }

    /**
     * Return user groups keywords
     * @param type $id
     * @return array
     */
    public static function userGroupskey($id)
    {
        $query = "SELECT GROUP_CONCAT(keyword) as keywords FROM user_groups WHERE id IN (SELECT user_group FROM user_group_relation WHERE user='$id')";
        $user_groups = self::query($query, 'SELECT', 1);
        return is_array($user_groups) && isset($user_groups['keywords']) ? explode(',', $user_groups['keywords']) : [];
    }




    public static function getTotalDays($date)
    {
        // $current_year = date($date);
        $total_days = DateTime::createFromFormat('Y-m-d', $date);
        $total_days = $total_days->format('z') + 1;
        return $total_days;
    }


    public static function getDaysDiff($date1, $date2)
    {
        $date1 = date_create($date1);
        $date2 = date_create($date2);
        $diff = date_diff($date1, $date2);
        $total = $diff->format("%R%a");
        return $total;
    }

    /**
     * Returns a row from table given its id
     * @param String $table
     * @param int $id
     * @return Array
     */
    public static function selectUniqueId($table, $id, $check_soft_deleted = false)
    {
        return self::query("select * from $table where id ='$id'", "SELECT", 1);
    }

    public static function selectParentMenu($table)
    {
        $query = "select * from $table where parent_menu<'1'";
        $db = Xcrud_db::get_instance();

        $db->query($query);
        $result_details = $db->result();
        // echo $result_details['name'];
        return $result_details;
    }

    public static function selectAll($table, $where = null)
    {
        $query = "select * from $table ";
        if ($where != null) {
            $query .= " WHERE " . $where;
        }
        $db = Xcrud_db::get_instance();
        $db->query($query);
        $result_details = $db->result();
        return $result_details;
    }

    public static function getLastRecord($table, $query_type = 'SELECT',$one_row= null, $where = null)
    {
        $query = "select * from $table ORDER BY id DESC LIMIT 1";
        if ($where != null) {
            $query .= " WHERE " . $where;
        }

        $db = Xcrud_db::get_instance();
        $db->query($query);
        if ($query_type == 'SELECT') {
            return $one_row ? $db->row() : $db->result();
        }
        $result_details = $db->result();
        return $result_details;
    }

    public static function selectUserToAddInGroup($table, $group_id)
    {
        $query = "select * from $table where id not in (select user from user_group_relation where user_group = '$group_id') ";
        $db = Xcrud_db::get_instance();

        $db->query($query);
        $result_details = $db->result();
        // echo $result_details['name'];
        return $result_details;
    }

    public static function nameToNumber($input = null)
    {
        $number = rand(1000, 9999);
        $filename = time() . $number;
        if ($input == null) {
            $result = $filename;
        } else {
            $myArray = explode(".", $input);
            $ext = end($myArray);
            $result = $filename . "." . $ext;
        }
        return $result;
    }

    public static function deleteById($table, $id)
    {
        $query = "DELETE FROM $table where id='$id'";
        return self::query($query, 'DELETE');
    }


    public static function getQuarter($date)
    {
        $month = strftime('%m', strtotime($date));
        $quarter = ceil($month / 3);
        return $quarter;
    }


    public static function isMel()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("MEL", $user_groups) || in_array("FAM", $user_groups)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isHOD()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array('HOD', $user_groups, true) || in_array('PRM', $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isPRM() { /// Program manager
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array('HOD', $user_groups, true) || in_array('PRM', $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isPO()
    {
        $user_groups = Utility::userGroupsKey($_SESSION['pf.id']);
        if (in_array('PO', $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isSO()
    {
        $user_groups = Utility::userGroupsKey($_SESSION['pf.id']);
        if (in_array('SO', $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isSUP()
    {
        $user_groups = Utility::userGroupsKey($_SESSION['pf.id']);
        if (in_array('SUP', $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isFAM() {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (count(array_intersect(['FAM', 'FDM', 'FMD'], $user_groups))) {
            return true;
        } else {
            return false;
        }
    }


    public static function isHRM() {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (count(array_intersect(['HR', 'HRM'], $user_groups))) {
            return true;
        } else {
            return false;
        }
    }

    public static function isCEO()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (count(array_intersect(['CEO'], $user_groups))) {
            return true;
        } else {
            return false;
        }
    }

    public static function isADMIN()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("ADMIN", $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isOO()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("OO", $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isADO() { // Administration Officer
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("ADO", $user_groups, true)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isCO()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("CO", $user_groups) || in_array("ED", $user_groups) || in_array("CEO", $user_groups)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isAUDITOR()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("AUDITOR", $user_groups)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isCDM()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("CDM", $user_groups)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isCRO()
    {
        $user_groups = self::userGroupsKey($_SESSION['pf.id']);
        if (in_array("CRO", $user_groups)) {
            return true;
        } else {
            return false;
        }
    }




    /**
     *   A quick helper for running basic insert and select queries in xCrud
     * @query_type SELECT/[INSERT/UPDATE/DELETE]
     */
    public static function query($query, $query_type = 'SELECT', $one_row = NULL) {
        $db = Xcrud_db::get_instance();
        $db->query($query);
        if ($query_type == 'INSERT') { $result =  $db->insert_id(); }
        else if ($query_type == 'SELECT') { $result = $one_row ? $db->row() : $db->result(); }
         else if ($query_type == 'DELETE' || $query_type == 'UPDATE') {  $result =  TRUE; }
         else {
             $result = $one_row ? $db->row() : $db->result();
         }

        /**
         * This section logs the queries in a table for audit trail
         */
        $LOG_QUERIES = TRUE;
        if (isset($_SESSION['pf.id']) && $LOG_QUERIES) {
            //Search the query to see if there is activity_report table anywhere on the query so as not to log the request
            $search_results = strpos($query, "activity_report");
            if ($query_type != 'SELECT' && $query_type != 'SELECTED' && $search_results === false) {
                $user_id = self::getLoggedInUser();
                $user_name = $_SESSION['pf.name'];
                $data_array = array('user_id' => $user_id, 'user_name' => $user_name, 'query' => $query);
                $data = json_encode($data_array);
                $data = urlencode($data);

                $pdb = Xcrud_db::get_instance();
                // escape() returns a quoted, escaped literal — $user_name comes from session data.
                $pdb->query("INSERT INTO logs (user_id, user_name, query_type, data) VALUES ("
                    . $pdb->escape($user_id) . ", "
                    . $pdb->escape($user_name) . ", "
                    . $pdb->escape($query_type) . ", "
                    . $pdb->escape($data) . ")");

            }
        }

        return $result;
    }

    /**
     *   Function for updating access for individual groups in the system
     *
     */
    public static function updateGroupAccess()
    {
        $menu_id = NULL;
        $group_id = $_POST['user_group'];
        unset($_POST['user_group']);
        unset($_POST['save']);

        $query = "INSERT INTO `config_access_rights` (`id`, `user_group`, `menu`, `submenu`) VALUES ";
        $new_array = array_chunk($_POST, 1, true);

        if (count($new_array) > 0) {

            foreach ($new_array as $key => $value) {

                foreach ($value as $subkey => $subvalue) {
                    $sk = explode('_', $subkey);
                    $menu_id = $sk[1];
                    $query .= "(NULL, '$group_id', '0', '$menu_id'),";
                }
            }

            $query = rtrim($query, ',');

            // 1:DELETE ALL ACCESS ENTRIES FOR THIS GROUP
            $db = Xcrud_db::get_instance();
            $db->query("DELETE FROM config_access_rights where user_group='$group_id'");

            //2: INSERT NEW RECORDS INTO THE DATABASE FOR THIS USER GROUP
            $db = Xcrud_db::get_instance();
            $db->query($query);

            if ($db->insert_id()) {
                $message = "Data was saved properly";
            } else {
                $message = "Error occured. Data was not saved. Inform system administrator";
            }
        } else {
            $message = "Nothing to save, since you did not select any access rights";
        }


        return "<div class='alert alert-error alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>$message</strong> 
                  </div>";
    }

    /**
     *   Inform whether the group has access to a particular menu or not
     *   - function return "checked" for menus that a group has access, and NULL for other
     */
    public static function groupAccessForMenuStatus($group_id, $menu_id)
    {
        $query = "SELECT * FROM config_access_rights where  user_group='$group_id' AND submenu ='$menu_id'";
        $db = Xcrud_db::get_instance();

        $db->query($query);
        $result = $db->result();

        if (count($result) > 0) {
            return 'checked';
        } else {
            return NULL;
        }
    }

    public static function getUserGroups($user_id, $as_string = true) {
        return User::getUserGroups($user_id, $as_string); // Should be eliminated later
    }

    public static function clearQuotes($string)
    {
        //        $string = str_replace("'", "'", $string); // This is'nt right
        $db = Xcrud_db::get_instance();
        $string = mysqli_real_escape_string($db->getConnection(), $string);
        return $string;
    }

    /**
     * In the system, financial numbers can be entered in a varied range of
     * formats, making it hard to do number formatting using standard php functions.
     * This function solves that.
     *
     * @param string $number
     * @return mixed|string|type
     */
    public static function putComma($number, $decimals = 0, $accounting = FALSE)
    {
        $number = self::clearMoney($number);
        $number = @number_format($number, $decimals);
        return $number;
    }

    /**
     * This function will return a linear array of details from the user table
     * @param type $user_id
     * @return bool
     */
    public static function getUserDetails($user_id)
    {
        return self::query("SELECT * FROM user WHERE id='" . $user_id . "'", "SELECT", 1);
    }


    /**
     * Returns a list of all staffs (with type 'STAFF')
     * @param string $status
     * @param bool $only_in_payroll Return Only staffs that currently get salary
     * @return array|null
     */
    public static function getStaff($status = "ACTIVE", $only_in_payroll = false)
    {
        $q = "SELECT u.* FROM user u WHERE u.id > 1 AND u.user_type = 'STAFF'";
        $status_query = $status == null ? " AND u.status = 'ACTIVE'" : " AND UPPER(u.user_status) = '{$status}'";
        $payroll_query = $only_in_payroll ? " AND id IN (SELECT staff FROM staff_salary WHERE (salary + arrears) > 0) " : "";

        $q .= $status_query . $payroll_query . " ORDER BY u.name";

        return self::query($q, "SELECT");

    }

    /**
     * Function to fill <select> options from a given array
     * @param $table
     * @param $field
     * @param null $unit
     * @return string
     * @author Ringle
     */
    public static function selectOptions($table, $field, $unit = NULL)
    {

        $options = Utility::selectAll($table);

        if ($unit != NULL) {
            $options = Utility::selectAll($table, ' unit=' . $unit);
        }

        $result = '';
        foreach ($options as $option) {
            $result = $result . '<option value="' . $option['id'] . '">' . $option[$field] . '</option>';
        }

        return $result;
    }

    public static function optDown($data, $value = "name", $key = "id", $description = null, $selected = null)
    {
        $res = "";
        if (is_array($data)) {
            if (is_array(reset($data))) {
                foreach ($data as $row => $item) {
                    $res .= '<option value="' . $item[$key] . '" ';
                    if ($selected != null) {
                        $xploaded = explode(",", $selected);
                        if (count($xploaded) > 0) {
                            $sel = $xploaded;
                        } else {
                            $sel = array($selected);
                        }
                        if (in_array($item[$key], $sel)) $res .= "selected ";
                    }
                    if ($description != null) {
                        $res .= 'title="' . $item[$description] . '" ';
                    }

                    $res .= '>' . $item[$value] . '</option>';

                }
            } else {
                foreach ($data as $row => $item) {
                    $res .= '<option value="' . $item . '" ';
                    if ($selected != null) {
                        $exploded = explode(",", $selected);
                        if (count($exploded) > 0) {
                            $sel = $exploded;
                        } else {
                            $sel = array($selected);
                        }
                        if (in_array($item, $sel)) $res .= "selected ";
                    }

                    $res .= '>' . $item . '</option>';
                }
            }

        }

        return $res;
    }

    /*
     * Function to prepare any date format so that it fits into mysq database DATE field
     * @params date
     * @author Ringle
     */

    public static function dateToDb($date)
    {
        $_date = new DateTime($date);
        return $_date->format('Y-m-d');
    }

    /**
     * Function to convert date format from mysql DATE field to any format
     * @params date, [format]
     * @param $date
     * @param string $format
     * @param bool $include_time
     * @return string
     * @throws Exception
     * @author Ringle
     */

    public static function dateFromDb($date, $include_time = false, $format = null) {
        $format = $format  ??  self::getSetting('DATE_FORMAT') ?? 'Y-m-d';
        if ($date == NULL) {
            return '-';
        }
        if($include_time) {
            $format .= ' H:i:s';
        }
        $_date = new DateTime($date);
        return $_date->format($format);
    }





    /**
     * (Special case) This returns an array | List of all assets accounts that should be included in calculating expenses (eg. Assets depreciation)
     * @param bool $as_string set true to return the id's as comma separated
     * @return array|string
     */
    public static function getAssetsExpenseAccounts($as_string = false)
    { // TODO this is more complicated than it looks ('AT COST' still needed)
        // PFMIS => //$depreciation_acc = Utility::query("SELECT id FROM config_charts_accounts WHERE account_name LIKE '%at Cost%' AND account_type = '1'",'SELECT');

        $parent_id = self::getAccountUsedAs('ASSETS_DEPRECIATION_PARENT');;
        $depreciation_acc = self::query("SELECT id FROM config_charts_accounts WHERE parent = '{$parent_id}'  OR parent IN (SELECT id FROM config_charts_accounts WHERE parent = '{$parent_id}')", 'SELECT');
        $accounts = array_column($depreciation_acc, 'id');
        return $as_string ? "'" . implode("', '", $accounts) . "'" : $accounts;

        /**
         * IN case we decide that all NON-CURRENT ASSET ACCOUNTS are used as expenses,
         */
//        $non_current_parent = self::getAccountUsedAs('NON_CURRENT_ASSETS_PARENT');
//        $non_current_asset_accounts = self::getAllChildAccountIds($non_current_parent);
//        return $as_string ? "'" . implode("', '", $non_current_asset_accounts) . "'" : $non_current_asset_accounts;
    }

    /**
     * This returns an array | List of all EXPENSE accounts that should be included in calculating expenses
     * ie. combining the Expense type with the asset depreciation accounts (above method)
     * @param bool $as_string set true to return the id's as comma separated
     * @return array|string
     */
    public static function getAllExpenseAccounts($as_string = false) {
        $EXPENSE_TYPE_ID = self::getExpenseTypeId();
        $asset_expense_accounts = self::getAssetsExpenseAccounts($as_string);
        if($as_string) {
            $expense_accounts = self::query("SELECT GROUP_CONCAT(id) as ids FROM config_charts_accounts WHERE account_type = '{$EXPENSE_TYPE_ID}'", "SELECT", 1)['ids'];
        } else {
            $expense_accounts = self::query("SELECT id FROM config_charts_accounts WHERE account_type = '{$EXPENSE_TYPE_ID}'", "SELECT");
            $expense_accounts = array_column($expense_accounts, 'id');
        }
        return $as_string ? implode(', ', [$expense_accounts, $asset_expense_accounts]) : array_merge($expense_accounts, $asset_expense_accounts);
    }

    /**
     * @return int
     */
    public static function getAssetsTypeId()
    {
        return 1; // TODO get from DB
    }

    public static function getLiabilitiesTypeId()
    {
        return self::query("SELECT id FROM config_account_types WHERE type IN ('LIABILITY', 'LIABILITIES')", 'SELECT', 1)['id'] ?? 5; // TODO get from DB
    }

    public static function getEquityTypeId()
    {
        return self::query("SELECT id FROM config_account_types WHERE type IN ('EQUITY', 'EQUITY AND RESERVE', 'TOTAL EQUITY')", 'SELECT', 1)['id'] ?? 5; // TODO get from DB
    }

    public static function getIncomeTypeId()
    {
        return self::query("SELECT id FROM config_account_types WHERE type IN ('INCOME', 'TOTAL INCOME', 'TOTAL REVENUE', 'REVENUE')", 'SELECT', 1)['id'] ?? 5; // TODO get from DB
    }

    public static function getExpenseTypeId()
    {
        return self::query("SELECT id FROM config_account_types WHERE type IN ('EXPENSE', 'EXPENDITURE', 'TOTAL EXPENSE', 'TOTAL EXPENDITURE')", 'SELECT', 1)['id'] ?? 5; // TODO get from DB
    }

    /**
     * This function returns an array of user's linked accounts
     * @param type $user_id ID of the user/staff
     * @param int|null $parent parent account in which the linking is done
     * @return array
     * @author Ringle
     */
    public static function getUserLinkedAccounts($user_id, $parent = null)
    {
        $q = "SELECT l.account_id FROM account_links AS l, config_charts_accounts AS c 
                WHERE  c.id = l.account_id AND l.staff_id = '{$user_id}' ";
        if (!is_null($parent)) {
            $q .= "AND c.parent = '{$parent}'";
        }
        $q .= 'ORDER BY c.code ';
        return self::query($q);
    }

    /**
     * This function will check if the specified user is allowed to access the current page based on access in db
     * @param type $user_id
     * @return bool
     * @author Ringle
     */
    public static function hasMenuAccessRight($user_id = null)
    {
        $user_id = ($user_id == null) ? $_SESSION['pf.id'] : $user_id;
        /**
         * @todo Check if the user is allowed to access this page [recursive]
         */
        return true;
    }


    /**
     * General function that is used for clearing currencies in the system
     * it takes an understanding that number can be written in so many different (wrong ways)
     * @param type $number
     * @return mixed|string|type
     */
    public static function clearMoney($number)
    {
        $dirts = array("'", "|", " ", ",", "/", "-");
        $number = str_replace($dirts, "", $number);

        if (is_numeric($number)) {
            return $number;
        } else {
            return '0';
        }
    }

    public static function reportTo($staff_id)
    {
        return (self::query("SELECT * FROM user WHERE id='$staff_id'", 'SELECT', 1)['report_to']);
    }

    public static function getDepartmentHead($staff_id)
    {
        return (self::query("SELECT * FROM user WHERE department IN(SELECT department FROM user WHERE id='$staff_id') AND id IN(SELECT user FROM user_group_relation WHERE user_group='3')", "SELECT", "1")['id']);
    }

    public static function money_format($number, $negative_brackets = true) {
        if ($number < 0 && $negative_brackets) {
            $number = ltrim($number, '-');
            $result = number_format($number, 2);
            $result = '(' . $result . ')';
        } else {
            $result = number_format($number, 2);
        }

        return $result;
    }

    /**
     * Deleting some data may cause referencing issues, safe delete allows for
     * deleting ONLY when there is no referencing data. Example, you should not be able
     * to delete an account from charts of account, if there are transactions in the system
     * that have been posted against that account.
     *
     * @param string $ref_table
     * @param string $ref_column
     * @param string $ref_value
     * @return array
     */
    public static function safeDelete($ref_table, $ref_column, $ref_value)
    {
        $check_result = Utility::query("SELECT * from $ref_table where $ref_column ='$ref_value'");
        if (count($check_result) > 0) {
            return array(
                'status' => FALSE,
                'message' => 'Sorry, this entry can not be deleted because it is referrenced else where in the system'
            );
        } else {
            // Its okay, we can delete now
            return array(
                'status' => TRUE,
                'message' => 'Entry can be deleted'
            );
        }
    }


    /**
     * Used to get different amount according the first day and second day rates
     *
     * @param double $amount
     * @param date('Y-m-d') $first_date
     * @param date('Y-m-d') $second_date
     * @param int $foreign_currency (id)
     * @return double difference in amount
     */

    public static function getExchangeLossGain($amount = 0, $first_date = null, $second_date = null, $foreign_currency = null) {
        $first_year = date_format(date_create($first_date), 'Y');
        $first_month = date_format(date_create($first_date), 'm');
        $first_rate = Utility::query("SELECT * FROM exchange_rate_monthly WHERE foreign_currency_id='$foreign_currency' AND base_currency_id IN (SELECT id FROM config_currencies WHERE is_base = 'YES') AND `year`<='$first_year' AND `month`<='$first_month' ORDER BY year DESC, month DESC", 'SELECT', '1')['rate'];

        $first_amount = $amount * $first_rate;


        $second_year = date_format(date_create($second_date), 'Y');
        $second_month = date_format(date_create($second_date), 'm');
        $second_rate = Utility::query("SELECT * FROM exchange_rate_monthly WHERE foreign_currency_id='$foreign_currency' AND base_currency_id IN (SELECT id FROM config_currencies WHERE is_base = 'YES') AND `year`<='$second_year' AND `month`<='$second_month' ORDER BY year DESC, month DESC", 'SELECT', '1')['rate'];

        $second_amount = $amount * $second_rate;

        return $first_amount - $second_amount;

    }


    /**
     * Used to get exchange amount to base currency
     *
     * @param double amount
     * @param int foreign_currency_ic
     * @param string date ('Y-m-d')
     * @return double exchange amount
     */
    public static function getExchangeAmount($amount = 0.0, $foreign_currency_id = null, $date = null)
    {
        $base_currency_id = Utility::query("SELECT * FROM config_currencies WHERE is_base='YES'", 'SELECT', 1)['id'];

        if ($base_currency_id == $foreign_currency_id) {
            return $amount;
        } else {
            $month = date_format(date_create($date), 'm');
            $year = date_format(date_create($date), 'Y');

            $rate = Utility::query("SELECT * FROM exchange_rate_monthly WHERE foreign_currency_id='$foreign_currency_id' AND base_currency_id='$base_currency_id' AND `year`<='$year' AND `month`<='$month' ORDER BY year DESC, month DESC", 'SELECT', '1')['rate'];

            return ($amount * $rate);
        }
    }


    /**
     * Used to get different amount according the first day and second day rates
     *
     * @param int request_id
     * @return boulean status
     */
    public static function lpoExchangeLossGain($id) {
        $lpo = new PurchaseOrder($id);
        if($lpo) {
            return $lpo->processLossAndGain();
        } else {
            return true;
        }
    }


    /**
     * Get a name from user's id
     * @param type $user_id
     * @return type
     */
    public static function nameFromId($user_id)
    {
        $r = self::query("SELECT name FROM user WHERE id='" . $user_id . "'", "SELECT", 1);
        return $r['name'];
    }

    public static function userGroupNameFromId($group_id)
    {
        $group = Utility::query("SELECT * FROM user_groups WHERE id = '$group_id'", "SELECT", 1);
        return $group['name'];
    }

    public static function userGroupIdFromKeyword($group_keyword)
    {
        $group = Utility::query("SELECT * FROM user_groups WHERE keyword = '$group_keyword'", "SELECT", 1);
        return $group['id'];
    }

    /**
     * Return the id of the charts of accounts given a code
     * @param type $account_code
     * @return
     */
    public static function accountIdFromCode($account_code)
    {
        $r = self::query("SELECT * FROM config_charts_accounts WHERE code='$account_code'", "SELECT", 1);
        return is_array($r) ? $r['id'] : false;
    }

    public static function accountIdFromName($account_name)
    {
        $r = self::query("SELECT * FROM config_charts_accounts WHERE account_name LIKE '%" . $account_name . "%'", "SELECT", 1);
        return is_array($r) ? $r['id'] : false;
    }

    /**
     * Check if a user belongs to a certain user group
     *
     * @param int $user_id
     * @param type $group_keyword
     * @return boolean
     */
    public static function isGroupMember($user_id, $group_keyword)
    {
        $query = "SELECT * from user_groups where keyword ='$group_keyword'";
        $group_details = Utility::query($query, "SELECT", TRUE);
        $group_id = $group_details['id'];

        $user_groups = explode(',', $_SESSION['pf.usergroup']);
        if (array_search($group_id, $user_groups)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }


    /**
     * Check the strength of the current password against
     * set criteria for strong password.
     *
     * @param type $pwd
     * @param type $old_pass
     * @return type
     */

    public static function checkPassword($pwd, $old_pass = NULL, $pass_compare = FALSE)
    {
        $errors = NULL;

        if ($pass_compare === TRUE) {
            if ($pwd == $old_pass && strlen($pwd) > 2) {
                $errors .= "<li>You new password should be different from the old one.</li>";
            }
        }

        $accepted_pass_length = self::query("SELECT meta_value from config_meta where meta_key = 'PASSWORD_LENGTH'", "SELECT", TRUE)['meta_value'];

        if (strlen($pwd) < $accepted_pass_length) {
            $errors .= "<li>Password too short, the length should be atleast <strong>$accepted_pass_length</strong> characters</li>";
        }

        if (!preg_match("#[0-9]+#", $pwd)) {
            $errors .= "<li>Password must include at least one number!</li>";
        }

        if (!preg_match("#[a-z]+#", $pwd)) {
            $errors .= "<li>Password must include at least one lower case letter!</li>";
        }
        if (!preg_match("#[A-Z]+#", $pwd)) {
            $errors .= "<li>Password must include at least one upper case letter!</li>";
        }


        if (is_null($errors)) {
            return $errors;
        } else {
            return ('<ol>' . $errors . '</ol>');
        }

    }

    /**
     * Function for login all user attempts to login into the system
     * @param type $username
     * @return last insert id
     */
    public static function loginAttempts($username, $matching_user_id = NULL, $login_success = FALSE)
    {
        // Record the login attempt into the database
        $user_id = (!is_null($matching_user_id)) ? $matching_user_id : '';
        $insert_id = self::query("INSERT INTO security_login_attempts SET user_name = '$username', login_success='$login_success', event_timestamp = NOW(), matching_user_id='$user_id'", "INSERT");

        if (!is_null($matching_user_id) and $login_success == FALSE) {
            // 1: Get allowed period for checking failures
            // 2: Get how many attempt are permitted in the "allowed period" for failed  
            // Check how many times this account has failed to login, in the last "allowed period"
        }

    }

    /**
     * Get meta configuration key from
     * the database
     * @param type $meta_key
     */
    public static function getMetaValue($meta_key)
    {
        return Utility::query("SELECT meta_value from config_meta where meta_key ='$meta_key'", "SELECT", TRUE)['meta_value'];
    }


    public static function runRemainder($staff_id)
    {

        //**** Activity Report Remainder *****

        $reports = Utility::query("SELECT * FROM activity_report WHERE entry_user='$staff_id' AND submitted_status='NO'", 'SELECT');
        foreach ($reports as $report) {
            $request_id = $report['request_id'];
            $request = Utility::selectUniqueId('config_activity_request', $request_id);
            $report_date = $request['expected_report_date'];
            $today = date('Y-m-d');
            $remain_days = Utility::getDaysDiff($today, $report_date);

            if ($remain_days <= 4 && $remain_days >= 0) {
                Notification::notifyUser($staff_id, 'REMINDER - Activity Report', 'We are reminding you to submit your activity report: ' . $remain_days . ' day(s) remaining!', '?p=operations.activity.report.edit&id=' . $report['id']);
            }


            //**** END of Activity Report Remainder *****

        }


    }


    public static function generateDocumentNumber($table, $document, $column = "document_number")
    {
        $last = Utility::query("SELECT " . $column . " FROM " . $table . " WHERE " . $column . " != '' ORDER BY id DESC", "SELECT", 1);
        if (!is_array($last) || count($last) == 0) {
            $number = strtoupper($document) . "/" . date("Y") . "/0";

        } else {
            $number = $last[$column];
        }
        $expl = explode("/", $number);
        $digit = $expl[2];
        $year = $expl[1];
        $next_digit = ($digit + 1);
        if ($year != date('Y')) {
            $next_digit = 1;
        }
        $next_number = strtoupper($document) . "/" . date("Y") . "/" . $next_digit;
        return $next_number;
    }

    public static function generateBillNumber(){
        $num = md5(uniqid(rand(), true));
        $hash = substr(md5($num), 0, 8);
        return $hash;
    }

    public static function getDocumentNumber($journal, $transaction_type)
    {
        $document_tables = [
            [
                "transaction_type" => "RCPT",
                "table" => "receipts",
                "document_column" => '',
                "link" => "?p=finance.receipt.view&id=",
                "journal_ids" => ''
            ],
            [
                "transaction_type" => "IMP",
                "table" => "config_activity_request",
                "document_column" => "document_number",
                "link" => "?operations.imprest&opt=view&id=",
                "journal_ids" => ''
            ],
        ];

    }

    public static function separateInBox($string)
    {
        if ($string == null || $string == '') {
            $result = '';
        } else {
            $string_array = str_split($string);
            $result = '<table border="1" style="border-colapse: colapse;"><tr>';
            foreach ($string_array as $key => $char) {
                $result .= '<td width="24px">' . $char . '</td>';
            }
            $result .= '</tr></table>';
        }

        return $result;
    }

    public static function separateTIN($tin = null)
    {

        if ($tin == null || $tin == '') {
            $result = '<table border="1" style="border-colapse: colapse;"><tr><td width="20px">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px" style="background-color: black;">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px" style="background-color: black;">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px">&nbsp;</td><td width="20px">&nbsp;</td></tr></table>';
        } else {
            $tin_array = explode('-', $tin);
            $group_count = count($tin_array);
            $result = '<table border="1" style="border-colapse: colapse;"><tr>';
            foreach ($tin_array as $group_key => $tin_group) {
                $tin_group_array = str_split($tin_group);
                foreach ($tin_group_array as $number_key => $tin_number) {
                    $result .= '<td width="20px">' . $tin_number . '</td>';
                }
                if ($group_count > 1) {
                    $result .= '<td width="20px" style="background-color: black;">&nbsp;</td>';
                    $group_count--;
                }
            }
            $result .= '</tr></table>';
        }

        return $result;
    }

    /**
     * This function works as the String.Format() in C and C++. It replaces indices with values from an array
     * @return string
     */
    public static function format($string, $replacer)
    {
        $args = func_get_args();
        if (count($args) == 1) {
            return $args[0];
        }
        $str = array_shift($args);
        @$str = preg_replace_callback('/\\{(0|[1-9]\\d*)\\}/', create_function('$match', '$args = ' . var_export($replacer, true) . '; return isset($args[$match[1]]) ? $args[$match[1]] : $match[1];'), $str);
        return $str;
    }

    /**
     * Get current Strategic Plan (id) based on configuration within SESSION
     * @return mixed
     */
    public static function getCurrentSp()
    {
        return $_SESSION['pf.current_sp'];
    }

    /**
     * This function return the full year from year id
     * @param type $year_id
     * @return year (Numerical year)
     */
    public static function getYearFromId($year_id)
    {
        $res = Utility::query("SELECT year_description as `year` FROM config_sp_years WHERE id = '" . $year_id . "'", "SELECT", 1);
        return $res['year'];
    }

    public static function getYearFromCount($year_count)
    {
        $res = Utility::query("SELECT year_description as `year` FROM config_sp_years WHERE year_count = '" . $year_count . "'", "SELECT", 1);
        return $res['year'];
    }

    /**
     * Get the current year (row) for the current SP
     * @param null $sp_id
     * @return bool
     */
    public static function currentYear($sp_id = null) {
        $numeric_year = date('Y');
        if ($sp_id == null) {
            $sp_id = Utility::getCurrentSp();
        }
        return $res = Utility::query("SELECT * FROM config_sp_years WHERE sp_id = '{$sp_id}' AND year_description = '{$numeric_year}'", "SELECT", 1);
    }

    public static function getYearId($numeric_year, $sp_id)
    {
        $yr = self::query("SELECT id from config_sp_years WHERE sp_id = '$sp_id' AND year_description = '$numeric_year'", "SELECT", 1);
        if (is_array($yr) && count($yr) > 0) {
            return $yr['id'];
        } else {
            return false;
        }
    }

    public static function getSpYears($sp_id, $assoc = false)
    {
        $q = Utility::query("SELECT * FROM config_sp_years WHERE sp_id = '" . $sp_id . "' ORDER BY year_description ASC");
        if ($assoc) {
            $arr = array();
            foreach ($q as $key => $year) {
                $arr[$year['id']] = $year['year_description'];
            }
            return $arr;
        } else {
            return $q;
        }

    }
    ///////////

    /**
     * Returns full name (Code : Account_name) of the account whose id is specified
     * @param int $account_id
     * @return String
     */
    public static function fullAccountName($account_id)
    {
        $ac = self::query("SELECT * FROM config_charts_accounts WHERE id= '{$account_id}'", "SELECT", 1);
        return (is_array($ac) && count($ac) > 0) ? ($ac['code'] . ": " . $ac['account_name']) : "Unnamed account";
    }


    public static function getAllChildAccountIds($account_id, $same_account_type = false)
    {
        if ($same_account_type) {
            $ac = self::query("SELECT * FROM config_charts_accounts WHERE id ='$account_id'", "SELECT", 1);
            $type_exception = " AND account_type = '{$ac['id']}'";
        } else {
            $type_exception = "";
        }

        $ids = self::query("SELECT GROUP_CONCAT(id) as ids FROM config_charts_accounts WHERE parent = '{$account_id}'" . $type_exception, "SELECT", 1)['ids'];
        if ($ids == '') return [];
        $ids_arr = explode(",", trim($ids));
        $child_ids = (count($ids_arr) == 0 || $ids == '') ? '' : self::query("SELECT GROUP_CONCAT(id) as ids FROM config_charts_accounts WHERE (parent IN ($ids) OR parent IN (SELECT id FROM config_charts_accounts WHERE parent IN ($ids) " . $type_exception . "))" . $type_exception, "SELECT", 1)['ids'];
        $res = $ids_arr;
        if (count($ids_arr) && $child_ids && $child_ids != '') {
            $child_ids_arr = explode(",", trim($child_ids));
            $res = array_merge($ids_arr, $child_ids_arr);
        }
        return $res;
    }

    /**
     * Function to return all accounts that are children or grand children of a specific type of account parent
     * @param type $type_id The type id of account as in the config_account_types table
     * @return array
     */
    public static function getChildAccountsByType($type_id)
    {
        $q = "SELECT * FROM config_charts_accounts as c WHERE (parent = '0' AND account_type = '$type_id') "
            . "OR parent IN (SELECT id FROM config_charts_accounts as c WHERE (parent = '0' AND account_type = '$type_id')) "
            . "OR parent IN (SELECT id FROM config_charts_accounts WHERE parent IN (SELECT id FROM config_charts_accounts as c WHERE (parent = '0' AND account_type = '$type_id'))) ";
        $res = Utility::query($q);
        return $res;
    }

    /**
     * Returns an array of all child accounts of specified account
     * @param int $account_id ID of the parent account
     * @param null|int|array $level optionally specify which level should be returned [null = all levels, 0 = children, 1 = grand children ... ] or array of levels ([0, 1])
     * @return Array
     */
    public static function getChildAccounts($account_id, $level = null) // Todo implement another argument($child_only) to get only accounts with no children
    {
        $r = [];
        $queries = [
            0 => "SELECT * FROM config_charts_accounts WHERE parent = '$account_id' ",
            1 => "SELECT * FROM config_charts_accounts WHERE parent IN (SELECT id FROM config_charts_accounts WHERE parent = '$account_id')",
            2 => "SELECT * FROM config_charts_accounts WHERE parent IN (SELECT id FROM config_charts_accounts WHERE parent IN ( SELECT id FROM config_charts_accounts WHERE parent = '$account_id'))"
        ];
        if (is_null($level)) { // return all levels
            $r = "SELECT * FROM config_charts_accounts WHERE parent = '$account_id' OR parent IN (SELECT id FROM config_charts_accounts WHERE parent = '$account_id')";
        } else if (is_numeric($level) && array_key_exists($level, array_keys($queries))) {
            $r = $queries [$level];
        } else if (is_array($level) && !empty($intersection = array_intersect($level, array_keys($queries)))) {
            $r = '(' . implode(') UNION (', array_intersect_key($queries, $intersection)) . ')';
        } else {
            $r = "SELECT * FROM config_charts_accounts WHERE parent = '$account_id' OR parent IN (SELECT id FROM config_charts_accounts WHERE parent = '$account_id'";
        }
        $r = self::query($r, 'SELECT');
        return is_array($r) ? $r : [];
    }

    /**
     * This function returns a select list options of chart of accounts (optionally under a particular parent)
     *
     * @param int $selected [optional] id of account that will be selected by default
     * @param int $parent [optional] id of parent account whose children are to be returned
     * @param boolean $include_parent [optional] If this is set true, the parent account is listed as one of its children
     * @param bool $include_sub_children
     * @return string
     */
    public static function optdownAccounts($selected = null, $parent = 0, $include_parent = false, $include_sub_children = true)
    {
        $coa_levels = self::getSetting('COA_LEVELS');
        $r = self::query("SELECT * FROM config_charts_accounts WHERE parent = '$parent'  ORDER BY code ASC");
        $html = "";
        foreach ($r as $key => $account) {
            $id = $account['id'];
            $html .= '<optgroup label="' . $account['account_name'] . '">';
            if ($include_parent) {
                $sel = ($selected == $account['id']) ? "selected" : "";
                $html .= '<option value="' . $account['id'] . '" ' . $sel . '>' . $account['code'] . ': ' . $account['account_name'] . '</option>';
            }
            $coa_4_str = $coa_levels > 3 ?  "OR c2.parent IN (SELECT id FROM config_charts_accounts c3 WHERE c3.parent = '$id' )" : '';
            $children = Utility::query("SELECT * FROM config_charts_accounts WHERE parent='$id' OR parent in (SELECT id FROM config_charts_accounts c2 WHERE c2.parent = '$id' {$coa_4_str})");
            foreach ($children as $c_key => $child) {
                $sel = ($selected == $child['id']) ? "selected" : "";
                $html .= '<option value="' . $child['id'] . '" ' . $sel . '>' . $child['code'] . ': ' . $child['account_name'] . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    }

    /**
     * List <options> for Locations by grouping them by Region, District ...
     */
    public static function optDownLocations($parent = null, $selected = null){
        $parent_q = $parent ? " AND parent_id = '{$parent}'" : "";
        $regions = self::query("SELECT * FROM config_locations WHERE type = 'REGION' {$parent_q}", 'SELECT') ?? [];
        $districts = self::query("SELECT * FROM config_locations WHERE type = 'DISTRICT' {$parent_q}", 'DISTRICT') ?? [];
        $wards = self::query("SELECT * FROM config_locations WHERE type = 'WARD' {$parent_q}", 'WARD') ?? [];

        $types = ['Regions' => $regions, 'Districts' => $districts, 'Wards' => $wards];
        $html = "";
        foreach ($types as $type_name => $locations) {
            $html .= "<optgroup label='{$type_name}'>";
            foreach ($locations as $index => $location) {
                $selected_txt = $selected == $location['id'] ? ' selected' :'';
                $html .= "<option value='{$location['id']} {$selected_txt}}>{$location['name']}</option>";
                }
            $html .= '</optgroup>';
        }
        return $html;
    }




    /**
     * This function returns a select list options of chart of accounts (optionally under a particular parent)
     *
     * @param int $selected [optional] id of account that will be selected by default
     * @param int $parent [optional] id of parent account whose children are to be returned
     * @param boolean $include_parent [optional] If this is set true, the parent account is listed as one of its children
     * @param bool $include_sub_children
     * @return string
     */
    public static function optdownExpensableAccounts($selected = null, $parent = 0, $include_parent = false, $include_sub_children = true)
    {
        $expense_type = self::getExpenseTypeId();
        $asset_type = self::getAssetsTypeId();
        $coa_levels = self::getSetting('COA_LEVELS');
        $r = self::query("SELECT * FROM config_charts_accounts WHERE parent = '$parent' AND account_type IN ($expense_type, $asset_type)  ORDER BY code ASC");
        $html = "";
        foreach ($r as $key => $account) {
            $id = $account['id'];
            $html .= '<optgroup label="' . $account['account_name'] . '">';
            if ($include_parent) {
                $sel = ($selected == $account['id']) ? "selected" : "";
                $html .= '<option value="' . $account['id'] . '" ' . $sel . '>' . $account['code'] . ': ' . $account['account_name'] . '</option>';
            }
            $coa_4_str = $coa_levels > 3 ?  "OR c2.parent IN (SELECT id FROM config_charts_accounts c3 WHERE c3.parent = '$id' )" : '';
            $children = Utility::query("SELECT * FROM config_charts_accounts WHERE parent='$id' OR parent in (SELECT id FROM config_charts_accounts c2 WHERE c2.parent = '$id' {$coa_4_str})");
            foreach ($children as $c_key => $child) {
                $sel = ($selected == $child['id']) ? "selected" : "";
                $html .= '<option value="' . $child['id'] . '" ' . $sel . '>' . $child['code'] . ': ' . $child['account_name'] . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    }

    /**
     * This function will help to isolate the form resubmission problem
     * Make sure you have an input called "token" in your form with a unique value.
     * The function should be called once and the answer used as needed
     * Check the function before you execute the submitted form
     * @return boolean
     */
    public static function isNewSubmit()
    {
        if (isset($_SESSION['token'])) {
            if (isset($_POST['token'])) {
                if ($_SESSION['token'] == $_POST['token']) {
                    return false;
                } else {
                    $_SESSION['token'] = $_POST['token'];
                    return true;
                }
            } else {
                return false;
            }
        } else {
            $_SESSION['token'] = (isset($_POST['token'])) ? $_POST['token'] : null;
            return true; //just to avoid errors
        }
    }

    /**
     * Retuns true if the two specified users are in the same department
     * @param int $user_1
     * @param int $user_2
     * @return boolean
     */
    public static function isSameDepartment($user_1, $user_2)
    {
        $check = self::query("SELECT id FROM `user` WHERE id='$user_1' AND department IN(SELECT department FROM user WHERE id='$user_2')", 'SELECT')??[];
        return (count($check) > 0);
    }


    /**
     * Retuns true if the two specified users are in the same division
     * @param int $user_1
     * @param int $user_2
     * @return boolean
     */
    public static function isSameDivision($user_1, $user_2) {
        $data = self::query("SELECT d.division_id FROM `user` u INNER JOIN config_departments d ON (d.id = u.department) WHERE u.id IN ($user_1, $user_2)") ?? [];
        return count($data) && reset($data)['division_id'] && $data[1]['division_id'] === $data[0]['division_id'];
    }

    /**
     * Function to redirect to another page after output has been printed [HTML]
     * @param type $url
     * @param type $time before redirecting
     */
    public static function redirectTo($url, $time = 0)
    {
        echo '<meta http-equiv="refresh" content="' . $time . ';url=' . $url . '">';
    }

    //@todo: modify the function to work properly
    public static function notify($text, $type = 'success', $title = null, $buttons = 1)
    {
        if ($title === null) {
            echo '<script>swal.fire("' . $text . '"); </script>';
        } else {
            if(is_null($buttons)) {
                echo '<script>swal.fire({html:true, text:"' . $text . '",title:"' . $title . '",type:"' . $type . '", allowOutsideClick: true, showConfirmButton: false}); </script>';
            }else{
                echo '<script>swal.fire({html:true, text:"' . $text . '",title:"' . $title . '",type:"' . $type . '", showConfirmButton: true}); </script>';
            }

        }
    }


    /**
     * Displays a full page notification based on sweetalert interface
     * @param String $text
     * @param String $subtext
     */
    public static function errorPage($text, $subtext = "")
    {
        $html = '<div>
            <div class="sweet-alert showSweetAlert visible" data-custom-class="" data-has-cancel-button="false" data-has-confirm-button="false" data-allow-outside-click="false" data-has-done-function="true" data-animation="pop" data-timer="null" style="display: block; margin-top: -181px; z-index:1 !important;"><div class="sa-icon sa-error" style="display: none;">
            <span class="sa-x-mark">
              <span class="sa-line sa-left"></span>
              <span class="sa-line sa-right"></span>
            </span>
          </div><div class="sa-icon sa-warning" style="display: none;">
            <span class="sa-body"></span>
            <span class="sa-dot"></span>
          </div><div class="sa-icon sa-info" style="display: block;"></div><div class="sa-icon sa-success" style="display: none;">
            <span class="sa-line sa-tip"></span>
            <span class="sa-line sa-long"></span>

            <div class="sa-placeholder"></div>
            <div class="sa-fix"></div>
          </div><div class="sa-icon sa-custom" style="display: none;"></div><h3>' . $text . '</h3>
          <p style="display: block;">' . $subtext . '</p>
          <fieldset>
            <input type="text" tabindex="3" placeholder="">
            <div class="sa-input-error"></div>
          </fieldset><div class="sa-error-container">
            <div class="icon">!</div>
            <p>Not valid!</p>
          </div><div class="sa-button-container">
          </div></div></div>';
        echo $html;
    }


    public static function annualExchangeRate($year = null, $currency = 2, $base_currency = 1)
    {
        $year = (null == $year) ? date('Y') : $year;
        $res = self::query("SELECT * FROM exchange_rate_annual WHERE foreign_currency_id='$currency' AND base_currency_id = '$base_currency' AND year='$year' ORDER BY entry_timestamp DESC", "SELECT", 1);
        $fallback = 2200;
        return (is_array($res) && count($res) > 0) ? $res['rate'] : $fallback;
    }

    /**
     * Get the exchange rate for the specified month and year (default is the current one)
     * @param null $month
     * @param null $year
     * @param int $currency
     * @param int $base_currency
     * @return int
     */
    public static function monthlyExchangeRate($month = null, $year = null, $currency = 2, $base_currency = 1)
    {
        $month = (null == $month) ? date('m') : $month;
        $year = (null == $year) ? date('Y') : $year;
        $res = self::query("SELECT * FROM exchange_rate_monthly WHERE foreign_currency_id='$currency' AND base_currency_id = '$base_currency' AND month='$month' AND year = '$year' ORDER BY entry_timestamp DESC", "SELECT", 1);

        if (is_array($res) && count($res) > 0) {
            return $res['rate'];
        } else {
            $fallback = self::query("SELECT MAX(year), rate FROM exchange_rate_annual WHERE foreign_currency_id='$currency' AND base_currency_id = '$base_currency' AND year <= $year GROUP BY rate", "SELECT", 1)['rate'];
            return $fallback;
        }

    }


    /**
     *
     *
     * @param $foreign_currency_id
     * @return int
     */
    public static function getCurrentExchangeRate($foreign_currency_id, $transaction_date = NULL)
    {
        if ($transaction_date == NULL) {
            $month = date('m');
            $year = date('Y');
        } else {
            $month = date("m", strtotime($transaction_date));
            $year = date("Y", strtotime($transaction_date));
        }

        $query = "SELECT rate from  exchange_rate_monthly where foreign_currency_id ='$foreign_currency_id' AND `month` = '$month' AND `year` = '$year'";

        $rate = Utility::query($query, "SELECT", TRUE);
        if ($rate == NULL) {
            return 1;
        } else {
            return $rate['rate'];
        }
    }

    /**
     * Returns amount in words
     * @param number $amount
     * @return String
     */
    public static function amountInWords($amount)
    {
        $ex = explode(".", $amount);
        $words = "";
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        if (count($ex) > 1) {
            $words = $f->format($ex[0]);
            if (intval($ex[1]) > 0) {
                $cents = $f->format($ex[1]);
                $words .= " and " . $cents . " cents";
            }
        } else {
            $words = $f->format($ex[0]);
        }
        return ucfirst($words);
    }


    //////////////// PV callback

    /**
     * This function is called whenever a payment voucher has been paid
     * @param String $document_type abbreviated name of the document type
     * @param int $pv_id The id of the payment voucher entry
     * @param int $document_id the id of the referenced document/request
     */
    public static function pvPaidCallback($document_type, $pv_id, $document_id) {
        switch ($document_type) {
            case "LPO":
                $exch = self::lpoExchangeLossGain($document_id);
                if ($exch) {
//                    echo "<script>toastr['success']('Exchange Loss and gain recorded!')</script>";
                } else {
                    echo "<script>toastr['error']('Failed to record exchange loss and gain!')</script>";
                }
                break;
            case "LON":
                   $loan = new Loan($document_id);
                   $loan->set('status', 'ACTIVE');
                   $loan->update();
                break;
            default:
                break;
        }
    }

    public static function showFile($full_path, $mask = NULL)
    {
        if (strlen($full_path) < 3) {
            return '';
        } else {
            $path_array = explode('/', $full_path);
            $file_name = end($path_array);
            $basear = explode("_", $file_name);
            $file_name = end($basear);
            if ($mask != NULL) {
                return "<a target='_blank' class='btn btn-xs btn-success' title='Click to see the file' href='$full_path'>$mask</a>";
            } else {
                return "<a target='_blank' title='Click to see the file' href='$full_path'>$file_name</a>";
            }
        }
    }

    /**
     * @param $type "These are like "levels/stage" of a sort, e.g. decision,approvals,justify,quotes,print"
     * @return associative_array of purchase requests
     *
     * @author David Kagoma
     * @todo Improve this function that it can be dynamic and be used to replace other occurrence of similar computations.
     * - At the moment in the part of decision this function deals with approvals
     * that are of cost_range > 1 only. The piece of code should be improved to accommodate cost_range = 1
     *
     */
    public static function myPendingProcurements($type = 'decision')
    {


        if ($type == 'decision') {
            // Get my user groups
            $my_groups = explode(',', $_SESSION['pf.usergroup']);

            $decision_array = [
                ['group_id' => '3', 'procurement_level' => '3d'],
                ['group_id' => '5', 'procurement_level' => '5d'],
                ['group_id' => '6', 'procurement_level' => '6d'],
                ['group_id' => '7', 'procurement_level' => '7d']
            ];

            $proc_access_level = NULL;
            foreach ($my_groups as $i => $v) {
                if (array_search($v, array_column($decision_array, 'group_id')) !== FALSE) {
                    $res = array_search($v, array_column($decision_array, 'group_id'));
                    $proc_access_level .= "'" . $decision_array[(int)$res]['procurement_level'] . "',";
                }
            }
            if (!is_null($proc_access_level)) {
                $proc_access_level = rtrim($proc_access_level, ',');


                $query = "SELECT *
                    FROM purchase_request
                    WHERE id IN (SELECT request_id
                                 FROM purchase_request_approval
                                 WHERE status = 'submitted'
                                   AND level = '7')
                      AND id NOT IN (SELECT request_id FROM approved_quotes)
                      AND id  IN (
                        SELECT request_id FROM purchase_request_approval
                        WHERE level IN ($proc_access_level) AND status IS NULL
                        )
                      AND is_deleted = 'NO'
                    ORDER BY entry_date DESC";

                return Utility::query($query);
            } else {
                /**
                 * This is applicable for those users who do not have access
                 * to any approval rights in procurement section
                 */
                return NULL;
            }
        }


    }

    public static function getWorkingDays($startDate, $endDate)
    {
        $begin = strtotime($startDate);
        $end = strtotime($endDate);
        if ($begin > $end) {
            return FALSE;
        } else {
            $no_days = 0;
            $weekends = 0;
            while ($begin <= $end) {
                $no_days++; // no of days in the given interval
                $what_day = date("N", $begin);
                if ($what_day > 5) { // 6 and 7 are weekend days
                    $weekends++;
                };
                $begin += 86400; // +1 day
            };
            $working_days = $no_days - $weekends;
            return $working_days;
        }
    }

    public static function getBaseCurrency()
    {
        return Utility::query("SELECT * from config_currencies where is_base='YES'", "SELECT", TRUE);
    }

    public static function allCurrency()
    {
        $results = Utility::query("SELECT * from config_currencies ", "SELECT");
        $output = [];
        foreach ($results as $result) {
            $output[$result['id']] = $result;
        }
        return $output;
    }


    public static function loadAccountVariables()
    {
        $results = self::query("SELECT * from config_account_variables");
        return $results;
    }


    public static function getAccountVariables($variable_name)
    {
        $results = $_SESSION['pf.account_variables'];

        $search_result = array_search($variable_name, array_column($results, 'variable'));
        if ($search_result) {
            $found_result = $results[$search_result]['value'];
        } else {
            $found_result = FALSE;
        }

        return $found_result;
    }

    public static function getStaffBenefits($staff_id, $year, $month, $realisation_status = '0')
    {
        $query = "SELECT (select sum(amount) from benefits_lunch where realisation_status ='$realisation_status' AND staff_id ='$staff_id' AND MONTH (`date`) ='$month' AND YEAR(`date`) ='$year' ) as lunch_amount, 
                (select sum(amount) from benefits_vouchers where  realisation_status ='$realisation_status' AND staff_id ='$staff_id' AND MONTH (`date`) ='$month' AND YEAR(`date`)='$year' ) as vouchers_amount, 
                (select sum(amount) from benefits_health where  realisation_status ='$realisation_status' AND staff_id ='$staff_id' AND MONTH (`date`) ='$month' AND YEAR(`date`)='$year' ) as health_amount";

        return self::query($query, "SELECT", TRUE);
    }

    /**
     * This method will return the id of an account in charts of account given a general name as defined in config_account_usage
     * @param $keyword string
     * @param $full_row boolean (if this is true it will return the entire row from charts of account)
     * @return int (Id for the account)
     */
    public static function getAccountUsedAs($keyword, $full_row = false)
    {
        $acc = self::query("SELECT a.* FROM config_account_usage u, config_charts_accounts a WHERE a.id = u.account_id AND u.name = '$keyword'", "SELECT", true);
        if (is_array($acc) && count($acc) > 0) {
            return $full_row ? $acc : $acc['id'];
        } else {
            return false;
        }
    }

    /**
     * @param $shareData array of project [{id => percentage}] for all projects (0 = basket)
     *  if an integer is specified, it means 100% to the project having that specific id
     * @return false|string (json)
     */
    public static function shareSource($shareData = ["0" => 100])
    {
        if (is_array($shareData)) {
            $sharing = [];
        } else if (is_numeric($shareData)) {
            $sharing = ['"' . $shareData . '"' => 100];
        } else {
            $sharing = ["0" => 100];
        }
        return json_encode($sharing);
    }

    /**
     * Due Imprests, are those imprests that have already been paid
     * but either no report has been created, of if created then not submitted,
     * or if submitted, then not approved by line manager
     * @param type $user_id
     */
    public static function dueImprests($user_id)
    {
        $query = "SELECT distinct(car.id), car.document_number, car.staff_id, pv.amount, pv.paid_date as paid_date, car.entry_date, datediff(date(now()), date(pv.paid_date) ) as days_passed
        FROM config_activity_request car, payment_voucher pv, 
                 activity_report ar
        WHERE
                 car.id = pv.reference_document_id
             AND pv.reference_document_type = 'IMP'
             AND pv.status ='PAID'
             AND (
             -- either is not in the reports table, or is there but has not been submitted
            car.id NOT IN ( SELECT activity_report.request_id FROM activity_report WHERE activity_report.submitted_status ='YES' AND activity_report.request_id IS NOT NULL )
            -- or it has been submitted, but line manager has not approved the report
            OR car.id 
                        NOT IN (
                                select distinct(arp.request_id) from activity_report arp, activity_report_approval app 
                                where arp.id = app.activity_report_id
                                AND (
                                        app.approval_user_id = (SELECT user.report_to from user where id ='$user_id')
                                         OR 
                                         app.approval_user_id =$user_id   
                                             -- this is for catering for those old events in which a manager was approving his/her own reports
                                      )
                                )
            ) AND car.staff_id = '$user_id'
            ORDER BY days_passed desc;   
        ";

        return Utility::query($query);
    }

    /**
     * This method loads all settings within system_settings table into a session variable 'settings'
     * (to minimize database queries)
     * returns true if the settings were loaded into session, false if it failed
     * @param bool $force Force overwrite session loaded settings
     * @return bool
     */
    public static function loadSystemSettings($force = false) {
        if (isset($_SESSION['settings']) && count($_SESSION['settings'])  && !$force) {
            $updated = $_SESSION['settings']['updated'] ?? 0;
            if(!$force && time() - $updated < 3600) {
                return true;
            }
        } // No need to reload;
        $settings = self::query("SELECT id, name, value FROM system_settings");
        if ($settings) {
            foreach ($settings as $key => $item) {
                $_SESSION['settings'][$item['name']] = $item['value'];
            }
            $_SESSION['settings']['updated'] = time();
            return true;
        } else {
            return false;
        }
    }

    /**
     * This method returns the value of a specific system setting available in session variable `settings'
     * @param $keyword
     * @param bool $force
     * @return mixed|null
     */
    public static function getSetting($keyword, $force = false) {
        self::loadSystemSettings($force); // <-- Temporary TODO remove
        if (isset($_SESSION['settings'])) {
            return isset($_SESSION['settings'][$keyword]) ? $_SESSION['settings'][$keyword] : null;
        } else {
            if (self::loadSystemSettings($force)) {
                return self::getSetting($keyword);
            } else {
                return null;
            }
        }
    }

    /**
     * Get a list of all Months or a range of months
     * @param int $start
     * @param int $end
     * @return array|false
     */
    public static function monthNames($start = 1, $end = 12)
    {
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $months = array_combine(range($start, $end), array_slice($months, $start - 1, $end + 1 - $start));
        return $months;
    }

    public static function getMonthlyWorkdays($staff, $salary_month)
    {
        $query = "select * from monthly_workdays where staff='$staff' AND salary_month='$salary_month'";

        return self::query($query, 'SELECT', TRUE);
    }

    /**
     * Get all who are actual users of the system - excluding anyone who is an external
     * user (eg, auditor or system_admin/support_account)
     */
    public static function getSystemUsers()
    {
        return self::query("SELECT * FROM user where UPPER (user_status) ='ACTIVE' AND (id > 0) AND user_type='STAFF' order by name asc");
    }

    /**
     * Get all active configured allowances for all staffs into a simple
     * to use array   e.g: $data[staff_id][allowance_id] = $allowance_amount
     *
     * @return array
     */
    public static function getConfigStaffAllowance()
    {
        $query = "SELECT * FROM staff_allowance_allocation where status ='ACTIVE' order by staff";
        $results = self::query($query);

        $data = [];

        foreach ($results as $result) {
            $data[$result['staff']][$result['allowance']] = $result['amount'];
        }

        return $data;
    }

    /**
     *
     *
     * @param $staff [Staff_id]
     * @param $salary_month [12-2019]
     * @return array
     */
    public static function getMonthlyAllowance($staff, $salary_month): array
    {
        $query = "select * from monthly_allowances where staff='$staff' AND salary_month='$salary_month'";
        $results = self::query($query);

        $data = [];
        foreach ($results as $result) {
            $data[$result['allowance_id']] = $result['amount'];
        }

        return $data;
    }

    public static function saveAllMonthlyAllowance($data)
    {
        $salary_month = $data['salary_month'];
        $entry_user = $data['entry_user'];
        $s_month_year = explode('-', $salary_month);

        unset($data['entry_user'], $data['salary_month']);

        $delete_query = "DELETE from monthly_allowances where salary_month ='$salary_month'";
        $insert_query = 'INSERT INTO `monthly_allowances` (`id`, `staff`, `allowance_id`, `amount`, `salary_month`,`entry_timestamp`, `entry_user`,`month`, `year`) VALUES ';


        foreach ($data as $index => $value) {
            $trans = explode('_', $index);
            $staff = $trans[2];
            $allowance = $trans[3];
            $amount = str_replace(',', '', $value);
            if (!is_numeric($amount)) {
                $amount = 0;
            }

            $insert_query .= "(NULL, '$staff', '$allowance', '$amount', '$salary_month', CURRENT_TIMESTAMP, $entry_user,'{$s_month_year[0]}', '{$s_month_year[1]}'),";

        }

        self::query($delete_query, 'DELETE');
        $insert_query = rtrim($insert_query, ',');
        if (self::query($insert_query, 'INSERT')) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public static function saveAllMonthlyWorkdays($data)
    {
        $salary_month = $data['salary_month'];
        $entry_user = $data['entry_user'];
        $s_month_year = explode('-', $salary_month);

        unset($data['entry_user']);
        unset($data['salary_month']);

        $delete_query = "DELETE from monthly_workdays where salary_month ='$salary_month'";
        $insert_query = "INSERT INTO `monthly_workdays` (`id`, `staff`, `days`, `salary_month`,`entry_timestamp`, `entry_user`,`month`, `year`) VALUES ";


        foreach ($data as $index => $value) {
            $trans = explode('_', $index);
            $staff = $trans[2];
            $days = str_replace(',', '', $value);
            if (!is_numeric($days)) {
                $days = 0;
            }

            $insert_query .= "(NULL, '$staff', '$days', '$salary_month', CURRENT_TIMESTAMP, $entry_user,'{$s_month_year[0]}', '{$s_month_year[1]}'),";

        }

        self::query($delete_query, 'DELETE');
        $insert_query = rtrim($insert_query, ',');
        if (self::query($insert_query, 'INSERT')) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * @param $month
     * @param $year
     */
    public static function initializeMonthlyWorkDays($month, $year)
    {
        $month = ltrim($month, '0');
        $month = ($month < 10) ? '0' . $month : $month;

        // Get all data that may be missing in this month
        $query = "select id from user where id NOT IN (select staff from monthly_workdays where salary_month ='{$month}-{$year}') AND UPPER (user_status) = 'ACTIVE'";
        $users = Utility::query($query);


        $work_days = Utility::query("select * from config_variables where variable_name ='MONTH_WORKDAYS'", "SELECT", TRUE)['value'];

        $insert_query = "INSERT INTO `monthly_workdays` (`id`, `staff`, `days`, `salary_month`,`entry_timestamp`, `entry_user`,`month`, `year`) VALUES ";

        if (count($users) > 0) {
            foreach ($users as $user) {
                $insert_query .= "(NULL, '{$user['id']}', '$work_days', '{$month}-{$year}', CURRENT_TIMESTAMP, {$_SESSION['pf.id']},'{$month}', '{$year}'),";
            }
            $insert_query = rtrim($insert_query, ',');
            return self::query($insert_query, "INSERT");
        } else {
            return TRUE;
        }


    }


    public static function orderArrayByColumn($array, $column_name, ...$params){
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }
    /**
     * Returns a trimmed paragraph with a specified number of words
     * @param $text String
     * @param $word_count int
     * @param string $style ['tooltip'] or ['toggle']
     * @return String
     */
    public static function ellipsis($text, $word_count = 50, $style = 'tooltip') {
        $real_word_count = str_word_count($text);
        if($real_word_count > $word_count) {
            $character_count = 0; $spaces = 0;
            while($spaces < $word_count) {
                if(substr($text, $character_count, 1) === ' ') {
                    $spaces ++;
                }
                $character_count++;
            }
            $new_text = substr($text, 0,  $character_count);
            if($style == 'tooltip') {
                return "<p data-toggle='tooltip' title='".(htmlspecialchars($text))."'>{$new_text}...</p>";
            } else {
                $rest_of_text = substr($text, $character_count);
                return "<p data-toggle='tooltip''>{$new_text}
                            <span class='ellipsis-toggleable' style='display:none;'>".(htmlspecialchars($rest_of_text))."</span>
                            <span class='ellipsis-toggle badge badge-xs' style='cursor:pointer;'>...</span>
                        </p>";
            }
        } else {
            return $text;
        }
    }

    /**
     * Temporary method for retrieving the outcome used for admin costs
     * This meethod should be improved to be more reliable
     * @param bool $full_row
     * @return array|bool|mixed|null
     */
    public static function getAdminOutcome($full_row = true) {
        $res =  self::query("SELECT * FROM config_logframe WHERE entry_title LIKE '%ADMIN%' AND (parent_id = 0 OR parent_id IS NULL)", 'SELECT', 1) ?? [];
        return $full_row ? $res : $res['id'] ?? null;
    }

    /**
     * @param $title
     * @param $start_date
     * @param $end_date
     */
    public static function printOrganizationAddress($title,$start_date=null,$end_date=null){
        $org_address = '<p style="line-height: 5px">' . Utility::getSetting('COMPANY_ADDRESS_LINE_1') . '</p>
                        <p style="line-height: 5px">Tel:' . Utility::getSetting('COMPANY_PHONE_NUMBER') . '</p>
                        <p style="line-height: 5px">Mob:' . Utility::getSetting('COMPANY_MOBILE_NUMBER') . '</p>
                        <p style="line-height: 5px">' . Utility::getSetting('COMPANY_ADDRESS_LINE_2') . '</p>';
        $dateTimeVariable = date('F j, Y \a\t g:ia');
        echo "
    <table>
        <tr>
           <td align='center' width='20%'>
               <img class='img img-responsive' src='./assets/images/organization_logo_t.png' width='200' />
           </td>
           <td width='20%'>
                 $org_address
            </td>
           <td width='70%'>
               <div class='text-center '>
                   <h4>$title</h4>
                   <h4>$start_date - $end_date</h4>
                   <h5 class='print-only'>
                       $dateTimeVariable
                    </h5>
                </div>
            </td>
            
        </tr>
    </table>";
    }


    public static function printDocumentHeader($echo = true, $report = false){
        $org_address = '<p style="line-height: 5px">' . Utility::getSetting('COMPANY_ADDRESS_LINE_1') . '</p>
                        <p style="line-height: 5px">Tel:' . Utility::getSetting('COMPANY_PHONE_NUMBER') . '</p>
                        <p style="line-height: 5px">' . Utility::getSetting('COMPANY_ADDRESS_LINE_2') . '</p>';
        $dateTimeVariable = date('F j, Y \a\t g:ia');
        $text = "
        <table>
            <tr>
            <td width='30%'>
                <img class='img img-responsive' src='./assets/images/organization_logo_t.png' width='200' />
            </td>
            <td width='70%'>
                    $org_address
                </td>
            </tr>
        </table>";

        $header_report = "<table width='100%'>
                    <tr>
                        <td><img src='./assets/images/organization_logo_t.png' height='58' /></td>
                        <td class='text-right'>$org_address</td>
                    </tr>
                </table>";

        if($report){
            return $header_report;
        }
        if($echo){
            echo $text;
        }else{
            return $text;
        }
    }

    public static  function fullDocumentHeaderWithNumber($request_id =null,$document_no = "",$employee_name="",$employee_number="",$entry_time=null, $title="SAMPLE")
    {
        $text = "<table class='' width='100%' >
                <tr>
                    <td width='70%'>
                        <br> <p>".
                        Utility::printDocumentHeader(false)
                    ."</td>

                    <td width='30%' class='text-right'><h4>$title</h4>";
                        if($document_no != "") {
                            $text .=  "<div style='width: 200px; border: 2px solid black; padding: 12px; text-align: center;' class='pull-right'>
                                <h5>No. <a class='hide_print no-print' href='?p=operations.lpo.view&id=$request_id'>$document_no</a><span class='print-only'>$document_no</span><h5>
                            <div>";
                        }
                $text .= "</td>
                </tr>
                ";
                if($employee_name != ""){
                    $text .= 
                    "<tr><td>
                        <br/>
                        <p>Requested By: $employee_name <br> Employee Number: $employee_number </p>
                        <br/>
                    </td>
                    <td width='40%'>
                        <p class='text-right'>Requested Date : <b> $entry_time </b></p>
                    </td></tr>";
                }
                    $text .="
            </table>";
        return $text;
    }

    public static function getRootDirectory() {
        $cur_dir  = __DIR__;
        $root_dir = preg_replace('/modules/', '', $cur_dir);
        return $root_dir;
    }

    /**
     * This method prints the approval statuses of individual lines of documents on the list view
     * @param $serialized_approvals A string of comma separated - colon separated values in form of (5:APPROVED, 6:APPROVED, 7:REJECTED)
     * @param $approval_stages an array cotainin gApproval stages in the format of Approvable::approvableApprovalStages()
     * @return string Div conataining labels for each approval stage with respective status
     */
    public static function approvalStatusesFromSerial($serialized_approvals, $approval_stages){
            $status_map = [
                'CREATED' =>['icon' => 'fa-unlock', 'color'=>'warning'],
                'PENDING' =>['icon' => 'fa-clock-o', 'color'=>'warning'],
                'APPROVED' => ['icon' => 'fa-check', 'color'=>'success'],
                'REJECTED'=> ['icon' => 'fa-times', 'color'=>'danger'],
                'DISCARDED' => ['icon' => 'fa-trash', 'color'=>'danger']
            ];
            $parts = explode(',', $serialized_approvals) ?? [];
            $approvals = [];
            foreach ($parts as $part) {
                $p = explode(':', $part);
                if(count($p) > 1){
                    $approvals[$p[0]] = $p[1];
                }

            }
            $html = "<div class='approval-status-group btn-group'>";
            foreach ($approval_stages as $index => $stage) {
                $status = $approvals[$stage['user_group']] ?? 'PENDING';
                $html .= "<span class='label btn  btn-rounded btn-xs btn-{$status_map[$status]['color']}'><i class='fa {$status_map[$status]['icon']}'>&nbsp;</i>{$stage['user_group_keyword']}</span>";
            }
            $html .= '</div>';
            return $html;
        }

    /**
     * Return <options> for activities (Supports multi logFrame)
     * @param null $selected
     * @param null $sp_id
     * @return string
     */
    public static function optDownActivities($selected = null, $sp_id = null) {
        if(self::getSetting('MULTI_LOGFRAME')) {
            $logframes = self::query("SELECT l.*, p.name as project_name FROM logframes l LEFT JOIN config_projects p ON (p.id = l.project_id)", 'SELECT');
            $html = '';
                foreach ($logframes as $index => $logframe) {
                    $activities = self::query("SELECT a.* FROM config_activities a INNER JOIN config_logframe l1 ON (l1.id = a.output_id) INNER JOIN config_logframe l2 ON (l2.id = l1.parent_id) WHERE l2.logframe_id = '{$logframe['id']}'") ??[];
                    if(count($activities)){
                        $html .= "<optgroup label='".($logframe['project_name'] ?? $logframe['name'])."'>";
                        foreach ( $activities as $a_index => $activity) {
                            $select = $activity['id'] == $selected ? 'selected' : '';
                            $html .= "<option value='{$activity['id']}' {$select} title='{$activity['entry_title']}'>{$activity['entry_title']}: {$activity['entry_description']}</option>";
                        }
                        $html .= "</optgroup>";
                    } 
                }
        } else {
            $html = self::optDown(self::query("SELECT a.*, CONCAT(a.entry_title, ': ', a.entry_description) as name FROM config_activities a"), 'name', 'id', 'name', $selected);
        }
        return $html;
    }
}


//// Other functions [Meant to be out of the class body]
/**
 * Format money for displaying
 * @param $number the money to be formated
 * @param int $decimals number of decimals
 * @param bool $unsigned if true then will return money in brackets when it is negative
 * @return int|string
 */
function money($number, $decimals = 2, $unsigned = false)
{
    if ($number == null || $number == "")
        $number = 0;
    if (is_numeric($number)) {
        if ($unsigned) {
            return $number < 0 ? '(' . number_format($number, $decimals) . ')' : number_format($number, $decimals);
        } else {
            return number_format($number, $decimals);
        }
    } else {
        return $number;
    }
}

/**
 * Temporary method to dump variables during dev.
 * @param type $object
 */
function dump($object)
{
    echo '<pre>';
    print_r($object);
    echo '</pre>';
}
function dd($object) {
    dump($object);
    exit();
}
