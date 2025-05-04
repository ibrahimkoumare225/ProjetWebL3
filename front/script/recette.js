/**
 * Script JavaScript pour gérer l'affichage et l'interaction avec les recettes côté client.
 * Inclut le chargement des recettes, l'affichage des cartes, la gestion des likes,
 * la suppression, l'édition, l'ajout de recettes via des formulaires modaux,
 * et la traduction des titres pour les traducteurs.
 */

/**
 * Instance globale du modal Materialize pour l'ajout de recettes.
 * @type {M.Modal}
 */
let modalInstance;

/**
 * Initialisation lors du chargement du DOM.
 * Vérifie l'authentification, configure Materialize, charge les recettes initiales
 * et ajoute les écouteurs d'événements pour la recherche et le filtrage.
 */
document.addEventListener("DOMContentLoaded", async () => {
  console.log("DOMContentLoaded: Initialisation");
  const user = JSON.parse(localStorage.getItem("user"));
  console.log("Utilisateur courant:", user);

  // Redirige vers la page de connexion si l'utilisateur n'est pas connecté
  if (!user && window.location.pathname === "/index.html") {
    console.warn("Aucun utilisateur connecté, redirection vers connexion.html");
    window.location.href = "/connexion.html";
    return;
  }

  // Vérifie l'existence du conteneur des recettes
  const container = document.getElementById("recette-list");
  if (!container) {
    console.error("Élément #recette-list introuvable dans le DOM");
    alert("Erreur: Conteneur des recettes introuvable");
    return;
  }

  // Initialise les composants Materialize
  M.AutoInit();
  M.Modal.init(document.querySelectorAll(".modal"), {
    onOpenEnd: () => {
      M.updateTextFields(); // Met à jour les champs de formulaire
      M.textareaAutoResize(document.querySelectorAll("textarea")); // Redimensionne les zones de texte
    },
  });
  modalInstance = M.Modal.getInstance(document.querySelector("#add-modal"));
  M.FormSelect.init(document.querySelectorAll("select"));

  // Ajoute des styles CSS dynamiques pour garantir la visibilité et la mise en forme
  const style = document.createElement("style");
  style.innerHTML = `
    #recette-list {
      display: block;
      visibility: visible;
      min-height: 100px;
    }
    .likes-count {
      display: inline-block !important;
      min-width: 20px;
      font-size: 14px !important;
      vertical-align: middle;
      margin-left: 5px;
      color: #757575 !important;
      opacity: 1 !important;
    }
    .heart-btn {
      padding: 0 8px;
      line-height: 24px;
      display: inline-flex;
      align-items: center;
    }
    .translate-btn.disabled {
      opacity: 0.5;
      pointer-events: none;
    }
  `;
  document.head.appendChild(style);

  // Charge les recettes initiales
  try {
    await chargerRecettes(10);
  } catch (error) {
    console.error("Échec du chargement initial des recettes:", error);
    alert("Erreur lors du chargement des recettes: " + error.message);
  }

  // Ajoute un écouteur pour changer le nombre de recettes affichées
  document
    .getElementById("entries-select")
    ?.addEventListener("change", async (e) => {
      console.log("Changement entries-select:", e.target.value);
      await chargerRecettes(parseInt(e.target.value));
    });

  // Ajoute un écouteur pour la recherche en temps réel
  document.getElementById("search")?.addEventListener("input", async (e) => {
    const query = e.target.value.trim();
    console.log("Recherche:", query);
    await chargerRecettes(10, query);
  });
});

/**
 * Charge les recettes depuis le serveur et les affiche.
 * Supporte la recherche et la limitation du nombre de résultats.
 *
 * @param {number} limit Nombre maximum de recettes à charger.
 * @param {string} [query=""] Chaîne de recherche optionnelle.
 * @returns {Promise<void>}
 */
