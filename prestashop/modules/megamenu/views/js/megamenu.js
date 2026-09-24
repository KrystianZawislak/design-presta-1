$(function () {
  $(document).on('click', '.megamenu-switcher .nav-link', function (event) {
    var $button = $(this);
    var $switcher = $button.closest('.megamenu-switcher');
    var isMobile = $button.closest('.ps-mainmenu__mobile-switcher').length > 0;
    var idCategory = $button.hasClass('active') ? 0 : $button.data('id-category');

    if (!isMobile) {
      $switcher.css('opacity', 0.5);
      $.post($switcher.data('select-url'), { id_category: idCategory }).always(function () {
        window.location.reload();
      });
      return;
    }

    event.preventDefault();
    $('.megamenu-switcher').css('opacity', 0.5);

    $.post($switcher.data('select-url'), { id_category: idCategory })
      .done(function (data) {
        if (data && data.success && typeof data.mobileMenu === 'string') {
          $('.ps-mainmenu__mobile').html(data.mobileMenu);
        }

        $('.megamenu-switcher .nav-link').removeClass('active');
        if (idCategory) {
          $('.megamenu-switcher .nav-link[data-id-category="' + idCategory + '"]').addClass('active');
        }
      })
      .always(function () {
        $('.megamenu-switcher').css('opacity', 1);
      });
  });
});

$(function () {
  var $mobileMenu = $('.ps-mainmenu__mobile');

  if (!$mobileMenu.length) {
    return;
  }

  var $backButton = $('.js-mobile-menu-back');
  var $backTitle = $('.js-mobile-menu-back-title');
  var defaultBackTitle = $backTitle.length ? $backTitle.html() : '';

  function goBack() {
    var $current = $mobileMenu.find('.menu--current');
    var $parent = $mobileMenu.find('.menu--parent');

    $backButton.addClass('d-none');
    $backTitle.html(defaultBackTitle);
    $current.removeClass('menu--current menu--fromLeft menu--fromRight');
    $parent.addClass('menu--fromLeft menu--current').removeClass('menu--parent');
  }

  $mobileMenu.on('click', '.js-mobile-menu-open', function () {
    var $current = $mobileMenu.find('.menu--current');
    var targetId = $(this).data('target');
    var $target = $mobileMenu.find('.menu[data-id="' + targetId + '"]');

    if (!$target.length) {
      return;
    }

    $current.removeClass('menu--current menu--fromLeft menu--fromRight').addClass('menu--parent');
    $target.addClass('menu--fromRight menu--current');

    $backButton.removeClass('d-none');
    $backTitle.html($target.data('back-title') || defaultBackTitle);
  });

  $backButton.on('click', goBack);

  $('.js-menu-canvas').on('hidden.bs.offcanvas', function () {
    if ($mobileMenu.find('.menu--parent').length) {
      goBack();
    }
  });
});

$(function () {
  var header = document.querySelector('.header-master');
  var toggle = document.querySelector('.ps-mainmenu__mobile-toggle');

  if (!header || !toggle) {
    return;
  }

  var toggleHome = document.createComment('mobile-toggle-home');
  toggle.parentNode.insertBefore(toggleHome, toggle);

  var mq = window.matchMedia('(max-width: 991.98px)');

  function moveToggleIn() {
    if (header.firstChild !== toggle) {
      header.insertBefore(toggle, header.firstChild);
    }
  }

  function moveToggleOut() {
    if (toggleHome.parentNode) {
      toggleHome.parentNode.insertBefore(toggle, toggleHome.nextSibling);
    }
  }

  function update() {
    if (mq.matches) {
      moveToggleIn();
    } else {
      moveToggleOut();
    }
  }

  mq.addEventListener('change', update);

  update();
});
