// Frontend JS for NBSTUP account layout.
// This file is processed by Gulp into `assets/js/dist/frontend.js`.
// Add any interactive behavior for the left menu / account layout here.

(function (window, document) {
  'use strict';

  var sidebar = document.querySelector('.pmpro-nbstup-account-sidebar');
  if (!sidebar) {
    return;
  }

  // Smooth scroll for in-page anchor links inside the NBSTUP menu.
  sidebar.addEventListener('click', function (event) {
    var link = event.target.closest('a[href^="#"]');
    if (!link) {
      return;
    }

    var targetId = link.getAttribute('href').slice(1);
    var target = document.getElementById(targetId);
    if (!target) {
      return;
    }

    event.preventDefault();

    window.scrollTo({
      top: target.getBoundingClientRect().top + window.pageYOffset - 80,
      behavior: 'smooth'
    });
  });

  // Function to update active navigation link based on scroll position
  function updateActiveNavLink() {
    var navLinks = sidebar.querySelectorAll('.pmpro-nbstup-account-nav a[href^="#"]');
    var scrollPosition = window.pageYOffset + 100; // Offset for header

    navLinks.forEach(function(link) {
      var targetId = link.getAttribute('href').slice(1);
      var target = document.getElementById(targetId);
      if (target) {
        var targetTop = target.offsetTop;
        var targetBottom = targetTop + target.offsetHeight;

        if (scrollPosition >= targetTop && scrollPosition < targetBottom) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      }
    });
  }

  // Update active link on scroll and on load
  window.addEventListener('scroll', updateActiveNavLink);
  window.addEventListener('load', updateActiveNavLink);

})(window, document);