async function chargerRecettes(limit, query = "") {
  try {
    // Construit l'URL avec ou sans paramètre de recherche
    const url = query
      ? `${webServerAddress}/recipes/search?q=${encodeURIComponent(
          query
        )}&limit=${limit}`
      : `${webServerAddress}/recipes?limit=${limit}`;
    console.log("Requête chargerRecettes:", url);
    const response = await fetch(url, {
      credentials: "include", // Inclut les cookies de session
    });

    // Gère les erreurs HTTP
    if (!response.ok) {
      const errorData = await response.json();
      console.error("Erreur HTTP:", response.status, errorData);
      if (response.status === 401) {
        console.warn("Session invalide, redirection vers connexion.html");
        localStorage.removeItem("user");
        window.location.href = "/connexion.html";
        return;
      }
      throw new Error(
        `HTTP error! status: ${response.status}, message: ${
          errorData.error || "Inconnu"
        }`
      );
    }

    const recipes = await response.json();
    console.log("Recettes reçues:", recipes);
    // Vérifie que les données sont un tableau
    if (!Array.isArray(recipes)) {
      console.error("Les données reçues ne sont pas un tableau:", recipes);
      throw new Error("Format de données invalide: tableau attendu");
    }
    afficherRecettes(recipes); // Affiche les recettes
  } catch (error) {
    console.error("Erreur de chargement:", error);
    alert("Impossible de charger les recettes: " + error.message);
  }
}

/**
 * Affiche les recettes dans le conteneur #recette-list sous forme de cartes.
 *
 * @param {Array} recipes Liste des recettes à afficher.
 */
