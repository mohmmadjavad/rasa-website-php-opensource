/* ===========================================================
   index.js
   اسکریپت‌های اختصاصی صفحه اصلی (index.html):
   انیمیشن هیرو، کاروسل تیم، مودال تیم، انیمیشن کارت‌های پروژه
   و کاروسل سه‌بعدی افتخارات.
   اسکریپت‌های مشترک بین همه صفحات در js/main.js قرار دارند.
   =========================================================== */
document.addEventListener("DOMContentLoaded", () => {
  initHeroScroll();
  initTeamCarousel();
  initTeamModalController();
  initProjectCardsAnimation();
  initAwards3DCarousel();
});

/* ---------------------------------------------------------
   3) Hero scroll sequence
--------------------------------------------------------- */
function initHeroScroll() {
  const hero = document.querySelector(".hero");
  if (!hero || typeof gsap === "undefined") return;

  gsap.registerPlugin(ScrollTrigger);
  ScrollTrigger.config({ ignoreMobileResize: true });

  ScrollTrigger.normalizeScroll({
    type: "touch",
    momentum: (self) => Math.min(3, Math.abs(self.velocityY) / 1000),
  });

  const stage = hero.querySelector(".hero-stage");
  const figure = hero.querySelector(".hero-figure");
  const headlines = hero.querySelectorAll(".hero-headline");
  const reveal = hero.querySelector(".hero-reveal");
  const navPill = document.querySelector(".nav-pill");

  const vh = window.innerHeight;
  const isDesktop = window.matchMedia("(min-width:900px)").matches;
  const distance = vh * 1.5;

  const HEADLINE_TRAVEL = isDesktop ? 0.32 : 0.36;
  const FIGURE_PARALLAX = isDesktop ? 0.3 : 0.42;

  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: hero,
      start: "top top",
      end: `+=${distance}`,
      scrub: true,
      pin: true,
      anticipatePin: 1,
    },
  });

  tl.to(stage, { y: -vh * 0.14, ease: "none", duration: 1 }, 0);
  tl.to(figure, { y: -vh * FIGURE_PARALLAX, ease: "none", duration: 1 }, 0);
  tl.to(headlines, { y: vh * HEADLINE_TRAVEL, scale: 0.56, ease: "none", duration: 1 }, 0);

  tl.call(() => navPill.classList.remove("is-visible"), null, 0);
  tl.call(() => navPill.classList.add("is-visible"), null, 0.05);

  tl.to(reveal, { opacity: 1, duration: 0.001 }, 0.82);
}

