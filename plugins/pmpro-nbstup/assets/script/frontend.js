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

// ========== Login Tabs + AJAX Login ==========
jQuery(document).ready(function ($) {
  var $containers = $('.pmpro-nbstup-login-tabs');
  if (!$containers.length || typeof pmpro_nbstup_login === 'undefined') {
    return;
  }

  $containers.each(function () {
    var $container = $(this);
    $container.find('.pmpro-nbstup-login-panel').not('.is-active').attr('hidden', true);

    $container.on('click', '.pmpro-nbstup-login-tab', function () {
      var $tab = $(this);
      var target = $tab.data('tab');
      $container.find('.pmpro-nbstup-login-tab')
        .removeClass('is-active')
        .attr('aria-selected', 'false');
      $tab.addClass('is-active').attr('aria-selected', 'true');

      $container.find('.pmpro-nbstup-login-panel')
        .removeClass('is-active')
        .attr('hidden', true);

      var $panel = $container.find('.pmpro-nbstup-login-panel[data-panel="' + target + '"]');
      $panel.addClass('is-active').removeAttr('hidden');
      $panel.find('.pmpro-nbstup-login-message').attr('hidden', true).text('');
      clearFieldErrors($panel.find('.pmpro-nbstup-login-form'));
    });
  });

  function showMessage($form, message) {
    var $message = $form.closest('.pmpro-nbstup-login-panel').find('.pmpro-nbstup-login-message');
    $message.removeAttr('hidden').text(message);
  }

  function setLoading($form, isLoading) {
    var $button = $form.find('button[type="submit"]');
    $button.prop('disabled', isLoading);
    $form.toggleClass('is-loading', isLoading);
  }

  function clearFieldErrors($form) {
    if (!$form || !$form.length) {
      return;
    }

    $form.find('.pmpro-nbstup-member-login__field').removeClass('has-error');
    $form.find('.pmpro-nbstup-field-error').remove();
    $form.find('.pmpro-nbstup-member-login__input').removeAttr('aria-invalid aria-describedby');
  }

  function setFieldError($form, $field, message) {
    var $wrapper = $field.closest('.pmpro-nbstup-member-login__field');
    var fieldName = $field.attr('name') || 'field';
    var formType = $form.data('login-type') || 'login';
    var errorId = 'pmpro-nbstup-' + formType + '-' + fieldName + '-error';

    $wrapper.addClass('has-error');
    $field.attr('aria-invalid', 'true').attr('aria-describedby', errorId);

    if (!$wrapper.find('#' + errorId).length) {
      $('<div/>', {
        class: 'pmpro-nbstup-field-error',
        id: errorId,
        text: message
      }).appendTo($wrapper);
    }
  }

  function clearFieldError($field) {
    var $wrapper = $field.closest('.pmpro-nbstup-member-login__field');
    $wrapper.removeClass('has-error');
    $wrapper.find('.pmpro-nbstup-field-error').remove();
    $field.removeAttr('aria-invalid aria-describedby');
  }

  function validateForm($form) {
    var isValid = true;
    var type = $form.data('login-type');

    if (type === 'admin') {
      var $login = $form.find('input[name="user_login"]');
      var $password = $form.find('input[name="user_password"]');

      if (!$.trim($login.val())) {
        setFieldError($form, $login, pmpro_nbstup_login.validation_login || 'Please enter your username or email.');
        isValid = false;
      }

      if (!$.trim($password.val())) {
        setFieldError($form, $password, pmpro_nbstup_login.validation_password || 'Please enter your password.');
        isValid = false;
      }
    } else {
      var $aadhar = $form.find('input[name="aadhar_number"]');
      var $memberPassword = $form.find('input[name="member_password"]');
      var aadharValue = $.trim($aadhar.val()).replace(/\s+/g, '');

      if (!aadharValue) {
        setFieldError($form, $aadhar, pmpro_nbstup_login.validation_aadhar || 'Please enter your Aadhar number.');
        isValid = false;
      } else if (!/^\d{12}$/.test(aadharValue)) {
        setFieldError($form, $aadhar, pmpro_nbstup_login.validation_aadhar_format || 'Enter a valid 12-digit Aadhar number.');
        isValid = false;
      }

      if (!$.trim($memberPassword.val())) {
        setFieldError($form, $memberPassword, pmpro_nbstup_login.validation_password || 'Please enter your password.');
        isValid = false;
      }
    }

    return isValid;
  }

  $(document).on('input', 'input[name="aadhar_number"]', function () {
    var $input = $(this);
    var value = $input.val();
    var sanitized = value.replace(/\D+/g, '').slice(0, 12);

    if (value !== sanitized) {
      $input.val(sanitized);
    }
  });

  $(document).on('input', '.pmpro-nbstup-member-login__input', function () {
    clearFieldError($(this));
  });

  $(document).on('submit', '.pmpro-nbstup-login-form', function (event) {
    event.preventDefault();

    var $form = $(this);
    var type = $form.data('login-type');
    clearFieldErrors($form);

    var data = {
      action: type === 'admin' ? 'pmpronbstup_admin_login' : 'pmpronbstup_member_login',
      nonce: pmpro_nbstup_login.nonce,
      redirect: $form.data('redirect') || ''
    };

    if (type === 'admin') {
      data.user_login = $.trim($form.find('input[name="user_login"]').val());
      data.user_password = $form.find('input[name="user_password"]').val();
      data.remember = $form.find('input[name="remember"]').is(':checked') ? 1 : 0;
    } else {
      var aadharNumber = $.trim($form.find('input[name="aadhar_number"]').val()).replace(/\D+/g, '');
      data.aadhar_number = aadharNumber;
      data.member_password = $form.find('input[name="member_password"]').val();
    }

    if (!validateForm($form)) {
      showMessage($form, pmpro_nbstup_login.validation_message || 'Please fix the highlighted fields and try again.');
      var $firstError = $form.find('.pmpro-nbstup-member-login__field.has-error').first();
      if ($firstError.length) {
        $firstError.find('input').first().trigger('focus');
      }
      return;
    }

    setLoading($form, true);

    $.ajax({
      url: pmpro_nbstup_login.ajax_url,
      type: 'POST',
      dataType: 'json',
      data: data
    })
      .done(function (response) {
        if (response && response.success) {
          var redirect = response.data && response.data.redirect ? response.data.redirect : window.location.href;
          window.location.href = redirect;
        } else {
          var message = response && response.data && response.data.message ? response.data.message : pmpro_nbstup_login.generic_error;
          showMessage($form, message);
        }
      })
      .fail(function () {
        showMessage($form, pmpro_nbstup_login.generic_error);
      })
      .always(function () {
        setLoading($form, false);
      });
  });
});
