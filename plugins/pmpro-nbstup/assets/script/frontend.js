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
    var link = null;
    var node = event.target;
    while (node && node !== sidebar) {
      if (node.tagName && node.tagName.toLowerCase() === 'a') {
        var href = node.getAttribute('href') || '';
        if (href.indexOf('#') === 0) {
          link = node;
          break;
        }
      }
      node = node.parentElement;
    }
    if (!link) {
      return;
    }

    var targetId = link.getAttribute('href').slice(1);
    var target = document.getElementById(targetId);
    if (!target) {
      return;
    }

    event.preventDefault();

    var targetTop = target.getBoundingClientRect().top + window.pageYOffset - 80;
    if ('scrollBehavior' in document.documentElement.style) {
      window.scrollTo({
        top: targetTop,
        behavior: 'smooth'
      });
    } else {
      window.scrollTo(0, targetTop);
    }
  });

  // Function to update active navigation link based on scroll position
  function updateActiveNavLink() {
    var navLinks = sidebar.querySelectorAll('.pmpro-nbstup-account-nav a[href^="#"]');
    var scrollPosition = window.pageYOffset + 100; // Offset for header

    for (var i = 0; i < navLinks.length; i++) {
      var link = navLinks[i];
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
    }
  }

  // Update active link on scroll and on load
  window.addEventListener('scroll', updateActiveNavLink);
  window.addEventListener('load', updateActiveNavLink);

})(window, document);

