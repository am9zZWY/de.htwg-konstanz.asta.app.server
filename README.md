# HTWG App - Backend

The [HTWG App](https://github.com/htwg-app/htwg-app-front) connects to this backend which is deployed on `Heroku`.

## 1. Installation

First, make sure `php` and `composer` are installed.

Install all packages specified in `composer.json`:

```shell
composer install
```

## 2. Development

### 2.1 Start Server

To start developing the backend you must start a server. It can be started with the following command:

```shell
composer dev
```

This will start a server at `localhost:8000`.

### 2.2 REST API

Following requests are possible.

#### 2.2.1 GET

```text
GET /?mensa
GET /?termine
GET /?endlicht&reqtype=preise
GET /?endlicht&reqtype=zeiten
```

#### 2.2.2 POST

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
- `immatrikulations_bescheinigung`

#### Important Note

`username` and `password` must be encrypted with a public key.

The private key should be stored in an `.env` file in the root directory in the following format:

```dotenv
PRIV_KEY="-----BEGIN RSA PRIVATE KEY-----
foobar
-----END RSA PRIVATE KEY-----"
```

### 2.3 Linting with PHPStan

To lint all files in the project run the following command in the console:

```shell
composer run lint
```

This project is linted with `PHPStan`. The config file `phpstan.neon` is passed as parameter to the linting command.