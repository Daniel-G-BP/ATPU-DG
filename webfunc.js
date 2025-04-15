function loadPage(url) {
    fetch(url)
        .then(response => response.text())
        .then(data => {
            document.getElementById('content').innerHTML = data;
        })
        .catch(error => console.error('Error:', error));
}

function toggleNavbar() {
    var navbar = document.getElementById('navbar');
    navbar.classList.toggle('hidden');

    var content = document.getElementById('content');
    content.classList.toggle('shifted');
}

function toggleNavbarRC() {
    var navbar = document.getElementById("navbar");
    var btn = document.getElementById("toggleButton");

    if (navbar.style.display === "none") {
        navbar.style.display = "block";
        btn.textContent = "Skrýt Menu";
    } else {
        navbar.style.display = "none";
        btn.textContent = "Zobrazit Menu";
    }
}

function exportToExcel() {
    var table = document.getElementById("dataTable");
    var html = table.outerHTML;

    // Odeslat tabulku HTML do skriptu PHP přes AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'export.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.responseType = 'blob'; // nastavi typ odpovedi na blob kvuli binarnim datum
    xhr.onload = function() {
        if (xhr.status === 200) {
            // Vytvori prvek odkazu kliknutím stáhněte soubor Excel
            var blob = new Blob([xhr.response], {type: 'application/vnd.ms-excel'});
            var link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = 'data.xls';
            link.click();
        } else {
            // řeší chyby
            console.error('Error occurred:', xhr.statusText);
        }
    };
    xhr.send('html=' + encodeURIComponent(html));
}