function afficherRecettes(recipes) {
  const container = document.getElementById("recette-list");
  if (!container) {
    console.error("Élément #recette-list introuvable lors de l'affichage");
    return;
  }
  container.innerHTML = ""; // Vide le conteneur
  const currentUser = JSON.parse(localStorage.getItem("user"));
  const isTranslator = currentUser && currentUser.role === "traducteur";

  console.log("Affichage de", recipes.length, "recettes");
  recipes.forEach((recipe, index) => {
    try {
      // Normalise l'ID utilisateur et les likes
      const userId = currentUser
        ? String(currentUser.id_user || currentUser.id)
        : null;
      const likedBy = Array.isArray(recipe.likedBy)
        ? recipe.likedBy.map(String)
        : [];
      const likedByUser = userId && likedBy.includes(userId);
      const likesCount = isNaN(parseInt(recipe.likes))
        ? 0
        : parseInt(recipe.likes);
      console.log(
        `Recette ${index + 1} ID ${recipe.id}: name=${
          recipe.nameFR || recipe.name
        }, rawLikes=${
          recipe.likes
        }, type=${typeof recipe.likes}, likesCount=${likesCount}, userId=${userId}, likedBy=${JSON.stringify(
          likedBy
        )}, likedByUser=${likedByUser}`
      );

      // Génère le HTML de la carte avec data-language pour suivre l'état de la langue
      const card = `
        <div class="col s12 m6 l4">
          <div class="card large hoverable" data-id="${
            recipe.id
          }" data-recipe='${JSON.stringify(recipe)}' data-language="fr">
            <div class="card-image waves-effect waves-light">
              <img src="${
                recipe.imageURL || "https://via.placeholder.com/300x200"
              }" 
                   class="activator responsive-img" style="width: 266px; height: 200px; object-fit: cover;">
            </div>
            <div class="card-content">
              <span class="card-title activator grey-text text-darken-4 truncate">
                ${recipe.nameFR || recipe.name || "Sans titre"}
              </span>
              <div class="row">
                <div class="col s4 center">
                  <p class="flow-text">
                    ${
                      recipe.createdAt
                        ? new Date(recipe.createdAt).toLocaleDateString(
                            "fr-FR",
                            { day: "2-digit" }
                          )
                        : "--"
                    }
                  </p>
                  <small class="grey-text">JOUR</small>
                </div>
                <div class="col s4 center">
                  <p class="flow-text">
                    ${
                      recipe.createdAt
                        ? new Date(recipe.createdAt).toLocaleDateString(
                            "fr-FR",
                            { month: "long" }
                          )
                        : "--"
                    }
                  </p>
                  <small class="grey-text">MOIS</small>
                </div>
                <div class="col s4 center">
                  <p class="flow-text">
                    ${
                      recipe.createdAt
                        ? new Date(recipe.createdAt).toLocaleDateString(
                            "fr-FR",
                            { year: "numeric" }
                          )
                        : "--"
                    }
                  </p>
                  <small class="grey-text">ANNÉE</small>
                </div>
              </div>
              <div class="divider"></div>
              <div class="row valign-wrapper" style="margin-top: 10px;">
                <div class="col s8">
                  <span class="grey-text">
                    <i class="material-icons tiny">person</i>
                    ${recipe.Author?.name || "Auteur inconnu"}
                    <span class="teal-text">${recipe.Author?.role || ""}</span>
                  </span>
                </div>
                <div class="col s4 right-align">
                  <a class="waves-effect waves-teal btn-flat heart-btn" data-recipe-id="${
                    recipe.id
                  }" data-likes="${likesCount}" data-liked="${
        likedByUser ? "true" : "false"
      }">
                    <i class="material-icons ${
                      likedByUser ? "red-text" : "grey-text"
                    }">${likedByUser ? "favorite" : "favorite_border"}</i>
                    <span class="likes-count">${likesCount}</span>
                  </a>
                </div>
              </div>
            </div>
            <div class="card-action">
              <div class="row" style="margin-bottom: 0;">
                <div class="col s2 center"> 
                  <a class="btn-floating waves-effect waves-light teal detail-btn">
                    <i class="material-icons">info</i>
                  </a>
                </div>
                <div class="col s2 center">
                  <a class="btn-floating waves-effect waves-light blue comment-btn" style="margin: 0 4px;">
                    <i class="material-icons">comment</i>
                  </a>
                </div>
                <div class="col s2 center">
                  <a class="btn-floating waves-effect waves-light orange edit-btn" style="margin: 0 8px;">
                    <i class="material-icons">edit</i>
                  </a>
                </div>
                <div class="col s2 center">
                  <a class="btn-floating waves-effect waves-light red delete-btn" data-recipe-id="${
                    recipe.id
                  }" style="margin: 0 12px;">
                    <i class="material-icons">delete</i>
                  </a>
                </div>
                <div class="col s2 center">
                  <a class="btn-floating waves-effect waves-light grey translate-btn ${
                    isTranslator ? "" : "disabled"
                  }" data-recipe-id="${recipe.id}" style="margin: 0 16px;">
                    <i class="material-icons">g_translate</i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
      container.insertAdjacentHTML("beforeend", card); // Ajoute la carte au conteneur
    } catch (error) {
      console.error(
        `Erreur lors du rendu de la recette ID ${recipe.id}:`,
        error
      );
    }
  });

  initCardInteractions(); // Initialise les interactions des cartes
}

/**
 * Initialise les écouteurs d'événements pour les interactions avec les cartes (like, supprimer, détails, éditer, traduire).
 */
function initCardInteractions() {
  console.log("Initialisation des interactions de cartes");

  // Ajoute les écouteurs pour les boutons de like
  document.querySelectorAll(".heart-btn").forEach((btn) => {
    btn.removeEventListener("click", handleHeartClick);
    btn.addEventListener("click", handleHeartClick);
  });

  // Ajoute les écouteurs pour les boutons de suppression
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.removeEventListener("click", handleDeleteClick);
    btn.addEventListener("click", handleDeleteClick);
  });

  // Ajoute les écouteurs pour les boutons de détails
  document.querySelectorAll(".detail-btn").forEach((btn) => {
    btn.removeEventListener("click", handleDetailClick);
    btn.addEventListener("click", handleDetailClick);
  });

  // Ajoute les écouteurs pour les boutons d'édition
  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.removeEventListener("click", handleEditClick);
    btn.addEventListener("click", handleEditClick);
  });

  // Ajoute les écouteurs pour les boutons de traduction
  document.querySelectorAll(".translate-btn").forEach((btn) => {
    btn.removeEventListener("click", handleTranslateClick);
    btn.addEventListener("click", handleTranslateClick);
  });
}

/**
 * Gère le clic sur le bouton de like/unlike.
 * Envoie une requête au serveur pour ajouter ou retirer un like et met à jour l'UI.
 *
 * @param {Event} e Événement de clic.
 * @returns {Promise<void>}
 */
async function handleHeartClick(e) {
  e.stopPropagation();
  const btn = e.target.closest(".heart-btn");
  if (btn.disabled) {
    console.warn(
      `Bouton heart-btn déjà en cours de traitement pour recipeId=${btn.dataset.recipeId}`
    );
    return;
  }
  btn.disabled = true; // Désactive le bouton pendant le traitement
  const recipeId = btn.dataset.recipeId;
  const isLiked = btn.dataset.liked === "true";
  let action = isLiked ? "unlike" : "like";
  const currentLikes = parseInt(btn.dataset.likes) || 0;
  const user = JSON.parse(localStorage.getItem("user"));
  const userId = user ? String(user.id_user || user.id) : null;

  console.log(
    `Clic sur heart-btn: recipeId=${recipeId}, action=${action}, liked=${isLiked}, userId=${userId}, currentLikes=${currentLikes}, localStorage.user=${JSON.stringify(
      user
    )}`
  );

  // Vérifie si l'utilisateur est connecté
  if (!user || !userId) {
    console.warn("Aucun utilisateur connecté pour le like/unlike", {
      user,
      userId,
    });
    alert("Veuillez vous connecter pour aimer une recette");
    localStorage.removeItem("user");
    window.location.href = "/connexion.html";
    btn.disabled = false;
    return;
  }

  if (!recipeId) {
    console.error("recipeId manquant pour heart-btn");
    btn.disabled = false;
    return;
  }

  // Met à jour l'UI immédiatement
  const icon = btn.querySelector("i");
  const likesCount = btn.querySelector(".likes-count");

  icon.textContent = action === "like" ? "favorite" : "favorite_border";
  icon.classList.toggle("red-text", action === "like");
  icon.classList.toggle("grey-text", action !== "like");
  btn.dataset.liked = action === "like" ? "true" : "false";
  btn.dataset.likes =
    action === "like" ? currentLikes + 1 : Math.max(0, currentLikes - 1);
  likesCount.textContent = btn.dataset.likes;
  console.log(
    `Mise à jour UI: recipeId=${recipeId}, action=${action}, likes=${btn.dataset.likes}`
  );

  try {
    // Envoie la requête de like/unlike
    const response = await fetch(`${webServerAddress}/like`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ recipeId, action }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      console.error("Erreur lors du like/unlike:", errorData);
      if (errorData.error === "Utilisateur non authentifié") {
        console.warn("Session invalide, redirection vers connexion.html");
        localStorage.removeItem("user");
        window.location.href = "/connexion.html";
      } else if (
        errorData.error === "L'utilisateur a déjà aimé la recette" &&
        action === "like"
      ) {
        console.warn(
          `L'utilisateur a déjà aimé la recette ${recipeId}, tentative de unlike`
        );
        // Tente un unlike si l'utilisateur a déjà aimé
        const unlikeResponse = await fetch(`${webServerAddress}/like`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({ recipeId, action: "unlike" }),
        });
        if (unlikeResponse.ok) {
          const unlikeResult = await unlikeResponse.json();
          console.log(
            `Unlike réussi pour recette: ${recipeId}, likes=${unlikeResult.likes}`
          );
          btn.dataset.likes = unlikeResult.likes;
          likesCount.textContent = unlikeResult.likes;
          btn.dataset.liked = unlikeResult.likedByUser ? "true" : "false";
          icon.textContent = unlikeResult.likedByUser
            ? "favorite"
            : "favorite_border";
          icon.classList.toggle("red-text", unlikeResult.likedByUser);
          icon.classList.toggle("grey-text", !unlikeResult.likedByUser);
        } else {
          const unlikeError = await unlikeResponse.json();
          throw new Error(unlikeError.error || "Échec du unlike");
        }
      } else {
        // Restaure l'UI en cas d'erreur
        icon.textContent = isLiked ? "favorite" : "favorite_border";
        icon.classList.toggle("red-text", isLiked);
        icon.classList.toggle("grey-text", !isLiked);
        btn.dataset.liked = isLiked ? "true" : "false";
        btn.dataset.likes = currentLikes;
        likesCount.textContent = currentLikes;
        alert(`Erreur: ${errorData.error || "Action non autorisée"}`);
        await chargerRecettes(10);
      }
    } else {
      // Met à jour l'UI avec les données du serveur
      const result = await response.json();
      console.log(
        `Like/unlike réussi pour recette: ${recipeId}, serveur renvoie likes=${result.likes}, likedByUser=${result.likedByUser}`
      );
      btn.dataset.likes = result.likes;
      likesCount.textContent = result.likes;
      btn.dataset.liked = result.likedByUser ? "true" : "false";
      icon.textContent = result.likedByUser ? "favorite" : "favorite_border";
      icon.classList.toggle("red-text", result.likedByUser);
      icon.classList.toggle("grey-text", !result.likedByUser);
    }
  } catch (error) {
    console.error(
      `Erreur réseau lors du like/unlike pour recette ${recipeId}:`,
      error
    );
    // Restaure l'UI en cas d'erreur réseau
    icon.textContent = isLiked ? "favorite" : "favorite_border";
    icon.classList.toggle("red-text", isLiked);
    icon.classList.toggle("grey-text", !isLiked);
    btn.dataset.liked = isLiked ? "true" : "false";
    btn.dataset.likes = currentLikes;
    likesCount.textContent = currentLikes;
    alert("Erreur réseau: " + error.message);
    await chargerRecettes(10);
  } finally {
    btn.disabled = false; // Réactive le bouton
  }
}

