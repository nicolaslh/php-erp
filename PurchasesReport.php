<?php
/* Shows a report of purchases from suppliers for the range of selected dates. */
/* This program is under the GNU General Public License, last version. Rafael E. Chacón, 2016-12-18. */
/* This creative work is under the CC BY-NC-SA, later version. Rafael E. Chacón, 2016-12-18. */

// Notes:
// Coding Conventions/Style: http://www.weberp.org/CodingConventions.html

// BEGIN: Functions division ---------------------------------------------------
// END: Functions division -----------------------------------------------------

// BEGIN: Procedure division ---------------------------------------------------
include('includes/session.php');
$Title = _('Purchases from Suppliers');
$ViewTopic = 'PurchaseOrdering';
$BookMark = 'PurchasesReport';
include('includes/header.php');

echo '<p class="page_title_text"><img alt="" src="', $RootPath, '/css/', $Theme,
	'/images/reports.png" title="', // Icon image.
	$Title, '" /> ', // Icon title.
	$Title, '</p>';// Page title.

// Merges gets into posts:
if(isset($_GET['PeriodFrom'])) {// Select period from.
	$_POST['PeriodFrom'] = $_GET['PeriodFrom'];
}
if(isset($_GET['PeriodTo'])) {// Select period to.
	$_POST['PeriodTo'] = $_GET['PeriodTo'];
}
if(isset($_GET['ShowDetails'])) {// Show the budget for the period.
	$_POST['ShowDetails'] = $_GET['ShowDetails'];
}

// Validates the data submitted in the form:
if(isset($_POST['PeriodFrom']) AND isset($_POST['PeriodTo'])) {
	if(Date1GreaterThanDate2($_POST['PeriodFrom'], $_POST['PeriodTo'])) {
		// The beginning is after the end.
		unset($_POST['PeriodFrom']);
		unset($_POST['PeriodTo']);
		prnMsg(_('The beginning of the period should be before or equal to the end of the period. Please reselect the reporting period.'), 'error');
	}
}

