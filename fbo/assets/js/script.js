// ── FBO typewriter animations ────────────────────────────────────────────────
(() => {
  const body = document.body;
  const frames = ["F", "FB", "FBO"];

  // Big full-screen overlay (page load + title click)
  function playBig(onDone) {
    const overlay = document.getElementById("introOverlay");
    const text = document.getElementById("introFboText");
    if (!overlay || !text) {
      if (onDone) onDone();
      return;
    }
    body.classList.add("intro-loading");
    overlay.classList.remove("done");
    text.textContent = frames[0];
    let frame = 0;
    let cycles = 0;
    const timer = window.setInterval(() => {
      text.textContent = frames[frame];
      frame += 1;
      if (frame >= frames.length) {
        frame = 0;
        cycles += 1;
        if (cycles >= 2) {
          window.clearInterval(timer);
          overlay.classList.add("done");
          // wait for full opacity transition (220ms) then act
          window.setTimeout(() => {
            if (onDone) {
              // navigating away – skip DOM cleanup, just go
              onDone();
            } else {
              // page-load case: restore scroll + reset overlay
              body.classList.remove("intro-loading");
              overlay.classList.remove("done");
            }
          }, 220);
        }
      }
    }, 130);
  }

  // Play big intro on page load
  if (body.classList.contains("intro-loading")) {
    playBig();
  }

  // Title/logo click → big animation then navigate
  const logoLink = document.getElementById("siteTitleDisplay");
  if (logoLink) {
    logoLink.addEventListener("click", (e) => {
      if (
        e.metaKey ||
        e.ctrlKey ||
        e.shiftKey ||
        e.altKey ||
        e.defaultPrevented
      )
        return;
      e.preventDefault();
      const href = logoLink.getAttribute("href") || "/";
      playBig(() => {
        window.location.href = href;
      });
    });
  }
})();

// ── FBO media buffering loader ────────────────────────────────────────────────
(() => {
  const frames = ["F", "FB", "FBO"];

  function attachLoader(mediaEl, wrap) {
    const loader = document.createElement("div");
    loader.className = "media-fbo-indicator";
    const text = document.createElement("div");
    text.className = "media-fbo-indicator-text";
    text.textContent = "F";
    loader.appendChild(text);
    wrap.appendChild(loader);

    let timer = null;
    let frame = 0;

    function startTyping() {
      if (timer !== null) return;
      wrap.classList.add("media-loading");
      loader.classList.add("visible");
      frame = 0;
      text.textContent = frames[0];
      timer = window.setInterval(() => {
        text.textContent = frames[frame];
        frame = (frame + 1) % frames.length;
      }, 120);
    }

    function stopTyping() {
      if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
      }
      loader.classList.remove("visible");
      wrap.classList.remove("media-loading");
    }

    const tag = mediaEl.tagName.toLowerCase();
    if (tag === "img") {
      if (mediaEl.complete && mediaEl.naturalWidth > 0) {
        loader.remove();
        return;
      }
      startTyping();
      mediaEl.addEventListener("load", stopTyping, { once: true });
      mediaEl.addEventListener("error", stopTyping, { once: true });
      // fallback: dismiss after 6s in case load never fires (e.g. lazy off-screen)
      window.setTimeout(() => {
        if (timer !== null) stopTyping();
      }, 6000);
    } else if (tag === "video") {
      if (mediaEl.readyState >= 2) {
        // first decoded frame is available
        loader.remove();
        return;
      }
      startTyping();
      mediaEl.addEventListener("loadeddata", stopTyping, { once: true });
      mediaEl.addEventListener("canplay", stopTyping, { once: true });
      mediaEl.addEventListener("error", stopTyping, { once: true });
      // 4s hard timeout fallback
      window.setTimeout(() => {
        if (timer !== null) stopTyping();
      }, 4000);
      mediaEl.addEventListener("waiting", startTyping);
      mediaEl.addEventListener("stalled", startTyping);
      mediaEl.addEventListener("seeking", startTyping);
      mediaEl.addEventListener("playing", () => {
        stopTyping();
      });
      mediaEl.addEventListener("pause", () => {
        stopTyping();
      });
    }
  }

  document.querySelectorAll(".media-wrap").forEach((wrap) => {
    const img = wrap.querySelector("img");
    const video = wrap.querySelector("video");
    if (video && wrap.closest(".archive.grid")) {
      return;
    }
    if (img) {
      attachLoader(img, wrap);
    } else if (video) {
      attachLoader(video, wrap);
    }
  });
})();

