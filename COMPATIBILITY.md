# Kompatibilita – Developer Apartments

## Cache pluginy (WP-Optimize, WP Compress, WP Rocket)

Plugin je **kompatibilný** s bežnými cache pluginmi. Odporúčania:

### Spoločné body

- **Stránkový cache (page cache):** Výstup modulov a shortcodov je čisto server-renderovaný HTML. Cache stránok je v poriadku; po úprave bytu (cena, status) by mal cache plugin sám zrušiť cache príslušnej stránky a archívov.
- **Vlastná cache pluginu:** Developer Apartments používa transienty len pre počet voľných bytov v mape (TTL v Nastaveniach). Pri ukladaní bytu alebo termu sa volá `dev_apt_cache_bump()`, ktorý invaliduje túto cache. Stránkovú cache musia spravovať cache pluginy.
- **Admin / AJAX:** Všetky `wp_ajax_*` handlery sú len pre prihlásených (admin alebo editor cien). Cache pluginy typicky necachujú admin ani `admin-ajax.php` pre tieto akcie.

### WP Rocket

- Automaticky zruší cache pri vytvorení/úprave/vymazaní **custom post types** (vrátane `apartment`).
- Ak by sa cache po úprave bytu nezrušila, plugin môže volať `rocket_clean_post()` – pozri nižšie integráciu.

### WP-Optimize (WPO Page Cache)

- Pri publikovaní alebo úprave obsahu robí **čiastočné zrušenie cache** (daný príspevok/stránka a súvisiace archívy), nie celý web.
- Custom post type `apartment` spadá pod štandardné „post update“ správanie.

### WP Compress

- Cache sa pri úpravách obsahu zrušuje automaticky; nie je potrebná manuálna konfigurácia pre tento plugin.

### Odporúčanie pri problémoch

- V nastaveniach cache pluginu skontrolovať, či sa cache zruší pri úprave príspevkov (a či to platí pre custom post types).
- **Integrácia v plugine:** Súbor `includes/integrations/cache-purge.php` pri ukladaní bytu (`save_post_apartment`) a pri úprave termov štruktúry/statusu/typu volá purge konkrétneho príspevku (WP Rocket: `rocket_clean_post`, WP-Optimize: `WPO_Page_Cache::delete_single_post_cache`); pri zmene termu sa volá flush domény. Ak niektorý cache plugin nie je nainštalovaný, príslušné volania sa preskočia.
- Ak používate **WP Rocket**: v **Advanced rules** nemusíte nič meniť; pri potrebe môžete vylúčiť konkrétne URL (napr. archívy bytov), väčšinou to nie je nutné. Pre **Delay JS**, vylúčenia URL a **Critical CSS** pozri sekciu „WP Rocket – presné odporúčania“ nižšie.
---

## WP Rocket – presné odporúčania

### Delay JavaScript (vylúčenia)

Skripty pluginu **musia bežať hneď pri načítaní stránky**, aby mapa a tabuľka fungovali (inicializácia z `data-payload`, tooltips, filtrovanie, export CSV). Ak sú odložené, používateľ uvidí „mŕtvu“ mapu/tabuľku až do prvého kliknutia.

**Čo vylúčiť z Delay JS** (WP Rocket → **File Optimization** → **Delay JavaScript execution** → **Excluded JavaScript Files**):

Pridajte jeden z nasledujúcich riadkov (stačí jeden spôsob, ktorý WP Rocket rozpoznáva):

| Spôsob | Hodnota |
|--------|--------|
| Handle (najspoľahlivejší) | `dev-apt-map` |
| Handle | `dev-apt-table` |
| Cesta k súboru | `developer-apartments/assets/js/map.js` |
| Cesta k súboru | `developer-apartments/includes/divi-modules/assets/table.js` |
| Čiastočná URL | `developer-apartments` |

**Odporúčanie:** Do poľa „Excluded JavaScript Files“ zadajte po jednom riadku:
```
dev-apt-map
dev-apt-table
```
alebo jednu všeobecnú vylúčku:
```
developer-apartments
```
Druhá možnosť vylúči všetky skripty pluginu (vrátane budúcich), čo je pre tento plugin v poriadku.

