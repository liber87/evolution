<div class="stepcontainer">
  <ul class="progressbar">
    <li class="visited"><?php echo $_lang['choose_language']?></li>
    <li class="visited"><?php echo $_lang['installation_mode']?></li>
    <li class="visited"><?php echo $_lang['optional_items']?></li>
    <li class="visited"><?php echo $_lang['preinstall_validation']?></li>
    <li class="active"><?php echo $_lang['install_results']?></li>
  </ul>
  <div class="clearleft"></div>
</div>
<h2><?php echo $_lang['install_results']?></h2>
<?php
if (file_exists(__DIR__."/../assets/cache/siteManager.php")) {
    include_once(__DIR__."/../assets/cache/siteManager.php");
}else{
    define('MGR_DIR', 'manager');
}
define('MODX_CLI', false);

global $moduleName;
global $moduleVersion;
global $moduleSQLBaseFile;
global $moduleSQLDataFile;
global $moduleSQLResetFile;

global $moduleChunks;
global $moduleTemplates;
global $moduleSnippets;
global $modulePlugins;
global $moduleModules;
global $moduleTVs;
global $moduleDependencies;

global $errors;

$create = false;

// set timout limit
@ set_time_limit(120); // used @ to prevent warning when using safe mode?

echo "<p>{$_lang['setup_database']}</p>\n";

$installMode= (int)$_POST['installmode'];
$installData = $_POST['installdata'] == "1" ? 1 : 0;

//if ($installMode == 1) {
//	include "../".MGR_DIR."/includes/config.inc.php";
//} else {
// get db info from post
$database_server = $_POST['databasehost'];
$database_user = $_SESSION['databaseloginname'];
$database_password = $_SESSION['databaseloginpassword'];
$database_collation = $_POST['database_collation'];
$database_charset = substr($database_collation, 0, strpos($database_collation, '_'));
$database_connection_charset = $_POST['database_connection_charset'];
$database_connection_method = $_POST['database_connection_method'];
$dbase = "`" . $_POST['database_name'] . "`";
$table_prefix = $_POST['tableprefix'];
$adminname = $_POST['cmsadmin'];
$adminemail = $_POST['cmsadminemail'];
$adminpass = $_POST['cmspassword'];
$managerlanguage = $_POST['managerlanguage'];
$custom_placeholders = array();
//}

// set session name variable
if (!isset ($site_sessionname)) {
    $site_sessionname = 'SN' . uniqid('');
}

// get base path and url
$a = explode("install", str_replace("\\", "/", dirname($_SERVER["PHP_SELF"])));
if (count($a) > 1)
    array_pop($a);
$url = implode("install", $a);
reset($a);
$a = explode("install", str_replace("\\", "/", realpath(__DIR__)));
if (count($a) > 1)
    array_pop($a);
$pth = implode("install", $a);
unset ($a);
$base_url = $url . (substr($url, -1) != "/" ? "/" : "");
$base_path = $pth . (substr($pth, -1) != "/" ? "/" : "");

// connect to the database
echo "<p>". $_lang['setup_database_create_connection'];
if (!$conn = mysqli_connect($database_server, $database_user, $database_password)) {
    echo '<span class="notok">'.$_lang["setup_database_create_connection_failed"]."</span></p><p>".$_lang['setup_database_create_connection_failed_note']."</p>";
    return;
} else {
    echo '<span class="ok">'.$_lang['ok']."</span></p>";
}

// select database
echo "<p>".$_lang['setup_database_selection']. str_replace("`", "", $dbase) . "`: ";
if (!mysqli_select_db($conn, str_replace("`", "", $dbase))) {
    echo "<span class=\"notok\" style='color:#707070'>".$_lang['setup_database_selection_failed']."</span>".$_lang['setup_database_selection_failed_note']."</p>";
    $create = true;
} else {
    if (function_exists('mysqli_set_charset')) mysqli_set_charset($conn, $database_charset);
    mysqli_query($conn, "{$database_connection_method} {$database_connection_charset}");
    echo '<span class="ok">'.$_lang['ok']."</span></p>";
}

// try to create the database
if ($create) {
    echo "<p>".$_lang['setup_database_creation']. str_replace("`", "", $dbase) . "`: ";
    //	if(!@mysqli_create_db(str_replace("`","",$dbase), $conn)) {
    if (! mysqli_query($conn, "CREATE DATABASE $dbase DEFAULT CHARACTER SET $database_charset COLLATE $database_collation")) {
        echo '<span class="notok">'.$_lang['setup_database_creation_failed']."</span>".$_lang['setup_database_creation_failed_note']."</p>";
        $errors += 1;
        ?>
        <pre>
        database charset = <?php echo $database_charset ?>
            database collation = <?php echo $database_collation ?>
        </pre>
        <p><?php echo $_lang['setup_database_creation_failed_note2']?></p>
        <?php

        return;
    } else {
        echo '<span class="ok">'.$_lang['ok']."</span></p>";
    }
}

