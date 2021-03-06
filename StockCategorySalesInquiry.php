<?php


include('includes/session.php');
$Title = _('Sales By Category By Item Inquiry');
include('includes/header.php');

echo '<p class="page_title_text"><img src="' . $RootPath . '/css/' . $Theme . '/images/transactions.png" title="' . _('Sales Report') . '" alt="" />' . ' ' . _('Sales By Category By Item Inquiry') . '</p>';
echo '<div class="page_help_text">' . _('Select the parameters for the inquiry') . '</div><br />';

if (!isset($_POST['DateRange'])) {
    /* then assume report is for This Month - maybe wrong to do this but hey better than reporting an error?*/
    $_POST['DateRange'] = 'ThisMonth';
}

echo '<form id="form1" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<div>';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
// stock category selection
$SQL = "SELECT categoryid,
					categorydescription
			FROM stockcategory
			ORDER BY categorydescription";
$result1 = DB_query($SQL);

echo '<table cellpadding="2" class="selection">
		<tr>
			<td style="width:150px">' . _('In Stock Category') . ':</td>
			<td><select name="StockCat">';
if (!isset($_POST['StockCat'])) {
    $_POST['StockCat'] = 'All';
}
if ($_POST['StockCat'] == 'All') {
    echo '<option selected="selected" value="All">' . _('All') . '</option>';
} else {
    echo '<option value="All">' . _('All') . '</option>';
}
while ($myrow1 = DB_fetch_array($result1)) {
    if ($myrow1['categoryid'] == $_POST['StockCat']) {
        echo '<option selected="selected" value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'] . '</option>';
    } else {
        echo '<option value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'] . '</option>';
    }
}
echo '</select></td>
	</tr>
	<tr>
		<th colspan="2" class="centre">' . _('Date Selection') . '</th>
	</tr>';

if (!isset($_POST['FromDate'])) {
    unset($_POST['ShowSales']);
    $_POST['FromDate'] = Date($_SESSION['DefaultDateFormat'], mktime(1, 1, 1, Date('m') - 12, Date('d') + 1, Date('Y')));
    $_POST['ToDate'] = Date($_SESSION['DefaultDateFormat']);
}
echo '<tr>
		<td>' . _('Date From') . ':</td>
		<td><input type="text" class="date" name="FromDate" maxlength="10" size="11" value="' . $_POST['FromDate'] . '" /></td>
		</tr>';
echo '<tr>
		<td>' . _('Date To') . ':</td>
		<td><input type="text" class="date" name="ToDate" maxlength="10" size="11" value="' . $_POST['ToDate'] . '" /></td>
	</tr>
</table>
<br />
<div class="centre">
	<input tabindex="4" type="submit" name="ShowSales" value="' . _('Show Sales') . '" />
	<input tabindex="4" type="submit" name="excel" value="报表下载" />
</div>
</div>
</form>
<br />';


