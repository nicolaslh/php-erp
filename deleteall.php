<?php

ini_set("display_errors", "On");
error_reporting(E_ALL);
if (!isset($PathPrefix)) {
    $PathPrefix = '';
}
if (!file_exists($PathPrefix . 'config.php')) {
    $RootPath = dirname(htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'));
    if ($RootPath == '/' OR $RootPath == "\\") {
        $RootPath = '';
    }
    header('Location:' . $RootPath . '/install/index.php');
    exit;
}
include($PathPrefix . 'config.php');
if (isset($dbuser)) { //this gets past an upgrade issue where old versions used lower case variable names
    $DBUser = $dbuser;
    $DBPassword = $dbpassword;
    $DBType = $dbType;
}
$_SESSION['DatabaseName'] = "weberpdemo";
//var_dump();exit;
//include($PathPrefix . 'includes/ConnectDB.inc');
include($PathPrefix . 'includes/DateFunctions.inc');
//include($PathPrefix . 'includes/db_c.inc');

include_once($PathPrefix . 'includes/ConnectDB_' . $DBType . '.inc');
//include($PathPrefix . 'includes/GetConfig.php');

$sql = "select stockid from stockmaster";
$result = DB_query($sql, "没找到物料", '', true);
while ($rows = DB_fetch_assoc($result)) {
    echo '<br />';
    $StockID = $rows["stockid"];
    echo $StockID;
    echo '<br />';
    $result = DB_Txn_Begin();
    $result = DB_query($sql, _('Could not delete the location stock records because'), '', true);
    /*Deletes LocStock records*/
    $sql = "DELETE FROM locstock WHERE stockid='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the location stock records because'), '', true);
    /*Deletes Price records*/
    $sql = "DELETE FROM prices WHERE stockid='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the prices for this stock record because'), '', true);
    /*and cascade deletes in PurchData */
    $sql = "DELETE FROM purchdata WHERE stockid='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the purchasing data because'), '', true);
    /*and cascade delete the bill of material if any */
    $sql = "DELETE FROM bom WHERE parent='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the bill of material because'), '', true);
//and cascade delete the item properties
    $sql = "DELETE FROM stockitemproperties WHERE stockid='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the item properties'), '', true);
//and cascade delete the item descriptions in other languages
    $sql = "DELETE FROM stockdescriptiontranslations WHERE stockid='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the item language descriptions'), '', true);
    $sql = "DELETE FROM stockmaster WHERE stockid='" . $StockID . "'";
    $result = DB_query($sql, _('Could not delete the item record'), '', true);
    $result = DB_Txn_Commit();
    prnMsg(_('Deleted the stock master record for') . ' ' . $StockID . '....' .
        '<br />. . ' . _('and all the location stock records set up for the part') .
        '<br />. . .' . _('and any bill of material that may have been set up for the part') .
        '<br /> . . . .' . _('and any purchasing data that may have been set up for the part') .
        '<br /> . . . . .' . _('and any prices that may have been set up for the part'), 'success');
    echo '<br />';
    var_dump("DB_Txn_Commit result", $result);
    echo '<br />';
}
echo "success";
exit;