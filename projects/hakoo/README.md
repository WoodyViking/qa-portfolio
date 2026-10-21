# hakoo.eu: QA och GDPR

**hakoo.eu** är en icke-kommersiell communitysajt för spelklanen [HAKOO] och den är i drift: inloggning via Discord,
medlemsprofiler, kopplade spelkonton (World of Tanks, Steam), en Discord-bot och 15+ webbspel
(schack, stratego, backgammon, sudoku med flera) som går att spela mot datorn eller mot andra medlemmar.
Tekniken är PHP 8.5, MariaDB och HTML/CSS/JS. Sajten ligger på ett svenskt webbhotell.

**Min roll:** projektägare och testansvarig (test manager i testplanen).

Det fanns ingen skriven specifikation, så kraven **togs fram i efterhand (reverse engineering)** ur koden och
projektets beslut. Det var det första testunderlaget, och granskningen av det var också statisk testning.

## Innehåll

| Mapp | Vad |
|---|---|
| [qa/](qa/) | All testdokumentation, ordnad efter ISTQB:s testprocess. Börja med [qa/README.md](qa/README.md) |
| [qa/requirements/](qa/requirements/) | User stories, funktionella, icke-funktionella och regulatoriska krav |
| [qa/test-design/](qa/test-design/) | Testdesigntekniker tillämpade på riktiga funktioner |
| [qa/automation/e2e/](qa/automation/e2e/) | Playwright: 193 tester i Chromium, Firefox och mobil |
| [qa/automation/integration/](qa/automation/integration/) | PHPUnit: 430 tester mot motor, API och databas |
| [qa/automation/mocks/](qa/automation/mocks/) | Mock av Discord och Wargaming API, så att testerna inte beror på externa tjänster |
| [qa/defects/](qa/defects/) | Defektlogg med 19 defekter: reproduktion, rotorsak, åtgärd och bekräftelsetest |
| [qa/test-reports/](qa/test-reports/) | Testrapporter (test completion reports) för två testcykler |
| [gdpr/](gdpr/) | Personuppgiftsregister, persondataarkitektur, integritetspolicy och genomgång av EU-regler |
| [evidence/](evidence/) | Testkörningar och skärmbilder från E2E-testerna |

## Höjdpunkter

- **Två kritiska säkerhetsbrister hittade med statisk testning** (DEF-010, DEF-011): kapning av kopplade
  spelkonton och förfalskade inloggningar. Båda har åtgärdats och bekräftats med automatiska tester.
  Ingen attack kördes mot produktion.
- **GDPR-brist hittad med test** (DEF-015): "Ladda ner mina data" saknade spel- och inställningstabeller
  (Art. 15/20). Den hittades genom att personuppgiftsregistret jämfördes med exporten.
- **Instabila tester åtgärdade** (DEF-012): E2E-tester berodde på varandra via delad testdata. Rotorsaken
  var parallella körningar mot en gemensam databas. Två körningar i rad blev gröna efter åtgärden.
- **Spårbarhet hela vägen:** varje automatiskt test har sitt TC-ID och de krav det täcker i en kommentar, och
  [spårbarhetsmatrisen](qa/traceability-matrix.md) visar täckningen.

## Köra testerna

Testerna körs mot en Docker-miljö med riktiga migreringar och fiktiv data. Produktkoden och Docker-miljön
ingår inte i det här repot, så testkoden visas här som exempel och går inte att köra härifrån.
