# Kravsammanställning: HAKOO spelmotor för sällskapsspel på flera skärmar

| | |
|---|---|
| Dokument | Testunderlag (test basis): kravsammanställning |
| Version | 0.1 (utkast), 2026-10-11 |
| Ägare | Woody_the_Viking (projektägare) |
| Källa | Kravintervju 2026-10-11 (Claude som kravanalytiker) |
| Status | Utkast, för granskning |

## 1. Mål och bakgrund
Familjen bor på olika orter och vill spela tillsammans: varje hushåll har den gemensamma spelplanen på sin egen TV, och varje spelare har sin privata information på mobil eller surfplatta. Det ska vara en spelmotor med flera kategorier (mordmysterium i stil med Cluedo, escape room, rollspel) där spelarna också kan skapa eget innehåll. Förebilden är Wii-kontrollen med egen skärm som gjorde spelet interaktivt. Kvalitet går före tid: "det är bättre att det blir riktigt bra från början".

## 2. User stories
US-01: Som spelare i en utspridd familj vill jag se den gemensamma spelplanen på min egen skärm och min privata information på min egen enhet så att vi kan spela tillsammans fast vi bor på olika orter.
  Källa: "min familj bor på olika orter så att det vore kul om deras och min TV visar spelplanen"

US-02: Som den som startar ett spel vill jag på ett enkelt och grafiskt sätt ställa in spelplaner, spelarenheter, spelform, lag och längd så att spelet passar just vårt sällskap.
  Källa: "när man sätter igång spelet så får man ange hur många spelplaner … gärna på ett enkelt och grafiskt sätt"

US-03: Som spelare vill jag hålla mina ledtrådar och min information privat så att jag kan tävla eller samarbeta på riktigt.
  Källa: "vi har alla olika ledtrådar … vill man ju oxå hålla sin information privat"

US-04: Som spelgrupp vill vi kunna pausa och fortsätta ett annat tillfälle så att en omgång inte måste spelas klart på en gång.
  Källa: "bra om man kan pausa och återuppta spelet vid ett senare tillfälle"

US-05: Som spelare vill jag kunna lämna, eller tappa uppkopplingen, utan att spelet förstörs för de andra.
  Källa: "en spelar vill/behöver avsluta så ska den kunna välja att lämna och de andra kan fortsätta"

US-06: Som spelledare vill jag kunna välja att bara leda eller också spela, godkänna vem som ansluter och se hur spelarnas uppkoppling mår, så att rätt personer är med och spelet flyter.
  Källa: "den som startat spelet får validera att det är rätt spelare som anslutit"

US-07: Som spelare vill jag logga in med ett konto jag redan har och dela så lite uppgifter som möjligt så att det är smidigt och säkert.
  Källa: "jag gillar ide´n av att redan använda befintliga konton … vårt mål är att spara så lite info som möjligt"

US-08: Som förälder vill jag kunna låta mitt barn under 13 vara med via familjedelning så att hela familjen kan spela (om det går att lösa tekniskt).
  Källa: "om inte en verifierad målsman kan dela via familjedelning … går inte det så får vi hoppa över det"

US-09: Som spelare vill jag välja språk för mig själv, och för spelplanen, så att vänner i olika länder kan spela tillsammans.
  Källa: "jag har vänner i olika länder"

US-10: Som spelare på annan ort vill jag prata med de andra via Discord eller en annan app jag väljer och se vem som pratar, så att det känns som att vi sitter tillsammans.
  Källa: "discord bot som skulle kunna skapa ett tillfälligt rum … se vem som pratar i en overlay"

US-11: Som spelare med en funktionsnedsättning vill jag kunna använda hjälpmedel och anpassa visningen så att jag kan vara med på lika villkor.
  Källa: "vi vill ju vara 'all inclusive'"

US-12: Som skapare vill jag kunna skapa egna mysterier och rum med slumpade delar, och senare sälja dem efter granskning, så att andra kan spela dem om och om igen.
  Källa: "vore kul om kunder kunde skapa egna mysterier/rum … efter en granskning kunna sälja det"

US-13: Som speltillverkare vill jag att testare får prova spelen gratis på en dold sida på hakoo.eu och granska nytt innehåll, så att kvalitet och regler håller före lansering.
  Källa: "lägga det så att man kommer bara dit med direktadress och att man måste vara inloggad först med rollen testare eller admin"

US-14: Som spelare vill jag kunna radera mitt konto när jag vill, med möjlighet att ångra mig, så att jag har kontroll över mina uppgifter.
  Källa: "14 dagars ångerrätt … under dessa dagar så kan personen ångra sitt borttagande"

US-15: Som testare vill jag spela Kunglig Vikingludo med spelplanen på TV:n och tärningen och mina numrerade pjäser på mobilen, så att vi kan prova spelmotorn med ett spel som alla redan kan.
  Källa: Woody 2026-10-15: "jag vill kunna testa mysteries spel motor och tänkte att vi kan använda oss av ludo … vi behöver ge pjäserna ett nummer så att vi kan slå tärning med mobilen och sedan välja vilken pjäs som vi ska flytta"

US-16: Som testare vill jag spela ett färdigt mordmysterium där mördaren och vapnet slumpas varje gång och ledtrådarna alltid räcker för att lösa det, så att vi kan prova mordmysteriekategorin med riktigt innehåll. *Godkänd av Woody 2026-10-16.* Första mysteriet: ”Den sista skålen” (nyårsafton 1929, ett insnöat fjällhotell, 2–6 spelare, 5 misstänkta × 3 vapen, 60 eller 90 min).
  Källa: Woody 2026-10-16: "jag starta en Xhigh tråd och börjar med ett mysterie." Godkänt: "jag har läst och det är ett bra första fall", "japp vi kan köra CPL på den" (2026-10-16).

US-17: Som testare vill jag spela ”Den sista skålen” med spelplanen på TV:n och mina ledtrådar på mobilen: leta på platserna, behålla eller visa det jag hittar och anklaga mördaren, så att vi kan prova mordmysteriet på riktigt. *Godkänt av Woody 2026-10-16 (”kör CPL”). Woody 2026-10-16: ”är det möjligt att låta den som startar spelet välja dessa i en lobby när spelarna är i den? så att alla är med på vad som gäller för spelet.” Därför är de fem valen inställningar i lobbyn (K-65..K-70).*
  Källa: Woody 2026-10-16: "så då skulle vi kunna bygga på spelmotorn för mysteriet. det först är nu byggt med bilder och allt."

US-18: Som testare vill jag spela ett färdigt escape room där koder, vikter och ordningar slumpas varje gång och bitarna alltid räcker för att ta sig ut, så att vi kan prova escape room-kategorin med riktigt innehåll. Första rummet: ”Runsmedens lås” (år 970, en runsmeds smedja, 2–6 spelare, 30 och 60 min nu, 90 min senare). *Godkänt av Woody 2026-10-17 (”Ja, bygg”).*
  Källa: Woody 2026-10-16: "ska vi ta och få fram ett escaperoom"; temat valt samma kväll, bilderna gjorda i Midjourney 2026-10-16..17.

US-19: Som testare vill jag spela ”Runsmedens lås” tillsammans med de andra, med rummet på TV:n och mina egna bitar och föremål på mobilen, så att vi måste prata med varandra för att ta oss ut. *Godkänt av Woody 2026-10-17 (”Ja, bygg” med förvalen: mjuk klocka, den som lämnar lämnar sina bitar till de andra).*

US-20: Som testare vill jag spela ett vikinga-rollspel i stil med D&D, med berättelsen och striderna på TV:n och min egen hjälte på mobilen, så att vi kan prova rollspelskategorin. *Godkänt av Woody 2026-10-17 (”ja”).*
  Källa: Woody 2026-10-17: ”sedan har jag funderingar på att skapa ett rpg typ D&D med vikinga teama”; A-01.

US-21: Som spelare vill jag skapa en hjälte genom att välja klass, folk och namn, och ta med hjälten till nästa äventyr där den har blivit starkare, så att det känns som min egen hjälte. *Godkänt av Woody 2026-10-17 (”ja”).*
  Källa: Woody 2026-10-17: ”vi kommer ju att behöva lite olika klasser med förklaringar om hur de fungerar och styrkor och svagheter”, och listan med klasser och folk samma dag.

US-22: Som spelledare vill jag kunna ta över berättelsen från datorn mitt i ett äventyr, och lämna tillbaka den, så att vi kan spela friare när någon vill leda. *Godkänt av Woody 2026-10-17 (”ja”); både datorn och en människa kan vara spelledare (Woody: ”Båda”).*
  Källa: Woody 2026-10-17 på frågan om vem som är spelledare: ”Båda”.

US-23: Som testare vill jag spela ett första färdigt äventyr där delar slumpas varje gång och där alla klasser får visa vad de kan i en strid, en fälla och ett samtal, så att vi kan prova rollspelet med riktigt innehåll. Första äventyret: ”Den stulna hammaren”, fritt efter Trymskvädet (60 eller 90 min, 2–6 spelare, 4–6 hjältar). *Godkänt av Woody 2026-10-17 (”ja”).*
  Källa: Förval godkänt av Woody 2026-10-17 med kraven; spelledarhäftet godkänt samma dag (”ser bra ut”), bilderna gjorda i Midjourney 2026-10-17..18.

US-24: Som testare vill jag lösa små pussel med fingret på min egen telefon i ett escape room (skjuta brickor, vrida rör och ringar, vända ventiler, knacka en takt), där vi ändå måste prata och hjälpas åt, så att rummet blir mer interaktivt. *Godkänt av Woody 2026-10-19 (”ja vi kör på”).*
  Källa: Woody 2026-10-19: ”jag skulle vilja göra ett mer interaktivt escaperoom där några av ledtrådarna är små pussel som man måste lösa på sin enhet”.

