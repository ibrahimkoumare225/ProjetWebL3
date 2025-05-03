/**
 * Script JavaScript pour gérer l'affichage et l'interaction avec les sections des rôles
 * et de l'administration des rôles. Inclut l'affichage des demandes de rôles, la soumission
 * de nouvelles demandes et la gestion des demandes en attente par les administrateurs.
 */

// Log pour indiquer le début du chargement du script
console.log("Début du chargement de role.js");

/**
 * Initialise les écouteurs d'événements pour les boutons "Rôles" et "Admin".
 * Vérifie la présence des boutons dans le DOM et configure leurs interactions.
 */
function initRoleListeners() {
  console.log("Initialisation des écouteurs de role.js");

  // Récupère les boutons "Rôles" et "Admin" depuis le DOM
  const roleBtn = document.getElementById("role-btn");
  const adminBtn = document.getElementById("admin-btn");

  // Journalisation pour vérifier si les boutons sont trouvés
  console.log("role-btn trouvé:", roleBtn);
  console.log("admin-btn trouvé:", adminBtn);

  // Affiche des erreurs si les boutons sont absents
  if (!roleBtn) console.error("Bouton #role-btn introuvable");
  if (!adminBtn) console.error("Bouton #admin-btn introuvable");

  // Ajoute un écouteur pour le bouton "Rôles"
  roleBtn?.addEventListener("click", async () => {
    console.log("Clic sur role-btn");
    await afficherSectionRoles();
  });

  // Ajoute un écouteur pour le bouton "Admin"
  adminBtn?.addEventListener("click", async () => {
    console.log("Clic sur admin-btn");
    await afficherSectionAdmin();
  });

  // Vérifie la présence des boutons après 2 secondes pour détecter des problèmes de rendu
  setTimeout(() => {
    console.log("Test après 2s: Vérification des boutons");
    console.log("role-btn après 2s:", document.getElementById("role-btn"));
    console.log("admin-btn après 2s:", document.getElementById("admin-btn"));
  }, 2000);
}

// Vérifie l'état initial du DOM pour une initialisation appropriée
console.log("Vérification initiale du DOM:", document.readyState);
if (document.readyState === "complete" || document.readyState === "interactive") {
  // Si le DOM est prêt, initialise immédiatement les écouteurs
  console.log("DOM déjà chargé, initialisation immédiate");
  initRoleListeners();
} else {
  // Attend l'événement DOMContentLoaded pour initialiser
  document.addEventListener("DOMContentLoaded", () => {
    console.log("DOMContentLoaded: Initialisation de role.js");
    initRoleListeners();
  });
}

/**
 * Affiche la section des rôles dans un modal.
 * Permet à l'utilisateur de consulter ses demandes de rôles et d'en soumettre de nouvelles.
 *
 * @returns {Promise<void>}
 */
