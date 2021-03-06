<?php
/**
 * Database
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

use ADODB_mysql;
use hrm\job\Job;
use hrm\job\JobDescription;
use hrm\param\base\Parameter;
use hrm\setting\AnalysisSetting;
use hrm\setting\base\Setting;
use hrm\setting\ParameterSetting;
use hrm\setting\TaskSetting;
use hrm\user\UserV2;

require_once dirname(__FILE__) . "/bootstrap.php";

/**
 * Manages the database connection through the ADOdb library.
 *
 * This class abstracts the database back-end and should be used to handle all
 * communication to and from it. Since there are some differences between the
 * databases that still require specialized code, this class officially
 * supports only MySQL and PostgreSQL.
 *
 * @package hrm
 */
class DatabaseConnection
{

    /**
     * Private ADOConnection object.
     * @var ADODB_mysql|\ADODB_postgres8|\ADODB_postgres9
     */
    private $connection;

    /**
     * Maps the Parameter names between HRM and Huygens.
     * @var array
     */
    private $parameterNameDictionary;

    /**
     * DatabaseConnection constructor: creates a database connection.
     */
    public function __construct()
    {
        global $db_type;
        global $db_host;
        global $db_name;
        global $db_user;
        global $db_password;

        $this->connection = ADONewConnection($db_type);
        $this->connection->Connect($db_host, $db_user, $db_password, $db_name);

        // Set the parameter name dictionary
        $this->parameterNameDictionary = array(
            "CCDCaptorSizeX" => "sampleSizesX",       // In HRM there is no distinction between x and y pixel size
            "ZStepSize" => "sampleSizesZ",
            "TimeInterval" => "sampleSizesT",
            "PinholeSize" => "pinhole",
            "NumberOfChannels" => "chanCnt",
            "PinholeSpacing" => "pinholeSpacing",
            "ExcitationWavelength" => "lambdaEx",
            "EmissionWavelength" => "lambdaEm",
            "MicroscopeType" => "mType",
            "NumericalAperture" => "NA",
            "ObjectiveType" => "RILens",
            "SampleMedium" => "RIMedia",
            "unused1" => "iFacePrim",          // PSFGenerationDepth?
            "unused2" => "iFaceScnd",
            "unused3" => "imagingDir",
            "unused4" => "objQuality",
            "unused5" => "photonCnt",
            "unused6" => "exBeamFill",
            "StedDepletionMode" => "stedMode",
            "StedWavelength" => "stedLambda",
            "StedSaturationFactor" => "stedSatFact",
            "StedImmunity" => "stedImmunity",
            "Sted3D" => "sted3D");
    }

    /**
     * Checks whether a connection to the DB is possible.
     * @return boolean True if the connection is possible, false otherwise.
     */
    public function isReachable()
    {
        global $db_type;
        global $db_host;
        global $db_name;
        global $db_user;
        global $db_password;
        /** @var ADODB_mysql|\ADODB_postgres8|\ADODB_postgres9 $connection */
        $connection = ADONewConnection($db_type);
        $result = $connection->Connect($db_host, $db_user, $db_password, $db_name);
        return $result;
    }

    /**
     * Returns the type of the database (mysql, postgres)
     * @return string The type of the database (e.g. mysql, postgres)
     */
    public function type()
    {
        global $db_type;
        return $db_type;
    }

    /**
     * Attempts to get the version of the underlying database.
     * @return string Version of the database (e.g. 2.2.14).
     */
    public function version()
    {
        try {
            $query = "SELECT version( );";
            $version = $this->queryLastValue($query);
        } catch (\Exception $e) {
            $version = "Could not get version information.";
        }
        return $version;
    }

    /**
     * Returns the database host name.
     * @return string Name of the database host.
     */
    public function host()
    {
        global $db_host;
        return $db_host;
    }

    /**
     * Returns the database name.
     * @return string Name of the database.
     */
    public function name()
    {
        global $db_name;
        return $db_name;
    }

    /**
     * Returns the name of the database user.
     * @return string Name of the database user.
     */
    public function user()
    {
        global $db_user;
        return $db_user;
    }

    /**
     * Returns the password of the database user.
     * @return string Password of the database user.
     */
    public function password()
    {
        global $db_password;
        return $db_password;
    }

    /**
     * Returns the ADOConnection object.
     * @return \ADORecordSet_mysql|\ADODB_postgres8|\ADODB_postgres9 The connection object.
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * Executes an SQL query.
     * @param string $query SQL query.
     * @return \ADORecordSet_empty|\ADORecordSet_mysql|False Query result.
     */
    public function execute($query)
    {
        /** @var ADODB_mysql|\ADODB_postgres8|\ADODB_postgres9 $connection */
        $connection = $this->connection();
        $result = $connection->Execute($query);
        return $result;
    }

    /**
     * Executes an SQL query and returns the results.
     * @param string $queryString SQL query.
     * @return array|false Result of the query (rows).
     */
    public function query($queryString)
    {
        $resultSet = $this->connection()->Execute($queryString);
        if ($resultSet === false) {
            return False;
        }
        /** @var \ADORecordSet $resultSet */
        $rows = $resultSet->GetRows();
        return $rows;
    }

    /**
     * Executes an SQL query and returns the results.
     * @param string $sql Prepared SQL query.
     * @param array $values Array of values for the prepared query.
     * @return array|false Result of the query (rows).
     */
    public function queryPrepared($sql, array $values)
    {
        $resultSet = $this->connection()->Execute($sql, $values);
        if ($resultSet === false) {
            return False;
        }
        /** @var \ADORecordSet $resultSet */
        $rows = $resultSet->GetRows();
        return $rows;
    }

    /**
     * Executes an SQL query and returns the last row of the results.
     * @param string $queryString SQL query.
     * @return array Last row of the result of the query.
     */
    public function queryLastRow($queryString)
    {
        $rows = $this->query($queryString);
        if (!$rows) {
            return False;
        }
        $result = end($rows);
        return $result;
    }

    /**
     * Executes an SQL query and returns the value in the last column of the
     * last row of the results.
     * @param string $queryString SQL query.
     * @return string Value of the last column of the last row of the result of
     * the query.
     */
    public function queryLastValue($queryString)
    {
        $rows = $this->queryLastRow($queryString);
        if (!$rows) {
            return False;
        }
        $result = end($rows);
        return $result;
    }

    /**
     * Saves the parameter values of the setting object into the database.
     *
     * If the setting already exists, the old values are overwritten, otherwise
     * a new setting is created.
     *
     * @param \hrm\setting\base\Setting $settings Settings object to be saved.
     * @return bool True if saving was successful, false otherwise.
     */
    public function saveParameterSettings(Setting $settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $settingTable = $settings->table();
        $table = $settings->parameterTable();
        if ($settings->isDefault())
            $standard = "t";
        else
            $standard = "f";
        $result = True;
        if (!$this->existsSetting($settings)) {
            $query = "insert into $settingTable values ('$user', '$name'" .
                ", '$standard')";
            $result = $result && $this->execute($query);
        }
        $existsAlready = $this->existsParametersFor($settings);

        foreach ($settings->parameterNames() as $parameterName) {
            $parameter = $settings->parameter($parameterName);
            $parameterValue = $parameter->internalValue();

            if (is_array($parameterValue)) {
                /*! Before, # was used as a separator, but the first element
                  with index zero was always NULL because channels started
                  their indexing at one. To keep backwards compatibility with
                  the database, we use # now as a channel marker, and even the
                  first channel has a # in front of its value "/" separator is
                  used to mark range values for signal to noise ratio.
                */

                /*!
                  @todo Currently there are not longer "range values" (values
                  separated by /). In the future they will be reintroduced.
                  We leave the code in place.
                */
                if (is_array($parameterValue[0])) {
                    $maxChanCnt = $this->getMaxChanCnt();
                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        if ($parameterValue[$i] != null) {
                            $parameterValue[$i] =
                                implode("/", array_filter($parameterValue[$i]));
                        }
                    }
                }
                $parameterValue = "#" . implode("#", $parameterValue);
            }

            if (!$existsAlready) {
                $query = "INSERT INTO $table VALUES ('$user', '$name', " .
                    "'$parameterName', '$parameterValue');";
            } else {
                /* Check that the parameter itself exists. */
                $query = "SELECT name FROM $table WHERE owner='$user' AND " .
                    "setting='$name' AND name='$parameterName' LIMIT 1;";
                $newValue = $this->queryLastValue($query);

                if ($newValue != NULL) {
                    $query = "UPDATE $table SET value = '$parameterValue' " .
                        "WHERE owner='$user' AND setting='$name' " .
                        "AND name='$parameterName';";
                } else {
                    $query = "INSERT INTO $table VALUES ('$user', '$name', "
                        . "'$parameterName', '$parameterValue');";
                }
            }

            // Accumulate the successes (or failures) of the queries. If a query
            // fails, the return of $this->execute() will be === false; otherwise
            // it is an ADORecordSet.
            $result &= ($this->execute($query) !== false);
        }