/**
 * Gère le clic sur le bouton de suppression.
 * Demande une confirmation et envoie une requête de suppression au serveur.
 *
 * @param {Event} e Événement de clic.
 * @returns {Promise<void>}
 */
async function handleDeleteClick(e) {
  e.stopPropagation();
  const btn = e.target.closest(".delete-btn");
  if (btn.disabled) {
    console.warn(
      `Bouton delete-btn déjà en cours de traitement pour recipeId=${btn.dataset.recipeId}`
    );
    return;
  }
  btn.disabled = true; // Désactive le bouton pendant le traitement
  const recipeId = btn.dataset.recipeId;
  console.log(`Clic sur delete-btn: recipeId=${recipeId}`);

  if (!recipeId) {
    console.error("recipeId manquant pour delete-btn");
    btn.disabled = false;
    return;
  }

  // Demande une confirmation avant suppression
  if (window.confirm("Êtes-vous sûr de vouloir supprimer cette recette ?")) {
    try {
      // Envoie la requête de suppression
      const response = await fetch(`${webServerAddress}/recipes/${recipeId}`, {
        method: "DELETE",
        credentials: "include",
      });

      const result = await response.json();
      if (response.ok) {
        console.log(`Suppression réussie pour recette: ${recipeId}`);
        await chargerRecettes(10); // Recharge les recettes
      } else {
        console.error(
          `Erreur lors de la suppression: ${JSON.stringify(result)}`
        );
        alert(result.error || "Suppression non autorisée !");
      }
    } catch (error) {
      console.error(
        `Erreur réseau lors de la suppression de la recette ${recipeId}:`,
        error
      );
      alert("Erreur réseau: " + error.message);
    } finally {
      btn.disabled = false; // Réactive le bouton
    }
  } else {
    btn.disabled = false;
  }
}

