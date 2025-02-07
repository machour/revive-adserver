<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

/**
 * OpenX Schema Management Utility
 */

require_once './init.php';

define('OX_CORE', '/etc/');
define('OX_PLUG', $pluginPath . '%s/etc/');

if (array_key_exists('btn_changeset_archive', $_POST)) {
    header('Location: archive.php');
    exit;
}

require_once 'lib/schema.inc.php';
if (array_key_exists('clear_cookies', $_POST)) {
    setcookie('schemaPath', '');
    setcookie('schemaFile', '');
} elseif (array_key_exists('xml_file', $_REQUEST) && (!empty($_REQUEST['xml_file']))) {
    $schemaPath = dirname($_REQUEST['xml_file']);

    $schemaFile = basename($_REQUEST['xml_file']);
    if ($schemaFile == $_COOKIE['schemaFile']) {
        $schemaPath = $_COOKIE['schemaPath'];
    }
    $_POST['table_edit'] = '';
} elseif (array_key_exists('schemaFile', $_COOKIE) && (!empty($_COOKIE['schemaFile']))) {
    $schemaPath = $_COOKIE['schemaPath'];
    $schemaFile = $_COOKIE['schemaFile'];
}
if (empty($schemaPath) || empty($schemaFile)) {
    $schemaPath = ''; //OX_CORE;
    $schemaFile = 'tables_core.xml';
}

// ensure correct directory format. $schemaPath requires trailing '/'. Using trailing DIRECTORY_SEPARATOR fails on Windows for reasons unknown
if (isset($schemaPath) && $schemaPath != '') {
    $schemaPath = OX::realPathRelative(urldecode($schemaPath));
    $schemaPath .= '/';
}

setcookie('schemaPath', $schemaPath);
setcookie('schemaFile', $schemaFile);

global $oaSchema;
$oaSchema = new Openads_Schema_Manager($schemaFile, '', $schemaPath);

if (is_array($aErrs = OX_DevToolbox::checkFilePermissions([PATH_DEV, PATH_VAR, MAX_PATH . $pluginPath]))) {
    setcookie('schemaFile', '');
    setcookie('schemaPath', '');
    $errorMessage =
        join("<br />\n", $aErrs['errors']) . "<br /><br ><hr /><br />\n" .
        'To fix, please execute the following commands:' . "<br /><br >\n" .
        join("<br />\n", $aErrs['fixes']);
    die($errorMessage);
}

require_once PATH_DEV . '/lib/xajax.inc.php';

if (array_key_exists('btn_copy_final', $_POST)) {
    $oaSchema->createTransitional();
} elseif (array_key_exists('btn_schema_new', $_POST)) {
    $oaSchema->createNew($_POST['new_schema_name']);
} elseif (array_key_exists('btn_delete_trans', $_POST)) {
    $oaSchema->deleteTransitional();
} elseif (array_key_exists('btn_compare_schemas', $_POST)) {
    setcookie('changesetFile', '');
    if ($oaSchema->createChangeset($oaSchema->changes_trans, $_POST['comments'])) {
        header('Content-Type: application/xhtml+xml; charset=ISO-8859-1');
        readfile($oaSchema->changes_trans);
        exit();
    }
} elseif (array_key_exists('btn_changeset_delete', $_POST)) {
    $oaSchema->deleteChangesTrans();
} elseif (array_key_exists('btn_commit_final', $_POST)) {
    $oaSchema->commitFinal($_POST['comments'], $_POST['version']);
} elseif (array_key_exists('btn_generate_dbo_final', $_POST)) {
    $oaSchema->setWorkingFiles();
    $oaSchema->parseWorkingDefinitionFile();
    $oaSchema->_generateDataObjects($oaSchema->changes_final, $oaSchema->_getBasename());
} elseif (array_key_exists('btn_generate_dbo_trans', $_POST)) {
    $oaSchema->setWorkingFiles();
    $oaSchema->parseWorkingDefinitionFile();
    $oaSchema->_generateDataObjects($oaSchema->changes_trans, $oaSchema->_getBasename());
}