/* ---------------------------------------------------------
   4) Team section — Responsive 3D 10-Card Loop Carousel
--------------------------------------------------------- */
function initTeamCarousel() {
  const viewport = document.getElementById("teamCarousel");
  const track = document.getElementById("teamTrack");
  const dotsWrap = document.getElementById("teamDots");
  if (!viewport || !track) return;

  const cards = Array.from(track.children);
  const totalCards = cards.length;
  if (totalCards === 0) return;

  const dots = dotsWrap ? Array.from(dotsWrap.children) : [];
  let currentIndex = 0;
  let autoplayTimer = null;
  const AUTOPLAY_DELAY = 3000; 

  let startX = 0;
  let isDragging = false;

  track.style.position = "relative";
  track.style.width = "100%";
  track.style.height = "100%";
  
  cards.forEach(card => {
    card.style.position = "absolute";
    card.style.top = "50%";
    card.style.left = "50%";
    card.style.transformOrigin = "center center"; 
    card.style.transition = "transform 0.7s cubic-bezier(0.2, 1, 0.25, 1), opacity 0.7s ease, visibility 0.7s";
  });

  function updateCarousel() {
    const isDesktop = window.matchMedia("(min-width: 900px)").matches;
    const stepX = isDesktop ? window.innerWidth * 0.22 : window.innerWidth * 0.32; 

    cards.forEach((card, i) => {
      let offset = i - currentIndex;
      if (offset > totalCards / 2) offset -= totalCards;
      if (offset < -totalCards / 2) offset += totalCards;

      const absOffset = Math.abs(offset);
      const maxVisibleRadius = isDesktop ? 2 : 1; 

      if (absOffset <= maxVisibleRadius) {
        card.style.visibility = "visible";
        
        const opacityValue = absOffset === 0 ? "1" : (absOffset === 1 ? "0.7" : "0.4");
        const blurValue = absOffset === 0 ? "0px" : (absOffset === 1 ? "2px" : "5px");
        
        card.style.opacity = opacityValue;
        card.style.filter = `blur(${blurValue})`;

        const scale = absOffset === 0 ? 1 : (absOffset === 1 ? 0.78 : 0.55);
        const dip = absOffset === 0 ? 0 : (absOffset === 1 ? 18 : 38);
        const rotateY = offset === 0 ? 0 : (offset > 0 ? -12 : 12);
        const translateX = -offset * stepX; 

        const zIndex = 100 - absOffset;

        card.style.transform = `translate3d(calc(-50% + ${translateX}px), calc(-50% + ${dip}px), 0) scale(${scale}) rotateY(${rotateY}deg)`;
        card.style.zIndex = zIndex;
      } else {
        card.style.visibility = "hidden";
        card.style.opacity = "0";
        card.style.filter = "blur(8px)";
        card.style.transform = `translate3d(-50%, -50%, 0) scale(0.4)`;
      }
    });

    dots.forEach((dot, i) => dot.classList.toggle("is-active", i === currentIndex));
  }

  function next() {
    currentIndex = (currentIndex + 1) % totalCards;
    updateCarousel();
  }

  function prev() {
    currentIndex = (currentIndex - 1 + totalCards) % totalCards;
    updateCarousel();
  }

  function startAutoplay() {
    stopAutoplay();
    autoplayTimer = setInterval(next, AUTOPLAY_DELAY);
  }

  function stopAutoplay() {
    if (autoplayTimer) clearInterval(autoplayTimer);
  }

  dots.forEach((dot, i) => {
    dot.addEventListener("click", () => {
      stopAutoplay();
      currentIndex = i;
      updateCarousel();
      startAutoplay();
    });
  });

  function handleStart(clientX) {
    startX = clientX;
    isDragging = true;
    stopAutoplay();
  }

  function handleMove(clientX) {
    if (!isDragging) return;
    const diff = clientX - startX;
    
    if (Math.abs(diff) > 45) { 
      // جهت درگ بخش تیم: حرکت دست به راست = کارت بعدی | حرکت دست به چپ = کارت قبلی
      if (diff > 0) {
        next(); 
      } else {
        prev(); 
      }
      isDragging = false; 
    }
  }

  function handleEnd() {
    isDragging = false;
    startAutoplay();
  }

  viewport.addEventListener("touchstart", (e) => handleStart(e.touches[0].clientX), { passive: true });
  viewport.addEventListener("touchmove", (e) => handleMove(e.touches[0].clientX), { passive: true });
  viewport.addEventListener("touchend", handleEnd, { passive: true });

  viewport.addEventListener("mousedown", (e) => {
    viewport.classList.add("is-dragging");
    handleStart(e.clientX);
  });
  window.addEventListener("mousemove", (e) => handleMove(e.clientX));
  window.addEventListener("mouseup", () => {
    viewport.classList.remove("is-dragging");
    handleEnd();
  });

  updateCarousel();
  startAutoplay();

  window.addEventListener("resize", updateCarousel);
  window.addEventListener("load", updateCarousel);
}

/* ---------------------------------------------------------
   5) Team Dynamic Modal Controller
--------------------------------------------------------- */
const MODAL_SOCIAL_SVG = {
  telegram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 4-9 16-3-7-7-3Z"/><path d="M21 4 8.5 13.2"/></svg>',
  whatsapp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
  instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>',
  pinterest: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 18c.6-1.7 1.6-5.3 1.6-5.3M12 8.5c2.2 0 3.5 1.4 3.5 3.3 0 2.4-1.3 4.4-3.2 4.4-1 0-1.8-.6-2-1.3"/></svg>',
  x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 4 16 16M20 4 4 20"/></svg>',
  linkedin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="17" height="17" rx="3"/><circle cx="8" cy="9" r="1"/><path d="M8 11.5v5.5M12 17v-3.5c0-1.2 1-2 2-2s2 .8 2 2V17"/></svg>',
  github: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.4c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1-.3-3.4 1.2a11.8 11.8 0 0 0-6.2 0C6.6 3 5.6 3.3 5.6 3.3a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4.2 9.7c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/></svg>',
  youtube: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5Z" fill="currentColor" stroke="none"/></svg>',
  website: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/></svg>',
  email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M4.5 7 12 13l7.5-6"/></svg>',
  phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
  custom: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/></svg>',
};

