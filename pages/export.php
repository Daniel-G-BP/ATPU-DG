<?php
if (isset($_POST['html'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="data.xls"');
    echo $_POST['html'];
}
?>
