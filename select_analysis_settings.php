<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/SettingEditor.inc.php");
require_once("./inc/Util.inc.php");

global $enableUserAdmin;

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['analysiseditor'])) {
  $_SESSION['analysiseditor'] = new AnalysisSettingEditor($_SESSION['user']);
}

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
  $admin = new User();
  $admin->setName( "admin" );
  $admin_editor = new AnalysisSettingEditor($admin);
  $_SESSION['admin_analysiseditor'] = $admin_editor;
}

$message = "";

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if (isset($_POST['analysis_setting'])) {
  $_SESSION['analysiseditor']->setSelected($_POST['analysis_setting']);
}

if (isset($_POST['copy_public'])) {
  if (isset($_POST["public_setting"])) {
    if (!$_SESSION['analysiseditor']->copyPublicSetting(
        $admin_editor->setting($_POST['public_setting']))) {
      $message = $_SESSION['editor']->message();
    }
  }
  else $message = "Please select a setting to copy";
}
else if (isset($_POST['create'])) {
  $analysis_setting = $_SESSION['analysiseditor']->createNewSetting(
    $_POST['new_setting']);
  if ($analysis_setting != NULL) {
    $_SESSION['analysis_setting'] = $analysis_setting;
    header("Location: " . "coloc_analysis.php"); exit();
  }
  $message = $_SESSION['analysiseditor']->message();
}
else if (isset($_POST['copy'])) {
  $_SESSION['analysiseditor']->copySelectedSetting($_POST['new_setting']);
  $message = $_SESSION['analysiseditor']->message();
}
else if (isset($_POST['edit'])) {
  $analysis_setting = $_SESSION['analysiseditor']->loadSelectedSetting();
  if ($analysis_setting) {
    $_SESSION['analysis_setting'] = $analysis_setting;
    header("Location: " . "coloc_analysis.php"); exit();
  }
  $message = $_SESSION['analysiseditor']->message();
}
else if (isset($_POST['make_default'])) {
  $_SESSION['analysiseditor']->makeSelectedSettingDefault();
  $message = $_SESSION['analysiseditor']->message();
}
else if ( isset($_POST['annihilate']) &&
    strcmp( $_POST['annihilate'], "yes") == 0 ) {
        $_SESSION['analysiseditor']->deleteSelectedSetting();
        $message = $_SESSION['analysiseditor']->message();
}
else if (isset($_POST['OK']) && $_POST['OK']=="OK" ) {
  if (!isset($_POST['analysis_setting'])) {
    $message = "Please select some analysis parameters";
  }
  else {
    $_SESSION['analysis_setting'] =
        $_SESSION['analysiseditor']->loadSelectedSetting();
    $_SESSION['analysis_setting']->setNumberOfChannels(
        $_SESSION['setting']->numberOfChannels());
    
      header("Location: " . "create_job.php"); exit();
  }
}

$script = array( "settings.js", "common.js", "ajax_utils.js" );

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <?php
            if ( !$_SESSION['user']->isAdmin()) {
            ?>
            <li><a href="file_manager.php">
                    <img src="images/filemanager_small.png" alt="file manager" />
                    &nbsp;File manager
                </a>
            </li>
            <?php
            }
            ?>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                    <img src="images/home.png" alt="home" />
                    &nbsp;Home
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpSelectTaskSettings')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>
    
    <div id="content">
    
<?php

if ($_SESSION['user']->isAdmin()) {

?>
        <h3>Analysis parameters</h3>
<?php

}
else {

?>
        <h3>Step 4/5 - Analysis parameters</h3>
<?php

}

// display public settings
if (!$_SESSION['user']->isAdmin()) {

?>
        <form method="post" action="">
        
            <fieldset>
              <legend>Template analysis parameters</legend>
              <p class="message_small">
                  These are the parameter sets prepared by your administrator.
              </p>
              <div id="templates">
<?php

  $settings = $admin_editor->settings();
  $flag = "";
  if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
                    <select name="public_setting"
                        onchange="getParameterListForSet('analysis_setting', $(this).val(), true);"
                        size="5"<?php echo $flag ?>>
<?php

  if (sizeof($settings) == 0) {
    echo "                        <option>&nbsp;</option>\n";
  }
  else {
    foreach ($settings as $set) {
      echo "                        <option>".$set->name()."</option>\n";
    }
  }

?>
                    </select>
                </div>
            </fieldset>

            
            <div id="selection">
                <input name="copy_public"
                       type="submit"
                       value=""
                       class="icon down"
                       id="controls_copyTemplate" />
            </div>
            
        </form>
        
<?php

}

