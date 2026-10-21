# QA-portfolio: Woody

> Portfolio för en roll som **mjukvarutestare / QA**. Här finns testdokumentation, testautomation
> och GDPR-arbete från riktiga projekt, och de kurser jag har gått och kommer att gå.

<!-- TODO: Skriv 2–3 meningar om dig själv: bakgrund, utbildning, vilken roll du söker och var. -->

> **Ny här?** I [KOMPETENSER.md](KOMPETENSER.md) beskrivs varje kompetens: vad jag har gjort, var det syns och
> vilken kurs det hör till.

## Snabböversikt

| | |
|---|---|
| **Metodik** | ISTQB CTFL v4.0, testdokumentation enligt ISO/IEC/IEEE 29119-3, kvalitetsegenskaper enligt ISO/IEC 25010 |
| **Automation** | Playwright (E2E i Chromium, Firefox och mobil), PHPUnit (enhets-, integrations- och API-tester) |
| **Testmiljö** | Docker (webbserver, databas med migreringar och fiktiv testdata, mockade externa API:er) |
| **Regelverk** | GDPR (registerförteckning Art. 30, export/radering Art. 15/17/20, säkerhet Art. 32), ePrivacy, DSA, NIS2, tillgänglighet |
| **Projekt** | [hakoo.eu](projects/hakoo/), en communitysajt i drift med inloggning och 15+ spel · [Mysteries](projects/mysteries/), en spelmotor för sällskapsspel på flera skärmar |

## Kompetens och var den syns

| Kompetens | Bevis |
|---|---|
| Testplanering | [Testplan](projects/hakoo/qa/test-plan.md) · [Mysteries testplan](projects/mysteries/qa/test-plan.md) |
| Riskbaserad testning | [Riskregister](projects/hakoo/qa/risk-register.md) (sannolikhet × konsekvens → testprioritet) |
| Kravanalys och testunderlag | [User stories med Given/When/Then](projects/hakoo/qa/requirements/user-stories.md) · [funktionella krav](projects/hakoo/qa/requirements/functional-requirements.md) · [icke-funktionella krav](projects/hakoo/qa/requirements/non-functional-requirements.md) · [kravsammanställning från kravintervju](projects/mysteries/qa/requirements/kravsammanstallning.md) |
| Testdesigntekniker | [Ekvivalensklasser och gränsvärdesanalys](projects/hakoo/qa/test-design/equivalence-partitioning-bva.md) · [beslutstabeller](projects/hakoo/qa/test-design/decision-tables.md) · [tillståndsövergångar](projects/hakoo/qa/test-design/state-transition.md) |
| Manuell testning | [Manuella testfall](projects/hakoo/qa/test-cases/manual-test-cases.md) |
| Testautomation | [Playwright E2E](projects/hakoo/qa/automation/e2e/) · [PHPUnit](projects/hakoo/qa/automation/integration/) · [mock av externt API](projects/hakoo/qa/automation/mocks/) |
| Spårbarhet | [Spårbarhetsmatris](projects/hakoo/qa/traceability-matrix.md): krav → testfall → status |
| Felrapportering | [Defektlogg](projects/hakoo/qa/defects/defect-log.md) (reproduktionssteg, rotorsak, bekräftelsetest) · [mall](templates/defect-report-template.md) |
| Testrapportering | [Testcykel 1](projects/hakoo/qa/test-reports/cycle-1-completion-report.md) · [Testcykel 2](projects/hakoo/qa/test-reports/cycle-2-completion-report.md) (slutkriterier, lärdomar) |
| Säkerhetstestning | Statisk granskning som hittade två kritiska brister (förfalskade inloggningar, kapning av kontolänkar), se DEF-010/011 i [defektloggen](projects/hakoo/qa/defects/defect-log.md) |
| GDPR och dataskydd | [Personuppgiftsregister (Art. 30)](projects/hakoo/gdpr/personal-data-register.md) · [arkitektur för persondata](projects/hakoo/gdpr/personal-data-architecture.md) · [regulatoriska krav](projects/hakoo/qa/requirements/regulatory-requirements.md) · [genomgång av EU-regler](projects/hakoo/gdpr/eu-regulations/) |
| Testresultat | [Körning med 445 gröna PHPUnit-tester](projects/hakoo/evidence/integration-testdox.txt) · [skärmbilder från E2E](projects/hakoo/evidence/) |