async function afficherSectionRoles() {
  console.log("Appel de afficherSectionRoles");

  // Récupère les données de l'utilisateur depuis localStorage
  const user = JSON.parse(localStorage.getItem("user"));
  console.log("Contenu de localStorage user:", user);

  // Vérifie si l'utilisateur est connecté et possède un ID
  if (!user || !user.id) {
    console.error("Aucun utilisateur connecté ou ID manquant dans localStorage");
    alert("Erreur : Veuillez vous reconnecter pour gérer les rôles");
    return;
  }

  // Convertit l'ID utilisateur en chaîne pour éviter des problèmes de type
  const userId = String(user.id);
  console.log("Utilisateur ID:", userId);

  // Récupère les éléments du DOM pour le modal
  const roleModal = document.getElementById("role-modal");
  const roleContent = document.getElementById("role-content");
  if (!roleModal || !roleContent) {
    console.error("Modale #role-modal ou #role-content introuvable dans le DOM");
    return;
  }

  try {
    // Envoie une requête pour récupérer les données des rôles
    const response = await fetch(`${webServerAddress}/roles`, {
      credentials: "include",
    });
    console.log("Requête /roles, statut:", response.status);

    // Gère les erreurs HTTP
    if (!response.ok) {
      const errorData = await response.json();
      console.error("Erreur lors de la récupération des rôles:", errorData);
      if (response.status === 401) {
        console.error("Session invalide");
        alert("Erreur : Session invalide, veuillez vous reconnecter");
      }
      throw new Error(errorData.error || "Erreur serveur");
    }

    // Récupère les données des rôles
    const roleData = await response.json();
    console.log("Données des rôles reçues:", roleData);

    // Extrait les demandes de l'utilisateur
    const userRequests = (roleData.requests || []);
    console.log("Demandes de l'utilisateur:", userRequests);

    /**
     * Affiche la liste des demandes de rôles de l'utilisateur.
     */
    const showRequests = () => {
      roleContent.innerHTML = `
        <div class="col s12">
          <h5>Mes demandes de rôles</h5>
          <div class="divider"></div>
          <ul class="collection">
            ${
              userRequests.length > 0
                ? userRequests
                    .map(
                      (request) => `
                      <li class="collection-item">
                        <span>Rôle demandé: <strong>${request.requestedRole}</strong></span>
                        <span class="right">État: <strong>${
                          request.status === "pending"
                            ? "En attente"
                            : request.status === "accepted"
                            ? "Accepté"
                            : "Rejeté"
                        }</strong></span>
                        <br>
                        <small>Créé le: ${new Date(
                          request.createdAt
                        ).toLocaleString()}</small>
                        ${
                          request.processedAt
                            ? `<br><small>Traitement: ${new Date(
                                request.processedAt
                              ).toLocaleString()}</small>`
                            : ""
                        }
                      </li>
                    `
                    )
                    .join("")
                : '<li class="collection-item">Aucune demande de rôle</li>'
            }
          </ul>
        </div>
      `;
    };

    /**
     * Affiche le formulaire pour soumettre une nouvelle demande de rôle.
     */
    const showForm = () => {
      roleContent.innerHTML = `
        <div class="col s12">
          <h5>Demander un nouveau rôle</h5>
          <div class="divider"></div>
          <form id="request-role-form" class="row">
            <div class="input-field col s6">
              <select id="requested-role" class="browser-default" required>
                <option value="" disabled selected>Choisir un rôle</option>
                <option value="chef">Chef</option>
                <option value="cuisinier">Cuisinier</option>
                <option value="traducteur">Traducteur</option>
              </select>
            </div>
            <div class="col s6">
              <button type="submit" class="btn waves-effect waves-light teal">
                Soumettre la demande
              </button>
            </div>
          </form>
        </div>
      `;

      // Initialise le select Materialize si disponible
      if (typeof M !== "undefined") {
        console.log("Initialisation du select Materialize");
        M.FormSelect.init(document.querySelectorAll("select"));
      } else {
        console.error("Materialize non chargé, select non initialisé");
      }

      // Gère la soumission du formulaire
      const form = document.getElementById("request-role-form");
      if (form) {
        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          const requestedRole = document.getElementById("requested-role").value;
          console.log(
            `Soumission de demande de rôle: userId=${userId}, role=${requestedRole}`
          );

          try {
            // Envoie la requête pour soumettre la demande
            const response = await fetch(`${webServerAddress}/roles/request`, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "include",
              body: JSON.stringify({ userId, requestedRole }),
            });
            console.log("Requête /roles/request, statut:", response.status);

            const result = await response.json();
            if (response.ok) {
              console.log("Demande de rôle soumise:", result);
              alert("Demande de rôle soumise avec succès");
              if (typeof M !== "undefined") {
                M.Modal.getInstance(roleModal).close();
              }
              await afficherSectionRoles();
            } else {
              console.error("Erreur lors de la soumission:", result);
              alert(`Erreur: ${result.error || "Échec de la soumission"}`);
            }
          } catch (error) {
            console.error("Erreur réseau lors de la soumission:", error);
            alert("Erreur réseau: " + error.message);
          }
        });
      } else {
        console.error("Formulaire #request-role-form introuvable");
      }
    };

    // Affiche la liste des demandes par défaut
    showRequests();

    // Configure les boutons pour basculer entre la liste et le formulaire
    const showRequestsBtn = document.getElementById("show-requests-btn");
    const showFormBtn = document.getElementById("show-form-btn");
    if (showRequestsBtn && showFormBtn) {
      showRequestsBtn.addEventListener("click", showRequests);
      showFormBtn.addEventListener("click", showForm);
    } else {
      console.error("Boutons #show-requests-btn ou #show-form-btn introuvables");
    }

    // Ouvre le modal si Materialize est chargé
    if (typeof M !== "undefined") {
      console.log("Initialisation de la modale Materialize");
      const modalInstance = M.Modal.getInstance(roleModal) || M.Modal.init(roleModal, { dismissible: true });
      modalInstance.open();
    } else {
      console.error("Materialize non chargé, modale non ouverte");
      roleContent.innerHTML = `<p class="red-text">Erreur: Materialize non chargé</p>`;
    }
  } catch (error) {
    console.error("Erreur dans afficherSectionRoles:", error);
    roleContent.innerHTML = `<p class="red-text">Erreur: ${error.message}</p>`;
    if (typeof M !== "undefined") {
      console.log("Affichage de l'erreur dans la modale");
      const modalInstance = M.Modal.getInstance(roleModal) || M.Modal.init(roleModal, { dismissible: true });
      modalInstance.open();
    } else {
      console.error("Materialize non chargé, modale non ouverte");
    }
  }
}

