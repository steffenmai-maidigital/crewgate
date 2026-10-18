# CrewGate – Benutzerhandbuch

Dieses Handbuch richtet sich an alle Anwender des CrewGate-Portals:
**Personalabteilung (HR)**, **Vorgesetzte** und die **Fachabteilungen**
(z. B. IT, Einkauf, Facility). Für die Konfiguration der Anwendung gibt es
ein eigenes **Admin-Handbuch** (`docs/ADMIN-GUIDE.md`).

---

## 1. Was macht die Anwendung?

Wenn ein neuer Mitarbeiter eingestellt wird, müssen mehrere Abteilungen etwas
vorbereiten: die IT ein Notebook und ein Benutzerkonto, der Einkauf Möbel, das
Facility-Management Schlüssel und Zutritt. Das Portal bildet genau diesen
Ablauf ab – nachvollziehbar, protokolliert und ohne E-Mail-Pingpong.

Der Ablauf in vier Schritten:

1. **HR legt den Vorgang an** (Name, Eintritt, Vorgesetzter) → Status
   *in Vorbereitung*.
2. **Der/die Vorgesetzte pflegt die Angaben** für alle beteiligten Abteilungen
   und sendet den Vorgang ab.
3. **Alle Fachabteilungen** werden gleichzeitig per E-Mail informiert und
   **quittieren jeweils den Erhalt** ihrer Informationen.
4. **HR quittiert abschließend** und schließt den Vorgang ab. Danach beginnt
   die Aufbewahrungsfrist.

---

## 2. Anmelden

Die Anmeldung erfolgt mit dem **Windows-Konto** (Active Directory) – also mit
demselben Benutzernamen und Passwort wie am Arbeitsplatzrechner. Welche Rechte
Sie im Portal haben, ergibt sich automatisch aus Ihren AD-Gruppen.

> **Demo-Modus:** Läuft die Anwendung als Demo, sind auf der Anmeldeseite
> Testkonten aufgelistet (Passwort jeweils `demo`). Es ist keine AD-Verbindung
> nötig.

### Sprache umschalten

Oben rechts finden Sie den **Sprachumschalter** mit deutscher und britischer
Flagge. Beim ersten Aufruf wählt das Portal die Sprache automatisch anhand
Ihrer Browsersprache (Deutsch, wenn erkannt – sonst Englisch). Ihre Auswahl
bleibt für die Sitzung erhalten.

### Hell/Dunkel

Der Schalter **◐** oben rechts wechselt zwischen hellem und dunklem Design.

---

## 3. Die Übersicht (Startseite)

Ganz oben sehen Sie drei Kennzahlen zu den für Sie sichtbaren Vorgängen:

| Kennzahl | Bedeutung |
|---|---|
| **Offen (in Vorbereitung)** | HR hat angelegt, der Vorgesetzte pflegt noch die Daten |
| **In Bearbeitung** | Abgesendet – die Abteilungen quittieren bzw. HR schließt ab |
| **Abgeschlossen** | Fertig bearbeitet, Aufbewahrungsfrist läuft |

Darunter folgen – je nach Ihrer Rolle – Ihre Aufgabenliste, die Vorgänge Ihrer
Abteilung und (für HR) die getrennten Listen *Offene Vorgänge* und
*Abgeschlossene Vorgänge*.

Der Status eines Vorgangs wird immer als farbige Plakette angezeigt:
*in Vorbereitung*, *in Bearbeitung*, *wartet auf HR-Abschluss*,
*abgeschlossen* oder *abgebrochen*.

---

## 4. Für die Personalabteilung (HR)

### 4.1 Neuen Mitarbeiter anlegen

1. Menüpunkt **Neuer Mitarbeiter**.
2. Vorname, Nachname und Eintrittsdatum eintragen.
3. **Vorgesetzter**: Benutzernamen oder Namen eingeben und auf
   **Im AD suchen** klicken. Das Portal liest Namen und **Abteilung** direkt
   aus dem Active Directory – die Abteilung bestimmt, wo der neue Mitarbeiter
   arbeiten wird.
4. Die zusätzlichen **Anlage-Felder** ausfüllen (z. B. Stellenbezeichnung,
   Beschäftigungsart, Personalnummer). Welche Felder das sind, legt die
   Administration fest.
5. **Anlegen** – der Vorgang steht auf *in Vorbereitung*, und der
   Vorgesetzte erhält automatisch eine E-Mail mit einer Beschreibung und dem
   Link zum Portal.

> Die E-Mails enthalten immer nur einen **allgemeinen Portal-Link**, nie einen
> Direktlink auf einen konkreten Vorgang.

### 4.2 Den Fortschritt verfolgen

