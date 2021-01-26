<?php
if (! defined('sugarEntry') || ! sugarEntry) die('Not A Valid Entry Point');

//At bottom of post_install - redirect to license validation page - CHANGE NAME BELOW - To your module name

function post_install() {

    //install table for user management
    global $db;
    if (!$db->tableExists('so_users')) {

        $fieldDefs = array(
            'id' => array (
              'name' => 'id',
              'vname' => 'LBL_ID',
              'type' => 'id',
              'required' => true,
              'reportable' => true,
            ),
            'deleted' => array (
                'name' => 'deleted',
                'vname' => 'LBL_DELETED',
                'type' => 'bool',
                'default' => '0',
                'reportable' => false,
                'comment' => 'Record deletion indicator',
            ),
            'shortname' => array (
                'name' => 'shortname',
                'vname' => 'LBL_SHORTNAME',
                'type' => 'varchar',
                'len' => 255,
            ),
            'user_id' => array (
                'name' => 'user_id',
                'rname' => 'user_name',
                'module' => 'Users',
                'id_name' => 'user_id',
                'vname' => 'LBL_USER_ID',
                'type' => 'relate',
                'isnull' => 'false',
                'dbType' => 'id',
                'reportable' => true,
                'massupdate' => false,
            ),
        );
        
        $indices = array(
            'id' => array (
                'name' => 'so_userspk',
                'type' => 'primary',
                'fields' => array (
                    0 => 'id',
                ),
            ),
            'shortname' => array (
                'name' => 'shortname',
                'type' => 'index',
                'fields' => array (
                    0 => 'shortname',
                ),
            ),
        );
        $db->createTableParams('so_users',$fieldDefs,$indices);
    }
    
//redirect to license validation page - CHANGE NAME BELOW - To your module name
//header('Location: index.php?module=SampleLicenseAddon&action=license');
    global $sugar_version;
    if(preg_match( "/^6.*/", $sugar_version)) {
        echo "
            <script>
            document.location = 'index.php?module=bfs_SuitetoXero&action=license';
            </script>"
        ;
    } else {
        echo "
            <script>
            var app = window.parent.SUGAR.App;
            window.parent.SUGAR.App.sync({callback: function(){
                app.router.navigate('#bwc/index.php?module=bfs_SuitetoXero&action=license', {trigger:true});
            }});
            </script>"
        ;
    }
}

