(() => {
  const textarea = document.getElementById("textPostContent");
  const count = document.getElementById("textPostCount");
  const epoch = document.getElementById("textPostClientEpoch");
  const form = document.getElementById("textPostForm");
  if (!textarea || !count || !epoch || !form) return;

  const limit = Number(document.body?.dataset?.maxTextPostLength || "280");
  const refreshCount = () => {
    const len = textarea.value.length;
    count.textContent = `${len} / ${limit}`;
  };

  // Toggle removed: compose sections always visible now.

  textarea.addEventListener("input", refreshCount);
  form.addEventListener("submit", () => {
    epoch.value = String(Date.now());
  });
  refreshCount();
})();

(() => {
  const input = document.getElementById("inlineUploadFiles");
  const epochInput = document.getElementById("uploadClientEpoch");
  const form = document.getElementById("inlineUploadForm");
  const preview = document.getElementById("inlineUploadPreview");
  const empty = document.getElementById("inlineUploadEmpty");
  const counter = document.getElementById("inlineUploadCounter");
  const status = document.getElementById("inlineUploadStatus");
  if (
    !input ||
    !preview ||
    !empty ||
    !epochInput ||
    !form ||
    !counter ||
    !status
  )
    return;

  const imageExt = new Set(["jpg", "jpeg", "png", "webp", "gif"]);
  const videoExt = new Set(["mp4", "mov", "webm", "m4v"]);
  const captionCharLimit = 67;
  const maxImagesPerUpload = 10;
  const maxImageSizeBytes = 10 * 1024 * 1024;
  const maxVideoSizeBytes = 300 * 1024 * 1024;
  const defaultStatus = "Select only images or one video per upload.";
  let selectedFiles = [];
  let captions = [];
  let useFilenameCaption = [];

  const countCaptionChars = (value) => Array.from(String(value || "")).length;
  const sliceCaptionChars = (value, limit) =>
    Array.from(String(value || ""))
      .slice(0, limit)
      .join("");

  const normalizeCaption = (value) => {
    const s = String(value || "")
      .replace(/[\r\n\t]/g, "")
      .trim();
    if (!s) return "";
    return sliceCaptionChars(s, captionCharLimit);
  };

  const sanitizeCaptionWhileTyping = (value) => {
    const raw = String(value || "").replace(/[\r\n\t]/g, "");
    if (raw === null || raw === undefined) return "";
    return sliceCaptionChars(raw, captionCharLimit);
  };

  const defaultCaptionFromFilename = (filename) => {
    let base = String(filename || "")
      .replace(/\.[^.]+$/, "")
      .trim();
    if (!base) return "";
    // replace underscores/slashes with spaces for word separation, remove newlines/tabs
    base = base
      .replace(/[_\/]+/g, " ")
      .replace(/[\r\n\t]/g, " ")
      .trim();
    if (!base) return "";
    const tokens = base.split(/\s+/);
    // drop trailing numeric tokens
    while (tokens.length > 1 && /^\d+$/.test(tokens[tokens.length - 1] || "")) {
      tokens.pop();
    }

    const joined = tokens.join(" ");
    return normalizeCaption(joined);
  };

  const resolveCaption = (raw, filename, allowFilenameFallback) => {
    const normalized = normalizeCaption(raw);
    if (normalized) return normalized;
    if (!allowFilenameFallback) return "";
    const fallback = defaultCaptionFromFilename(filename);
    return normalizeCaption(fallback);
  };

  const classifyFile = (file) => {
    const ext = (
      String(file.name || "")
        .split(".")
        .pop() || ""
    ).toLowerCase();
    const mime = String(file.type || "").toLowerCase();

    if (mime.startsWith("image/") || imageExt.has(ext)) {
      return "image";
    }

    if (mime.startsWith("video/") || videoExt.has(ext)) {
      if (ext === "webm" && mime.startsWith("audio/")) {
        return null;
      }
      return "video";
    }

    return null;
  };

  const setStatus = (message, isError = false) => {
    status.textContent = message;
    status.classList.toggle("is-error", isError);
    input.setCustomValidity(isError ? message : "");
  };

  const updateCounter = () => {
    if (selectedFiles.length === 0) {
      counter.textContent = "No files selected yet.";
      return;
    }

    const videoCount = selectedFiles.reduce(
      (count, file) => count + (classifyFile(file) === "video" ? 1 : 0),
      0,
    );
    const imageCount = selectedFiles.length - videoCount;

    if (videoCount === 1 && imageCount === 0) {
      counter.textContent = "1 video selected.";
      return;
    }

    counter.textContent = `${imageCount} / ${maxImagesPerUpload} images selected.`;
  };

  const syncInputFiles = () => {
    const transfer = new DataTransfer();
    selectedFiles.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
  };

  const render = () => {
    epochInput.value = selectedFiles.length > 0 ? String(Date.now()) : "";
    preview.innerHTML = "";
    updateCounter();
    if (selectedFiles.length === 0) {
      empty.style.display = "";
      return;
    }
    empty.style.display = "none";

    selectedFiles.forEach((file, index) => {
      const card = document.createElement("article");
      card.className = "item";

      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "upload-preview-remove";
      removeBtn.setAttribute("aria-label", `Remove ${file.name}`);
      removeBtn.textContent = "X";
      removeBtn.addEventListener("click", () => {
        selectedFiles = selectedFiles.filter(
          (_, selectedIndex) => selectedIndex !== index,
        );
        captions = captions.filter(
          (_, selectedIndex) => selectedIndex !== index,
        );
        useFilenameCaption = useFilenameCaption.filter(
          (_, selectedIndex) => selectedIndex !== index,
        );
        syncInputFiles();
        render();
        setStatus(defaultStatus, false);
      });

      const mediaWrap = document.createElement("div");
      mediaWrap.className = "media-wrap";
      const ext = (file.name.split(".").pop() || "").toLowerCase();
      const url = URL.createObjectURL(file);

      const mime = (file.type || "").toLowerCase();

      if (mime.startsWith("video/") || videoExt.has(ext)) {
        mediaWrap.classList.add("upload-video-preview");
        const video = document.createElement("video");
        video.src = url;
        video.preload = "metadata";
        video.playsInline = true;
        video.muted = true;
        video.setAttribute("webkit-playsinline", "true");
        video.setAttribute("aria-label", file.name);

        const videoTrigger = document.createElement("button");
        videoTrigger.type = "button";
        videoTrigger.className = "upload-preview-video-trigger";
        videoTrigger.setAttribute("aria-label", `Play ${file.name}`);
        videoTrigger.textContent = "Play";

        const syncPlayingState = () => {
          const isPlaying = !video.paused && !video.ended;
          mediaWrap.classList.toggle("is-playing", isPlaying);
          videoTrigger.setAttribute(
            "aria-label",
            isPlaying ? `Pause ${file.name}` : `Play ${file.name}`,
          );
          videoTrigger.textContent = isPlaying ? "Pause" : "Play";
        };

        const toggleVideo = () => {
          if (video.paused || video.ended) {
            video.play().catch(() => {});
          } else {
            video.pause();
          }
        };

        videoTrigger.addEventListener("click", toggleVideo);
        video.addEventListener("click", toggleVideo);
        video.addEventListener("play", syncPlayingState);
        video.addEventListener("pause", syncPlayingState);
        video.addEventListener("ended", syncPlayingState);
        video.addEventListener("loadeddata", syncPlayingState);

        mediaWrap.appendChild(video);
        mediaWrap.appendChild(videoTrigger);
      } else if (mime.startsWith("image/") || imageExt.has(ext)) {
        const img = document.createElement("img");
        img.src = url;
        img.alt = file.name;
        mediaWrap.appendChild(img);
      } else {
        const fallback = document.createElement("div");
        fallback.className = "stamp";
        fallback.textContent = file.name;
        mediaWrap.appendChild(fallback);
      }

      const stamp = document.createElement("div");
      stamp.className = "stamp";
      stamp.textContent = file.name;

      const captionInput = document.createElement("input");
      captionInput.type = "text";
      captionInput.name = "media_captions[]";
      captionInput.className = "upload-preview-caption";
      captionInput.maxLength = captionCharLimit;
      captionInput.placeholder = defaultCaptionFromFilename(file.name);
      captionInput.setAttribute("aria-label", `Caption for ${file.name}`);
      captionInput.spellcheck = false;
      captionInput.autocomplete = "off";
      captionInput.inputMode = "text";
      captionInput.value = captions[index] ?? "";

      const captionLabel = document.createElement("div");
      captionLabel.className = "upload-preview-caption-label";
      captionLabel.textContent = "Caption";

      const captionCounter = document.createElement("div");
      captionCounter.className = "upload-preview-caption-counter";
      captionCounter.textContent = `0 / ${captionCharLimit} chars`;

      const captionHeader = document.createElement("div");
      captionHeader.className = "upload-preview-caption-header";
      captionHeader.appendChild(captionLabel);
      captionHeader.appendChild(captionCounter);

      const filenameToggleRow = document.createElement("label");
      filenameToggleRow.className = "upload-preview-caption-toggle";

      const filenameToggle = document.createElement("input");
      filenameToggle.type = "checkbox";
      filenameToggle.name = `use_filename_caption[${index}]`;
      filenameToggle.value = "1";
      filenameToggle.checked = useFilenameCaption[index] === true;
      filenameToggle.addEventListener("change", () => {
        useFilenameCaption[index] = filenameToggle.checked;
      });

      const filenameToggleText = document.createElement("span");
      filenameToggleText.textContent = "Use filename caption";
      filenameToggleRow.appendChild(filenameToggle);
      filenameToggleRow.appendChild(filenameToggleText);

      // show/hide filename toggle depending on whether caption already present
      if (String(captionInput.value || "").trim() !== "") {
        filenameToggleRow.style.display = "none";
      } else {
        filenameToggleRow.style.display = "";
      }

      captionInput.addEventListener("input", () => {
        const sanitized = sanitizeCaptionWhileTyping(captionInput.value);
        if (captionInput.value !== sanitized) {
          captionInput.value = sanitized;
        }
        captions[index] = normalizeCaption(captionInput.value);

        // update live counter
        const count = countCaptionChars(captionInput.value);
        captionCounter.textContent = `${Math.min(count, captionCharLimit)} / ${captionCharLimit} chars`;

        // hide filename toggle while user is typing; restore when cleared
        if (captionInput.value.trim() !== "") {
          filenameToggleRow.style.display = "none";
          filenameToggle.checked = false;
          useFilenameCaption[index] = false;
        } else {
          filenameToggleRow.style.display = "";
        }
      });

      card.appendChild(removeBtn);
      card.appendChild(mediaWrap);
      card.appendChild(captionHeader);
      card.appendChild(captionInput);
      card.appendChild(filenameToggleRow);
      card.appendChild(stamp);
      preview.appendChild(card);
    });
  };

  form.addEventListener("submit", () => {
    input.setCustomValidity("");
    if (!epochInput.value) {
      epochInput.value = String(Date.now());
    }

    const captionInputs = Array.from(
      preview.querySelectorAll('input[name="media_captions[]"]'),
    );
    const filenameToggles = Array.from(
      preview.querySelectorAll('input[name^="use_filename_caption["]'),
    );

    captionInputs.forEach((inputEl, idx) => {
      const filename = selectedFiles[idx]?.name || "";
      const fallbackChecked = filenameToggles[idx]?.checked === true;
      const resolved = resolveCaption(inputEl.value, filename, fallbackChecked);
      inputEl.value = resolved;
      captions[idx] = resolved;
      useFilenameCaption[idx] = fallbackChecked;
    });
  });

  input.addEventListener("change", () => {
    const files = Array.from(input.files || []);
    const images = [];
    const videos = [];
    const rejected = [];

    setStatus(defaultStatus, false);

    if (files.length === 0) {
      selectedFiles = [];
      captions = [];
      useFilenameCaption = [];
      syncInputFiles();
      render();
      return;
    }

    files.forEach((file) => {
      const kind = classifyFile(file);
      if (kind === "image") {
        if (file.size > maxImageSizeBytes) {
          rejected.push(file.name);
          return;
        }
        images.push(file);
        return;
      }

      if (kind === "video") {
        if (file.size > maxVideoSizeBytes) {
          rejected.push(file.name);
          return;
        }
        videos.push(file);
        return;
      }

      rejected.push(file.name);
    });

    if (images.length === 0 && videos.length === 0) {
      selectedFiles = [];
      captions = [];
      useFilenameCaption = [];
      syncInputFiles();
      render();
      setStatus(
        rejected.length > 0
          ? "No valid images or videos selected."
          : defaultStatus,
        rejected.length > 0,
      );
      return;
    }

    if (images.length > 0 && videos.length > 0) {
      selectedFiles = [];
      captions = [];
      useFilenameCaption = [];
      syncInputFiles();
      render();
      setStatus("Select either images or one video per upload.", true);
      return;
    }

    if (videos.length > 0) {
      selectedFiles = [videos[0]];
      captions = selectedFiles.map(() => "");
      useFilenameCaption = selectedFiles.map(() => false);
      syncInputFiles();
      render();

      if (videos.length > 1) {
        setStatus(
          "Only one video can be uploaded at a time. Keeping the first video.",
          false,
        );
      } else if (rejected.length > 0) {
        setStatus(
          "Ignored unsupported or oversized files. Only the video limit applies.",
          false,
        );
      } else {
        setStatus(defaultStatus, false);
      }
      return;
    }

    const trimmedImages = images.slice(0, maxImagesPerUpload);
    selectedFiles = trimmedImages;
    captions = selectedFiles.map(() => "");
    useFilenameCaption = selectedFiles.map(() => false);
    render();

    if (images.length > maxImagesPerUpload) {
      setStatus(
        `Only the first ${maxImagesPerUpload} images were kept.`,
        false,
      );
    } else if (rejected.length > 0) {
      setStatus("Ignored unsupported or oversized files.", false);
    } else {
      setStatus(defaultStatus, false);
    }
  });

  if (!input.files || input.files.length === 0) {
    selectedFiles = [];
    captions = [];
    useFilenameCaption = [];
    render();
    setStatus(defaultStatus, false);
  }
})();

