/**
 * Script JavaScript pour gérer l'affichage et l'interaction avec les commentaires des recettes.
 * Inclut l'affichage des commentaires dans un modal, l'ajout, l'édition et la suppression
 * de commentaires, ainsi que la récupération des commentaires depuis le serveur.
 */

/**
 * ID de la recette actuellement sélectionnée pour les commentaires.
 * @type {string|null}
 */
let currentRecipeId = null;

/**
 * Initialisation lors du chargement du DOM.
 * Configure les modals Materialize et les écouteurs d'événements pour l'interaction
 * avec les commentaires.
 */
document.addEventListener("DOMContentLoaded", () => {
  // Initialise les modals Materialize
  M.Modal.init(document.querySelectorAll(".modal"));

  // Gère le clic sur les boutons de commentaire
  document.addEventListener("click", async (e) => {
    if (e.target.closest(".comment-btn")) {
      const card = e.target.closest(".card");
      currentRecipeId = card.dataset.id; // Stocke l'ID de la recette
      await showCommentsModal(currentRecipeId); // Affiche le modal des commentaires
    }
  });

  // Gère la soumission du formulaire d'ajout de commentaire
  document
    .getElementById("add-comment-form")
    ?.addEventListener("submit", async (e) => {
      e.preventDefault();
      await addComment(); // Ajoute un nouveau commentaire
    });
});

/**
 * Affiche le modal des commentaires pour une recette donnée.
 * Charge et affiche les commentaires associés, avec options d'édition/suppression
 * pour l'auteur du commentaire ou les administrateurs.
 *
 * @param {string} recipeId ID de la recette.
 * @returns {Promise<void>}
 */
async function showCommentsModal(recipeId) {
  try {
    // Récupère les commentaires depuis le serveur
    const comments = await fetchComments(recipeId);

    // Prépare la liste des commentaires
    const commentsList = document.getElementById("comments-list");
    commentsList.innerHTML = "";

    // Affiche un message si aucun commentaire n'existe
    if (comments.length === 0) {
      commentsList.innerHTML =
        '<li class="collection-item">Aucun commentaire pour cette recette</li>';
    } else {
      // Récupère l'utilisateur connecté depuis localStorage
      const user = JSON.parse(localStorage.getItem("user"));

      // Affiche chaque commentaire
      comments.forEach((comment) => {
        // Vérifie si l'utilisateur peut éditer/supprimer le commentaire :
        // - L'utilisateur est l'auteur (même ID)
        // - L'utilisateur est un administrateur
        const canEditDelete =
          user &&
          (String(user.id) === String(comment.Author.id) ||
            user.role === "admin");

        const commentItem = document.createElement("li");
        commentItem.className = "collection-item avatar";
        commentItem.innerHTML = `
          <i class="material-icons circle teal">person</i>
          <span class="title"><strong>${comment.Author.name} ${
            comment.Author.prenom
          }</strong></span>
          <p>${comment.message}</p>
          <small class="grey-text">${new Date(
            comment.createdAt
          ).toLocaleString()}</small>
          ${
            canEditDelete
              ? `
          <div class="secondary-content">
            <a class="btn-flat waves-effect waves-teal edit-comment-btn" data-id="${comment.id}">
              <i class="material-icons teal-text">edit</i>
            </a>
            <a class="btn-flat waves-effect waves-teal delete-comment-btn" data-id="${comment.id}">
              <i class="material-icons red-text">delete</i>
            </a>
          </div>
          `
              : ""
          }
        `;
        commentsList.appendChild(commentItem);
      });
    }

    // Ouvre le modal des commentaires
    const modal = M.Modal.getInstance(document.getElementById("comment-modal"));
    modal.open();

    // Initialise les boutons d'édition et de suppression
    initCommentButtons();
  } catch (error) {
    console.error("Erreur:", error);
    M.toast({ html: "Erreur lors du chargement des commentaires" });
  }
}

/**
 * Initialise les écouteurs d'événements pour les boutons d'édition et de suppression
 * des commentaires.
 */
