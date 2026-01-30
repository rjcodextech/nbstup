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

// ========== Location Cascading Dropdowns ==========
jQuery(document).ready(function($) {
  // Check if we're on checkout page with location fields
  if (!$('#user_state').length) {
    return;
  }

  var $stateSelect = $('#user_state');
  var $districtSelect = $('#user_district');
  var $blockSelect = $('#user_block');

  // When state changes, load districts
  $stateSelect.on('change', function() {
    var stateId = $(this).val();
    
    // Reset district and block
    $districtSelect.html('<option value="">Select State First</option>').prop('disabled', true);
    $blockSelect.html('<option value="">Select District First</option>').prop('disabled', true);
    
    if (!stateId) {
      return;
    }
    
    // Load districts
    $.ajax({
      url: pmpro_nbstup_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'pmpro_nbstup_get_districts',
        state_id: stateId,
        nonce: pmpro_nbstup_ajax.nonce
      },
      beforeSend: function() {
        $districtSelect.html('<option value="">Loading...</option>');
      },
      success: function(response) {
        if (response.success && response.data.length > 0) {
          var options = '<option value="">Select District</option>';
          $.each(response.data, function(index, district) {
            options += '<option value="' + district.id + '">' + district.name + '</option>';
          });
          $districtSelect.html(options).prop('disabled', false);
        } else {
          $districtSelect.html('<option value="">No districts available</option>');
        }
      },
      error: function() {
        $districtSelect.html('<option value="">Error loading districts</option>');
      }
    });
  });

  // When district changes, load blocks
  $districtSelect.on('change', function() {
    var districtId = $(this).val();
    
    // Reset block
    $blockSelect.html('<option value="">Select District First</option>').prop('disabled', true);
    
    if (!districtId) {
      return;
    }
    
    // Load blocks
    $.ajax({
      url: pmpro_nbstup_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'pmpro_nbstup_get_blocks',
        district_id: districtId,
        nonce: pmpro_nbstup_ajax.nonce
      },
      beforeSend: function() {
        $blockSelect.html('<option value="">Loading...</option>');
      },
      success: function(response) {
        if (response.success && response.data.length > 0) {
          var options = '<option value="">Select Block</option>';
          $.each(response.data, function(index, block) {
            options += '<option value="' + block.id + '">' + block.name + '</option>';
          });
          $blockSelect.html(options).prop('disabled', false);
        } else {
          $blockSelect.html('<option value="">No blocks available</option>');
        }
      },
      error: function() {
        $blockSelect.html('<option value="">Error loading blocks</option>');
      }
    });
  });
});

// ========== Contributions Admin: Select All ==========
jQuery(document).ready(function($) {
  var $selectAll = $('#select-all');
  if (!$selectAll.length) {
    return;
  }

  $selectAll.on('change', function() {
    $('input[name="user_ids[]"]').prop('checked', this.checked);
  });
});
