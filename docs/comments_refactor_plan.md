# Plán refaktoru komentářového systému s Issues

## 📊 Aktuální stav (November 29, 2025)

| Fáze | Stav | Poznámka |
|------|------|----------|
| **Fáze 0 - Příprava** | ✅ COMPLETE | Databáze, modely, controllery, elements, routes |
| **Fáze 1 - Zobrazení** | 🟡 PARTIAL | Elements fungují, potřeba CSS doladění |
| **Fáze 2 - Komentáře** | ✅ MOSTLY COMPLETE | Přidání/smazání funguje, chybí position picker |
| **Fáze 3 - Issues** | ✅ MOSTLY COMPLETE | Close/reopen/move funguje, admin tlačítka OK |
| **Fáze 4 - Přehled** | ✅ MOSTLY COMPLETE | Index stránka funguje, chybí odkaz v menu |

**Co funguje:**
- Zobrazení komentářů a issues na stránce tsumega
- Tab filtrování (ALL/COMMENTS/ISSUES)
- Přidání komentářů (form s POST)
- Odpověď na issue (reply tlačítko)
- Admin akce: Close/Reopen/Move to Issue/Remove from Issue
- Delete komentáře (autor nebo admin)
- Globální issues přehled na `/tsumego-issues`
- 8 browser testů v `CommentsControllerTest.php` ✅

**Co zbývá:**
- CSS styling a vizuální doladění
- Kolapsibilní issues
- Position picker integrace
- Odkaz na `/tsumego-issues` v admin menu

---

## 🏗️ Architektonické rozhodnutí: CakePHP 2 best practices

### Problém s původním přístupem (`*Renderer` třídy)

Původní implementace používala vlastní `*Renderer` třídy v `src/Utility/` (SMAZÁNY):
- `TsumegoCommentsRenderer`
- `TsumegoIssuesRenderer`
- `TsumegoCommentsSectionRenderer`

**Proč to nebylo ideální:**
1. Renderery jsou v `Utility/` - místo pro pomocné utility funkce, ne view logiku
2. Používají `echo` přímo místo vracení HTML - porušuje separation of concerns
3. Nemohou využívat CakePHP view infrastructure (Helpers, Blocks, caching)
4. Těžší testování a znovupoužitelnost
5. Nesleduje CakePHP konvence pro MVC

### Správný CakePHP 2 přístup