In der Liste **Offene Vorgänge (HR)** sehen Sie in der Spalte
**Ausstehende Quittierungen** genau, welche Abteilung ihre Informationen noch
nicht quittiert hat. Steht dort **alle quittiert**, sind alle Fachabteilungen
fertig und der Vorgang wartet auf Sie.

### 4.3 Vorgang abschließen

Sobald der Status **wartet auf HR-Abschluss** lautet, erscheint in der Liste
(und auf der Vorgangsseite) der Button **Abschließen**. Mit dem Klick
quittieren Sie abschließend: Der Vorgang wechselt auf *abgeschlossen*, das
Abschlussdatum wird festgehalten und ab diesem Zeitpunkt läuft die
Aufbewahrungsfrist, nach der der Vorgang automatisch gelöscht wird.

### 4.4 Vorgang abbrechen oder löschen

Auf der Vorgangsseite stehen HR zwei weitere Aktionen zur Verfügung:

- **Vorgang abbrechen** – z. B. wenn die Einstellung platzt. Der Workflow
  stoppt, der Vorgang bleibt mit dem Status *abgebrochen* sichtbar.
- **Vorgang löschen** – entfernt den Vorgang und alle erfassten Daten
  endgültig. Die Löschung selbst wird revisionssicher im Protokoll festgehalten
  (wer, wann, welcher Mitarbeiter, wie viele Datensätze).

Beide Aktionen fragen vorher noch einmal nach.

---

## 5. Für Vorgesetzte

### 5.1 Die Benachrichtigung

Sobald HR einen Vorgang für Ihren neuen Mitarbeiter angelegt hat, erhalten Sie
eine E-Mail mit einer kurzen Beschreibung und dem Link zum Portal. Melden Sie
sich dort mit Ihrem Windows-Konto an – der Vorgang erscheint in der Übersicht
unter **Neue Mitarbeiter in deiner Abteilung**.

### 5.2 Angaben pflegen

Öffnen Sie den Vorgang. Im Bereich **Vorbereitung: Angaben für die Abteilungen
pflegen** finden Sie alle Felder – nach Abteilung gruppiert, z. B.:

- **IT**: Gerätetyp, benötigte Software, gewünschter Benutzername
- **Einkauf**: Möbelauswahl (mit Bildern, Preisen und Kategorien), Kostenstelle
- **Facility**: Zutrittsberechtigung, Schlüssel, Parkplatz-Kennzeichen

Zwei Schaltflächen stehen bereit:

| Schaltfläche | Wirkung |
|---|---|
| **Zwischenspeichern** | Speichert den aktuellen Stand. Pflichtfelder dürfen noch leer sein. Sie können jederzeit weiterarbeiten. |
| **Absenden & Workflow starten** | Prüft alle Pflichtfelder und benachrichtigt anschließend **alle Abteilungen gleichzeitig** per E-Mail. |

Fehlt beim Absenden ein Pflichtfeld, erscheint oben eine rote Meldung mit den
betroffenen Feldern – der Vorgang wird dann **nicht** abgesendet.

> Mit dem Absenden geben Sie die Angaben frei. Danach quittieren die
> Abteilungen; Änderungen sind ab diesem Punkt Sache der jeweiligen Abteilung.

### 5.3 Danach

Im Bereich **Neue Mitarbeiter in deiner Abteilung** verfolgen Sie den Stand:
Die Spalte *Ausstehende Quittierungen* zeigt, welche Abteilung noch nicht
bestätigt hat.

---

## 6. Für Fachabteilungen (IT, Einkauf, Facility …)

### 6.1 Die Benachrichtigung

Ihre Abteilung erhält eine **Text-E-Mail** an die zentrale Adresse der Rolle.
Darin stehen bereits alle für Sie relevanten Angaben – Ihre eigenen Felder und
zusätzlich die Daten, die für Ihre Rolle freigegeben wurden (z. B. sieht die IT
die Bestellung des Einkaufs). Die Mail ist zweisprachig: erst der deutsche,
darunter der englische Text.

### 6.2 Erhalt quittieren

Auf der Startseite erscheint der Vorgang unter **Aufgaben für deine
Abteilung**. Die Spalte **Eure Daten** zeigt:

- **ausgefüllt** – alle Pflichtangaben für Ihre Abteilung liegen vor
- **unvollständig** – es fehlt noch etwas

Es gibt zwei Wege:

1. **Direkt in der Liste**: Steht dort *ausgefüllt*, klicken Sie einfach auf
   **Erhalt quittieren**. Die vorliegenden Daten werden unverändert übernommen.
2. **Im Vorgang**: Klicken Sie auf **Öffnen**. Dort können Sie die Angaben
   Ihrer Abteilung prüfen, ergänzen oder korrigieren und dann auf
   **Speichern & Erhalt quittieren** klicken.

