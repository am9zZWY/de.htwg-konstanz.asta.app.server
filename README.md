# HTWG App - Backend

Die [HTWG App](https://github.com/htwg-app/htwg-app-front) für die [HTWG Konstanz](https://www.htwg-konstanz.de) verbindet sich mit diesem Backend, das auf `Heroku` bereitgestellt wird.

## 1. Installation

Stelle zunächst sicher, dass `php` und `composer` installiert sind.

Installiere alle Pakete, die in `composer.json` angegeben sind:

```shell
composer install
```

## 2. Entwicklung

### 2.1 Server starten

Um mit der Entwicklung des Backends zu beginnen, musst du einen Server starten. Er kann mit dem folgenden Befehl gestartet werden:

```shell
composer dev
```

Dadurch wird ein Server unter `localhost:8000` gestartet.

### 2.2 REST API

Die folgenden Anfragen sind möglich.

#### 2.2.1 GET

```Text
GET /?mensa
GET /?termine
GET /?endlicht&reqtype=preise
GET /?endlicht&reqtype=zeiten
```

#### 2.2.2 POST

```Text
POST BODY {
    username: "foo",
    password: "bar"
    reqtype: "..."
}
```

Folgende `reqtype`-Werte sind möglich

- `drucker`
- `noten`
- `stundenplan`
- `immatrikulations_bescheinigung`

#### Wichtiger Hinweis

`username` und `password` müssen mit einem öffentlichen Schlüssel verschlüsselt werden.

Der private Schlüssel sollte in einer `.env`-Datei im Stammverzeichnis in folgendem Format gespeichert werden:

```dotenv
PRIV_KEY="-----BEGIN RSA PRIVATE KEY-----
foobar
-----END RSA PRIVATE KEY-----"
```

### 2.3 Linting mit PHPStan

[![PHP Composer](https://github.com/HTWG-App/htwg-app-back/actions/workflows/php.yml/badge.svg)](https://github.com/HTWG-App/htwg-app-back/actions/workflows/php.yml)

Um alle Dateien im Projekt zu linsen, führe den folgenden Befehl in der Konsole aus:

```shell
composer run lint
```

Dieses Projekt wird mit `PHPStan` gelintet. Die Konfigurationsdatei `phpstan.neon` wird als Parameter an den Linting-Befehl übergeben.
