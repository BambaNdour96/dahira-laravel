<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" id="formulaires-intelligents">
    (() => {
        const tous = (selecteur, racine = document) => Array.from(racine.querySelectorAll(selecteur));
        const bloquer = (bouton, bloque) => {
            bouton.disabled = bloque;
            bouton.classList.toggle('opacity-50', bloque);
            bouton.classList.toggle('cursor-not-allowed', bloque);
        };
        const conteneur = (champ) => champ.closest('div') || champ.parentElement;

        // 1. Afficher le mot de passe pendant la saisie.
        tous('input[type="password"]').forEach((champ, index) => {
            const etiquette = document.createElement('label');
            etiquette.className = 'mt-2 flex items-center gap-2 text-sm text-stone-600';
            const case_ = document.createElement('input');
            case_.type = 'checkbox';
            case_.id = 'afficher-mdp-' + index;
            case_.className = 'accent-emerald-800';
            case_.addEventListener('change', () => { champ.type = case_.checked ? 'text' : 'password'; });
            etiquette.append(case_, ' Afficher le mot de passe');
            conteneur(champ).appendChild(etiquette);
        });

        // 2. Nouveau mot de passe : jauge de solidité et règles cochées en direct.
        tous('form').forEach((formulaire) => {
            const mdp = formulaire.querySelector('input[name="password"]');
            const confirmation = formulaire.querySelector('input[name="password_confirmation"]');
            if (!mdp || !confirmation) {
                return;
            }
            const regles = [
                ['longueur', '8 caractères au moins', (v) => v.length >= 8],
                ['lettre', 'Au moins une lettre', (v) => /\p{L}/u.test(v)],
                ['chiffre', 'Au moins un chiffre', (v) => /\d/.test(v)],
                ['identiques', 'Les deux saisies sont identiques', (v) => v !== '' && v === confirmation.value],
            ];
            const bloc = document.createElement('div');
            bloc.dataset.reglesMdp = '';
            bloc.className = 'mt-3 rounded-lg bg-stone-50 p-3 text-sm ring-1 ring-stone-200';
            bloc.innerHTML = '<div class="mb-1 h-2 rounded-full bg-stone-200"><div data-jauge class="h-2 w-0 rounded-full bg-red-600"></div></div>'
                + '<p data-niveau class="mb-2 text-xs font-medium text-stone-600">Solidité : —</p>'
                + regles.map(([cle, texte]) => '<p data-regle="' + cle + '" class="text-stone-500">○ ' + texte + '</p>').join('');
            conteneur(confirmation).appendChild(bloc);
            const boutons = tous('button[type="submit"]', formulaire);
            const maj = () => {
                const v = mdp.value;
                let reussies = 0;
                regles.forEach(([cle, texte, test]) => {
                    const ok = test(v);
                    reussies += ok ? 1 : 0;
                    const ligne = bloc.querySelector('[data-regle="' + cle + '"]');
                    ligne.textContent = (ok ? '✓ ' : '○ ') + texte;
                    ligne.className = ok ? 'font-medium text-emerald-800' : 'text-stone-500';
                });
                let force = 0;
                force += v.length >= 8 ? 1 : 0;
                force += /\p{L}/u.test(v) && /\d/.test(v) ? 1 : 0;
                force += v.length >= 12 ? 1 : 0;
                force += /[^\p{L}\d]/u.test(v) ? 1 : 0;
                const jauge = bloc.querySelector('[data-jauge]');
                jauge.style.width = (force * 25) + '%';
                jauge.className = 'h-2 rounded-full ' + (force <= 1 ? 'bg-red-600' : force === 2 ? 'bg-amber-500' : 'bg-emerald-700');
                bloc.querySelector('[data-niveau]').textContent = 'Solidité : ' + (v === '' ? '—' : ['faible', 'faible', 'moyenne', 'bonne', 'excellente'][force]);
                boutons.forEach((bouton) => bloquer(bouton, reussies < regles.length));
            };
            mdp.addEventListener('input', maj);
            confirmation.addEventListener('input', maj);
            maj();
        });

        // 3. Connexion : « Se connecter » grisé tant que le mot de passe est vide.
        tous('form').filter((f) => (f.getAttribute('action') || '').replace(/\/+$/, '').endsWith('/connexion')).forEach((formulaire) => {
            const mdp = formulaire.querySelector('input[name="password"]');
            const telephone = formulaire.querySelector('input[name="telephone"]');
            const memorise = formulaire.querySelector('input[name="numero_memorise"]');
            const bouton = formulaire.querySelector('button[type="submit"]');
            if (!mdp || !bouton) {
                return;
            }
            const maj = () => bloquer(bouton, !(mdp.value !== '' && (memorise || (telephone && telephone.value.trim() !== ''))));
            [mdp, telephone].forEach((champ) => { champ?.addEventListener('input', maj); champ?.addEventListener('change', maj); });
            maj();
            // Les mots de passe remplis automatiquement par le téléphone ne préviennent pas toujours.
            let essais = 0;
            const minuterie = setInterval(() => { maj(); if (++essais > 10) { clearInterval(minuterie); } }, 500);
        });

        // 4. Inscription : « Continuer » grisé tant que la première page est incomplète.
        const suivant = document.querySelector('[data-suivant]');
        if (suivant) {
            const champs = tous('[data-etape="1"] input, [data-etape="1"] select');
            const maj = () => {
                const complet = champs.every((champ) => champ.checkValidity());
                suivant.classList.toggle('opacity-50', !complet);
                suivant.dataset.incomplet = complet ? 'non' : 'oui';
            };
            champs.forEach((champ) => { champ.addEventListener('input', maj); champ.addEventListener('change', maj); });
            maj();
        }

        // 5. Date de naissance : jamais dans le futur.
        const aujourdhui = new Date().toISOString().slice(0, 10);
        tous('input[type="date"][name="date_naissance"]').forEach((champ) => {
            if (!champ.max || champ.max > aujourdhui) { champ.max = aujourdhui; }
            if (!champ.min) { champ.min = '1900-01-01'; }
        });

        // 6. Référence Wave : majuscules automatiques, format contrôlé, bouton grisé sinon.
        tous('input[name="reference"]').forEach((champ) => {
            champ.setAttribute('autocapitalize', 'characters');
            champ.setAttribute('autocorrect', 'off');
            champ.setAttribute('spellcheck', 'false');
            const formulaire = champ.form;
            const bouton = formulaire?.querySelector('button[type="submit"]');
            const aide = document.createElement('p');
            (formulaire || champ).after(aide);
            const maj = () => {
                const v = champ.value.toUpperCase().replace(/\s+/g, '');
                if (champ.value !== v) { champ.value = v; }
                const ok = /^T_[A-Z0-9]{12,20}$/.test(v);
                if (bouton) { bloquer(bouton, !ok); }
                aide.className = 'mt-1 text-xs ' + (ok ? 'font-medium text-emerald-800' : v === '' ? 'text-stone-500' : 'text-red-700');
                aide.textContent = ok ? '✓ Format de la référence correct'
                    : 'Format attendu : T_ suivi de 12 à 20 lettres et chiffres, par exemple T_CN535JQDN4VAPI5R.';
            };
            champ.addEventListener('input', maj);
            maj();
        });

        // 7. Téléphone sénégalais mis en forme : 77 123 45 67.
        tous('input[name="telephone"], input[name="numero_wave"]').forEach((champ) => {
            champ.setAttribute('inputmode', 'tel');
            champ.addEventListener('blur', () => {
                const chiffres = champ.value.replace(/\D/g, '');
                if (/^[37]\d{8}$/.test(chiffres)) {
                    champ.value = chiffres.replace(/(\d{2})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4');
                }
            });
        });

        // 8. Profil : champs grisés selon les choix.
        const valeurDe = (nom) => {
            const coche = document.querySelector('[name="' + nom + '"]:checked');
            const champ = document.querySelector('select[name="' + nom + '"]');
            return coche ? coche.value : champ ? champ.value : null;
        };
        const griser = (noms, inactif) => noms.forEach((nom) => tous('[name="' + nom + '"]').forEach((champ) => {
            champ.disabled = inactif;
            conteneur(champ).classList.toggle('opacity-50', inactif);
        }));
        const regleProfil = () => {
            griser(['diplome', 'domaine_etudes'], valeurDe('niveau_etudes') === 'aucun');
            tous('[name="niveau_etudes_autre"]').forEach((champ) => conteneur(champ).classList.toggle('hidden', valeurDe('niveau_etudes') !== 'autre'));
            const situation = valeurDe('situation_professionnelle');
            griser(['secteur_activite'], situation === 'retraite');
            griser(['profession'], situation === 'retraite' || situation === 'eleve_etudiant');
        };
        if (document.querySelector('[name="niveau_etudes"], [name="situation_professionnelle"]')) {
            tous('[name="niveau_etudes"], [name="situation_professionnelle"]').forEach((champ) => champ.addEventListener('change', regleProfil));
            regleProfil();
        }

        // 9. Pays de résidence : l'indicatif du téléphone suit.
        const pays = document.querySelector('[name="pays_residence"]');
        const indicatif = document.querySelector('select[name="indicatif"]');
        if (pays && indicatif) {
            const codes = {
                senegal: '221', gambie: '220', mauritanie: '222', mali: '223', guinee: '224', 'guinee-bissau': '245',
                "cote d'ivoire": '225', 'cap-vert': '238', cameroun: '237', gabon: '241', maroc: '212', france: '33',
                italie: '39', espagne: '34', portugal: '351', belgique: '32', suisse: '41', allemagne: '49',
                'royaume-uni': '44', 'etats-unis': '1', canada: '1',
            };
            pays.addEventListener('change', () => {
                const cle = pays.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
                if (codes[cle]) { indicatif.value = codes[cle]; }
            });
        }

        // 10. Email : suggestion en cas de faute de frappe sur le domaine.
        const domaines = ['gmail.com', 'yahoo.fr', 'yahoo.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.fr', 'icloud.com', 'orange.sn'];
        const distance = (a, b) => {
            const d = Array.from({ length: a.length + 1 }, (_, i) => [i, ...Array(b.length).fill(0)]);
            for (let j = 1; j <= b.length; j++) { d[0][j] = j; }
            for (let i = 1; i <= a.length; i++) {
                for (let j = 1; j <= b.length; j++) {
                    d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
                }
            }
            return d[a.length][b.length];
        };
        tous('input[type="email"]').forEach((champ) => {
            const aide = document.createElement('p');
            aide.className = 'mt-1 hidden text-sm text-amber-800';
            conteneur(champ).appendChild(aide);
            champ.addEventListener('blur', () => {
                aide.classList.add('hidden');
                const v = champ.value.trim().toLowerCase();
                const arobase = v.lastIndexOf('@');
                if (arobase < 1) { return; }
                const domaine = v.slice(arobase + 1);
                if (domaines.includes(domaine)) { return; }
                const [proche, ecart] = domaines.map((d) => [d, distance(domaine, d)]).sort((x, y) => x[1] - y[1])[0];
                if (ecart > 0 && ecart <= 2) {
                    const proposition = v.slice(0, arobase + 1) + proche;
                    const bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'font-semibold underline';
                    bouton.textContent = proposition;
                    bouton.addEventListener('click', () => { champ.value = proposition; aide.classList.add('hidden'); });
                    aide.replaceChildren('Vouliez-vous dire ', bouton, ' ?');
                    aide.classList.remove('hidden');
                }
            });
        });

        // 11. Aperçu immédiat de la photo, en rond comme sur la carte.
        tous('input[type="file"][name="photo"], input[type="file"]#photo-appareil').forEach((champ) => {
            champ.addEventListener('change', () => {
                const fichier = champ.files && champ.files[0];
                if (!fichier) { return; }
                let apercu = document.getElementById('apercu-photo');
                if (!apercu) {
                    apercu = document.createElement('img');
                    apercu.id = 'apercu-photo';
                    apercu.alt = 'Aperçu de la photo';
                    apercu.className = 'mt-3 h-24 w-24 rounded-full object-cover ring-2 ring-or-400';
                    (document.getElementById('photo-choisie') || champ).insertAdjacentElement('afterend', apercu);
                }
                const lecteur = new FileReader();
                lecteur.onload = () => { apercu.src = String(lecteur.result); };
                lecteur.readAsDataURL(fichier);
            });
        });

        // 12. Aucun double envoi : le bouton affiche « Envoi en cours… ».
        tous('form').forEach((formulaire) => {
            formulaire.addEventListener('submit', (evenement) => {
                if (formulaire.dataset.envoye === '1') { evenement.preventDefault(); return; }
                formulaire.dataset.envoye = '1';
                tous('button[type="submit"]', formulaire).forEach((bouton) => {
                    bouton.dataset.texte = bouton.textContent;
                    bouton.textContent = 'Envoi en cours…';
                    bouton.classList.add('opacity-50');
                });
            });
        });
        window.addEventListener('pageshow', () => tous('form[data-envoye="1"]').forEach((formulaire) => {
            delete formulaire.dataset.envoye;
            tous('button[type="submit"]', formulaire).forEach((bouton) => {
                if (bouton.dataset.texte) { bouton.textContent = bouton.dataset.texte; }
                bouton.classList.remove('opacity-50');
            });
        }));

        // 13. Défilement jusqu'à la première erreur.
        const erreur = tous('p.text-red-700').find((element) => element.offsetParent !== null);
        if (erreur) { erreur.scrollIntoView({ block: 'center', behavior: 'smooth' }); }

        // 14. Brouillon de l'inscription : rien n'est perdu si le réseau coupe.
        const inscription = document.getElementById('formulaire-inscription');
        if (inscription) {
            const CLE = 'ndk-brouillon-inscription';
            const exclus = ['password', 'password_confirmation', '_token', 'consentement', 'indicatif'];
            const champs = tous('input, select, textarea', inscription)
                .filter((c) => c.name && !exclus.includes(c.name) && !['file', 'hidden', 'checkbox'].includes(c.type));
            const enregistrer = () => {
                const valeurs = {};
                champs.forEach((c) => { if (c.type === 'radio') { if (c.checked) { valeurs[c.name] = c.value; } } else { valeurs[c.name] = c.value; } });
                try { localStorage.setItem(CLE, JSON.stringify({ le: Date.now(), valeurs })); } catch (e) {}
            };
            let brouillon = null;
            try { brouillon = JSON.parse(localStorage.getItem(CLE) || 'null'); } catch (e) {}
            const dejaRempli = champs.some((c) => c.type !== 'radio' && c.value !== '' && c.name !== 'pays_residence');
            if (brouillon && !dejaRempli && Date.now() - brouillon.le < 24 * 3600 * 1000) {
                champs.forEach((c) => {
                    const v = brouillon.valeurs[c.name];
                    if (v === undefined) { return; }
                    if (c.type === 'radio') { c.checked = c.value === v; } else { c.value = v; }
                    c.dispatchEvent(new Event('change'));
                });
                const avis = document.createElement('p');
                avis.className = 'mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 ring-1 ring-emerald-200';
                avis.textContent = 'Nous avons retrouvé les informations que vous aviez commencé à saisir. La photo et le mot de passe sont à refaire.';
                inscription.prepend(avis);
            }
            champs.forEach((c) => { c.addEventListener('input', enregistrer); c.addEventListener('change', enregistrer); });
            inscription.addEventListener('submit', () => { try { localStorage.removeItem(CLE); } catch (e) {} });
        }
    })();
</script>