US-25: Som testare vill jag spela ”Fyren i stormen” (år 1923, en släckt fyr i stormen, 2–6 spelare, 30 och 60 min nu, 90 min senare), så att vi kan prova pusslen i ett riktigt rum. *Godkänt av Woody 2026-10-19 (”ja vi kör på”).*
  Källa: Woody valde temat 2026-10-19 (beslutskortet); spelledarhäftet och bildpromptarna i projektmappen `mysteries/`.

US-26: Som värd i testfasen vill jag kunna spela flera spelare själv i egna flikar och bjuda in någon med en länk, så att jag kan prova spelen live på hakoo.eu ensam eller tillsammans med en vän. *Önskat av Woody 2026-10-19; godkänns med CPL.*
  Källa: Woody 2026-10-19: ”kan du göra ett testläge så jag kan testa live och skapa en länk att bjuda in någon att testa med mig”.

## 3. Kompletterande krav (hur)
**Skärmar och enheter**
K-01 En spelomgång kan ha flera spelplaner (gemensamma skärmar) samtidigt på olika orter. (US-01) | Källa: "flera skärmar ska kunna visa den gemensamma spelplanen"
K-02 Varje spelare har en egen spelarenhet: mobil, surfplatta eller datorfönster. (US-01) | Källa: "En mobil skulle ju kunna vara en tablet oxå"
K-03 En spelare vid dator kan ha spelplanen på en skärm och sin spelarvy på en annan. (US-01) | Källa: "en skärm vara spelplanen och en annan vara den med spelarens information"
K-04 Spelplanen kan visas via dator (projektor/castning), Android TV, Apple TV och smart-TV:ns webbläsare. (US-01) | Källa: "bra om vi kan ha en lösning för alla de alternativen"
K-05 Spelarenheten kan vara en interaktiv kontroll som ändrar sig efter spelet och kan använda rörelsesensorer och gyro (t.ex. tärningsskål). (US-01) | Källa: Wii-kontrollen; "läderskål … rörelsesensorer och gyro"

**Starta och sätta upp ett spel**
K-06 Vid start anges antal spelplaner och antal spelarenheter, och det visas grafiskt. (US-02) | Källa: se US-02
K-07 Spelform väljs före start: alla mot alla, eller lag som den som startar sätter ihop fritt (inte nödvändigtvis per hushåll). (US-02) | Källa: "man skulle kunna välja vilka som ska spela i lag"
K-08 Längd väljs bland 30/60/90/120/150/180 min. Den styr hur stort och svårt spelet är och är ingen nedräkning. (US-02) | Källa: "speltiden ska bara vara en riktning på hur komplext … svårt"
K-09 Den som startar väljer att vara ren spelledare eller spelledare som spelar med. (US-06) | Källa: "ett val den personen kan göra när denne drar igång det"
K-10 Den som startar får vägledning om hur man startar, markerar deltagare, sätter ihop lag och fördelar platser, även för spelare som behöver assistans. (US-02, US-06) | Källa: "bra med information till den som ska starta ett spel om hur man gör"

**Anslutning och privat information**
K-11 Anslutning kan ske med QR-kod, länk eller app. (US-01) | Källa: "bra att ha alla 3 möjligheterna"
K-12 En ansluten spelare kommer in först när den som startat spelet har godkänt den. (US-06) | Källa: se US-06
K-13 En spelares privata information visas bara på spelarens egna enheter. (US-03)
K-14 Ingen chatt mellan spelare. Spelaren skickar bara spelinmatning (t.ex. en kod) till spelet. (US-03) | Källa: "det är att kommunicera med spelet och inte en annan deltagare"

**Paus, lämna och avbrott**
K-15 Ett spel kan pausas och återupptas senare. (US-04)
K-16 Den som lämnar väljer om ens ledtrådar delas ut till alla eller går tillbaka till spelplanen för att hittas igen. (US-05) | Källa: "det kan spelaren som lämnar bestämma"
K-17 Tappar en spelare uppkopplingen (utan att välja det) pausas spelet och väntar den tid spelledaren ställt in (1–10 min, förval 5). Därefter beslutar en ren spelledare, annars röstar de kvarvarande om paus eller att fortsätta. (US-05) | Källa: svar i granskningen, punkt 1–2
K-18 Fortsätter spelet antingen tar en AI-/datorspelare över platsen (den ursprungliga spelaren kan återansluta och läsa vad AI:n hittat), eller så tas spelaren bort och det röstas om ledtrådarna delas ut eller går tillbaka. (US-05)
K-19 Spelledaren kan avsluta en spelares session, t.ex. om spelaren hört av sig på annat sätt, och kan låta en annan spelare ta över platsen. (US-05, US-06)
K-20 En omröstning avgörs med minst hälften av rösterna. Vid lika röster avgör spelledaren eller den som startade spelet. (US-05)
K-21 Spelet mäter uppkopplingen (ping m.m.). Spelledaren ser en inforuta över spelare med sämre uppkoppling och kan visa den på spelplanen. (US-06) | Källa: "en lite inforuta som spelledaren ser och kan visa upp på spelplanen"

**Konton och data**
K-22 Inloggning med Discord, sedan Steam, sedan Apple, Google och Microsoft (i den prioritetsordningen). (US-07)
K-23 Konton från tjänster där 2FA inte kan kontrolleras eller krävas kan inte användas. (US-07) | Källa: "kan vi inte kolla/kräva det så ska inte ett sådant konto kunna användas"
K-24 Det framgår tydligt vilka uppgifter som är obligatoriska och vilka som är frivilliga. (US-07)
K-25 Kontoradering har 14 dagars ångerfrist. Därefter raderas all data, utom transaktioner som sparas anonymiserat så länge lagen kräver. (US-14)
K-26 Raderar en spelare sitt konto mitt i ett spel hanteras det som när någon lämnar eller tappar uppkopplingen (K-17, K-18). (US-14, US-05)
K-27 Den som raderar sitt konto bekräftar att skapat innehåll tillfaller spelet och att eventuell försäljning tillfaller speltillverkaren. (US-14, US-12)

**Språk**
K-28 Språk: engelska (förval), svenska, danska och nederländska. Saknas en text visas den engelska. (US-09)
K-29 Varje spelare väljer sitt språk, och spelplanen har ett eget språkval. (US-09)
K-71 Spelplanens adress kan välja språk med två bokstäver: EN engelska, SE svenska, DK danska, NL nederländska (även sv och da går). Då visar just den skärmen det språket i stället för värdens; okända bokstäver ignoreras. Tyska (GE) läggs till när tyska blir ett språk. (US-09, K-28, K-29) | Källa: Woody 2026-10-16 ”kan vi lägga till i board dressen 2 bokstäver som bestämmer språk”

**Kommunikation**
K-30 Vid start kan Discord-boten skapa ett tillfälligt röstrum som finns medan spelet pågår. (US-10)
K-31 En overlay visar vem som pratar. (US-10)
K-32 Varje spelare kan välja en annan kommunikationsapp (t.ex. WhatsApp, Teams, TeamSpeak). (US-10)

**Tillgänglighet**
K-33 Text kan läsas upp, t.ex. i headset. (US-11)
K-34 Man kan zooma och ställa in färg på text och bakgrund samt typsnitt. (US-11)
K-35 Spelet fungerar med enhetens inbyggda tillgänglighetsinställningar och hjälpmedel. (US-11)

**Innehåll och granskning**
K-36 Mysterier och rum har delar som slumpas, så att de kan spelas om. (US-12)
K-37 Skaparverktyget finns för testarna redan i testfasen. (US-12)
K-38 Testare granskar nytt innehåll före publicering. Följande stoppas: lagbrott, grooming, sexuellt innehåll som rör eller riktar sig till minderåriga, hat mot specifika personer, ras eller religion, samt verkliga religioner och deras ledare eller figurer. Undantag: nordisk mytologi (asagudar, jättar och väsen) får förekomma som saga i rollspel, så länge den inte hånar dagens asatro. En präst eller cleric utan koppling till verklig religion är tillåten i rollspel. (US-12, US-13; undantaget godkänt av Woody 2026-10-17)
K-39 Innehåll med grövre våld kräver ett verifierat konto där spelaren är 18 år eller äldre. (US-12)
K-40 Kända varumärken får förekomma bara med uttryckligt godkännande från speltillverkaren. (US-12)
K-41 Testfasen ligger på hakoo.eu bakom en adress som inte länkas ut och kräver inloggning med rollen testare eller admin. (US-13)

