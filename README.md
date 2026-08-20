Automatizace tvorby pracovnich uvazku
=====================================

Webova aplikace slouzi ke sprave studijnich predmetu, ucitelu a jejich uvazku.
Umoznuje import dat z IS/STAG, automaticke predvyplneni prirazeni ucitelu,
rucni upravy uvazku, spravu studentu v rocnicich, evidenci nepokryte vyuky
a export jednotlivych i hromadnych vystupu do XLSX.

Spusteni aplikace
-----------------

1. Nainstalujte Docker Desktop:
   https://www.docker.com/products/docker-desktop

2. Naklonujte projekt:

   ```bash
   git clone https://github.com/Daniel-G-BP/ATPU-DG
   cd ATPU-DG
   ```

3. Spustte aplikaci:

   ```bash
   docker compose up -d --build
   ```

4. Otevrete aplikaci v prohlizeci:

   ```text
   http://localhost:8080
   ```

   Volitelne je dostupny take phpMyAdmin:

   ```text
   http://localhost:8081
   ```

5. Pri prvnim spusteni kliknete na hlavni strance na tlacitko `Start`.
   Tim se vytvori zakladni struktura databaze a naplni se ciselniky.

Prihlasovaci udaje do IS/STAG
-----------------------------

Prihlasovaci udaje do IS/STAG se neukladaji do `docker-compose.yml`,
do databaze ani do repozitare.

Uzivatel je zadava az na strance `Import dat`. Aplikace je ulozi pouze do
serverove session a pouzije je pri serverovych requestech na STAG webove sluzby.
Po ukonceni session nebo po kliknuti na `Odstranit udaje ze session` se udaje
zahodi.

Prace s aplikaci
----------------

1. Inicializace databaze

   Na hlavni strance kliknete na tlacitko `Start`.

2. Import dat

   V casti `Import dat`:

   - zadejte prihlasovaci udaje do IS/STAG,
   - vytvorte nebo vyberte aktivni verzi dat,
   - nastavte akademicky rok, semestr a vybranou katedru,
   - nactete strukturu fakulty a potrebne ciselniky,
   - importujte aktualni i historicka data z IS/STAG.

3. Manualni editace

   V casti `Manualni editace` lze upravovat prirazeni ucitelu k predmetum,
   menit podily, odebirat ucitele, mazat nebo kopirovat radky a resit
   nepokrytou vyuku.

4. Zkouseni a cviceni

   V casti `Zkouseni` se spravuji cinnosti A.2. V casti `Edit` lze pridat
   externistu, upravit pocty studentu v rocnicich a rozdelit cviceni podle
   poctu studentu.

5. Export

   Z prehledu ucitelu a detailu ucitele lze vygenerovat XLSX soubor s uvazkem
   vybraneho vyucujiciho. Samostatne lze exportovat take prehled vytizenosti
   a prehled nepokryte vyuky.

Vytizenost vyucujicich
----------------------

- Koeficienty A.1 a A.2 se spravuji na strance `Nastaveni` a ukladaji se pro
  aktivni verzi dat.
- Hranice pretizenosti je konfigurovatelna na strance `Nastaveni`
  (parametr `PretizeniProcent`, vychozi hodnota 110 % uvazku, rozsah 50-300 %).
- Velikost uvazku, pozici, datum nastupu a souhrnne body A.3 az D lze upravit
  v detailu vyucujiciho.
- Aktualni PB a procento kapacity jsou videt v prehledu vyucujicich i pri
  manualnim prirazovani.
- Web a XLSX export pouzivaji stejny kalkulator; podil vyucujiciho i jednotky
  `HOD/TYD` a `HOD/SEM` se zapocitavaji shodne.

Prehled vytizenosti
-------------------

Stranka `Prehled ucitelu` zobrazuje vytizenost vsech vyucujicich s moznosti
filtrovat podle fakulty, katedry a jmena. Pretizeni vyucujici jsou zvyrazneni.
Cely prehled lze exportovat do XLSX tlacitkem `Export prehledu do Excelu`;
export respektuje nastavene filtry a obsahuje rozpad na oblasti A.1 az D.

Nepokryta vyuka
---------------

Stranka `Nepokryta vyuka` zobrazuje predmety, u kterych jakakoliv cast vyuky
neni plne pokryta. Casti se posuzuji zvlast pro kazdou kombinaci typu vyuky
a jazyka; cast je nepokryta, pokud nema prirazeneho vyucujiciho nebo soucet
podilu nedosahuje 100 %. Prehled lze filtrovat podle fakulty, katedry,
semestru, zkratky a nazvu a exportovat do XLSX.