(() => {
  const label = document.getElementById("pageInfoLabel");
  const select = document.getElementById("pageJumpSelect");
  if (!label || !select) return;

  const close = () => {
    select.classList.remove("open");
    label.setAttribute("aria-expanded", "false");
  };

  label.addEventListener("click", (event) => {
    event.stopPropagation();
    const nextState = !select.classList.contains("open");
    select.classList.toggle("open", nextState);
    label.setAttribute("aria-expanded", nextState ? "true" : "false");
    if (nextState) {
      select.focus();
    }
  });

  select.addEventListener("change", () => {
    const selectedPage = Number(select.value || "1");
    if (!Number.isFinite(selectedPage) || selectedPage < 1) return;

    const url = new URL(window.location.href);
    url.searchParams.set("page", String(selectedPage));
    window.location.href = url.toString();
  });

  document.addEventListener("click", (event) => {
    if (event.target === label || event.target === select) return;
    if (!select.classList.contains("open")) return;
    close();
  });

  select.addEventListener("blur", () => {
    window.setTimeout(() => {
      if (document.activeElement !== select) {
        close();
      }
    }, 80);
  });
})();

(() => {
  const isMobileGrid = (el) => {
    if (!(el instanceof Element)) return false;
    if (!window.matchMedia("(max-width: 700px)").matches) return false;
    return !!el.closest(".archive.grid");
  };

  const formatLocalEu = (tsSec, multiline = false) => {
    const d = new Date(tsSec * 1000);
    if (Number.isNaN(d.getTime())) return "";
    const pad = (v) => String(v).padStart(2, "0");
    const datePart = `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`;
    const timePart = `${pad(d.getHours())}:${pad(d.getMinutes())}`;
    return multiline ? `${datePart}\n${timePart}` : `${datePart} ${timePart}`;
  };

  document.querySelectorAll(".stamp[data-ts]").forEach((el) => {
    const ts = Number(el.getAttribute("data-ts"));
    if (!Number.isFinite(ts) || ts <= 0) return;
    const formatted = formatLocalEu(ts, isMobileGrid(el));
    if (formatted) {
      el.textContent = formatted;
    }
  });
})();

(() => {
  const copyText = async (value) => {
    if (
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function"
    ) {
      await navigator.clipboard.writeText(value);
      return;
    }

    const temp = document.createElement("textarea");
    temp.value = value;
    temp.setAttribute("readonly", "readonly");
    temp.style.position = "fixed";
    temp.style.opacity = "0";
    document.body.appendChild(temp);
    temp.select();
    document.execCommand("copy");
    temp.remove();
  };

  const isDesktop = () => window.matchMedia("(min-width: 701px)").matches;
  const refreshLocationMarquee = (btn) => {
    const textWindow = btn.querySelector(".location-stamp-text-window");
    const fullTextEl = btn.querySelector(".location-stamp-full");
    if (!textWindow || !fullTextEl) return;

    btn.classList.remove("location-stamp-marquee");
    btn.style.removeProperty("--location-marquee-shift");
    btn.style.removeProperty("--location-marquee-duration");

    if (!isDesktop()) return;
    if (btn.closest(".archive.grid")) return;
    if (btn.classList.contains("location-stamp-icon-only")) return;

    const visibleWidth = textWindow.clientWidth;
    const contentWidth = fullTextEl.scrollWidth;
    if (!Number.isFinite(visibleWidth) || !Number.isFinite(contentWidth))
      return;

    const overflow = contentWidth - visibleWidth;
    if (overflow <= 4) return;

    btn.classList.add("location-stamp-marquee");
    btn.style.setProperty(
      "--location-marquee-shift",
      `${Math.ceil(overflow)}px`,
    );
    const durationSec = Math.max(
      6,
      Math.min(22, (contentWidth + visibleWidth) / 42),
    );
    btn.style.setProperty(
      "--location-marquee-duration",
      `${durationSec.toFixed(2)}s`,
    );
  };

  const locationButtons = Array.from(
    document.querySelectorAll(".location-stamp[data-coords]"),
  );
  const refreshAllLocationMarquees = () => {
    locationButtons.forEach((btn) => {
      refreshLocationMarquee(btn);
    });
  };

  window.addEventListener("resize", () => {
    window.requestAnimationFrame(refreshAllLocationMarquees);
  });
  window.addEventListener("load", refreshAllLocationMarquees);
  window.requestAnimationFrame(refreshAllLocationMarquees);

  locationButtons.forEach((btn) => {
    if (btn.closest(".archive.grid")) {
      btn.removeAttribute("title");
      return;
    }

    const originalTitle =
      btn.getAttribute("title") || "Click to copy coordinates";
    const fullTextEl = btn.querySelector(".location-stamp-full");
    const shortTextEl = btn.querySelector(".location-stamp-short");
    const originalFullText = fullTextEl ? fullTextEl.textContent : "";
    const originalShortText = shortTextEl ? shortTextEl.textContent : "";
    let resetTimer = null;

    btn.addEventListener("click", async () => {
      const coords = (btn.getAttribute("data-coords") || "").trim();
      if (!coords) return;

      const showMobileHint = window.matchMedia("(max-width: 700px)").matches;

      try {
        await copyText(coords);
        btn.setAttribute("title", "Copied coordinates");
        if (showMobileHint) {
          if (fullTextEl) fullTextEl.textContent = "copied to clipboard";
          if (shortTextEl) shortTextEl.textContent = "copied";
        }
        refreshLocationMarquee(btn);
      } catch (error) {
        btn.setAttribute("title", "Could not copy");
        if (showMobileHint) {
          if (fullTextEl) fullTextEl.textContent = "copy failed";
          if (shortTextEl) shortTextEl.textContent = "failed";
        }
        refreshLocationMarquee(btn);
      }

      if (resetTimer !== null) {
        window.clearTimeout(resetTimer);
      }
      resetTimer = window.setTimeout(() => {
        btn.setAttribute("title", originalTitle);
        if (fullTextEl) fullTextEl.textContent = originalFullText;
        if (shortTextEl) shortTextEl.textContent = originalShortText;
        refreshLocationMarquee(btn);
        resetTimer = null;
      }, 1500);
    });

    refreshLocationMarquee(btn);
  });
})();