/**
 * Gère le clic sur le bouton de détails.
 * Affiche les détails de la recette dans un modal.
 *
 * @param {Event} e Événement de clic.
 */
function handleDetailClick(e) {
  e.stopPropagation();
  const recipe = JSON.parse(e.target.closest(".card").dataset.recipe);
  console.log(`Clic sur detail-btn pour recette: ${recipe.id}`);
  showDetailsModal(recipe); // Affiche le modal des détails
}

/**
 * Gère le clic sur le bouton d'édition.
 * Vérifie les autorisations et affiche le formulaire d'édition dans un modal.
 *
 * @param {Event} e Événement de clic.
 * @returns {Promise<void>}
 */
async function handleEditClick(e) {
  e.stopPropagation();
  const recipe = JSON.parse(e.target.closest(".card").dataset.recipe);
  console.log(`Clic sur edit-btn pour recette: ${recipe.id}`);

  try {
    // Vérifie les autorisations via une requête de test
    const testResponse = await fetch(
      `${webServerAddress}/recipes/${recipe.id}`,
      {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ test: true }),
      }
    );

    if (testResponse.ok) {
      showEditModal(recipe); // Affiche le modal d'édition
    } else {
      const error = await testResponse.json();
      throw new Error(error.error);
    }
  } catch (error) {
    console.error("Erreur lors de l'édition:", error);
    alert(`Édition impossible : ${error.message}`);
  }
}

