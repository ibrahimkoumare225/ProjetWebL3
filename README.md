# Gestion de Recettes de Cuisine Bilingue

## Description
Ce projet est une application web permettant la gestion de recettes de cuisine en français et en anglais. Il inclut différentes fonctionnalités de gestion des recettes, des utilisateurs et de leurs rôles.

## Fonctionnalités
- **Gestion des rôles utilisateurs**
  - Utilisateurs : Voir les recettes ainsi que les details de chaque recettes et les leurs commentaires, ils peuvent aussi liker les recettes 
  - Cuisiniers : Commentent des recettes.
  - Chefs : Proposent de nouvelles recettes et modifient ou supprime leurs propres recettes.
  - Traducteurs : Traduisent les recettes d'une langue à l'autre.
  - Administrateur : Ajoute, modifie, supprime et traduit les recettes, gère les utilisateurs et leurs rôles.

- **Authentification et gestion des utilisateurs**
  - Inscription et connexion (Login / Mot de passe)
  - Tout utilisateur inscrit commence en tant que Utilisateur 
  - Possibilité de demander un rôle de Chef , de Cusinier ou de Traducteur
  - L'administrateur peut approuver ces demandes et attribuer les rôles

- **Gestion des recettes**
  - Ajout de nouvelles recettes par les Chefs
  - Modification des recettes propres aux Chefs (auteur de la reccette )
  - Possibilité d'ajouter des photos et commentaires
  - Recherche une recette par son nom
  - Visualisation détaillée d'une recette avec :
    - Titres
    - Ingrédients
    - Étapes de préparation
  - possibiliter de voir les Commentaires d'une recette
    - De modifier le commentaire si on est l'auteur ou l'admin
    - De supprimer le commentaire si on est l'auteur ou l'admin
  
- **Interface de traduction**
  - Interface à deux colonnes (Français / Anglais)
  - Traduction possible uniquement des champs vides si l'autre langue est remplie
  - Un traducteur peut être aussi Chef et modifier complètement ses propres recettes

- **Expérience utilisateur**
  - Interface moderne et ergonomique en CSS
  - Commutation facile entre les langues
  - Gestion des images via URL 
  - Affichage des champs restants à remplir
  - Ajout d'un "cœur" pour aimer une recette

## Technologies Utilisées
- **Front-end** : HTML, CSS, Materialize JavaScript notament Ajax
- **Back-end** : PHP
- **Base de données** : JSON (initialement), possibilité d'évolution vers une BD relationnelle

## Installation et Exécution
1. **Cloner le projet**
   ```bash
   git clone https://github.com/votre-repo.git](https://github.com/ibrahimkoumare225/ProjetWebL3
   
   ```
2. **Naviguer pour ce placer dans le bon dossier**
   ```bash
   cd ProjetWebL3  
   ```
3. **Lancer le serveur**
      ```bash
        a.   **Lancer le serveur pour le front**
      ```
             php -S localhost:3000 -t front/

      ```bash
        b.  **Lancer le serveur pour le backend **
      ```
                 php -S localhost:8000 -t back/

5. **Accéder à l'application**
   - Ouvrir un navigateur et aller sur `http://localhost:3000/instance.html`

## Structure du Projet
```
│── back                          # Dossier contenant le code source principal
│   │── data                      # Dossier contenant les fichiers json
│   │     │── users               # fichier stockant les utilisateurs
│   │     │── roles               # fichier stockant les demandes de roles
│   │     │── recipes             # fichier stockant les recettes
│   │     │── comments            # fichier stockant les commentaires
│   │── index.php                 # Fichier qui configure l’environnement et définit les routes de l’API en les associant aux contrôleurs
│   │── RecipeController.php      # fichier contenant les fonctions pour géré les recettes
│   │── AuthController.php        # fichier contenant les fonctions pour l'inscription et l'authentification
│   │── CommentController.php     # fichier contenant les fonctions pour géré les commentaires 
│   │── RoleController.php        # fichier contenant les fonctions pour géré les role
│   │── Router.php                # fichier contenant les fonctions pour gérer le routage des requêtes HTTP
│   │── error.log                 # fichier stockant les logs(erreurs)
│   
│── front                         # Dossier contenant les fichiers gérant les vues
│     │── script                  # Dossier contenant les fichiers js pour les appels ajax
│           │── auth.js           # Ce fichier permet de connecter, inscrire ou déconnecter un utilisateur depuis l’interface du site.
│           │── comment.js        # Ce fichier permet de commenter, modifier, supprimer, voir (un commentaire) des recettes depuis l'interface du site.
│           │── recette.js        # Ce fichier permet d'ajouter, modifier, supprimer, voir une reccette depuis l’interface du site.
│           │── role.js           # Ce fichier permet de voir, de demander, accepter ou refuser un rôle depuis l’interface du site.
│           │── traduction.js     # Ce fichier permet de traduire, complété ou modifier(les traductions d'une recette) une recettte depuis l’interface du site.
│── instance.html                   # Première page de l'applicatio
│── inscription.html                # Page d'inscription
│── connexion.html                  # Page d'authentification
│── nav.php                         # Page contenant la navbar 
│── README.md                       # Documentation du projet
```

## Contributions
- Forker le projet
- Créer une branche (`feature-nouvelle-fonctionnalite`)
- Soumettre une Pull Request


## Licence
Ce projet est sous licence MIT. Voir `LICENSE` pour plus de détails.

---
**Auteur(s) :** (IBRAHIM KOUMARE ET IRIS BESSALA LEONNE)