**Testspelet Kunglig Vikingludo** (beslut Woody 2026-10-15, skissen: Kunglig Vikingludo, regler: danskt globludo med kung för glob och fjäder för stjärna)
K-42 Ludo är en spelkategori för 2–6 spelare. 2–4 spelare spelar på den kungliga planen (15 × 15 rutor, 56 rutor runt, 5 rutor hem), 5–6 spelare på planen med sex armar (84 rutor runt, 5 rutor hem). Varje arm har 14 rutor: 13 längs armen och hörnrutan vid mitten där två armar möts, samma plan som Kunglig Vikingludo på hakoo.eu. 2 spelare sitter mitt emot varandra. (US-15) | Källa: "så 2-4 spelare kör på kunglig ludo och sedan kan vi göra en för 5-6"; 14 rutor per arm: "vi behöver uppgradera mysteries så att det fungerar som hakoo" (Woody 2026-10-20, tidigare 13 rutor per arm, 52 och 78 runt)
K-43 Specialrutorna ligger på samma ställen för varje spelare, räknat från startrutan: kung på startrutan och 9 rutor efter, fjäder 5 (hörnrutan) och 12 rutor efter, som på hakoo.eu. Ett parti som pågick med 13 rutor per arm flyttas över till den nya planen med pjäserna på motsvarande rutor. (US-15) | Källa: Woodys inringade bild 2026-10-15; ändrat till hakoos placering 2026-10-20 (tidigare kung 0 och 7, fjäder 4 och 10)
K-44 Pjäserna har nummer 1–4 på spelplanen och i boet. Spelaren väljer pjäs med knappar 1–4 på sin enhet; bara pjäser som får flytta går att välja, och varje knapp säger vad draget gör. Har bara en pjäs (eller flera likadana) ett möjligt drag flyttas den direkt. (US-15, K-05)
K-45 I lobbyn väljer varje spelare sin färg bland 8 (Blodröd, Fjordblå, Mossgrön, Bärnsten, HAKOO-lila, Isturkos, Eldorange, Kolsvart). En färg kan bara ha en spelare; den som inte valt får en ledig färg vid start. (US-15) | Källa: "de spelare som ansluter kan få välja vilken färg de vill ha"
K-46 Varje spelare väljer hur den slår: trycka på tärningen, eller skaka telefonen och hälla ut tärningen (rörelsesensorn). Skålen går alltid att trycka på, så spelet fungerar utan sensor. (US-15, K-05) | Källa: "kan välja om man bara vill trycka på tärningen för att slå eller om vill skaka och hälla ut"
K-47 Reglerna: ut ur boet bara på en sexa, med tre försök när man inte har några pjäser ute. En sexa ger ett nytt slag. Landar man på en motståndare åker den hem till sitt bo. **Kungen** är fredad: den som landar på en motståndare som står på en kung åker själv hem (utom den som kommer ut ur boet på sin egen startruta, den knuffar). **Fjädern**: landar man på en fjäder hoppar man vidare till nästa fjäder. **Tärningen** har 1, 2, kung, 4, fjäder och 6: kung flyttar en pjäs till närmaste kung framåt och fjäder till nästa fjäder (ingen av dem tar ut en pjäs ur boet). Egna pjäser får stå på samma ruta. Mål kräver exakt slag. Först med alla fyra i mål vinner. (US-15) | Källa: reglerna för globludo som Woody skickade 2026-10-15; förvalen "kung tar inte ut ur boet" och "exakt i mål" godkända samma dag
K-48 Tärningen slås på servern, aldrig i webbläsaren, och servern kontrollerar varje drag. (US-15, NFR-05)
K-49 Lämnar en spelare ett pågående Ludo-spel väljer den som startade spelet om datorn eller en ny spelare tar över platsen, med färgen och pjäserna där de står. Spelet väntar när det blir den tomma platsens tur. En ny spelare går med med koden och släpps in som vanligt, och får sedan platsen; den som redan har en plats kan inte få en till. Är den som startade spelet själv borta tar datorn över. Kommer den som lämnade tillbaka medan datorn spelar tar hen tillbaka platsen. (US-15, US-05, K-18, K-19) | Källa: "Om en spelare lämnar så får bli den som startat spelet bestämma om dator eller ny spelar som ska ta över." (Woody 2026-10-15)

**Mordmysteriet ”Den sista skålen”** (godkänt av Woody 2026-10-16; hur det spelas på skärmarna: K-55..K-64)
K-50 Ett mysterium är data: en mapp med personer, platser, vapen, vilka delar som slumpas, ledtrådarna (en text per variant när ledtråden beror på det slumpade) och slutsatsreglerna, plus en textfil per språk. Ett nytt mysterium kräver ingen kodändring. (US-16, US-12, NFR-10)
K-51 Ett mysterium kontrolleras maskinellt innan det får spelas: för varje kombination av slumpade delar och varje längd ska ledtrådarna leda till exakt en lösning, den slumpade mördaren med det slumpade vapnet. Hittar kontrollen ingen eller fler än en lösning, eller ett faktum, en plats eller en text som inte stämmer, stoppas mysteriet och kontrollen säger vad som är fel. (US-16, US-12, K-36, K-38)
K-52 Längden styr mysteriets storlek (K-08): mysteriet anger vilka längder det finns i och hur många ledtrådar som får saknas i varje längd (aldrig hittade, eller borta med en spelare som lämnade, K-16) utan att det slutar gå att lösa. Ett längre spel har fler ledtrådar och färre genvägar. (US-16, K-08, K-16)
K-53 En ledtråd som skiljer sig mellan varianterna har samma titel, plats och källa i alla varianter; bara texten skiljer sig, och det som skickas till en enhet säger aldrig vilken variant som spelas. (US-16, K-13, NFR-05)
K-54 Mysteriernas filer (alla varianters ledtrådar och lösningarna) skickas aldrig direkt till en webbläsare, inte ens till ägaren. Bara servern läser dem. (US-16, NFR-05)

**Mordmysteriet på skärmarna** (godkänt av Woody 2026-10-16; K-58..K-60 beskriver förvalen, K-66..K-70 det värden kan välja i stället)
K-55 Mordmysterium är en spelkategori. Värden väljer mysterium och en av mysteriets längder. Antalet spelare ska ligga inom mysteriets gränser. Ett mysterium som kontrollen inte godkänner (K-51) går inte att starta. (US-17, K-08, K-51)
K-56 Vid start slumpar servern varianten (K-36) och blandar ordningen på ledtrådarna på varje plats. Varianten och ordningen finns bara på servern. (US-17, K-53, K-54, NFR-05)
K-57 Alla spelare har fallakten från start: inledning, regler, karta, personer, vapen och de offentliga ledtrådarna. Spelplanen visar omslaget och inledningen, sedan planen. (US-17, US-01)
K-58 Leta: den som har turen väljer en plats med ledtrådar kvar och får platsens nästa ledtråd privat. Spelplanen visar vem som letade var, vems tur det är och hur många ledtrådar som finns kvar på varje plats. Har den som har turen lämnat går turen vidare. (US-17, K-13)
K-59 Visa: en spelare kan när som helst visa en av sina ledtrådar för alla. Den syns på spelplanerna och i allas fallakt. (US-17, US-03)
K-60 Anklaga: på sin tur väljer spelaren en misstänkt och ett vapen. Rätt: spelaren vinner och spelet slutar. Fel: bara spelaren får veta det, och hen kan inte anklaga igen men letar och visar som vanligt. Har alla anklagat fel slutar spelet utan vinnare. (US-17)
K-61 När spelet slutar visar alla skärmar lösningen och efterordet. Värden kan avsluta spelet och visa lösningen. (US-17, US-06)
K-62 Anteckningsblock: varje spelare kan stryka misstänkta och vapen. Det är privat och sparas med spelet. (US-17, K-13, K-15)
K-63 Ledtrådar, personer, platser och vapen kan ha en bild. En bild är samma i alla varianter (K-53). Saknar en ledtråd bild visas porträttet av den den kommer från. (US-17, US-16)
K-64 Varje text en spelare ser i mysteriet finns på engelska, svenska, danska och nederländska; spelplanen visar värdens språk och telefonen spelarens eget. (US-17, K-28, K-29)
K-65 I lobbyn väljer värden fem regler: spelform (mot varandra eller tillsammans), turer (turas om eller fritt), fel anklagelse (ute eller straff), fråga (ja eller nej) och gå (fritt eller genom dörrar). Förvalet är mot varandra, turas om, ute, ja och fritt. Alla telefoner och spelplanen visar reglerna i ord medan valen görs, och fallakten visar dem under spelet. Bara värden kan ändra dem, och bara innan spelet startar. (US-17, K-09) | Källa: Woody 2026-10-16, ”så att alla är med på vad som gäller för spelet”
K-66 Tillsammans: allt som hittas visas direkt för alla. Den som har turen föreslår en misstänkt och ett vapen, och alla vid bordet röstar. Fler ja än nej: anklagelsen görs för laget; lika: värdens röst avgör (K-20), och spelar inte värden går förslaget igenom. Rätt: laget vinner. Fel: med ute har laget förlorat, med straff går lagets 2 nästa turer förlorade (fritt: ingen får leta eller anklaga på 2 minuter). Går förslaget inte igenom behåller förslagsställaren turen. Den som lämnar under en omröstning räknas inte. Man kan inte fråga varandra (K-69). (US-17, K-20)
K-67 Fritt: inga turer. Alla letar, frågar och anklagar när de vill; en omröstning i taget och en egen fråga i taget. Spelplanen visar ”Alla letar fritt” i stället för vems tur det är. (US-17)
K-68 Straff: den som anklagar fel får veta det (bara hen) och står över sina 2 nästa turer, sedan får hen anklaga igen; utan turer väntar hen 2 minuter innan hen får leta eller anklaga. Spelplanen visar ”står över” eller ”väntar” vid namnet. Med straff slutar spelet när någon anklagar rätt eller värden avslutar. (US-17, K-60)
K-69 Fråga: i stället för att leta frågar spelaren en annan spelare om en plats. Den som får frågan måste visa en av sina ledtrådar från den platsen, bara för den som frågade, och får välja vilken; har hen ingen svarar hen det. Spelplanen visar vem som frågade vem om vad och om svaret var en ledtråd, aldrig vilken. Med turer väntar turen på svaret och går sedan vidare. Lämnar den som fick frågan tas frågan tillbaka och den som frågade behåller turen. En ledtråd man fått visad kan man inte visa för alla. (US-17, K-13)
K-70 Dörrar: alla börjar i mysteriets första plats (lobbyn i ”Den sista skålen”) och kan bara leta där de står eller på en plats med dörr dit (mysteriets länkar). Finns inget kvar inom räckhåll får man leta var som helst. (US-17, K-50)

