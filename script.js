/* =============================================
   SEPHA-JO — One Pager JavaScript
   ============================================= */

document.addEventListener('DOMContentLoaded', () => {

  // ---- Navbar scroll effect ----
  const navbar = document.getElementById('navbar');
  const heroSection = document.getElementById('hero');

  const handleNavbarScroll = () => {
    const scrollY = window.scrollY;
    if (scrollY > 60) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  };

  window.addEventListener('scroll', handleNavbarScroll, { passive: true });
  handleNavbarScroll(); // Run once on load

  // ---- Mobile hamburger menu ----
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');
  const navOverlay = document.getElementById('navOverlay');

  const toggleMenu = () => {
    const isOpen = navLinks.classList.toggle('open');
    hamburger.classList.toggle('active');
    hamburger.setAttribute('aria-expanded', isOpen);
    
    if (isOpen) {
      navOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    } else {
      navOverlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  };

  const closeMenu = () => {
    navLinks.classList.remove('open');
    hamburger.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
    navOverlay.classList.remove('active');
    document.body.style.overflow = '';
  };

  hamburger.addEventListener('click', toggleMenu);
  navOverlay.addEventListener('click', closeMenu);

  // Close menu on link click
  navLinks.querySelectorAll('.navbar__link').forEach(link => {
    link.addEventListener('click', closeMenu);
  });

  // ---- Scroll reveal animation ----
  const revealElements = document.querySelectorAll('.reveal');

  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.15,
    rootMargin: '0px 0px -40px 0px'
  });

  revealElements.forEach(el => revealObserver.observe(el));

  // ---- Smooth scroll for all anchor links ----
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        const navHeight = navbar.offsetHeight;
        const targetPosition = target.getBoundingClientRect().top + window.scrollY - navHeight;
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }
    });
  });

  // ---- Parallax effect on hero background ----
  const heroBg = document.querySelector('.hero__background img');
  
  const handleParallax = () => {
    const scrollY = window.scrollY;
    const heroHeight = heroSection.offsetHeight;
    
    if (scrollY < heroHeight) {
      const parallaxOffset = scrollY * 0.35;
      heroBg.style.transform = `translateY(${parallaxOffset}px) scale(1.05)`;
    }
  };

  window.addEventListener('scroll', handleParallax, { passive: true });

  // ---- Hide scroll indicator on scroll ----
  const scrollIndicator = document.querySelector('.hero__scroll-indicator');
  
  const handleScrollIndicator = () => {
    if (window.scrollY > 150) {
      scrollIndicator.style.opacity = '0';
      scrollIndicator.style.pointerEvents = 'none';
    } else {
      scrollIndicator.style.opacity = '1';
      scrollIndicator.style.pointerEvents = 'auto';
    }
  };

  window.addEventListener('scroll', handleScrollIndicator, { passive: true });

  // ---- Service card subtle tilt on mouse move ----
  const serviceCards = document.querySelectorAll('.service-card');
  
  serviceCards.forEach(card => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      const rotateX = (y - centerY) / centerY * -3;
      const rotateY = (x - centerX) / centerX * 3;
      
      card.style.transform = `translateY(-6px) perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });

});
