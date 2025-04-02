const webServerAddress = "http://localhost:8000"; // Adresse du serveur

// Récupération du formulaire d'inscription
const register = document.getElementById("inscription");

if (register) {
  register.addEventListener("submit", async (event) => {
    event.preventDefault(); // Empêche le rechargement de la page
    const userData = await inscription(event); // Envoie les données et attend la réponse
  });
}

// Récupération du formulaire de connexion
const login = document.getElementById("connexion");

if (login) {
  login.addEventListener("submit", async (event) => {
    event.preventDefault(); // Empêche le rechargement de la page
    const userData = await connexion(event); // Envoie les données et attend la réponse
  });
}

// Fonction d'inscription
async function inscription(event) {
  event.preventDefault(); // Empêche le rechargement de la page

  const body = new URLSearchParams(new FormData(event.target));

  try {
    const response = await fetch(`${webServerAddress}/register`, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body,
    });

    const result = await response.json(); // Convertir la réponse en JSON

    if (response.ok) {
      console.log("Inscription réussie:", result);
      alert(result.message); // Affiche un message de confirmation

      // Vérifier si une redirection est fournie et valide
      if (result.redirect && typeof result.redirect === "string") {
        console.log("Redirection vers:", result.redirect);
        window.location.href = result.redirect; // Redirection vers connexion.html
      }
    } else {
      console.error("Échec de l'inscription:", result);
      alert(result.message);
    }
  } catch (error) {
    console.error("Erreur lors de l'inscription:", error);
  }
}

// Fonction de connexion
async function connexion(event) {
  const body = new URLSearchParams(new FormData(event.target)); // Récupération et encodage des données du formulaire

  console.log("Données envoyées:", body.toString());

  try {
    console.log("Envoi de la requête à:", `${webServerAddress}/login`);
    const response = await fetch(`${webServerAddress}/login`, {
      // Envoi de la requête au serveur
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body,
    });

    console.log("Statut de la réponse HTTP:", response.status);
    const text = await response.text(); // Lire la réponse brute du serveur
    console.log("Réponse brute du serveur:", text);

    if (response.ok) {
      const userData = JSON.parse(text); // Conversion de la réponse en JSON
      console.log("Connexion réussie:", userData);
      window.location.href = userData.redirect; // Redirection vers index.html après connexion

      return userData;
    } else {
      console.error(
        "Échec de la connexion:",
        response.status,
        response.statusText
      );
    }
  } catch (error) {
    console.error("Erreur lors de la connexion:", error);
  }
}

// Fonction de déconnexion
async function loggoutUser() {
  try {
    const response = await fetch(`${webServerAddress}/logout`, {
      // Envoi de la requête de déconnexion
      method: "POST",
    });

    if (response.ok) {
      const result = await response.json(); // Conversion de la réponse en JSON
      console.log("Déconnexion réussie", result);
      window.location.href = result.redirect; // Redirection après déconnexion
      return result;
    } else {
      console.error(
        "Échec de la déconnexion:",
        response.status,
        response.statusText
      );
    }
  } catch (error) {
    console.error("Erreur lors de la déconnexion:", error);
  }
}

//RECETTES
const formRecette = document.getElementById("addRecette");
if (formRecette) {
  console.log("requête envoyée");
  formRecette.addEventListener("submit", async (event) => {
    event.preventDefault();
    const recette = await sendRecette(event);
  });
}

const button1 = document.getElementById("get-recipes");

if (button1) {
  button1.addEventListener("click", async () => {
    const recipes = await getRecette();
    await afficherRecette(recipes);
  });
}

const searchRecipe = document.querySelector(".search-input");
if (searchRecipe) {
  searchRecipe.addEventListener("input", async function (event) {
    const searchTerm = event.target.value.trim(); // Récupère la valeur
    if (searchTerm.length > 0) {
      console.log("Texte recherché :", searchTerm);
      const recettes = await getRecettesByLettre(searchTerm);
      console.log("Recettes filtrées :", recettes);
      await afficherRecette(recettes);
    } else {
      const recipes = await getRecette();
      await afficherRecette(recipes);
    }
  });
}
const detailRecette = document.getElementById("recette-list");
detailRecette.addEventListener("click", async (event) => {
  let target = event.target;

  // Vérifie si on clique sur une image ou un titre
  if (target.tagName === "IMG" || target.tagName === "H2") {
    const nomRecette = target
      .closest(".recette-card")
      .querySelector("h2")
      .textContent.trim();

    try {
      const recette = await getRecettesByNom(nomRecette); // Récupération des données
      console.log("test recette : ", recette);
      await afficherDetailRecette(recette);
      await openModale();
    } catch (error) {
      console.error("Erreur lors de la récupération de la recette :", error);
    }
  }
});

const btnAddRecette = document.getElementById("ajouterRecette");
if (btnAddRecette) {
  btnAddRecette.addEventListener("click", async (event) => {
    await afficherFormulaire();
  });
}