function initTeamModalController() {
  const modal = document.getElementById("teamModal");
  const cards = document.querySelectorAll(".team-card");

  if (!modal) return;

  cards.forEach(card => {
    card.addEventListener("click", () => {
      const name = card.getAttribute("data-name") || "";
      const job = card.getAttribute("data-job") || "";
      const img = card.getAttribute("data-img") || "";
      const bio = card.getAttribute("data-bio") || "";
      const profileUrl = card.getAttribute("data-profile") || "";
      let socials = [];
      try {
        socials = JSON.parse(card.getAttribute("data-socials") || "[]");
      } catch (e) {
        socials = [];
      }

      document.getElementById("modalName").textContent = name;
      document.getElementById("modalImg").src = img;

      const roleEl = document.getElementById("modalRole");
      roleEl.textContent = job;
      roleEl.style.display = job ? "block" : "none";

      const bioEl = document.getElementById("modalBio");
      bioEl.textContent = bio;
      bioEl.style.display = bio ? "block" : "none";

      const socialsWrap = document.getElementById("modalSocials");
      socialsWrap.innerHTML = socials
        .map(
          (s) => `
        <a href="${s.url}" target="_blank" rel="noopener" class="social-item" aria-label="${s.label} ${name}">
          <span class="social-icon-link">${MODAL_SOCIAL_SVG[s.key] || MODAL_SOCIAL_SVG.website}</span>
          <span class="social-label">${s.label}</span>
        </a>`
        )
        .join("");

      const profileBtn = document.getElementById("modalProfileBtn");
      if (profileUrl) {
        profileBtn.href = profileUrl;
        profileBtn.style.display = "inline-flex";
      } else {
        profileBtn.style.display = "none";
      }

      modal.classList.add("is-open");
    });
  });
}

function closeTeamModal() {
  const modal = document.getElementById("teamModal");
  if (modal) {
    modal.classList.remove("is-open");
  }
}

window.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeTeamModal();
});

/* ---------------------------------------------------------
   6) Creative 3D Tilt & Glow Effect for Project Cards
--------------------------------------------------------- */
function initProjectCardsAnimation() {
  const cards = document.querySelectorAll('.creative-tilt');

  cards.forEach(card => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left; 
      const y = e.clientY - rect.top; 
      
      card.style.setProperty('--x', `${x}px`);
      card.style.setProperty('--y', `${y}px`);
      
      const multiplier = 15;
      const rotateX = ((y / rect.height) - 0.5) * -multiplier;
      const rotateY = ((x / rect.width) - 0.5) * multiplier;
      
      card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = `rotateX(0deg) rotateY(0deg)`;
      const glow = card.querySelector('.project-glow');
      if (glow) glow.style.opacity = '0';
    });

    card.addEventListener('touchstart', (e) => {
      const touch = e.touches[0];
      const rect = card.getBoundingClientRect();
      const x = touch.clientX - rect.left;
      const y = touch.clientY - rect.top;
      
      card.style.setProperty('--x', `${x}px`);
      card.style.setProperty('--y', `${y}px`);
      card.style.transform = `scale(0.98) rotateX(4deg)`;
    }, { passive: true });

    card.addEventListener('touchend', () => {
      card.style.transform = `rotateX(0deg) rotateY(0deg) scale(1)`;
    });
  });
}

