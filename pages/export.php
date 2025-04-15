<?php
if (isset($_POST['html'])) {
    // Set headers for Excel file download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="data.xls"');

    // Output the received HTML as is
    echo $_POST['html'];
}
?>
