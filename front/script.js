const webServerAddress = "http://localhost:8000";

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
      credentials: "include",
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
      credentials: "include",
    });

    const result = await response.json();
    if (response.ok) {
      localStorage.setItem(
        "user",
        JSON.stringify({
          id_user: result.user.id_user,
          name: result.user.name,
          role: result.user.role,
        })
      );
      window.location.href = result.redirect;
    } else {
      alert(result.error || "Erreur de connexion");
    }
  } catch (error) {
    console.error("Erreur de connexion:", error);
  }
}

// Gestion de la déconnexion
document.getElementById("logout-btn")?.addEventListener("click", async (e) => {
  e.preventDefault();
  try {
    const response = await fetch(`${webServerAddress}/logout`, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
      },
    });

    const result = await response.json();

    if (response.ok) {
      localStorage.removeItem("user");
      // Suppression du cookie de session
      document.cookie =
        "PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
      window.location.href = "/connexion.html";
    } else {
      alert(result.message || "La déconnexion a échoué");
    }
  } catch (error) {
    console.error("Erreur lors de la déconnexion:", error);
    alert("Une erreur est survenue lors de la déconnexion");
  }
});

// Gestion des recettes
let modalInstance;

document.addEventListener("DOMContentLoaded", async () => {
  const user = JSON.parse(localStorage.getItem("user"));
  if (!user && window.location.pathname === "/index.html") {
    window.location.href = "/connexion.html";
  }

  M.AutoInit();
  M.Modal.init(document.querySelectorAll(".modal"), {
    onOpenEnd: () => {
      M.updateTextFields();
      M.textareaAutoResize(document.querySelectorAll("textarea"));
    },
  });
  modalInstance = M.Modal.init(document.querySelectorAll(".modal"));
  M.FormSelect.init(document.querySelectorAll("select"));

  await chargerRecettes(10);

  document
    .getElementById("entries-select")
    .addEventListener("change", async (e) => {
      await chargerRecettes(parseInt(e.target.value));
    });
});

