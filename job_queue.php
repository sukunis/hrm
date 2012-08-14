<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/JobDescription.inc.php");
require_once("./inc/JobQueue.inc.php");

session_start();

$queue = new JobQueue();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) &&
    !strstr($_SERVER['HTTP_REFERER'], 'job_queue')) {
        $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_POST['delete'])) {
  if (isset($_POST['jobs_to_kill'])) {
    $queue->markJobsAsRemoved($_POST['jobs_to_kill'],
        $_SESSION['user']->name());
  }
}
else if (isset($_POST['update']) && $_POST['update']=='update') {
  // nothing to do
}

$meta = "<meta http-equiv=\"refresh\" content=\"10\" />";

$script = "queue.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <?php $referer = $_SESSION['referer']; ?>
            <li>
                <a href="<?php echo $referer;?>">
                    <img src="images/back_small.png" alt="back" />&nbsp;Back</a>
            </li>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                    <img src="images/home.png" alt="home" />&nbsp;Home</a>
            </li>
            <li>
                <a href="javascript:openWindow('
                   http://www.svi.nl/HuygensRemoteManagerHelpQueue')">
                    <img src="images/help.png" alt="help" />&nbsp;Help
                </a>
            </li>
        </ul>
    </div>
   
   <div id="joblist">
   <h3><img src="images/queue_small.png" alt="Queue" />&nbsp;Queue status</h3>
    
    <form method="post" action="" id="jobqueue">
    <p>
        <input name="update" type="submit" value="" class="icon update"
            id="controls_refresh" /><?php echo date("l d. F Y, H:i:s"); ?>
   </p>

    <?php

        // Get the total number of jobs
        $rows = $queue->getContents();
        $allJobsInQueue = count($rows);
        /*!
          \todo Activate showStopTime when in place.
        */
        $showStopTime = false;
        $summary = "";

        if ( $allJobsInQueue == 0 ) {
          $summary .= "There are no jobs in the queue.";
        } else {
            foreach ($rows as $r) {
                if ($r['stop'] != "" ) {
                    $showStopTime = true;
                }
            }
          if ( $allJobsInQueue == 1 ) {
            $str = 'is <strong>1 job</strong>';
          } else {
            $str = 'are <strong>' .$allJobsInQueue . ' jobs</strong>';
          }
          $summary .= "There " . $str . " in the queue.";

          if ( !$_SESSION['user']->isAdmin() )  {
              $db = new DatabaseConnection();
              $jobsInQueue = $db->getNumberOfQueuedJobsForUser(
                  $_SESSION['user']->name( ) );

            if ( $jobsInQueue == 0 ) {
              $str = '<strong>no jobs</strong>';
            } elseif ( $jobsInQueue == 1 ) {
              $str = '<strong>1 job</strong>';
            } else {
              $str = '<strong>' .$jobsInQueue . ' jobs</strong>';
            }
            $summary .= " You own " . $str . ".";
          }
        }
        echo "<p id=\"summary\"><img src=\"./images/note.png\" " .
            "alt=\"Summary\" />&nbsp;"
            . $summary . "</p>";
    ?>

    <div id="queue">
            
      <table>
        <tr>
          <td class="del"></td>
          <td class="nr">nr</td>
          <td class="owner">owner</td>
          <td class="files">file(s)</td>
          <td class="created">created</td>
          <td class="status">status</td>
          <td class="started">started</td>
          <?php if ($showStopTime) {
              echo "<td class=\"stop\">estimated end</td>";
          }
          ?>
          <td class="pid">pid</td>
          <td class="server">server</td>
        </tr>

        <?php
            if (count($rows) > 0) {
        ?>
        <tr style="background: #eeeeee">
          <td colspan="9">
             <?php
                if ( !$_SESSION['user']->isAdmin() )  {
                    echo "You can delete jobs owned by yourself.";
                } else {
                    echo "You can delete any jobs.";
                }
                ?>
          </td>
        </tr>
        <?php
            }
        ?>

<?php

if (count($rows) == 0) {
  echo "                    <tr style=\"background: #ffffcc\">" .
    "<td colspan=\"9\">The job queue is empty</td></tr>";
}
else {
  $index = 1;
  foreach ($rows as $row) {
    if ($row['status'] == "started") {
      $color='#99ffcc';
    }
    else if ($row['status'] == "broken" || $row['status'] == "kill") {
      $color='#ff9999';
    }
    else if ($index % 2 == 0) {
      //$color='#f3cba5';
      $color='#ffccff';
    }
    else {
      //$color='#11d6ff';
      $color='#ccccff';
    }

?>
                    <tr style="background: <?php echo $color ?>">
<?php

    if ($row['username'] == $_SESSION['user']->name() ||
            $_SESSION['user']->isAdmin()) {
      if($row['status'] != "broken") {

?>
                            <td>
                                <input name="jobs_to_kill[]"
                                       type="checkbox"
                                       value="<?php echo $row['id'] ?>" />
                            </td>
<?php

      }
      else {

?>
                        <td></td>
<?php

      }
    }
    else {

?>
                        <td></td>
<?php

    }

?>
                        <td><?php echo $index ?></td>
                        <td><?php echo $row['username'] ?></td>
                        <td><?php 
                                echo implode(';',
                                    $queue->getJobFilesFor($row['id']))
                             ?>
                        </td>
                        <td><?php echo $row['queued'] ?></td>
                        <td><?php echo $row['status'] ?></td>
                        <td><?php echo $row['start'] ?></td>
                        <?php if ($showStopTime) {
                            echo "<td>".$row['stop']." </td>";
                        }
                        ?>
                        <td><?php echo $row['process_info'] ?></td> 
                        <td><?php echo $row['server'] ?></td> 		
                    </tr>
<?php

    $index++;
  }
}

?>
                </table>
                
<?php

if (count($rows) != 0) {
    // <input name="jobs_to_kill[]" type="checkbox" value="45a4bd343e852" />

?>
                <label style="padding-left: 3px">
                    <img src="images/arrow.png" alt="arrow" />
                    <a href="javascript:mark()">Check All</a> /
                    <a href="javascript:unmark()">Uncheck All</a>
                </label>
                
                &nbsp;
                
                <label style="font-style: italic">
                    With selected:
                    <input name="delete" type="submit" value=""
                      class="icon delete"
                      id="controls_delete" />
                </label>
<?php

}

?>

        </div> <!-- queue -->

        </form>
  
    </div> <!-- joblist -->

<?php

/*
 * Tooltips. 
 * 
 * Define $tooltips array with object id as key and tooltip string as value.
 */
$tooltips = array(
    "controls_refresh" => "Refresh the queue.",
    "controls_delete" => "Delete selected job(s) from the queue. If a job is running, it will be killed!"
);

include("footer.inc.php");

?>