**Escape room ”Runsmedens lås”** (godkänt av Woody 2026-10-17, ”Ja, bygg”; innehållet: `content/runesmith/`, formatet: `docs/escape-format.md`)
K-72 Ett escape room är data: rum, platser att undersöka, föremål, lås med svarstyp (kod, val av föremål, ordning, plats, bitar på rätt plats, samtidiga tryck), bitar, vad som slumpas, tips och slutscen, plus en textfil per språk. Ett nytt rum kräver ingen kodändring, så länge det använder de låstyper som finns. (US-18, US-12, K-50, NFR-10)
K-73 Vid start slumpar servern varje lås (koder, vikter, ordningar, gömställen, kursen) och delar ut bitarna jämnt mellan spelarna. Svaren och slumpen finns bara på servern; ett svar skickas aldrig till en webbläsare innan låset är öppnat. (US-18, K-36, K-54, NFR-05)
K-74 Ett rum kontrolleras maskinellt innan det får spelas: för varje längd och 2–6 spelare, över minst 10 000 dragningar, ska varje lås ha exakt ett svar, svaret ska gå att räkna ut av bitarna, varje gåta med mer än en bit ska ha bitar hos minst två spelare, alla lås ska gå att nå och rummet gå att spela igenom, och alla texter och bilder ska finnas på fyra språk. Annars går rummet inte att starta, och kontrollen säger vad som är fel. (US-18, K-51)
K-75 Längden styr storleken (K-08): Runsmedens lås har 30 min (Smedjan, 3 gåtor) och 60 min (tre rum, 8 gåtor). 90 min (Vävstugan, 11 gåtor) läggs till senare. (US-18, K-08)
K-76 Runor, siffror, prickar och kursen på sjökortet ritas av appen ovanpå bilderna (runor med typsnittet Noto Sans Runic, OFL), eftersom de skiljer sig mellan omgångarna. Bilderna har ingen text. (US-18, K-63)

**Escape room på skärmarna** (godkänt av Woody 2026-10-17 med förvalen: bara mjuk klocka, den som lämnar lämnar bitarna till de andra)
K-77 Escape room är en spelkategori. Värden väljer rum och längd i lobbyn; antalet spelare ska ligga inom rummets gränser, och ett rum som kontrollen inte godkänner (K-74) går inte att starta. (US-19, K-55)
K-78 Alla spelar i samma lag och det finns inga turer: vem som helst kan undersöka, ge, använda och skicka svar när som helst. (US-19)
K-79 Bitar syns bara på den egna telefonen och kan inte visas för de andra; spelarna berättar för varandra. Bara sjökortets bitar läggs på TV:n. (US-19, K-13)
K-80 Föremål hamnar hos den som hittar dem och kan ges till en annan spelare. Ett lås som kräver föremål (kistan) öppnas bara av den som har alla. (US-19)
K-81 Svar skickas från telefonen och kontrolleras på servern. Rätt: låset öppnas på alla skärmar. Fel: TV:n visar att låset höll, och två minuter läggs till (fel plats i verktygsväggen: en minut; en sjökortsbit på fel plats glider bara tillbaka). Servern tar emot högst ett svar per lås var femte sekund. Klockan: TV:n visar elden som falnar under den valda längden. Den är mjuk: spelet fortsätter när elden slocknat, det ger bara färre stjärnor. En hård klocka (rummet vinner) är inte byggd. (US-19, K-08, K-14, NFR-05)
K-82 Tips: varje lås har tre tips, det sista är svaret (för sjökortet lägger det sista tipset de sista bitarna). Vem som helst kan be om nästa tips; det syns på TV:n och alla telefoner. (US-19)
K-83 När sista låset öppnas visar alla skärmar slutscenen, tiden, antal fel svar, antal tips och stjärnorna: ★★★ inom tiden och högst två tips, ★★ ett av dem, ★ annars. Värden kan avsluta spelet; då visas tiden men inga stjärnor. (US-19)
K-84 Lämnar en spelare går hens bitar och föremål till den kvarvarande spelare som har minst, och TV:n säger vem som fick vad. (US-19, US-05; avviker från K-16, godkänt av Woody 2026-10-17)
K-85 Bälgen pumpas genom att skaka telefonen eller trycka på den, och elden tar sig bara när två olika spelare turas om. Klockan börjar när ässjan tänds. (US-19, K-05)
K-86 I lobbyn väljer värden regeln Tips (på/av). Förval: på. Alla telefoner och TV:n visar reglerna i ord innan start. (US-19, K-65)
K-87 Varje text en spelare ser finns på engelska, svenska, danska och nederländska; spelplanen visar värdens språk (eller adressens, K-71) och telefonen spelarens eget. (US-19, K-28, K-29, K-64)

**Vikinga-rollspelet: hjältarna och reglerna** (godkänt av Woody 2026-10-17, ”ja”; klasserna och folken: `docs/rpg/vikinga-rpg-klasser.md`)
K-88 Klasser och folk är data: egenskaper, Liv, Försvar, resurs, förmågor, nivå 1–5 och texter på fyra språk. En ny klass eller ett nytt folk kräver ingen kodändring. En kontroll stoppar en klass vars egenskaper inte summerar till +5, vars Försvar inte följer 10 + Smidighet + rustning, eller som saknar en text. (US-21, NFR-10)
K-89 Allt osäkert avgörs med T20 + egenskap mot svårighet 8, 12, 16 eller 20. 20 på tärningen lyckas alltid, 1 misslyckas alltid men stoppar aldrig äventyret. Fördel och nackdel slår två T20 och tar den högsta eller lägsta. Servern slår alla tärningar; spelaren skakar mobilen eller trycker för att slå, och resultatet kommer från servern. (US-20, K-05, NFR-05)
K-90 Ett party har 4–6 hjältar och 2–6 spelare. De 4 första hjältarna är av olika klasser; hjälte 5 och 6 får vara av samma klass som någon annan. En spelare styr högst 2 hjältar och byter mellan dem med en flik på mobilen; det är en spelare med två hjältar, inte hotseat. Saknar partyt både skydd och stöd visar lobbyn ett tips, men det går att starta. (US-20, US-21, K-12)
K-91 En hjälte som når 0 Liv är utslagen, aldrig död. En vän kan hjälpa upp den, och efter striden vaknar den med 1 Liv. Besegrade fiender flyr, ger upp eller faller omkull. Inget blod i text eller bild. (US-20, K-39)
K-92 Hjälten sparas på spelarens konto mellan äventyren: namn, klass, folk, nivå, utrustning och val vid nivåerna, inget annat. Den går upp en nivå efter varje äventyr som spelas klart, upp till nivå 5. Hjälten raderas med kontot (K-25) och följer med i ”Ladda ner mina data”. (US-21, NFR-07)
K-93 Hjältens namn väljs bland nordiska namnförslag eller skrivs själv (högst 20 tecken). Spelledaren eller värden kan byta ett olämpligt namn. (US-21, K-38)

**Vikinga-rollspelet: äventyret** (första äventyret: `docs/rpg/den-stulna-hammaren-spelledarhafte.md`)
K-94 Ett äventyr är data: scener (berättelse, strid, utforska, prata), val som leder vidare, fiender, fällor, skatter, vad som slumpas, slut och en textfil per språk. Ett nytt äventyr kräver ingen kodändring. (US-23, US-12, K-36, K-50, NFR-10)
K-95 Ett äventyr kontrolleras maskinellt innan det får spelas: varje scen går att nå, varje väg leder till ett slut, ett misslyckat slag leder alltid vidare, alla texter finns på fyra språk, och varje strid vinns av partyt i 70–95 % av minst 10 000 simulerade strider för varje partystorlek 4–6 med slumpade klasser och folk. Annars går äventyret inte att starta, och kontrollen säger vad som är fel. (US-23, K-51, K-74)
K-96 Längden styr äventyrets storlek (K-08): det första äventyret har 60 och 90 minuter. (US-23, K-08)
K-97 När datorn är spelledare visar TV:n scenen med bild och text, som kan läsas upp (K-33), och vilka val som finns. Varje spelare väljer på mobilen. Val som gäller hela partyt avgörs med omröstning (K-20), där varje hjälte har en röst. (US-20, K-20, K-33)
K-98 Strid går i turordning som slås fram med Smidighet. På sin tur väljer hjälten på mobilen: anfalla, använda en förmåga, flytta mellan nära och på håll, hjälpa en vän eller använda ett föremål. Bara det som går att göra just då går att välja. Fiendernas drag görs av servern. TV:n visar alla hjältar och fiender, deras Liv och vems tur det är. (US-20, K-44)
K-99 Hjältens blad (Liv, resurs, förmågor, föremål) syns på mobilen. Det en enda hjälte får veta, till exempel völvans spådom eller vad stråtrövaren ser i mörkret, syns bara på den mobilen; spelaren berättar själv för de andra. (US-20, K-13, K-14)
K-100 Mobilen används som kontroll (K-05): skaka för att slå och för stridsvrål, luta för att lyfta skölden, rita runan med fingret. Varje rörelse har också en knapp som gör samma sak. (US-20, K-05, K-35)

