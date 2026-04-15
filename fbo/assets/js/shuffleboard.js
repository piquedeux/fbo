(() => {
  const ROTATE_INTERVAL_MS = 5000;
  const SWITCH_FADE_OUT_MS = 90;
  const SWITCH_FADE_TOTAL_MS = 220;
  const dataEl = document.getElementById("shuffleMaskCardsData");
  const grid = document.querySelector(".shuffleboard-grid");
  const maskLinks = Array.from(document.querySelectorAll(".shuffle-mask-cell"));

  if (!dataEl || !grid || maskLinks.length === 0) return;

  let cards = [];
  try {
    cards = JSON.parse(dataEl.textContent || "[]");
    if (!Array.isArray(cards)) cards = [];
  } catch (error) {
    cards = [];
  }

  cards = cards.filter((card) => {
    if (!card || typeof card !== "object") return false;
    const mediaUrl = String(card.media_url || "").trim();
    const postUrl = String(card.post_url || "").trim();
    return mediaUrl !== "" && postUrl !== "";
  });

  if (cards.length < 2) return;

  cards.forEach((card) => {
    const img = new Image();
    img.src = card.media_url;
  });

  let index = 0;
  let displayedCard = cards[0];
  let interactionUntil = 0;
  let switchApplyTimer = null;
  let switchDoneTimer = null;
  const activeImage = String(
    getComputedStyle(grid).getPropertyValue("--mask-image") || "",
  ).trim();
  if (activeImage) {
    const matchedIndex = cards.findIndex((card) => {
      const probe = `url(${JSON.stringify(card.media_url)})`;
      return probe === activeImage;
    });
    if (matchedIndex >= 0) {
      index = matchedIndex;
    }
  }

  displayedCard = cards[index];

  const setCard = (card) => {
    const imageCss = `url(${JSON.stringify(card.media_url)})`;
    grid.style.setProperty("--mask-image", imageCss);
    maskLinks.forEach((link) => {
      link.setAttribute("href", card.post_url);
    });
    displayedCard = card;
  };

  setCard(displayedCard);

  const switchToIndex = (nextIndex, animate = true) => {
    index = nextIndex;
    const next = cards[index];

    if (!animate) {
      setCard(next);
      return;
    }

    if (switchApplyTimer !== null) {
      window.clearTimeout(switchApplyTimer);
      switchApplyTimer = null;
    }
    if (switchDoneTimer !== null) {
      window.clearTimeout(switchDoneTimer);
      switchDoneTimer = null;
    }

    grid.classList.add("is-mask-switching");
    switchApplyTimer = window.setTimeout(() => {
      setCard(next);
      switchApplyTimer = null;
    }, SWITCH_FADE_OUT_MS);

    switchDoneTimer = window.setTimeout(() => {
      grid.classList.remove("is-mask-switching");
      switchDoneTimer = null;
    }, SWITCH_FADE_TOTAL_MS);
  };

  const nextCard = (animate = true) => {
    const now = Date.now();
    if (now < interactionUntil) {
      return;
    }
    const nextIndex = (index + 1) % cards.length;
    switchToIndex(nextIndex, animate);
  };

  let intervalId = window.setInterval(() => {
    nextCard(true);
  }, ROTATE_INTERVAL_MS);

  const bumpNow = () => {
    interactionUntil = Date.now() + 1200;
    nextCard(true);
    if (intervalId !== null) {
      window.clearInterval(intervalId);
    }
    intervalId = window.setInterval(() => {
      nextCard(true);
    }, ROTATE_INTERVAL_MS);
  };

  const markInteraction = () => {
    interactionUntil = Date.now() + 1200;
  };

  maskLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      if (!displayedCard || !displayedCard.post_url) return;
      event.preventDefault();
      window.location.href = displayedCard.post_url;
    });
  });

  grid.addEventListener("pointerenter", bumpNow);
  grid.addEventListener("pointerdown", markInteraction, { passive: true });
  grid.addEventListener(
    "touchstart",
    (event) => {
      markInteraction();
      const target = event.target;
      if (target instanceof Element && target.closest(".shuffle-mask-cell")) {
        return;
      }
      bumpNow();
    },
    { passive: true },
  );
})();

(() => {
  const dataEl = document.getElementById("shuffleBlogsData");
  if (!dataEl) return;

  let blogs = [];
  try {
    blogs = JSON.parse(dataEl.textContent || "[]");
    if (!Array.isArray(blogs)) blogs = [];
  } catch (error) {
    blogs = [];
  }

  const input = document.getElementById("shuffleBlogSearch");
  const preview = document.getElementById("shuffleSearchPreview");
  const searchBlock = document.querySelector(".shuffle-search-block");

  function keepSearchVisible(target) {
    if (!target || !input) return;
    if (!window.matchMedia("(max-width: 700px)").matches) return;

    window.requestAnimationFrame(() => {
      const vv = window.visualViewport;
      const viewportTop = window.scrollY + (vv ? vv.offsetTop : 0);
      const viewportHeight = vv ? vv.height : window.innerHeight;
      const viewportBottom = viewportTop + viewportHeight;

      const rect = target.getBoundingClientRect();
      const targetTop = window.scrollY + rect.top;
      const targetBottom = window.scrollY + rect.bottom;

      if (targetBottom > viewportBottom - 12) {
        window.scrollTo({
          top: Math.max(0, targetBottom - viewportHeight + 12),
          behavior: "auto",
        });
        return;
      }

      if (targetTop < viewportTop + 8) {
        window.scrollTo({ top: Math.max(0, targetTop - 8), behavior: "auto" });
      }
    });
  }

  function render(query) {
    const value = (query || "").trim().toLowerCase();
    if (!preview) return;

    if (value === "") {
      preview.innerHTML = "";
      keepSearchVisible(searchBlock || input);
      return;
    }

    const matches = blogs
      .filter((blog) => (blog.word || "").indexOf(value) !== -1)
      .slice(0, 8);

    if (matches.length === 0) {
      preview.innerHTML = "";
      keepSearchVisible(searchBlock || input);
      return;
    }

    preview.innerHTML = matches
      .map((blog) => {
        return (
          '<a class="shuffle-search-hit" href="' +
          blog.url +
          '">' +
          '<span class="shuffle-search-hit-word">' +
          blog.word +
          "</span>" +
          '<span class="shuffle-search-hit-url">' +
          blog.fullUrl +
          "</span>" +
          "</a>"
        );
      })
      .join("");
    keepSearchVisible(preview);
  }

  if (input) {
    input.addEventListener("input", () => {
      render(input.value);
    });
    input.addEventListener("focus", () => {
      keepSearchVisible(searchBlock || input);
    });
  }

  if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", () => {
      if (document.activeElement === input) {
        keepSearchVisible(
          preview && preview.children.length > 0
            ? preview
            : searchBlock || input,
        );
      }
    });
  }

  render(input ? input.value : "");
})();