Podle [CakePHP 2 dokumentace](https://book.cakephp.org/2/en/views.html):

#### 1. **Elements** (pro znovupoužitelné view fragmenty)
Umístění: `templates/Elements/` (nebo `src/View/Elements/`)

```php
// templates/Elements/Tsumego/issue.ctp
<div class="tsumego-issue tsumego-issue--<?= $issue['status'] ?>">
    <div class="tsumego-issue__header">
        <span class="tsumego-issue__badge"><?= $status ?></span>
        Issue #<?= $issue['id'] ?> by <?= $author ?>
    </div>
    <div class="tsumego-issue__comments">
        <?php foreach ($comments as $comment): ?>
            <?= $this->element('Tsumego/comment', ['comment' => $comment]) ?>
        <?php endforeach; ?>
    </div>
</div>
```

Použití ve view:
```php
<?= $this->element('Tsumego/issue', ['issue' => $issue, 'comments' => $comments]) ?>
```

#### 2. **Helpers vs. inline logika v Elements**

**Helper se hodí pro:**
- Formátování, které se používá na více místech
- Komplexní transformace textu (např. `commentCoordinates`)
- Funkce které potřebují přístup k View kontextu

**Logika přímo v elementu:**
- Jednorázový rendering specifický pro ten element
- Jednoduchá HTML generace (badge, ikona)

**Praktický přístup:**
Většinu logiky dáme přímo do elementů. Helper vytvoříme pouze pokud:
1. Funkce se používá z více elementů
2. Funkce je komplexní a zaslouží si vlastní testování

```php
// src/View/Elements/Tsumego/issue.ctp - logika přímo zde
<div class="tsumego-issue tsumego-issue--<?= $status ?>">
    <span class="badge badge--<?= $status === 1 ? 'danger' : 'success' ?>">
        <?= TsumegoIssue::statusName($issue['tsumego_issue_status_id']) ?>
    </span>
    ...
</div>
```

#### 3. **Separate Controllers pro Comments a Issues**

**DŮLEŽITÉ:** Komentáře a issues NEPATŘÍ do `TsumegosController`!

Vytvoříme samostatné controllery:

```php
// src/Controller/TsumegoCommentsController.php
class TsumegoCommentsController extends AppController
{
    public function add($tsumegoId) { }      // POST: přidat komentář
    public function delete($commentId) { }   // POST: smazat komentář
}

// src/Controller/TsumegoIssuesController.php  
class TsumegoIssuesController extends AppController
{
    public function index() { }               // GET: seznam všech issues
    public function create($tsumegoId) { }    // POST: vytvořit issue
    public function close($issueId) { }       // POST: zavřít issue
    public function reopen($issueId) { }      // POST: znovu otevřít
    public function moveComment($commentId) { } // POST: přesunout komentář do issue
}
```

**Routes (config/routes.php):**
```php
Router::connect('/tsumego-comments/add/:tsumegoId', 
    ['controller' => 'TsumegoComments', 'action' => 'add']);
Router::connect('/tsumego-issues', 
    ['controller' => 'TsumegoIssues', 'action' => 'index']);
Router::connect('/tsumego-issues/create/:tsumegoId', 
    ['controller' => 'TsumegoIssues', 'action' => 'create']);
// atd.
```

#### 4. **Datová příprava v Controlleru**
Controller připraví data, View je jen zobrazí:

```php
// V TsumegosController nebo Play.php
$issues = ClassRegistry::init('TsumegoIssue')->find('all', [
    'conditions' => ['tsumego_id' => $tsumegoID],
    'contain' => ['TsumegoComment', 'User']
]);
$this->set('tsumegoIssues', $issues);
$this->set('standaloneComments', $standaloneComments);
```

### Navržená struktura souborů

**ROZHODNUTÍ: Helper zatím nevytváříme!**

Existující logika pro formátování komentářů (`commentCoordinates`) je již v `TsumegosController` jako static metoda. 
Můžeme ji volat přímo z Elements. Helper vytvoříme pouze pokud se ukáže potřeba sdílet logiku mezi více views.

```
src/
├── Controller/
│   ├── TsumegoCommentsController.php   # NOVÝ - CRUD pro komentáře
│   └── TsumegoIssuesController.php     # NOVÝ - správa issues
├── View/
│   ├── Elements/                        
│   │   └── Tsumego/                     # NOVÝ adresář
│   │       ├── comments_section.ctp     # sekce komentářů (issues + volné)
│   │       ├── issue.ctp                # jeden issue s komentáři
│   │       ├── comment.ctp              # jeden komentář
│   │       └── comment_form.ctp         # formulář pro přidání komentáře
│   └── TsumegoIssues/
│       └── index.ctp                    # NOVÝ - stránka přehledu issues
```

**Existující kód k využití:**
- `TsumegosController::commentCoordinates()` - parsování Go souřadnic v textu
- `TsumegoCommentController` - základ pro add/delete (přejmenovat na plural)

**Poznámka:** CakePHP 2 hledá Elements v těchto cestách (v pořadí):
1. `src/View/Elements/`
2. `templates/Elements/` (fallback)

Projekt již používá `templates/Elements/Flash/default.ctp`, takže Elements jsou funkční.

**Použití v play.ctp:**
```php
<?= $this->element('Tsumego/comments_section', [
    'issues' => $tsumegoIssues,
    'standaloneComments' => $standaloneComments,
    'tsumegoId' => $t['Tsumego']['id']
]) ?>
```

### Výhody Elements přístupu

- ✅ Sleduje CakePHP 2 konvence
- ✅ HTML v `.ctp` souborech, logika oddělená
- ✅ Znovupoužitelnost a testovatelnost
- ✅ Native caching podpora

### CakePHP 2 Controller vzory (z dokumentace)

```php
// Kontrola HTTP metody
$this->request->is('post');
$this->request->is('ajax');
$this->request->allowMethod(['post']);  // Vyhodí exception pro jiné metody

// Přístup k POST datům
$this->request->data['Comment']['message'];
$this->request->data('Comment.message');  // Bezpečnější, vrací null

// Redirect
return $this->redirect($this->referer());
return $this->redirect(['controller' => 'tsumegos', 'action' => 'play', $id]);

// JSON response pro AJAX (vyžaduje RequestHandler component)
$this->set('success', true);
$this->set('_serialize', ['success']);

// Načtení modelu
$this->loadModel('TsumegoComment');
// nebo
ClassRegistry::init('TsumegoComment')->find('all', [...]);

// Předání dat do view
$this->set('issues', $issues);
$this->set(compact('issues', 'comments'));
```

### Existující TsumegoCommentController

Projekt již má základ v `src/Controller/TsumegoCommentController.php`:
```php
class TsumegoCommentController extends AppController
{
    public function add() { /* ... */ }
    public function delete($id) { /* ... */ }
}
```
→ Tento controller rozšíříme, přejmenujeme na `TsumegoCommentsController` (plurál)

## 🎨 UI Design

### Hlavní layout - Smíchaný seznam s taby

```
┌─────────────────────────────────────────────────────────────┐
│ 💬 Comments (8)                                             │
│ ┌─────────┬────────────┬──────────────┐                     │
│ │  ALL    │  COMMENTS  │  ISSUES (2)  │                     │
│ │  (8)    │    (6)     │   🔴 1 open  │                     │
│ └─────────┴────────────┴──────────────┘                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ 👤 player1 • Mar. 15, 2024                                 │
│ Nice problem! Very tricky.                                  │
│                                                             │
│ ┌─ ISSUE #1 ─────────────────────────── ✅ CLOSED ─────┐   │
│ │ 👤 kovarex • Mar. 16, 2024                           │   │
│ │ A15-B16-C17 should also be accepted.                 │   │
│ │                                                      │   │
│ │ 👤 admin • Mar. 17, 2024                             │   │
│ │ Your move(s) have been added.                        │   │
│ └──────────────────────────────────────────────────────┘   │
│                                                             │
│ 👤 beginner99 • Mar. 18, 2024                              │
│ I don't understand why C4 doesn't work...                  │
│                                                             │
│ ┌─ ISSUE #2 ─────────────────────────── 🔴 OPENED ─────┐   │
│ │ 👤 player2 • Mar. 20, 2024                           │   │
│ │ Missing variant: Q13-R14-S15...                      │   │
│ └──────────────────────────────────────────────────────┘   │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ [Add Comment Form...]                                       │
└─────────────────────────────────────────────────────────────┘
```

### Taby
- **ALL** = vše smícháno chronologicky (default)
- **COMMENTS** = pouze standalone komentáře  
- **ISSUES** = pouze issues (s indikátorem kolik je opened)

### Vizuální rozlišení
- **ISSUE** = má rámeček + status badge + reply uvnitř boxu
- **KOMENTÁŘ** = bez rámečku, jednoduchý

### Status badges
- 🔴 OPENED - červený badge
- ✅ CLOSED - zelený badge

### Řazení
Chronologicky podle data:
- Standalone komentář → `created`
- Issue → `created` (datum prvního komentáře)

### Admin akce
U issue (viditelné jen pro adminy):
```
[✓ Close Issue]  [↩ Reopen]
```

U komentáře (admin only):
```
[📤 Move to Issue ▾]  [🗑 Delete]
```

---

### Databázová struktura (již implementována)

**Migrace:** `20251127021907_convert_comments.php`

#### Tabulka `tsumego_issue_status`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| id | INT | PK |
| name | VARCHAR(16) | Název statusu |

**Statusy:**
- `1` = opened (otevřený)
- `2` = closed (uzavřený)
- `3` = reviewed (přezkoumáno)
- `4` = deleted (smazáno)

#### Tabulka `tsumego_issue`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| id | INT AUTO_INCREMENT | PK |
| tsumego_issue_status_id | INT | FK → tsumego_issue_status |
| tsumego_id | INT | FK → tsumego |
| user_id | INT | FK → user (autor issue) |
| created | DATETIME | Datum vytvoření |

#### Tabulka `tsumego_comment`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| id | INT AUTO_INCREMENT | PK |
| tsumego_id | INT | FK → tsumego |
| tsumego_issue_id | INT NULL | FK → tsumego_issue (NULL = volný komentář) |
| message | VARCHAR(2048) | Text komentáře |
| created | DATETIME | Datum vytvoření |
| user_id | INT | FK → user |
| position | VARCHAR(300) NULL | Pozice na desce |
| deleted | BOOL | Soft delete flag |

### Existující modely

1. **`TsumegoIssue`** (`src/Model/TsumegoIssue.php`)
   - Statické konstanty pro statusy
   - Metoda `statusName($status)` pro překlad statusu

2. **`TsumegoComment`** (`src/Model/TsumegoComment.php`)
   - Základní model bez logiky

### Existující renderery

1. **`TsumegoCommentsRenderer`** (`src/Utility/TsumegoCommentsRenderer.php`)
   - Hlavní renderer pro komentáře tsumega
   - Načte všechny issues a vytvoří pro každý `TsumegoIssuesRenderer`
   - Načte volné komentáře (bez issue) do `TsumegoCommentsSectionRenderer`

2. **`TsumegoIssuesRenderer`** (`src/Utility/TsumegoIssuesRenderer.php`)
   - Renderuje jeden issue se všemi jeho komentáři
   - Velmi základní - pouze vypíše status + komentáře

3. **`TsumegoCommentsSectionRenderer`** (`src/Utility/TsumegoCommentsSectionRenderer.php`)
   - Renderuje seznam komentářů (buď v rámci issue nebo volné)
   - Obsahuje logiku pro zobrazení pozice na desce
   - Hodně zakomentovaného kódu (delete, admin odpovědi)

### Integrace do UI

- **`play.ctp`** (řádek 597): `new TsumegoCommentsRenderer($t['Tsumego']['id'])->render();`
- **`Play.php`**: Používá `TsumegoCommentsRenderer`

---

## 🎯 Požadované funkce

### 1. Vylepšené zobrazení komentářů a issues

**Aktuální stav:**
- Issue se zobrazuje jako prosté: "Opened issue" + komentáře pod sebou
- Žádné vizuální rozlišení issues a volných komentářů
- Chybí informace o autorovi issue, datu

**Cíl:**
- Vizuálně odlišit issues od volných komentářů (box, barva, ikona)
- Zobrazit status issue jasně (barevný badge: zelený=closed, červený=opened)
- Zobrazit autora issue a datum vytvoření
- Kolapsibilní issues (sbalení/rozbalení komentářů)
- Číslování issues (Issue #1, #2, ...)

### 2. Přidání komentáře

**Akce:**
- **Přidat volný komentář** (nezávislý na issue)
- **Přidat komentář do existujícího issue** (odpověď)

**UI Flow:**
- Formulář pro nový komentář na stránce tsumega
- Volba: "Komentář" vs "Komentář k issue #X" (dropdown)
- Možnost přiložit pozici na desce (existující funkcionalita)

### 3. Vytvoření nového Issue

**Akce:**
- **Přidat komentář a vytvořit nový issue** (první komentář = otevírá issue)

**UI Flow:**
- Checkbox nebo tlačítko "Nahlásit problém" u formuláře
- Nový issue se vytvoří automaticky se statusem "opened"
- Autor issue = autor prvního komentáře

### 4. Přesun existujícího komentáře do Issue

**Akce (admin only):**
- Vzít existující volný komentář a přesunout ho do issue (nového nebo existujícího)

**UI Flow:**
- Akční tlačítko u komentáře "Přesunout do issue"
- Dropdown: "Nový issue" nebo "Issue #X"

### 5. Zavření/Znovuotevření Issue

**Akce (admin only):**
- Změnit status issue na "closed" nebo zpět na "opened"

**UI Flow:**
- Tlačítko u issue: "Zavřít issue" / "Znovu otevřít"
- Možnost přidat komentář při zavírání (volitelné)

### 6. Globální přehled otevřených Issues

**Nová stránka/sekce:**
- Seznam všech otevřených issues napříč všemi tsumegy
- Filtrování: všechny/opened/closed
- Řazení: datum, tsumego, autor
- Odkaz na konkrétní tsumego

**URL návrh:** `/tsumegos/issues` nebo `/admin/issues`

---

## 📋 Implementační plán (Aktualizovaný)

### Fáze 0: Architektonická příprava 🏗️

**Cíl:** Připravit správnou CakePHP 2 strukturu před implementací features

**Úkoly:**
1. Vytvořit `TsumegoCommentHelper` v `src/View/Helper/`
2. Vytvořit adresář `templates/Elements/Tsumego/` pro elements
3. Refaktorovat stávající Renderery - místo echo vracet string
4. Přesunout data loading z Rendererů do Controlleru/Play.php

**Soubory k vytvoření:**
```
src/Controller/TsumegoCommentsController.php  # NOVÝ controller pro komentáře
src/Controller/TsumegoIssuesController.php    # NOVÝ controller pro issues
src/View/Elements/Tsumego/comments_section.ctp
src/View/Elements/Tsumego/issue.ctp  
src/View/Elements/Tsumego/comment.ctp
src/View/Elements/Tsumego/comment_form.ctp
src/View/TsumegoIssues/index.ctp              # stránka přehledu issues
```

**Soubory k upravení:**
- `src/Controller/Component/Play.php` - přidat načtení dat pro komentáře
- `src/Controller/TsumegosController.php` - registrace Helperu
- `src/View/Tsumegos/play.ctp` - použít elements místo Renderer

### Fáze 1: Vylepšené zobrazení ✏️

**Vytvořit Elements:**
- `comment.ctp` - jeden komentář
- `issue.ctp` - issue s komentáři
- `comments_section.ctp` - celá sekce
- `comment_form.ctp` - formulář

**CSS styling:**
- Badges pro statusy (opened=červený, closed=zelený)
- Kolapsibilní issues

### Fáze 2: CRUD operace pro komentáře

**Úkoly:**
1. Vytvořit `TsumegoCommentsController`:
   - `add($tsumegoId)` - přidání komentáře (POST)
   - `delete($commentId)` - smazání komentáře (POST, owner nebo admin)
   
2. Formulář v `comment_form.ctp`:
   - Textarea pro zprávu
   - Select pro výběr issue (volitelné)
   - Position picker (existující funkcionalita)
   - Submit tlačítko

3. Routes v `config/routes.php`

4. Testy pro controller

**Soubory:**
- `src/Controller/TsumegoCommentsController.php` (NOVÝ)
- `src/View/Elements/Tsumego/comment_form.ctp`
- `config/routes.php`
- `tests/TestCase/Controller/TsumegoCommentsControllerTest.php` (NOVÝ)

### Fáze 3: Správa Issues

**Úkoly:**
1. Vytvořit `TsumegoIssuesController`:
   - `index()` - seznam všech issues s filtrováním
   - `create($tsumegoId)` - vytvoření issue s prvním komentářem
   - `close($issueId)` - zavření issue
   - `reopen($issueId)` - znovuotevření
   - `moveComment($commentId)` - přesun komentáře do issue

2. UI prvky v Elements:
   - "Report Issue" checkbox u formuláře
   - "Close Issue" / "Reopen" tlačítka (admin only)
   - "Move to Issue" dropdown (admin only)

3. Autorizace:
   - `Auth::isAdmin()` pro admin akce
   - Autor issue může zavřít vlastní issue

**Soubory:**
- `src/Controller/TsumegoIssuesController.php` (NOVÝ)
- `src/View/TsumegoIssues/index.ctp` (NOVÝ)
- `src/View/Elements/Tsumego/issue.ctp` (admin buttons)
- `config/routes.php`
- `tests/TestCase/Controller/TsumegoIssuesControllerTest.php` (NOVÝ)

### Fáze 4: Globální přehled Issues

**Poznámka:** Tato fáze je integrována do Fáze 3 - `TsumegoIssuesController::index()`

---

## 🔧 Technické detaily

### API Endpointy (návrh)

**TsumegoCommentsController:**
```
POST   /tsumego-comments/add/:tsumegoId
  - message: string
  - tsumego_issue_id: int|null (null = volný komentář)
  - position: string|null

POST   /tsumego-comments/delete/:commentId
  - (autorizace: owner nebo admin)
```

**TsumegoIssuesController:**
```
GET    /tsumego-issues
  - status: opened|closed|all (default: opened)
  - page: int (paginace)

POST   /tsumego-issues/create/:tsumegoId
  - message: string (první komentář)
  - position: string|null

POST   /tsumego-issues/close/:issueId
  - message: string|null (volitelný závěrečný komentář)

POST   /tsumego-issues/reopen/:issueId
  - (admin only)

POST   /tsumego-issues/move-comment/:commentId
  - tsumego_issue_id: int|null (null = nový issue)
  - (admin only)
```

### Autorizace

| Akce | Kdo může |
|------|----------|
| Přidat komentář | Přihlášený uživatel |
| Vytvořit issue | Přihlášený uživatel |
| Zavřít issue | Admin nebo autor issue |
| Znovu otevřít issue | Admin |
| Přesunout komentář | Admin |
| Smazat komentář | Admin nebo autor komentáře |

### CSS třídy (návrh)

```css
.tsumego-issue { /* wrapper pro issue */ }
.tsumego-issue--opened { /* otevřený issue */ }
.tsumego-issue--closed { /* zavřený issue */ }
.tsumego-issue__header { /* hlavička s číslem a statusem */ }
.tsumego-issue__badge { /* status badge */ }
.tsumego-issue__comments { /* kontejner pro komentáře */ }
.tsumego-comment { /* jednotlivý komentář */ }
.tsumego-comment--admin { /* admin komentář */ }
.tsumego-comment--standalone { /* volný komentář mimo issue */ }
```

---

## 📝 Testovací scénáře

### Browser testy

1. Celý flow: vytvoř issue → přidej komentář → zavři
2. Přepínání kolapsibilních issues
3. Filtry na stránce přehledu

---

## 📌 Poznámky

- Starý model `Comment` zůstává pro zpětnou kompatibilitu (původní tabulka `comment`)
- Nové komentáře jdou do `tsumego_comment`, nové issues do `tsumego_issue`
- Migrace `20251127021907_convert_comments.php` již převedla staré komentáře
- Branch `comments` obsahuje základní strukturu, je třeba doladit UI a přidat CRUD
- **Elements jsou ověřeny** - CakePHP je hledá v `src/View/Elements/` (testováno a funguje)

---

## ✅ Checklist pro implementaci

### Fáze 0 - Příprava ✅ COMPLETE
- [x] Databázová struktura (migrace `20251127021907_convert_comments.php`)
- [x] Modely existují (`TsumegoIssue`, `TsumegoComment`)
- [x] Ověřeno že Elements fungují
- [x] Rozhodnuto: Helper NENÍ potřeba (použijeme existující `TsumegosController::commentCoordinates`)
- [x] Přejmenovat `TsumegoCommentController` → `TsumegoCommentsController`
- [x] Vytvořit `TsumegoIssuesController`
- [x] Smazat staré Renderery (`TsumegoCommentsRenderer`, `TsumegoIssuesRenderer`, `TsumegoCommentsSectionRenderer`)
- [x] Element `comment.ctp` - vytvořen v `src/View/Elements/TsumegoComments/`
- [x] Element `issue.ctp` - vytvořen v `src/View/Elements/TsumegoIssues/`
- [x] Element `section.ctp` - vytvořen v `src/View/Elements/TsumegoComments/`
- [x] Element `form.ctp` - vytvořen v `src/View/Elements/TsumegoComments/`
- [x] Přidat custom find do `TsumegoIssue` modelu (`find('withComments')`)
- [x] Upravit `Play.php` component - `loadCommentsData()` metoda
- [x] Upravit `play.ctp` - používá Elements místo Rendererů
- [x] Routes pro `/tsumego-comments/*` a `/tsumego-issues/*`
- [x] Všech 180 testů prochází

### Fáze 1 - Zobrazení
- [x] Základní Elements vytvořeny (viz Fáze 0)
- [ ] CSS styling pro issues (rámečky, barvy, badges) - základní styl existuje, potřeba doladit
- [ ] Kolapsibilní issues (JS) - zatím nejsou kolapsibilní
- [ ] Responzivní design
- [ ] Visual QA na reálných datech

### Fáze 2 - Přidání komentářů ✅ MOSTLY COMPLETE
- [x] `TsumegoCommentsController` existuje (add, delete)
- [x] Element `form.ctp` pro přidání komentáře
- [x] Routes
- [x] Funkční formulář (POST, ne AJAX - stránka se refreshne)
- [x] Odpověď na issue (reply tlačítko v issue boxu)
- [ ] AJAX odesílání (nice-to-have, zatím funguje POST)
- [ ] Position picker integrace (pole existuje, chybí UI picker)
- [x] Testy pro controller - 8 browser testů v `CommentsControllerTest.php`

### Fáze 3 - Správa issues ✅ MOSTLY COMPLETE
- [x] `TsumegoIssuesController` existuje (create, close, reopen, moveComment, removeComment)
- [x] Admin tlačítka v UI (Close Issue, Reopen, Move to Issue, Remove from Issue)
- [x] Autorizace (admin nebo autor)
- [x] Testy pro controller - součástí CommentsControllerTest (testDeleteOwnComment, testReplyToIssue)
- [x] Auto-delete prázdných issues (`TsumegoIssue::deleteIfEmpty()` - smaže issue když nemá žádné komentáře)

### Fáze 4 - Globální přehled ✅ MOSTLY COMPLETE
- [x] `TsumegoIssuesController::index()` action připravena
- [x] View `TsumegoIssues/index.ctp` existuje s CSS styly
- [x] Filtry (opened/closed/all) - fungují
- [x] Paginace - implementována v controlleru i view
- [ ] Odkaz v admin menu - potřeba přidat

---

## 🧪 Testovací data

Pro testování komentářů a issues použijte tyto tsumega s produkčními daty:

### Tsumega s OBĚMA issues a standalone komentáři:
| Tsumego ID | Issues | Standalone Comments | URL |
|------------|--------|---------------------|-----|
| **15902** | 2 | 18 | https://tsumego.ddev.site:33003/tsumegos/play/15902 |
| **17264** | 3 | 15 | https://tsumego.ddev.site:33003/tsumegos/play/17264 |
| 2847 | 1 | 18 | https://tsumego.ddev.site:33003/tsumegos/play/2847 |
| 25550 | 1 | 17 | https://tsumego.ddev.site:33003/tsumegos/play/25550 |
| 7321 | 1 | 14 | https://tsumego.ddev.site:33003/tsumegos/play/7321 |

### Tsumega s MNOHA standalone komentáři (bez issues):
| Tsumego ID | Comments | URL |
|------------|----------|-----|
| 15551 | 78 | https://tsumego.ddev.site:33003/tsumegos/play/15551 |
| 15508 | 32 | https://tsumego.ddev.site:33003/tsumegos/play/15508 |
| **15352** | 31 | https://tsumego.ddev.site:33003/tsumegos/play/15352 *(DEFAULT_TSUMEGO_ID)* |

### Tsumega s issues (pro testování issue flow):
| Issue ID | Tsumego ID | Status | Comments in Issue |
|----------|------------|--------|-------------------|
| 1 | 9690 | closed (2) | 2 |
| 2 | 3015 | closed (2) | 2 |

---

## 🔧 Aktuální stav souborů

### Nově vytvořené soubory (Fáze 0):
```
src/Controller/TsumegoCommentsController.php   # add(), delete() actions
src/Controller/TsumegoIssuesController.php     # index(), create(), close(), reopen(), moveComment()
src/View/Elements/TsumegoComments/section.ctp  # hlavní sekce komentářů
src/View/Elements/TsumegoComments/comment.ctp  # jeden komentář
src/View/Elements/TsumegoComments/form.ctp     # formulář pro přidání
src/View/Elements/TsumegoIssues/issue.ctp      # jeden issue s komentáři
```

### Upravené soubory:
```
src/Controller/Component/Play.php              # loadCommentsData() metoda
src/Model/TsumegoIssue.php                     # find('withComments'), loadCommentsForIssue()
src/Model/TsumegoComment.php                   # loadStandaloneComments() - pro komentáře bez issue
src/View/Tsumegos/play.ctp                     # používá Elements místo Rendererů
config/routes.php                              # routes pro comments/issues controllers
```

### Smazané soubory:
```
src/Utility/TsumegoCommentsRenderer.php        # nahrazeno Elements
src/Utility/TsumegoIssuesRenderer.php          # nahrazeno Elements
src/Utility/TsumegoCommentsSectionRenderer.php # nahrazeno Elements
```

---

## 🚀 Import produkčních dat

Pro práci s reálnými daty použijte skript:
```powershell
.\.local\import-and-setup-db.ps1
```

**Co skript dělá:**
1. Dropne a znovu vytvoří `db` databázi
2. Importuje produkční data z `E:\Projects\tsumego-db\db.sql` (~1.2 GB)
3. Spustí nové migrace (phinxlog z produkce říká které jsou už aplikované)
4. Opraví schema issues (AUTO_INCREMENT)
5. Vytvoří admin účet: `admin` / `admin`

**Potřebné soubory:**
- `E:\Projects\tsumego-db\db.sql` - produkční dump databáze
- `.local/fix-schema.sql` - opravy schema (AUTO_INCREMENT na user_contribution)
- `.local/create-admin.sql` - vytvoření admin účtu