**Vikinga-rollspelet: människan som spelledare**
K-101 En spelledare kan ta över berättelsen när som helst och lämna tillbaka den. Hen ser då hela äventyret, vad som har slumpats och fiendernas värden på sin enhet. Hen kan välja nästa scen, ändra en svårighet, lägga till eller ta bort fiender, ge fördel eller nackdel, ge ett föremål, slå dolt för fiender och skriva en kort egen text på TV:n. (US-22, K-09)
K-102 Bara en ren spelledare (K-09) kan ta över. Det spelledaren ser syns aldrig på spelarnas enheter eller på TV:n. (US-22, K-13, NFR-05)
K-103 Spelledarens egna texter visas som de skrevs och översätts inte, eftersom de skrivs under spelet. Allt annat följer K-28. Egna texter är spelinmatning och ingen chatt (K-14). (US-22, K-14, K-28)
K-104 Lämnar spelledaren eller tappar uppkopplingen tar datorn tillbaka berättelsen där den är, utan att spelet pausas. (US-22, K-17)

**Vikinga-rollspelet på skärmarna** (godkänt med förvalen: T20, nära/på håll i stället för rutnät, hjälten sparas och går upp en nivå per äventyr till nivå 5, bara en ren spelledare kan ta över)
K-105 Rollspel är en spelkategori. Värden väljer äventyr och längd i lobbyn, varje spelare väljer en ny eller sparad hjälte (eller två), och lobbyn visar partyt och reglerna i ord innan start. (US-20, K-55, K-65)
K-106 Ett äventyr kan pausas och återupptas (K-15), även mitt i en scen eller en strid, och fortsätter exakt där det var. (US-20, US-04, K-15)
K-107 Lämnar en spelare tas hens hjältar över av en annan spelare som har färre än 2, annars av datorn (K-18), så att partyt aldrig blir mindre än 4. Hjältarna behåller sina saker, och den som lämnade får tillbaka dem om hen återansluter. (US-20, US-05, K-18)
K-108 Varje text en spelare ser finns på engelska, svenska, danska och nederländska (utom spelledarens egna texter, K-103). TV:n visar värdens språk eller adressens (K-71), och mobilen spelarens eget. (US-20, K-28, K-29, K-71)

**Pussel på telefonen** (godkänt av Woody 2026-10-19, ”ja vi kör på”, med förvalen: pussel har inget fel svar och kostar inga minuter; 60 minuter med fem pusseltyper byggs först, minnet, lutningslabyrinten och 90 minuter senare)
K-109 Escape room får fem nya låstyper, som data på samma sätt som de andra (K-72): `slide` (skjutbricka), `pipes` (koppla ihop rör), `toggle` (vänd ventilerna), `rings` (vridringar) och `rhythm` (rytmen). Kodlåset kan visas som hjul (`"look": "dial"`) och ta sina siffror från ett skjutbrickelås (`"digits": "<lås>"`). Ett rum kan byta ut skärmarnas allmänna ord (elden, bälgen) mot sina egna (`screen.<nyckel>`). Minnet och lutningslabyrinten kommer senare. (US-24, K-72, NFR-10)
K-110 Varje pussel går att spela med pekskärm, mus och knappar. Rytmen har knapparna Kort och Lång utöver lampan att trycka på, hjulen har pilar upp och ner, och ringarna har pilar åt vänster och höger. Tillstånd visas med form och färg, aldrig bara färg: en öppen ventil är ett hjul med ekrar, en stängd en fylld ratt med en tvärslå. (US-24, K-05, K-33–K-35)
K-111 Ett pussel har inget fel svar. Delade pussel (`pipes`, `toggle`, `rings`) har sitt läge på servern: varje drag skickas dit, och låset öppnas av sig självt när läget är rätt. En skjutbricka spelas på telefonen och skickar sina drag när den ligger rätt; servern spelar upp dragen från startläget och godtar bara ett löst spel. Inget av dem kostar minuter. Rytmen skickas som ett svar; ett fel svar kostar inget (skeppet blinkar bara igen), och servern tar emot högst ett var tredje sekund. Servern tar emot högst tio drag per sekund från en spelare. (US-24, K-81, K-14, NFR-05; avviker från K-81:s två minuter)
K-112 Det som löser ett pussel finns bara på servern (K-73): siffran bakom en skjutbricka skickas till den spelarens telefon först när servern har godkänt spelet; ventilschemat, ringarnas mål och signalbokens svar finns bara som bitar hos spelarna. (US-24, K-73, NFR-05)
K-113 I ett delat pussel delar servern ut delar att äga (rörbitar, ringar) jämnt, och bara ägaren kan vrida sin del. Vägen genom ett rörnät går genom minst två spelares rutor, och raden för en ring finns aldrig hos ringens ägare när ni är två eller fler. TV:n och telefonerna visar hela pusslet med varje del i ägarens färg och initial, och en ruta i rörnätet med bokstav och siffra (A1 uppe till vänster). (US-24, K-79)
K-114 Kontrollen (K-74) har en lösare för varje typ och stoppar en dragning där: en skjutbricka inte går att lösa eller ligger färre än åtta drag från färdig; ett rörnät saknar väg, redan är färdigt eller har ojämnt ägda bitar; ett ventilschema inte går att nå eller ligger färre än tre vridningar bort; en ring redan står rätt eller dess rad finns hos ägaren; två rader i signalboken har samma signal eller samma svar, eller skeppets signal finns i fler än en rad. (US-24, K-74)
K-115 Tips för ett pussel (K-82): det andra tipset pekar ut en ruta, en ring eller vem som har raden; det tredje lägger pusslet rätt på servern (för skjutbrickor: ett av de olösta spelen, och siffran går till dess spelare), och det syns på alla skärmar. (US-24, K-82)
K-116 TV:n visar ett delat pussel som det ligger, inom 5 sekunder efter ett drag (NFR-02). För skjutbrickorna visar TV:n vem som pusslar och vem som är klar, men inte bilden eller siffran. (US-24, NFR-02)
K-117 Pusslens delar (sjömärkena på dörren, rören, ventilerna, ringarnas tecken, skeppets blinkande lampa och hjulens siffror) ritas av appen. Bilderna har ingen text; en skjutbricka är en bild som appen klipper i rutor. (US-24, K-76)
K-118 Lämnar en spelare går också hens olösta skjutspel, rörbitar och ringar till den kvarvarande som har minst (K-84). (US-24, K-84)

**Fyren i stormen** (godkänt av Woody 2026-10-19, ”ja vi kör på”)
K-119 ”Fyren i stormen” (`lighthouse`) har 30 min (Bostaden och Lanternan: fotogenlampan, sjömärkespusslen, tornets dörr och två linsringar) och 60 min (Bostaden, Maskinrummet och Lanternan, 8 lås). 90 min (Signalhuset, med minnet och lutningslabyrinten) kommer senare. Vid 60 min: skjutbrickor 3×3, rörnät 5×4, ventiler 3×3, tre linsringar med åtta tecken och signaler med 3–4 blink. Tornets dörr öppnas bara av den som har Albins nyckelknippa, som den som tänder lampan får. (US-25, K-08, K-75, K-80)
K-120 Antalet skjutspel är fyra, eller ett per spelare när ni är fler än fyra; dörren visar fyra av sjömärkena. (US-25, K-73)

**Testläge** (önskat av Woody 2026-10-19)
K-121 Värden kan i lobbyn lägga till upp till fem testspelare. Var och en öppnas i en ny flik på värdens dator, är godkänd direkt och är en egen spelare med eget privat läge (K-13), men hör till värdens konto. En testspelare är aldrig värd (kan inte starta, pausa, avsluta eller släppa in), och i rollspelet sparas dess hjältar inte. Det är inte hotseat: varje testspelare har sin egen skärm. (US-26, K-03, K-12, K-13)
K-122 Värden ser i lobbyn en inbjudningslänk till spelet och kan kopiera den. Med länken kan en medlem som är inloggad med Discord men saknar rollen testare be att få gå med som spelare i just det spelet; hen väntar ändå på värdens ja (K-12) och får inte starta egna spel. Utan inloggning visar sidan en knapp till Discord-inloggningen som leder tillbaka till spelet. (US-26, K-12, K-41)
K-123 Inbjudningen är en slumpad nyckel per spel (128 bitar) som bara värden ser, och den gäller bara det spelet. Fel eller saknad nyckel ger samma svar som utan länk. (US-26, K-41)

## 4. Icke-funktionella krav
NFR-01 Plattform: mobil, surfplatta, dator med flera skärmar och TV (K-04) stöds från första versionen. | Mått: varje enhetstyp i K-02 och K-04 klarar ett helt spel. | Källa: "alla spel har en variant och stöd för alla apparater/enheter"
NFR-02 Prestanda: en handling syns på alla spelplaner inom högst 5 sekunder vid normal uppkoppling. | Mått: ≤ 5 s | Källa: "i vanliga fall max 5 sekunder"
NFR-03 Tillförlitlighet: pausade spel sparas i minst 1 år. | Mått: ett spel som återupptas efter 364 dagar fungerar. | Källa: "kan få vara kvar ett år till en början"
NFR-04 Tillförlitlighet: väntetid vid tappad uppkoppling. | Mått: inställbar 1–10 min, förval 5. | Källa: "låt spelledaren ställa in 1 till 10 min och default blir 5"
NFR-05 Säkerhet: det ska inte gå att fuska med hjälp av program, virus eller liknande, och trafiken får inte kunna läsas eller ändras av obehöriga. | Mått: saknas, se 8. | Källa: "inte ska gå att fuska via program/virus"
NFR-06 Säkerhet: bara konton med 2FA godkänns. | Mått: inloggning utan verifierad 2FA nekas. | Källa: K-23
NFR-07 Integritet: minimal datamängd, ingen försäljning eller delning av uppgifter, GDPR följs. | Mått: varje sparat fält har ett dokumenterat syfte. | Källa: "vi ska aldrig sälja/dela dessa uppgifter vidare"
NFR-08 Användbarhet: kan användas av alla från 13 år (yngre via familjedelning). | Mått: en 13-åring ansluter och spelar utan hjälp. | Källa: "då kanske vi får ta och börja från 13 år"
NFR-09 Tillgänglighet: stöd för syn, hörsel, motorik och kognition. | Mått: saknas, se 8. | Källa: "huvudkategorier: syn, hörsel, motorik och kognition"
NFR-10 Flerspråkighet: ett nytt språk kan läggas till utan kodändring. | Mått: nytt språk = bara nya texter. | Källa: förslaget om språktabell med engelska som reserv
NFR-11 Affär: testfasen är kostnadsfri för testare. Betalning först vid lansering. | Källa: "låta några vänner kunna prova några spel kostnadsfritt"
NFR-12 Kvalitet: ingen deadline, kvalitet först. Testning enligt ISTQB. | Källa: "tiden är av mindre vikt"

