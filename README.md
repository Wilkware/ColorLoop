# Farbeverlauf (Color Loop)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.1.20240224-orange.svg?style=flat-square)](https://github.com/Wilkware/ColorLoop)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/ColorLoop/style.yml?branch=main&label=CheckStyle&style=flat-square)](https://github.com/Wilkware/ColorLoop/actions)

Das Modul bietet die Möglichkeit, einen automatischen Farbverlauf bzw. einen Farbwechsel zu aktivieren. Sobald er aktiviert ist, läuft eine kontinuierliche Schleife durch verschiedene Farben, die sich fortlaufend wiederholt.  

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Die Idee für das Modul stammt aus der Philips HUE App, welche ein Farbschleifen-Funktionalität bereitstellt.  
Durch den Umstieg auf Zigbee2Mqtt und den Wegfall des HUE-Gateways und den damit verbunden Wegfall der HUE-App fehlte mir dieser nette Effekt.
Im Endeffekt versucht das Modul diesen Effekt nachzubilden.  

* Bilden einer Gruppe über mehrere Leuchtmittel hinweg
* Starten und stoppen der Schleife über einen hinterlegten Gruppenschalter (z.B. Z2M Group Status)
* Festlegen der Startfarbe pro Leuchte oder direktes Aufsetzen auf aktuellen Farbwert der Leuchte
* Steuerung der Funktionalität über das WebFront
  * Steuerung der Farbschritte und Übergangsgeschwindigkeit
  * Zeitweises Aktivieren bzw. Deaktivieren der Funktionalität
  * Möglichkeit die Startfarbe pro Leuchtmittel festzulegen

Gute Effekte kann man erzielen bei kleiner Schrittweite (5) und einem sehr langsamen Übergang (12sek)

### 2. Voraussetzungen

* IP-Symcon ab Version 6.4
* Getestet mit Philips Hue Color Ambiance Leuchtmitteln

### 3. Installation

* Über den Modul Store das Modul _Color Loop_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/ColorLoop` oder `git://github.com/Wilkware/ColorLoop.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das _Color Loop_-Modul (Alias: _Farbverlauf_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Schaltung ...

Name                            | Beschreibung
------------------------------- | -----------------------------------------------------------------
Schaltervariable                | Die Schaltvariable, welche als Indikator für den Schaltzustand (An/Aus) der ganzen Leuchtgruppe dient.

> Geräte ...

Name                            | Beschreibung
------------------------------- | -----------------------------------------------------------------
Leuchtgruppe (Liste)            | Alle Geräte, welche an der Farbschleife beteiligt seien sollen
-- Statusvariable                 | Statusvariable des Leuchtmittels, welche die Farbe abbildet. Muss sich über RequestAction steuern lassen und das Profil _~HexColor_ besitzen.
-- Startfarbe                   | Farbwert mit dem die Farbschleife beginnen soll. Die Farbauswahl 'Transparent' bewirkt die Verwendung des aktuell eingestellten Farbcodes des Leuchtmittels als Startfarbe.
-- Leuchtenname                 | Der Leuchtenname ist nur notwendig wenn man die Startfarbe auch über das WebFront ändern möchte (Statusvariable).

> Erweiterte Einstellungen ...

Name                            | Beschreibung
------------------------------- | -----------------------------------------------------------------
Variablen für Auswahl der Startfarbe pro Leuchte anlegen? | Legt pro hinterlegten Leuchtmittel eine Statusvariable an (siehe Leuchtenname) um die Startfarbe über das Webfront festlegen bzw. ändern zu können.

### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                          | Typ       | Beschreibung
------------------------------| --------- | ----------------
Aktiv                         | Boolean   | Schalter für Aktivierung oder Deaktivierung der Farbschleife, d.h. soll Farbschleife starten wenn Leuchtgruppe angeschaltet wird.
Schrittweite                  | Integer   | Auswahl, wie groß die Farbänderungsschritte erfolgen soll (in 5er Schritten zwischen 5 und 355).
Übergang                      | String    | Auswahl, wie schnell der einzelne Farbwechsel erfolgen soll.
\[Leuchtenname\]                | Integer   | Aktivierbar über die erweiterten Einstellungen. Startfarbe fürs WebFront.

Folgendes Profil wird angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------------
WWXCL.Increment      | Integer   | Schrittweite (5 - 355)
WWXCL.Transition     | Integer   | Übergang in Sekunden (2, 5, 8 und 12)

### 6. Visualisierung

Man kann die Statusvariablen direkt im WF verlinken.

### 7. PHP-Befehlsreferenz

Das Modul stellt keine direkten Funktionsaufrufe zur Verfügung.

### 8. Versionshistorie

v1.1.20240224

* _NEU_: Farbschleife kann automatisch mit Gerät eingeschaltet werden
* _NEU_: Farbschleife kann bei Wiedereinschalten auf letzte Farbwerte aufsetzen
* _FIX_: Interne Bibliotheken überarbeitet
* _FIX_: Internes Deployment überarbeitet

v1.0.20230728

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