// check table prefix
if ($installMode == 0) {
    echo "<p>" . $_lang['checking_table_prefix'] . $table_prefix . "`: ";
    if (@ $rs = mysqli_query($conn, "SELECT COUNT(*) FROM $dbase.`" . $table_prefix . "site_content`")) {
        echo '<span class="notok">' . $_lang['failed'] . "</span>" . $_lang['table_prefix_already_inuse'] . "</p>";
        $errors += 1;
        echo "<p>" . $_lang['table_prefix_already_inuse_note'] . "</p>";
        return;
    } else {
        echo '<span class="ok">'.$_lang['ok']."</span></p>";
    }
}

// check status of Inherit Parent Template plugin
$auto_template_logic = 'parent';
if ($installMode != 0) {
    $rs = mysqli_query($conn, "SELECT properties, disabled FROM $dbase.`" . $table_prefix . "site_plugins` WHERE name='Inherit Parent Template'");
    $row = mysqli_fetch_row($rs);
    if(!$row) {
        // not installed
        $auto_template_logic = 'system';
    } else {
        if($row[1] == 1) {
            // installed but disabled
            $auto_template_logic = 'system';
        } else {
            // installed, enabled .. see how it's configured
            $properties = parseProperties($row[0]);
            if(isset($properties['inheritTemplate'])) {
                if($properties['inheritTemplate'] == 'From First Sibling') {
                    $auto_template_logic = 'sibling';
                }
            }
        }
    }
}

// open db connection
$setupPath = realpath(__DIR__);
include dirname(__DIR__) . '/processor/result.php';
include_once dirname(__DIR__) . '/sqlParser.class.php';
$sqlParser = new SqlParser($database_server, $database_user, $database_password, str_replace("`", "", $dbase), $table_prefix, $adminname, $adminemail, $adminpass, $database_connection_charset, $managerlanguage, $database_connection_method, $auto_template_logic);
$sqlParser->mode = ($installMode < 1) ? "new" : "upd";
/* image and file manager paths now handled via settings screen in Manager
$sqlParser->imageUrl = 'http://' . $_SERVER['SERVER_NAME'] . $base_url . "assets/";
$sqlParser->imageUrl = "assets/";
$sqlParser->imagePath = $base_path . "assets/";
$sqlParser->fileManagerPath = $base_path;
*/
$sqlParser->ignoreDuplicateErrors = true;
$sqlParser->connect();

// install/update database
echo "<p>" . $_lang['setup_database_creating_tables'];
if ($moduleSQLBaseFile) {
    $sqlParser->process($moduleSQLBaseFile);
    // display database results
    if ($sqlParser->installFailed == true) {
        $errors += 1;
        echo "<span class=\"notok\"><b>" . $_lang['database_alerts'] . "</span></p>";
        echo "<p>" . $_lang['setup_couldnt_install'] . "</p>";
        echo "<p>" . $_lang['installation_error_occured'] . "<br /><br />";
        for ($i = 0; $i < count($sqlParser->mysqlErrors); $i++) {
            echo "<em>" . $sqlParser->mysqlErrors[$i]["error"] . "</em>" . $_lang['during_execution_of_sql'] . "<span class='mono'>" . strip_tags($sqlParser->mysqlErrors[$i]["sql"]) . "</span>.<hr />";
        }
        echo "</p>";
        echo "<p>" . $_lang['some_tables_not_updated'] . "</p>";
        return;
    } else {
        echo '<span class="ok">'.$_lang['ok']."</span></p>";
    }
}

// custom or not
if (file_exists(__DIR__."/../../assets/cache/siteManager.php")) {
    $mgrdir = 'include_once(__DIR__."/../../assets/cache/siteManager.php");';
}else{
    $mgrdir = 'define(\'MGR_DIR\', \'manager\');';
}

// write the config.inc.php file if new installation
echo "<p>" . $_lang['writing_config_file'];

$confph = array();
$confph['database_server']    = $database_server;
$confph['user_name']          = mysqli_real_escape_string($conn, $database_user);
$confph['password']           = mysqli_real_escape_string($conn, $database_password);
$confph['connection_charset'] = $database_connection_charset;
$confph['connection_method']  = $database_connection_method;
$confph['dbase']              = str_replace('`', '', $dbase);
$confph['table_prefix']       = $table_prefix;
$confph['lastInstallTime']    = time();
$confph['site_sessionname']   = $site_sessionname;

$configString = file_get_contents(dirname(dirname(__DIR__)) . '/config.inc.tpl');
$configString = parse($configString, $confph);