// ========== Location Cascading Dropdowns ==========
jQuery(document).ready(function($) {
  // Check if we're on checkout page with location fields
  if (!$('#user_state').length || typeof pmpro_nbstup_data === 'undefined') {
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
      url: pmpro_nbstup_data.ajax_url,
      type: 'POST',
      data: {
        action: 'pmpro_nbstup_get_districts',
        state_id: stateId,
        nonce: pmpro_nbstup_data.ajax_nonce
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
      url: pmpro_nbstup_data.ajax_url,
      type: 'POST',
      data: {
        action: 'pmpro_nbstup_get_blocks',
        district_id: districtId,
        nonce: pmpro_nbstup_data.ajax_nonce
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

// ========== Contribution Search + AJAX Pagination ==========
jQuery(document).ready(function ($) {
  if (typeof pmpro_nbstup_data === 'undefined') {
    return;
  }

  var $searchForm = $('.pmpro-nbstup-search-form[data-ajax="1"]');
  if (!$searchForm.length) {
    return;
  }

  var $results = $('[data-search-results]').first();
  if (!$results.length) {
    return;
  }

  function setSearchLoading(isLoading) {
    var $submit = $searchForm.find('button[type="submit"]');
    var $loadingText = $searchForm.find('[data-search-loading]');
    $submit.prop('disabled', isLoading);
    $searchForm.toggleClass('is-loading', isLoading);

    if ($loadingText.length) {
      if (isLoading) {
        $loadingText.text(pmpro_nbstup_data.search_loading || 'Loading members...');
        $loadingText.removeAttr('hidden');
      } else {
        $loadingText.attr('hidden', true);
      }
    }
  }

  function fetchMembers(page) {
    var searchValue = $.trim($searchForm.find('input[name="s"]').val() || '');

    setSearchLoading(true);
    $results.attr('aria-busy', 'true');
    $results.css('opacity', '0.6');

    $.ajax({
      url: pmpro_nbstup_data.ajax_url,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'pmpronbstup_filter_deceased_members',
        nonce: pmpro_nbstup_data.ajax_nonce,
        s: searchValue,
        paged: page || 1
      }
    })
      .done(function (response) {
        if (response && response.success && response.data && typeof response.data.html === 'string') {
          $results.html(response.data.html);
        } else {
          var message = (response && response.data && response.data.message)
            ? response.data.message
            : (pmpro_nbstup_data.search_error || 'Unable to load members right now. Please try again.');
          $results.html('<p class="pmpro_message pmpro_error">' + message + '</p>');
        }
      })
      .fail(function () {
        var message = pmpro_nbstup_data.search_error || 'Unable to load members right now. Please try again.';
        $results.html('<p class="pmpro_message pmpro_error">' + message + '</p>');
      })
      .always(function () {
        setSearchLoading(false);
        $results.removeAttr('aria-busy');
        $results.css('opacity', '1');
      });
  }

  $searchForm.on('submit', function (event) {
    event.preventDefault();
    fetchMembers(1);
  });

  $(document).on('click', '.pmpro-nbstup-pagination a[data-page]', function (event) {
    event.preventDefault();
    var page = parseInt($(this).attr('data-page'), 10);
    fetchMembers(page || 1);
  });
});

// ========== Login Tabs + AJAX Login ==========
jQuery(document).ready(function ($) {
  var $memberDetails = $('#pmpro_form_fieldset-member-details');
  if ($memberDetails.length) {
    var $aadhar = $memberDetails.find('#aadhar_number');
    var $memberName = $memberDetails.find('#member_name');
    var $memberPhone = $memberDetails.find('#phone_no');
    var $memberPassword = $memberDetails.find('#member_password');
    var $username = $('#pmpro_user_fields #username');
    var $password = $('#pmpro_user_fields #password');
    var $password2 = $('#pmpro_user_fields #password2');
    var $bemail = $('#pmpro_user_fields #bemail');
    var $bconfirm = $('#pmpro_user_fields #bconfirmemail');
    var $bfirstname = $('#pmpro_billing_address_fields #bfirstname');
    var $blastname = $('#pmpro_billing_address_fields #blastname');
    var $bphone = $('#pmpro_billing_address_fields #bphone');

    function buildEmail(aadharValue) {
      return aadharValue ? aadharValue + '@nbstup.com' : '';
    }

    function syncFromMemberDetails() {
      var aadharValue = $aadhar.length ? $.trim($aadhar.val()).replace(/\D+/g, '') : '';
      var fullName = $memberName.length ? $.trim($memberName.val() || '') : '';
      var phoneValue = $memberPhone.length ? $.trim($memberPhone.val() || '') : '';
      var passwordValue = $memberPassword.length ? ($memberPassword.val() || '') : '';
      var nameParts = fullName ? fullName.split(/\s+/) : [];
      var firstName = nameParts.length ? nameParts[0] : '';
      var lastName = nameParts.length > 1 ? nameParts.slice(1).join(' ') : '';

      if ($username.length) {
        $username.val(aadharValue);
      }
      if ($password.length) {
        $password.val(passwordValue);
      }
      if ($password2.length) {
        $password2.val(passwordValue);
      }
      var emailValue = buildEmail(aadharValue);
      if ($bemail.length) {
        $bemail.val(emailValue);
      }
      if ($bconfirm.length) {
        $bconfirm.val(emailValue);
      }
      if ($bfirstname.length) {
        $bfirstname.val(firstName);
      }
      if ($blastname.length) {
        $blastname.val(lastName);
      }
      if ($bphone.length) {
        $bphone.val(phoneValue);
      }
    }

    $memberDetails.on('input change', '#aadhar_number, #member_password, #member_name, #phone_no', syncFromMemberDetails);
    syncFromMemberDetails();
  }

  // Handle login tabs if they exist (legacy support)
  var $containers = $('.pmpro-nbstup-login-tabs');
  if ($containers.length && typeof pmpro_nbstup_data !== 'undefined') {
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
  }

  // Only continue if we have login form data available
  if (typeof pmpro_nbstup_data === 'undefined') {
    return;
  }

  function showMessage($form, message) {
    var $message = $form.closest('.pmpro-nbstup-login-panel').find('.pmpro-nbstup-login-message');
    if (!$message.length) {
      $message = $form.closest('.pmpro-nbstup-member-login, .pmpro-nbstup-admin-login').find('.pmpro-nbstup-login-message').first();
    }
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
        setFieldError($form, $login, pmpro_nbstup_data.validation_login || 'Please enter your username or email.');
        isValid = false;
      }

      if (!$.trim($password.val())) {
        setFieldError($form, $password, pmpro_nbstup_data.validation_password || 'Please enter your password.');
        isValid = false;
      }
    } else {
      var $aadhar = $form.find('input[name="aadhar_number"]');
      var $memberPassword = $form.find('input[name="member_password"]');
      var aadharValue = $.trim($aadhar.val()).replace(/\s+/g, '');

      if (!aadharValue) {
        setFieldError($form, $aadhar, pmpro_nbstup_data.validation_aadhar || 'Please enter your Aadhar number.');
        isValid = false;
      } else if (!/^\d{12}$/.test(aadharValue)) {
        setFieldError($form, $aadhar, pmpro_nbstup_data.validation_aadhar_format || 'Enter a valid 12-digit Aadhar number.');
        isValid = false;
      }

      if (!$.trim($memberPassword.val())) {
        setFieldError($form, $memberPassword, pmpro_nbstup_data.validation_password || 'Please enter your password.');
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
      nonce: pmpro_nbstup_data.login_nonce,
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
      showMessage($form, pmpro_nbstup_data.validation_message || 'Please fix the highlighted fields and try again.');
      var $firstError = $form.find('.pmpro-nbstup-member-login__field.has-error').first();
      if ($firstError.length) {
        $firstError.find('input').first().trigger('focus');
      }
      return;
    }

    setLoading($form, true);

    $.ajax({
      url: pmpro_nbstup_data.ajax_url,
      type: 'POST',
      dataType: 'json',
      data: data
    })
      .done(function (response) {
        if (response && response.success) {
          var redirect = response.data && response.data.redirect ? response.data.redirect : window.location.href;
          window.location.href = redirect;
        } else {
          var message = response && response.data && response.data.message ? response.data.message : pmpro_nbstup_data.generic_error;
          var serverCode = response && response.data && response.data.error_code ? response.data.error_code : '';

          if (type === 'member') {
            var $serverAadhar = $form.find('input[name="aadhar_number"]');
            var $serverPassword = $form.find('input[name="member_password"]');

            if (serverCode === 'invalid_aadhar' && $serverAadhar.length) {
              setFieldError($form, $serverAadhar, message);
              $serverAadhar.trigger('focus');
            } else if (serverCode === 'invalid_password' && $serverPassword.length) {
              setFieldError($form, $serverPassword, message);
              $serverPassword.trigger('focus');
            }
          }

          showMessage($form, message);
        }
      })
      .fail(function () {
        showMessage($form, pmpro_nbstup_data.generic_error);
      })
      .always(function () {
        setLoading($form, false);
      });
  });
});

// ========== Checkout Form Validation ==========
jQuery(document).ready(function ($) {
  'use strict';

  // Only run on PMPro checkout page
  if (!$('#pmpro_form').length) {
    return;
  }

  // Validation rules and messages
  var validationRules = {
    member_name: {
      required: true,
      pattern: /^[\p{L}][\p{L}\s.\-]{1,60}$/u,
      message: 'Name should contain only letters and valid characters (2-60 chars)'
    },
    phone_no: {
      required: true,
      pattern: /^\d{10}$/,
      message: 'Phone number must be exactly 10 digits'
    },
    aadhar_number: {
      required: true,
      pattern: /^\d{12}$/,
      message: 'Aadhar number must be exactly 12 digits'
    },
    father_husband_name: {
      required: true,
      pattern: /^[\p{L}][\p{L}\s.\-]{1,60}$/u,
      message: 'Father/Husband name should contain only letters and valid characters'
    },
    dob: {
      required: true,
      custom: function(value) {
        if (!value) return 'Date of birth is required';
        var dob = new Date(value);
        var today = new Date();
        var age = today.getFullYear() - dob.getFullYear();
        var monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
          age--;
        }
        if (dob > today) return 'Date of birth cannot be in the future';
        if (age < 18) return 'You must be at least 18 years old';
        if (age > 55) return 'Age must not exceed 55 years';
        return null;
      }
    },
    gender: {
      required: true,
      message: 'Please select a gender'
    },
    Occupation: {
      required: true,
      message: 'Please select an occupation'
    },
    member_password: {
      required: true,
      minLength: 6,
      message: 'Password must be at least 6 characters'
    },
    user_state: {
      required: true,
      message: 'Please select a state'
    },
    user_district: {
      required: true,
      message: 'Please select a district'
    },
    user_block: {
      required: true,
      message: 'Please select a block'
    },
    user_address: {
      required: true,
      minLength: 5,
      message: 'Address must be at least 5 characters'
    },
    declaration_accept: {
      required: true,
      checked: true,
      message: 'You must accept the declaration to proceed'
    },
    nominee_name_1: {
      required: true,
      pattern: /^[\p{L}][\p{L}\s.\-]{1,60}$/u,
      message: 'Nominee 1 name should contain only letters and valid characters'
    },
    relation_with_nominee_1: {
      required: true,
      minLength: 2,
      message: 'Relation with nominee 1 is required (min 2 characters)'
    },
    nominee_1_mobile: {
      required: true,
      pattern: /^\d{10}$/,
      message: 'Nominee 1 mobile must be exactly 10 digits'
    },
    nominee_name_2: {
      required: true,
      pattern: /^[\p{L}][\p{L}\s.\-]{1,60}$/u,
      message: 'Nominee 2 name should contain only letters and valid characters'
    },
    relation_with_nominee_2: {
      required: true,
      minLength: 2,
      message: 'Relation with nominee 2 is required (min 2 characters)'
    },
    nominee_2_mobile: {
      required: true,
      pattern: /^\d{10}$/,
      message: 'Nominee 2 mobile must be exactly 10 digits'
    }
  };

  // Helper function to show field error
  function showFieldError($field, message) {
    var $wrap = $field.closest('.pmpro_form_field');
    $wrap.addClass('pmpro_form_field-error');
    
    // Remove existing error message
    $wrap.find('.pmpro-nbstup-validation-error').remove();
    
    // Add new error message
    var $error = $('<span class="pmpro-nbstup-validation-error" style="color: #dc3232; font-size: 13px; display: block; margin-top: 5px;"></span>').text(message);
    $field.after($error);
    $field.attr('aria-invalid', 'true');
  }

  // Helper function to clear field error
  function clearFieldError($field) {
    var $wrap = $field.closest('.pmpro_form_field');
    $wrap.removeClass('pmpro_form_field-error');
    $wrap.find('.pmpro-nbstup-validation-error').remove();
    $field.removeAttr('aria-invalid');
  }

  // Validate a single field
  function validateField($field) {
    var fieldName = $field.attr('name');
    var rule = validationRules[fieldName];
    
    if (!rule) return true;

    var value = $field.val();
    var fieldType = $field.attr('type');
    
    // Check required
    if (rule.required) {
      if (fieldType === 'checkbox') {
        if (!$field.is(':checked')) {
          showFieldError($field, rule.message);
          return false;
        }
      } else if (fieldType === 'radio') {
        var radioGroup = $('input[name="' + fieldName + '"]');
        if (!radioGroup.is(':checked')) {
          showFieldError(radioGroup.first(), rule.message);
          return false;
        }
      } else if (!value || $.trim(value) === '') {
        showFieldError($field, rule.message || fieldName + ' is required');
        return false;
      }
    }

    // Clean value for validation
    if (fieldName === 'phone_no' || fieldName === 'aadhar_number' || fieldName === 'nominee_1_mobile' || fieldName === 'nominee_2_mobile') {
      value = value.replace(/\D+/g, '');
    } else {
      value = $.trim(value);
    }

    // Check minLength
    if (rule.minLength && value.length < rule.minLength) {
      showFieldError($field, rule.message);
      return false;
    }

    // Check pattern
    if (rule.pattern && value !== '' && !rule.pattern.test(value)) {
      showFieldError($field, rule.message);
      return false;
    }

    // Check custom validation
    if (rule.custom && typeof rule.custom === 'function') {
      var customError = rule.custom(value);
      if (customError) {
        showFieldError($field, customError);
        return false;
      }
    }

    clearFieldError($field);
    return true;
  }

  // Real-time validation on blur
  $.each(validationRules, function(fieldName) {
    var $field = $('[name="' + fieldName + '"]');
    if ($field.length) {
      $field.on('blur change', function() {
        validateField($(this));
      });
      
      // Clear error on input
      $field.on('input', function() {
        clearFieldError($(this));
      });
    }
  });

  // Validate form on submit
  $('#pmpro_form').on('submit', function(e) {
    var isValid = true;
    var $firstError = null;

    // Validate all fields
    $.each(validationRules, function(fieldName) {
      var $field = $('[name="' + fieldName + '"]');
      if ($field.length) {
        if (!validateField($field)) {
          isValid = false;
          if (!$firstError) {
            $firstError = $field;
          }
        }
      }
    });

    if (!isValid) {
      e.preventDefault();
      e.stopImmediatePropagation();
      
      // Scroll to first error
      if ($firstError) {
        $('html, body').animate({
          scrollTop: $firstError.closest('.pmpro_form_field').offset().top - 100
        }, 300);
        $firstError.focus();
      }
      
      // Show general error message
      var $messageDiv = $('#pmpro_message');
      if (!$messageDiv.length) {
        $messageDiv = $('<div id="pmpro_message" class="pmpro_message pmpro_error"></div>');
        $('#pmpro_form').prepend($messageDiv);
      }
      $messageDiv.html('Please correct the highlighted errors before submitting.').show();
      
      return false;
    }
  });

  // Format phone numbers and aadhar as user types
  $('[name="phone_no"], [name="nominee_1_mobile"], [name="nominee_2_mobile"]').on('input', function() {
    var value = $(this).val().replace(/\D+/g, '').slice(0, 10);
    $(this).val(value);
  });

  $('[name="aadhar_number"]').on('input', function() {
    var value = $(this).val().replace(/\D+/g, '').slice(0, 12);
    $(this).val(value);
  });
});

// ========== Password Visibility Toggle ==========
jQuery(document).ready(function ($) {
  var passwordSelector = '#pmpro_form input[type="password"], .pmpro-nbstup-login-form input[type="password"]';

  function getEyeIcon(isVisible) {
    if (isVisible) {
      return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3.53 2.47 2.47 3.53l3.06 3.06A11.3 11.3 0 0 0 1.2 12c1.8 3.3 5.2 6 10.8 6 2.1 0 4-.4 5.6-1.1l2.87 2.87 1.06-1.06ZM12 16.5c-3.2 0-5.7-1.4-7.3-3.6A9.4 9.4 0 0 1 6.7 10l1.73 1.73a4 4 0 0 0 4.84 4.84L15 18.3c-.9.2-1.9.2-3 .2Zm0-9c3.2 0 5.7 1.4 7.3 3.6-.5.8-1.2 1.7-2.2 2.4l1.1 1.1A11.2 11.2 0 0 0 22.8 12c-1.8-3.3-5.2-6-10.8-6-1.7 0-3.2.3-4.5.7l1.27 1.27c1-.3 2-.47 3.23-.47Zm-.06 2.5 4.12 4.12A4 4 0 0 0 11.94 10Z"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 6c5.6 0 9 2.7 10.8 6-1.8 3.3-5.2 6-10.8 6S3 15.3 1.2 12C3 8.7 6.4 6 12 6Zm0 1.5c-3.2 0-5.7 1.4-7.3 3.6 1.6 2.2 4.1 3.6 7.3 3.6s5.7-1.4 7.3-3.6c-1.6-2.2-4.1-3.6-7.3-3.6Zm0 1.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7Zm0 1.5a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/></svg>';
  }

  function enhancePasswordField($input) {
    if (!$input.length || $input.data('pmpro-nbstup-toggle-ready')) {
      return;
    }

    if ($input.closest('.pmpro-nbstup-password-wrap').length) {
      $input.data('pmpro-nbstup-toggle-ready', true);
      return;
    }

    $input.wrap('<span class="pmpro-nbstup-password-wrap"></span>');
    var $wrap = $input.parent();

    var $button = $('<button/>', {
      type: 'button',
      class: 'pmpro-nbstup-password-toggle',
      'aria-label': 'Show password',
      'aria-pressed': 'false'
    });

    $button.append($('<span/>', {
      class: 'pmpro-nbstup-password-toggle__icon',
      'aria-hidden': 'true',
      html: getEyeIcon(false)
    }));

    $button.append($('<span/>', {
      class: 'screen-reader-text',
      text: 'Show password'
    }));

    $wrap.append($button);
    $input.data('pmpro-nbstup-toggle-ready', true);
  }

  function updatePasswordState($button, showPassword) {
    var $wrap = $button.closest('.pmpro-nbstup-password-wrap');
    var $input = $wrap.find('input[type="password"], input[type="text"]').first();

    if (!$input.length) {
      return;
    }

    $input.attr('type', showPassword ? 'text' : 'password');
    $button.attr('aria-pressed', showPassword ? 'true' : 'false');
    $button.attr('aria-label', showPassword ? 'Hide password' : 'Show password');
    $button.find('.screen-reader-text').text(showPassword ? 'Hide password' : 'Show password');
    $button.find('.pmpro-nbstup-password-toggle__icon').html(getEyeIcon(showPassword));
  }

  $(passwordSelector).each(function () {
    enhancePasswordField($(this));
  });

  $(document).on('focusin', passwordSelector, function () {
    enhancePasswordField($(this));
  });

  $(document).on('click', '.pmpro-nbstup-password-toggle', function () {
    var $button = $(this);
    var showPassword = $button.attr('aria-pressed') !== 'true';
    updatePasswordState($button, showPassword);
  });
});
