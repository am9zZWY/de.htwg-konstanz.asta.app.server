# Willkommen bei der HTWG App Back contributing guide <!-- omit in toc -->

Danke, dass du deine Zeit investierst, um zu diesem Projekt beizutragen! Du trägst zu einem Projekt bei, das Studierenden im Alltag an der HTWG Konstanz hilft.

In diesem Leitfaden erhältst du einen Überblick über den Beitrags-Workflow vom Eröffnen eines Issues über das Erstellen eines PRs bis hin zum Überprüfen und Zusammenführen des PRs.

## Leitfaden für neue Mitwirkende

Um einen Überblick über das Projekt zu bekommen, lies die [`README.md`](README.md).

## Erste Schritte

Der Code ist in mehrere Dateien unterteilt. 
[`index.php`](index.php) bildet den Einstiegspunkt und wird aufgerufen, sobald die Server-Adresse eingegeben wurde.
Der Code des Projektes ist derzeit auf Heroku deployed und wird unter der Addresse `htwg-app-back.herokuapp.com` zu finden.

Sobald die [`index.php`](index.php) aufgerufen wurde, wird abhängig von der Anfrage zwischen einer Anfrage, die einen eingeloggten Benutzer voraussetzt und, einer allgemeinen Anfrage unterschieden.
Du findest alle Anfragetypen in der [`README.md`](README.md). 

Die Dateien, für die Anfrage findest du im [src](src)-Ordner.

### Themen

#### Erstelle ein neues Issue

Wenn du ein Problem im Code entdeckst, suche, ob bereits ein Problem existiert.

#### Löse ein Issue

Durchsuche unsere bestehenden Probleme, um ein Problem zu finden, das dich interessiert.
Du kannst die Suche eingrenzen, indem du "Labels" als Filter verwendest.
Generell gilt: Wir weisen niemandem ein Thema zu. Wenn du ein Problem findest, an dem du arbeiten möchtest, kannst du gerne einen PR mit einer Lösung eröffnen.

### Änderungen vornehmen

#### Änderungen lokal vornehmen

1. Forke das Repository.

2. Installiere die in der [`README.md`](README.md) angegebene Tools.

3. Erstelle einen neuen Branch und beginne mit deinen Änderungen!

4. Starte den Entwicklungsserver. Eine Anleitung findest du in der [`README.md`](README.md).

### Committe dein Update

Übertrage die Änderungen, wenn du mit ihnen zufrieden bist.
Im [Atom's contributing guide](https://github.com/atom/atom/blob/master/CONTRIBUTING.md#git-commit-messages) erfährst du, wie du Emoji für Commit-Nachrichten verwenden kannst.

Bevor du deine Änderungen veröffentlichst, stelle bitte sicher, dass diese mittels `PHP-Stan` gelintet wurden. Weitere Informationen, findest du in der [`README.md`](README.md).

Sobald deine Änderungen fertig sind, vergiss nicht ein Review zu schreiben, um den Review-Prozess zu beschleunigen:zap:.

### Pull Request

Wenn du mit deinen Änderungen fertig bist, erstellst du einen Pull Request, auch PR genannt.
Trage [jpkmiller](https://github.com/jpkmiller) als Reviewer ein.

### Fertig

Sobald dein Pull Request eingegangen ist, heißt es abwarten.
Es kann manchmal sein, dass der Request nicht akzeptiert wird und du gebeten wird Änderungen vorzunehmen. Das kann vorkommen und ist nichts Persönliches, sondern dient lediglich der Sicherstellung der Code-Qualität.
