# beraterwechsel.ch: Go-Live-Anleitung

## Inhalt
- `index.html` die Landingpage (Fotos liegen in `img/`)
- `img/` Fotos (komprimiert, werden verzögert geladen)
- `favicon.svg` Browser-Symbol
- `lead.php` nimmt Anfragen entgegen und schickt sie per E-Mail an `jetzt@beraterwechsel.ch`

## 1. Hochladen
Alles in den Hauptordner der Domain `beraterwechsel.ch` kopieren, mit gleicher Ordnerstruktur. Das Hosting braucht PHP 7.4 oder neuer.

## 2. Formular testen
- In `lead.php` oben `$TO` und `$FROM` prüfen. `$FROM` sollte eine Adresse der eigenen Domain sein (z. B. anfrage@beraterwechsel.ch), sonst landen die Mails im Spam.
- Testanfrage abschicken. Es kommen bis zu drei Mails an: Anfrage, Vertragsangaben und das unterschriebene Mandat (Unterschrift als PNG im Anhang). Der Kunde bekommt eine Bestätigungsmail.
- Ohne PHP: in `index.html` bei `CONFIG.endpoint` die URL eines Dienstes eintragen (Make, Zapier, CRM-Webhook). Das Format ist JSON, die Felder stehen in `lead.php`.

## 3. Tracking (nur nach Einwilligung)
In `index.html` den Block `CONFIG` ausfüllen:
- `ga4`: Google-Analytics-ID (G-XXXXXXXXXX)
- `adsId` und `adsLeadLabel`: Google-Ads-Conversion (AW-… und Label)
- `metaPixel`: Meta-Pixel-ID

Die Skripte laden erst, wenn der Besucher im Cookie-Banner zustimmt (Consent Mode). Die Lead-Conversion wird beim Absenden von Schritt 2 (Kontaktdaten) ausgelöst. Die Danke-Ansicht hat die Adresse `/#danke` und sendet `virtual_pageview` mit `page_path=/danke`.
Mit jeder Anfrage werden gespeichert: `utm_*`, `gclid`, `gbraid`, `wbraid`, `fbclid`, Hero-Variante, Referrer.

## 4. Anzeigen-Links
- Hero-Varianten: `https://beraterwechsel.ch/?variant=broker` (weitere: ab-test, versicherungsberater, makler, vorsorgeberater, lebensversicherung, nicht-erreichbar, keine-betreuung, saeule-3a)
- UTM-Parameter im Anzeigenlink anhängen, z. B. `&utm_source=google&utm_medium=cpc&utm_campaign=...`

## 5. Noch offen vor dem Start der Anzeigen
- Datenschutzerklärung und AGB juristisch prüfen (Entwürfe in der Fusszeile, in `index.html` im Objekt `FP`)
- UID / MWST-Nr. im Impressum ergänzen
- Angaben nach Art. 45 VAG und Vermittlerstatus der Wertenburg GmbH ergänzen
- Maklermandat von der Wertenburg GmbH freigeben lassen und die Gültigkeit der Bildschirm-Unterschrift prüfen
- Die Fotos sind Symbolbilder (KI-generiert): kennzeichnen oder durch eigene Fotos ersetzen
- Vorgaben von Google Ads und Meta für Finanz- und Vermittlungsleistungen prüfen
- Rückruf-Prozess festlegen: Anfragen möglichst innerhalb weniger Minuten beantworten
