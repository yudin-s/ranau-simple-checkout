(function (window, document) {
  'use strict';

  var config = window.ranauSimpleCheckout || {};
  var scheduled = false;
  var saving = false;

  function first(selectors) {
    var fallback = null;
    for (var index = 0; index < selectors.length; index += 1) {
      var elements = document.querySelectorAll(selectors[index]);
      for (var elementIndex = 0; elementIndex < elements.length; elementIndex += 1) {
        var element = elements[elementIndex];
        fallback = fallback || element;
        if (!element.disabled && element.getClientRects().length > 0) {
          return element;
        }
      }
    }
    return fallback;
  }

  function cityInput() {
    return first([
      '#shipping-city',
      'input[name="shipping_city"]',
      '#billing-city',
      'input[name="billing_city"]',
      'input[autocomplete="address-level2"]'
    ]);
  }

  function phoneInput() {
    return first([
      '#shipping-phone',
      'input[name="shipping_phone"]',
      '#billing-phone',
      'input[name="billing_phone"]',
      'input[autocomplete="tel"]'
    ]);
  }

  function emailInput() {
    return first([
      '#email',
      '#billing-email',
      'input[name="billing_email"]',
      'input[autocomplete="email"]'
    ]);
  }

  function setNativeValue(input, value) {
    if (!input || input.value === value) {
      return;
    }
    var descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
    if (descriptor && descriptor.set) {
      descriptor.set.call(input, value);
    } else {
      input.value = value;
    }
    input.dispatchEvent(new window.Event('input', {bubbles: true}));
    input.dispatchEvent(new window.Event('change', {bubbles: true}));
  }

  function phoneDigits(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function phoneIsValid(value) {
    var digits = phoneDigits(value);
    return digits.length >= 10 && digits.length <= 15;
  }

  function hash(value) {
    var result = 2166136261;
    for (var index = 0; index < value.length; index += 1) {
      result ^= value.charCodeAt(index);
      result = Math.imul(result, 16777619);
    }
    return (result >>> 0).toString(16);
  }

  function syncInternalFields() {
    var phone = phoneInput();
    var city = cityInput();
    var email = emailInput();
    var digits = phoneDigits(phone && phone.value);
    var internalValues = {
      'input[name="billing_first_name"], #billing-first_name': config.customerLabel || 'Customer',
      'input[name="shipping_first_name"], #shipping-first_name': config.customerLabel || 'Customer',
      'input[name="billing_last_name"], #billing-last_name': config.orderLabel || 'Online order',
      'input[name="shipping_last_name"], #shipping-last_name': config.orderLabel || 'Online order',
      'input[name="billing_country"], #billing-country': config.country || 'RU',
      'input[name="shipping_country"], #shipping-country': config.country || 'RU'
    };

    Object.keys(internalValues).forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (input) {
        if (!String(input.value || '').trim()) {
          setNativeValue(input, internalValues[selector]);
        }
      });
    });

    if (city && String(city.value || '').trim()) {
      document.querySelectorAll('input[name="billing_city"], input[name="shipping_city"]').forEach(function (input) {
        setNativeValue(input, String(city.value || '').trim());
      });
    }

    if (email && !String(email.value || '').trim() && digits) {
      setNativeValue(email, 'order-' + hash(digits) + '@example.invalid');
      var wrapper = email.closest('.wc-block-components-text-input, .form-row');
      if (wrapper) {
        wrapper.classList.add('ranau-simple-checkout__placeholder-email');
      }
    }
  }

  function statusNode(city) {
    var wrapper = city && city.closest('.wc-block-components-text-input, .form-row');
    if (!wrapper) {
      return null;
    }
    var status = wrapper.parentNode.querySelector('.ranau-simple-checkout__status');
    if (!status) {
      status = document.createElement('div');
      status.className = 'ranau-simple-checkout__status';
      status.setAttribute('aria-live', 'polite');
      wrapper.insertAdjacentElement('afterend', status);
    }
    return status;
  }

  function updateButton(button) {
    var city = cityInput();
    var phone = phoneInput();
    var ready = Boolean(
      city && String(city.value || '').trim().length >= 2 &&
      phone && phoneIsValid(phone.value)
    );
    button.disabled = saving || !ready;
  }

  function refreshCheckout(city, phone) {
    if (
      window.wc &&
      window.wc.blocksCheckout &&
      typeof window.wc.blocksCheckout.extensionCartUpdate === 'function'
    ) {
      return Promise.resolve(window.wc.blocksCheckout.extensionCartUpdate({
        namespace: config.namespace || 'ranau-simple-checkout',
        data: {city: city, phone: phone}
      }));
    }

    if (window.jQuery && document.body) {
      window.jQuery(document.body).trigger('update_checkout');
      return Promise.resolve();
    }

    return Promise.reject(new Error('checkout_refresh_unavailable'));
  }

  function commitCity(button) {
    var city = cityInput();
    var phone = phoneInput();
    var status = statusNode(city);
    var cityValue = city ? String(city.value || '').trim() : '';
    var phoneValue = phone ? String(phone.value || '').trim() : '';

    if (cityValue.length < 2) {
      if (status) { status.textContent = config.messages.invalidCity; }
      return;
    }
    if (!phoneIsValid(phoneValue)) {
      if (status) { status.textContent = config.messages.invalidPhone; }
      return;
    }

    saving = true;
    updateButton(button);
    if (status) { status.textContent = config.messages.saving; }
    syncInternalFields();

    refreshCheckout(cityValue, phoneValue).then(function () {
      city.dataset.ranauCityCommitted = cityValue;
      if (status) { status.textContent = config.messages.saved; }
    }).catch(function () {
      if (status) { status.textContent = config.messages.failed; }
    }).then(function () {
      saving = false;
      updateButton(button);
    });
  }

  function mount() {
    var city = cityInput();
    var phone = phoneInput();
    if (!city || !phone) {
      return;
    }

    city.setAttribute('required', 'required');
    city.setAttribute('aria-required', 'true');
    phone.setAttribute('required', 'required');
    phone.setAttribute('aria-required', 'true');
    phone.setAttribute('autocomplete', 'tel');
    syncInternalFields();

    var wrapper = city.closest('.wc-block-components-text-input, .form-row');
    if (!wrapper || wrapper.parentNode.querySelector('.ranau-simple-checkout__commit-city')) {
      return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'ranau-simple-checkout__commit-city wc-block-components-button wp-element-button';
    button.textContent = config.messages.chooseCity;
    button.addEventListener('click', function () { commitCity(button); });
    city.addEventListener('input', function () {
      delete city.dataset.ranauCityCommitted;
      updateButton(button);
    });
    phone.addEventListener('input', function () {
      updateButton(button);
    });
    wrapper.insertAdjacentElement('afterend', button);
    updateButton(button);
  }

  function scheduleMount() {
    if (scheduled) {
      return;
    }
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      mount();
    });
  }

  document.addEventListener('DOMContentLoaded', scheduleMount);
  new window.MutationObserver(scheduleMount).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
}(window, document));