$filename = '../'.MGR_DIR.'/includes/config.inc.php';
$configFileFailed = false;
if (@ !$handle = fopen($filename, 'w')) {
    $configFileFailed = true;
}

// write $somecontent to our opened file.
if (@ fwrite($handle, $configString) === FALSE) {
    $configFileFailed = true;
}
@ fclose($handle);

// try to chmod the config file go-rwx (for suexeced php)
@chmod($filename, 0404);

if ($configFileFailed == true) {
    echo '<span class="notok">' . $_lang['failed'] . "</span></p>";
    $errors += 1;
    ?>
    <p><?php echo $_lang['cant_write_config_file']?><span class="mono"><?php echo MGR_DIR; ?>/includes/config.inc.php</span></p>
    <textarea style="width:400px; height:160px;">
    <?php echo $configString; ?>
    </textarea>
    <p><?php echo $_lang['cant_write_config_file_note']?></p>
    <?php
    return;
} else {
    echo '<span class="ok">'.$_lang['ok']."</span></p>";
}

// generate new site_id and set manager theme to default
if ($installMode == 0) {
    $siteid = uniqid('');
    mysqli_query($sqlParser->conn, "REPLACE INTO $dbase.`" . $table_prefix . "system_settings` (setting_name,setting_value) VALUES('site_id','$siteid'),('manager_theme','default')");
} else {
    // update site_id if missing
    $ds = mysqli_query($sqlParser->conn, "SELECT setting_name,setting_value FROM $dbase.`" . $table_prefix . "system_settings` WHERE setting_name='site_id'");
    if ($ds) {
        $r = mysqli_fetch_assoc($ds);
        $siteid = $r['setting_value'];
        if ($siteid == '' || $siteid = 'MzGeQ2faT4Dw06+U49x3') {
            $siteid = uniqid('');
            mysqli_query($sqlParser->conn, "REPLACE INTO $dbase.`" . $table_prefix . "system_settings` (setting_name,setting_value) VALUES('site_id','$siteid')");
        }
    }
}

// Reset database for installation of demo-site
if ($installData && $moduleSQLDataFile && $moduleSQLResetFile) {
    echo "<p>" . $_lang['resetting_database'];
    $sqlParser->process($moduleSQLResetFile);
    // display database results
    if ($sqlParser->installFailed == true) {
        $errors += 1;
        echo "<span class=\"notok\"><b>" . $_lang['database_alerts'] . "</span></p>";
        echo "<p>" . $_lang['setup_couldnt_install'] . "</p>";
        echo "<p>" . $_lang['installation_error_occured'] . "<br /><br />";
        for ($i = 0; $i < count($sqlParser->mysqlErrors); $i++) {
            echo "<em>" . $sqlParser->mysqlErrors[$i]["error"] . "</em>" . $_lang['during_execution_of_sql'] . "<span class='mono'>" . strip_tags($sqlParser->mysqlErrors[$i]["sql"]) . "</span>.<hr />";
        }
        echo "</p>";
        echo "<p>" . $_lang['some_tables_not_updated'] . "</p>";
        return;
    } else {
        echo '<span class="ok">'.$_lang['ok']."</span></p>";
    }
}

