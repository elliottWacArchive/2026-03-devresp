function tweetComposer() {
  return {
    text: "",
    get remaining() {
      return 140 - this.text.length;
    },
  };
}

function mentionSearch() {
  return {
    query: "",
    results: [],
    loading: false,
    searched: false,

    async search() {
      const q = this.query.trim();

      if (!q || q.length < 2) {
        this.results = [];
        this.searched = false;
        return;
      }

      this.loading = true;
      this.searched = true;

      try {
        const res = await fetch(`/profile/search?q=${encodeURIComponent(q)}`, {
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
        });

        if (!res.ok) {
          this.results = [];
          return;
        }

        const data = await res.json();
        this.results = Array.isArray(data) ? data : [];
      } catch (error) {
        console.error("Search error:", error);
        this.results = [];
      } finally {
        this.loading = false;
      }
    },
  };
}

window.tweetComposer = tweetComposer;
window.mentionSearch = mentionSearch;

window.toggleRenameBox = function () {
  const box = document.getElementById("rename-chat-box");
  if (!box) return;

  box.style.display =
    box.style.display === "none" || box.style.display === "" ? "block" : "none";
};

document.addEventListener("click", async (e) => {
  const button = e.target.closest("[data-like-button]");
  if (!button) return;

  e.preventDefault();

  const targetId = button.dataset.likeTarget;
  if (!targetId) return;

  const likeGroup = button.closest(".like-group") || button.parentElement;
  const countEl = likeGroup?.querySelector("[data-like-count]");
  const icon = button.querySelector(".like-icon");

  const wasLiked = button.dataset.liked === "1";
  const previousCount = countEl ? parseInt(countEl.textContent, 10) || 0 : 0;

  button.disabled = true;
  button.classList.add("animating");

  try {
    const response = await fetch(`/tweets/${targetId}/like`, {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
    });

    if (!response.ok) {
      throw new Error("Like request failed");
    }

    const data = await response.json();
    const isLiked = !!data.liked;

    let nextCount;
    if (typeof data.likes_count !== "undefined" && data.likes_count !== null) {
      nextCount = parseInt(data.likes_count, 10) || 0;
    } else {
      nextCount = isLiked ? previousCount + 1 : Math.max(previousCount - 1, 0);
    }

    button.dataset.liked = isLiked ? "1" : "0";
    button.classList.toggle("is-liked", isLiked);

    if (icon) {
      icon.textContent = isLiked ? "♥" : "♡";
    }

    if (countEl) {
      countEl.textContent = nextCount;
    }
  } catch (error) {
    console.error("Like error:", error);

    button.dataset.liked = wasLiked ? "1" : "0";
    button.classList.toggle("is-liked", wasLiked);

    if (icon) {
      icon.textContent = wasLiked ? "♥" : "♡";
    }

    if (countEl) {
      countEl.textContent = previousCount;
    }
  } finally {
    setTimeout(() => {
      button.classList.remove("animating");
      button.disabled = false;
    }, 160);
  }
});

async function toggleFollow(button) {
  const id = button.dataset.userId;
  if (!id) return;

  try {
    const response = await fetch(`/follow/${id}`, {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
    });

    if (!response.ok) return;

    const data = await response.json();
    if (data.error) return;

    button.dataset.following = data.following ? "1" : "0";
    button.dataset.pending = data.pending ? "1" : "0";

    if (data.following) {
      button.textContent = "Unfollow";
    } else if (data.pending) {
      button.textContent = "Requested";
    } else {
      button.textContent = "Follow";
    }
  } catch (error) {
    console.error("Follow error:", error);
  }
}

async function acceptRequest(button) {
  const id = button.dataset.requestAccept;
  if (!id) return;

  try {
    const response = await fetch(`/follow/requests/${id}/accept`, {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
    });

    if (!response.ok) return;

    const data = await response.json();

    if (data.accepted) {
      const card = button.closest("[data-request-id]");
      if (card) card.remove();
    }
  } catch (error) {
    console.error("Accept request error:", error);
  }
}

async function rejectRequest(button) {
  const id = button.dataset.requestReject;
  if (!id) return;

  try {
    const response = await fetch(`/follow/requests/${id}/reject`, {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
    });

    if (!response.ok) return;

    const data = await response.json();

    if (data.rejected) {
      const card = button.closest("[data-request-id]");
      if (card) card.remove();
    }
  } catch (error) {
    console.error("Reject request error:", error);
  }
}

