# Site vitrine Gourmet Distri

Site public de https://gourmetdistri.fr, déployé automatiquement par OVH à chaque poussée sur la branche `main` (en ligne en une vingtaine de secondes).

**Dépôt public : ne jamais y mettre de mot de passe, de clé, de jeton ni de donnée client.**

## Contenu
| Fichier | Rôle |
| --- | --- |
| `index.html` | La page du site (une seule longue page : rayons, qui sommes-nous, livraison, FAQ, contact) |
| `contact.php` | Formulaire « Demande de rappel » : e-mail au service commercial + accusé de réception au prospect |
| `merci/` | Page affichée après l'envoi du formulaire (navigateurs sans JavaScript) |
| `mentions-legales/` | Mentions légales, données personnelles, cookies |
| `404.html` | Page « introuvable » |
| `.htaccess` | Adresse unique https://gourmetdistri.fr, compression, cache, sécurité, **QR codes dynamiques** |
| `sitemap.xml`, `robots.txt` | Pour Google et Bing |
| `llms.txt` | Résumé de l'entreprise pour les assistants IA (ChatGPT, Claude, Perplexity…) |
| `assets/` | Logo, dessins de fond, polices (hébergées ici : aucun service tiers), icônes, image de partage |

## Formulaire de contact
- Envoi authentifié par le serveur SMTP d'OVH, depuis `ne-pas-repondre@gourmetdistri.fr`, comme l'appli de commande.
- Les identifiants ne sont pas dans ce dépôt : `contact.php` les lit sur le serveur, hors du site, dans
  `/home/gourmetdin/prive/site-vitrine/config.php` s'il existe, sinon dans la configuration de l'appli de commande
  (`/home/gourmetdin/prive/appli-prod/config.php`, lue sans jamais être modifiée). Un changement du mot de passe d'envoi
  fait dans l'appli (Espace équipe › Technique) vaut donc aussi pour le site vitrine.
- Les demandes arrivent sur `contact@gourmetdistri.fr` (constante `DESTINATAIRE` en haut de `contact.php`), avec l'origine du
  prospect (QR code scanné, Google, lien depuis un autre site…). « Répondre » écrit directement au prospect.
- Copie de secours de chaque demande dans `/home/gourmetdin/prive/site-vitrine/demandes/` (un fichier par mois, effacé au bout de 3 ans).
- Anti-robots : champ invisible, délai minimal de remplissage, 5 demandes par heure au plus par visiteur.

## QR codes dynamiques
Chaque QR code imprimé contient une adresse courte (par exemple `https://gourmetdistri.fr/qr/camion`).
Pour changer sa page d'arrivée, modifier la ligne correspondante en bas de `.htaccess` et pousser sur `main`.
Le QR code imprimé ne change jamais.

## Modifier le site
La page est générée à partir de la maquette du canevas Claude (« Site retenu »). Pour un petit changement de texte,
modifier directement `index.html` sur GitHub (crayon › Commit changes) : c'est en ligne une vingtaine de secondes plus tard.