## 5. Acceptanskriterier
AK-01 (US-01 / K-01) Givet ett spel med 2 spelplaner på olika orter och 4 spelarenheter, När spelare A visar en ledtråd för alla, Då syns den på båda spelplanerna inom 5 s.
AK-02 (US-01 / K-03) Givet en spelare vid en dator med två skärmar, När spelaren öppnar spelplanen i ett fönster och spelarvyn i ett annat, Då fungerar båda och räknas som en spelare.
AK-03 (US-01 negativt / K-12) Givet att en andra person försöker ansluta som ny spelare från samma datorsession, När spelledaren inte godkänner, Då kommer personen inte in (ingen hotseat).
AK-04 (US-02 / K-06–K-08) Givet att den som startar väljer 3 spelplaner, 5 enheter, 2 lag och 90 min, När spelet startas, Då visas uppsättningen grafiskt och spelet har den komplexitet som hör till 90 min.
AK-05 (US-02 negativt) Givet att lag är valt, När en spelare inte placerats i något lag, Då kan spelet inte startas och det syns vem som saknar lag.
AK-06 (US-03 / K-13) Givet att spelare A har en privat ledtråd, När spelare B tittar på sin enhet eller på spelplanen, Då syns inte A:s ledtråd.
AK-07 (US-03 negativt / K-14) Givet ett pågående spel, När en spelare försöker skicka fritext till en annan spelare, Då finns ingen sådan funktion. Bara spelinmatning går till spelet.
AK-08 (US-04 / K-15) Givet ett spel pausat för 30 dagar sedan, När spelledaren återupptar det, Då är ställning, ledtrådar och lag oförändrade.
AK-09 (US-04 negativt / NFR-03) Givet ett pausat spel som är äldre än 1 år, När någon försöker återuppta det, Då ges ett tydligt besked om att det har tagits bort.
AK-10 (US-05 / K-16) Givet att spelare A väljer att lämna, När A väljer "lämna tillbaka till spelplanen", Då försvinner ledtrådarna från A och kan hittas av de andra.
AK-11 (US-05 / K-17, K-18, K-20) Givet att A tappar uppkopplingen och väntetiden är 5 min, När 5 min har gått och de 4 kvarvarande röstar 2–2, Då avgör spelledarens röst, och om spelet fortsätter väljs AI-spelare eller borttagning.
AK-12 (US-05 negativt) Givet att en AI-spelare har tagit A:s plats, När A ansluter igen, Då tar A tillbaka platsen och kan läsa vad AI:n hittat.
AK-13 (US-06 / K-21) Givet att en spelare har sämre uppkoppling, När spelledaren öppnar inforutan, Då syns spelaren där och rutan kan visas på spelplanen.
AK-14 (US-07 / K-22, K-23) Givet ett Discord-konto med 2FA, När användaren loggar in, Då lyckas det, och obligatoriska och frivilliga uppgifter visas tydligt.
AK-15 (US-07 negativt) Givet ett konto där 2FA inte kan kontrolleras, När användaren försöker logga in, Då nekas inloggningen med en förklaring.
AK-16 (US-08) Givet en verifierad förälder vars barn finns i familjegruppen hos kontotjänsten, När föräldern visar en QR-kod och barnet skannar den, Då kommer barnet in i spelet.
AK-17 (US-08 negativt) Givet att familjekopplingen inte kan bekräftas, När barnet skannar QR-koden, Då nekas barnet att ansluta.
AK-18 (US-09 / K-28, K-29) Givet att A har valt danska och spelplanen svenska, När en text saknas på danska, Då ser A den på engelska och spelplanen visas på svenska.
AK-19 (US-10 / K-30, K-31) Givet att Discord-rum är valt vid start, När spelet startar, Då skapas ett tillfälligt rum, overlayen visar vem som pratar, och rummet försvinner när spelet avslutas.
AK-20 (US-10 negativt / K-32) Givet en spelare utan Discord, När spelaren ansluter, Då kan hen välja en annan kommunikationsapp och ändå spela fullt ut.
AK-21 (US-11 / K-33–K-35) Givet att en spelare har slagit på skärmläsare och större text i telefonen, När spelaren öppnar spelarvyn, Då läses texten upp och visas förstorad, utan att något hamnar utanför skärmen.
AK-22 (US-11 negativt) Givet en spelare som har valt egna färger och eget typsnitt, När spelet byter vy, Då behålls inställningarna.
AK-23 (US-12 / K-36) Givet ett mysterium som spelas två gånger, När det startas andra gången, Då skiljer sig de slumpade delarna (t.ex. mördaren eller ledtrådarnas placering).
AK-24 (US-12 negativt / K-38) Givet ett inskickat mysterium med en verklig religiös ledare, När testaren granskar det, Då nekas publicering och skaparen får veta varför.
AK-25 (US-12 / K-39) Givet ett mysterium märkt med grövre våld, När en spelare under 18 år eller utan verifierad ålder försöker spela det, Då nekas spelaren.
AK-26 (US-13 / K-41) Givet en inloggad användare utan rollen testare eller admin, När användaren går till den dolda adressen, Då nekas åtkomst.
AK-27 (US-14 / K-25) Givet en spelare som raderade sitt konto för 10 dagar sedan, När spelaren väljer att ångra, Då är kontot och alla uppgifter kvar.
AK-28 (US-14 negativt) Givet att 14 dagar har gått sedan raderingen bekräftades, När spelaren försöker logga in, Då finns inga personuppgifter kvar, bara anonymiserade transaktioner.
AK-29 (US-15 / K-42, K-44) Givet ett Ludo-spel med 2 spelare och en TV, När spelaren som har turen slår på mobilen och väljer pjäs 2, Då flyttar pjäs 2 och syns på TV:n inom 5 s.
AK-30 (US-15 negativt / K-44, K-48) Givet att det inte är min tur, eller att pjäsen inte får flytta, När jag försöker slå eller flytta, Då nekas det och ingenting ändras.
AK-31 (US-15 / K-45) Givet att Erik har valt Fjordblå i lobbyn, När Bob försöker välja Fjordblå, Då nekas det och Fjordblå visas som upptagen.
AK-32 (US-15 / K-47) Givet att Bobs pjäs står på en kungruta, När Eriks pjäs landar där, Då åker Eriks pjäs hem till boet och Bobs står kvar.
AK-33 (US-15 / K-47) Givet att en pjäs landar på en fjäder, När draget görs, Då hamnar pjäsen på nästa fjäder.
AK-34 (US-15 / K-46) Givet att en spelare har valt att skaka, När hen skakar telefonen och häller ut tärningen, Då slås tärningen; och utan sensor går det att trycka på skålen.
AK-35 (US-15 / K-42) Givet 5 spelare, När spelet startar, Då används planen med sex armar och en arm står tom; med 1 eller 7 spelare går det inte att starta.
AK-36 (US-15 / K-49) Givet att Erik lämnar ett Ludo-spel, När Bob som startade spelet väljer att datorn tar över, Då slår och flyttar datorn för Eriks färg och TV:n visar det; kommer Erik tillbaka spelar han själv igen.
AK-37 (US-15 / K-49) Givet att Erik har lämnat och Anna har släppts in, När Bob ger Eriks plats till Anna, Då spelar Anna med Eriks färg och pjäser där de står.
AK-38 (US-15 negativt / K-49) Givet ett pågående Ludo-spel, När någon annan än den som startade det försöker välja vem som tar över, eller platsen ges till en spelare som redan har en, Då nekas det och ingenting ändras.
AK-39 (US-16 / K-51) Givet ”Den sista skålen” med 5 möjliga mördare och 3 vapen, När kontrollen körs för alla 15 kombinationer på 60 och 90 minuter, Då finns exakt en lösning i varje kombination: den slumpade mördaren med det slumpade vapnet.
AK-40 (US-16 negativt / K-51) Givet ett mysterium med ett fel (den skyldige får alibi, en oskyldig går inte att utesluta, en ledtråd saknar en variant, ett felstavat faktum, en plats som inte finns, en text som saknas eller blivit över), När kontrollen körs, Då stoppas mysteriet och kontrollen säger vad som är fel.
AK-41 (US-16 / K-52) Givet ”Den sista skålen” på 60 minuter, När vilken som helst av de privata ledtrådarna saknas, Då går mysteriet ändå att lösa; och på 90 minuter finns fler ledtrådar, och tidsfönstret och vapnet måste räknas ut.
AK-42 (US-16 negativt / K-54) Givet en inloggad ägare, När hen öppnar adressen till en mysteriefil i webbläsaren, Då nekas det (403) och ingenting av innehållet visas.
AK-43 (US-17 / K-56) Givet ett mysteriespel med 3 spelare, När det startas, Då finns varken mördaren, vapnet eller ledtrådarnas ordning i något som skickas till en skärm.
AK-44 (US-17 / K-58) Givet att det är Annas tur, När hon letar i Tornrummet, Då får bara Anna ledtråden, och spelplanen visar inom 5 s att Anna letade där och att ledtrådarna där blev en färre.
AK-45 (US-17 negativt / K-58) Givet att det inte är Bobs tur, eller att platsen är tom, När Bob försöker leta, Då nekas det och ingenting ändras.
AK-46 (US-17 / K-59) Givet att Bob har ledtråden ”Gästboken”, När han visar den för alla, Då syns den på spelplanen och hos alla spelare.
AK-47 (US-17 / K-60, K-61) Givet att Anna anklagar rätt mördare med rätt vapen, När hon skickar anklagelsen, Då vinner hon och alla skärmar visar lösningen och efterordet.
AK-48 (US-17 negativt / K-60) Givet att Bob anklagar fel, När han skickar anklagelsen, Då får bara Bob veta att det var fel, han kan inte anklaga igen, och spelplanen visar att han anklagat fel men inte vad.
AK-49 (US-17 / K-60) Givet 2 spelare som båda har anklagat fel, När den andra anklagelsen görs, Då slutar spelet utan vinnare och lösningen visas.
AK-50 (US-17 negativt / K-55) Givet ”Den sista skålen”, När värden väljer 30 minuter, eller startar med 1 eller 7 spelare, Då går spelet inte att starta.
AK-51 (US-17 / K-65) Givet en lobby med värden Bob, spelaren Erik och en TV, När Bob väljer ”Tillsammans”, Då ser Erik och TV:n inom 5 s att ni spelar tillsammans; Erik kan inte ändra reglerna, och efter start kan ingen.
AK-52 (US-17 / K-67) Givet regeln ”Fritt”, När Anna och Bob letar direkt efter varandra och Bob igen, Då lyckas alla tre.
AK-53 (US-17 / K-68) Givet regeln ”Straff”, När Bob anklagar fel, Då står han över sina 2 nästa turer (utan turer: kan inte leta eller anklaga på 2 minuter men kan efter exakt 2 minuter) och får sedan anklaga igen.
AK-54 (US-17 / K-69) Givet att Bob har en ledtråd från Biblioteket, När Anna frågar honom om Biblioteket och han visar den, Då ser bara Anna den, och spelplanen visar att Bob visade Anna en ledtråd men inte vilken.
AK-55 (US-17 negativt / K-69) Givet att Bob har en ledtråd från platsen han får frågan om, När han svarar att han inte har någon, Då nekas svaret.
AK-56 (US-17 / K-70) Givet regeln ”Dörrar” och att Anna står i lobbyn, När hon försöker leta i Tornrummet, Då nekas det; i Biblioteket går det, och därifrån når hon Tornrummet.
AK-57 (US-17 / K-66, K-20) Givet regeln ”Tillsammans” med 3 spelare, När Bob föreslår en anklagelse och röstningen blir 2 ja mot 1 nej, Då görs anklagelsen för laget; vid 1–1 avgör värdens röst.
AK-58 (US-09 / K-71) Givet att värden har valt svenska för spelplanerna, När en TV öppnar spelplanen med `&lang=DK` i adressen och släpps in, Då visar den TV:n danska och en TV utan koden svenska; med `&lang=GE` visas värdens språk.
AK-59 (US-18 / K-74) Givet Runsmedens lås, När kontrollen körs för 30 och 60 min och 2–6 spelare, Då godkänns rummet; och Givet en kopia där en sköldrad är falsk, Då stoppas den och kontrollen pekar ut raden.
AK-60 (US-18 / K-73) Givet en pågående omgång, När en telefon eller TV:n hämtar sitt läge, Då innehåller svaret inte koden, vikterna, sköldordningen eller kursen för något oöppnat lås.
AK-61 (US-19 / K-79, K-73) Givet två spelare, När spelet startar, Då har deras telefoner tillsammans alla sexton runor, ingen runa finns på båda, och ingen av dem har alla fyra runor som dörren visar.
AK-62 (US-19 / K-81) Givet runlåset, När en spelare skickar rätt kod, Då öppnas dörren på alla skärmar inom 5 s; När hen skickar fel kod, Då håller dörren och tiden ökar med två minuter; När två svar kommer inom 5 s, Då avvisas det andra.
AK-63 (US-19 negativt / K-80) Givet att järnnyckeln och silvernyckeln finns hos olika spelare, När den ena försöker öppna kistan, Då går det inte; När nycklarna har getts till en spelare som också har bronsnyckeln, Då öppnas kistan.
AK-64 (US-19 / K-84) Givet att en spelare har järnnyckeln och sin del av runraden, När hen lämnar, Då hamnar de hos den kvarvarande med minst och TV:n säger det, och runlåset går fortfarande att lösa.
AK-65 (US-19 / K-85) Givet en telefon utan rörelsesensor, När två spelare trycker om vartannat tio gånger var, Då tänds ässjan; När bara en spelare trycker, Då tänds den inte.
AK-66 (US-19 / K-82, K-83) Givet tips på, När spelarna tar tre tips och blir klara inom tiden, Då visar slutet ★★.
AK-67 (US-19 / K-81) Givet den mjuka klockan, När tiden har gått ut, Då fortsätter spelet och slutet visar högst ★★.
AK-68 (US-19 / K-87, K-71) Givet att värden har valt svenska och en spelare danska, När båda läser rummet, Då ser spelaren danska och TV:n svenska; en TV med `&lang=NL` visar nederländska.
AK-69 (US-19 / NFR-01) Givet chromium, firefox, phone, tablet och tv, När ett helt 60-minutersspel spelas igenom av testerna, Då går det att ta sig ut på alla.
AK-70 (US-21 / K-88) Givet de 11 klasserna och 4 folken, När kontrollen körs, Då godkänns alla; och Givet en kopia där vikingen har Kraft +4, Då stoppas den och kontrollen pekar ut vikingen.
AK-71 (K-90) Givet 3 spelare, När två av dem väljer bonde bland de 4 första hjältarna, Då går det inte att starta och lobbyn säger varför; När en spelare försöker välja en tredje hjälte, Då går det inte; Givet 2 spelare med 2 olika hjältar var, Då går det att starta.
AK-72 (K-89, NFR-05) Givet en pågående strid, När en telefon skickar ett eget tärningsresultat, Då ignoreras det och servern slår själv; och När en T20 visar 20, Då lyckas slaget även mot svårighet 20.
AK-73 (K-91) Givet en hjälte med 3 Liv, När den tar 5 i skada, Då är den utslagen med 0 Liv; När en vän hjälper upp den, Då står den igen; När striden är slut, Då har varje utslagen hjälte 1 Liv, och ingen hjälte är någonsin död.
AK-74 (US-23 / K-95) Givet det första äventyret, När kontrollen körs för 60 och 90 min och partyn med 4–6 hjältar, Då godkänns äventyret; och Givet en kopia där en scen inte går att nå eller en nederländsk text saknas, Då stoppas den och kontrollen pekar ut felet.
AK-75 (K-97, K-20) Givet ett val som gäller hela partyt och 4 hjältar, När två röstar på väg A och två på väg B, Då avgör värden; När tre röstar på A, Då väljs A på alla skärmar inom 5 s.
AK-76 (K-101, K-102) Givet en ren spelledare som har tagit över, När hen öppnar en strid, Då ser hen fiendernas Liv, men När en telefon eller TV:n hämtar sitt läge, Då innehåller svaret inte fiendernas Liv eller vad som slumpats; och Givet en spelledare som spelar med, Då finns det ingen knapp för att ta över, och servern nekar om hen försöker.
AK-77 (K-104) Givet att spelledaren har tagit över, När hen tappar uppkopplingen, Då fortsätter datorn berättelsen från samma scen utan paus.
AK-78 (K-92) Givet en hjälte på nivå 1, När partyt spelar klart ett äventyr, Då är hjälten på nivå 2 nästa gång spelaren väljer den; och När kontot raderas och ångerfristen gått ut, Då finns hjälten inte kvar.
AK-79 (K-107) Givet 3 spelare och 4 hjältar, När spelaren med en hjälte lämnar, Då tas hjälten över av den andra spelaren med en hjälte, och partyt har fortfarande 4 hjältar med samma saker.
AK-80 (K-100) Givet en telefon utan rörelsesensor, När en fiende anfaller sköldmön, Då kan hon Parera med en knapp; och völvan kan rista en runa med en knapp i stället för att rita.
AK-81 (K-106) Givet en strid där det är jägarens tur, När spelet pausas och återupptas, Då är det jägarens tur med samma Liv, resurser och fiender.
AK-82 (K-108, K-71) Givet att värden har valt svenska och en spelare danska, När båda läser en scen, Då ser spelaren danska och TV:n svenska; en TV med `&lang=NL` visar nederländska.
AK-83 (NFR-01) Givet chromium, firefox, phone, tablet och tv, När ett helt 60-minutersäventyr spelas igenom av testerna med datorn som spelledare, Då går det att spela klart på alla.
AK-84 (US-25 / K-114) Givet Fyren i stormen, När kontrollen körs för 30 och 60 min och 2–6 spelare, Då godkänns rummet; och Givet en kopia där rörnätet saknar väg, ventilschemat redan är nått eller en ring redan står rätt, Då stoppas den och kontrollen säger vilket lås det gäller.
AK-85 (US-24 / K-112) Givet en pågående omgång, När en telefon eller TV:n hämtar sitt läge, Då innehåller svaret inte ventilschemat, ringarnas mål, signalbokens svar eller siffran bakom ett olöst skjutspel.
AK-86 (US-24 / K-111) Givet ett skjutspel, När telefonen skickar drag som inte löser det, eller ett drag som inte går, Då avvisas det och ingen siffra skickas; När den skickar drag som löser det, Då får bara den telefonen siffran och TV:n säger att spelet är löst.
AK-87 (US-24 / K-113, K-116) Givet gasledningen med tre spelare, När en spelare vrider en ruta som inte är hens, Då avvisas det; När en ruta vrids, Då syns det på TV:n inom 5 s; När den sista rutan på vägen vrids rätt, Då öppnas låset och ingen tid läggs till.
AK-88 (US-24 / K-113) Givet linsringarna med två spelare, När spelet startar, Då finns raden för varje ring hos den andra spelaren än ringens ägare.
AK-89 (US-24 / K-111) Givet signalen, När en spelare skickar fel takt, Då blinkar skeppet igen och tiden ökar inte; När två svar kommer inom 3 s, Då avvisas det andra; När rätt takt skickas, Då visar alla skärmar slutet.
AK-90 (US-24 / K-110) Givet en telefon utan rörelsesensor, När en spelare vrider dörrens hjul med pilarna och knackar signalen med Kort och Lång, Då går båda att lösa.
AK-91 (US-24 / K-115) Givet ventilerna och tips på, När spelarna tar det tredje tipset, Då ligger panelen rätt, låset öppnas och tipset räknas i stjärnorna.
AK-92 (US-24 / K-118) Givet att en spelare har olösta skjutspel eller rörbitar, När hen lämnar, Då hamnar de hos den kvarvarande med minst, TV:n säger det och låsen går fortfarande att lösa.
AK-93 (US-25 / NFR-01) Givet chromium, firefox, phone, tablet och tv, När ett helt 60-minutersspel spelas igenom av testerna med pusslen lagda för hand, Då går det att tända fyren på alla.
AK-94 (US-26 / K-121) Givet att värden har lagt till en testspelare, När spelet startar, Då har testspelaren egna kort som bara dess flik ser och kan spela dem, men kan inte starta, pausa eller släppa in; och Givet ett escape room för minst två, När värden och en testspelare är med, Då går det att starta; När värden försöker lägga till en sjätte testspelare eller lägger till en efter starten, Då avvisas det.
AK-95 (US-26 / K-122, K-123) Givet en inloggad medlem utan rollen testare, När hen går med utan nyckel, med fel nyckel eller med ett annat spels nyckel, Då nekas hen; När hen går med med rätt nyckel, Då väntar hen på värdens ja och kommer in när värden släpper in hen; och nyckeln syns bara för värden och bara i lobbyn.

