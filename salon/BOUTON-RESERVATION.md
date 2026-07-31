# Bouton de réservation — intégration dans index.html

Trois emplacements, du plus visible au plus discret. Ajoutez-les tous, ou
seulement ceux que vous voulez.

Remplacez partout `https://VOTRE-DOMAINE.up.railway.app` par l'adresse
Railway une fois le site déployé.

---

## 1. Dans la navigation

Trouvez le bloc `<ul class="navbar__links" id="navLinks">` et ajoutez la
troisième ligne :

```html
<ul class="navbar__links" id="navLinks">
  <li><a href="#diensten" class="navbar__link">Behandelingen</a></li>
  <li><a href="#contact" class="navbar__link">Contact</a></li>
  <li><a href="https://VOTRE-DOMAINE.up.railway.app" class="navbar__link navbar__link--book">Afspraak maken</a></li>
</ul>
```

---

## 2. Dans le hero, sous le bouton existant

Trouvez `<a href="#diensten" class="hero__cta" id="hero-cta">` et ajoutez
juste après la balise fermante `</a>` :

```html
<a href="https://VOTRE-DOMAINE.up.railway.app" class="hero__cta hero__cta--book">
  Direct online reserveren
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
  </svg>
</a>
```

---

## 3. Une section dédiée, entre Behandelingen et Contact

Collez ce bloc entre `</section>` de la section `#diensten` et
`<section class="section contact" id="contact">` :

```html
<!-- ===== RESERVERING ===== -->
<section class="booking-band" id="reserveren">
  <div class="container">
    <div class="booking-band__inner reveal">
      <div class="booking-band__text">
        <p class="booking-band__label">Online agenda</p>
        <h2 class="booking-band__title">Maak zelf uw afspraak</h2>
        <p class="booking-band__desc">
          Kies uw behandeling, bekijk de vrije momenten en boek in enkele
          klikken. U ontvangt meteen een bevestiging per e-mail.
        </p>
      </div>
      <a href="https://VOTRE-DOMAINE.up.railway.app" class="booking-band__cta">
        Afspraak maken
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </a>
    </div>
  </div>
</section>
```

---

## 4. CSS à ajouter à la fin de style.css

```css
/* =====================================================================
   Réservation en ligne
   ===================================================================== */

/* --- Lien de navigation mis en avant --------------------------------- */
.navbar__link--book {
    border: 1px solid currentColor;
    border-radius: 100px;
    padding: 0.45em 1.15em;
    transition: background-color .25s ease, color .25s ease;
}

.navbar__link--book:hover {
    background: currentColor;
}

.navbar__link--book:hover {
    color: #fff;
}

/* --- Second bouton du hero ------------------------------------------- */
.hero__cta--book {
    margin-top: 0.9rem;
}

/* --- Bandeau de réservation ------------------------------------------ */
.booking-band {
    padding: 4.5rem 0;
    background: linear-gradient(135deg, #F6F1EB 0%, #EFE7DE 100%);
}

.booking-band__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2.5rem;
    flex-wrap: wrap;
}

.booking-band__text {
    flex: 1 1 320px;
}

.booking-band__label {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.72rem;
    font-weight: 500;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #A08B72;
    margin: 0 0 0.7rem;
}

.booking-band__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(1.9rem, 4vw, 2.6rem);
    font-weight: 400;
    line-height: 1.2;
    color: #2E2620;
    margin: 0 0 0.8rem;
}

.booking-band__desc {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.95rem;
    font-weight: 300;
    line-height: 1.7;
    color: #5C5148;
    max-width: 46ch;
    margin: 0;
}

.booking-band__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    flex-shrink: 0;
    padding: 1.05rem 2.2rem;
    background: #2E2620;
    color: #F6F1EB;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.82rem;
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    text-decoration: none;
    border-radius: 100px;
    transition: background-color .25s ease, transform .25s ease;
}

.booking-band__cta:hover {
    background: #A08B72;
    transform: translateY(-2px);
}

.booking-band__cta svg {
    transition: transform .25s ease;
}

.booking-band__cta:hover svg {
    transform: translateX(3px);
}

@media (max-width: 700px) {
    .booking-band {
        padding: 3.2rem 0;
    }

    .booking-band__inner {
        flex-direction: column;
        align-items: flex-start;
    }

    .booking-band__cta {
        width: 100%;
        justify-content: center;
    }
}
```

Les couleurs `#2E2620`, `#A08B72` et `#F6F1EB` sont des tons neutres choisis
pour s'accorder à une charte de salon de beauté. Si votre `style.css` définit
déjà des variables de couleur, remplacez-les par les vôtres pour une cohérence
parfaite.

---

## 5. À supprimer au passage

Dans la section `#diensten`, juste après la carte Relaxatie, il y a ce bouton
qui n'a rien à faire là :

```html
<button id="" class="btn btn-primary btn-lg">Prédire les clusters</button>
```

Il vient visiblement d'un autre projet. Supprimez cette ligne.

---

## 6. Après le déploiement

Une fois l'adresse Railway connue, remplacez les trois occurrences de
`https://VOTRE-DOMAINE.up.railway.app`. Dans VS Code : Ctrl+H, chercher
`VOTRE-DOMAINE.up.railway.app`, remplacer par votre domaine réel.

Si vous voulez que le lien ouvre un nouvel onglet, ajoutez
`target="_blank" rel="noopener"` sur chaque balise `<a>`.