function initCommentButtons() {
  // Boutons de suppression
  document.querySelectorAll(".delete-comment-btn").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.stopPropagation();
      const commentId = btn.dataset.id;
      // Demande une confirmation avant suppression
      const confirm = window.confirm(
        "Êtes-vous sûr de vouloir supprimer ce commentaire ?"
      );

      if (confirm) {
        try {
          // Envoie la requête de suppression
          const response = await fetch(
            `${webServerAddress}/comments/${commentId}`,
            {
              method: "DELETE",
              credentials: "include",
            }
          );

          if (response.ok) {
            M.toast({ html: "Commentaire supprimé" });
            await showCommentsModal(currentRecipeId); // Rafraîchit le modal
          } else {
            const error = await response.json();
            throw new Error(error.error);
          }
        } catch (error) {
          console.error("Erreur:", error);
          M.toast({ html: error.message || "Erreur lors de la suppression" });
        }
      }
    });
  });

  // Boutons d'édition
  document.querySelectorAll(".edit-comment-btn").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.stopPropagation();
      const commentId = btn.dataset.id;
      const commentItem = btn.closest(".collection-item");
      const currentText = commentItem.querySelector("p").textContent;

      // Crée un formulaire d'édition
      const editForm = document.createElement("form");
      editForm.innerHTML = `
        <div class="input-field">
          <textarea class="materialize-textarea edit-comment-text">${currentText}</textarea>
          <div class="right-align" style="margin-top: 10px;">
            <button type="button" class="btn-flat waves-effect waves-teal cancel-edit-btn">
              Annuler
            </button>
            <button type="submit" class="btn waves-effect waves-light teal">
              Enregistrer
            </button>
          </div>
        </div>
      `;

      // Remplace le contenu du commentaire par le formulaire
      commentItem.querySelector("p").replaceWith(editForm);
      M.textareaAutoResize(editForm.querySelector("textarea"));
      editForm.querySelector("textarea").focus();

      // Gère l'annulation de l'édition
      editForm
        .querySelector(".cancel-edit-btn")
        .addEventListener("click", () => {
          editForm.replaceWith(
            (document.createElement("p").textContent = currentText)
          );
        });

      // Gère la soumission du formulaire d'édition
      editForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const newText = editForm.querySelector(".edit-comment-text").value;

        try {
          // Envoie la requête de mise à jour
          const response = await fetch(
            `${webServerAddress}/comments/${commentId}`,
            {
              method: "PUT",
              headers: { "Content-Type": "application/json" },
              credentials: "include",
              body: JSON.stringify({ message: newText }),
            }
          );

          if (response.ok) {
            M.toast({ html: "Commentaire modifié" });
            await showCommentsModal(currentRecipeId); // Rafraîchit le modal
          } else {
            const error = await response.json();
            throw new Error(error.error);
          }
        } catch (error) {
          console.error("Erreur:", error);
          M.toast({ html: error.message || "Erreur lors de la modification" });
        }
      });
    });
  });
}

/**
 * Ajoute un nouveau commentaire pour la recette actuelle.
 * Envoie les données au serveur et rafraîchit le modal des commentaires.
 *
 * @returns {Promise<void>}
 */
async function addComment() {
  const message = document.getElementById("comment-message").value.trim();
  // Vérifie que le message n'est pas vide
  if (!message) {
    M.toast({ html: "Le message ne peut pas être vide" });
    return;
  }

  try {
    // Envoie la requête d'ajout de commentaire
    const response = await fetch(`${webServerAddress}/comments`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({
        message: message,
        recipeId: currentRecipeId,
      }),
    });

    if (response.ok) {
      document.getElementById("comment-message").value = ""; // Réinitialise le champ
      M.toast({ html: "Commentaire ajouté" });
      await showCommentsModal(currentRecipeId); // Rafraîchit le modal
    } else {
      const error = await response.json();
      throw new Error(error.error);
    }
  } catch (error) {
    console.error("Erreur:", error);
    M.toast({ html: error.message || "Erreur lors de l'ajout du commentaire" });
  }
}

/**
 * Récupère les commentaires associés à une recette depuis le serveur.
 *
 * @param {string} recipeId ID de la recette.
 * @returns {Promise<Array>} Liste des commentaires.
 * @throws {Error} Si la requête échoue.
 */
async function fetchComments(recipeId) {
  const response = await fetch(
    `${webServerAddress}/comments?recipeId=${recipeId}`,
    {
      credentials: "include",
    }
  );

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  return await response.json();
}