<?php


class Backup extends Entity
{
    var $table = 'backups';
    var $ignore = ['updated_at'];

    public function add() {
//        $this->set('document_number', Utility::generateDocumentNumber($this->table, 'MCOMP'));
//        $this->set('entry_user', Utility::getLoggedInUser());
//        $this->set('entry_timestamp', date('Y-d-m H:i:s'));
        return parent::add();
    }
}