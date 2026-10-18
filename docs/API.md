# CrewGate – API-Dokumentation (v2)

> Wird bei jedem Release mitgepflegt.


Die API wird in *Einstellungen → 9 · API (v2)* mit einem Token aktiviert
(„Neues Token generieren" oder manuell eintragen; leeres Feld = API aus).
Jede Änderung wird protokolliert. Authentifizierung je Request:

```
Authorization: Bearer <Token>      (alternativ: X-Api-Token: <Token>)
```

| Endpunkt | Zweck |
|---|---|
| `POST /api/v2/cases` | Vorgang anlegen (JSON: `process`, `first_name`, `last_name`, `supervisor_username`, optional `start_date`, `supervisor_due_date`, `roles_due_date`, `notes`, `fields` als `{field_id: value}`). Der Vorgang startet wie gewohnt in der Vorbereitung, die Vorgesetzten-Mail geht sofort raus |
| `GET /api/v2/cases` | Statusliste (Filter `?status=draft|open|review|done|cancelled`, `?process=<key>`) |
| `GET /api/v2/cases/{id}` | Einzelstatus inkl. Quittierungen (wer/wann), ausstehender Abteilungen und **aller gepflegten Werte** (Feld, Abteilung, Wert, Anzeigewert) |

**Alte Adressen:** `/api/v2/vorgaenge` und `/api/v2/vorgaenge/{id}` gelten
weiter, gleichwertig zu `/api/v2/cases` (keine Weiterleitung, auch `POST`).

**Prozess-Schlüssel** (`process`): eingebaut sind `onboarding`, `job_change`
(Stellenwechsel), `exit` (Austritt) und `master_data_change`
(Stammdatenänderung); eigene Prozessarten haben den Schlüssel aus ihrem Namen.
Die alten Schlüssel `wechsel`, `austritt` und `stammdaten` werden beim Anlegen
und im Filter `?process=` weiter angenommen. Die Antwort enthält in dieser
Version zusätzlich `process_legacy` mit dem alten Schlüssel; das Feld entfällt
mit einer der nächsten Versionen.

Beispiel:

```bash
curl -H "Authorization: Bearer <Token>" -H "Content-Type: application/json" \
  -d '{"process":"onboarding","first_name":"Max","last_name":"Mustermann","supervisor_username":"m.chef"}' \
  https://portal.example.com/api/v2/cases
```

Empfehlungen: Token nur über TLS verwenden, bei Personalwechsel neu
generieren (altes Token wird sofort ungültig), API hinter dem Reverse Proxy
nur intern freigeben.

