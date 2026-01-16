// Frontend JS for NBSTUP account layout.
// This file is processed by Gulp into `assets/js/dist/frontend.js`.
// Add any interactive behavior for the left menu / account layout here.

(function (window, document) {
  'use strict';

  // Example: Smooth scroll for in-page anchor links inside the NBSTUP menu.
  var sidebar = document.querySelector('.pmpro-nbstup-account-sidebar');
  if (!sidebar) {
    return;
  }

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
})(window, document);

