<!-- navbar.php -->
<?php require_once __DIR__ . '/../includes/main-menu.php'; ?>
    <div id="navbar" style="display: none;">
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <?php renderMainMenuItems('../index.php'); ?>
        </ul>
    </div>
    <button id="toggleButton" onclick="toggleNavbarRC()">Zobrazit Menu</button>
