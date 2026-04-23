(() => {
  const ROTATE_INTERVAL_MS = 5000;
  const SWITCH_FADE_OUT_MS = 110;
  const SWITCH_FADE_TOTAL_MS = 240;
  const dataEl = document.getElementById("shuffleMaskCardsData");
  const grid = document.querySelector(".shuffleboard-grid");
  const maskLinks = Array.from(document.querySelectorAll(".shuffle-mask-cell"));
  const shuffleBtn = document.getElementById("shuffleMaskNow");

  if (!dataEl || !grid) return;

  const gridCols = Math.max(
    1,
    Number.parseInt(grid.getAttribute("data-grid-cols") || "11", 10) || 11,
  );
  const gridRows = Math.max(
    1,
    Number.parseInt(grid.getAttribute("data-grid-rows") || "9", 10) || 9,
  );
  grid.style.setProperty("--grid-cols", String(gridCols));
  grid.style.setProperty("--grid-rows", String(gridRows));

  maskLinks.forEach((link) => {
    const col = Number.parseInt(link.getAttribute("data-col") || "0", 10) || 0;
    const row = Number.parseInt(link.getAttribute("data-row") || "0", 10) || 0;
    const x = gridCols > 1 ? (col / (gridCols - 1)) * 100 : 0;
    const y = gridRows > 1 ? (row / (gridRows - 1)) * 100 : 0;
    link.style.setProperty("--tile-x", `${x.toFixed(3)}%`);
    link.style.setProperty("--tile-y", `${y.toFixed(3)}%`);
  });

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

  const postItems = Array.from(document.querySelectorAll(".shuffle-item"));

  const shuffleArray = (values) => {
    const next = values.slice();
    for (let i = next.length - 1; i > 0; i -= 1) {
      const j = Math.floor(Math.random() * (i + 1));
      const tmp = next[i];
      next[i] = next[j];
      next[j] = tmp;
    }
    return next;
  };

  const renderGridCards = () => {
    if (!postItems.length || !cards.length) return;
    const picked = shuffleArray(cards).slice(0, postItems.length);
    postItems.forEach((item, idx) => {
      const card = picked[idx];
      if (!card) return;

      const link = item.querySelector(".shuffle-post-link");
      const image = item.querySelector("img");
      const stamp = item.querySelector(".shuffle-blog-stamp");

      if (link) {
        link.setAttribute("href", card.post_url);
      }
      if (image) {
        image.src = card.media_url;
        image.alt = card.blog_name || "shuffleboard image";
      }
      if (stamp) {
        stamp.textContent = card.blog_name || "blog";
      }
    });
  };

  if (cards.length === 0) return;

  const hasMaskCells = maskLinks.length > 0;

  cards.forEach((card) => {
    const img = new Image();
    img.src = card.media_url;
  });

  let index = Math.floor(Math.random() * cards.length);
  let displayedCard = cards[0];
  let switchApplyTimer = null;
  let switchDoneTimer = null;
  const initialMaskImage = String(
    grid.getAttribute("data-mask-image") || "",
  ).trim();

  if (initialMaskImage) {
    grid.style.setProperty("--mask-image", initialMaskImage);
    const matchedIndex = cards.findIndex(
      (card) => `url(${JSON.stringify(card.media_url)})` === initialMaskImage,
    );
    if (matchedIndex >= 0) index = matchedIndex;
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

  if (hasMaskCells) {
    setCard(displayedCard);
  }

  const switchToIndex = (nextIndex, animate = true, immediate = false) => {
    index = nextIndex;
    const next = cards[index];

    if (!animate || immediate) {
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

  const randomNextIndex = () => {
    if (cards.length <= 1) return index;
    let nextIndex = index;
    while (nextIndex === index) {
      nextIndex = Math.floor(Math.random() * cards.length);
    }
    return nextIndex;
  };

  const nextCard = (animate = true, immediate = false) => {
    if (!hasMaskCells) return;
    const nextIndex = randomNextIndex();
    switchToIndex(nextIndex, animate, immediate);
  };

  let intervalId = null;
  const restartInterval = () => {
    if (intervalId !== null) {
      window.clearInterval(intervalId);
    }
    intervalId = window.setInterval(() => {
      nextCard(true);
    }, ROTATE_INTERVAL_MS);
  };

  if (shuffleBtn) {
    shuffleBtn.addEventListener("click", () => {
      renderGridCards();
      nextCard(true, true);
      restartInterval();
    });
  }

  if (hasMaskCells) {
    restartInterval();
    maskLinks.forEach((link) => {
      link.addEventListener("click", (event) => {
        if (!displayedCard || !displayedCard.post_url) return;
        event.preventDefault();
        window.location.href = displayedCard.post_url;
      });
    });
  }
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
