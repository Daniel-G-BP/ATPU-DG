Tato webová aplikace slouží ke správě studijních předmětů, učitelů a jejich úvazků. Umožňuje import dat, přiřazování učitelů k předmětům, výpočty hodinových úvazků a jejich úpravy.

Spuštění aplikace:
1. Nainstalujte Docker Desktop - https://www.docker.com/products/docker-desktop

2. Naklonujte projekt: 
git clone https://github.com/Daniel-G-BP/ATPU-DG
cd ATPU-DG

3. Změna config.php:
v adresáři projektu jdi na "includes\config-example.php" (zde musíš přidat své přihlašovací údaje k databázi STAGu aby se načítala data ze STAGu), následně uložit a přejmenovat na config.php


4. Spusťte aplikaci: 
docker-compose up --build

5. Přejděte v prohlížeči na:
http://localhost:8080



Práce s aplikací:
1. Inicializace databáze

Na hlavní stránce klikni na tlačítko „Start“.
To vytvoří základní strukturu databáze a naplní ji daty.
Poznámka: Tlačítko je nutné zmáčknout při prvním spuštění aplikace, jinak nebudou fungovat další části.

2. Import dat
V části Insert do DB lze (Import STAG) 
-Vytvořit novou verzi: vytvořit a uložit novou verzi dat 
-Vyberte aktivní verzi: změnit verzi dat se kterou se pracuje
-Vyberte akademický rok: vybrat akademický rok
-Vyberte semestr: vybrat semestr
-Načíst katedry fakulty: vybrat fakultu
-Načíst předměty katedry: nahrát data pro zvolenou katedru fakulty se zvolenými parametry (semestr, akademický rok, fakulta, katedra)

3. Manuální editace
V části Manuální editace se zobrazuje tabulka s přiřazenými učiteli k předmětům a jejich atributy. S každým záznamem můžeme provádět tyto akce: 
Uložit - tlačítko uloží změny provedené v řádku
Odebrat - tlačítko odebere učitele z řádku
Smazat řádek - tlačítko smaže celý řádek (záznam v tabulce)
Kopírovat řádek - tlačítko zkopíruje řádek daného předmětu - bez učitele
