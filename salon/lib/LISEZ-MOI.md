# Installation manuelle de PHPMailer

À utiliser si Composer ne s'installe pas sur votre machine.

## Étapes

1. Ouvrez https://github.com/PHPMailer/PHPMailer/releases
2. Sur la version la plus récente, téléchargez le fichier **Source code (zip)**
3. Extrayez l'archive : vous obtenez un dossier du type `PHPMailer-6.9.x`
4. Renommez ce dossier en `PHPMailer` et placez-le ici, dans `lib/`

Vous devez obtenir exactement ce chemin :

```
salon/lib/PHPMailer/src/PHPMailer.php
salon/lib/PHPMailer/src/SMTP.php
salon/lib/PHPMailer/src/Exception.php
```

Seul le sous-dossier `src/` est nécessaire : vous pouvez supprimer le reste
(`test/`, `examples/`, `language/` si vous n'en avez pas besoin).

5. Rechargez `public/test-mail.php` : la ligne « PHPMailer installé » doit
   passer à « oui ».

## Remarque

Cette méthode fonctionne, mais Composer reste préférable : il gère les mises
à jour de sécurité. Sur Railway, c'est bien Composer qui est utilisé, via le
`Dockerfile` — cette installation manuelle ne concerne que votre poste.

Si vous déposez PHPMailer ici, pensez à retirer `lib/` du `.gitignore` si
vous souhaitez le versionner, ou laissez-le hors du dépôt : Railway n'en a
pas besoin.