        return $result;
    }

    /**
     * Save the parameter values of the setting object into the shared tables.
     *
     * @param Setting $settings Settings object to be saved.
     * @param string $targetUserName User name of the user that the Setting is
     * to be shared with.
     * @return bool True if saving was successful, false otherwise.
     */
    public function saveSharedParameterSettings($settings, $targetUserName)
    {
        $owner = $settings->owner();
        $original_user = $owner->name();
        $name = $settings->name();
        $new_owner = new UserV2();
        $new_owner->setName($targetUserName);
        $settings->setOwner($new_owner);
        /** @var ParameterSetting|TaskSetting|AnalysisSetting $settings */
        $settingTable = $settings->sharedTable();
        $table = $settings->sharedParameterTable();
        $result = True;
        if (!$this->existsSharedSetting($settings)) {
            $query = "insert into $settingTable " .
                "(owner, previous_owner, sharing_date, name) values " .
                "('$targetUserName', '$original_user', CURRENT_TIMESTAMP, '$name')";
            $result = $result && $this->execute($query);
        }

        if (!$result) {
            return False;
        }

        // Get the Id
        $query = "select id from $settingTable where " .
            "owner='$targetUserName' AND previous_owner='$original_user' " .
            "AND name='$name'";
        $id = $this->queryLastValue($query);
        if (!$id) {
            return False;
        }

        // Get the parameter names
        $parameterNames = $settings->parameterNames();

        // Add the parameters
        foreach ($parameterNames as $parameterName) {

            $parameter = $settings->parameter($parameterName);
            $parameterValue = $parameter->internalValue();

            if (is_array($parameterValue)) {
                // Before, # was used as a separator, but the first element with
                // index zero was always NULL because channels started their indexing
                // at one. To keep backwards compatibility with the database, we use
                // # now as a channel marker, and even the first channel has a # in
                // front of its value.
                // "/" separator is used to mark range values for signal to noise ratio


                // Special treatment for the PSF parameter.
                if ($parameter->name() == "PSF") {

                    // Create hard links and update paths to the PSF files
                    // to point to the hard-links.
                    $fileServer = new Fileserver($original_user);
                    $parameterValue = $fileServer->createHardLinksToSharedPSFs(
                        $parameterValue, $targetUserName);

                }

                /*!
                  @todo Currently there are not longer "range values" (values
                  separated by /). In the future they will be reintroduced.
                  We leave the code in place.
                */
                if (is_array($parameterValue[0])) {
                    $maxChanCnt = $this->getMaxChanCnt();
                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        if ($parameterValue[$i] != null) {
                            $parameterValue[$i] = implode("/", array_filter($parameterValue[$i]));
                        }
                    }
                }
                $parameterValue = "#" . implode("#", $parameterValue);
            }

            $query = "insert into $table " .
                "(setting_id, owner, setting, name, value) " .
                "values ('$id', '$targetUserName', '$name', " .
                "'$parameterName', '$parameterValue');";
            $result = $result && $this->execute($query);
        }

        return $result;
    }

    /**
     * Loads the parameter values for a setting and returns a copy of the
     * setting with the loaded parameter values.
     *
     * If a value starts with # it is considered to be an array with the first
     * value at the index 0.
     * @param Setting $settings Setting object to be loaded.
     * @return Setting $settings Setting object with loaded values.
     * @todo Debug the switch blog (possibly buggy!)
     */
    public function loadParameterSettings($settings)
    {
        $user = $settings->owner();
        $user = $user->name();
        $name = $settings->name();
        $table = $settings->parameterTable();

        foreach ($settings->parameterNames() as $parameterName) {
            $parameter = $settings->parameter($parameterName);
            $query = "SELECT value FROM $table WHERE owner='$user' AND " .
                "setting='$name' AND name='$parameterName';";

            $newValue = $this->queryLastValue($query);

            if ($newValue == NULL) {

                // See if the Parameter has a usable default
                $newValue = $parameter->defaultValue();
                if ($newValue == NULL) {
                    continue;
                }
            }


            if ($newValue{0} == '#') {
                switch ($parameterName) {
                    case "ExcitationWavelength":
                    case "EmissionWavelength":
                    case "PinholeSize":
                    case "PinholeSpacing":
                    case "SignalNoiseRatio":
                    case "BackgroundOffsetPercent":
                    case "ChromaticAberration":
                    case "StedDepletionMode":
                    case "StedWavelength":
                    case "StedSaturationFactor":
                    case "StedImmunity":
                    case "Sted3D":
                    case "SpimExcMode":
                    case "SpimGaussWidth":
                    case "SpimCenterOffset":
                    case "SpimFocusOffset":
                    case "SpimNA":
                    case "SpimFill":
                    case "SpimDir":
                    case "ColocChannel":
                    case "ColocThreshold":
                    case "ColocCoefficient":
                    case "PSF":
                        /* Extract and continue to explode. */
                        $newValue = substr($newValue, 1);
                    default:
                        $newValues = explode("#", $newValue);
                }

                if (strcmp($parameterName, "PSF") != 0
                    && strpos($newValue, "/")
                ) {
                    $newValue = array();
                    for ($i = 0; $i < count($newValues); $i++) {
                        if (strpos($newValues[$i], "/")) {
                            $newValue[] = explode("/", $newValues[$i]);
                        } else {
                            $newValue[] = array($newValues[$i]);
                        }
                    }
                } else {
                    $newValue = $newValues;
                }
            }

            $parameter->setValue($newValue);
            $settings->set($parameter);
        }

        return $settings;
    }

    /**
     * Loads the parameter values for a setting fro mthe sharead tabled and
     * returns it.
     * @param int $id Setting id.
     * @param string $type Setting type (one of "parameter", "task", "analysis").
     * @return Setting object with loaded values.
     * @throws \Exception
     * @todo Debug the second switch block (probably buggy!)
     */
    public function loadSharedParameterSettings($id, $type)
    {

        // Get the correct objects
        switch ($type) {

            case "parameter":

                $settingTable = ParameterSetting::sharedTable();
                $table = ParameterSetting::sharedParameterTable();
                $settings = new ParameterSetting();
                break;

            case "task":

                $settingTable = TaskSetting::sharedTable();
                $table = TaskSetting::sharedParameterTable();
                $settings = new TaskSetting();
                break;

            case "analysis":

                $settingTable = AnalysisSetting::sharedTable();
                $table = AnalysisSetting::sharedParameterTable();
                $settings = new AnalysisSetting();
                break;

            default:

                throw new \Exception("bad value for type!");
        }

        // Get the setting info
        $query = "select * from $settingTable where id=$id;";
        $response = $this->queryLastRow($query);
        if (!$response) {
            return NULL;
        }

        // Fill the setting
        $settings->setName($response["name"]);
        $user = new UserV2();
        $user->setName($response["owner"]);
        $settings->setOwner($user);

        // Load from shared table
        foreach ($settings->parameterNames() as $parameterName) {
            $parameter = $settings->parameter($parameterName);
            $query = "select value from $table where setting_id=$id and name='$parameterName'";
            $newValue = $this->queryLastValue($query);
            if ($newValue == NULL) {
                // See if the Parameter has a usable default
                $newValue = $parameter->defaultValue();
                if ($newValue == NULL) {
                    continue;
                }
            }
            if ($newValue{0} == '#') {
                switch ($parameterName) {
                    case "ExcitationWavelength":
                    case "EmissionWavelength":
                    case "SignalNoiseRatio":
                    case "BackgroundOffsetPercent":
                    case "ChromaticAberration":
                        /* Extract and continue to explode. */
                        $newValue = substr($newValue, 1);
                    default:
                        $newValues = explode("#", $newValue);
                }

                if (strcmp($parameterName, "PSF") != 0 && strpos($newValue, "/")) {
                    $newValue = array();
                    for ($i = 0; $i < count($newValues); $i++) {
                        //$val = explode("/", $newValues[$i]);
                        //$range = array(NULL, NULL, NULL, NULL);
                        //for ($j = 0; $j < count($val); $j++) {
                        //  $range[$j] = $val[$j];
                        //}
                        //$newValue[] = $range;
                        /*!
                          @todo Currently there are not longer "range values" (values
                                separated by /). In the future they will be reintroduced.
                                We leave the code in place.
                        */
                        if (strpos($newValues[$i], "/")) {
                            $newValue[] = explode("/", $newValues[$i]);
                        } else {
                            $newValue[] = array($newValues[$i]);
                        }
                    }
                } else {
                    $newValue = $newValues;
                }
            }
            //$shiftedNewValue = array(1 => NULL, 2 => NULL, 3 => NULL, 4 => NULL, 5 => NULL);
            //if (is_array($newValue)) {
            //  // start array at 1
            //  for ($i = 1; $i <= count($newValue); $i++) {
            //    $shiftedNewValue[$i] = $newValue[$i - 1];
            //  }
            //}
            //else $shiftedNewValue = $newValue;
            $parameter->setValue($newValue);
            $settings->set($parameter);
        }
        return $settings;
    }

    /**
     * Returns the list of shared templates with the given user.
     * @param string $username Name of the user for whom to query for shared
     * templates.
     * @param string $table Name of the shared table to query.
     * @return array List of shared jobs.
     */
    public function getTemplatesSharedWith($username, $table)
    {
        $query = "SELECT * FROM $table WHERE owner='$username'";
        $result = $this->query($query);
        return $result;
    }

    /**
     * Returns the list of shared templates by the given user.
     * @param string $username Name of the user for whom to query for shared
     * templates.
     * @param string $table Name of the shared table to query.
     * @return array List of shared jobs.
     */
    public function getTemplatesSharedBy($username, $table)
    {
        $query = "SELECT * FROM $table WHERE previous_owner='$username'";
        $result = $this->query($query);
        return $result;
    }

    /**
     * Copies the relevant rows from shared- to user- tables.
     * @param int $id ID of the setting to be copied.
     * @param string $sourceSettingTable Setting table to copy from.
     * @param string $sourceParameterTable Parameter table to copy from.
     * @param string $destSettingTable Setting table to copy to.
     * @param string $destParameterTable Parameter table to copy to.
     * @return bool True if copying was successful; false otherwise.
     */
    public function copySharedTemplate($id, $sourceSettingTable,
                                       $sourceParameterTable, $destSettingTable, $destParameterTable)
    {

        // Get the name of the previous owner (the one sharing the setting).
        $query = "select previous_owner, owner, name from $sourceSettingTable where id=$id";
        $rows = $this->queryLastRow($query);
        if (False === $rows) {
            return False;
        }
        $previous_owner = $rows["previous_owner"];
        $owner = $rows["owner"];
        $setting_name = $rows["name"];

        // Compose the new name of the setting
        $out_setting_name = $previous_owner . "_" . $setting_name;

        // Check if a setting with this name already exists in the target tables
        $query = "select name from $destSettingTable where " .
            "name='$out_setting_name' and owner='$owner'";
        if ($this->queryLastValue($query)) {

            // The setting already exists; we try adding numerical indices
            $n = 1;
            $original_out_setting_name = $out_setting_name;
            while (1) {

                $test_name = $original_out_setting_name . "_" . $n++;
                $query = "select name from $destSettingTable where name='$test_name' and owner='$owner'";
                if (!$this->queryLastValue($query)) {
                    $out_setting_name = $test_name;
                    break;
                }
            }

        }

        // Get all rows from source table for given setting id
        $query = "select * from $sourceParameterTable where setting_id=$id";
        $rows = $this->query($query);
        if (count($rows) == 0) {
            return False;
        }

        // Now add the rows to the destination table
        $ok = True;
        $record = array();
        $this->connection->BeginTrans();
        foreach ($rows as $row) {
            $record["owner"] = $row["owner"];
            $record["setting"] = $out_setting_name;
            $record["name"] = $row["name"];

            // PSF files must be processed differently
            if ($record["name"] == "PSF") {

                // Instantiate a Fileserver object for the target user
                $fileserver = new Fileserver($owner);

                // Get the array of PSF names
                $values = $row["value"];
                if ($values[0] == "#") {
                    $values = substr($values, 1);
                }
                $psfFiles = explode('#', $values);

                // Create hard-links to the target user folder
                $newPSFFiles = $fileserver->createHardLinksFromSharedPSFs(
                    $psfFiles, $owner, $previous_owner);

                // Update the entries for the database
                $record["value"] = "#" . implode('#', $newPSFFiles);

            } else {

                $record["value"] = $row["value"];

            }

            $insertSQL = $this->connection->GetInsertSQL($destParameterTable,
                $record);
            $status = $this->connection->Execute($insertSQL);
            $ok &= !(false === $status);
            if (!$ok) {
                break;
            }
        }

        // If everything went okay, we commit the transaction; otherwise we roll
        // back
        if ($ok) {
            $this->connection->CommitTrans();
        } else {
            $this->connection->RollbackTrans();
            return False;
        }

        // Now add the setting to the setting table
        $query = "select * from $sourceSettingTable where id=$id";
        $rows = $this->query($query);
        if (count($rows) != 1) {
            return False;
        }

        $ok = True;
        $this->connection->BeginTrans();
        $record = array();
        $row = $rows[0];
        $record["owner"] = $row["owner"];
        $record["name"] = $out_setting_name;
        $record["standard"] = 'f';
        $insertSQL = $this->connection->GetInsertSQL($destSettingTable,
            $record);
        $status = $this->connection->Execute($insertSQL);
        $ok &= !(false === $status);

        if ($ok) {
            $this->connection->CommitTrans();
        } else {
            $this->connection->RollbackTrans();
            return False;
        }

        // Now we can delete the records from the source tables. Even if it
        // if it fails we do not roll back, since the parameters were copied
        // successfully.

        // Delete setting entry
        $query = "delete from $sourceSettingTable where id=$id";
        $status = $this->connection->Execute($query);
        if (false === $status) {
            return False;
        }

        // Delete parameter entries
        $query = "delete from $sourceParameterTable where setting_id=$id";
        $status = $this->connection->Execute($query);
        if (false === $status) {
            return False;
        }

        return True;
    }

    /**
     * Delete the relevant rows from the shared tables.
     * @param int $id ID of the setting to be deleted.
     * @param string $sourceSettingTable Setting table to copy from.
     * @param string $sourceParameterTable Parameter table to copy from.
     * @return bool  True if deleting was successful; false otherwise.
     */
    public function deleteSharedTemplate($id, $sourceSettingTable,
                                         $sourceParameterTable)
    {

        // Initialize success
        $ok = True;

        // Delete shared PSF files if any exist
        if ($sourceParameterTable == "shared_parameter") {
            $query = "select value from $sourceParameterTable where setting_id=$id and name='PSF'";
            $psfFiles = $this->queryLastValue($query);
            if (NULL != $psfFiles && $psfFiles != "#####") {
                if ($psfFiles[0] == "#") {
                    $psfFiles = substr($psfFiles, 1);
                }

                // Extract PSF file paths from the string
                $psfFiles = explode("#", $psfFiles);

                // Delete them
                Fileserver::deleteSharedFSPFilesFromBuffer($psfFiles);
            }
        }

        // Delete setting entry
        $query = "delete from $sourceSettingTable where id=$id";
        $status = $this->connection->Execute($query);
        $ok &= !(false === $status);

        // Delete parameter entries
        $query = "delete from $sourceParameterTable where setting_id=$id";
        $status = $this->connection->Execute($query);
        $ok &= !(false === $status);

        return $ok;
    }

    /**
     * Updates the default entry in the database according to the default
     * value in the setting.
     * @param Setting $settings Settings object to be used to update the default.
     * @return array query result.
     */
    public function updateDefault($settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        if ($settings->isDefault())
            $standard = "t";
        else
            $standard = "f";
        $table = $settings->table();
        $query = "update $table set standard = '$standard' where owner='$user' and name='$name'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Deletes the setting and all its parameter values from the database.
     * @param Setting $settings Settings object to be used to delete all entries
     * from the database.
     * @return bool true if the setting and all parameters were deleted from the
     * database; false otherwise.
     */
    public function deleteSetting($settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $result = True;
        $table = $settings->parameterTable();
        $query = "delete from $table where owner='$user' and setting='$name'";
        $result = $result && $this->execute($query);
        if (!$result) {
            return FALSE;
        }
        $table = $settings->table();
        $query = "delete from $table where owner='$user' and name='$name'";
        $result = $result && $this->execute($query);
        return $result;
    }

    /**
     * Checks whether parameters are already stored for a given setting.
     * @param Setting $settings Settings object to be used to check for
     * existence in the database.
     * @return bool True if the parameters exist in the database; false otherwise.
     */
    public function existsParametersFor($settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->parameterTable();
        $query = "select name from $table where owner='$user' and setting='$name' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /**
     * Checks whether parameters are already stored for a given shared setting.
     * @param ParameterSetting|TaskSetting|AnalysisSetting $settings Settings object to be used to check for existence
     * in the database.
     * @return bool  True if the parameters exist in the database; false otherwise.
     */
    public function existsSharedParametersFor($settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->sharedParameterTable();
        $query = "select name from $table where owner='$user' and setting='$name' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /**
     * Checks whether settings exist in the database for a given owner.
     *
     * @param Setting $settings Settings object to be used to check for
     * existence in the database (the name of the owner must be set in the
     * settings).
     * @return bool True if the settings exist in the database; false otherwise.
     */
    public function existsSetting(Setting $settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->table();
        $query = "select standard from $table where owner='$user' and name='$name' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /**
     * Checks whether shared settings exist in the database for a given owner.
     * @param ParameterSetting|TaskSetting|AnalysisSetting $settings $settings Settings object to be used to check for
     * existence in the database (the name of the owner must be set in the
     * settings)
     * @return bool True if the settings exist in the database; false otherwise.
     */
    public function existsSharedSetting($settings)
    {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->sharedTable();
        $query = "select standard from $table where owner='$user' and name='$name' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /**
     * Adds all files for a given job id and user to the database.
     * @param string $id Job id.
     * @param string $owner Name of the user that owns the job.
     * @param array $files Array of file names.
     * @param bool $autoseries True if the series is to be loaded automatically, false otherwise.
     * @return bool True if the job files could be saved successfully; false
     * otherwise.
     */
    public function saveJobFiles($id, $owner, $files, $autoseries)
    {
        $result = True;
        /** @var UserV2 $owner */
        $username = $owner->name();
        $sqlAutoSeries = "";
        foreach ($files as $file) {
            if (strcasecmp($autoseries, "TRUE") == 0 || strcasecmp($autoseries, "T") == 0) {
                $sqlAutoSeries = "T";
            }
            $slashesFile = addslashes($file);
            $query = "insert into job_files values ('$id', '$username', '$slashesFile', '$sqlAutoSeries')";
            $result = $result && $this->execute($query);
        }
        return $result;
    }

    /**
     * Adds a job for a given job id and user to the queue.
     * @param string $id Job id.
     * @param string $username Name of the user that owns the job.
     * @return array Query result.
     */
    public function queueJob($id, $username)
    {
        $query = "insert into job_queue (id, username, queued, status) values ('$id', '$username', NOW(), 'queued')";
        return $this->execute($query);
    }

    /**
     * Assigns priorities to the jobs in the queue.
     * @return True if assigning priorities was successful.
     */
    public function setJobPriorities()
    {

        $result = True;

        ////////////////////////////////////////////////////////////////////////////
        //
        // First we analyze the queue
        //
        ////////////////////////////////////////////////////////////////////////////

        // Get the number of users that currently have jobs in the queue
        $users = $this->execute("SELECT DISTINCT( username ) FROM job_queue;");
        $row = $this->execute("SELECT COUNT( DISTINCT( username ) ) FROM job_queue;")->FetchRow();
        $numUsers = $row[0];

        // 'Highest' priority (i.e. lowest value) is 0
        $currentPriority = 0;

        // First, we make sure to give the highest priorities to paused and
        // broken jobs
        $rs = $this->execute("SELECT id FROM job_queue WHERE status = 'broken' OR status = 'paused';");
        if ($rs) {
            while ($row = $rs->FetchRow()) {

                // Update the priority for current job id
                $query = "UPDATE job_queue SET priority = " . $currentPriority++ .
                    " WHERE id = '" . $row[0] . "';";

                $rs = $this->execute($query);
                if (!$rs) {
                    Log::error("Could not update priority for key " . $row[0]);
                    $result = False;
                    return $result;
                }

            }
        }

        // Then, we go through to running jobs
        $rs = $this->execute("SELECT id FROM job_queue WHERE status = 'started';");
        if ($rs) {
            while ($row = $rs->FetchRow()) {

                // Update the priority for current job id
                $query = "UPDATE job_queue SET priority = " . $currentPriority++ .
                    " WHERE id = '" . $row[0] . "';";

                $rs = $this->execute($query);
                if (!$rs) {
                    Log::error("Could not update priority for key " . $row[0]);
                    $result = False;
                    return $result;
                }
            }
        }

        // Then we organize the queued jobs in a way that lets us then assign
        // priorities easily in a second pass
        $numJobsPerUser = array();
        $userJobs = array();
        for ($i = 0; $i < $numUsers; $i++) {
            // Get current username
            $row = $users->FetchRow();
            $username = $row[0];
            $query = "SELECT id
        FROM job_queue, job_files
        WHERE job_queue.id = job_files.job AND
          job_queue.username = job_files.owner AND
          job_queue.username = '$username' AND
          status = 'queued'
        ORDER BY job_queue.queued asc, job_files.file asc";
            $rs = $this->execute($query);
            if ($rs) {
                $userJobs[$i] = array();
                $counter = 0;
                while ($row = $rs->FetchRow()) {
                    $userJobs[$i][$counter++] = $row[0];
                }
                $numJobsPerUser[$i] = $counter;
            }
        }

        // Now we can assign priorities to the queued jobs -- minimum priority is 1
        // above the priorities assigned to all other types of jobs
        $maxNumJobs = max($numJobsPerUser);
        for ($j = 0; $j < $maxNumJobs; $j++) {
            for ($i = 0; $i < $numUsers; $i++) {
                if ($j < count($userJobs[$i])) {
                    // Update the priority for current job id
                    $query = "UPDATE job_queue SET priority = " .
                        $currentPriority . " WHERE id = '" .
                        $userJobs[$i][$j] . "';";

                    $rs = $this->execute($query);
                    if (!$rs) {
                        Log::error("Could not update priority for key " . $userJobs[$i][$j]);
                        $result = False;
                        return $result;
                    }
                    $currentPriority++;
                }
            }
        }

        // We can now return true
        return $result;
    }

    /**
     * Logs job information in the statistics table.
     * @param Job $job Job object whose information is to be logged in the
     * database.
     * @param string $startTime Job start time.
     * @return void
     */
    public function updateStatistics(Job $job, $startTime)
    {
        /** @var JobDescription $desc */
        $desc = $job->description();
        $parameterSetting = $desc->parameterSetting();
        $taskSetting = $desc->taskSetting();
        $analysisSetting = $desc->analysisSetting();

        $stopTime = date("Y-m-d H:i:s");
        $id = $desc->id();
        /** @var UserV2 $user */
        $user = $desc->owner();
        $owner = $user->name();
        $group = $user->group();

        $parameter = $parameterSetting->parameter('ImageFileFormat');
        $inFormat = $parameter->value();
        $parameter = $parameterSetting->parameter('PointSpreadFunction');
        $PSF = $parameter->value();
        $parameter = $parameterSetting->parameter('MicroscopeType');
        $microscope = $parameter->value();
        $parameter = $taskSetting->parameter('OutputFileFormat');
        $outFormat = $parameter->value();
        $parameter = $analysisSetting->parameter('ColocAnalysis');
        $colocAnalysis = $parameter->value();

        $query = "insert into statistics values ('$id', '$owner', '$group', " .
            "'$startTime', '$stopTime', '$inFormat', '$outFormat', " .
            "'$PSF', '$microscope', '$colocAnalysis')";

        $this->execute($query);

    }

    /**
     * Flattens a multi-dimensional array.
     * @param array $anArray Multi-dimensional array.
     * @return array Flattened array.
     */
    public function flatten($anArray)
    {
        $result = array();
        foreach ($anArray as $row) {
            $result[] = end($row);
        }
        return $result;
    }

    /**
     * Returns the possible values for a given parameter.
     * @param Parameter $parameter Parameter object.
     * @return array Flattened array of possible values.
     */
    public function readPossibleValues($parameter)
    {
        $name = $parameter->name();
        $query = "select value from possible_values where parameter = '$name';";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /**
     * Returns the translated possible values for a given parameter.
     * @param Parameter $parameter Parameter object.
     * @return array Flattened array of translated possible values.
     */
    public function readTranslatedPossibleValues($parameter)
    {
        $name = $parameter->name();
        $query = "select translation from possible_values where parameter = '$name';";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /**
     * Returns the translation of current value for a given parameter.
     * @param string $parameterName Name of the Parameter object.
     * @param string $value Value for which a translation should be returned.
     * @return string Translated value.
     */
    public function translationFor($parameterName, $value)
    {
        $query = "select translation from possible_values where parameter = '$parameterName' and value = '$value';";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Returns the translation of a hucore value.
     * @param string $parameterName Name of the Parameter object.
     * @param string $hucorevalue Value name in HuCore.
     * @return string Expected value by HRM.
     */
    public function hucoreTranslation($parameterName, $hucorevalue)
    {
        $query = "select value from possible_values where parameter = '" . $parameterName . "' and translation = '" . $hucorevalue . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Returns an array of all file extensions.
     * @return array Array of file extensions.
     */
    public function allFileExtensions()
    {
        $query = "select distinct extension from file_extension";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /**
     * Returns an array of all extensions for multi-dataset files.
     * @return array Array of file extensions for multi-dataset files.
     */
    public function allMultiFileExtensions()
    {
        $query = "SELECT name FROM file_format, file_extension
        WHERE file_format.name = file_extension.file_format
        AND file_format.ismultifile LIKE 't'";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /**
     * Returns an array of file extensions associated to a given file format.
     * @param string $imageFormat File format.
     * @return array Array of file extensions.
     */
    public function fileExtensions($imageFormat)
    {
        $query = "select distinct extension from file_extension where file_format = '$imageFormat';";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /**
     * Returns all restrictions for a given numerical parameter.
     * @param Parameter $parameter Parameter (object).
     * @return array Array of restrictions.
     */
    public function readNumericalValueRestrictions(Parameter $parameter)
    {
        $name = $parameter->name();
        $query = "select min, max, min_included, max_included, standard from boundary_values where parameter = '$name';";
        $result = $this->queryLastRow($query);
        if (!$result) {
            $result = array(null, null, null, null, null);
        }
        return $result;
    }

    /**
     * Returns the file formats that fit the conditions expressed by the
     * parameters.
     * @param bool $isSingleChannel Set whether the file format must be single
     * channel (True), multi channel (False) or if it doesn't matter (NULL).
     * @param bool $isVariableChannel Set whether the number of channels must be
     * variable (True), fixed (False) or if it doesn't matter (NULL).
     * @param bool $isFixedGeometry Set whether the geometry (xyzt) must be fixed
     * (True), variable (False) or if it doesn't  matter (NULL).
     * @return array Array of file formats.
     * @todo Check if this method is still used.
     */
    public function fileFormatsWith($isSingleChannel, $isVariableChannel, $isFixedGeometry)
    {
        $isSingleChannelValue = 'f';
        $isVariableChannelValue = 'f';
        $isFixedGeometryValue = 'f';
        if ($isSingleChannel) {
            $isSingleChannelValue = 't';
        }
        if ($isVariableChannel) {
            $isVariableChannelValue = 't';
        }
        if ($isFixedGeometry) {
            $isFixedGeometryValue = 't';
        }
        $conditions = array();
        if ($isSingleChannel != NULL) {
            $conditions['isSingleChannel'] = $isSingleChannelValue;
        }
        if ($isVariableChannel != NULL) {
            $conditions['isVariableChannel'] = $isVariableChannelValue;
        }
        if ($isFixedGeometry != NULL) {
            $conditions['isFixedGeometry'] = $isFixedGeometryValue;
        }
        return $this->retrieveColumnFromTableWhere('name', 'file_format', $conditions);
    }

    /**
     * Returns the geometries (XY, XY-time, XYZ, XYZ-time) fit the conditions
     * expressed by the parameters.
     * @param bool $isThreeDimensional True if 3D.
     * @param bool $isTimeSeries True if time-series.
     * @return array Array of geometries.
     * @todo Check if this method is still used.
     */
    public function geometriesWith($isThreeDimensional, $isTimeSeries)
    {
        $isThreeDimensionalValue = 'f';
        $isTimeSeriesValue = 'f';
        if ($isThreeDimensional) {
            $isThreeDimensionalValue = 't';
        }
        if ($isTimeSeries) {
            $isTimeSeriesValue = 't';
        }
        $conditions = array();
        if ($isThreeDimensional != NULL) {
            $conditions['isThreeDimensional'] = $isThreeDimensionalValue;
        }
        if ($isTimeSeries != NULL) {
            $conditions['isTimeSeries'] = $isTimeSeriesValue;
        }
        return $this->retrieveColumnFromTableWhere("name", "geometry", $conditions);
    }

    /**
     * Return all values from the column from the table where the condition
     * evaluates to true.
     * @param string $column Name of the column from which the values are taken
     * @param string $table Name of the table from which the values are taken
     * @param array $conditions Array of conditions that the result values must
     * fulfill. This is an array with column names as indices and boolean values
     * as content.
     * @return array Array of values.
     */
    public function retrieveColumnFromTableWhere($column, $table, $conditions)
    {
        $query = "select distinct $column from $table where ";
        foreach ($conditions as $eachName => $eachValue) {
            $query = $query . $eachName . " = '" . $eachValue . "' and ";
        }
        $query = $query . "1 = 1";
        $answer = $this->query($query);
        $result = array();

        if (!empty($answer)) {
            foreach ($answer as $row) {
                $result[] = end($row);
            }
        }

        return $result;
    }

    /**
     * Returns the default value for a given parameter.
     * @param string $parameterName Name of the parameter.
     * @return string Default value.
     */
    public function defaultValue($parameterName)
    {
        $query = "SELECT value FROM possible_values WHERE " .
            "parameter='$parameterName' AND isDefault='t'";
        $result = $this->queryLastValue($query);
        if ($result === False) {
            return NULL;
        }

        return $result;
    }

    /**
     * Returns the id for next job from the queue, sorted by priority.
     * @return string Job id.
     */
    public function getNextIdFromQueue()
    {
        // For the query we join job_queue and job_files, since we want to sort also by file name
        $query = "SELECT id
    FROM job_queue, job_files
    WHERE job_queue.id = job_files.job AND job_queue.username = job_files.owner
    AND job_queue.status = 'queued'
    ORDER BY job_queue.priority desc, job_queue.status desc, job_files.file desc;";
        $result = $this->queryLastValue($query);
        if (!$result) {
            return NULL;
        }
        return $result;
    }

    /**
     * Returns all jobs from the queue, both compound and simple, ordered by
     * priority.
     * @return array All jobs.
     */
    public function getQueueJobs()
    {
        // Get jobs as they are in the queue, compound or not, without splitting
        // them.
        $query = "SELECT id, username, queued, start, server, process_info, status
    FROM job_queue
    ORDER BY job_queue.priority asc, job_queue.queued asc, job_queue.status asc;";
        $result = $this->query($query);
        return $result;
    }

    /**
     * Returns all jobs from the queue, both compound and simple, and the
     * associated file names, ordered by priority.
     * @return array All jobs.
     */
    public function getQueueContents()
    {
        // For the query we join job_queue and job_files, since we want to sort also by file name
        $query = "SELECT id, username, queued, start, stop, server, process_info, status, file
    FROM job_queue, job_files
    WHERE job_queue.id = job_files.job AND job_queue.username = job_files.owner
    ORDER BY job_queue.priority asc, job_queue.queued asc, job_queue.status asc, job_files.file asc
    LIMIT 100";
        $result = $this->query($query);
        return $result;
    }

    /**
     * Returns all jobs from the queue for a given id (that must be unique!)
     * @param string $id Id of the job.
     * @return array All jobs for the id
     */
    public function getQueueContentsForId($id)
    {
        $query = "select id, username, queued, start, server, process_info, status from job_queue where id='$id';";
        $result = $this->queryLastRow($query);  // it is supposed that just one job exists with a given id
        return $result;
    }

    /**
     * Returns all file names associated to a job with given id.
     * @param string $id Job id.
     * @return array Array of file names.
     */
    public function getJobFilesFor($id)
    {
        $query = "select file from job_files where job = '" . $id . "'";
        $result = $this->query($query);
        $result = $this->flatten($result);
        return $result;
    }

    /**
     * Returns the file series mode of a job with given id.
     * @param string $id Job id
     * @return bool True if file series, false otherwise.
     */
    public function getSeriesModeForId($id)
    {
        $query = "select autoseries from job_files where job = '$id';";
        $result = $this->queryLastValue($query);

        return $result;
    }

    /**
     * Returns the name of the user who created the job with given id.
     * @param string $id SId of the job.
     * @return string Name of the user.
     */
    public function userWhoCreatedJob($id)
    {
        $query = "select username from job_queue where id = '$id';";
        $result = $this->queryLastValue($query);
        if (!$result) {
            return NULL;
        }
        return $result;
    }

    /**
     * Deletes job with specified ID from all job tables.
     * @param string $id Id of the job.
     * @return bool True if success, false otherwise.
     */
    public function deleteJobFromTables($id)
    {
        // TODO: Use foreign keys in the database!
        $result = True;
        $result = $result && $this->execute(
                "delete from job_analysis_parameter where setting='$id';");
        $result = $result && $this->execute(
                "delete from job_analysis_setting where name='$id';");
        $result = $result && $this->execute(
                "delete from job_files where job='$id';");
        $result = $result && $this->execute(
                "delete from job_parameter where setting='$id';");
        $result = $result && $this->execute(
                "delete from job_parameter_setting where name='$id';");
        $result = $result && $this->execute(
                "delete from job_queue where id='$id';");
        $result = $result && $this->execute(
                "delete from job_task_parameter where setting='$id';");
        $result = $result && $this->execute(
                "delete from job_task_setting where name='$id';");
        return $result;
    }

    /**
     * Returns the path to hucore on given host.
     * @param string $host Host name.
     * @return string Full path to hucore.
     * @todo Better management of multiple hosts.
     */
    function huscriptPathOn($host)
    {
        $query = "SELECT huscript_path FROM server where name = '$host'";
        $result = $this->queryLastValue($query);
        if (!$result) {
            return NULL;
        }
        return $result;
    }

    /**
     * Get the name of a free server.
     * @return string Name of the free server.
     */
    public function freeServer()
    {
        $query = "select name from server where status='free'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Get the status (i.e. free, busy, paused) of server with given name.
     * @param string $name Name of the server.
     * @return string Status (one of 'free', 'busy', or 'paused').
     */
    public function statusOfServer($name)
    {
        $query = "select status from server where name='$name'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Checks whether server is busy.
     * @param string $name Name of the server.
     * @return bool True if the server is busy, false otherwise
     */
    public function isServerBusy($name)
    {
        $status = $this->statusOfServer($name);
        $result = ($status == 'busy');
        return $result;
    }

    /**
     * Checks whether the switch in the queue manager is 'on'.
     * @return bool True if switch is on, false otherwise.
     */
    public function isSwitchOn()
    {
        // Handle some back-compatibility issue
        if ($this->doGlobalVariablesExist()) {
            $query = "SELECT value FROM queuemanager WHERE field = 'switch'";
            $answer = $this->queryLastValue($query);
            $result = True;
            if ($answer == 'off') {
                $result = False;
                Log::warning("$query; returned '$answer'");
                Util::notifyRuntimeError("hrmd stopped",
                    "$query; returned '$answer'\n\nThe HRM queue manager will stop.");
            }
        } else {
            $query = "select switch from queuemanager";
            $answer = $this->queryLastValue($query);
            $result = True;
            if ($answer == 'off') {
                $result = False;
                Log::warning("$query; returned '$answer'");
                Util::notifyRuntimeError("hrmd stopped",
                    "$query; returned '$answer'\n\nThe HRM queue manager will stop.");
            }
        }

        return $result;
    }

    /**
     * Gets the status of the queue manager's switch.
     * @return string 'on' or 'off'
     */
    public function getSwitchStatus()
    {
        if ($this->doGlobalVariablesExist()) {
            $query = "SELECT value FROM queuemanager WHERE field = 'switch'";
            $answer = $this->queryLastValue($query);
        } else {
            $query = "select switch from queuemanager";
            $answer = $this->queryLastValue($query);
        }
        return $answer;
    }

    /**
     * Sets the status of the queue manager's switch.
     * @param string $status Either 'on' or 'off'
     * @return array Query result.
     */
    public function setSwitchStatus($status)
    {
        $result = $this->execute("UPDATE queuemanager SET value = '$status' WHERE field = 'switch'");
        return $result;
    }

    /**
     * Sets the state of the server to 'busy' and the pid for a running job.
     * @param string $name Server name.
     * @param string $pid Process identifier associated with a running job.
     * @return array Query result.
     */
    public function reserveServer($name, $pid)
    {
        $query = "update server set status='busy', job='$pid' where name='$name'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Sets the state of the server to 'free' and deletes the the pid.
     * @param string $name Server name.
     * @param string $pid Process identifier associated with a running job (UNUSED!).
     * @return array Query result.
     */
    public function resetServer($name, $pid)
    {
        $query = "update server set status='free', job=NULL where name='$name'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Starts a job.
     * @param Job $job Job object.
     * @return array Query result.
     */
    public function startJob(Job $job)
    {
        $desc = $job->description();
        $id = $desc->id();
        $server = $job->server();
        $process_info = $job->pid();
        $query = "update job_queue set start=NOW(), server='$server', process_info='$process_info', status='started' where id='$id'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Get all running jobs.
     * @return array Array of Job objects.
     */
    public function getRunningJobs()
    {
        $result = array();
        $query = "select id, process_info, server from job_queue where status = 'started'";
        $rows = $this->query($query);
        if (!$rows) return $result;

        foreach ($rows as $row) {
            $desc = new JobDescription();
            $desc->setId($row['id']);
            $desc->load();
            $job = new Job($desc);
            $job->setServer($row['server']);
            $job->setPid($row['process_info']);
            $job->setStatus('started');
            $result[] = $job;
        }
        return $result;
    }

    /**
     * Get names of all processing servers (independent of their status).
     * @return array Array of server names.
     */
    public function availableServer()
    {
        $query = "select name from server";
        $result = $this->query($query);
        $result = $this->flatten($result);
        return $result;
    }

    /**
     * Get the starting time of given job object.
     * @param Job $job Job object.
     * @return string Start time.
     */
    public function startTimeOf(Job $job)
    {
        $desc = $job->description();
        $id = $desc->id();
        $query = "select start from job_queue where id = '$id';";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Returns a formatted time from a unix timestamp.
     * @param string $timestamp Unix timestamp.
     * @return string Formatted time string: YYYY-MM-DD hh:mm:ss.
     */
    public function fromUnixTime($timestamp)
    {
        $query = "select FROM_UNIXTIME($timestamp)";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Pauses a job of given id.
     * @param string $id Job id
     * @return array query result.
     */
    public function pauseJob($id)
    {
        $query = "update job_queue set status='paused' where id='$id';";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Sets the end time of a job.
     * @param string $id Job id.
     * @param string $date Formatted date: YYYY-MM-DD hh:mm:ss.
     * @return array Query result.
     */
    public function setJobEndTime($id, $date)
    {
        $query = "update job_queue set stop='$date' where id='$id'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Changes status of 'paused' jobs to 'queued'.
     * @return array Query result
     */
    public function restartPausedJobs()
    {
        $query = "update job_queue set status='queued' where status='paused'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Marks a job with given id as 'broken' (i.e. to be removed).
     * @param string $id Job id.
     * @return array Query result.
     */
    public function markJobAsRemoved($id)
    {
        $query = "update job_queue set status='broken' where (status='queued' or status='paused') and id='$id';";
        // $query = "update job_queue set status='broken' where id='" . $id . "'";
        $result = $this->execute($query);
        $query = "update job_queue set status='kill' where status='started' and id='$id';";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Set the server status to free.
     * @param string $server Server name.
     * @return array Query result.
     */
    public function markServerAsFree($server)
    {
        $query = "update server set status='free', job=NULL where name='$server'";
        $result = $this->execute($query);
        return $result;
    }

    /**
     * Get all jobs with status 'broken'.
     * @return array Array of ids for broken jobs.
     */
    public function getMarkedJobIds()
    {
        $conditions['status'] = 'broken';
        $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
        return $ids;
    }

    /**
     * Get all jobs with status 'kill' to be killed by the Queue Manager.
     * @return array Array of ids for jobs to be killed.
     */
    public function getJobIdsToKill()
    {
        $conditions['status'] = 'kill';
        $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
        return $ids;
    }

    /**
     * Return the list of known users (without the administrator).
     * @param string String User name to filter out from the list (optional).
     * @return array Filtered array of users.
     */
    public function getUserList($name)
    {
        $query = "select name from username where name != '$name' " .
            " and name != 'admin';";
        $result = $this->query($query);
        return $result;
    }

    /**
     * Get the name of the user who owns a job with given id.
     * @param string $id Job id.
     * @return string Name of the user who owns the job.
     */
    public function getJobOwner($id)
    {
        $query = "select username from job_queue where id = '$id'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Returns current database (!) date and time.
     * @return string formatted date (YYYY-MM-DD hh:mm:ss).
     */
    public function now()
    {
        $query = "select now()";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Returns the group to which the user belongs.
     * @param string $userName Name of the user
     * @return string Group name.
     */
    public function getGroup($userName)
    {
        $query = "SELECT research_group FROM username WHERE name= '$userName'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /**
     * Updates the e-mail address of a user.
     * @param string $userName Name of the user.
     * @param string $email E-mail address.
     * @return array Query result.
     */
    public function updateMail($userName, $email)
    {
        $cmd = "UPDATE username SET email = '$email' WHERE name = '$userName'";
        $result = $this->execute($cmd);
        return $result;
    }

    /**
     * Gets the maximum number of channels from the database.
     * @return int The number of channels.
     */
    public function getMaxChanCnt()
    {
        $query = "SELECT MAX(CAST(value AS unsigned)) as \"\"";
        $query .= "FROM possible_values WHERE parameter='NumberOfChannels'";
        $result = trim($this->execute($query));

        if (!is_numeric($result)) {
            $result = 5;
        }

        return $result;
    }


    /**
     * Get the list of settings for the user with given name from the given
     * settings table.
     *
     * The Parameter values are not loaded.
     * @param string $username Name of the user.
     * @param string $table Name of the settings table.
     * @return array Array of settings
     */
    public function getSettingList($username, $table)
    {
        $query = "select name, standard from $table where owner ='$username' order by name";
        return ($this->query($query));
    }

    /**
     * Get the parameter confidence level for given file format.
     * @param string $fileFormat File format for which the Parameter confidence
     * level is queried (not strictly necessary for the Parameters with
     * confidence level 'Provide', could be set to '' for those).
     * @param string $parameterName Name of the Parameter the confidence level
     * should be returned.
     * @return string Parameter confidence level.
     */
    public function getParameterConfidenceLevel($fileFormat, $parameterName)
    {
        // Some Parameters MUST be provided by the user and cannot be overridden
        // by the file metadata
        switch ($parameterName) {
            case 'ImageFileFormat' :
            case 'NumberOfChannels' :
            case 'PointSpreadFunction':
            case 'MicroscopeType' :
            case 'CoverslipRelativePosition':
            case 'PerformAberrationCorrection':
            case 'AberrationCorrectionMode':
            case 'AdvancedCorrectionOptions':
            case 'PSF' :
                return "provided";
            case 'Binning':
            case 'IsMultiChannel':
            case 'ObjectiveMagnification':
            case 'CMount':
            case 'TubeFactor':
            case 'AberrationCorrectionNecessary':
            case 'CCDCaptorSize':
            case 'PSFGenerationDepth':
                return "default";
            default:

                // For the other Parameters, the $fileFormat must be specified
                if (($fileFormat == '') && ($fileFormat == null)) {
                    exit("Error: please specify a file format!" . "\n");
                }

                // The wavelength and voxel size parameters have a common
                // confidence in HRM but two independent confidences in hucore
                if (($parameterName == "ExcitationWavelength") ||
                    ($parameterName == "EmissionWavelength")
                ) {

                    $confidenceLevelEx = $this->huCoreConfidenceLevel(
                        $fileFormat, "ExcitationWavelength");
                    $confidenceLevelEm = $this->huCoreConfidenceLevel(
                        $fileFormat, "EmissionWavelength");
                    $confidenceLevel = $this->minConfidenceLevel(
                        $confidenceLevelEx, $confidenceLevelEm);

                } elseif (($parameterName == "CCDCaptorSizeX") ||
                    ($parameterName == "ZStepSize")
                ) {

                    $confidenceLevelX = $this->huCoreConfidenceLevel(
                        $fileFormat, "CCDCaptorSizeX");
                    $confidenceLevelZ = $this->huCoreConfidenceLevel(
                        $fileFormat, "ZStepSize");
                    $confidenceLevel = $this->minConfidenceLevel(
                        $confidenceLevelX, $confidenceLevelZ);

                } else {

                    $confidenceLevel = $this->huCoreConfidenceLevel(
                        $fileFormat, $parameterName);

                }

                // Return the confidence level
                return $confidenceLevel;

        }

    }

    /**
     * Finds out whether a Huygens module is supported by the license.
     * @param  string $feature The module to find out about. It can use (SQL)
     * wildcards.
     * @return bool True if the module is supported by the license, false
     * otherwise.
     */
    public function hasLicense($feature)
    {

        $query = "SELECT feature FROM hucore_license WHERE " .
            "feature LIKE '$feature' LIMIT 1;";

        if ($this->queryLastValue($query) === FALSE) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Checks whether Huygens Core has a valid license.
     * @return bool True if the license is valid, false otherwise.
     */
    public function hucoreHasValidLicense()
    {

        // We (ab)use the hasLicense() method
        return ($this->hasLicense("freeware") == false);
    }

    /**
     * Gets the licensed server type for Huygens Core.
     * @return string One of desktop, small, medium, large, extreme.
     */
    public function hucoreServerType()
    {

        $query = "SELECT feature FROM hucore_license WHERE feature LIKE 'server=%';";
        $server = $this->queryLastValue($query);
        if ($server == false) {
            return "no server information";
        }
        return substr($server, 7);
    }

    /**
     * Gets the (active) licenses store in the hucore_license table.
     * @return array of licenses.
     */
    public function getActiveLicenses()
    {
        $query = "SELECT feature FROM hucore_license;";
        $licenses = $this->query($query);
        $licenses = $this->flatten($licenses);
        return $licenses;
    }

    /**
     * Updates the database with the current HuCore license details.
     * @param string $licDetails A string with the supported license features.
     * @return bool True if the license details were successfully saved, false
     * otherwise.
     */
    public function storeLicenseDetails($licDetails)
    {

        $licStored = true;

        // Make sure that the hucore_license table exists.
        $tables = $this->connection->MetaTables("TABLES");
        if (!in_array("hucore_license", $tables)) {
            $msg = "Table hucore_license does not exist! " .
                "Please update the database!";
            Log::error($msg);
            exit($msg);
        }

        // Empty table: remove existing values from older licenses.
        $query = "DELETE FROM hucore_license";
        $result = $this->execute($query);

        if (!$result) {
            Log::error("Could not store license details in the database!\n");
            $licStored = false;
            return $licStored;
        }

        // Populate the table with the new license.
        $features = explode(" ", $licDetails);
        foreach ($features as $feature) {

            Log::info("Storing license feature: " . $feature . PHP_EOL);

            switch ($feature) {
                case 'desktop':
                case 'small':
                case 'medium':
                case 'large':
                case 'extreme':
                    $feature = "server=" . $feature;
                    Log::info("Licensed server: $feature");
                    break;
                default:
                    Log::info("Licensed feature: $feature");
            }

            $query = "INSERT INTO hucore_license (feature) VALUES ('$feature')";
            $result = $this->execute($query);

            if (!$result) {
                Log::error("Could not store license feature
                    '$feature' in the database!\n");
                $licStored = false;
                break;
            }
        }

        return $licStored;
    }

    /**
     * Store the confidence levels returned by huCore into the database for
     * faster retrieval.
     *
     * This is a rather low-level function that creates the table if needed.
     *
     * @param array $confidenceLevels Array of confidence levels with file
     * formats as keys.
     * @return bool True if storing (or updating) the database was successful,
     * false otherwise.
     */
    public function storeConfidenceLevels($confidenceLevels)
    {

        // Make sure that the confidence_levels table exists
        $tables = $this->connection->MetaTables("TABLES");
        if (!in_array("confidence_levels", $tables)) {
            $msg = "Table confidence_levels does not exist! " .
                "Please update the database!";
            Log::error($msg);
            exit($msg);
        }

        // Get the file formats
        $fileFormats = array_keys($confidenceLevels);

        // Go over all $confidenceLevels and set the values
        foreach ($fileFormats as $format) {

            // If the row for current $fileFormat does not exist, INSERT a new
            // row with all parameters, otherwise UPDATE the existing one.
            $query = "SELECT fileFormat FROM confidence_levels WHERE " .
                "fileFormat = '" . $format . "' LIMIT 1;";

            if ($this->queryLastValue($query) === FALSE) {

                // INSERT
                if (!$this->connection->AutoExecute("confidence_levels",
                    $confidenceLevels[$format], "INSERT")
                ) {
                    $msg = "Could not insert confidence levels for file format $format!";
                    Log::error($msg);
                    exit($msg);
                }

            } else {

                // UPDATE
                if (!$this->connection->AutoExecute("confidence_levels",
                    $confidenceLevels[$format], 'UPDATE',
                    "fileFormat = '$format'")
                ) {
                    $msg = "Could not update confidence levels for file format $format!";
                    Log::error($msg);
                    exit($msg);
                }

            }

        }

        return true;

    }


    /**
     * Get the state of GPU acceleration (as string).
     * @return string One "true" or "false".
     */
    public function getGPUID($server)
    {
        $query = "SELECT gpuId FROM server WHERE name = '$server';";
        
        $result = $this->queryLastValue($query);

        return intval($result);
    }


    /**
     * Add a server (including GPU info) to the list of processing machines
       for the queue manager.
     * @return integer > 0 on failure; 0 on success.
     */
    public function addServer($serverName, $huPath, $gpuId)
    {
        if ($gpuId != "" && !is_numeric($gpuId)) {
            return "error: invalid GPU ID";
        }

        $tableName = "server";

        /* This allows for multiple entries for the same machine. */
        /* The queue manager only looks at the machine name, rejecting
           anything after the blank. */        
        if ($gpuId != "") {
            $serverName = "$serverName $gpuId";
            $record['gpuId'] = $gpuId;
        }
        
        $record['name'] = $serverName;
        $record['huscript_path'] = $huPath;
        $record['status'] = 'free';
        
        $insertSQL = $this->connection->GetInsertSQL($tableName, $record);
        $status = $this->connection->Execute($insertSQL);
        
        return $status;
    }


    /**
     * Remove a server from the list of processing machines for the queue
       manager.
     * @return integer > 0 on failure; 0 on success.
     */
    public function removeServer($serverName)
    {
        $query = "DELETE FROM server WHERE name='$serverName';";
        $result = $this->queryLastValue($query);

        return intval($result);
    }

    public function getAllServers()
    {
        $query = "SELECT * FROM server;";

        $result = $this->query($query);

        return $result;
    }


    /* ------------------------ PRIVATE FUNCTIONS --------------------------- */

    /**
     * Return the minimum of two confidence levels.
     *
     * The order of the levels is as follows:
     *
     *  'default' < 'estimated' < 'reported' < 'verified' < 'asIs'.
     *
     * @param string $level1 One of 'default', 'estimated', 'reported',
     * 'verified', 'asIs'.
     * @param string $level2 One of 'default', 'estimated', 'reported',
     * 'verified', 'asIs'.
     * @return string The minimum of the two confidence levels.
     */
    private function minConfidenceLevel($level1, $level2)
    {
        $levels = array();
        $levels['default'] = 0;
        $levels['estimated'] = 1;
        $levels['reported'] = 2;
        $levels['verified'] = 3;
        $levels['asIs'] = 3;

        if ($levels[$level1] <= $levels[$level2]) {
            return $level1;
        } else {
            return $level2;
        }

    }

    /**
     * Returns the raw HuCore confidence level.
     * @param string $fileFormat HRM's file format.
     * @param string $parameterName Name of the HRM Parameter.
     * @return string HuCore's raw confidence level.
     */
    private function huCoreConfidenceLevel($fileFormat, $parameterName)
    {

        // Get the mapped file format
        $query = "SELECT hucoreName FROM file_format WHERE name = '" .
            $fileFormat . "' LIMIT 1";
        $hucoreFileFormat = $this->queryLastValue($query);
        if (!$hucoreFileFormat) {
            Log::warning("Could not get the mapped file name for " . $fileFormat . "!");
            return "default";
        }

        // Use the mapped file format to retrieve the
        if (!array_key_exists($parameterName, $this->parameterNameDictionary)) {
            return "default";
        }
        $query = "SELECT " . $this->parameterNameDictionary[$parameterName] .
            " FROM confidence_levels WHERE fileFormat = '" . $hucoreFileFormat .
            "' LIMIT 1;";
        $confidenceLevel = $this->queryLastValue($query);
        if (!$confidenceLevel) {
            Log::warning("Could not get the confidence level for " . $fileFormat . "!");
            return "default";
        }

        // return the confidence level
        return $confidenceLevel;
    }

    /**
     * Ugly hack to check for old table structure.
     * @return bool True if global variables table exists, false otherwise.
     */
    private function doGlobalVariablesExist()
    {
        global $db_type;
        global $db_host;
        global $db_name;
        global $db_user;
        global $db_password;

        $test = False;

        $dsn = $db_type . "://" . $db_user . ":" . $db_password . "@" . $db_host . "/" . $db_name;
        /** @var ADODB_mysql|\ADODB_postgres8|\ADODB_postgres9 $db */
        $db = ADONewConnection($dsn);
        if (!$db)
            return False;
        $tables = $db->MetaTables("TABLES");
        if (in_array("global_variables", $tables))
            $test = True;

        return $test;
    }

}