(() => {
  const storageKey = "template-theme";
  const stored = localStorage.getItem(storageKey);
  if (stored === "dark") {
    document.body.classList.add("dark");
  }
  const btn = document.getElementById("themeToggle");
  const refreshToggleLabel = () => {
    if (!btn) return;
    btn.textContent = document.body.classList.contains("dark")
      ? "light mode"
      : "dark mode";
  };

  refreshToggleLabel();
  if (btn) {
    btn.addEventListener("click", () => {
      document.body.classList.toggle("dark");
      localStorage.setItem(
        storageKey,
        document.body.classList.contains("dark") ? "dark" : "light",
      );
      refreshToggleLabel();
    });
  }
})();

(() => {
  const shareButtons = Array.from(
    document.querySelectorAll("[data-share-current-page]"),
  );
  if (!shareButtons.length) return;

  const pageTitle = (document.title || "FBO Blog").trim();

  const getBaseBlogUrl = () => {
    const logoLink = document.getElementById("siteTitleDisplay");
    const href = (logoLink && logoLink.getAttribute("href")) || "";
    if (href !== "") {
      try {
        const url = new URL(href, window.location.origin);
        url.search = "";
        url.hash = "";
        return url.toString();
      } catch (error) {
        // fallback below
      }
    }

    const url = new URL(window.location.href);
    return `${url.origin}${url.pathname}`;
  };

  const getShareUrl = () => {
    const current = new URL(window.location.href);
    if (current.searchParams.has("post_id")) {
      return current.toString();
    }
    return getBaseBlogUrl();
  };

  const copyToClipboard = async (value) => {
    if (
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function"
    ) {
      await navigator.clipboard.writeText(value);
      return;
    }

    const temp = document.createElement("textarea");
    temp.value = value;
    temp.setAttribute("readonly", "readonly");
    temp.style.position = "fixed";
    temp.style.opacity = "0";
    document.body.appendChild(temp);
    temp.select();
    document.execCommand("copy");
    temp.remove();
  };

  const setTemporaryLabel = (btn, label) => {
    const original = btn.dataset.originalLabel || btn.textContent || "share";
    btn.dataset.originalLabel = original;
    btn.textContent = label;
    window.setTimeout(() => {
      btn.textContent = original;
    }, 1400);
  };

  shareButtons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const shareUrl = getShareUrl();

      if (navigator.share) {
        try {
          await navigator.share({
            title: pageTitle,
            url: shareUrl,
          });
          return;
        } catch (error) {
          if (error && error.name === "AbortError") {
            return;
          }
        }
      }

      try {
        await copyToClipboard(shareUrl);
        setTemporaryLabel(btn, "copied");
      } catch (error) {
        setTemporaryLabel(btn, "failed");
      }
    });
  });
})();