## Projekten i siffror

| | hakoo.eu | Mysteries |
|---|---|---|
| User stories | 54 | 26 |
| Krav (funktionella, icke-funktionella, regulatoriska) | ~420 | ~120 |
| PHPUnit-tester | 430 | 116 |
| Playwright-tester (× 3 webbläsare/enheter) | 193 | 29 |
| Registrerade defekter | 19 | — |

## Utbildning

**IT-testspecialist**, yrkeshögskoleutbildning på 400 YH-poäng vid Frans Schartaus Handelsinstitut (2026–2027).
Utbildningen omfattar testprocessen, testtekniker, krav, teststrategi, testverktyg, testdata och GDPR, testautomation,
användbarhetstest, ISTQB Foundation Level med certifiering och två LIA-perioder (praktik).

| Termin | Kurser | Status |
|---|---|---|
| VT 2026 | Kommunikation och teamarbete · Applied Script · QA – En introduktion · Testtekniker · Testnivåer och metodiker | ✅ |
| HT 2026 | Krav och användningsfall ✅ · Teststrategi och planering 🟡 · Testverktyg · Testdata, testmiljöer och GDPR | 🟡 |
| VT 2027 | Test Automation · LIA 1 · GUI och användbarhetstest · ISTQB Foundation Level | ⬜ |
| HT 2027 | LIA 2 · Examensarbete | ⬜ |

**Betyg: VG i alla avslutade kurser hittills** (6 av 6).

Varje kurs, med innehåll, examensmål och länkar till bevis i projekten, finns i [courses/](courses/).

## Hur arbetet har gjorts

Projekten är mina egna. Jag är projektägare och testansvarig: jag har ställt kraven, prioriterat,
granskat och godkänt testunderlag och resultat.
Jag har arbetat tillsammans med AI-assistenten **Claude (Anthropic)** som verktyg för att skriva testdokumentation,
testkod och analyser. Defekter som Claude hittade står med "Claude" som rapportör i defektloggen.
Att styra, granska och kvalitetssäkra AI-genererat arbete är en del av det jag vill visa här.

## Om materialet

- Ögonblicksbild från oktober 2026. Själva produktkoden ligger inte här, bara QA- och GDPR-materialet.
  Relativa länkar till produktkod (`src/`, `docker/`, `database/`) leder därför ingenstans.
- All testdata är fiktiv (Alice, Bob, Carol, påhittade ID:n). Inga riktiga användaruppgifter finns med.
- En säkerhetsbrist som inte är åtgärdad ännu (DEF-013) är maskerad enligt principen om ansvarsfullt
  avslöjande (responsible disclosure).
- Känsliga namn är maskerade med platshållare i formen `<NAMN>`. Det gäller hemligheter, konfigurationsfiler,
  databas och webbhotell. Platshållarna står för riktiga namn, men inga riktiga värden finns i repot.
  Testkoden går därför inte att köra som den står här.

  | Platshållare | Står för |
  |---|---|
  | `<SESSION_SECRET>`, `<API_SECRET>`, `<BOT_SECRET>`, `<DELETION_LOG_SECRET>` | Serverhemligheter (namnet på inställningen, aldrig värdet) |
  | `<TEST_SECRET>`, `<TEST_SECRET_VALUE>`, `<WRONG_SECRET_VALUE>` | Hemligheter som bara används i testmiljön |
  | `<BOT_AUTH_HEADER>` | HTTP-headern som botten autentiserar sig med |
  | `<CONFIG_FILE>`, `<DEPLOY_DIR>`, `<FTP_CREDENTIALS_FILE>`, `<CREDENTIALS_FILE>` | Filer med hemligheter, som aldrig checkas in |
  | `<DB_NAME>` | Databasens namn |
  | `<HOSTING_PROVIDER>`, `<HOSTING_PANEL>`, `<WEB_SERVER>` | Webbhotell, kontrollpanel och webbserver |
- Dokumentationen är på engelska. Kravsammanställningen för Mysteries är på svenska, eftersom den kommer
  från en kravintervju på svenska.

## Kontakt

<!-- TODO: LinkedIn, e-post (gärna en separat jobbsökaradress) -->
