# Rua – der Wikinger-Fuchs

*Erst Deutsch, dann Englisch — German first, English below.*

Rua ist der Fuchs-Helfer in CrewGate. Wer neue Hinweise für ihn schreibt oder
seine Zeichnung ändert, hält sich bitte an diese Beschreibung.

## Herkunft

Rua stammt aus einem Fjorddorf in Norwegen. Seinen Namen hat er aus Irland:
Als junger Fuchs segelte er nach Dyflin, dem Wikinger-Dublin, und die Iren
nannten ihn wegen seines Fells Rua, „der Rote“.

## Eigenschaften

- **Entdecker:** kennt jeden Winkel der App und zeigt den kürzesten Weg.
- **Schlau statt stark:** löst Probleme mit Überblick, nicht mit Gewalt.
- **Verlässlich:** Was er sagt, stimmt.
- **Geduldig:** Ein Fehler bringt ihn nicht aus der Ruhe.
- **Knapp:** kein Satz zu viel.
- **Gastfreundlich:** Wer kommt, wird willkommen geheißen.

## Ton

- Er **duzt** – auch wenn die übrige Oberfläche beim „Sie“ bleibt.
- Warm, kurz und kernig.
- Hier und da ein Bild vom Meer, von Schiffen und Runen – **höchstens eins
  pro Hinweis**.
- Fehler sind kein Drama, sondern Gegenwind.
- **Ernstes ohne Bild und Witz:** Passwörter, Zwei-Faktor, Datenschutz,
  Löschen und alles, was Daten endgültig verändert.
- Seefahrer und Erzähler, **kein Krieger**: keine Kämpfe, keine
  Plünderwitze, keine Hörnerhelme.

| Anlass | Beispiel |
|---|---|
| Begrüßung | „Heil dir! Ich bin Rua. Wohin soll die Fahrt gehen?“ |
| Erfolg | „Gespeichert, fest wie ein Runenstein.“ · „Skål! Erledigt.“ |
| Fehler | „Gegenwind. Versuch es nochmal, ich bleib an Deck.“ |
| Warten | „Die Ruder sind im Wasser …“ |
| Ernst | „Dein Passwort kennt nur du. Gib es niemandem weiter, auch mir nicht.“ |

## Aussehen

Rotes Fell mit weißer Brust und Schwanzspitze, ein Ohr leicht schief, ein
grob gewebter moosgrüner Umhang mit runder Fibel und ein Runen-Amulett
(ᚱ Raidō – „Reise“). **Kein Hörnerhelm.** Die Zeichnung ist ein eigenes SVG in
`resources/views/partials/fox.blade.php`.

## Wo die Texte stehen

`app/Support/Fox.php` – die deutschen Texte sind die Übersetzungsschlüssel,
die Übersetzungen stehen in `lang/*.json`. Abschalten lässt sich Rua zentral
in den Einstellungen und für jede Person auf der Konto-Seite.

## English

Rua is the fox helper in CrewGate: a Viking fox from a Norwegian fjord village
who got his name ("the red one") in Viking Dublin. He is an explorer, clever
rather than strong, reliable, patient, brief and hospitable. He always uses
the informal "du" in German, speaks warmly and briefly, uses at most one
image of the sea, ships or runes per hint, treats errors as headwind, and
never jokes about passwords, two-factor, privacy or deleting. He is a
seafarer, not a warrior – no horned helmet. Texts live in `app/Support/Fox.php`
(German strings are the keys, translations in `lang/*.json`); the drawing is
an SVG in `resources/views/partials/fox.blade.php`.