// Upload media toggle removed: inline upload form always visible now.

(() => {
  const markButtons = Array.from(
    document.querySelectorAll(".mark-delete-btn[data-post-id]"),
  );
  const form = document.getElementById("pendingDeleteForm");
  const inputs = document.getElementById("pendingDeleteInputs");
  const countEl = document.getElementById("pendingDeleteCount");
  const cancelBtn = document.getElementById("cancelDeleteBtn");
  const saveDeleteBtn = document.getElementById("saveDeleteBtn");
  const saveCloseBtns = Array.from(
    document.querySelectorAll("#saveCancelUploadBtn, #saveCloseUploadBtn"),
  );
  const closeAfterSaveInput = document.getElementById("closeAfterSaveInput");

  if (!markButtons.length || !form || !inputs || !countEl || !cancelBtn) {
    saveCloseBtns.forEach((saveCloseBtn) => {
      saveCloseBtn.addEventListener("click", () => {
        const closeUrl = saveCloseBtn.getAttribute("data-close-url");
        if (closeUrl) window.location.href = closeUrl;
      });
    });
    return;
  }

  const selected = new Set();

  const refreshState = () => {
    inputs.innerHTML = "";
    selected.forEach((postId) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "page_post_ids[]";
      input.value = postId;
      inputs.appendChild(input);
    });

    const count = selected.size;
    countEl.textContent = `${count} selected for delete.`;
    form.classList.toggle("active", count > 0);
  };

  markButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const postId = button.getAttribute("data-post-id") || "";
      if (!postId) return;

      const article = button.closest(".item");
      if (selected.has(postId)) {
        selected.delete(postId);
        button.textContent = "Delete";
        if (article) article.classList.remove("marked-delete");
      } else {
        selected.add(postId);
        button.textContent = "Undo";
        if (article) article.classList.add("marked-delete");
      }

      refreshState();
    });
  });

  cancelBtn.addEventListener("click", () => {
    selected.clear();
    markButtons.forEach((button) => {
      button.textContent = "Delete";
      const article = button.closest(".item");
      if (article) article.classList.remove("marked-delete");
    });
    if (closeAfterSaveInput) closeAfterSaveInput.value = "0";
    refreshState();
  });

  if (saveDeleteBtn) {
    saveDeleteBtn.addEventListener("click", (event) => {
      if (selected.size <= 0) {
        event.preventDefault();
        return;
      }
    });
  }

  saveCloseBtns.forEach((saveCloseBtn) => {
    saveCloseBtn.addEventListener("click", () => {
      const closeUrl = saveCloseBtn.getAttribute("data-close-url");
      if (selected.size > 0) {
        if (closeAfterSaveInput) closeAfterSaveInput.value = "1";
        form.submit();
        return;
      }
      if (closeUrl) window.location.href = closeUrl;
    });
  });

  refreshState();
})();