/**
 * Gère le clic sur le bouton de traduction.
 * Bascule l'affichage du titre de la recette entre français et anglais pour les traducteurs.
 *
 * @param {Event} e Événement de clic.
 */
function handleTranslateClick(e) {
  e.stopPropagation();
  const btn = e.target.closest(".translate-btn");
  const card = btn.closest(".card");
  const recipe = JSON.parse(card.dataset.recipe);
  const currentLanguage = card.dataset.language;
  const user = JSON.parse(localStorage.getItem("user"));

  // Vérifie si l'utilisateur est un traducteur
  if (!user || user.role !== "traducteur") {
    console.warn(
      "Accès refusé au bouton de traduction: utilisateur non traducteur",
      user
    );
    M.toast({ html: "Seuls les traducteurs peuvent utiliser cette fonction" });
    return;
  }

  // Bascule la langue
  const newLanguage = currentLanguage === "fr" ? "en" : "fr";
  card.dataset.language = newLanguage;
  const titleElement = card.querySelector(".card-title");
  titleElement.textContent =
    newLanguage === "fr"
      ? recipe.nameFR || recipe.name || "Sans titre"
      : recipe.name || recipe.nameFR || "Sans titre";
  console.log(
    `Traduction pour recette ID ${recipe.id}: langue=${newLanguage}, titre=${
      titleElement.textContent
    }`
  );

  // Met à jour l'icône du bouton pour refléter l'état
  const icon = btn.querySelector("i");
  icon.textContent = newLanguage === "fr" ? "g_translate" : "translate";
}

/**
 * Affiche les détails d'une recette dans un modal.
 *
 * @param {Object} recipe Données de la recette.
 */
function showDetailsModal(recipe) {
  const modalContent = document.querySelector("#detail-modal .modal-content");
  modalContent.innerHTML = `
    <h4>${recipe.nameFR || recipe.name || "Sans titre"}</h4>
    <div class="row">
      <div class="col s12 m6">
        <h5>Ingrédients</h5>
        <ul class="collection">
          ${
            recipe.ingredientsFR
              ?.map(
                (ing) => `
            <li class="collection-item">${ing.quantity} ${ing.name}</li>
          `
              )
              .join("") || "<li class='collection-item'>Aucun ingrédient</li>"
          }
        </ul>
      </div>
      <div class="col s12 m6">
        <h5>Étapes de préparation</h5>
        <ol class="collection">
          ${
            recipe.stepsFR
              ?.map(
                (step, index) => `
            <li class="collection-item">${step} 
              ${
                recipe.timers?.[index]
                  ? `
                <span class="teal-text">(${recipe.timers[index]} min)</span>
              `
                  : ""
              }
            </li>
          `
              )
              .join("") || "<li class='collection-item'>Aucune étape</li>"
          }
        </ol>
      </div>
    </div>
  `;
  M.Modal.getInstance(document.querySelector("#detail-modal")).open(); // Ouvre le modal
}

