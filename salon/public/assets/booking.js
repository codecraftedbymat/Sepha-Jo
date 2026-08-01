/* =====================================================================
   Réservation en ligne — logique côté client
   ===================================================================== */

(function () {
    'use strict';

    const state = { prestation: null, date: null, heure: null };

    const $  = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));

    const step2   = $('#step2');
    const step3   = $('#step3');
    const slotsEl = $('#slots');
    const alertEl = $('#alert');

    let rafraichir = null;

    /* ---------------------------------------------------------------
       Étape 1 — choix de la prestation
       --------------------------------------------------------------- */
    $$('.svc').forEach((btn) => {
        btn.addEventListener('click', () => {
            $$('.svc').forEach((b) => b.classList.remove('is-selected'));
            btn.classList.add('is-selected');

            state.prestation = {
                id:    Number(btn.dataset.id),
                nom:   btn.dataset.nom,
                duree: Number(btn.dataset.duree),
                prix:  btn.dataset.prix,
            };
            state.date = null;
            state.heure = null;

            step2.classList.remove('is-locked');
            step3.classList.add('is-locked');
            $('#step2sub').textContent =
                `${state.prestation.nom} — ${state.prestation.duree} min. Choisissez un jour, puis un horaire.`;

            $$('.day').forEach((d) => d.classList.remove('is-selected'));
            slotsEl.innerHTML = '<p class="hint">Sélectionnez un jour ci-dessus.</p>';

            step2.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    /* ---------------------------------------------------------------
       Étape 2 — choix du jour puis du créneau
       --------------------------------------------------------------- */
    $$('.day').forEach((btn) => {
        if (btn.disabled) return;
        btn.addEventListener('click', () => {
            $$('.day').forEach((d) => d.classList.remove('is-selected'));
            btn.classList.add('is-selected');

            state.date = btn.dataset.date;
            state.heure = null;
            step3.classList.add('is-locked');

            chargerCreneaux();
        });
    });

    async function chargerCreneaux() {
        if (!state.prestation || !state.date) return;

        clearInterval(rafraichir);
        slotsEl.innerHTML = '<p class="hint">Chargement des disponibilités…</p>';

        await dessinerCreneaux();

        // Les disponibilités évoluent si un autre client réserve pendant
        // que celui-ci hésite : on rafraîchit régulièrement.
        rafraichir = setInterval(dessinerCreneaux, 20000);
    }

    async function dessinerCreneaux() {
        try {
            const url = `api/creneaux.php?prestation=${state.prestation.id}&date=${state.date}`;
            const res = await fetch(url);
            const data = await res.json();

            if (!data.ok) {
                slotsEl.innerHTML = `<p class="hint">${data.message || 'Impossible de charger les créneaux.'}</p>`;
                return;
            }
            if (data.ferme || data.creneaux.length === 0) {
                slotsEl.innerHTML = '<p class="hint">Le salon est fermé ce jour-là.</p>';
                return;
            }

            const libres = data.creneaux.filter((c) => c.libre).length;
            if (libres === 0) {
                slotsEl.innerHTML =
                    '<p class="hint">Plus aucun créneau disponible ce jour-là pour cette prestation. Essayez un autre jour.</p>';
                return;
            }

            slotsEl.innerHTML = '';

            const legende = document.createElement('p');
            legende.className = 'slots-legend';
            legende.textContent = `${libres} créneau${libres > 1 ? 'x' : ''} disponible${libres > 1 ? 's' : ''}`;
            slotsEl.appendChild(legende);

            const grille = document.createElement('div');
            grille.className = 'slots-grid';

            data.creneaux.forEach((c) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'slot' + (c.libre ? '' : ' is-taken');
                b.textContent = c.label;
                b.disabled = !c.libre;
                if (state.heure === c.debut && c.libre) b.classList.add('is-selected');

                if (c.libre) {
                    b.addEventListener('click', () => {
                        state.heure = c.debut;
                        $$('.slot').forEach((s) => s.classList.remove('is-selected'));
                        b.classList.add('is-selected');
                        step3.classList.remove('is-locked');
                        majRecap(data.longue, c.label);
                        step3.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }
                grille.appendChild(b);
            });

            slotsEl.appendChild(grille);

        } catch (err) {
            slotsEl.innerHTML = '<p class="hint">Connexion au serveur impossible. Réessayez dans un instant.</p>';
        }
    }

    function majRecap(dateLongue, heureLabel) {
        const p = state.prestation;
        const prix = p.prix ? ` · ${p.prix} €` : '';
        $('#recap').innerHTML =
            `<strong>${p.nom}</strong> · ${dateLongue} · ${heureLabel} <span class="dim">(${p.duree} min${prix})</span>`;
    }

    /* ---------------------------------------------------------------
       Étape 3 — envoi de la réservation
       --------------------------------------------------------------- */
    $('#form').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        alertEl.classList.remove('is-visible');

        const nom   = $('#nom').value.trim();
        const email = $('#email').value.trim();
        const tel   = $('#tel').value.trim();

        if (!nom || !email || !tel) {
            return afficherErreur('Merci de renseigner votre nom, votre e-mail et votre téléphone.');
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return afficherErreur('Cette adresse e-mail ne semble pas valide.');
        }

        const btn = $('#submitBtn');
        btn.disabled = true;
        btn.textContent = 'Enregistrement…';

        try {
            const res = await fetch('api/reserver.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    prestation: state.prestation.id,
                    date: state.date,
                    heure: state.heure,
                    nom, email, tel,
                }),
            });
            const data = await res.json();

            if (!data.ok) {
                btn.disabled = false;
                btn.textContent = 'Confirmer ma réservation';
                afficherErreur(data.message || 'La réservation a échoué.');

                // Créneau pris entre-temps : on recharge et on renvoie à l'étape 2
                if (res.status === 409) {
                    state.heure = null;
                    step3.classList.add('is-locked');
                    dessinerCreneaux();
                    step2.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                return;
            }

            afficherConfirmation(data.reservation);

        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Confirmer ma réservation';
            afficherErreur('Connexion au serveur impossible. Réessayez dans un instant.');
        }
    });

    function afficherErreur(message) {
        alertEl.textContent = message;
        alertEl.classList.add('is-visible');
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /* ---------------------------------------------------------------
       Confirmation
       --------------------------------------------------------------- */
    function afficherConfirmation(r) {
        clearInterval(rafraichir);

        ['step1', 'step2', 'step3'].forEach((id) => { $('#' + id).hidden = true; });

        const conf = $('#confirm');
        conf.hidden = false;

        $('#confirmSub').textContent =
            `Un e-mail de confirmation vient d'être envoyé à ${r.client_email}.`;

        $('#ticket').innerHTML = [
            ligne('Prestation', r.prestation),
            ligne('Date',       r.date_longue),
            ligne('Horaire',    `${r.heure_debut} – ${r.heure_fin}`),
            r.prix ? ligne('Tarif', `${r.prix} €`) : '',
            ligne('Nom',        r.client_nom),
            ligne('Téléphone',  r.client_tel),
            ligne('E-mail',     r.client_email),
        ].join('');

        $('#icsLink').href = `api/agenda.php?id=${r.id}`;

        conf.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function ligne(label, valeur) {
        return `<div class="t-row"><span>${label}</span><span>${valeur}</span></div>`;
    }

})();