(() => {
  const form = document.getElementById("deleteBlogForm");
  const composeConfirmInput = document.getElementById(
    "deleteBlogConfirmCompose",
  );
  const irreversibleConfirmInput = document.getElementById(
    "deleteBlogConfirmIrreversible",
  );
  const downloadBackupBtn = document.getElementById("downloadBackupBtn");
  const modal = document.getElementById("blogActionModal");
  const modalClose = document.getElementById("blogActionModalClose");
  const modalKicker = document.getElementById("blogActionModalKicker");
  const modalTitle = document.getElementById("blogActionModalTitle");
  const modalBody = document.getElementById("blogActionModalBody");
  const modalActions = document.getElementById("blogActionModalActions");
  const deleteComposeUrl = document.getElementById("deleteBlogComposeUrl");
  if (
    !form ||
    !composeConfirmInput ||
    !irreversibleConfirmInput ||
    !modal ||
    !modalClose ||
    !modalKicker ||
    !modalTitle ||
    !modalBody ||
    !modalActions
  ) {
    return;
  }

  const body = document.body;
  const downloadUrl = downloadBackupBtn?.getAttribute("href") || "";
  const backupEstimateBytes = Number(body?.dataset?.backupEstimateBytes || "0");

  const formatBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return "0 B";
    const units = ["B", "KB", "MB", "GB", "TB"];
    let value = bytes;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
      value /= 1024;
      unitIndex += 1;
    }
    return `${value >= 10 || unitIndex === 0 ? value.toFixed(0) : value.toFixed(1)} ${units[unitIndex]}`;
  };

  const closeModal = () => {
    modal.hidden = true;
    body.classList.remove("modal-open");
    modalActions.innerHTML = "";
    modalBody.innerHTML = "";
    modalTitle.textContent = "";
    modalKicker.textContent = "";
  };

  const openModal = ({
    kicker = "",
    title,
    bodyLines = [],
    primaryLabel,
    primaryClass = "ui-btn ui-btn-strong",
    secondaryLabel,
    secondaryClass = "ui-btn",
    onPrimary,
    onSecondary,
  }) => {
    modalKicker.textContent = kicker;
    modalTitle.textContent = title;
    modalBody.innerHTML = "";
    bodyLines.forEach((line) => {
      if (typeof line === "string") {
        const paragraph = document.createElement("p");
        paragraph.textContent = line;
        modalBody.appendChild(paragraph);
        return;
      }
      modalBody.appendChild(line);
    });

    modalActions.innerHTML = "";
    if (secondaryLabel) {
      const secondaryBtn = document.createElement("button");
      secondaryBtn.type = "button";
      secondaryBtn.className = secondaryClass;
      secondaryBtn.textContent = secondaryLabel;
      secondaryBtn.addEventListener("click", () => {
        if (onSecondary) onSecondary();
        else closeModal();
      });
      modalActions.appendChild(secondaryBtn);
    }

    if (primaryLabel) {
      const primaryBtn = document.createElement("button");
      primaryBtn.type = "button";
      primaryBtn.className = primaryClass;
      primaryBtn.textContent = primaryLabel;
      primaryBtn.addEventListener("click", () => {
        if (onPrimary) onPrimary();
      });
      modalActions.appendChild(primaryBtn);
    }

    modal.hidden = false;
    body.classList.add("modal-open");
  };

  const openDeleteConfirm = () => {
    openModal({
      kicker: "Account deletion",
      title: "Change to compose mode first?",
      bodyLines: [
        "Delete media manually in compose mode before removing the blog permanently.",
        "If you keep going, the next step will ask for a permanent deletion confirmation.",
      ],
      primaryLabel: "Change to compose mode",
      secondaryLabel: "Keep deleting",
      secondaryClass: "ui-btn",
      primaryClass: "ui-btn ui-btn-strong",
      onPrimary: () => {
        const composeUrl =
          deleteComposeUrl?.value ||
          deleteComposeUrl?.getAttribute("value") ||
          "";
        if (composeUrl) {
          window.location.href = composeUrl;
        }
        closeModal();
      },
      onSecondary: openDeleteFinalConfirm,
    });
  };

  const openDeleteFinalConfirm = () => {
    openModal({
      kicker: "Final warning",
      title: "Delete everything permanently?",
      bodyLines: [
        "This removes the blog, media files, and backend data.",
        "This cannot be undone.",
      ],
      primaryLabel: "Delete everything permanently",
      primaryClass: "ui-btn danger-btn",
      secondaryLabel: "Cancel",
      secondaryClass: "ui-btn",
      onPrimary: () => {
        composeConfirmInput.value = "1";
        irreversibleConfirmInput.value = "1";
        form.submit();
      },
      onSecondary: closeModal,
    });
  };

  const openDownloadModal = () => {
    const estimateText = formatBytes(backupEstimateBytes);
    const estimateLine = document.createElement("div");
    estimateLine.className = "blog-modal-estimate";
    estimateLine.innerHTML = `Estimated backup size: <strong>${estimateText}</strong>`;

    openModal({
      kicker: "Download backup",
      title: "Download your whole blog?",
      bodyLines: [
        "The ZIP includes blog data and uploaded media.",
        estimateLine,
      ],
      primaryLabel: "Download",
      secondaryLabel: "Cancel",
      primaryClass: "ui-btn ui-btn-strong",
      secondaryClass: "ui-btn",
      onPrimary: () => {
        closeModal();
        if (downloadUrl) {
          window.location.href = downloadUrl;
        }
      },
      onSecondary: closeModal,
    });
  };

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    composeConfirmInput.value = "0";
    irreversibleConfirmInput.value = "0";
    openDeleteConfirm();
  });

  if (downloadBackupBtn) {
    downloadBackupBtn.addEventListener("click", (event) => {
      event.preventDefault();
      openDownloadModal();
    });
  }

  modalClose.addEventListener("click", () => {
    if (modalTitle.textContent === "Delete everything permanently?") {
      closeModal();
      return;
    }
    if (modalTitle.textContent === "Change to compose mode first?") {
      openDeleteFinalConfirm();
      return;
    }
    closeModal();
  });

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      if (modalTitle.textContent === "Change to compose mode first?") {
        openDeleteFinalConfirm();
        return;
      }
      closeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || modal.hidden) return;
    if (modalTitle.textContent === "Change to compose mode first?") {
      openDeleteFinalConfirm();
      return;
    }
    closeModal();
  });
})();