/**
 * Ouvre le modal d'ajout de recette.
 */
document.getElementById("recette-add")?.addEventListener("click", () => {
  console.log("Clic sur recette-add");
  M.Modal.getInstance(document.querySelector("#add-modal")).open();
});

/**
 * Gère la soumission du formulaire d'ajout de recette.
 * Envoie les données au serveur et recharge les recettes.
 *
 * @param {Event} e Événement de soumission.
 * @returns {Promise<void>}
 */
document.getElementById("add-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  console.log("Soumission add-form");

  // Traite les ingrédients FR
  const ingredientsFR = document
    .getElementById("add-ingredientsFR")
    .value.split("\n")
    .filter((line) => line.trim())
    .map((line) => {
      const [quantity, ...nameParts] = line.trim().split(" ");
      return {
        quantity: quantity || "",
        name: nameParts.join(" ") || "",
      };
    });
  // Traite les ingrédients
  const ingredients = document
    .getElementById("add-ingredients")
    .value.split("\n")
    .filter((line) => line.trim())
    .map((line) => {
      const [quantity, ...nameParts] = line.trim().split(" ");
      return {
        quantity: quantity || "",
        name: nameParts.join(" ") || "",
      };
    });
  // Traite les étapes FR
  const stepsFR = document
    .getElementById("add-stepsFR")
    .value.split("\n")
    .filter((step) => step.trim());

  // Traite les étapes
  const steps = document
    .getElementById("add-steps")
    .value.split("\n")
    .filter((step) => step.trim());

  // Prépare les données du formulaire
  const formData = {
    name: document.getElementById("add-name").value,
    nameFR: document.getElementById("add-nameFR").value,
    ingredientsFR: ingredientsFR,
    ingredients: ingredients,
    steps: steps,
    stepsFR: steps,
    imageURL: document.getElementById("add-imageURL").value || null,
  };

  try {
    // Envoie la requête d'ajout
    const response = await fetch(`${webServerAddress}/recipes`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(formData),
    });

    const result = await response.json();
    if (response.ok) {
      console.log("Recette ajoutée:", result);
      M.Modal.getInstance(document.querySelector("#add-modal")).close(); // Ferme le modal
      await chargerRecettes(10); // Recharge les recettes
      document.getElementById("add-form").reset(); // Réinitialise le formulaire
      M.updateTextFields();
    } else {
      console.error(`Erreur lors de l'ajout: ${JSON.stringify(result)}`);
      alert(result.error || "Erreur lors de l'ajout");
    }
  } catch (error) {
    console.error("Erreur réseau lors de l'ajout:", error);
    alert("Erreur réseau: " + error.message);
  }
});

/**
 * Affiche le modal d'édition avec les données de la recette.
 *
 * @param {Object} recipe Données de la recette.
 */