function bindInteractiveButtons() {
  document.querySelectorAll("[data-like-button]").forEach((button) => {
    if (button.dataset.likeInit === "1") return;
    button.dataset.likeInit = "1";

    if (button.dataset.liked === "1") {
      button.classList.add("is-liked");
    } else {
      button.classList.remove("is-liked");
    }
  });

  document.querySelectorAll("[data-follow-button]").forEach((button) => {
    if (button.dataset.bound === "1") return;
    button.dataset.bound = "1";
    button.addEventListener("click", (event) => {
      event.preventDefault();
      toggleFollow(button);
    });
  });

  document.querySelectorAll("[data-request-accept]").forEach((button) => {
    if (button.dataset.bound === "1") return;
    button.dataset.bound = "1";
    button.addEventListener("click", () => acceptRequest(button));
  });

  document.querySelectorAll("[data-request-reject]").forEach((button) => {
    if (button.dataset.bound === "1") return;
    button.dataset.bound = "1";
    button.addEventListener("click", () => rejectRequest(button));
  });

  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    if (form.dataset.bound === "1") return;
    form.dataset.bound = "1";
    form.addEventListener("submit", (event) => {
      const message = form.dataset.confirm || "Are you sure?";
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll(".autocomplete-item").forEach((item) => {
    item.setAttribute("aria-label", item.textContent?.trim() || "suggestion");
  });
}

async function refreshTimeline() {
  const timeline = document.getElementById("timeline");
  if (!timeline) return;

  try {
    const response = await fetch("/api/timeline", {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) return;

    const html = await response.text();
    timeline.innerHTML = html;
    bindInteractiveButtons();
    setupAutocomplete();
  } catch (error) {
    console.error("Timeline refresh error:", error);
  }
}

function setupAutocomplete() {
  const fields = document.querySelectorAll(
    'textarea[name="tweet"], textarea[name="message"], textarea[name="participant_usernames"], input[name="username"], input[name="to_username"]',
  );

  fields.forEach((field) => {
    if (field.dataset.autocompleteBound === "1") return;
    field.dataset.autocompleteBound = "1";

    const wrapper = document.createElement("div");
    wrapper.className = "autocomplete-box";
    wrapper.style.display = "none";

    const parent = field.parentElement;
    if (parent && getComputedStyle(parent).position === "static") {
      parent.style.position = "relative";
    }
    parent?.appendChild(wrapper);

    field.addEventListener("input", async () => {
      const fullValue = field.value;
      const cursor = field.selectionStart ?? fullValue.length;
      const beforeCursor = fullValue.slice(0, cursor);

      const match = beforeCursor.match(/(^|[\s,])([@#])([A-Za-z0-9_]*)$/);
      if (!match) {
        wrapper.innerHTML = "";
        wrapper.style.display = "none";
        return;
      }

      const symbol = match[2];
      const query = match[3];

      if (!query) {
        wrapper.innerHTML = "";
        wrapper.style.display = "none";
        return;
      }

      const url =
        symbol === "@"
          ? `/api/users/search?q=${encodeURIComponent(query)}`
          : `/api/hashtags/search?q=${encodeURIComponent(query)}`;

      try {
        const res = await fetch(url, {
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
        });

        if (!res.ok) {
          wrapper.innerHTML = "";
          wrapper.style.display = "none";
          return;
        }

        const data = await res.json();
        const items = Array.isArray(data) ? data : [];

        if (items.length === 0) {
          wrapper.innerHTML = "";
          wrapper.style.display = "none";
          return;
        }

        wrapper.innerHTML = "";

        items.forEach((item) => {
          const option = document.createElement("button");
          option.type = "button";
          option.className = "autocomplete-item";

          const insertText =
            symbol === "@" ? `@${item.username}` : `#${item.name}`;
          option.textContent = insertText;

          option.addEventListener("click", () => {
            const startIndex =
              beforeCursor.length - (symbol.length + query.length);
            const afterCursor = fullValue.slice(cursor);

            field.value =
              fullValue.slice(0, startIndex) + insertText + afterCursor;

            const newCursor = startIndex + insertText.length;
            field.focus();
            field.setSelectionRange(newCursor, newCursor);

            wrapper.innerHTML = "";
            wrapper.style.display = "none";
          });

          wrapper.appendChild(option);
        });

        wrapper.style.display = "block";
      } catch (error) {
        console.error("Autocomplete error:", error);
        wrapper.innerHTML = "";
        wrapper.style.display = "none";
      }
    });

    field.addEventListener("blur", () => {
      setTimeout(() => {
        wrapper.style.display = "none";
      }, 150);
    });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  bindInteractiveButtons();
  setupAutocomplete();
  setupSidebar();

  const refreshButton = document.querySelector("[data-refresh-timeline]");
  if (refreshButton) {
    refreshButton.addEventListener("click", refreshTimeline);
    setInterval(refreshTimeline, 15000);
  }
});

function setupSidebar() {
  const toggle = document.getElementById("sidebar-toggle");
  const backdrop = document.getElementById("sidebar-backdrop");
  const sidebar = document.getElementById("app-sidebar");

  if (!toggle || !sidebar) return;

  const closeSidebar = () => {
    document.body.classList.remove("sidebar-open");
    toggle.setAttribute("aria-expanded", "false");
  };

  const openSidebar = () => {
    document.body.classList.add("sidebar-open");
    toggle.setAttribute("aria-expanded", "true");
  };

  toggle.addEventListener("click", () => {
    if (document.body.classList.contains("sidebar-open")) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  backdrop?.addEventListener("click", closeSidebar);

  window.addEventListener("resize", () => {
    if (window.innerWidth > 980) {
      closeSidebar();
    }
  });
}
