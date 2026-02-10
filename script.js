/* ============================================
   Isaac & Corine Wedding — Script
   ============================================ */

(function () {
  "use strict";

  // Wedding date: June 26, 2026, 6:00 PM (Lebanon time, UTC+3)
  const WEDDING_DATE = new Date("2026-06-26T18:00:00+03:00");

  /* ---------- DOM refs ---------- */
  const overlay      = document.getElementById("intro-overlay");
  const introVideo   = document.getElementById("intro-video");
  const introPrompt  = document.getElementById("intro-prompt");
  const mainContent  = document.getElementById("main-content");
  const mainNav      = document.getElementById("main-nav");
  const musicToggle  = document.getElementById("music-toggle");
  const musicIconOn  = document.getElementById("music-icon-on");
  const musicIconOff = document.getElementById("music-icon-off");
  const bgMusic      = document.getElementById("bg-music");
  const hamburger    = document.getElementById("nav-hamburger");
  const navLinks     = document.getElementById("nav-links");
  const rsvpForm     = document.getElementById("rsvp-form");
  const rsvpSuccess  = document.getElementById("rsvp-success");
  const rsvpMessage  = document.getElementById("rsvp-success-message");

  /* ---------- Intro overlay ---------- */
  let introState = "idle"; // idle | playing | fading

  function handleIntroClick() {
    if (introState !== "idle") return;
    introState = "playing";
    if (introPrompt) introPrompt.style.display = "none";
    introVideo.play().catch(function () {
      revealMain();
    });
  }

  function handleTimeUpdate() {
    if (!introVideo) return;
    var remaining = introVideo.duration - introVideo.currentTime;
    if (remaining <= 0.8 && introState === "playing") {
      introState = "fading";
      overlay.classList.add("fading");
      setTimeout(revealMain, 800);
    }
  }

  function handleVideoEnded() {
    if (introState !== "fading") revealMain();
  }

  overlay.addEventListener("click", handleIntroClick);
  introVideo.addEventListener("timeupdate", handleTimeUpdate);
  introVideo.addEventListener("ended", handleVideoEnded);

  function revealMain() {
    overlay.style.display = "none";
    mainContent.classList.remove("hidden");
    mainNav.classList.remove("hidden");
    musicToggle.classList.remove("hidden");

    // Smooth fade-in
    mainContent.classList.add("page-fade-in");
    mainNav.classList.add("page-fade-in");

    // Try to start music
    bgMusic.volume = 0.3;
    musicPlaying = true;
    bgMusic.play().catch(function () { /* user interaction needed */ });

    // Start countdown
    startCountdown();

    // Observe fade-in elements
    observeFadeIns();
  }

  /* ---------- Music toggle ---------- */
  let musicPlaying = false;

  musicToggle.addEventListener("click", function () {
    if (musicPlaying) {
      bgMusic.pause();
      musicIconOn.classList.add("hidden");
      musicIconOff.classList.remove("hidden");
      musicPlaying = false;
    } else {
      bgMusic.play().catch(function () {});
      musicIconOff.classList.add("hidden");
      musicIconOn.classList.remove("hidden");
      musicPlaying = true;
    }
  });

  /* ---------- Navigation ---------- */
  // Hamburger
  hamburger.addEventListener("click", function () {
    hamburger.classList.toggle("open");
    navLinks.classList.toggle("open");
  });

  // Close mobile nav on link click & handle clicks while on discover page
  navLinks.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function (e) {
      hamburger.classList.remove("open");
      navLinks.classList.remove("open");

      var href = link.getAttribute("href");
      // If we're on the discover page and clicking a non-discover link,
      // switch back to main content first, then scroll to the target section.
      if (href !== "#discover" && discoverContent && !discoverContent.classList.contains("hidden")) {
        e.preventDefault();
        discoverContent.classList.add("page-leaving");
        setTimeout(function () {
          discoverContent.classList.add("hidden");
          discoverContent.classList.remove("page-leaving", "page-fade-in");
          mainContent.classList.remove("hidden");
          mainContent.classList.add("page-fade-in");
          // Scroll to the target section
          var target = document.querySelector(href);
          if (target) {
            target.scrollIntoView({ behavior: "smooth" });
          }
          navItems.forEach(function (a) { a.classList.remove("active"); });
          if (link) link.classList.add("active");
        }, 400);
      }
    });
  });

  // Active link on scroll
  const sections = document.querySelectorAll("section[id]");
  const navItems = navLinks.querySelectorAll("a");

  function updateActiveNav() {
    const scrollY = window.scrollY + 120;
    sections.forEach(function (section) {
      const top = section.offsetTop;
      const height = section.offsetHeight;
      const id = section.getAttribute("id");
      if (scrollY >= top && scrollY < top + height) {
        navItems.forEach(function (a) {
          a.classList.remove("active");
          if (a.getAttribute("href") === "#" + id) a.classList.add("active");
        });
      }
    });
  }

  // Hide/show nav on scroll
  let lastScrollY = 0;
  function handleNavScroll() {
    const current = window.scrollY;
    if (current > lastScrollY && current > 200) {
      mainNav.classList.add("nav-hidden");
    } else {
      mainNav.classList.remove("nav-hidden");
    }
    lastScrollY = current;
    updateActiveNav();
  }
  window.addEventListener("scroll", handleNavScroll, { passive: true });

  /* ---------- Countdown ---------- */
  function startCountdown() {
    function update() {
      const now = new Date();
      const diff = WEDDING_DATE - now;

      if (diff <= 0) {
        document.getElementById("cd-days").textContent    = "0";
        document.getElementById("cd-hours").textContent   = "0";
        document.getElementById("cd-minutes").textContent = "0";
        document.getElementById("cd-seconds").textContent = "0";
        return;
      }

      const days    = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours   = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const minutes = Math.floor((diff / (1000 * 60)) % 60);
      const seconds = Math.floor((diff / 1000) % 60);

      document.getElementById("cd-days").textContent    = days;
      document.getElementById("cd-hours").textContent   = String(hours).padStart(2, "0");
      document.getElementById("cd-minutes").textContent = String(minutes).padStart(2, "0");
      document.getElementById("cd-seconds").textContent = String(seconds).padStart(2, "0");
    }

    update();
    setInterval(update, 1000);
  }

  /* ---------- Fade-in on scroll ---------- */
  function observeFadeIns() {
    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );

    document.querySelectorAll(".fade-in").forEach(function (el) {
      observer.observe(el);
    });
  }

  /* ---------- Discover page toggle (in-page, keeps music playing) ---------- */
  var discoverContent = document.getElementById("discover-content");
  var backBtn = document.getElementById("back-to-wedding");

  function showDiscover(e) {
    if (e) e.preventDefault();
    mainContent.classList.add("page-leaving");
    setTimeout(function () {
      mainContent.classList.add("hidden");
      mainContent.classList.remove("page-leaving");
      discoverContent.classList.remove("hidden");
      discoverContent.classList.add("page-fade-in");
      window.scrollTo(0, 0);
      // Update active nav
      navItems.forEach(function (a) { a.classList.remove("active"); });
      var discoverLink = navLinks.querySelector('a[href="#discover"]');
      if (discoverLink) discoverLink.classList.add("active");
      // Observe fade-ins in discover
      discoverContent.querySelectorAll(".fade-in").forEach(function (el) {
        el.classList.remove("visible");
      });
      var discoverObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            discoverObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1 });
      discoverContent.querySelectorAll(".fade-in").forEach(function (el) {
        discoverObserver.observe(el);
      });
    }, 400);
  }

  function showMain(e) {
    if (e) e.preventDefault();
    discoverContent.classList.add("page-leaving");
    setTimeout(function () {
      discoverContent.classList.add("hidden");
      discoverContent.classList.remove("page-leaving", "page-fade-in");
      mainContent.classList.remove("hidden");
      mainContent.classList.add("page-fade-in");
      window.scrollTo(0, 0);
      // Update active nav
      navItems.forEach(function (a) { a.classList.remove("active"); });
    }, 400);
  }

  // Discover links
  document.querySelectorAll('a[href="#discover"]').forEach(function (link) {
    link.addEventListener("click", showDiscover);
  });

  // Back to wedding
  if (backBtn) backBtn.addEventListener("click", showMain);

  /* ---------- Accordion ---------- */
  document.querySelectorAll(".accordion-trigger").forEach(function (trigger) {
    trigger.addEventListener("click", function () {
      const item = trigger.closest(".accordion-item");
      const isOpen = item.classList.contains("open");

      // Close siblings in same accordion
      const accordion = trigger.closest(".accordion");
      if (accordion) {
        accordion.querySelectorAll(".accordion-item.open").forEach(function (openItem) {
          openItem.classList.remove("open");
        });
      }

      if (!isOpen) item.classList.add("open");
    });
  });

  /* ---------- Radio card selection ---------- */
  document.querySelectorAll(".radio-label").forEach(function (label) {
    var radio = label.querySelector('input[type="radio"]');
    if (!radio) return;
    radio.addEventListener("change", function () {
      document.querySelectorAll(".radio-label").forEach(function (l) {
        l.classList.remove("selected");
      });
      label.classList.add("selected");
    });
  });

  /* ---------- Confetti overlay ---------- */
  function showConfetti() {
    var el = document.createElement("div");
    el.className = "confetti-overlay";
    var img = document.createElement("img");
    img.src = "./assets/confetti-CrGrT4ka.gif";
    img.alt = "";
    el.appendChild(img);
    document.body.appendChild(el);
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 4200);
  }

  /* ---------- RSVP form ---------- */
  rsvpForm.addEventListener("submit", function (e) {
    e.preventDefault();

    var name      = document.getElementById("rsvp-name").value.trim();
    var guests    = document.getElementById("rsvp-guests").value;
    var attending = rsvpForm.querySelector('input[name="attending"]:checked');

    if (!name || !guests || !attending) return;

    var isAttending = attending.value === "yes";

    // Store locally (backend integration can be added later)
    try {
      var rsvps = JSON.parse(localStorage.getItem("wedding_rsvps") || "[]");
      rsvps.push({
        name: name,
        guests: guests,
        attending: isAttending,
        timestamp: new Date().toISOString()
      });
      localStorage.setItem("wedding_rsvps", JSON.stringify(rsvps));
    } catch (err) { /* ignore storage errors */ }

    // Show confetti if attending
    if (isAttending) {
      showConfetti();
    }

    // Show success
    rsvpForm.classList.add("hidden");
    rsvpSuccess.classList.remove("hidden");

    if (isAttending) {
      rsvpMessage.textContent = "We can't wait to celebrate with you, " + name + "!";
    } else {
      rsvpMessage.textContent = "We'll miss you, " + name + ". Thank you for letting us know.";
    }
  });

})();
