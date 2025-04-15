<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export to Excel</title>
    <script src="../webfunc.js"></script>
</head>
<body>
    <table id="dataTable">
        <tr>
            <th>Name</th>
            <th>Age</th>
            <th>Email</th>
        </tr>
        <tr>
            <td>John</td>
            <td>30</td>
            <td>john@example.com</td>
        </tr>
        <tr>
            <td>Alice</td>
            <td>25</td>
            <td>alice@example.com</td>
        </tr>
        <tr>
            <td>Bob</td>
            <td>35</td>
            <td>bob@example.com</td>
        </tr>
    </table>
    <button onclick="exportToExcel()">Export to Excel</button>

</body>
</html>