## 6. Avgränsningar (ingår INTE)
- Hotseat (flera spelare på samma enhet) | Varför: "så inte så kallad hotseat"
- Spel med okända eller öppen matchmaking (till en början) | Varför: "designad att spelas med vänner/familj"
- Chatt mellan spelare i spelet | Varför: "all kommunikation som inte är spelrelaterat ska ske via andra metoder"
- Skydd mot att någon fysiskt tittar på en annans skärm | Varför: "vi kan inte göra något åt att någon rent fysiskt tittar"
- Betalning och försäljning under testfasen | Varför: "betalning tar vi fram innan lansering"
- Konton där 2FA inte kan kontrolleras | Varför: K-23
- Försäljning eller delning av personuppgifter | Varför: "vi ska aldrig sälja/dela dessa uppgifter vidare"
- Verkliga religioner och deras ledare eller figurer i innehållet, utom nordisk mytologi som saga i rollspel (K-38) | Varför: "för allas trevnad"
- Spelare under 13 år, om familjeverifiering inte går att lösa | Varför: "går inte det så får vi hoppa över det"

## 7. Antaganden
- A-01 Rollspelet ingår i första versionen. Testordningen är mordmysterium → escape room → rollspel → skaparverktyg. (Behöver bekräftas: nej)
- A-02 Finns ingen ren spelledare har den som startade spelet spelledarens befogenheter (godkänna, utslagsröst). (Behöver bekräftas: ja)
- A-03 Den som startar spelet väljer spelplanens språk. (Behöver bekräftas: ja)
- A-04 Rollerna testare och admin går att lägga till i den befintliga Discord-inloggningen på hakoo.eu. (Behöver bekräftas: ja)
- A-05 Kravet på 5 s gäller normal uppkoppling. Vid dålig uppkoppling räcker en varning (K-21). (Behöver bekräftas: nej)
- A-06 AI-spelaren följer samma regler som en människa och ser bara den platsens privata information. (Behöver bekräftas: ja)
- A-07 En spelplan (TV) behöver inte vara inloggad: den går med med koden och ett namn och väntar tills värden släpper in den; den ser bara det alla får se (ADR-001). Spelare och spelledare är inloggade med Discord. (Behöver bekräftas: ja)

