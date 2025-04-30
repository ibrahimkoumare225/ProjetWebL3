const webServerAddress = "http://localhost:8000";

// Gestion du bouton "Démarrer"
document.getElementById('debut')?.addEventListener('click', async (e) => {
  e.preventDefault();
  
  const button = e.target;
  button.classList.add('disabled');
  
  await new Promise(resolve => setTimeout(resolve, 200));
  
  window.location.href = 'connexion.html';
});

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
      console.log("connexion: localStorage.user défini:", localStorage.getItem("user"));
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
      console.log("logout: localStorage.user vidé, cookie PHPSESSID expiré");
      // Suppression du cookie de session
      document.cookie =
        "PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
      window.location.href = "/connexion.html";
    } else {
      console.error("Erreur de déconnexion:", result);
      alert(result.message || "La déconnexion a échoué");
    }
  } catch (error) {
    console.error("Erreur réseau lors de la déconnexion:", error);
    alert("Une erreur est survenue lors de la déconnexion");
  }
});