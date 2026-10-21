# Testdata, testmiljöer och dataskyddsförordningen (GDPR)

| | |
|---|---|
| Utbildning | IT-testspecialist (YH), Frans Schartaus Handelsinstitut |
| Termin | Termin 2, HT 2026 |
| Period | v. 48–51 |
| Omfattning | 20 YH-poäng |
| Kursplan | Kurs-PM från förra omgången (TS25); kursplanen själv saknas |
| Examination | Projektarbete (IG/G/VG), muntlig presentation (IG/G) |
| Status | ⬜ Kommande |
| Betyg | <!-- valfritt: IG / G / VG --> |

## Kursens innehåll

Hur testdata och testmiljöer hanteras korrekt och säkert, och vad dataskyddsförordningen kräver. IT-system är ofta beroende av produktionsdata, så kursen tar upp förberedelse, design, bearbetning och anonymisering av data. Den tar också upp krav på och förberedelse av testmiljöer, säkra miljöer för databaser, åtkomst, kopplingar till andra miljöer och syftet med användningen.

**Huvudsakliga moment**

- GDPR och dess påverkan på testprocessen
- Testautomatisering och GDPR-validering (Python, SQLite, Faker)
- Testmiljöer och säker datalagring (Docker, Docker Compose, Git/GitHub, CI/CD)
- Agil testplanering i Jira (SAFe)
- Helhetsperspektiv: testdata, miljö och kvalitetssäkring

## Läranderesultat (ur kursplanen, förkortat)

**Kunskaper**

- Principer för hantering av testdata och testmiljöer
- GDPR och dess påverkan på testprocessen
- Säkerhetstekniker för att skydda testdata
- Hur testautomatisering hänger ihop med testdata och testmiljöer

**Färdigheter**

- Planera, genomföra och analysera tester med olika verktyg
- Skapa och köra automatiserade tester
- Hantera och skydda testdata

**Kompetens**

- Självständigt planera och genomföra tester
- Reflektera över testdatahanteringen och föreslå förbättringar
- Skapa och underhålla en säker testmiljö för databaser

## Vad jag lärde mig

<!-- TODO: 3–5 konkreta punkter med egna ord, t.ex. "kan designa testfall med gränsvärdesanalys". -->

-

## Vad jag gjorde

<!-- TODO: Projektarbete, presentation och övningar i kursen. -->

| Uppgift | Beskrivning | Länk |
|---|---|---|
| | | |

## Bevis i portfolion

- [Fiktiv testdata](../../projects/hakoo/qa/automation/integration/HakooTestData.php): inga produktionsdata i testerna
- [Mockade externa tjänster](../../projects/hakoo/qa/automation/mocks/)
- [Personuppgiftsregister (GDPR Art. 30)](../../projects/hakoo/gdpr/personal-data-register.md) och [arkitektur för persondata](../../projects/hakoo/gdpr/personal-data-architecture.md)
- [Regulatoriska krav](../../projects/hakoo/qa/requirements/regulatory-requirements.md) och [genomgång av EU-regler](../../projects/hakoo/gdpr/eu-regulations/)
- [GDPR-defekt hittad med test](../../projects/hakoo/qa/defects/defect-log.md) (DEF-015: exporten av egna data var ofullständig)
- Testmiljö i Docker med riktiga migreringar och fiktiv data, se [testplanen](../../projects/hakoo/qa/test-plan.md)