/* ---------------------------------------------------------
   7) 3D Virtual Trophies Carousel (ویترین افتخارات رسا)
--------------------------------------------------------- */
function initAwards3DCarousel() {
  const viewport = document.getElementById("awardsCarousel");
  const track = document.getElementById("awardsTrack");
  if (!viewport || !track) return;

  const items = Array.from(track.children);
  const totalItems = items.length;
  if (totalItems === 0) return;

  let currentIndex = 0;
  let autoplayTimer = null;
  const AUTOPLAY_DELAY = 3000;

  let startX = 0;
  let isDragging = false;
  let globalTime = 0;

  track.style.position = "relative";
  track.style.width = "100%";
  track.style.height = "100%";

  items.forEach(item => {
    item.style.position = "absolute";
    item.style.top = "50%";
    item.style.left = "50%";
    item.style.transformOrigin = "center center";
    item.style.transition = "transform 0.65s cubic-bezier(0.2, 1, 0.25, 1), opacity 0.65s ease, filter 0.65s";
  });

  const fallbackSVG = `
    <svg class="award-fallback-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6v5Zm0 0v8a4 4 0 0 0 4 4h4a4 4 0 0 0 4-4V9M6 9h12M18 9h1.5a2.5 2.5 0 0 0 0-5H18v5Zm-6 12v2m-4 0h8" stroke="#0c8d8d" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  `;

  items.forEach(item => {
    const img = item.querySelector('img');
    if(img) {
      img.onerror = () => { item.innerHTML = fallbackSVG; };
    }
  });

  function updateAwards() {
    const isDesktop = window.matchMedia("(min-width: 900px)").matches;
    const isTablet = window.matchMedia("(min-width: 600px)").matches;
    
    let stepX = isDesktop ? window.innerWidth * 0.24 : (isTablet ? window.innerWidth * 0.32 : window.innerWidth * 0.42);
    let stepZ = isDesktop ? 180 : 120;

    items.forEach((item, i) => {
      let offset = i - currentIndex;
      
      if (offset > totalItems / 2) offset -= totalItems;
      if (offset < -totalItems / 2) offset += totalItems;

      const absOffset = Math.abs(offset);

      if (absOffset <= 1.4) {
        item.style.visibility = "visible";

        let scale = 1 - (absOffset * 0.32); 
        let opacity = 1 - (absOffset * 0.55);
        let blur = absOffset * 6;
        let rotateY = -offset * 35;
        let transX = -offset * stepX;
        let transZ = -absOffset * stepZ;

        item.dataset.transX = transX;
        item.dataset.transZ = transZ;
        item.dataset.scale = scale;
        item.dataset.rotateY = rotateY;
        item.dataset.absOffset = absOffset;

        item.style.opacity = Math.max(0, Math.min(1, opacity));
        item.style.filter = `blur(${blur}px)`;
        item.style.zIndex = Math.round(100 - absOffset * 10);
        
        item.style.transform = `translate3d(calc(-50% + ${transX}px), -50%, ${transZ}px) scale(${scale}) rotateY(${rotateY}deg)`;
      } else {
        item.style.visibility = "hidden";
        item.style.opacity = "0";
        item.style.transform = `translate3d(-50%, -50%, -300px) scale(0.3)`;
      }
    });
  }

  function floatLoop() {
    globalTime += 0.025;
    
    items.forEach((item) => {
      if (item.style.visibility === "visible" && item.dataset.absOffset) {
        const absOffset = parseFloat(item.dataset.absOffset);
        const transX = parseFloat(item.dataset.transX);
        const transZ = parseFloat(item.dataset.transZ);
        const scale = parseFloat(item.dataset.scale);
        const rotateY = parseFloat(item.dataset.rotateY);

        const centerFactor = 1 - Math.min(1, absOffset);
        const floatY = Math.sin(globalTime) * 12 * centerFactor; 
        const floatTilt = Math.cos(globalTime * 0.6) * 1.5 * centerFactor;

        item.style.transform = `translate3d(calc(-50% + ${transX}px), calc(-50% + ${floatY}px), ${transZ}px) scale(${scale}) rotateY(${rotateY + floatTilt}deg)`;
      }
    });
    
    requestAnimationFrame(floatLoop);
  }

  function next() {
    currentIndex = (currentIndex + 1) % totalItems;
    updateAwards();
  }

  function prev() {
    currentIndex = (currentIndex - 1 + totalItems) % totalItems;
    updateAwards();
  }

  function handleStart(clientX) {
    startX = clientX;
    isDragging = true;
    stopAutoplay();
  }

  function handleMove(clientX) {
    if (!isDragging) return;
    const diff = clientX - startX;
    
    if (Math.abs(diff) > 50) { 
      // جهت درگ بخش افتخارات: حرکت دست به راست = کارت بعدی | حرکت دست به چپ = کارت قبلی
      if (diff > 0) {
        next();
      } else {
        prev();
      }
      isDragging = false; 
    }
  }

  function handleEnd() {
    isDragging = false;
    startAutoplay();
  }

  viewport.addEventListener("touchstart", (e) => handleStart(e.touches[0].clientX), { passive: true });
  viewport.addEventListener("touchmove", (e) => handleMove(e.touches[0].clientX), { passive: true });
  viewport.addEventListener("touchend", handleEnd, { passive: true });

  viewport.addEventListener("mousedown", (e) => {
    e.preventDefault();
    viewport.style.cursor = "grabbing";
    handleStart(e.clientX);
  });
  window.addEventListener("mousemove", (e) => handleMove(e.clientX));
  window.addEventListener("mouseup", () => {
    viewport.style.cursor = "grab";
    handleEnd();
  });

  function startAutoplay() {
    stopAutoplay();
    autoplayTimer = setInterval(next, AUTOPLAY_DELAY);
  }

  function stopAutoplay() {
    if (autoplayTimer) clearInterval(autoplayTimer);
  }

  viewport.style.cursor = "grab";
  updateAwards();
  startAutoplay();
  floatLoop();

  window.addEventListener("resize", updateAwards);
}