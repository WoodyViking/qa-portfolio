# Inlämningar: Krav och användningsfall

Lägg filerna i den här mappen och fyll i en rad per uppgift. Gå igenom [checklistan](../../README.md#innan-du-lägger-upp-en-inlämning) först.

| Uppgift | Typ | Min del | Betyg | Filer |
|---|---|---|---|---|
| Projektarbete: krav och användningsfall för webbspelet Gomoku (rapport, 18 sidor) | Grupp (grupp 2) med individuell analys | Analysen av fyra användningsfall (UC-02, UC-28, UC-29, UC-NFR-04) med testfall och aktivitetsdiagram, och mina egna reflektioner | <!-- IG/G/VG --> | [gomoku-krav-och-anvandningsfall.pdf](gomoku-krav-och-anvandningsfall.pdf) |

## Kort om uppgiften

Kunden gav en enda user story: att kunna spela ett parti Gomoku och vinna, förlora eller spela oavgjort. Gruppen
tog fram resten genom intervju och följdfrågor. Definitionen vi arbetade efter var att **ett krav är en förväntan som
är testbar**.

**Gruppens arbete (sidorna 1–4 och 17–18):** kravfångst, en begreppslista som gemensamt språk (till exempel *exakt*
fem i rad), en begreppsmodell, UML-diagram, use cases med funktionella, icke-funktionella och kompletterande krav,
GDPR som en följd av användningsfallen, och acceptanskriterier i Given/When/Then med spårbarhet krav ↔ use case ↔ test.

**Min individuella analys (sidorna 4–16):** fyra användningsfall, valda för att de visar fyra *olika svåra testproblem*:

| Use case | Testproblemet |
|---|---|
| UC-02 Gör ett drag | Ett testfall som skiljer rätt från *nästan* rätt: "sex eller fler i rad ger inte vinst" kan bara testas med ett konstruerat brädläge där ett drag fyller en lucka |
| UC-28 Rapportera ett tekniskt problem | Testförutsättningen ligger utanför appen (nätverksfel måste framkallas). Testet avslöjade också en motsägelse i ett GDPR-krav |
| UC-29 Tillfälligt blockera en spelare | Tidsgränser i tre storleksordningar: 1 minut, 24 timmar och 12 månader. Det sista går inte att testa, bara granska |
| UC-NFR-04 Begär radering av data | Ett krav som inte går att bevisa ("kan inte återskapas"). Det skrevs om till fem namngivna platser att söka i |

Varje testfall avslutas med ett aktivitetsdiagram över **testaktiviteten**, alltså hur kontrollen går till och var den kan gå fel.

## Gruppens repo

Gruppens kod och dokumentation finns på GitHub. Jag är en av de fem som bidragit.

- **Min fork:** [github.com/WoodyViking/Gomoku](https://github.com/WoodyViking/Gomoku), en säkerhetskopia med hela commit-historiken
- **Original:** [github.com/SebTns/Gomoku](https://github.com/SebTns/Gomoku), som rapporten länkar till

Bra att titta på i repot:
[begreppslistan](https://github.com/WoodyViking/Gomoku/blob/main/docs/requirements/00-begreppslista.md) ·
[begreppsmodellen](https://github.com/WoodyViking/Gomoku/blob/main/docs/requirements/05-begreppsmodell.md) ·
[use case-översikten](https://github.com/WoodyViking/Gomoku/blob/main/docs/requirements/07-use-cases-overview.md) ·
[UML-diagrammen](https://github.com/WoodyViking/Gomoku/tree/main/docs/diagrams/uml) ·
[reflektionerna](https://github.com/WoodyViking/Gomoku/blob/main/docs/requirements/10-reflektioner.md)
