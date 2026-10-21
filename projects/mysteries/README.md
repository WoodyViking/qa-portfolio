# Mysteries: QA för en spelmotor för sällskapsspel

**Mysteries** är en spelmotor för sällskapsspel på flera skärmar. Den gemensamma spelplanen visas på TV:n och
varje spelares privata information på mobilen, så att familj och vänner på olika orter kan spela tillsammans.
Motorn har tre kategorier: mordmysterium (i stil med Cluedo), escape room och vikinga-rollspel.

**Min roll:** beställare, projektägare och testansvarig.

## Det här projektet visar

- **Kravinsamling:** kraven kommer från en [kravintervju](qa/requirements/kravsammanstallning.md) där varje user
  story har en källa (ett citat från intervjun) och ett godkännande med datum. Dokumentet är på svenska.
- **Arkitekturbeslut:** [ADR-001](docs/adr-001-architecture.md) visar alternativ, avvägningar och konsekvenser.
- **Testdesign för slumpat innehåll:** varje omgång slumpas (mördare, koder, pussel). Testdesignen visar hur
  man testar att varje omgång alltid går att lösa, se [test-design/](qa/test-design/).
- **Automation:** 116 PHPUnit-tester och 29 Playwright-tester, se [qa/automation/](qa/automation/) och
  [testkörningen](evidence/phpunit-testdox.txt).

## Innehåll

| Mapp | Vad |
|---|---|
| [qa/](qa/) | Testplan, riskregister, krav, testdesign, automation och spårbarhet. Börja med [qa/README.md](qa/README.md) |
| [docs/](docs/) | Arkitekturbeslut (ADR) |
| [evidence/](evidence/) | Testkörning |
