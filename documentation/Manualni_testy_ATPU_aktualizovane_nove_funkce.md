# Tabulka manuálních a integračních testů aplikace ATPU

| ID | Oblast | Scénář | Vstup / postup | Očekávaný výsledek | Výsledek |
| --- | --- | --- | --- | --- | --- |
| MT-01 | Spuštění | Načtení hlavní stránky aplikace. | GET http://localhost:8080/ | HTTP 200, stránka obsahuje hlavní menu a tlačítko Start. | Splněno |
| MT-02 | Navigace | Kontrola hlavních položek menu. | Načtení úvodní stránky a kontrola odkazů. | Menu obsahuje Main, View, Edit, Import dat, Manuální editace, Zkoušení, Rozdělit cvičení, Přehled učitelé, Nepokrytá výuka, Nastavení. | Splněno |
| MT-03 | Import dat | Načtení stránky Import dat. | GET /pages/insert1.php | Stránka se načte a obsahuje práci se session a importem. | Splněno |
| MT-04 | A.1 | Načtení ruční editace přímé výuky. | GET /pages/result-counting.php?limit=50 | Stránka obsahuje filtry a editační tabulku. | Splněno |
| MT-05 | A.1 | Ověření filtrování ruční editace. | GET /pages/result-counting.php?zkratka=AAA&limit=50 | Stránka odpoví bez chyby a aplikuje filtr zkratky. | Splněno |
| MT-06 | A.1 | Uložení jednoho řádku, odebrání učitele, smazání a kopírování. | Tlačítka Uložit, Odebrat, Smazat, Kopírovat v jednom řádku. | Po akci se uživatel vrátí na stejný filtr a zobrazí se potvrzení. | Splněno |
| MT-07 | A.2 | Načtení stránky Zkoušení. | GET /pages/zkouseni.php?limit=50 | Stránka obsahuje filtry a tlačítko Uložit u řádků. | Splněno |
| MT-08 | A.2 | Změna počtu zkoušení. | Úprava hodnot kl/zap/zk/dz a Uložit. | Náhled PB se přepočítá a po uložení se hodnota projeví v detailu učitele/exportu. | Splněno |
| MT-09 | Přehled učitelů | Načtení přehledu učitelů. | GET /pages/overview-ucitele.php?limit=50 | Stránka obsahuje filtry, export a akce Detail / editace. | Splněno |
| MT-10 | Přehled učitelů | Získání učitele pro detail a export. | Parsování HTML přehledu. | V tabulce je dostupný alespoň jeden teacherId. | Splněno |
| MT-11 | Detail úvazku | Načtení detailu úvazku učitele. | GET /pages/uvazek-ucitele.php?id=1682 | Detail obsahuje souhrn a části A.1/A.2. | Splněno |
| MT-12 | Editace učitele | Načtení detailu/editace učitele. | GET /pages/edit-ucitel.php?id=1682 | Formulář obsahuje uložení a údaje o kapacitě/úvazku. | Splněno |
| MT-13 | Export | Export úvazku jednoho učitele. | GET /pages/export-uvazek.php?teacherId=1682 | Server vrátí XLSX soubor. | Splněno |
| MT-14 | Export | Chybový stav exportu bez učitele. | GET /pages/export-uvazek.php | Server odmítne požadavek bez teacherId. | Splněno |
| MT-15 | Export | Hromadný export přehledu vytíženosti. | GET /pages/export-prehled-vytizeni.php?q=&katedra=&fakulta= | Server vrátí XLSX soubor. | Splněno |
| MT-16 | Nepokrytá výuka | Načtení přehledu nepokryté výuky. | GET /pages/nepokryte-predmety.php | Stránka obsahuje filtry a export. | Splněno |
| MT-17 | Nepokrytá výuka | Export nepokryté výuky. | GET /pages/export-nepokryte.php | Server vrátí XLSX soubor. | Splněno |
| MT-18 | Nastavení | Načtení nastavení systému. | GET /pages/settings.php | Stránka obsahuje koeficienty a správu titulů. | Splněno |
| MT-19 | Nastavení | Uložení koeficientů, hranice přetížení a plného úvazku. | Změna hodnot a tlačítka Uložit/Uložit koeficienty. | Po uložení se objeví potvrzení a hodnoty se projeví ve výpočtech. | Splněno |
| MT-20 | Edit | Načtení rozcestníku editačních nástrojů. | GET /pages/edit.php | Stránka obsahuje odkazy na externistu a ročníky. | Splněno |
| MT-21 | Externista | Načtení formuláře pro externistu. | GET /pages/insertExternista.php | Formulář obsahuje pole pro osobní a kontaktní údaje a tlačítko Vytvořit. | Splněno |
| MT-22 | Externista | Vytvoření externisty. | Vyplnění formuláře a tlačítko Vytvořit. | Externista se uloží a je dostupný v ruční editaci A.1. | Splněno s nálezem |
| MT-23 | Ročníky | Načtení editace počtu studentů. | GET /pages/edit_rocniky.php | Stránka obsahuje filtry a tlačítko pro uložení změn. | Splněno |
| MT-24 | Ročníky | Uložení počtu studentů. | Změna hodnoty Počet studentů a Uložit změny. | Hodnota se uloží a následně se použije pro A.2/rozdělení cvičení. | Splněno |
| MT-25 | Rozdělení cvičení | Načtení stránky rozdělení cvičení. | GET /pages/rozdat_cviceni.php | Stránka se načte a buď zobrazí plán skupin, nebo informaci o chybějících vstupech. | Splněno |
| MT-26 | Rozdělení cvičení | Přidání skupin cvičení. | Na předmětu s cvičením nastavte v A.1 Max. počet na 24, např. AP2LO, AP1L1 nebo AP3FY. Při 120 studentech má stránka Rozdělit cvičení ukázat potřebu 5 skupin. Poté použijte Rozdat tento a ověřte doplnění prázdných řádků. | Aplikace doplní chybějící skupiny a ponechá stávající vyučující. | Splněno |
| MT-27 | Sestavy | Načtení rozcestníku View. | GET /pages/view.php | Stránka obsahuje odkaz na sestavu studijních programů. | Splněno |
| MT-28 | Výpočty | Jednotkový test výpočtu úvazků. | C:\\xampp\\php\\php.exe tests\\workload-functions-test.php | Test skončí textem workload-functions: OK. | Splněno |
| MT-29 | Výpočty | Jednotkový test nepokryté výuky. | C:\\xampp\\php\\php.exe tests\\coverage-functions-test.php | Test skončí textem coverage-functions: OK. | Splněno |
| MT-30 | Export | Smoke test šablony XLSX. | C:\\xampp\\php\\php.exe -d extension=zip tests\\export-template-smoke.php | Test skončí textem export-template: OK. | Splněno |
| MT-31 | A.1 | Načtení hromadných akcí v Manuální editaci. | GET /pages/result-counting.php?limit=50 a kontrola ovládacích prvků nad tabulkou. | Stránka obsahuje checkbox Vybrat vše na stránce a tlačítka Odebrat vybrané, Smazat vybrané, Odebrat vše vyfiltrované a Smazat vše vyfiltrované. | Splněno |
| MT-32 | A.1 | Kontrola potvrzení před hromadnou akcí. | GET /pages/result-counting.php?limit=50 a kontrola JavaScriptových potvrzení. | Vybrané i vyfiltrované hromadné akce před odesláním zobrazí potvrzení; vyfiltrovaná akce uvádí počet zasažených záznamů. | Splněno |
| MT-33 | A.1 | Hromadné odebrání učitele z vybraných řádků. | Ve filtru vybrat malý počet testovacích řádků, zaškrtnout je a použít Odebrat vybrané. | U označených záznamů zůstane výuka zachována, ale teacherid se nastaví na NULL; stránka zobrazí potvrzení s počtem změněných řádků. | Splněno |
| MT-34 | A.1 | Hromadné smazání všech vyfiltrovaných řádků. | Nastavit úzký filtr v Manuální editaci, ověřit počet vyfiltrovaných záznamů a použít Smazat vše vyfiltrované. | Aplikace před smazáním zobrazí potvrzení a smaže pouze záznamy odpovídající aktivní verzi a aktuálním filtrům. | Splněno |
| MT-35 | Editace ročníků | Načtení filtrů a comboboxů v editaci ročníků. | GET /pages/edit_rocniky.php a kontrola polí fakulta, ročník, forma a typ. | Stránka se načte bez chyby a nabídne filtry pro omezení seznamu studijních ročníků. | Splněno |
| MT-36 | Editace ročníků | Zobrazení prokliku Nastavit cvičení u řádků ročníků. | GET /pages/edit_rocniky.php a kontrola odkazu rocnik-cviceni.php?rocnik_id=... | U každého dostupného řádku ročníku je k dispozici tlačítko Nastavit cvičení, které předá ID ročníku. | Splněno |
| MT-37 | Cvičení ročníku | Načtení nové mezistránky Cvičení studijního ročníku. | GET /pages/rocnik-cviceni.php?rocnik_id=785. | Stránka zobrazí informační blok ročníku, počet studentů, seznam předmětů s cvičením a stav nastavení maxima studentů. | Splněno |
| MT-38 | Cvičení ročníku | Proklik z nové mezistránky do Manuální editace A.1. | Na stránce rocnik-cviceni.php otevřít tlačítko Nastavit v A.1 u předmětu. | Otevře se Manuální editace s filtrem na konkrétní zkratku, semestr a pracoviště; uživatel může nastavit Max. počet pro cvičení. | Splněno |
| MT-39 | Externista | Načtení dat do comboboxů při přidání externisty. | GET /pages/insertExternista.php a kontrola selectů titul, fakulta a pracoviště. | Combobox Titul načte tituly, Fakulta načte fakulty a Pracoviště načte pracoviště aktivní verze včetně vazby na fakultu. | Splněno |
| MT-40 | Externista | Filtrování pracovišť podle vybrané fakulty. | Na stránce Přidat externistu vybrat fakultu a sledovat combobox Pracoviště. | Seznam pracovišť se omezí podle hodnoty data-fakulta; při neexistujícím pracovišti pro fakultu se zobrazí pomocná informace. | Splněno |
| MT-41 | Rozdělení cvičení | Návaznost počtu studentů, maxima v A.1 a rozdělení skupin. | V editaci ročníků uložit počet studentů, přes Nastavit cvičení otevřít A.1, nastavit Max. počet u cvičení a otevřít Rozdělit cvičení. | Po nastavení maxima se předmět objeví na stránce Rozdělit cvičení a aplikace dopočítá počet skupin podle počtu studentů a maxima na skupinu. | Splněno |
