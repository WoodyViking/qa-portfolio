# Kompetenser: vad jag har gjort, var det syns och vilken kurs det hör till

Det här dokumentet är till för dig som vill förstå vad jag kan, utan att leta igenom hela repot.
Varje avsnitt beskriver **en kompetens**: vad jag har gjort, **filerna där arbetet finns** och **kurserna** i
utbildningen [IT-testspecialist (YH)](courses/) där kunskapen hör hemma.

Allt arbete kommer från två egna projekt:
[hakoo.eu](projects/hakoo/), en communitysajt i drift med inloggning, profiler och 15+ webbspel, och
[Mysteries](projects/mysteries/), en spelmotor för sällskapsspel på flera skärmar.

## Läs på fem minuter

Har du bara några minuter? Börja här:

1. [Defektloggen](projects/hakoo/qa/defects/defect-log.md), DEF-010 och DEF-011: två kritiska säkerhetsbrister som jag
   hittade med statisk testning, med reproduktion, rotorsak, åtgärd och bekräftelsetest.
2. [Spårbarhetsmatrisen](projects/hakoo/qa/traceability-matrix.md): varje krav kopplat till sina testfall och sin status.
3. [Testrapporten för cykel 2](projects/hakoo/qa/test-reports/cycle-2-completion-report.md): slutkriterier, defekter,
   driftsättningssteg och lärdomar.
4. [Personuppgiftsregistret](projects/hakoo/gdpr/personal-data-register.md): GDPR Art. 30 i praktiken.

## Översikt: kompetens × kurs

