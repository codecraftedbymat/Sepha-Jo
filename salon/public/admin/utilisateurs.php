<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$moi = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    /* --- Changer son propre mot de passe ------------------------------ */
    if ($action === 'mon_mdp') {
        $actuel   = $_POST['actuel']   ?? '';
        $nouveau  = $_POST['nouveau']  ?? '';
        $confirme = $_POST['confirme'] ?? '';

        $stmt = $conn->prepare('SELECT Password FROM Users WHERE Id = :id');
        $stmt->execute([':id' => $moi]);
        $ligne = $stmt->fetch();

        if (!$ligne || !password_verify($actuel, $ligne['Password'])) {
            header('Location: utilisateurs.php?err=' . urlencode('Mot de passe actuel incorrect.'));
            exit;
        }
        if (strlen($nouveau) < 10) {
            header('Location: utilisateurs.php?err=' . urlencode('Le nouveau mot de passe doit faire au moins 10 caractères.'));
            exit;
        }
        if ($nouveau !== $confirme) {
            header('Location: utilisateurs.php?err=' . urlencode('Les deux nouveaux mots de passe ne correspondent pas.'));
            exit;
        }

        $conn->prepare('UPDATE Users SET Password = :p WHERE Id = :id')
             ->execute([':p' => password_hash($nouveau, PASSWORD_DEFAULT), ':id' => $moi]);

        header('Location: utilisateurs.php?ok=' . urlencode('Votre mot de passe a été modifié.'));
        exit;
    }

    /* --- Créer un compte ---------------------------------------------- */
    if ($action === 'creer') {
        $username = trim($_POST['username'] ?? '');
        $mdp      = $_POST['mdp'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            header('Location: utilisateurs.php?err=' . urlencode('Identifiant invalide : 3 à 50 caractères, lettres, chiffres, point, tiret ou souligné.'));
            exit;
        }
        if (strlen($mdp) < 10) {
            header('Location: utilisateurs.php?err=' . urlencode('Le mot de passe doit faire au moins 10 caractères.'));
            exit;
        }

        $existe = $conn->prepare('SELECT COUNT(*) FROM Users WHERE Username = :u');
        $existe->execute([':u' => $username]);
        if ($existe->fetchColumn() > 0) {
            header('Location: utilisateurs.php?err=' . urlencode('Cet identifiant est déjà utilisé.'));
            exit;
        }

        $conn->prepare('INSERT INTO Users (Username, Password) VALUES (:u, :p)')
             ->execute([':u' => $username, ':p' => password_hash($mdp, PASSWORD_DEFAULT)]);

        header('Location: utilisateurs.php?ok=' . urlencode('Compte « ' . $username . ' » créé.'));
        exit;
    }

    /* --- Réinitialiser le mot de passe d'un autre compte --------------- */
    if ($action === 'reinit') {
        $id  = (int) ($_POST['id'] ?? 0);
        $mdp = $_POST['mdp'] ?? '';

        if ($id === $moi) {
            header('Location: utilisateurs.php?err=' . urlencode('Pour votre propre compte, utilisez le formulaire du haut.'));
            exit;
        }
        if (strlen($mdp) < 10) {
            header('Location: utilisateurs.php?err=' . urlencode('Le mot de passe doit faire au moins 10 caractères.'));
            exit;
        }

        $conn->prepare('UPDATE Users SET Password = :p WHERE Id = :id')
             ->execute([':p' => password_hash($mdp, PASSWORD_DEFAULT), ':id' => $id]);

        header('Location: utilisateurs.php?ok=' . urlencode('Mot de passe réinitialisé.'));
        exit;
    }

    /* --- Supprimer un compte ------------------------------------------ */
    if ($action === 'supprimer') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $moi) {
            header('Location: utilisateurs.php?err=' . urlencode('Vous ne pouvez pas supprimer votre propre compte.'));
            exit;
        }
        if ((int) $conn->query('SELECT COUNT(*) FROM Users')->fetchColumn() <= 1) {
            header('Location: utilisateurs.php?err=' . urlencode('Impossible de supprimer le dernier compte.'));
            exit;
        }

        $conn->prepare('DELETE FROM Users WHERE Id = :id')->execute([':id' => $id]);
        header('Location: utilisateurs.php?ok=' . urlencode('Compte supprimé.'));
        exit;
    }
}

$utilisateurs = $conn->query('SELECT Id, Username, CreatedAt FROM Users ORDER BY Username ASC')->fetchAll();

admin_header('Utilisateurs', 'utilisateurs');
flash();
?>

<section class="card">
    <div class="card-head"><h2>Mon mot de passe</h2></div>
    <form method="post" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mon_mdp">
        <div class="fld">
            <label for="actuel">Mot de passe actuel</label>
            <input id="actuel" name="actuel" type="password" required>
        </div>
        <div class="fld">
            <label for="nouveau">Nouveau</label>
            <input id="nouveau" name="nouveau" type="password" required>
        </div>
        <div class="fld">
            <label for="confirme">Confirmer</label>
            <input id="confirme" name="confirme" type="password" required>
        </div>
        <button class="btn btn-primary">Modifier</button>
    </form>
</section>

<section class="card">
    <div class="card-head"><h2>Créer un compte</h2></div>
    <form method="post" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer">
        <div class="fld grow">
            <label for="username">Identifiant</label>
            <input id="username" name="username" type="text" placeholder="sophie" required>
        </div>
        <div class="fld">
            <label for="mdp">Mot de passe</label>
            <input id="mdp" name="mdp" type="text" placeholder="10 caractères minimum" required>
        </div>
        <button class="btn btn-primary">Créer</button>
    </form>
</section>

<section class="card card-flush">
    <div class="card-head"><h2>Comptes existants</h2></div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Identifiant</th>
                    <th>Créé le</th>
                    <th>Nouveau mot de passe</th>
                    <th class="ta-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($utilisateurs as $u) : ?>
                <tr>
                    <td>
                        <span class="cell-user">
                            <span class="avatar"><?= e(initiales($u['Username'])) ?></span>
                            <?= e($u['Username']) ?>
                            <?php if ((int) $u['Id'] === $moi) : ?>
                                <span class="badge badge-on" style="margin-left:8px;">vous</span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td class="mono"><?= e(date('d/m/Y', strtotime($u['CreatedAt']))) ?></td>
                    <?php if ((int) $u['Id'] === $moi) : ?>
                        <td colspan="2" class="dim" style="text-align:right;">
                            Utilisez le formulaire « Mon mot de passe » ci-dessus.
                        </td>
                    <?php else : ?>
                        <td>
                            <form method="post" class="inline-form" style="justify-content:flex-start;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $u['Id'] ?>">
                                <input class="cell-input wide" type="text" name="mdp" placeholder="10 caractères min.">
                                <button class="btn btn-mini btn-primary" name="action" value="reinit">Réinitialiser</button>
                            </form>
                        </td>
                        <td class="ta-right">
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $u['Id'] ?>">
                                <button class="btn btn-mini btn-danger" name="action" value="supprimer"
                                        onclick="return confirm('Supprimer le compte « <?= e($u['Username']) ?> » ?');">Supprimer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="table-foot">
        Tous les comptes ont les mêmes droits : accès complet aux réservations, aux
        prestations et aux utilisateurs.
    </p>
</section>

<?php admin_footer(); ?>
