const webServerAddress = "http://localhost:8000"; // Adresse du serveur

// Gestion de l'inscription
document
  .getElementById("inscription")
  ?.addEventListener("submit", async (event) => {
    event.preventDefault();
    await inscription(event);
  });

async function inscription(event) {
  const body = new URLSearchParams(new FormData(event.target));
  try {
    const response = await fetch(`${webServerAddress}/register`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    const result = await response.json();
    if (response.ok) {
      alert(result.message);
      if (result.redirect) window.location.href = result.redirect;
    } else {
      alert(result.error || "Erreur lors de l'inscription");
    }
  } catch (error) {
    console.error("Erreur d'inscription:", error);
  }
}

// Gestion de la connexion
document
  .getElementById("connexion")
  ?.addEventListener("submit", async (event) => {
    event.preventDefault();
    await connexion(event);
  });

async function connexion(event) {
  const body = new URLSearchParams(new FormData(event.target));
  try {
    const response = await fetch(`${webServerAddress}/login`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    const result = await response.json();
    if (response.ok) {
      window.location.href = result.redirect;
    } else {
      alert(result.error || "Erreur de connexion");
    }
  } catch (error) {
    console.error("Erreur de connexion:", error);
  }
}

// Gestion de la récupération des recettes
// Initialisation Materialize
// Initialisation Materialize
document.addEventListener("DOMContentLoaded", function () {
  M.AutoInit();
});

// Gestion des recettes
document.getElementById("get-recipes")?.addEventListener("click", async () => {
  const recipes = await getRecettes();
  afficherRecettes(recipes);
});

async function getRecettes() {
  try {
    const response = await fetch(`${webServerAddress}/recipes`);
    return await response.json();
  } catch (error) {
    console.error("Erreur lors de la récupération des recettes:", error);
    return [];
  }
}

function afficherRecettes(recipes) {
  const container = document.getElementById("recette-list");
  container.innerHTML = "";

  recipes.forEach((recipe) => {
    const card = `
          <div class="col s12 m6 l4">
              <div class="card hoverable">
                  <div class="card-image waves-effect waves-light">
                      <img src="${
                        recipe.imageURL || "https://via.placeholder.com/300x200"
                      }" class="activator responsive-img" style="width: 266px; height: 200px; object-fit: cover;">
                  </div>
                  <div class="card-content">
                      <span class="card-title truncate">${
                        recipe.nameFR || recipe.name
                      }</span>
                      <div class="row valign-wrapper stats-container" style="margin: 15px 0">
                          ${getStatsHTML(recipe)}
                      </div>
                      <div class="row valign-wrapper" style="margin-top: 10px;">
                          <div class="col s8">
                              <span class="grey-text">
                                  <i class="material-icons tiny">person</i>
                                  ${recipe.Author?.name || "Auteur inconnu"}
                                  <span class="teal-text">${
                                    recipe.Author?.role
                                      ? `${recipe.Author.role}`
                                      : ""
                                  }</span>
                              </span>
                          </div>
                          <div class="col s4 right-align">
                              <a class="waves-effect waves-teal btn-flat heart-btn" data-likes="${
                                recipe.likes || 0
                              }">
                                  <i class="material-icons">favorite_border</i>
                                  <span class="likes-count">${
                                    recipe.likes || 0
                                  }</span>
                              </a>
                          </div>
                      </div>
                  </div>
                  <div class="card-action">
                      <div class="row valign-wrapper" style="margin-bottom: 0;">
                          <div class="col s6">
                              <a class="waves-effect waves-light btn-small teal detail-btn" 
                                 href="#detail-modal" 
                                 data-recipe='${JSON.stringify(recipe)}'>
                                  <i class="material-icons left">info</i>Détail
                              </a>
                          </div>
                          <div class="col s6 right-align">
                              <a class="waves-effect waves-light btn-small grey">
                                  <i class="material-icons left">comment</i>Commenter
                              </a>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      `;

    container.insertAdjacentHTML("beforeend", card);
  });

  initCardInteractions();
}

function getStatsHTML(recipe) {
  return `
      <div class="col s4 center">
      </div>
      <div class="col s4 center">
      </div>
      <div class="col s4 center">
      </div>
  `;
}

function initCardInteractions() {
  // Gestion des favoris
  document.querySelectorAll(".heart-btn").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const icon = btn.querySelector("i");
      const likesCount = btn.nextElementSibling;
      let currentLikes = parseInt(btn.dataset.likes);

      if (icon.textContent === "favorite_border") {
        icon.textContent = "favorite";
        currentLikes++;
      } else {
        icon.textContent = "favorite_border";
        currentLikes--;
      }

      btn.dataset.likes = currentLikes;
      likesCount.textContent = currentLikes;

      // Envoyer la mise à jour au serveur
      fetch(`${webServerAddress}/like`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify({
          recipeId: btn.closest(".card").dataset.id,
          action: icon.textContent === "favorite" ? "like" : "unlike",
        }),
      });
    });
  });

  // Gestion des modals
  document.querySelectorAll(".detail-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const recipe = JSON.parse(btn.dataset.recipe);
      showDetailsModal(recipe);
    });
  });
}

function showDetailsModal(recipe) {
  const modalContent = document.querySelector("#detail-modal .modal-content");
  modalContent.innerHTML = `
      <h4>${recipe.nameFR || recipe.name}</h4>
      <div class="row">
          <div class="col s12 m6">
              <h5>Ingrédients</h5>
              <ul class="collection">
                  ${
                    recipe.ingredients
                      ?.map(
                        (ing) => `
                      <li class="collection-item">${ing.quantity} ${ing.name}</li>
                  `
                      )
                      .join("") ||
                    "<li class='collection-item'>Aucun ingrédient</li>"
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
                              ? `<span class="teal-text">(${recipe.timers[index]} min)</span>`
                              : ""
                          }
                      </li>
                  `
                      )
                      .join("") ||
                    "<li class='collection-item'>Aucune étape</li>"
                  }
              </ol>
          </div>
      </div>
  `;
  M.Modal.getInstance(document.querySelector("#detail-modal")).open();
}