// Install Templates
if (isset ($_POST['template']) || $installData) {
    echo "<h3>" . $_lang['templates'] . ":</h3> ";
    $selTemplates = $_POST['template'];
    foreach ($moduleTemplates as $k=>$moduleTemplate) {
        $installSample = in_array('sample', $moduleTemplate[6]) && $installData == 1;
        if($installSample || in_array($k, $selTemplates)) {
            $name = mysqli_real_escape_string($conn, $moduleTemplate[0]);
            $desc = mysqli_real_escape_string($conn, $moduleTemplate[1]);
            $category = mysqli_real_escape_string($conn, $moduleTemplate[4]);
            $locked = mysqli_real_escape_string($conn, $moduleTemplate[5]);
            $filecontent = $moduleTemplate[3];
            $save_sql_id_as = $moduleTemplate[7]; // Nessecary for demo-site
            if (!file_exists($filecontent)) {
                echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_template'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
            } else {
                // Create the category if it does not already exist
                $category_id = getCreateDbCategory($category, $sqlParser);

                // Strip the first comment up top
                $template = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', file_get_contents($filecontent), 1);
                $template = mysqli_real_escape_string($conn, $template);

                // See if the template already exists
                $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_templates` WHERE templatename='$name'");

                if (mysqli_num_rows($rs)) {
                    if (!mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_templates` SET content='$template', description='$desc', category=$category_id, locked='$locked'  WHERE templatename='$name' LIMIT 1;")) {
                        $errors += 1;
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    if(!is_null($save_sql_id_as)) {
                        $sql_id = @mysqli_insert_id($sqlParser->conn);
                        if(!$sql_id) {
                            $idQuery = mysqli_fetch_assoc(mysqli_query($sqlParser->conn, "SELECT id FROM $dbase.`" . $table_prefix . "site_templates` WHERE templatename='$name' LIMIT 1;"));
                            $sql_id = $idQuery['id'];
                        }
                        $custom_placeholders[$save_sql_id_as] = $sql_id;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . "</span></p>";
                } else {
                    if (!@ mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_templates` (templatename,description,content,category,locked) VALUES('$name','$desc','$template',$category_id,'$locked');")) {
                        $errors += 1;
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    if(!is_null($save_sql_id_as)) $custom_placeholders[$save_sql_id_as] = @mysqli_insert_id($sqlParser->conn);
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . "</span></p>";
                }
            }
        }
    }
}

// Install Template Variables
if (isset ($_POST['tv']) || $installData) {
    echo "<h3>" . $_lang['tvs'] . ":</h3> ";
    $selTVs = $_POST['tv'];
    foreach ($moduleTVs as $k=>$moduleTV) {
        $installSample = in_array('sample', $moduleTV[12]) && $installData == 1;
        if($installSample || in_array($k, $selTVs)) {
            $name = mysqli_real_escape_string($conn, $moduleTV[0]);
            $caption = mysqli_real_escape_string($conn, $moduleTV[1]);
            $desc = mysqli_real_escape_string($conn, $moduleTV[2]);
            $input_type = mysqli_real_escape_string($conn, $moduleTV[3]);
            $input_options = mysqli_real_escape_string($conn, $moduleTV[4]);
            $input_default = mysqli_real_escape_string($conn, $moduleTV[5]);
            $output_widget = mysqli_real_escape_string($conn, $moduleTV[6]);
            $output_widget_params = mysqli_real_escape_string($conn, $moduleTV[7]);
            $filecontent = $moduleTV[8];
            $assignments = $moduleTV[9];
            $category = mysqli_real_escape_string($conn, $moduleTV[10]);
            $locked = mysqli_real_escape_string($conn, $moduleTV[11]);


            // Create the category if it does not already exist
            $category = getCreateDbCategory($category, $sqlParser);

            $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_tmplvars` WHERE name='$name'");
            if (mysqli_num_rows($rs)) {
                $insert = true;
                while($row = mysqli_fetch_assoc($rs)) {
                    if (!mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_tmplvars` SET type='$input_type', caption='$caption', description='$desc', category=$category, locked=$locked, elements='$input_options', display='$output_widget', display_params='$output_widget_params', default_text='$input_default' WHERE id={$row['id']};")) {
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    $insert = false;
                }
                echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . "</span></p>";
            } else {
                //$q = "INSERT INTO $dbase.`" . $table_prefix . "site_tmplvars` (type,name,caption,description,category,locked,elements,display,display_params,default_text) VALUES('$input_type','$name','$caption','$desc',(SELECT (CASE COUNT(*) WHEN 0 THEN 0 ELSE `id` END) `id` FROM $dbase.`" . $table_prefix . "categories` WHERE `category` = '$category'),$locked,'$input_options','$output_widget','$output_widget_params','$input_default');";
                $q = "INSERT INTO $dbase.`" . $table_prefix . "site_tmplvars` (type,name,caption,description,category,locked,elements,display,display_params,default_text) VALUES('$input_type','$name','$caption','$desc',$category,$locked,'$input_options','$output_widget','$output_widget_params','$input_default');";
                if (!mysqli_query($sqlParser->conn, $q)) {
                    echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                    return;
                }
                echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . "</span></p>";
            }

            // add template assignments
            $assignments = explode(',', $assignments);

            if (count($assignments) > 0) {

                // remove existing tv -> template assignments
                $ds=mysqli_query($sqlParser->conn, "SELECT id FROM $dbase.`".$table_prefix."site_tmplvars` WHERE name='$name' AND description='$desc';");
                $row = mysqli_fetch_assoc($ds);
                $id = $row["id"];
                mysqli_query($sqlParser->conn, 'DELETE FROM ' . $dbase . '.`' . $table_prefix . 'site_tmplvar_templates` WHERE tmplvarid = \'' . $id . '\'');

                // add tv -> template assignments
                foreach ($assignments as $assignment) {
                    $template = mysqli_real_escape_string($conn, $assignment);
                    $ts = mysqli_query($sqlParser->conn, "SELECT id FROM $dbase.`".$table_prefix."site_templates` WHERE templatename='$template';");
                    if ($ds && $ts) {
                        $tRow = mysqli_fetch_assoc($ts);
                        $templateId = $tRow['id'];
                        mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_tmplvar_templates` (tmplvarid, templateid) VALUES($id, $templateId)");
                    }
                }
            }
        }
    }
}

// Install Chunks
if (isset ($_POST['chunk']) || $installData) {
    echo "<h3>" . $_lang['chunks'] . ":</h3> ";
    $selChunks = $_POST['chunk'];
    foreach ($moduleChunks as $k=>$moduleChunk) {
        $installSample = in_array('sample', $moduleChunk[5]) && $installData == 1;
        $count_new_name = 0;
        if($installSample || in_array($k, $selChunks)) {

            $name = mysqli_real_escape_string($conn, $moduleChunk[0]);
            $desc = mysqli_real_escape_string($conn, $moduleChunk[1]);
            $category = mysqli_real_escape_string($conn, $moduleChunk[3]);
            $overwrite = mysqli_real_escape_string($conn, $moduleChunk[4]);
            $filecontent = $moduleChunk[2];

            if (!file_exists($filecontent))
                echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_chunk'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
            else {

                // Create the category if it does not already exist
                $category_id = getCreateDbCategory($category, $sqlParser);

                $chunk = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', file_get_contents($filecontent), 1);
                $chunk = mysqli_real_escape_string($conn, $chunk);
                $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_htmlsnippets` WHERE name='$name'");
                $count_original_name = mysqli_num_rows($rs);
                if($overwrite == 'false') {
                    $newname = $name . '-' . str_replace('.', '_', $modx_version);
                    $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_htmlsnippets` WHERE name='$newname'");
                    $count_new_name = mysqli_num_rows($rs);
                }
                $update = $count_original_name > 0 && $overwrite == 'true';
                if ($update) {
                    if (!mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_htmlsnippets` SET snippet='$chunk', description='$desc', category=$category_id WHERE name='$name';")) {
                        $errors += 1;
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . "</span></p>";
                } elseif($count_new_name == 0) {
                    if($count_original_name > 0 && $overwrite == 'false') {
                        $name = $newname;
                    }
                    if (!mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_htmlsnippets` (name,description,snippet,category) VALUES('$name','$desc','$chunk',$category_id);")) {
                        $errors += 1;
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . "</span></p>";
                }
            }
        }
    }
}

// Install Modules
if (isset ($_POST['module']) || $installData) {
    echo "<h3>" . $_lang['modules'] . ":</h3> ";
    $selModules = $_POST['module'];
    foreach ($moduleModules as $k=>$moduleModule) {
        $installSample = in_array('sample', $moduleModule[7]) && $installData == 1;
        if($installSample || in_array($k, $selModules)) {
            $name = mysqli_real_escape_string($conn, $moduleModule[0]);
            $desc = mysqli_real_escape_string($conn, $moduleModule[1]);
            $filecontent = $moduleModule[2];
            $properties = $moduleModule[3];
            $guid = mysqli_real_escape_string($conn, $moduleModule[4]);
            $shared = mysqli_real_escape_string($conn, $moduleModule[5]);
            $category = mysqli_real_escape_string($conn, $moduleModule[6]);
            if (!file_exists($filecontent))
                echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_module'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
            else {

                // Create the category if it does not already exist
                $category = getCreateDbCategory($category, $sqlParser);

                $module = end(preg_split("/(\/\/)?\s*\<\?php/", file_get_contents($filecontent), 2));
                // $module = removeDocblock($module, 'module'); // Modules have no fileBinding, keep docblock for info-tab
                $module = mysqli_real_escape_string($conn, $module);
                $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_modules` WHERE name='$name'");
                if (mysqli_num_rows($rs)) {
                    $row = mysqli_fetch_assoc($rs);
                    $props = mysqli_real_escape_string($conn, propUpdate($properties,$row['properties']));
                    if (!mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_modules` SET modulecode='$module', description='$desc', properties='$props', enable_sharedparams='$shared' WHERE name='$name';")) {
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . "</span></p>";
                } else {
                    $properties = mysqli_real_escape_string($conn, parseProperties($properties, true));
                    if (!mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_modules` (name,description,modulecode,properties,guid,enable_sharedparams,category) VALUES('$name','$desc','$module','$properties','$guid','$shared', $category);")) {
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . "</span></p>";
                }
            }
        }
    }
}

// Install Plugins
if (isset ($_POST['plugin']) || $installData) {
    echo "<h3>" . $_lang['plugins'] . ":</h3> ";
    $selPlugs = $_POST['plugin'];
    foreach ($modulePlugins as $k=>$modulePlugin) {
        $installSample = is_array($modulePlugin[8]) && in_array('sample', $modulePlugin[8]) && $installData == 1;
        if($installSample || in_array($k, $selPlugs)) {
            $name = mysqli_real_escape_string($conn, $modulePlugin[0]);
            $desc = mysqli_real_escape_string($conn, $modulePlugin[1]);
            $filecontent = $modulePlugin[2];
            $properties = $modulePlugin[3];
            $events = explode(",", $modulePlugin[4]);
            $guid = mysqli_real_escape_string($conn, $modulePlugin[5]);
            $category = mysqli_real_escape_string($conn, $modulePlugin[6]);
            $leg_names = '';
            $disabled = $modulePlugin[9];
            if(array_key_exists(7, $modulePlugin)) {
                // parse comma-separated legacy names and prepare them for sql IN clause
                $leg_names = "'" . implode("','", preg_split('/\s*,\s*/', mysqli_real_escape_string($conn, $modulePlugin[7]))) . "'";
            }
            if (!file_exists($filecontent))
                echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_plugin'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
            else {

                // disable legacy versions based on legacy_names provided
                if(!empty($leg_names)) {
                    $update_query = "UPDATE $dbase.`" . $table_prefix . "site_plugins` SET disabled='1' WHERE name IN ($leg_names);";
                    $rs = mysqli_query($sqlParser->conn, $update_query);
                }

                // Create the category if it does not already exist
                $category = getCreateDbCategory($category, $sqlParser);

                $plugin = end(preg_split("/(\/\/)?\s*\<\?php/", file_get_contents($filecontent), 2));
                $plugin = removeDocblock($plugin, 'plugin');
                $plugin = mysqli_real_escape_string($conn, $plugin);
                $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_plugins` WHERE name='$name'");
                if (mysqli_num_rows($rs)) {
                    $insert = true;
                    while($row = mysqli_fetch_assoc($rs)) {
                        $props = mysqli_real_escape_string($conn, propUpdate($properties,$row['properties']));
                        if($row['description'] == $desc){
                            if (! mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_plugins` SET plugincode='$plugin', description='$desc', properties='$props' WHERE id={$row['id']};")) {
                                echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                                return;
                            }
                            $insert = false;
                        } else {
                            if (!mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_plugins` SET disabled='1' WHERE id={$row['id']};")) {
                                echo "<p>".mysqli_error($sqlParser->conn)."</p>";
                                return;
                            }
                        }
                    }
                    if($insert === true) {
                        $properties = mysqli_real_escape_string($conn, propUpdate($properties,$row['properties']));
                        if(!mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`".$table_prefix."site_plugins` (name,description,plugincode,properties,moduleguid,disabled,category) VALUES('$name','$desc','$plugin','$properties','$guid','0',$category);")) {
                            echo "<p>".mysqli_error($sqlParser->conn)."</p>";
                            return;
                        }
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . "</span></p>";
                } else {
                    $properties = mysqli_real_escape_string($conn, parseProperties($properties, true));
                    if (!mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_plugins` (name,description,plugincode,properties,moduleguid,category,disabled) VALUES('$name','$desc','$plugin','$properties','$guid',$category,$disabled);")) {
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . "</span></p>";
                }
                // add system events
                if (count($events) > 0) {
                    $ds=mysqli_query($sqlParser->conn, "SELECT id FROM $dbase.`".$table_prefix."site_plugins` WHERE name='$name' AND description='$desc';");
                    if ($ds) {
                        $row = mysqli_fetch_assoc($ds);
                        $id = $row["id"];
                        // remove existing events
                        mysqli_query($sqlParser->conn, 'DELETE FROM ' . $dbase . '.`' . $table_prefix . 'site_plugin_events` WHERE pluginid = \'' . $id . '\'');
                        // add new events
                        mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_plugin_events` (pluginid, evtid) SELECT '$id' as 'pluginid',se.id as 'evtid' FROM $dbase.`" . $table_prefix . "system_eventnames` se WHERE name IN ('" . implode("','", $events) . "')");
                    }
                }
            }
        }
    }
}

// Install Snippets
if (isset ($_POST['snippet']) || $installData) {
    echo "<h3>" . $_lang['snippets'] . ":</h3> ";
    $selSnips = $_POST['snippet'];
    foreach ($moduleSnippets as $k=>$moduleSnippet) {
        $installSample = in_array('sample', $moduleSnippet[5]) && $installData == 1;
        if($installSample || in_array($k, $selSnips)) {
            $name = mysqli_real_escape_string($conn, $moduleSnippet[0]);
            $desc = mysqli_real_escape_string($conn, $moduleSnippet[1]);
            $filecontent = $moduleSnippet[2];
            $properties = $moduleSnippet[3];
            $category = mysqli_real_escape_string($conn, $moduleSnippet[4]);
            if (!file_exists($filecontent))
                echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_snippet'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
            else {

                // Create the category if it does not already exist
                $category = getCreateDbCategory($category, $sqlParser);

                $snippet = end(preg_split("/(\/\/)?\s*\<\?php/", file_get_contents($filecontent)));
                $snippet = removeDocblock($snippet, 'snippet');
                $snippet = mysqli_real_escape_string($conn, $snippet);
                $rs = mysqli_query($sqlParser->conn, "SELECT * FROM $dbase.`" . $table_prefix . "site_snippets` WHERE name='$name'");
                if (mysqli_num_rows($rs)) {
                    $row = mysqli_fetch_assoc($rs);
                    $props = mysqli_real_escape_string($conn, propUpdate($properties,$row['properties']));
                    if (!mysqli_query($sqlParser->conn, "UPDATE $dbase.`" . $table_prefix . "site_snippets` SET snippet='$snippet', description='$desc', properties='$props' WHERE name='$name';")) {
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . "</span></p>";
                } else {
                    $properties = mysqli_real_escape_string($conn, parseProperties($properties, true));
                    if (!mysqli_query($sqlParser->conn, "INSERT INTO $dbase.`" . $table_prefix . "site_snippets` (name,description,snippet,properties,category) VALUES('$name','$desc','$snippet','$properties',$category);")) {
                        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                        return;
                    }
                    echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . "</span></p>";
                }
            }
        }
    }
}

// Install demo-site
if ($installData && $moduleSQLDataFile) {
    echo "<p>" . $_lang['installing_demo_site'];
    $sqlParser->process($moduleSQLDataFile);
    // display database results
    if ($sqlParser->installFailed == true) {
        $errors += 1;
        echo "<span class=\"notok\"><b>" . $_lang['database_alerts'] . "</span></p>";
        echo "<p>" . $_lang['setup_couldnt_install'] . "</p>";
        echo "<p>" . $_lang['installation_error_occured'] . "<br /><br />";
        for ($i = 0; $i < count($sqlParser->mysqlErrors); $i++) {
            echo "<em>" . $sqlParser->mysqlErrors[$i]["error"] . "</em>" . $_lang['during_execution_of_sql'] . "<span class='mono'>" . strip_tags($sqlParser->mysqlErrors[$i]["sql"]) . "</span>.<hr />";
        }
        echo "</p>";
        echo "<p>" . $_lang['some_tables_not_updated'] . "</p>";
        return;
    } else {
        $sql = sprintf("SELECT id FROM `%ssite_templates` WHERE templatename='EVO startup - Bootstrap'", $sqlParser->prefix);
        $rs = mysqli_query($sqlParser->conn, $sql);
        if(mysqli_num_rows($rs)) {
            $row = mysqli_fetch_assoc($rs);
            $sql = sprintf('UPDATE `%ssite_content` SET template=%s WHERE template=4', $sqlParser->prefix, $row['id']);
            mysqli_query($sqlParser->conn, $sql);
        }
        echo '<span class="ok">'.$_lang['ok']."</span></p>";
    }
}

// Install Dependencies
foreach ($moduleDependencies as $dependency) {
    $ds = mysqli_query($sqlParser->conn, 'SELECT id, guid FROM ' . $dbase . '`' . $sqlParser->prefix . 'site_modules` WHERE name="' . $dependency['module'] . '"');
    if (!$ds) {
        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
        return;
    } else {
        $row = mysqli_fetch_assoc($ds);
        $moduleId = $row["id"];
        $moduleGuid = $row["guid"];
    }
    // get extra id
    $ds = mysqli_query($sqlParser->conn, 'SELECT id FROM ' . $dbase . '`' . $sqlParser->prefix . 'site_' . $dependency['table'] . '` WHERE ' . $dependency['column'] . '="' . $dependency['name'] . '"');
    if (!$ds) {
        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
        return;
    } else {
        $row = mysqli_fetch_assoc($ds);
        $extraId = $row["id"];
    }
    // setup extra as module dependency
    $ds = mysqli_query($sqlParser->conn, 'SELECT module FROM ' . $dbase . '`' . $sqlParser->prefix . 'site_module_depobj` WHERE module=' . $moduleId . ' AND resource=' . $extraId . ' AND type=' . $dependency['type'] . ' LIMIT 1');
    if (!$ds) {
        echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
        return;
    } else {
        if (mysqli_num_rows($ds) === 0) {
            mysqli_query($sqlParser->conn, 'INSERT INTO ' . $dbase . '`' . $sqlParser->prefix . 'site_module_depobj` (module, resource, type) VALUES(' . $moduleId . ',' . $extraId . ',' . $dependency['type'] . ')');
            echo '<p>&nbsp;&nbsp;' . $dependency['module'] . ' Module: <span class="ok">' . $_lang['depedency_create'] . '</span></p>';
        } else {
            mysqli_query($sqlParser->conn, 'UPDATE ' . $dbase . '`' . $sqlParser->prefix . 'site_module_depobj` SET module = ' . $moduleId . ', resource = ' . $extraId . ', type = ' . $dependency['type'] . ' WHERE module=' . $moduleId . ' AND resource=' . $extraId . ' AND type=' . $dependency['type']);
            echo '<p>&nbsp;&nbsp;' . $dependency['module'] . ' Module: <span class="ok">' . $_lang['depedency_update'] . '</span></p>';
        }
        if ($dependency['type'] == 30 || $dependency['type'] == 40) {
            // set extra guid for plugins and snippets
            $ds = mysqli_query($sqlParser->conn, 'SELECT id FROM ' . $dbase . '`' . $sqlParser->prefix . 'site_' . $dependency['table'] . '` WHERE id=' . $extraId . ' LIMIT 1');
            if (!$ds) {
                echo "<p>" . mysqli_error($sqlParser->conn) . "</p>";
                return;
            } else {
                if (mysqli_num_rows($ds) != 0) {
                    mysqli_query($sqlParser->conn, 'UPDATE ' . $dbase . '`' . $sqlParser->prefix . 'site_' . $dependency['table'] . '` SET moduleguid = ' . $moduleGuid . ' WHERE id=' . $extraId);
                    echo '<p>&nbsp;&nbsp;' . $dependency['name'] . ': <span class="ok">' . $_lang['guid_set'] . '</span></p>';
                }
            }
        }
    }
}

// call back function
if ($callBackFnc != "")
    $callBackFnc ($sqlParser);

// Setup the MODX API -- needed for the cache processor
define('MODX_API_MODE', true);
define('MODX_BASE_PATH', $base_path);
if (!defined('MODX_MANAGER_PATH')) define('MODX_MANAGER_PATH', $base_path.MGR_DIR.'/');
$database_type = 'mysqli';
// initiate a new document parser
include_once('../'.MGR_DIR.'/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
$modx->db->connect();
// always empty cache after install
$sync = new EvolutionCMS\Cache();
$sync->setCachepath("../assets/cache/");
$sync->setReport(false);
$sync->emptyCache(); // first empty the cache

// try to chmod the cache go-rwx (for suexeced php)
@chmod('../assets/cache/siteCache.idx.php', 0600);
@chmod('../assets/cache/sitePublishing.idx.php', 0600);

// remove any locks on the manager functions so initial manager login is not blocked
mysqli_query($conn, "TRUNCATE TABLE `".$table_prefix."active_users`");

// close db connection
$sqlParser->close();

// andrazk 20070416 - release manager access
if (file_exists('../assets/cache/installProc.inc.php')) {
    @chmod('../assets/cache/installProc.inc.php', 0755);
    unlink('../assets/cache/installProc.inc.php');
}

// setup completed!
echo "<p><b>" . $_lang['installation_successful'] . "</b></p>";
echo "<p>" . $_lang['to_log_into_content_manager'] . "</p>";
if ($installMode == 0) {
    echo "<p><img src=\"img/ico_info.png\" width=\"40\" height=\"42\" align=\"left\" style=\"margin-right:10px;\" />" . $_lang['installation_note'] . "</p>";
} else {
    echo "<p><img src=\"img/ico_info.png\" width=\"40\" height=\"42\" align=\"left\" style=\"margin-right:10px;\" />" . $_lang['upgrade_note'] . "</p>";
}
?>
<form name="install" id="install_form" action="index.php?action=options" method="post">
<?php
if ($errors == 0) {
	// check if install folder is removeable
    if (is_writable("../install")) { ?>
<span id="removeinstall" style="float:left;cursor:pointer;color:#505050;line-height:18px;" onclick="var chk=document.install.rminstaller; if(chk) chk.checked=!chk.checked;"><input type="checkbox" name="rminstaller" onclick="event.cancelBubble=true;" <?php echo (empty ($errors) ? 'checked="checked"' : '') ?> style="cursor:default;" /><?php echo $_lang['remove_install_folder_auto'] ?></span>
<?php
    } else {
?>
<span id="removeinstall" style="float:left;color:#505050;line-height:18px;"><?php echo $_lang['remove_install_folder_manual']?></span>
<?php
    }
}
?>
    <p class="buttonlinks">
        <a href="javascript:closepage();" title="<?php echo $_lang['btnclose_value']?>"><span><?php echo $_lang['btnclose_value']?></span></a>
    </p>
	<br />
</form>
<br />
<script type="text/javascript">
/* <![CDATA[ */
function closepage(){
	var chk = document.install.rminstaller;
	if(chk && chk.checked) {
		// remove install folder and files
		window.location.href = "../<?php echo MGR_DIR;?>/processors/remove_installer.processor.php?rminstall=1";
	}
	else {
		window.location.href = "../<?php echo MGR_DIR;?>/";
	}
}
/* ]]> */
</script>