/**
 * Affiche la section d'administration pour gérer les demandes de rôles en attente.
 * Accessible uniquement aux administrateurs.
 *
 * @returns {Promise<void>}
 */
async function afficherSectionAdmin() {
  console.log("Appel de afficherSectionAdmin");

  // Vérifie l'utilisateur connecté
  const user = JSON.parse(localStorage.getItem("user"));
  if (!user || !user.id) {
    console.error("Aucun utilisateur connecté ou ID manquant dans localStorage");
    alert("Erreur : Veuillez vous reconnecter pour accéder à l'administration");
    return;
  }

  // Récupère les éléments du DOM pour le modal admin
  const adminModal = document.getElementById("admin-modal");
  const adminRequests = document.getElementById("admin-requests");
  if (!adminModal || !adminRequests) {
    console.error("Modale #admin-modal ou #admin-requests introuvable dans le DOM");
    return;
  }

  try {
    // Envoie une requête pour récupérer les demandes en attente
    const response = await fetch(`${webServerAddress}/roles/requests/pending`, {
      credentials: "include",
    });
    console.log("Requête /roles/requests/pending, statut:", response.status);

    // Gère les erreurs HTTP
    if (!response.ok) {
      const errorData = await response.json();
      console.error("Erreur lors de la récupération des demandes:", errorData);
      if (response.status === 401 || response.status === 403) {
        console.error("Accès non autorisé");
        alert("Erreur : Accès non autorisé, veuillez vérifier vos permissions");
        return;
      }
      throw new Error(errorData.error || "Erreur serveur");
    }

    // Récupère les demandes en attente
    const pendingRequests = await response.json();
    console.log("Demandes en attente reçues:", pendingRequests);

    // Affiche les demandes dans le modal
    adminRequests.innerHTML = `
      <ul class="collection">
        ${
          pendingRequests.length > 0
            ? pendingRequests
                .map(
                  (request) => `
                  <li class="collection-item">
                    <span>Utilisateur: <strong>${request.userName} ${request.userPrenom}</strong> (ID: ${request.userId})</span>
                    <br>
                    <span>Email: <strong>${request.userEmail}</strong></span>
                    <br>
                    <span>Rôle demandé: <strong>${request.requestedRole}</strong></span>
                    <br>
                    <small>Créé le: ${new Date(
                      request.createdAt
                    ).toLocaleString()}</small>
                    <div class="right">
                      <button class="btn-small green waves-effect waves-light handle-role-btn" data-request-id="${
                        request.id
                      }" data-action="accept">
                        Accepter
                      </button>
                      <button class="btn-small red waves-effect waves-light handle-role-btn" data-request-id="${
                        request.id
                      }" data-action="reject">
                        Rejeter
                      </button>
                    </div>
                  </li>
                `
                )
                .join("")
            : '<li class="collection-item">Aucune demande en attente</li>'
        }
      </ul>
    `;

    // Ouvre le modal si Materialize est chargé
    if (typeof M !== "undefined") {
      console.log("Initialisation de la modale Materialize");
      const modalInstance = M.Modal.getInstance(adminModal) || M.Modal.init(adminModal, { dismissible: true });
      modalInstance.open();
    } else {
      console.error("Materialize non chargé, modale non ouverte");
      adminRequests.innerHTML = `<p class="red-text">Erreur: Materialize non chargé</p>`;
    }

    // Configure les écouteurs pour les boutons "Accepter" et "Rejeter"
    document.querySelectorAll(".handle-role-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const requestId = parseInt(btn.dataset.requestId);
        const action = btn.dataset.action;
        console.log(
          `Clic sur handle-role-btn: requestId=${requestId}, action=${action}`
        );

        try {
          // Envoie une requête pour traiter la demande
          const response = await fetch(
            `${webServerAddress}/roles/requests/${requestId}/${action}`,
            {
              method: "PUT",
              headers: { "Content-Type": "application/json" },
              credentials: "include",
              body: JSON.stringify({ requestId, action }),
            }
          );
          console.log(
            `Requête /roles/requests/${requestId}/${action}, statut:`,
            response.status
          );

          const result = await response.json();
          if (response.ok) {
            console.log("Demande traitée:", result);
            alert("Demande traitée avec succès");

            // Met à jour les données de l'utilisateur connecté si son rôle a été modifié
            const user = JSON.parse(localStorage.getItem("user"));
            const modifiedUserId = pendingRequests.find(req => req.id === requestId)?.userId;
            if (user && modifiedUserId && String(user.id) === String(modifiedUserId)) {
              const userResponse = await fetch(`${webServerAddress}/user`, {
                credentials: "include",
              });
              if (userResponse.ok) {
                const updatedUser = await userResponse.json();
                localStorage.setItem("user", JSON.stringify(updatedUser));
                console.log("Données utilisateur mises à jour:", updatedUser);
              } else {
                console.error("Erreur lors de la récupération des données utilisateur:", await userResponse.json());
              }
            }

            // Ferme et rafraîchit le modal
            if (typeof M !== "undefined") {
              M.Modal.getInstance(adminModal).close();
            }
            await afficherSectionAdmin();
          } else {
            console.error("Erreur lors du traitement:", result);
            alert(`Erreur: ${result.error || "Échec du traitement"}`);
          }
        } catch (error) {
          console.error("Erreur réseau lors du traitement:", error);
          alert("Erreur réseau: " + error.message);
        }
      });
    });
  } catch (error) {
    console.error("Erreur dans afficherSectionAdmin:", error);
    adminRequests.innerHTML = `<p class="red-text">Erreur: ${error.message}</p>`;
    if (typeof M !== "undefined") {
      console.log("Affichage de l'erreur dans la modale");
      const modalInstance = M.Modal.getInstance(adminModal) || M.Modal.init(adminModal, { dismissible: true });
      modalInstance.open();
    } else {
      console.error("Materialize non chargé, modale non ouverte");
    }
  }
  
}