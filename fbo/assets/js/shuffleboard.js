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