$oaSchema->setWorkingFiles();

if (array_key_exists('table_edit', $_POST) && $_POST['table_edit']) {
    $table = $_POST['table_edit'];

    if (array_key_exists('btn_field_save', $_POST) && $_POST['field_name']) {
        $field_name_old = $_POST['field_name'];
        $field_name_new = $_POST['fld_new_name'];
        $field_type_old = $_POST['field_type'];
        $field_type_new = $_POST['fld_new_type'];
        $oaSchema->fieldSave($table, $field_name_old, $field_name_new, $field_type_old, $field_type_new);
    } elseif (array_key_exists('btn_field_add', $_POST) && $_POST['field_add']) {
        $field_name = $_POST['field_add'];
        $dd_field_name = $_POST['sel_field_add'];
        $oaSchema->fieldAdd($table, $field_name, $dd_field_name);
    } elseif (array_key_exists('btn_field_del', $_POST) && $_POST['field_name']) {
        $field = $_POST['field_name'];
        $oaSchema->fieldDelete($table, $field);
    } elseif (array_key_exists('btn_index_del', $_POST) && $_POST['index_name']) {
        $index = $_POST['index_name'];
        $oaSchema->indexDelete($table, $index);
    } elseif (array_key_exists('btn_index_add', $_POST) && $_POST['idx_fld_add'] && $_POST['index_add']) {
        $index_fields = $_POST['idx_fld_add'];
        $index_name = $_POST['index_add'];
        $sort_desc = $_POST['idx_fld_desc'];
        $unique = $_POST['idx_unique'];
        $primary = $_POST['idx_primary'];
        $oaSchema->indexAdd($table, $index_name, $index_fields, $primary, $unique, $sort_desc);
    } elseif (array_key_exists('btn_index_save', $_POST) && $_POST['index_name']) {
        $index_name = $_POST['index_name'];
        $index_no = $_POST['index_no'];
        $index_def = $_POST['idx'][$index_no];
        $oaSchema->indexSave($table, $index_name, $index_def);
    } elseif (array_key_exists('btn_link_del', $_POST) && $_POST['link_name']) {
        $link_name = $_POST['link_name'];
        $oaSchema->linkDelete($table, $link_name);
    } elseif (array_key_exists('btn_link_add', $_POST) && $_POST['link_add'] && $_POST['link_add_target']) {
        $link_add = $_POST['link_add'];
        $link_add_target = $_POST['link_add_target'];
        $oaSchema->linkAdd($table, $link_add, $link_add_target);
    } elseif (array_key_exists('btn_table_save', $_POST) && $_POST['tbl_new_name']) {
        $table_name_new = $_POST['tbl_new_name'];
        $ok = $oaSchema->tableSave($table, $table_name_new);
        if ($ok) {
            $table = $table_name_new;
        }
    } elseif (array_key_exists('btn_table_delete', $_POST)) {
        $oaSchema->tableDelete($table);
        unset($table);
    } elseif (array_key_exists('btn_table_cancel', $_POST)) {
        $table = '';
    }
} elseif (array_key_exists('btn_table_new', $_POST) && isset($_POST['new_table_name'])) {
    $table = $_POST['new_table_name'];
    $oaSchema->tableNew($table);
    unset($table);
} elseif (array_key_exists('btn_table_edit', $_POST)) {
    $table = $_POST['btn_table_edit'];
}

if (!$table) {
    header('Content-Type: application/xhtml+xml; charset=ISO-8859-1');

    readfile($oaSchema->working_file_schema);
    // echo $before.' - '.$after ;
    exit();
} else {
    $oaSchema->parseWorkingDefinitionFile();
    $aDD_definition = $oaSchema->aDD_definition;
    $aDB_definition = $oaSchema->aDB_definition;
    $aTbl_definition = $oaSchema->aDB_definition['tables'][$table];
    $aLinks = $oaSchema->readForeignKeys($table);
    $aTbl_links = $aLinks[$table];
    $aLink_targets = $oaSchema->getLinkTargets();

    include 'templates/schema_edit.html';
    exit();
}