### Vylúčenia URL (cache / pravidlá)

- **Žiadne vylúčenia nie sú potrebné.** Stránky s modulmi (mapa, tabuľka) môžu byť plne cachované; obsah je rovnaký pre všetkých návštevníkov.
- Ak by ste chceli **nikdy necachovať** konkrétnu stránku (napr. testovaciu), použite WP Rocket → **Advanced rules** → **Never cache the following pages** a pridajte konkrétnu URL. Pre bežné použitie to nepotrebujete.

### Critical CSS

- **Žiadna špeciálna konfigurácia.** WP Rocket „Remove Unused CSS“ / Critical CSS generuje kritické štýly podľa prvého vykreslenia; štýly pluginu (`map.css`, `table.css`, …) sú súčasťou stránky a budú zahrnuté podľa viditeľného obsahu.
- Ak používate **Optimize CSS Delivery**: neodporúčame vylúčovať štýly `dev-apt-map-css` ani `dev-apt-table` z optimalizácie – sú potrebné pre správny layout mapy (výška, viewBox) a tabuľky (preškrtnutá cena, sticky). Ak by sa objavil FOUC (bliknutie neštýlovanej mapy/tabuľky), v **Excluded CSS Files** nepridávajte tieto súbory – radšej v nastavení Critical CSS nechajte generovať kritické CSS znova alebo zvýšte „Above the fold“ obsah tak, aby zahŕňal modul.

### Súhrn WP Rocket

| Nastavenie | Odporúčanie |
|------------|-------------|
| **Delay JS – vylúčenia** | Pridať `dev-apt-map` a `dev-apt-table` alebo `developer-apartments` |
| **Never cache URL** | Nie je potrebné |
| **Critical CSS / Remove Unused CSS** | Štandardné; žiadne vylúčenia pre tento plugin |

---

## Divi 5.x

Plugin je postavený na **Divi 4.x** (trieda `ET_Builder_Module`, hook `et_builder_ready`).  

V **Divi 5** je k dispozícii **backward compatibility**: obsah postavený na starom builderi (vrátane modulov tretích strán) beží cez vrstvu kompatibility. Moduly Developer Apartments (Mapa Bytov v2, Tabuľka Bytov v2, Údaje bytu, Breadcrumb, Galéria, Podobné byty, atď.) by mali v Divi 5 **fungovať bez úprav**.

### Čo počítať v Divi 5

- **Správanie:** Rovnaké ako v Divi 4.
- **Editor:** Moduly môžu mať v Divi 5 inú (staršiu) editovateľnú skúsenosť ako natívne Divi 5 moduly – to je bežné pri kompatibilite.
- **Výkon:** Stránky so starými modulmi nemusia mať rovnaké optimalizácie ako čistý Divi 5 obsah; pre väčšinu webov je to prijateľné.

### Migrácia na Divi 5

- Pred prechodom na Divi 5 otestujte stránky s modulmi Developer Apartments (mapa, tabuľka, detail bytu).
- Ak Elegant Themes v budúcnosti poskytne natívne Divi 5 API pre vlastné moduly, bude možné moduly postupne prepísať na nový formát pre plnú Divi 5 integráciu.

---

## Súhrn

| Oblasť              | Stav        | Poznámka                                                    |
|---------------------|------------|-------------------------------------------------------------|
| WP Rocket           | Kompatibilné | Auto-purge pri úprave bytov; voliteľne vlastný purge hook.  |
| WP-Optimize (cache) | Kompatibilné | Čiastočné zrušenie cache pri úpravách obsahu.              |
| WP Compress         | Kompatibilné | Automatické zrušenie cache pri úpravách.                   |
| Divi 4.x            | Plne       | Pôvodná cieľová verzia.                                    |
| Divi 5.x            | Kompatibilné | Cez backward compatibility; žiadna zmena kódu nie je nutná. |