| Kompetens | 01 | 02 | 03 | 04 | 05 | 06 | 07 | 08 | 09 | 10 | 12 | 13 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| [1. Testprocess och QA-struktur](#1-testprocess-och-qa-struktur) | | | ● | | | | ● | | | | | ● |
| [2. Krav och kravanalys](#2-krav-och-kravanalys) | ● | | | | | ● | | | | | | |
| [3. Testdesign och testtekniker](#3-testdesign-och-testtekniker) | | | ● | ● | | | | | | | ● | ● |
| [4. Testnivåer och testautomation](#4-testnivåer-och-testautomation) | | ● | | | ● | | | ● | | ● | | |
| [5. Testplanering, risk och rapportering](#5-testplanering-risk-och-rapportering) | ● | | | | ● | | ● | | | | | ● |
| [6. Felrapportering och defekthantering](#6-felrapportering-och-defekthantering) | | | ● | ● | | | | ● | | | | ● |
| [7. Säkerhetstestning](#7-säkerhetstestning) | | | | ● | ● | | | | ● | | | ● |
| [8. GDPR, testdata och testmiljöer](#8-gdpr-testdata-och-testmiljöer) | | ● | | | | | | | ● | | | |
| [9. GUI, användbarhet och tillgänglighet](#9-gui-användbarhet-och-tillgänglighet) | | | | | ● | | | | | | ● | |
| [10. Kommunikation med beställare](#10-kommunikation-med-beställare) | ● | | | | | ● | ● | | | | | |

**Kurser:**
[01 Kommunikation och teamarbete](courses/01-kommunikation-och-teamarbete/) ·
[02 Applied Script](courses/02-applied-script/) ·
[03 QA – En introduktion](courses/03-qa-en-introduktion/) ·
[04 Testtekniker](courses/04-testtekniker/) ·
[05 Testnivåer och metodiker](courses/05-testnivaer-och-metodiker/) ·
[06 Krav och användningsfall](courses/06-krav-och-anvandningsfall/) ·
[07 Teststrategi och planering](courses/07-teststrategi-och-planering/) ·
[08 Testverktyg](courses/08-testverktyg/) ·
[09 Testdata, testmiljöer och GDPR](courses/09-testdata-testmiljoer-och-gdpr/) ·
[10 Test Automation](courses/10-test-automation/) ·
[12 GUI och användbarhetstest](courses/12-gui-och-anvandbarhetstest/) ·
[13 ISTQB Foundation Level](courses/13-istqb-foundation-level/).
LIA 1, LIA 2 och examensarbetet läggs till när de är gjorda.

---

## 1. Testprocess och QA-struktur

**Vad jag gjorde:** byggde upp all QA-dokumentation efter ISTQB:s testprocess (CTFL v4.0): planering, analys,
design, implementering, genomförande, uppföljning och avslut. Dokumenten följer ISO/IEC/IEEE 29119-3 och
kvalitetsegenskaperna följer ISO/IEC 25010. Samma struktur återanvändes i det andra projektet.

| Var det syns | Titta efter |
|---|---|
| [QA-översikt för hakoo.eu](projects/hakoo/qa/README.md) | Tabellen som kopplar varje ISTQB-aktivitet till sina arbetsprodukter |
| [QA-översikt för Mysteries](projects/mysteries/qa/README.md) | Samma struktur i ett nytt projekt, med ID-konventioner för testfall |

**Kurser:** [03 QA – En introduktion](courses/03-qa-en-introduktion/) ·
[07 Teststrategi och planering](courses/07-teststrategi-och-planering/) ·
[13 ISTQB Foundation Level](courses/13-istqb-foundation-level/)

## 2. Krav och kravanalys

**Vad jag gjorde:** tog fram testunderlaget på två sätt. För hakoo.eu fanns ingen specifikation, så kraven togs fram
i efterhand ur koden (reverse engineering). Granskningen av dem var också statisk testning. För Mysteries
genomfördes en kravintervju, där varje user story har ett citat som källa och ett datum för godkännande.

| Var det syns | Titta efter |
|---|---|
| [Kravsammanställning från kravintervju](projects/mysteries/qa/requirements/kravsammanstallning.md) (svenska) | User stories med källa, krav, acceptanskriterier, avgränsningar, antaganden, öppna frågor |
| [User stories för hakoo.eu](projects/hakoo/qa/requirements/user-stories.md) | Acceptanskriterier i Given/When/Then |
| [Funktionella krav](projects/hakoo/qa/requirements/functional-requirements.md) | Kravnedbrytning per område med ID:n (`FR-AREA-nn`) |
| [Icke-funktionella krav](projects/hakoo/qa/requirements/non-functional-requirements.md) | Krav sorterade efter ISO/IEC 25010 |
| [Regulatoriska krav](projects/hakoo/qa/requirements/regulatory-requirements.md) | Lagkrav (GDPR, ePrivacy, DSA) som testbara krav |
| [Spårbarhetsmatris](projects/hakoo/qa/traceability-matrix.md) | Krav → user story → testfall → status |
| [Skolprojekt: krav och användningsfall för Gomoku](courses/06-krav-och-anvandningsfall/inlamningar/gomoku-krav-och-anvandningsfall.pdf) | Begreppslista som gemensamt språk, use cases och testfall för krav som är svåra eller omöjliga att testa (s. 4–16). Gruppens repo: [WoodyViking/Gomoku](https://github.com/WoodyViking/Gomoku) |

**Kurser:** [06 Krav och användningsfall](courses/06-krav-och-anvandningsfall/) ·
[01 Kommunikation och teamarbete](courses/01-kommunikation-och-teamarbete/) (kravintervjun)

## 3. Testdesign och testtekniker

**Vad jag gjorde:** tillämpade de svartlådetekniker som ISTQB beskriver på riktiga funktioner och dokumenterade
varför tekniken passar. För spel där varje omgång slumpas designades tester som visar att varje omgång alltid går att lösa.

| Var det syns | Titta efter |
|---|---|
| [Ekvivalensklasser och gränsvärdesanalys](projects/hakoo/qa/test-design/equivalence-partitioning-bva.md) | Giltiga och ogiltiga klasser, gränsvärden, testfall per värde |
| [Beslutstabeller](projects/hakoo/qa/test-design/decision-tables.md) | Kombinationer av villkor och förväntat utfall |
| [Tillståndsövergångar](projects/hakoo/qa/test-design/state-transition.md) | Tillstånd, händelser och ogiltiga övergångar |
| [Manuella testfall](projects/hakoo/qa/test-cases/manual-test-cases.md) | Steg, förväntat resultat, koppling till krav |
| [Testdesign för slumpat innehåll](projects/mysteries/qa/test-design/) | Hur man testar att en slumpad escape room- eller mordgåta alltid går att lösa |

**Kurser:** [04 Testtekniker](courses/04-testtekniker/) ·
[03 QA – En introduktion](courses/03-qa-en-introduktion/) ·
[12 GUI och användbarhetstest](courses/12-gui-och-anvandbarhetstest/) ·
[13 ISTQB Foundation Level](courses/13-istqb-foundation-level/)

## 4. Testnivåer och testautomation

**Vad jag gjorde:** automatiserade tester på flera testnivåer. Komponent- och integrationstester körs med PHPUnit
mot spelmotorer, API och databas. Systemtester körs med Playwright i Chromium, Firefox och en mobil vy. Varje test
har sitt TC-ID och de krav det täcker i en kommentar. Externa tjänster ersätts med mockar så att testerna är stabila.

| Var det syns | Titta efter |
|---|---|
| [PHPUnit-tester](projects/hakoo/qa/automation/integration/) (430 st.) | Engine-tester (komponent) och Api-tester (system över HTTP) |
| [Playwright-tester](projects/hakoo/qa/automation/e2e/tests/) (193 st. × 3 webbläsare/enheter) | Användarflöden i webbläsaren |
| [Playwright-konfiguration](projects/hakoo/qa/automation/e2e/playwright.config.js) | Flera webbläsare och mobil i en körning |
| [Mock av Discord och Wargaming API](projects/hakoo/qa/automation/mocks/wg-api.php) | Testisolering från externa tjänster |
| [Testkörning](projects/hakoo/evidence/integration-testdox.txt) | 445 gröna tester grupperade per testklass |
| [DEF-012 i defektloggen](projects/hakoo/qa/defects/defect-log.md) | Instabila tester: rotorsak (delad testdata vid parallell körning) och åtgärd |

**Kurser:** [05 Testnivåer och metodiker](courses/05-testnivaer-och-metodiker/) ·
[10 Test Automation](courses/10-test-automation/) ·
[08 Testverktyg](courses/08-testverktyg/) ·
[02 Applied Script](courses/02-applied-script/)

## 5. Testplanering, risk och rapportering

**Vad jag gjorde:** skrev testplaner med omfattning, testansats, testmiljö, start- och slutkriterier och mätetal.
Prioriterade med ett riskregister (sannolikhet × konsekvens). När en risk inträffade länkades den till defekten.
Avslutade varje testcykel med en testrapport.

| Var det syns | Titta efter |
|---|---|
| [Testplan för hakoo.eu](projects/hakoo/qa/test-plan.md) | Avsnitt 4 (testansats), 6 (start- och slutkriterier) och 10 (mätetal) |
| [Testplan för Mysteries](projects/mysteries/qa/test-plan.md) | En kortare plan för ett nytt projekt |
| [Riskregister](projects/hakoo/qa/risk-register.md) | R-14 och R-15: risker som inträffade och blev DEF-010 och DEF-011 |
| [Testrapport cykel 1](projects/hakoo/qa/test-reports/cycle-1-completion-report.md) och [cykel 2](projects/hakoo/qa/test-reports/cycle-2-completion-report.md) | Uppfyllda och ej uppfyllda slutkriterier, driftsättningsordning, lärdomar |
| [Skolrapport: livscykelhantering av 1177.se](courses/03-qa-en-introduktion/inlamningar/livscykelhantering-1177.pdf) | Risker analyserade i kedjor, riskmatris, och en teststrategi som också säger vad som inte går att testa |

**Kurser:** [07 Teststrategi och planering](courses/07-teststrategi-och-planering/) ·
[05 Testnivåer och metodiker](courses/05-testnivaer-och-metodiker/) ·
[01 Kommunikation och teamarbete](courses/01-kommunikation-och-teamarbete/) ·
[13 ISTQB Foundation Level](courses/13-istqb-foundation-level/)

## 6. Felrapportering och defekthantering

**Vad jag gjorde:** förde en defektlogg med 19 defekter. Varje defekt har allvarlighetsgrad, prioritet, status och
spårning till krav och testfall. De större har reproduktionssteg, förväntat och faktiskt resultat, rotorsak, åtgärd
och bekräftelsetest. Defekter i produkten och i testmaterialet hålls isär.

| Var det syns | Titta efter |
|---|---|
| [Defektlogg](projects/hakoo/qa/defects/defect-log.md) | DEF-010 (fullständig rapport), DEF-015 (GDPR), DEF-016/017 (mobil), DEF-019 (testmiljö) |
| [Mall för defektrapport](templates/defect-report-template.md) | Fälten som varje rapport ska ha |

**Kurser:** [04 Testtekniker](courses/04-testtekniker/) ·
[03 QA – En introduktion](courses/03-qa-en-introduktion/) ·
[08 Testverktyg](courses/08-testverktyg/) ·
[13 ISTQB Foundation Level](courses/13-istqb-foundation-level/)

## 7. Säkerhetstestning

**Vad jag gjorde:** granskade koden statiskt och använde felgissning kring förtroendegränser. Det gav två kritiska
fynd: kapning av andras kopplade spelkonton (DEF-010) och förfalskning av inloggningar (DEF-011). Inga attacker
kördes mot produktion. Båda fynden bekräftades och åtgärdades med automatiska tester. En öppen brist är maskerad
här enligt principen om ansvarsfullt avslöjande (responsible disclosure).

| Var det syns | Titta efter |
|---|---|
| [Defektlogg](projects/hakoo/qa/defects/defect-log.md), DEF-010 och DEF-011 | Analys, varför testet inte kördes mot produktion, åtgärd |
| [Sessionssäkerhet](projects/hakoo/qa/automation/integration/SessionSecurityTest.php) | Bekräftelsetester för DEF-011 |
| [Säkerhetsheaders](projects/hakoo/qa/automation/e2e/tests/security-headers.spec.js) | Automatisk kontroll av HTTP-säkerhetsheaders |
| [Säkerhetskrav](projects/hakoo/qa/requirements/non-functional-requirements.md) | NFR-SEC: krav på säkerhet |

**Kurser:** [05 Testnivåer och metodiker](courses/05-testnivaer-och-metodiker/) (penetrationstest) ·
[04 Testtekniker](courses/04-testtekniker/) ·
[09 Testdata, testmiljöer och GDPR](courses/09-testdata-testmiljoer-och-gdpr/) ·
[13 ISTQB Foundation Level](courses/13-istqb-foundation-level/) (statisk testning)

## 8. GDPR, testdata och testmiljöer

**Vad jag gjorde:** förde en registerförteckning över allt som lagras om en person och hur det exporteras och
raderas. Testade att "Ladda ner mina data" och "Radera mina data" fungerar, och hittade en brist i exporten (DEF-015).
All testdata är fiktiv och externa tjänster är mockade. Testmiljön körs i Docker med riktiga migreringar.

| Var det syns | Titta efter |
|---|---|
| [Personuppgiftsregister](projects/hakoo/gdpr/personal-data-register.md) | Varje tabell, fil och cookie: vad, export, radering (Art. 30) |
| [Arkitektur för persondata](projects/hakoo/gdpr/personal-data-architecture.md) | Avvägningar och beslut om var persondata ligger |
| [Genomgång av EU-regler](projects/hakoo/gdpr/eu-regulations/) | GDPR, ePrivacy, NIS2, DSA, tillgänglighet, AI-förordningen, med en checklista |
| [Tester av export och radering](projects/hakoo/qa/automation/integration/MyDataApiTest.php) | Systemtester av rätten till tillgång och radering (Art. 15/17/20) |
| [Integritetstester i webbläsaren](projects/hakoo/qa/automation/e2e/tests/privacy.spec.js) | Sidor som lovar "inget sparas" kontrolleras på cookies och lagring |
| [Fiktiv testdata](projects/hakoo/qa/automation/integration/HakooTestData.php) | Inga produktionsdata i testerna |
| [DEF-015 i defektloggen](projects/hakoo/qa/defects/defect-log.md) | GDPR-brist hittad genom att registret jämfördes med exporten |

**Kurser:** [09 Testdata, testmiljöer och GDPR](courses/09-testdata-testmiljoer-och-gdpr/) ·
[02 Applied Script](courses/02-applied-script/) (SQL)

## 9. GUI, användbarhet och tillgänglighet

**Vad jag gjorde:** testade gränssnittet i flera webbläsare och skärmbredder, med större textstorlekar och
högkontrasttema. Skärmbilderna tas automatiskt av testerna. Defekter som användare upplever på mobilen hittades och åtgärdades.

| Var det syns | Titta efter |
|---|---|
| [Skärmbilder från testerna](projects/hakoo/evidence/) | Chromium, Firefox, mobil, 390 px, högkontrast |
| [Utseendetester](projects/hakoo/qa/automation/e2e/tests/appearance.spec.js) | Teman, kontrast och dialoger på flera sidor |
| [DEF-016 och DEF-017](projects/hakoo/qa/defects/defect-log.md) | Fel som bara syns på mobil med pekskärm |

**Kurser:** [12 GUI och användbarhetstest](courses/12-gui-och-anvandbarhetstest/) ·
[05 Testnivåer och metodiker](courses/05-testnivaer-och-metodiker/) (icke-funktionell testning)

## 10. Kommunikation med beställare

**Vad jag gjorde:** genomförde en kravintervju och dokumenterade beställarens egna ord. Skrev testrapporter för
beställare med tydliga rekommendationer, till exempel i vilken ordning driftsättningen ska ske.

| Var det syns | Titta efter |
|---|---|
| [Kravsammanställning](projects/mysteries/qa/requirements/kravsammanstallning.md) | Citat som källa, godkännanden med datum, öppna frågor |
| [Testrapport cykel 2](projects/hakoo/qa/test-reports/cycle-2-completion-report.md) | Avsnitt 6: driftsättningssteg i rätt ordning |
| [Arkitekturbeslut ADR-001](projects/mysteries/docs/adr-001-architecture.md) | Alternativ, avvägningar och konsekvenser |

**Kurser:** [01 Kommunikation och teamarbete](courses/01-kommunikation-och-teamarbete/) ·
[06 Krav och användningsfall](courses/06-krav-och-anvandningsfall/) ·
[07 Teststrategi och planering](courses/07-teststrategi-och-planering/)

---

## Arbetssätt

Projekten är mina egna. Jag har varit projektägare och testansvarig och arbetat tillsammans med AI-assistenten
Claude (Anthropic) som verktyg. Jag har ställt kraven, prioriterat, granskat och godkänt arbetet.
Se [README](README.md#hur-arbetet-har-gjorts).
