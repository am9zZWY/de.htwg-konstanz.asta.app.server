# HTWG App - Backend

The [HTWG App](https://github.com/htwg-app/htwg-app-front) connects to this backend which is deployed on `Heroku`.

## 1. Installation

Install all packages specified in `composer.json`:

```shell
composer install
```

## 2. Running

You can start the server via the console.

E.g.:

```shell
php -S 127.0.0.1:8000
```

## 3. Development

### 3.1 REST API

Following requests are possible.

#### 3.1.1 GET

```text
GET /?mensa
GET /?termine
GET /?veranstaltungen
GET /?endlicht&reqtype=preise
GET /?endlicht&reqtype=zeiten
```

#### 3.1.2 POST

```text
POST BODY {
    username: "foo",
    password: "bar"
    reqtype: "..."
}
```

Following `reqtype` values are possible

- `drucker`
- `noten`
- `stundenplan`

#### Important Note

`username` and `password` must be encrypted with a public key.

The private key should be stored in an `.env` file in the root directory in the following format:

```dotenv
PRIV_KEY="-----BEGIN RSA PRIVATE KEY-----
foobar
-----END RSA PRIVATE KEY-----"
```

### 3.2 Linting with PHPStan

To lint all files in the project run the following command in the console:

```shell
composer run lint
```

This project is linted with `PHPStan`. The config file `phpstan.neon` is passed as parameter to the linting command.