(() => {
  const body = document.body;
  const heroHead = document.querySelector(".hero .hero-head");
  if (!body || !heroHead) return;

  const composeMode = body.dataset?.composeMode === "1";
  if (composeMode) {
    body.classList.remove("header-peek");
    return;
  }

  let lastY = window.scrollY || 0;
  let ticking = false;

  const applyState = () => {
    const currentY = window.scrollY || 0;
    const atTop = currentY <= 16;

    if (atTop) {
      body.classList.remove("header-peek");
      lastY = currentY;
      ticking = false;
      return;
    }

    const delta = currentY - lastY;
    if (delta <= -8 && currentY > 88) {
      body.classList.add("header-peek");
    } else if (delta >= 8) {
      body.classList.remove("header-peek");
    }

    lastY = currentY;
    ticking = false;
  };

  window.addEventListener(
    "scroll",
    () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(applyState);
    },
    { passive: true },
  );
})();

(() => {
  const body = document.body;
  if (!body || body.dataset.shuffleCelebration !== "1") return;

  const overlay = document.createElement("div");
  overlay.className = "shuffle-celebration-overlay";
  overlay.setAttribute("aria-hidden", "true");

  const message = document.createElement("div");
  message.className = "shuffle-celebration-message";
  message.textContent = "Shuffle mode activated!";
  overlay.appendChild(message);

  const heartsLayer = document.createElement("div");
  heartsLayer.className = "shuffle-celebration-hearts";
  overlay.appendChild(heartsLayer);

  const heartCount = 24;
  for (let index = 0; index < heartCount; index += 1) {
    const heart = document.createElement("span");
    heart.className = "shuffle-celebration-heart";
    heart.textContent = "♥";
    heart.style.left = `${Math.random() * 100}%`;
    heart.style.animationDelay = `${Math.random() * 0.65}s`;
    heart.style.fontSize = `${14 + Math.random() * 18}px`;
    heart.style.setProperty("--heart-x", `${(Math.random() * 2 - 1) * 180}px`);
    heart.style.setProperty("--heart-rot", `${-140 + Math.random() * 280}deg`);
    heartsLayer.appendChild(heart);
  }

  body.appendChild(overlay);
  body.classList.add("shuffle-celebration-active");

  const dismiss = () => {
    overlay.classList.add("fade-out");
    window.setTimeout(() => {
      overlay.remove();
      body.classList.remove("shuffle-celebration-active");
    }, 320);
  };

  window.setTimeout(dismiss, 2400);
})();