function showEditModal(recipe) {
  console.log("showEditModal pour recette:", recipe.id);
  // Remplit les champs du formulaire d'édition
  document.getElementById("edit-id").value = recipe.id;
  document.getElementById("edit-name").value = recipe.name;
  document.getElementById("edit-nameFR").value = recipe.nameFR;

  const ingredientsText =
    recipe.ingredientsFR
      ?.map((ing) => `${ing.quantity} ${ing.name}`)
      .join("\n") || "";
  const ingredientsTextEN =
    recipe.ingredients
      ?.map((ing) => `${ing.quantity} ${ing.name}`)
      .join("\n") || "";
  document.getElementById("edit-ingredientsFR").value = ingredientsText;
  document.getElementById("edit-ingredients").value = ingredientsTextEN;

  document.getElementById("edit-stepsFR").value =
    recipe.stepsFR?.join("\n") || "";
  document.getElementById("edit-steps").value = recipe.steps?.join("\n") || "";
  document.getElementById("edit-imageURL").value = recipe.imageURL || "";

  // Met à jour les champs Materialize
  M.updateTextFields();
  M.textareaAutoResize(document.getElementById("edit-ingredientsFR"));
  M.textareaAutoResize(document.getElementById("edit-stepsFR"));
  M.textareaAutoResize(document.getElementById("edit-ingredients"));
  M.textareaAutoResize(document.getElementById("edit-steps"));

  M.Modal.getInstance(document.querySelector("#edit-modal")).open(); // Ouvre le modal
}

/**
 * Gère la soumission du formulaire d'édition de recette.
 * Envoie les données mises à jour au serveur et recharge les recettes.
 *
 * @param {Event} e Événement de soumission.
 * @returns {Promise<void>}
 */
document.getElementById("edit-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  console.log("Soumission edit-form");

  // Traite les ingrédients FR
  const ingredientsFR = document
    .getElementById("edit-ingredientsFR")
    .value.split("\n")
    .filter((line) => line.trim())
    .map((line) => {
      const [quantity, ...nameParts] = line.trim().split(" ");
      return {
        quantity: quantity || "",
        name: nameParts.join(" ") || "",
      };
    });

  // Traite les ingrédients
  const ingredients = document
    .getElementById("edit-ingredients")
    .value.split("\n")
    .filter((line) => line.trim())
    .map((line) => {
      const [quantity, ...nameParts] = line.trim().split(" ");
      return {
        quantity: quantity || "",
        name: nameParts.join(" ") || "",
      };
    });

  // Traite les étapes FR
  const stepsFR = document
    .getElementById("edit-stepsFR")
    .value.split("\n")
    .filter((step) => step.trim());

  // Traite les étapes
  const steps = document
    .getElementById("edit-steps")
    .value.split("\n")
    .filter((step) => step.trim());

  // Prépare les données du formulaire
  const formData = {
    id: document.getElementById("edit-id").value,
    name: document.getElementById("edit-name").value,
    nameFR: document.getElementById("edit-nameFR").value,
    ingredients: ingredients,
    ingredientsFR: ingredientsFR,
    steps: steps,
    stepsFR: stepsFR,
    imageURL: document.getElementById("edit-imageURL").value,
  };

  try {
    // Envoie la requête de mise à jour
    const response = await fetch(`${webServerAddress}/recipes/${formData.id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(formData),
    });

    const result = await response.json();
    if (response.ok) {
      console.log("Recette mise à jour:", result);
      M.Modal.getInstance(document.querySelector("#edit-modal")).close(); // Ferme le modal
      await chargerRecettes(10); // Recharge les recettes
    } else {
      console.error(`Erreur lors de la mise à jour: ${JSON.stringify(result)}`);
      alert(result.error || "Erreur lors de la mise à jour");
    }
  } catch (error) {
    console.error("Erreur réseau lors de la mise à jour:", error);
    alert("Erreur réseau: " + error.message);
  }
});

/**
 * Bascule le texte entre français et anglais pour un élément donné.
 * Utilisé pour la fonctionnalité de traduction.
 *
 * @param {Object} recipe Données de la recette.
 * @param {string} currentLanguage Langue actuelle ("fr" ou "en").
 * @returns {string} Texte traduit.
 */
function translateText(recipe, currentLanguage) {
  return currentLanguage === "fr"
    ? recipe.nameFR || recipe.name || "Sans titre"
    : recipe.name || recipe.nameFR || "Sans titre";
}