Jede Abteilung quittiert **unabhängig** von den anderen – es gibt keine
Wartezeit und keine Reihenfolge. Sobald alle quittiert haben, wandert der
Vorgang automatisch zu HR.

> Quittieren kann nur, wer Mitglied der entsprechenden AD-Gruppe ist. Fehlen
> Pflichtangaben, weist das Portal Sie mit einer Meldung darauf hin, welche.

---

## 7. Protokoll (HR, Administratoren, Auditoren)

Der Menüpunkt **Protokoll** zeigt lückenlos jede Aktion: Anlage, ausgefüllte
Felder, Quittierungen, versendete E-Mails, Änderungen an Einstellungen,
Löschungen. Die Einträge sind fortlaufend über eine SHA-256-Hash-Kette
verkettet und können nachträglich nicht verändert werden.

- **Filter** nach Typ (Vorgänge, Felder, Schritte, E-Mails, Löschungen)
- **Als CSV exportieren** – zweisprachige Spaltenköpfe
- **Audit-Bericht (PDF)** – vollständiger Bericht mit Firmen-Branding,
  Export-Zeitstempel, Löschungen und den zuletzt eingestellten Mitarbeitern

Auf jeder Vorgangsseite finden HR, Administratoren und Auditoren zusätzlich
das **Protokoll zu diesem Vorgang**.

---

## 8. Hilfe

Der Link **Hilfe** im Footer zeigt Lizenzname (Standard oder
Enterprise), Lizenznehmer, Kundennummer, Anzahl, Lizenzcode und das Ende des
Supports. Ist das Support-Ende überschritten, erscheint es rot mit dem Hinweis
**Support abgelaufen** – rein informativ; die Anwendung läuft unverändert
weiter.

---

## 9. Häufige Fragen

**Ich sehe einen Vorgang, kann aber nicht alle Daten sehen.**
Fachabteilungen sehen nur ihre eigenen Felder plus die Daten, die für ihre
Rolle ausdrücklich freigegeben wurden. HR, Vorgesetzte, Administratoren und
Auditoren sehen alles.

**Ich kann nicht quittieren – der Button fehlt.**
Entweder ist Ihre Abteilung nicht mehr offen (bereits quittiert), oder die
Pflichtdaten fehlen noch (dann steht *unvollständig* – öffnen Sie den Vorgang
und ergänzen Sie sie), oder Sie sind nicht Mitglied der AD-Gruppe dieser Rolle.

**Ich habe beim Absenden eine Fehlermeldung bekommen.**
Beim Absenden werden alle Pflichtfelder geprüft. Die rote Meldung nennt die
fehlenden Felder mit Abteilung und Bezeichnung. Ergänzen Sie sie und senden Sie
erneut ab. Zwischenspeichern funktioniert auch mit Lücken.

**Warum steht in der E-Mail kein Link zum Vorgang?**
Aus Datenschutzgründen enthalten alle E-Mails nur den allgemeinen Portal-Link.
Melden Sie sich an – Sie finden den Vorgang in Ihrer Übersicht.

**Wie lange bleiben Vorgänge gespeichert?**
Abgeschlossene Vorgänge werden nach Ablauf der eingestellten Aufbewahrungsfrist
automatisch und protokolliert gelöscht. Die Frist legt die Administration fest.


## Für HR: Mehrere Mitarbeiter auf einmal anlegen (CSV-Assistent)

Auf „Neuen Vorgang anlegen" öffnen Sie unten den **Import-Assistenten**. Er
führt in drei Schritten durch den Import: (1) aktuelle Vorlage herunterladen
(mit Anleitung zu allen Spalten direkt auf der Seite), (2) ausgefüllte CSV
hochladen, (3) jede Zeile prüfen – alle Felder sind dort noch korrigierbar,
der Vorgesetzte wird bequem über die AD-Suche gewählt – und den Vorgang
per Klick anlegen. So arbeiten Sie Zeile für Zeile ab, bis alles angelegt
ist; nichts wird ungeprüft übernommen. Jeder Vorgang startet wie gewohnt in
der Vorbereitung, die Vorgesetzten erhalten sofort ihre E-Mail.


## Die Workflow-Kette im Detail

Die Kreis-Grafik zeigt die komplette Kette eines Vorgangs: **Anlage (HR)** →
**Vorgesetzter** (Vorbereitung) → die beteiligten **Abteilungen**
(Quittierungen) → **Abschluss (HR)**. Erledigte Schritte tragen einen Haken,
der aktuell anstehende Schritt ist blau hervorgehoben und pulsiert leicht.
Wer mit der Maus über einen Kreis fährt, sieht Details (z. B. Anlagedatum,
Name des Vorgesetzten oder „Quittierung ausstehend"). Bei abgebrochenen
Vorgängen werden offene Schritte ausgegraut.