(() => {
  const grid = document.querySelector(".archive.grid");
  if (!grid) return;

  const thumbs = Array.from(
    document.querySelectorAll(".archive.grid .grid-video-thumb"),
  );
  if (thumbs.length) {
    const inViewport = window.matchMedia("(max-width: 700px)").matches
      ? 260
      : 180;
    const rootMargin = `${inViewport}px 0px`;
    const maxConcurrent = 1;
    const queue = [];
    let active = 0;

    const waitForEvent = (target, eventName, timeoutMs = 2500) => {
      return new Promise((resolve, reject) => {
        const timer = window.setTimeout(() => {
          cleanup();
          reject(new Error("timeout"));
        }, timeoutMs);

        const cleanup = () => {
          window.clearTimeout(timer);
          target.removeEventListener(eventName, onDone);
          target.removeEventListener("error", onError);
        };

        const onDone = () => {
          cleanup();
          resolve();
        };

        const onError = () => {
          cleanup();
          reject(new Error("error"));
        };

        target.addEventListener(eventName, onDone, { once: true });
        target.addEventListener("error", onError, { once: true });
      });
    };

    const drawCoverFrame = (ctx, video, dx, dy, dw, dh) => {
      const srcW = Math.max(1, video.videoWidth || 1);
      const srcH = Math.max(1, video.videoHeight || 1);
      const srcAspect = srcW / srcH;
      const dstAspect = dw / dh;
      let sx = 0;
      let sy = 0;
      let sw = srcW;
      let sh = srcH;

      if (srcAspect > dstAspect) {
        sw = Math.max(1, Math.round(srcH * dstAspect));
        sx = Math.max(0, Math.round((srcW - sw) / 2));
      } else {
        sh = Math.max(1, Math.round(srcW / dstAspect));
        sy = Math.max(0, Math.round((srcH - sh) / 2));
      }

      ctx.drawImage(video, sx, sy, sw, sh, dx, dy, dw, dh);
    };

    const buildPosterSheet = async (thumb) => {
      if (thumb.dataset.posterReady === "1") return;
      const src = thumb.currentSrc || thumb.getAttribute("src") || "";
      if (!src) return;

      const probe = document.createElement("video");
      probe.preload = "auto";
      probe.muted = true;
      probe.playsInline = true;
      probe.setAttribute("webkit-playsinline", "true");
      probe.src = src;
      probe.style.position = "fixed";
      probe.style.left = "-99999px";
      probe.style.top = "0";
      probe.style.width = "1px";
      probe.style.height = "1px";
      probe.style.opacity = "0";
      probe.style.pointerEvents = "none";
      document.body.appendChild(probe);

      try {
        if (probe.readyState < 1) {
          await waitForEvent(probe, "loadedmetadata");
        }

        const duration = Number.isFinite(probe.duration) ? probe.duration : 0;
        const safeEnd = Math.max(0.15, duration > 0 ? duration - 0.15 : 0.15);
        const sampleTimes =
          duration > 0.6
            ? [0.12, Math.max(0.12, duration * 0.5), safeEnd]
            : [0.12, 0.12, 0.12];

        const canvas = document.createElement("canvas");
        const outW = 360;
        const outH = 480;
        canvas.width = outW;
        canvas.height = outH;
        const ctx = canvas.getContext("2d");
        if (!ctx) return;

        ctx.fillStyle = "#000000";
        ctx.fillRect(0, 0, outW, outH);

        const sliceH = Math.floor(outH / 3);
        for (let index = 0; index < sampleTimes.length; index += 1) {
          const time = Math.min(safeEnd, sampleTimes[index]);
          await new Promise((resolve, reject) => {
            const cleanup = () => {
              probe.removeEventListener("seeked", onSeeked);
              probe.removeEventListener("error", onError);
            };
            const onSeeked = () => {
              cleanup();
              resolve();
            };
            const onError = () => {
              cleanup();
              reject(new Error("seek error"));
            };
            probe.addEventListener("seeked", onSeeked, { once: true });
            probe.addEventListener("error", onError, { once: true });
            try {
              probe.currentTime = time;
            } catch (error) {
              cleanup();
              reject(error);
            }
          });

          drawCoverFrame(
            ctx,
            probe,
            0,
            index * sliceH,
            outW,
            index === 2 ? outH - sliceH * 2 : sliceH,
          );
        }

        thumb.poster = canvas.toDataURL("image/jpeg", 0.78);
        thumb.dataset.posterReady = "1";
      } catch {
        // Leave the video element as-is if poster generation fails.
      } finally {
        probe.remove();
      }
    };

    const pump = () => {
      if (active >= maxConcurrent) return;
      const next = queue.shift();
      if (!next) return;
      active += 1;
      buildPosterSheet(next)
        .catch(() => {})
        .finally(() => {
          active -= 1;
          pump();
        });
    };

    const enqueue = (thumb) => {
      if (
        thumb.dataset.posterState === "queued" ||
        thumb.dataset.posterReady === "1"
      ) {
        return;
      }
      thumb.dataset.posterState = "queued";
      queue.push(thumb);
      pump();
    };

    if ("IntersectionObserver" in window) {
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            const thumb = entry.target;
            observer.unobserve(thumb);
            enqueue(thumb);
          });
        },
        {
          root: null,
          rootMargin,
          threshold: 0.2,
        },
      );

      thumbs.forEach((thumb) => observer.observe(thumb));
    } else {
      thumbs.forEach((thumb) => enqueue(thumb));
    }
  }

  const composeMode = document.body?.dataset?.composeMode === "1";
  if (composeMode) return;

  const articleItems = Array.from(grid.querySelectorAll(".item[data-post-id]"));
  if (!articleItems.length) return;

  articleItems.forEach((item) => {
    item.addEventListener("click", (event) => {
      if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) {
        return;
      }

      const target = event.target;
      if (!(target instanceof Element)) return;
      if (target.closest("a, button, input, textarea, select, label, form")) {
        return;
      }

      event.preventDefault();
      const postId = item.getAttribute("data-post-id") || "";
      if (!postId) return;

      const url = new URL(window.location.href);
      const currentPage = Number(url.searchParams.get("page") || "1");
      url.searchParams.set("view", "single");
      url.searchParams.set("post_id", postId);
      url.searchParams.set(
        "from_page",
        String(
          Number.isFinite(currentPage) && currentPage > 0 ? currentPage : 1,
        ),
      );
      url.searchParams.delete("compose");
      window.location.href = url.toString();
    });
  });
})();
