// Variable pour stocker l'ID de la recette actuelle
let currentRecipeId = null;

document.addEventListener("DOMContentLoaded", () => {
  // Initialisation des modals
  M.Modal.init(document.querySelectorAll(".modal"));

  // Gestion du clic sur le bouton de commentaire
  document.addEventListener("click", async (e) => {
    if (e.target.closest(".comment-btn")) {
      const card = e.target.closest(".card");
      currentRecipeId = card.dataset.id;
      await showCommentsModal(currentRecipeId);
    }
  });

  // Gestion de l'ajout de commentaire
  document
    .getElementById("add-comment-form")
    ?.addEventListener("submit", async (e) => {
      e.preventDefault();
      await addComment();
    });
});

// Affiche le modal avec les commentaires
async function showCommentsModal(recipeId) {
  try {
    // Charger les commentaires
    const comments = await fetchComments(recipeId);

    // Afficher les commentaires
    const commentsList = document.getElementById("comments-list");
    commentsList.innerHTML = "";

    if (comments.length === 0) {
      commentsList.innerHTML =
        '<li class="collection-item">Aucun commentaire pour cette recette</li>';
    } else {
      comments.forEach((comment) => {
        const user = JSON.parse(localStorage.getItem("user"));
        const canEditDelete =
          user && (user.id_user === comment.Author.id || user.role === "admin");

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

    // Ouvrir le modal
    const modal = M.Modal.getInstance(document.getElementById("comment-modal"));
    modal.open();

    // Gestion des boutons d'édition/suppression
    initCommentButtons();
  } catch (error) {
    console.error("Erreur:", error);
    M.toast({ html: "Erreur lors du chargement des commentaires" });
  }
}

// Initialise les boutons d'édition/suppression des commentaires
function initCommentButtons() {
  // Boutons de suppression
  document.querySelectorAll(".delete-comment-btn").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.stopPropagation();
      const commentId = btn.dataset.id;
      const confirm = window.confirm(
        "Êtes-vous sûr de vouloir supprimer ce commentaire ?"
      );

      if (confirm) {
        try {
          const response = await fetch(
            `${webServerAddress}/comments/${commentId}`,
            {
              method: "DELETE",
              credentials: "include",
            }
          );

          if (response.ok) {
            M.toast({ html: "Commentaire supprimé" });
            await showCommentsModal(currentRecipeId);
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

      // Créer un formulaire d'édition
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

      // Remplacer le contenu du commentaire par le formulaire
      commentItem.querySelector("p").replaceWith(editForm);
      M.textareaAutoResize(editForm.querySelector("textarea"));
      editForm.querySelector("textarea").focus();

      // Gestion de l'annulation
      editForm
        .querySelector(".cancel-edit-btn")
        .addEventListener("click", () => {
          editForm.replaceWith(
            (document.createElement("p").textContent = currentText)
          );
        });

      // Gestion de la soumission
      editForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const newText = editForm.querySelector(".edit-comment-text").value;

        try {
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
            await showCommentsModal(currentRecipeId);
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

// Ajoute un nouveau commentaire
async function addComment() {
  const message = document.getElementById("comment-message").value.trim();

  if (!message) {
    M.toast({ html: "Le message ne peut pas être vide" });
    return;
  }

  try {
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
      document.getElementById("comment-message").value = "";
      M.toast({ html: "Commentaire ajouté" });
      await showCommentsModal(currentRecipeId);
    } else {
      const error = await response.json();
      throw new Error(error.error);
    }
  } catch (error) {
    console.error("Erreur:", error);
    M.toast({ html: error.message || "Erreur lors de l'ajout du commentaire" });
  }
}



// Récupère les commentaires d'une recette
async function fetchComments(recipeId) {
  const response = await fetch(`${webServerAddress}/comments?recipeId=${recipeId}`, {
    credentials: 'include'
  });
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  return await response.json();
}