// Main code:
if(isset($_POST['PeriodFrom']) AND isset($_POST['PeriodTo']) AND $_POST['Action']!='New') {// If all parameters are set and valid, generates the report:
	echo '<table class="selection">
		<thead>
			<tr>';
	$TableFoot =
			'</tr>
		</thead><tfoot>
			<tr>
				<td colspan="9"><br /><b>' .
					_('Notes') . '</b><br />' .
					_('Original amounts in the supplier\'s currency. GL amounts in the functional currency.') .
				'</td>
			</tr>
		</tfoot><tbody>';// Common table code.
	$TotalGlAmount = 0;
	$TotalGlTax = 0;
	$PeriodFrom = FormatDateForSQL($_POST['PeriodFrom']);
	$PeriodTo = FormatDateForSQL($_POST['PeriodTo']);
	if($_POST['ShowDetails']) {// Parameters: PeriodFrom, PeriodTo, ShowDetails=on.
		echo		'<th>', _('Date'), '</th>
					<th>', _('Purchase Invoice'), '</th>
					<th>', _('Reference'), '</th>
					<th>', _('Original Overall Amount'), '</th>
					<th>', _('Original Overall Taxes'), '</th>
					<th>', _('Original Overall Total'), '</th>
					<th>', _('GL Overall Amount'), '</th>
					<th>', _('GL Overall Taxes'), '</th>
					<th>', _('GL Overall Total'), '</th>', $TableFoot;
		$SupplierId = '';
		$SupplierOvAmount = 0;
		$SupplierOvTax = 0;
		$SupplierGlAmount = 0;
		$SupplierGlTax = 0;
		$Sql = "SELECT
					supptrans.supplierno,
					suppliers.suppname,
					suppliers.currcode,
					supptrans.trandate,
					supptrans.suppreference,
					supptrans.transno,
					supptrans.ovamount,
					supptrans.ovgst,
					supptrans.rate
				FROM supptrans
					INNER JOIN suppliers ON supptrans.supplierno=suppliers.supplierid
				WHERE supptrans.trandate>='" . $PeriodFrom . "'
					AND supptrans.trandate<='" . $PeriodTo . "'
					AND supptrans.`type`=20
				ORDER BY supptrans.supplierno, supptrans.trandate";
		$Result = DB_query($Sql);
		include('includes/CurrenciesArray.php'); // To get the currency name from the currency code.
		foreach($Result as $MyRow) {
			if($MyRow['supplierno'] != $SupplierId) {// If different, prints supplier totals:
				if($SupplierId != '') {// If NOT the first line.
					echo '<tr>',
							'<td colspan="3">&nbsp;</td>',
							'<td class="number">', locale_number_format($SupplierOvAmount, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
							'<td class="number">', locale_number_format($SupplierOvTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
							'<td class="number">', locale_number_format($SupplierOvAmount+$SupplierOvTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
							'<td class="number">', locale_number_format($SupplierGlAmount, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
							'<td class="number">', locale_number_format($SupplierGlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
							'<td class="number">', locale_number_format($SupplierGlAmount+$SupplierGlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
						'</tr>';
				}
				echo '<tr><td colspan="9">&nbsp;</td></tr>';
				echo '<tr><td class="text" colspan="9">', $MyRow['supplierno'], ' - ', $MyRow['suppname'], ' - ', $MyRow['currcode'], ' ', $CurrencyName[$MyRow['currcode']], '</td></tr>';
				$TotalGlAmount += $SupplierGlAmount;
				$TotalGlTax += $SupplierGlTax;
				$SupplierId = $MyRow['supplierno'];
				$SupplierOvAmount = 0;
				$SupplierOvTax = 0;
				$SupplierGlAmount = 0;
				$SupplierGlTax = 0;
			}

			$GlAmount = $MyRow['ovamount']/$MyRow['rate'];
			$GlTax = $MyRow['ovgst']/$MyRow['rate'];
			echo '<tr class="striped_row">
					<td class="centre">', $MyRow['trandate'], '</td>',
					'<td class="number">', $MyRow['transno'], '</td>',
					'<td class="text">', $MyRow['suppreference'], '</td>',
					'<td class="number">', locale_number_format($MyRow['ovamount'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">', locale_number_format($MyRow['ovgst'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number"><a href="', $RootPath, '/SuppWhereAlloc.php?TransType=20&TransNo=', $MyRow['transno'], '&amp;ScriptFrom=PurchasesReport" target="_blank" title="', _('Click to view where allocated'), '">', locale_number_format($MyRow['ovamount']+$MyRow['ovgst'], $_SESSION['CompanyRecord']['decimalplaces']), '</a></td>',
					'<td class="number">', locale_number_format($GlAmount, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">',	locale_number_format($GlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number"><a href="', $RootPath, '/GLTransInquiry.php?TypeID=20&amp;TransNo=', $MyRow['transno'], '&amp;ScriptFrom=PurchasesReport" target="_blank" title="', _('Click to view the GL entries'), '">', locale_number_format($GlAmount+$GlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</a></td>', // RChacon: Should be "Click to view the General Ledger transaction" instead?
				'</tr>';
			$SupplierOvAmount += $MyRow['ovamount'];
			$SupplierOvTax += $MyRow['ovgst'];
			$SupplierGlAmount += $GlAmount;
			$SupplierGlTax += $GlTax;
		}

		// Prints last supplier total:
		echo '<tr>',
				'<td colspan="3">&nbsp;</td>',
				'<td class="number">', locale_number_format($SupplierOvAmount, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
				'<td class="number">', locale_number_format($SupplierOvTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
				'<td class="number">', locale_number_format($SupplierOvAmount+$SupplierOvTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
				'<td class="number">', locale_number_format($SupplierGlAmount, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
				'<td class="number">', locale_number_format($SupplierGlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
				'<td class="number">', locale_number_format($SupplierGlAmount+$SupplierGlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
			'</tr>',
			'<tr><td colspan="9">&nbsp;</td></tr>';

		$TotalGlAmount += $SupplierGlAmount;
		$TotalGlTax += $SupplierGlTax;

	} else {// Parameters: PeriodFrom, PeriodTo, ShowDetails=off.
		// RChacon: Needs to update the table_sort function to use in this table.
		echo		'<th>', _('Supplier Code'), '</th>
					<th>', _('Supplier Name'), '</th>
					<th>', _('Supplier\'s Currency'), '</th>
					<th>', _('Original Overall Amount'), '</th>
					<th>', _('Original Overall Taxes'), '</th>
					<th>', _('Original Overall Total'), '</th>
					<th>', _('GL Overall Amount'), '</th>
					<th>', _('GL Overall Taxes'), '</th>
					<th>', _('GL Overall Total'), '</th>', $TableFoot;
		$Sql = "SELECT
					supptrans.supplierno,
					suppliers.suppname,
					suppliers.currcode,
					SUM(supptrans.ovamount) AS SupplierOvAmount,
					SUM(supptrans.ovgst) AS SupplierOvTax,
					SUM(supptrans.ovamount/supptrans.rate) AS SupplierGlAmount,
					SUM(supptrans.ovgst/supptrans.rate) AS SupplierGlTax
				FROM supptrans
					INNER JOIN suppliers ON supptrans.supplierno=suppliers.supplierid
				WHERE supptrans.trandate>='" . $PeriodFrom . "'
					AND supptrans.trandate<='" . $PeriodTo . "'
					AND supptrans.`type`=20
				GROUP BY
					supptrans.supplierno
				ORDER BY supptrans.supplierno, supptrans.trandate";
		$Result = DB_query($Sql);
		foreach($Result as $MyRow) {
			echo '<tr class="striped_row">
					<td class="text"><a href="', $RootPath, '/SupplierInquiry.php?SupplierID=', $MyRow['supplierno'], '">', $MyRow['supplierno'], '</a></td>',
					'<td class="text">', $MyRow['suppname'], '</td>',
					'<td class="text">', $MyRow['currcode'], '</td>',
					'<td class="number">', locale_number_format($MyRow['SupplierOvAmount'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">', locale_number_format($MyRow['SupplierOvTax'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">', locale_number_format($MyRow['SupplierOvAmount']+$MyRow['SupplierOvTax'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">', locale_number_format($MyRow['SupplierGlAmount'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">', locale_number_format($MyRow['SupplierGlTax'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
					'<td class="number">', locale_number_format($MyRow['SupplierGlAmount']+$MyRow['SupplierGlTax'], $_SESSION['CompanyRecord']['decimalplaces']), '</td>',
				'</tr>';
			$TotalGlAmount += $MyRow['SupplierGlAmount'];
			$TotalGlTax += $MyRow['SupplierGlTax'];
		}
	}
	echo	'<tr>
				<td class="text" colspan="6">&nbsp;</td>
				<td class="number">', locale_number_format($TotalGlAmount, $_SESSION['CompanyRecord']['decimalplaces']), '</td>
				<td class="number">', locale_number_format($TotalGlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>
				<td class="number">', locale_number_format($TotalGlAmount+$TotalGlTax, $_SESSION['CompanyRecord']['decimalplaces']), '</td>
			</tr>',// Prints all suppliers total.
		'</tbody></table>
		<br />
		<form action="', htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8'), '" method="post">
		<input name="FormID" type="hidden" value="', $_SESSION['FormID'], '" />
		<input name="PeriodFrom" type="hidden" value="', $_POST['PeriodFrom'], '" />
		<input name="PeriodTo" type="hidden" value="', $_POST['PeriodTo'], '" />
		<input name="ShowDetails" type="hidden" value="', $_POST['ShowDetails'], '" />
		<div class="centre noprint">', // Form buttons:
			'<button onclick="javascript:window.print()" type="button"><img alt="" src="', $RootPath, '/css/', $Theme,
				'/images/printer.png" /> ', _('Print'), '</button>', // "Print" button.
			'<button name="Action" type="submit" value="New"><img alt="" src="', $RootPath, '/css/', $Theme,
				'/images/reports.png" /> ', _('New Report'), '</button>', // "New Report" button.
			'<button onclick="window.location=\'index.php?Application=PO\'" type="button"><img alt="" src="', $RootPath, '/css/', $Theme,
				'/images/return.svg" /> ', _('Return'), '</button>', // "Return" button.
		'</div>';

} else {
	// Shows a form to allow input of criteria for the report to generate:
	echo '<br />',
		'<form action="', htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'), '" method="post">',
		'<input name="FormID" type="hidden" value="', $_SESSION['FormID'], '" />',
		// Input table:
		'<table class="selection">',
		// Content of the header and footer of the input table:
		'<thead>
			<tr>
				<th colspan="2">', _('Report Parameters'), '</th>
			</tr>
		</thead><tfoot>
			<tr>
				<td colspan="2">',
					'<div class="centre">',
						'<button name="Action" type="submit" value="', _('Submit'), '"><img alt="" src="', $RootPath, '/css/', $Theme,
							'/images/tick.svg" /> ', _('Submit'), '</button>', // "Submit" button.
						'<button onclick="window.location=\'index.php?Application=PO\'" type="button"><img alt="" src="', $RootPath, '/css/', $Theme,
							'/images/return.svg" /> ', _('Return'), '</button>', // "Return" button.
					'</div>',
				'</td>
			</tr>
		</tfoot><tbody>',
		// Content of the body of the input table:
			// Select period from:
			'<tr>',
				'<td><label for="PeriodFrom">', _('Select period from'), '</label></td>';
	if(!isset($_POST['PeriodFrom'])) {
		$_POST['PeriodFrom'] = date($_SESSION['DefaultDateFormat'], strtotime("-1 year", time()));// One year before current date.
	}
	echo 		'<td><input class="date" id="PeriodFrom" maxlength="10" name="PeriodFrom" required="required" size="11" type="text" value="', $_POST['PeriodFrom'], '" />',
					(!isset($_SESSION['ShowFieldHelp']) || $_SESSION['ShowFieldHelp'] ? _('Select the beginning of the reporting period') : ''), // If it is not set the $_SESSION['ShowFieldHelp'] parameter OR it is TRUE, shows the page help text.
		 		'</td>
			</tr>',
			// Select period to:
			'<tr>',
				'<td><label for="PeriodTo">', _('Select period to'), '</label></td>';
	if(!isset($_POST['PeriodTo'])) {
		$_POST['PeriodTo'] = date($_SESSION['DefaultDateFormat']);
	}
	echo 		'<td><input class="date" id="PeriodTo" maxlength="10" name="PeriodTo" required="required" size="11" type="text" value="', $_POST['PeriodTo'], '" />',
					(!isset($_SESSION['ShowFieldHelp']) || $_SESSION['ShowFieldHelp'] ? _('Select the end of the reporting period') : ''), // If it is not set the $_SESSION['ShowFieldHelp'] parameter OR it is TRUE, shows the page help text.
		 		'</td>
			</tr>',
			// Show the budget for the period:
			'<tr>',
			 	'<td><label>', _('Show details'), '</label></td>
			 	<td><input', (isset($_POST['ShowDetails']) ? ' checked="checked"' : ''), ' name="ShowDetails" type="checkbox">', // "Checked" if ShowDetails is set AND it is TRUE.
			 		(!isset($_SESSION['ShowFieldHelp']) || $_SESSION['ShowFieldHelp'] ? _('Check this box to show purchase invoices') : ''), // If it is not set the $_SESSION['ShowFieldHelp'] parameter OR it is TRUE, shows the page help text.
		 		'</td>
			</tr>',
		 '</tbody></table>';

}
echo	'</form>';
include('includes/footer.php');
// END: Procedure division -----------------------------------------------------
?>
