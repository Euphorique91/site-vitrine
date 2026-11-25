# Site vitrine Gourmet Distri

Site public de https://gourmetdistri.fr, déployé automatiquement par OVH à chaque poussée sur la branche `main`.

**Dépôt public : ne jamais y mettre de mot de passe, de clé, de jeton ni de donnée client.**

## Contenu actuel
- `index.html` : page d'attente aux couleurs de la charte 2026, en attendant le site vitrine.
- `.htaccess` : redirections des QR codes dynamiques (`/qr/camion`, `/qr/carte`, `/qr/colis`, `/qr/s1` à `/qr/s3`, `/qr`).
- `assets/` : logo, favicon et motif de dessins.

## QR codes dynamiques
Chaque QR code imprimé contient une adresse courte (par exemple `https://gourmetdistri.fr/qr/camion`).
Pour changer sa page d'arrivée, modifier la ligne correspondante dans `.htaccess` et pousser sur `main`.
Le QR code imprimé ne change jamais.
