/**
 * Script JavaScript pour gérer l'authentification côté client.
 * Inclut la gestion de l'inscription, de la connexion, de la déconnexion,
 * et la redirection via un bouton "Démarrer".
 */

/**
 * Adresse du serveur web backend.
 * @constant {string}
 */
const webServerAddress = "http://localhost:8000";

// Gestion du bouton "Démarrer"
/**
 * Ajoute un écouteur d'événements au bouton "Démarrer".
 * Désactive le bouton temporairement et redirige vers la page de connexion.
 */
document.getElementById('debut')?.addEventListener('click', async (e) => {
  e.preventDefault(); // Empêche le comportement par défaut du bouton

  const button = e.target;
  button.classList.add('disabled'); // Désactive visuellement le bouton

  // Ajoute un léger délai pour une meilleure UX
  await new Promise(resolve => setTimeout(resolve, 200));

  // Redirige vers la page de connexion
  window.location.href = 'connexion.html';
});

// Gestion de l'inscription
/**
 * Ajoute un écouteur d'événements au formulaire d'inscription.
 * Déclenche la fonction d'inscription lors de la soumission.
 */
document
  .getElementById("inscription")
  ?.addEventListener("submit", async (event) => {
    event.preventDefault(); // Empêche la soumission par défaut du formulaire
    await inscription(event); // Appelle la fonction d'inscription
  });

/**
 * Gère l'inscription d'un utilisateur.
 * Envoie les données du formulaire au serveur et traite la réponse.
 * @param {Event} event - Événement de soumission du formulaire.
 * @returns {Promise<void>}
 */
async function inscription(event) {
  const body = new URLSearchParams(new FormData(event.target)); // Convertit les données du formulaire en URL-encoded

  try {
    const response = await fetch(`${webServerAddress}/register`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" }, // Type de contenu requis par le serveur
      body,
      credentials: "include", // Inclut les cookies (ex. : PHPSESSID)
    });

    const result = await response.json();

    if (response.ok) {
      alert(result.message); // Affiche le message de succès
      if (result.redirect) window.location.href = result.redirect; // Redirige si spécifié
    } else {
      alert(result.error || "Erreur lors de l'inscription"); // Affiche l'erreur
    }
  } catch (error) {
    console.error("Erreur d'inscription:", error); // Journalise les erreurs réseau
  }
}

// Gestion de la connexion
/**
 * Ajoute un écouteur d'événements au formulaire de connexion.
 * Déclenche la fonction de connexion lors de la soumission.
 */
document
  .getElementById("connexion")
  ?.addEventListener("submit", async (event) => {
    event.preventDefault(); // Empêche la soumission par défaut du formulaire
    await connexion(event); // Appelle la fonction de connexion
  });

/**
 * Gère la connexion d'un utilisateur.
 * Envoie les identifiants au serveur, stocke les données utilisateur dans localStorage
 * et redirige vers la page spécifiée.
 * @param {Event} event - Événement de soumission du formulaire.
 * @returns {Promise<void>}
 */
async function connexion(event) {
  const body = new URLSearchParams(new FormData(event.target)); // Convertit les données du formulaire en URL-encoded

  try {
    const response = await fetch(`${webServerAddress}/login`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" }, // Type de contenu requis par le serveur
      body,
      credentials: "include", // Inclut les cookies (ex. : PHPSESSID)
    });

    const result = await response.json();

    if (response.ok) {
      // Stocke les données utilisateur dans localStorage pour une utilisation côté client
      localStorage.setItem(
        "user",
        JSON.stringify({
          id: result.user.id,
          name: result.user.name,
          role: result.user.role,
          prenom: result.user.prenom,
          email: result.user.email
        })
      );
      console.log("connexion: localStorage.user défini:", localStorage.getItem("user")); // Journalise pour débogage
      window.location.href = result.redirect; // Redirige vers la page spécifiée
    } else {
      alert(result.error || "Erreur de connexion"); // Affiche l'erreur
    }
  } catch (error) {
    console.error("Erreur de connexion:", error); // Journalise les erreurs réseau
  }
}

// Gestion de la déconnexion
/**
 * Ajoute un écouteur d'événements au bouton de déconnexion.
 * Envoie une requête de déconnexion, vide localStorage, expire le cookie de session
 * et redirige vers la page de connexion.
 */
document.getElementById("logout-btn")?.addEventListener("click", async (e) => {
  e.preventDefault(); // Empêche le comportement par défaut du bouton

  try {
    const response = await fetch(`${webServerAddress}/logout`, {
      method: "POST",
      credentials: "include", // Inclut les cookies (ex. : PHPSESSID)
      headers: {
        "Content-Type": "application/json", // Type de contenu JSON pour la requête
      },
    });

    const result = await response.json();

    if (response.ok) {
      localStorage.removeItem("user"); // Supprime les données utilisateur de localStorage
      console.log("logout: localStorage.user vidé, cookie PHPSESSID expiré"); // Journalise pour débogage
      // Supprime manuellement le cookie de session côté client
      document.cookie =
        "PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
      window.location.href = "/connexion.html"; // Redirige vers la page de connexion
    } else {
      console.error("Erreur de déconnexion:", result); // Journalise l'erreur
      alert(result.message || "La déconnexion a échoué"); // Affiche l'erreur
    }
  } catch (error) {
    console.error("Erreur réseau lors de la déconnexion:", error); // Journalise les erreurs réseau
    alert("Une erreur est survenue lors de la déconnexion"); // Affiche un message d'erreur générique
  }
});