if (isset($_POST['ShowSales'])) {
    $InputError = 0; //assume no input errors now test for errors
    if (!Is_Date($_POST['FromDate'])) {
        $InputError = 1;
        prnMsg(_('The date entered for the from date is not in the appropriate format. Dates must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'], 'error');
    }
    if (!Is_Date($_POST['ToDate'])) {
        $InputError = 1;
        prnMsg(_('The date entered for the to date is not in the appropriate format. Dates must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'], 'error');
    }
    if (Date1GreaterThanDate2($_POST['FromDate'], $_POST['ToDate'])) {
        $InputError = 1;
        prnMsg(_('The from date is expected to be a date prior to the to date. Please review the selected date range'), 'error');
    }
    $FromDate = FormatDateForSQL($_POST['FromDate']);
    $ToDate = FormatDateForSQL($_POST['ToDate']);

    $sql = "SELECT stockmaster.categoryid,
					stockcategory.categorydescription,
					stockmaster.stockid,
					stockmaster.description,
					SUM(price*(1-discountpercent)* -qty) as salesvalue,
					SUM(-qty) as quantitysold,
					SUM(standardcost * -qty) as cogs
			FROM stockmoves INNER JOIN stockmaster
			ON stockmoves.stockid=stockmaster.stockid
			INNER JOIN stockcategory
			ON stockmaster.categoryid=stockcategory.categoryid
			WHERE (stockmoves.type=10 OR stockmoves.type=11)
			AND show_on_inv_crds =1
			AND trandate>='" . $FromDate . "'
			AND trandate<='" . $ToDate . "'
			GROUP BY stockmaster.categoryid,
					stockcategory.categorydescription,
					stockmaster.stockid,
					stockmaster.description
			ORDER BY stockmaster.categoryid,
					salesvalue DESC";

    $ErrMsg = _('The sales data could not be retrieved because') . ' - ' . DB_error_msg();
    $SalesResult = DB_query($sql, $ErrMsg);

    echo '<table cellpadding="2" class="selection">';

    echo '<tr>
			<th>' . _('Item Code') . '</th>
			<th>' . _('Item Description') . '</th>
			<th>已售数量</th>
			<th>产品销售收入</th>
			<th>销货成本</th>
			<th>' . _('Gross Margin') . '</th>
			<th>' . _('Avg Unit') . '<br/>' . _('Sale Price') . '</th>
			<th>' . _('Avg Unit') . '<br/>' . _('Cost') . '</th>
			<th>' . _('Margin %') . '</th>
		</tr>';

    $CumulativeTotalSales = 0;
    $CumulativeTotalQty = 0;
    $CumulativeTotalCOGS = 0;
    $CategorySales = 0;
    $CategoryQty = 0;
    $CategoryCOGS = 0;
    $CategoryID = '';

    while ($SalesRow = DB_fetch_array($SalesResult)) {
        if ($CategoryID != $SalesRow['categoryid']) {
            if ($CategoryID != '') {
                //print out the previous category totals
                echo '<tr>
					<td colspan="2" class="number">' . _('Category Total') . '</td>
					<td class="number">' . locale_number_format($CategoryQty, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
					<td class="number">' . locale_number_format($CategorySales, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
					<td class="number">' . locale_number_format($CategoryCOGS, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
					<td class="number">' . locale_number_format($CategorySales - $CategoryCOGS, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
					<td colspan="2"></td>';
                if ($CumulativeTotalSales != 0) {
                    echo '<td class="number">' . locale_number_format(($CategorySales - $CategoryCOGS) * 100 / $CategorySales, $_SESSION['CompanyRecord']['decimalplaces']) . '%</td>';
                } else {
                    echo '<td>' . _('N/A') . '</td>';
                }
                echo '</tr>';

                //reset the totals
                $CategorySales = 0;
                $CategoryQty = 0;
                $CategoryCOGS = 0;

            }
            echo '<tr>
					<th colspan="9">' . _('Stock Category') . ': ' . $SalesRow['categoryid'] . ' - ' . $SalesRow['categorydescription'] . '</th>
				</tr>';
            $CategoryID = $SalesRow['categoryid'];
        }

        echo '<tr class="striped_row">
				<td>' . $SalesRow['stockid'] . '</td>
				<td>' . $SalesRow['description'] . '</td>
				<td class="number">' . locale_number_format($SalesRow['quantitysold'], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
				<td class="number">' . locale_number_format($SalesRow['salesvalue'], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
				<td class="number">' . locale_number_format($SalesRow['cogs'], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
				<td class="number">' . locale_number_format($SalesRow['salesvalue'] - $SalesRow['cogs'], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
        if ($SalesRow['quantitysold'] != 0) {
            echo '<td class="number">' . locale_number_format(($SalesRow['salesvalue'] / $SalesRow['quantitysold']), $_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
            echo '<td class="number">' . locale_number_format(($SalesRow['cogs'] / $SalesRow['quantitysold']), $_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
        } else {
            echo '<td>' . _('N/A') . '</td>
				<td>' . _('N/A') . '</td>';
        }
        if ($SalesRow['salesvalue'] != 0) {
            echo '<td class="number">' . locale_number_format((($SalesRow['salesvalue'] - $SalesRow['cogs']) * 100 / $SalesRow['salesvalue']), $_SESSION['CompanyRecord']['decimalplaces']) . '%</td>';
        } else {
            echo '<td>' . _('N/A') . '</td>';
        }
        echo '</tr>';

        $CumulativeTotalSales += $SalesRow['salesvalue'];
        $CumulativeTotalCOGS += $SalesRow['cogs'];
        $CumulativeTotalQty += $SalesRow['quantitysold'];
        $CategorySales += $SalesRow['salesvalue'];
        $CategoryQty += $SalesRow['quantitysold'];
        $CategoryCOGS += $SalesRow['cogs'];

    } //loop around category sales for the period
//print out the previous category totals
    echo '<tr>
		<td colspan="2" class="number">' . _('Category Total') . '</td>
		<td class="number">' . locale_number_format($CategoryQty, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
		<td class="number">' . locale_number_format($CategorySales, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
		<td class="number">' . locale_number_format($CategoryCOGS, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
		<td class="number">' . locale_number_format($CategorySales - $CategoryCOGS, $_SESSION['CompanyRecord']['decimalplaces']) . '</td>
		<td colspan="2"></td>';
    if ($CumulativeTotalSales != 0) {
        echo '<td class="number">' . locale_number_format(($CategorySales - $CategoryCOGS) * 100 / $CategorySales, $_SESSION['CompanyRecord']['decimalplaces']) . '%</td>';
    } else {
        echo '<td>' . _('N/A') . '</td>';
    }
    echo '</tr>
		<tr>
		<th colspan="2" class="number">' . _('GRAND Total') . '</th>
		<th class="number">' . locale_number_format($CumulativeTotalQty, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
		<th class="number">' . locale_number_format($CumulativeTotalSales, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
		<th class="number">' . locale_number_format($CumulativeTotalCOGS, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
		<th class="number">' . locale_number_format($CumulativeTotalSales - $CumulativeTotalCOGS, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
		<th colspan="2"></td>';
    if ($CumulativeTotalSales != 0) {
        echo '<th class="number">' . locale_number_format(($CumulativeTotalSales - $CumulativeTotalCOGS) * 100 / $CumulativeTotalSales, $_SESSION['CompanyRecord']['decimalplaces']) . '%</th>';
    } else {
        echo '<th>' . _('N/A') . '</th>';
    }
    echo '</tr>
		</table>';

} //end of if user hit show sales
if (isset($_POST['excel'])) {

    $InputError = 0; //assume no input errors now test for errors
    if (!Is_Date($_POST['FromDate'])) {
        $InputError = 1;
        prnMsg(_('The date entered for the from date is not in the appropriate format. Dates must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'], 'error');
    }
    if (!Is_Date($_POST['ToDate'])) {
        $InputError = 1;
        prnMsg(_('The date entered for the to date is not in the appropriate format. Dates must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'], 'error');
    }
    if (Date1GreaterThanDate2($_POST['FromDate'], $_POST['ToDate'])) {
        $InputError = 1;
        prnMsg(_('The from date is expected to be a date prior to the to date. Please review the selected date range'), 'error');
    }
    $FromDate = FormatDateForSQL($_POST['FromDate']);
    $ToDate = FormatDateForSQL($_POST['ToDate']);

    $sql = "SELECT stockmaster.categoryid,
					stockcategory.categorydescription,
					stockmaster.stockid,
					stockmaster.description,
					SUM(price*(1-discountpercent)* -qty) as salesvalue,
					SUM(-qty) as quantitysold,
					SUM(standardcost * -qty) as cogs
			FROM stockmoves INNER JOIN stockmaster
			ON stockmoves.stockid=stockmaster.stockid
			INNER JOIN stockcategory
			ON stockmaster.categoryid=stockcategory.categoryid
			WHERE (stockmoves.type=10 OR stockmoves.type=11)
			AND show_on_inv_crds =1
			AND trandate>='" . $FromDate . "'
			AND trandate<='" . $ToDate . "'
			GROUP BY stockmaster.categoryid,
					stockcategory.categorydescription,
					stockmaster.stockid,
					stockmaster.description
			ORDER BY stockmaster.categoryid,
					salesvalue DESC";

    $ErrMsg = _('The sales data could not be retrieved because') . ' - ' . DB_error_msg();
    $SalesResult = DB_query($sql, $ErrMsg);

    $CumulativeTotalSales = 0; //累计销售
    $CumulativeTotalQty = 0; //累计数量
    $CumulativeTotalCOGS = 0; //累计成本
    $CategorySales = 0; //分类销售
    $CategoryQty = 0; //分类数量
    $CategoryCOGS = 0; //销售成本
    $CategoryID = ''; //分类ID

    include('includes/PHPExcel/PHPExcel.php');
    $objPHPExcel = new PHPExcel();
    $objPHPExcel->getProperties()->setCreator("fatfish")
        ->setLastModifiedBy("fatfish")
        ->setTitle("report")//标题
        ->setSubject(date('Ymd H:i:s', time()))//主题
        ->setDescription("tiaoshucount")//备注
        ->setKeywords("excel")
        ->setCategory("result file");
    $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', '物料编号')
        ->setCellValue('B1', '物料描述')
        ->setCellValue('C1', '已售数量')
        ->setCellValue('D1', '产品销售收入')
        ->setCellValue('E1', '销货成本')
        ->setCellValue('F1', 'Gross Margin')
        ->setCellValue('G1', 'Avg Unit Sale Price')
        ->setCellValue('H1', 'Avg Unit 成本')
        ->setCellValue('I1', 'Margin %');

    $head = 2;
    while ($SalesRow = DB_fetch_array($SalesResult)) {
        /*if ($CategoryID != $SalesRow['categoryid']) {
            if ($CategoryID !='') {
                //print out the previous category totals
                echo '<tr>
                    <td colspan="2" class="number">' . _('Category Total') . '</td>
                    <td class="number">' . locale_number_format($CategoryQty,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
                    <td class="number">' . locale_number_format($CategorySales,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
                    <td class="number">' . locale_number_format($CategoryCOGS,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
                    <td class="number">' . locale_number_format($CategorySales - $CategoryCOGS,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
                    <td colspan="2"></td>';
                if ($CumulativeTotalSales !=0) {
                    echo '<td class="number">' . locale_number_format(($CategorySales-$CategoryCOGS)*100/$CategorySales,$_SESSION['CompanyRecord']['decimalplaces']) . '%</td>';
                } else {
                    echo '<td>' . _('N/A') . '</td>';
                }
                echo '</tr>';

                //reset the totals
                $CategorySales = 0;
                $CategoryQty = 0;
                $CategoryCOGS = 0;

            }
            echo '<tr>
                    <th colspan="9">' . _('Stock Category') . ': ' . $SalesRow['categoryid'] . ' - ' . $SalesRow['categorydescription'] . '</th>
                </tr>';
            $CategoryID = $SalesRow['categoryid'];
        }*/

        $objPHPExcel->setActiveSheetIndex(0)
//            ->setCellValue('A' . $head, $SalesRow['stockid'])
            ->setCellValue('B' . $head, $SalesRow['description'])
            ->setCellValue('C' . $head, locale_number_format($SalesRow['quantitysold'], $_SESSION['CompanyRecord']['decimalplaces']))
            ->setCellValue('D' . $head, locale_number_format($SalesRow['salesvalue'], $_SESSION['CompanyRecord']['decimalplaces']))
            ->setCellValue('E' . $head, locale_number_format($SalesRow['cogs'], $_SESSION['CompanyRecord']['decimalplaces']))
            ->setCellValue('F' . $head, locale_number_format($SalesRow['salesvalue'] - $SalesRow['cogs'], $_SESSION['CompanyRecord']['decimalplaces']));

        $objPHPExcel->getActiveSheet()->setCellValueExplicit('A' . $head, $SalesRow['stockid'], \PHPExcel_Cell_DataType::TYPE_STRING);

        if ($SalesRow['quantitysold'] != 0) {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('G' . $head, locale_number_format(($SalesRow['salesvalue'] / $SalesRow['quantitysold']), $_SESSION['CompanyRecord']['decimalplaces']))
                ->setCellValue('H' . $head, locale_number_format(($SalesRow['cogs'] / $SalesRow['quantitysold']), $_SESSION['CompanyRecord']['decimalplaces']));
        } else {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('G' . $head, _('N/A'))
                ->setCellValue('H' . $head, _('N/A'));
        }

        if ($SalesRow['salesvalue'] != 0) {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('I' . $head, locale_number_format((($SalesRow['salesvalue'] - $SalesRow['cogs']) * 100 / $SalesRow['salesvalue']), $_SESSION['CompanyRecord']['decimalplaces']) . '%');
        } else {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('I' . $head, _('N/A'));
        }

        $CumulativeTotalSales += $SalesRow['salesvalue'];
        $CumulativeTotalCOGS += $SalesRow['cogs'];
        $CumulativeTotalQty += $SalesRow['quantitysold'];
        $CategorySales += $SalesRow['salesvalue'];
        $CategoryQty += $SalesRow['quantitysold'];
        $CategoryCOGS += $SalesRow['cogs'];
        ++$head;
    } //loop around category sales for the period
//print out the previous category totals

    $objPHPExcel->getActiveSheet()->mergeCells('A' . $head . ':B' . $head);
    $objPHPExcel->getActiveSheet()->mergeCells('G' . $head . ':H' . $head);
    $objPHPExcel->setActiveSheetIndex()
        ->setCellValue('A' . $head, _('Category Total'))
        ->setCellValue('C' . $head, locale_number_format($CategoryQty, $_SESSION['CompanyRecord']['decimalplaces']))
        ->setCellValue('D' . $head, locale_number_format($CategorySales, $_SESSION['CompanyRecord']['decimalplaces']))
        ->setCellValue('E' . $head, locale_number_format($CategoryCOGS, $_SESSION['CompanyRecord']['decimalplaces']))
        ->setCellValue('F' . $head, locale_number_format($CategorySales - $CategoryCOGS, $_SESSION['CompanyRecord']['decimalplaces']));
    if ($CumulativeTotalSales != 0) {
        $objPHPExcel->setActiveSheetIndex()
            ->setCellValue('I' . $head, locale_number_format(($CategorySales - $CategoryCOGS) * 100 / $CategorySales, $_SESSION['CompanyRecord']['decimalplaces']) . '%');
    } else {
        $objPHPExcel->setActiveSheetIndex()
            ->setCellValue('I' . $head, _('N/A'));
    }

    $objPHPExcel->getActiveSheet()->getStyle('A' . $head)
        ->getFont()
        ->setBold(true); //字体加粗
    $objPHPExcel->getActiveSheet()->getStyle('A' . $head)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
    ++$head;

    $objPHPExcel->getActiveSheet()->mergeCells('A' . $head . ':B' . $head);
    $objPHPExcel->getActiveSheet()->mergeCells('G' . $head . ':H' . $head);
    $objPHPExcel->setActiveSheetIndex()
        ->setCellValue('A' . $head, _('GRAND Total'))
        ->setCellValue('C' . $head, locale_number_format($CumulativeTotalQty, $_SESSION['CompanyRecord']['decimalplaces']))
        ->setCellValue('D' . $head, locale_number_format($CumulativeTotalSales, $_SESSION['CompanyRecord']['decimalplaces']))
        ->setCellValue('E' . $head, locale_number_format($CumulativeTotalCOGS, $_SESSION['CompanyRecord']['decimalplaces']))
        ->setCellValue('F' . $head, locale_number_format($CumulativeTotalSales - $CumulativeTotalCOGS, $_SESSION['CompanyRecord']['decimalplaces']));
    if ($CumulativeTotalSales != 0) {
        $objPHPExcel->setActiveSheetIndex()
            ->setCellValue('I' . $head, locale_number_format(($CumulativeTotalSales - $CumulativeTotalCOGS) * 100 / $CumulativeTotalSales, $_SESSION['CompanyRecord']['decimalplaces']) . '%');
    } else {
        $objPHPExcel->setActiveSheetIndex()
            ->setCellValue('I' . $head, _('N/A'));
    }

    $objPHPExcel->getActiveSheet()->getStyle('A' . $head)
        ->getFont()
        ->setBold(true); //字体加粗
    $objPHPExcel->getActiveSheet()->getStyle('A' . $head)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);


    $objPHPExcel->getActiveSheet()->setTitle($FromDate.'至'.$ToDate);
    $objPHPExcel->setActiveSheetIndex(0);
    ob_end_clean();//避免乱码
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="'.$FromDate.'至'.$ToDate.'销售报表.xls"');
    header('Cache-Control: max-age=0');
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    exit;
} //end of if user hit excel
include('includes/footer.php');
?>