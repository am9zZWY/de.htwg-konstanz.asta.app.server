# HTWG App - Backend

The [HTWG App](https://github.com/htwg-app/htwg-app-front) connects to this backend which is deployed on `Heroku`.

## 1. Running

You can start the server via the console.

E.g.:

```shell
php -S 127.0.0.1:8000
```

## 2. REST API

Following requests are possible

### 2.1 GET

```text
GET /?mensa
GET /?termine
GET /?veranstaltungen
GET /?endlicht&reqtype=preise
GET /?endlicht&reqtype=zeiten
```

### 2.2 POST

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