?>

        <form method="post" action="" id="select">
        
            <fieldset>
            
              <?php
                if ($_SESSION['user']->isAdmin()) {
                  echo "<legend>Template analysis parameters</legend>";
                  echo "<p class=\"message_small\">Create template parameter " .
                    "sets visible to all users.</p>";
                } else {
                  echo "<legend>Your analysis parameters</legend>";
                  echo "<p class=\"message_small\">These are your (private) " .
                    "parameter sets.</p>";
                }
              ?>
              <div id="settings">
<?php

$settings = $_SESSION['analysiseditor']->settings();
$size = "8";
if ($_SESSION['user']->isAdmin()) $size = "12";
$flag = "";
if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
                    <select name="analysis_setting"
                        onchange="getParameterListForSet('analysis_setting', $(this).val(), false);"
                        size="<?php echo $size ?>"
                        <?php echo $flag ?>>
<?php

if (sizeof($settings) == 0) {
  echo "                        <option>&nbsp;</option>\n";
}
else {
  foreach ($settings as $set) {
    echo "                        <option";
    if ($set->isDefault()) {
      echo " class=\"default\"";
    }
    if ($_SESSION['analysiseditor']->selected() == $set->name()) {
      echo " selected=\"selected\"";
    }
    echo ">".$set->name()."</option>\n";
  }
}

?>
                    </select>
                </div>
                
            </fieldset>
            
            <div id="actions"
                 class="taskselection">
                <input name="create"
                       type="submit"
                       value=""
                       class="icon create"
                       id="controls_create" />
                <input name="edit"
                       type="submit"
                       value=""
                       class="icon edit"
                       id="controls_edit" />
                <input name="copy"
                       type="submit"
                       value=""
                       class="icon clone"
                       id="controls_clone" />
<?php

if (!$_SESSION['user']->isAdmin()) {

?>
                <input name="make_default" 
                       type="submit"
                       value=""
                       class="icon mark"
                       id="controls_default" />
<?php

}

?>
                <input type="hidden" name="annihilate" />
                <input name="delete"
                       type="button"
                       value=""
                       class="icon delete"
                       onclick="warn(this.form,
                         'Do you really want to delete this parameter set?',
                         this.form['analysis_setting'].selectedIndex )"
                       id="controls_delete" />
                <label>New/clone parameter set name:
                    <input name="new_setting"
                           type="text"
                           class="textfield" />
                </label>
                <input name="OK" type="hidden" />
                
            </div>
<?php

if (!$_SESSION['user']->isAdmin()) {

?>
                <div id="controls">      
                  <input type="button"
                         value=""
                         class="icon previous"
                         onclick="document.location.href='select_analysis_settings.php'"
                         id="controls_back" />
                  <input type="submit" 
                         value=""
                         class="icon next"
                        onclick="process()"
                        id="controls_forward" />
                </div>
<?php

}

?>
            
        </form> <!-- select -->
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">
          
          <h3>Quick help</h3>
    
    <?php    
	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>In this step, you are asked to specify all parameters relative
        to the analysis of your images.</p>";
	} else {
	  echo "<p>Here, you can create template parameters relative to the
      analysis procedure.</p>";
	}
	?>
        <p>These are the choice for colocalization analysis, colocalization coefficients and maps.</p>

    <?php        
	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>'Template analysis parameters' created by your facility
        manager can be copied to the list of 'Your analysis parameters' and
        adapted to fit your analysis needs.</p>";
	} else {
	  echo "<p>The created templates will be visible for the users in an
      additional selection field from which they can be copied to the user's
      parameters.</p>";
	}
	?>

    </div>
        
    <div id="message">
<?php

echo "<p>$message</p>";

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

/*
 * Tooltips. 
 * 
 * Define $tooltips array with object id as key and tooltip string as value.
 */
$tooltips = array(
    "controls_create" => "Create a new parameter set with the specified name.",
    "controls_edit" => "Edit the selected parameter set.",
    "controls_clone" => "Copy the selected parameter set to a new one with the specified name.",
    "controls_delete" => "Delete the selected parameter set.",
    "controls_default" => "Sets (or resets) the selected parameter set as the default one.",
    "controls_copyTemplate" => "Copy a template.",
    "controls_back" => "Go back to step 3/5 - Restoration parameters.",
    "controls_forward" => "Continue to step 5/5 - Create job.",
);

include("footer.inc.php");

?>