## 8. Öppna frågor och risker
- Kan 2FA kontrolleras hos varje leverantör? Om inte, kan listan med inloggningar krympa kraftigt. | Påverkar: K-22, K-23, NFR-06
- Går familjedelning för barn under 13 att verifiera tekniskt hos Apple, Google eller Microsoft? | Påverkar: US-08, AK-16
- Hur verifieras att en spelare är 18 år (metod)? | Påverkar: K-39, AK-25
- Juridisk granskning av villkoren för att skaparens innehåll tillfaller spelet vid kontoavslut. | Påverkar: K-27
- Overlayen för vem som talar går troligen bara att bygga för Discord. | Påverkar: K-31, K-32
- Lagar och regler att utreda: tillgänglighetsdirektivet EAA och vilken WCAG-nivå, DSA (användarskapat innehåll), ångerrätt för digitalt innehåll, åldersmärkning (PEGI), bokföringslagens lagringstid, appbutikernas regler för betalning i app. | Påverkar: NFR-07, NFR-09, K-25, K-38
- Mått för tillgänglighet och vad personer med NPF behöver. | Påverkar: NFR-09, US-11
- Fuskskydd behöver en konkret hotbild och mått. | Påverkar: NFR-05
- Högsta antal spelare, spelplaner och lag per spelomgång har inte diskuterats. | Påverkar: K-01, K-06, NFR-02
- Driftkostnad och var datan lagras (t.ex. inom EU). | Påverkar: NFR-07, NFR-11
- Ska Discord-rummet finnas kvar när spelet är pausat i flera dagar? | Påverkar: K-30
- Omfattningen är stor (allt från start), vilket ger lång utveckling och omfattande testning. | Påverkar: NFR-01, A-01
- Vem har utslagsrösten om spelledaren själv har tappat uppkopplingen? | Påverkar: K-20
- hakoo:s "Ladda ner mina data" måste ta med Mysteries-tabellerna (sessioner, enheter, privat spelläge) innan riktiga spelare används; "Radera all min data" tar redan bort dem (ON DELETE CASCADE). Ändringen görs i hakoo-repot. | Påverkar: NFR-07
- Kan webbhotellet hålla WebSockets öppna? Behövs inte nu (ADR-001: skärmarna frågar var 1,5 s), men skulle minska belastningen. | Påverkar: NFR-02, R-03
- ~~Hur ett mordmysterium spelas på skärmarna~~ Besvarat 2026-10-16: US-17 och K-55..K-70, godkända av Woody. De fem valen är regler som värden väljer i lobbyn (K-65). Straffets storlek (2 turer eller 2 minuter) är Claudes förval. | Påverkar: US-17, K-07, K-58, K-60
- ”Den sista skålen” behöver granskas av en testare (K-38) innan den spelas. | Påverkar: US-16, K-38
- Runsmedens lås: 90 minuter (Vävstugan, tre gåtor till) är inte skrivet än. | Påverkar: US-18, K-75
- Ska en hård klocka finnas (rummet vinner när elden slocknar)? Woody valde 2026-10-17 bara mjuk klocka tills vidare; K-08 säger att längden inte är en nedräkning. | Påverkar: K-81, K-86
- ”Runsmedens lås” behöver granskas av en testare (K-38) innan den spelas. | Påverkar: US-18, K-38
- ”Den stulna hammaren” och de 47 bilderna behöver granskas av en testare (K-38) innan äventyret spelas, särskilt hur Tor, Loke och Hårbard (Oden) visas. | Påverkar: US-23, K-38
- Rollspelet är byggt 2026-10-18 (Woodys ”kör på”). Motorn skiljer sig från utkasten på några punkter (till exempel jättekarlens Liv 32 → 38 för att striderna ska hamna inom 70–95 %); listan finns i rapporten till Woody. Föreslaget men obesvarat: völvans Kaun T8 → T10. | Påverkar: US-20..US-23, K-94, K-95
- Nivå 3–5 för de sju extra klasserna är Claudes förslag och har inte godkänts. | Påverkar: K-88, K-92
- hakoo:s ”Ladda ner mina data” måste också ta med `mysteries_heroes` (hjältarnas namn, klass, nivå och gåvor). Ändringen görs i hakoo-repot. | Påverkar: K-92, NFR-07
- Fyren i stormen: 90 minuter (Signalhuset med minnet och lutningslabyrinten) är inte byggt än. | Påverkar: US-25, K-109, K-119
- Fyren i stormen har Woodys Midjourney-bilder sedan 2026-10-19 (`design/approved/US-25_*`) och behöver granskas av en testare (K-38). | Påverkar: US-25, K-38, K-117