async function chargerRecettes(limit) {
  try {
    const response = await fetch(`${webServerAddress}/recipes?limit=${limit}`, {
      credentials: "include",
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const recipes = await response.json();
    afficherRecettes(recipes);
  } catch (error) {
    console.error("Erreur de chargement:", error);
    alert("Impossible de charger les recettes");
  }
}

function afficherRecettes(recipes) {
  const container = document.getElementById("recette-list");
  container.innerHTML = "";

  recipes.forEach((recipe) => {
    const card = `
      <div class="col s12 m6 l4">
        <div class="card large hoverable" data-id="${
          recipe.id
        }" data-recipe='${JSON.stringify(recipe)}'>
          <div class="card-image waves-effect waves-light">
            <img src="${
              recipe.imageURL || "https://via.placeholder.com/300x200"
            }" 
                 class="activator responsive-img" style="width: 266px; height: 200px; object-fit: cover;">
          </div>
          <div class="card-content">
            <span class="card-title activator grey-text text-darken-4 truncate">
              ${recipe.nameFR || recipe.name}
            </span>
            
            <div class="row">
              <div class="col s4 center">
                <p class="flow-text">${recipe.duration || "--"}</p>
                <small class="grey-text">JOUR</small>
              </div>
              <div class="col s4 center">
                <p class="flow-text">${recipe.servings || "--"}</p>
                <small class="grey-text">MOIS</small>
              </div>
              <div class="col s4 center">
                <p class="flow-text">${recipe.difficulty || "--"}</p>
                <small class="grey-text">ANNEE</small>
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
                <a class="waves-effect waves-teal btn-flat heart-btn" data-likes="${
                  recipe.likes || 0
                }">
                  <i class="material-icons">favorite_border</i>
                  <span class="likes-count">${recipe.likes || 0}</span>
                </a>
              </div>
            </div>
          </div>

          <div class="card-action">
            <div class="row" style="margin-bottom: 0;">
              <div class="col s3 center"> 
                <a class="btn-floating waves-effect waves-light teal detail-btn">
                  <i class="material-icons">info</i>
                </a>
              </div>
              <div class="col s3 center">
                <a class="btn-floating waves-effect waves-light blue comment-btn">
                  <i class="material-icons">comment</i>
                </a>
              </div>
              <div class="col s3 center">
                <a class="btn-floating waves-effect waves-light orange edit-btn">
                  <i class="material-icons">edit</i>
                </a>
              </div>
              <div class="col s3 center">
                <a class="btn-floating waves-effect waves-light red delete-btn">
                  <i class="material-icons">delete</i>
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

function initCardInteractions() {
  // Gestion des favoris
  document.querySelectorAll(".heart-btn").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.stopPropagation();
      const icon = btn.querySelector("i");
      const likesCount = btn.querySelector(".likes-count");
      let currentLikes = parseInt(btn.dataset.likes);

      const action = icon.textContent === "favorite_border" ? "like" : "unlike";
      icon.textContent = action === "like" ? "favorite" : "favorite_border";
      currentLikes += action === "like" ? 1 : -1;

      btn.dataset.likes = currentLikes;
      likesCount.textContent = currentLikes;

      try {
        await fetch(`${webServerAddress}/like`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({
            recipeId: btn.closest(".card").dataset.id,
            action: action,
          }),
        });
      } catch (error) {
        console.error("Erreur:", error);
      }
    });
  });

  // Gestion détails
  document.querySelectorAll(".detail-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const recipe = JSON.parse(btn.closest(".card").dataset.recipe);
      showDetailsModal(recipe);
    });
  });

  // Gestion suppression
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      const recipeId = btn.closest(".card").dataset.id;
      const confirm = window.confirm(
        "Êtes-vous sûr de vouloir supprimer cette recette ?"
      );

      if (confirm) {
        try {
          const response = await fetch(
            `${webServerAddress}/recipes/${recipeId}`,
            {
              method: "DELETE",
              credentials: "include",
            }
          );

          const result = await response.json();
          if (response.ok) {
            await chargerRecettes(10);
          } else {
            alert(result.error || "Suppression non autorisée !");
          }
        } catch (error) {
          console.error("Erreur:", error);
          alert("Action non autorisée !");
        }
      }
    });
  });

  // Gestion édition
  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      const recipe = JSON.parse(btn.closest(".card").dataset.recipe);

      try {
        // Vérification des droits
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
          showEditModal(recipe);
        } else {
          const error = await testResponse.json();
          throw new Error(error.error);
        }
      } catch (error) {
        alert(`Édition impossible : ${error.message}`);
      }
    });
  });
}

// Modal détails
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
  M.Modal.getInstance(document.querySelector("#detail-modal")).open();
}

// Gestion ajout
document.getElementById("recette-add")?.addEventListener("click", () => {
  M.Modal.getInstance(document.querySelector("#add-modal")).open();
});

// Gestion ajout
document.getElementById("add-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();

  // Conversion des ingrédients
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

  // Conversion des étapes
  const steps = document
    .getElementById("add-steps")
    .value.split("\n")
    .filter((step) => step.trim());

  const formData = {
    name: document.getElementById("add-name").value,
    nameFR: document.getElementById("add-nameFR").value,
    ingredients: ingredients,
    stepsFR: steps,
    imageURL: document.getElementById("add-imageURL").value || null,
  };

  try {
    const response = await fetch(`${webServerAddress}/recipes`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(formData),
    });

    const result = await response.json();
    if (response.ok) {
      M.Modal.getInstance(document.querySelector("#add-modal")).close();
      await chargerRecettes(10);
      // Réinitialiser le formulaire
      document.getElementById("add-form").reset();
      M.updateTextFields();
    } else {
      alert(result.error || "Erreur lors de l'ajout");
    }
  } catch (error) {
    console.error("Erreur:", error);
    alert("Erreur lors de l'ajout");
  }
});

// Initialiser l'auto-resize quand le modal s'ouvre
document.getElementById("add-modal").addEventListener("modalOpen", function () {
  M.textareaAutoResize(document.getElementById("add-ingredients"));
  M.textareaAutoResize(document.getElementById("add-steps"));
});

// Gestion édition
function showEditModal(recipe) {
  document.getElementById("edit-id").value = recipe.id;
  document.getElementById("edit-name").value = recipe.name;
  document.getElementById("edit-nameFR").value = recipe.nameFR;

  // Gestion des ingrédients
  const ingredientsText =
    recipe.ingredients
      ?.map((ing) => `${ing.quantity} ${ing.name}`)
      .join("\n") || "";
  document.getElementById("edit-ingredients").value = ingredientsText;

  // Gestion des étapes
  document.getElementById("edit-steps").value =
    recipe.stepsFR?.join("\n") || "";

  // URL de l'image
  document.getElementById("edit-imageURL").value = recipe.imageURL || "";

  // Initialisation des textareas Materialize
  M.updateTextFields();
  M.textareaAutoResize(document.getElementById("edit-ingredients"));
  M.textareaAutoResize(document.getElementById("edit-steps"));

  M.Modal.getInstance(document.querySelector("#edit-modal")).open();
}

document.getElementById("edit-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();

  // Conversion des données du formulaire
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

  const steps = document
    .getElementById("edit-steps")
    .value.split("\n")
    .filter((step) => step.trim());

  const formData = {
    id: document.getElementById("edit-id").value,
    name: document.getElementById("edit-name").value,
    nameFR: document.getElementById("edit-nameFR").value,
    ingredients: ingredients,
    stepsFR: steps,
    imageURL: document.getElementById("edit-imageURL").value,
  };

  try {
    const response = await fetch(`${webServerAddress}/recipes/${formData.id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(formData),
    });

    const result = await response.json();
    if (response.ok) {
      M.Modal.getInstance(document.querySelector("#edit-modal")).close();
      await chargerRecettes(10);
    } else {
      alert(result.error || "Erreur lors de la mise à jour");
    }
  } catch (error) {
    console.error("Erreur:", error);
    alert("Erreur lors de la mise à jour");
  }
});
